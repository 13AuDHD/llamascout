<?php

declare(strict_types=1);

require_once __DIR__ . '/printful-orders.php';
require_once __DIR__ . '/printful-sync.php';


function llama_printful_cancellable_status(
    string $status
): bool {
    $status = strtolower(trim($status));

    /*
     * Printful documents draft and pending as cancellable through the
     * v1 DELETE /orders/{id} endpoint.
     *
     * Archived is intentionally included as an attempted cancellation
     * state because archiving only hides an order in Printful; it does
     * not cancel fulfillment. Llama Scout never changes local state for
     * an archived order unless Printful itself confirms "canceled".
     */
    return in_array(
        $status,
        [
            'draft',
            'pending',
            'archived',
        ],
        true
    );
}


function llama_printful_active_fulfillments(
    PDO $db,
    int $limit = 200
): array {
    $limit = max(
        1,
        min(500, $limit)
    );

    $stmt = $db->query(
        'SELECT
            f.*,
            o.order_number,
            o.user_id,
            o.customer_email,
            o.payment_status,
            o.order_status
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE LOWER(
            COALESCE(
                f.fulfillment_provider,
                ""
            )
         ) = "printful"
           AND f.provider_order_id IS NOT NULL
           AND f.provider_order_id <> ""
         ORDER BY
            f.created_at DESC,
            f.id DESC
         LIMIT ' . $limit
    );

    return $stmt
        ? (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
        )
        : [];
}


function llama_printful_cancel_fulfillment(
    PDO $db,
    int $fulfillmentId,
    int $actorUserId
): array {
    if ($fulfillmentId < 1) {
        throw new InvalidArgumentException(
            'A valid Printful fulfillment is required.'
        );
    }

    /*
     * Cancellation is an external provider action. Serialize it per
     * fulfillment so a double tap cannot issue two DELETE requests.
     */
    $lockName =
        'llamascout_printful_cancel_' .
        $fulfillmentId;

    $lockStmt = $db->prepare(
        'SELECT GET_LOCK(?, 10)'
    );

    $lockStmt->execute([
        $lockName,
    ]);

    if ((int) $lockStmt->fetchColumn() !== 1) {
        throw new RuntimeException(
            'Could not acquire the Printful cancellation lock.'
        );
    }

    try {
        $stmt = $db->prepare(
            'SELECT
                f.*,
                o.order_number,
                o.user_id,
                o.customer_email,
                o.payment_status,
                o.order_status
             FROM shop_order_fulfillments f
             INNER JOIN shop_orders o
                ON o.id = f.order_id
             WHERE f.id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $fulfillmentId,
        ]);

        $fulfillment =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fulfillment) {
            throw new InvalidArgumentException(
                'Printful fulfillment not found.'
            );
        }

        $provider = strtolower(
            trim(
                (string) (
                    $fulfillment[
                        'fulfillment_provider'
                    ]
                    ?? ''
                )
            )
        );

        if ($provider !== 'printful') {
            throw new InvalidArgumentException(
                'This fulfillment is not assigned to Printful.'
            );
        }

        $providerOrderId = trim(
            (string) (
                $fulfillment[
                    'provider_order_id'
                ]
                ?? ''
            )
        );

        if ($providerOrderId === '') {
            throw new InvalidArgumentException(
                'This fulfillment does not have a Printful order ID.'
            );
        }

        $localStatus = strtolower(
            trim(
                (string) (
                    $fulfillment['status']
                    ?? ''
                )
            )
        );

        if (
            in_array(
                $localStatus,
                [
                    'shipped',
                    'delivered',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'This fulfillment has already shipped. Use the return workflow instead of cancelling it.'
            );
        }

        $remoteOrder =
            llama_printful_get_order(
                $providerOrderId
            );

        $remoteStatus = strtolower(
            trim(
                (string) (
                    $remoteOrder['status']
                    ?? ''
                )
            )
        );

        if (
            in_array(
                $remoteStatus,
                [
                    'canceled',
                    'cancelled',
                ],
                true
            )
        ) {
            /*
             * Provider is already cancelled. Reconcile local state
             * instead of sending a duplicate DELETE request.
             */
        } elseif (
            !llama_printful_cancellable_status(
                $remoteStatus
            )
        ) {
            if ($remoteStatus === 'inreview') {
                throw new InvalidArgumentException(
                    'Printful is reviewing this order right now and does not allow cancellation in that state. Refresh the order after the review finishes.'
                );
            }

            if (
                in_array(
                    $remoteStatus,
                    [
                        'inprocess',
                        'partial',
                        'fulfilled',
                    ],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Printful has already started fulfillment for order #' .
                    $providerOrderId .
                    '. It can no longer be cancelled through the API. If merchandise ships, use the return workflow before refunding the customer.'
                );
            }

            if ($remoteStatus === 'onhold') {
                throw new InvalidArgumentException(
                    'Printful has this order on hold. Resolve the hold with Printful before cancelling or refunding the customer.'
                );
            }

            throw new InvalidArgumentException(
                'Printful order #' .
                $providerOrderId .
                ' is currently ' .
                (
                    $remoteStatus !== ''
                        ? $remoteStatus
                        : 'in an unknown state'
                ) .
                ' and cannot be safely cancelled through the API.'
            );
        } else {
            try {
                $response =
                    llama_printful_request(
                        'DELETE',
                        'orders/' .
                        rawurlencode(
                            $providerOrderId
                        )
                    );
            } catch (Throwable $exception) {
                if ($remoteStatus === 'archived') {
                    throw new InvalidArgumentException(
                        'This Printful order is archived, not cancelled. Printful refused the cancellation request while it is archived. Unarchive the order in Printful, then return here and click Cancel at Printful again.',
                        0,
                        $exception
                    );
                }

                throw $exception;
            }

            $remoteOrder =
                is_array(
                    $response['result']
                    ?? null
                )
                    ? $response['result']
                    : [];

            $remoteStatus = strtolower(
                trim(
                    (string) (
                        $remoteOrder['status']
                        ?? ''
                    )
                )
            );

            /*
             * Do not trust a successful HTTP response by itself. Local
             * cancellation is committed only after Printful explicitly
             * confirms the order is canceled.
             */
            if (
                !in_array(
                    $remoteStatus,
                    [
                        'canceled',
                        'cancelled',
                    ],
                    true
                )
            ) {
                try {
                    $confirmed =
                        llama_printful_get_order(
                            $providerOrderId
                        );

                    $confirmedStatus = strtolower(
                        trim(
                            (string) (
                                $confirmed['status']
                                ?? ''
                            )
                        )
                    );

                    if ($confirmedStatus !== '') {
                        $remoteStatus =
                            $confirmedStatus;
                        $remoteOrder =
                            $confirmed;
                    }
                } catch (Throwable) {
                    // The DELETE response remains the authority below.
                }
            }

            if (
                !in_array(
                    $remoteStatus,
                    [
                        'canceled',
                        'cancelled',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Printful accepted the cancellation request but did not confirm a canceled order state. No local cancellation was recorded.'
                );
            }
        }

        $db->beginTransaction();

        try {
            $updateFulfillment =
                $db->prepare(
                    'UPDATE shop_order_fulfillments
                     SET
                        status = "cancelled",
                        tracking_number = NULL,
                        tracking_carrier = NULL,
                        tracking_url = NULL,
                        shipped_at = NULL,
                        delivered_at = NULL,
                        updated_at = UTC_TIMESTAMP()
                     WHERE id = ?'
                );

            $updateFulfillment->execute([
                $fulfillmentId,
            ]);

            /*
             * Keep payment and fulfillment truth separate. Cancelling
             * Printful does not refund the customer. The normal order
             * status synchronizer will leave a fully-cancelled paid
             * order in Problem until the Stripe refund succeeds.
             */
            if (
                function_exists(
                    'admin_fulfillment_sync_order_status'
                )
            ) {
                admin_fulfillment_sync_order_status(
                    $db,
                    (int) $fulfillment[
                        'order_id'
                    ]
                );
            }

            $paymentStatus = strtolower(
                trim(
                    (string) (
                        $fulfillment[
                            'payment_status'
                        ]
                        ?? ''
                    )
                )
            );

            if (
                $actorUserId > 0
                && function_exists(
                    'admin_users_audit'
                )
            ) {
                admin_users_audit(
                    $db,
                    $actorUserId,
                    !empty(
                        $fulfillment['user_id']
                    )
                        ? (int) $fulfillment[
                            'user_id'
                        ]
                        : null,
                    'shop.printful_order_cancelled',
                    'Cancelled Printful order #' .
                        $providerOrderId .
                        ' for Llama Scout order ' .
                        (string) $fulfillment[
                            'order_number'
                        ] .
                        '.',
                    [
                        'order_id' =>
                            (int) $fulfillment[
                                'order_id'
                            ],
                        'fulfillment_id' =>
                            $fulfillmentId,
                        'printful_order_id' =>
                            $providerOrderId,
                        'remote_status' =>
                            $remoteStatus,
                        'customer_payment_status' =>
                            $paymentStatus,
                        'customer_refund_required' =>
                            $paymentStatus === 'paid',
                    ]
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'fulfillment_id' =>
                $fulfillmentId,
            'order_id' =>
                (int) $fulfillment[
                    'order_id'
                ],
            'order_number' =>
                (string) $fulfillment[
                    'order_number'
                ],
            'provider_order_id' =>
                $providerOrderId,
            'remote_status' =>
                $remoteStatus,
            'refund_required' =>
                strtolower(
                    trim(
                        (string) (
                            $fulfillment[
                                'payment_status'
                            ]
                            ?? ''
                        )
                    )
                ) === 'paid',
        ];
    } finally {
        try {
            $releaseStmt = $db->prepare(
                'SELECT RELEASE_LOCK(?)'
            );

            $releaseStmt->execute([
                $lockName,
            ]);
        } catch (Throwable) {
            // Connection cleanup releases the advisory lock.
        }
    }
}

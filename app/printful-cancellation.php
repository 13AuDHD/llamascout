<?php

declare(strict_types=1);

require_once __DIR__ . '/printful-orders.php';
require_once __DIR__ . '/printful-sync.php';


function llama_printful_cancellable_status(
    string $status
): bool {
    return in_array(
        strtolower(trim($status)),
        [
            'draft',
            'pending',
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
        throw new InvalidArgumentException(
            'Printful order #'
            . $providerOrderId
            . ' is currently '
            . (
                $remoteStatus !== ''
                    ? $remoteStatus
                    : 'in an unknown state'
            )
            . ' and cannot be cancelled through the API.'
        );
    } else {
        $response =
            llama_printful_request(
                'DELETE',
                'orders/'
                . rawurlencode(
                    $providerOrderId
                )
            );

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
                'Printful accepted the cancellation request but did not confirm a cancelled order state.'
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
                    updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );

        $updateFulfillment->execute([
            $fulfillmentId,
        ]);

        /*
         * Printful cancellation refunds only Llama Scout's
         * provider charge. It does not refund the customer.
         *
         * A paid customer order therefore moves to Problem until
         * the existing Stripe refund workflow is completed.
         */
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

        if ($paymentStatus === 'paid') {
            $updateOrder =
                $db->prepare(
                    'UPDATE shop_orders
                     SET
                        order_status = "problem",
                        updated_at = UTC_TIMESTAMP()
                     WHERE id = ?'
                );

            $updateOrder->execute([
                (int) $fulfillment[
                    'order_id'
                ],
            ]);
        } elseif (
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
                'Cancelled Printful order #'
                    . $providerOrderId
                    . ' for Llama Scout order '
                    . (string) $fulfillment[
                        'order_number'
                    ]
                    . '.',
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
}

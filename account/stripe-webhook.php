<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once dirname(__DIR__) . '/app/stripe-webhook-events.php';
require_once dirname(__DIR__) . '/app/promotion-events.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';


/* =========================================================
   LLAMA SCOUT MEMBERSHIP STRIPE WEBHOOK

   Public endpoint:
   https://account.llamascout.com/stripe-webhook.php

   Stripe signature verification happens before any event is
   claimed or any application state is changed.
   ========================================================= */

header(
    'Content-Type: application/json; charset=utf-8'
);
header(
    'Cache-Control: no-store, max-age=0'
);


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);

    echo json_encode([
        'received' => false,
        'error' => 'Method not allowed.',
    ]);

    exit;
}


try {
    $payload =
        llama_stripe_webhook_payload();

    $signature =
        llama_stripe_webhook_signature();

    /*
     * Loading the secret also loads Stripe's PHP library.
     */
    $webhookSecret =
        llama_stripe_webhook_secret();

    $event =
        \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );
} catch (LengthException $exception) {
    http_response_code(413);

    echo json_encode([
        'received' => false,
        'error' => 'Payload too large.',
    ]);

    exit;
} catch (
    \Stripe\Exception\SignatureVerificationException
    $exception
) {
    http_response_code(400);

    error_log(
        'Llama Scout Stripe webhook signature error: ' .
        $exception->getMessage()
    );

    echo json_encode([
        'received' => false,
        'error' => 'Invalid signature.',
    ]);

    exit;
} catch (UnexpectedValueException $exception) {
    http_response_code(400);

    error_log(
        'Llama Scout Stripe webhook invalid payload: ' .
        $exception->getMessage()
    );

    echo json_encode([
        'received' => false,
        'error' => 'Invalid payload.',
    ]);

    exit;
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'stripe_webhook_setup',
        []
    );

    http_response_code(500);

    echo json_encode([
        'received' => false,
        'error' => 'Webhook configuration error.',
    ]);

    exit;
}


try {
    $identity =
        llama_stripe_webhook_event_identity(
            $event
        );

    $eventId =
        $identity['id'];

    $eventType =
        $identity['type'];

    $allowedEventTypes = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'invoice.payment_failed',
    ];

    /*
     * Ignore signed Stripe events that this endpoint does not own.
     * They are not inserted into the local processing ledger.
     */
    if (
        !in_array(
            $eventType,
            $allowedEventTypes,
            true
        )
    ) {
        http_response_code(200);

        echo json_encode([
            'received' => true,
            'ignored' => true,
        ]);

        exit;
    }

    $db = db();
    $object =
        $event->data->object
        ?? null;

    if (!is_object($object)) {
        throw new UnexpectedValueException(
            'Stripe webhook event object is missing.'
        );
    }

    /*
     * Checkout event destinations can overlap. Shop Checkout has its
     * own independently signed endpoint and processing ledger.
     */
    if (
        in_array(
            $eventType,
            [
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded',
            ],
            true
        )
    ) {
        $checkoutType =
            strtolower(
                trim(
                    (string) (
                        $object
                            ->metadata
                            ->llama_checkout_type
                        ?? ''
                    )
                )
            );

        if ($checkoutType === 'shop') {
            http_response_code(200);

            echo json_encode([
                'received' => true,
                'ignored' => true,
                'owner' => 'shop',
            ]);

            exit;
        }

        /*
         * Membership checkout created by Llama Scout always has
         * membership metadata. This prevents an unrelated Checkout
         * Session in the same Stripe account from becoming a
         * permanent webhook failure.
         */
        $metadataUserId =
            (int) (
                $object
                    ->metadata
                    ->llama_user_id
                ?? 0
            );

        $membershipPlanId =
            trim(
                (string) (
                    $object
                        ->metadata
                        ->membership_plan_id
                    ?? ''
                )
            );

        $membershipInterval =
            strtolower(
                trim(
                    (string) (
                        $object
                            ->metadata
                            ->membership_interval
                        ?? ''
                    )
                )
            );

        $isMembershipCheckout =
            $metadataUserId > 0
            || $membershipPlanId !== ''
            || in_array(
                $membershipInterval,
                [
                    'monthly',
                    'annual',
                ],
                true
            );

        if (!$isMembershipCheckout) {
            http_response_code(200);

            echo json_encode([
                'received' => true,
                'ignored' => true,
                'owner' => 'other_checkout',
            ]);

            exit;
        }
    }

    if (
        !llama_membership_stripe_event_claim(
            $db,
            $eventId,
            $eventType
        )
    ) {
        http_response_code(200);

        echo json_encode([
            'received' => true,
            'duplicate' => true,
        ]);

        exit;
    }

    try {
        switch ($eventType) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $session = $object;

                /*
                 * A completed Checkout Session can precede settlement
                 * for an asynchronous payment method. Do not treat an
                 * unpaid completed Session as a membership purchase.
                 * Stripe will later send async_payment_succeeded,
                 * subscription, or invoice events when state changes.
                 */
                $paymentStatus =
                    strtolower(
                        trim(
                            (string) (
                                $session->payment_status
                                ?? ''
                            )
                        )
                    );

                if (
                    $eventType
                        === 'checkout.session.completed'
                    && !in_array(
                        $paymentStatus,
                        [
                            'paid',
                            'no_payment_required',
                        ],
                        true
                    )
                ) {
                    llama_membership_stripe_event_finish(
                        $db,
                        $eventId,
                        'processed'
                    );

                    http_response_code(200);

                    echo json_encode([
                        'received' => true,
                        'deferred' => true,
                    ]);

                    exit;
                }

                $userId =
                    (int) (
                        $session
                            ->client_reference_id
                        ?? $session
                            ->metadata
                            ->llama_user_id
                        ?? 0
                    );

                $subscriptionId =
                    trim(
                        (string) (
                            $session
                                ->subscription
                            ?? ''
                        )
                    );

                if (
                    $userId < 1
                    || $subscriptionId === ''
                ) {
                    throw new RuntimeException(
                        'Completed Checkout Session is missing the Llama Scout user or subscription.'
                    );
                }

                $subscription =
                    llama_stripe_client()
                        ->subscriptions
                        ->retrieve(
                            $subscriptionId,
                            []
                        );

                if (
                    !llama_stripe_subscription_is_llama_scout(
                        $db,
                        $subscription
                    )
                ) {
                    throw new RuntimeException(
                        'Completed Checkout Session subscription is not linked to Llama Scout.'
                    );
                }

                llama_sync_stripe_subscription(
                    $db,
                    $subscription,
                    $userId
                );

                $promotionId =
                    (int) (
                        $session
                            ->metadata
                            ->membership_promotion_id
                        ?? 0
                    );

                $interval =
                    strtolower(
                        trim(
                            (string) (
                                $session
                                    ->metadata
                                    ->membership_interval
                                ?? ''
                            )
                        )
                    );

                $sessionId =
                    trim(
                        (string) (
                            $session->id
                            ?? ''
                        )
                    );

                $amountTotal =
                    isset(
                        $session->amount_total
                    )
                        ? (int)
                          $session->amount_total
                        : null;

                if ($promotionId > 0) {
                    llama_membership_promotion_event(
                        $db,
                        $promotionId,
                        'membership_purchased',
                        $userId,
                        $interval,
                        $sessionId,
                        $subscriptionId,
                        $amountTotal,
                        [
                            'stripe_event_id' =>
                                $eventId,
                            'payment_status' =>
                                $paymentStatus,
                        ]
                    );
                }

                if ($sessionId !== '') {
                    $completedSession =
                        llama_stripe_client()
                            ->checkout
                            ->sessions
                            ->retrieve(
                                $sessionId,
                                [
                                    'expand' => [
                                        'discounts',
                                    ],
                                ]
                            );

                    $amountTotal =
                        isset(
                            $completedSession
                                ->amount_total
                        )
                            ? (int)
                              $completedSession
                                  ->amount_total
                            : $amountTotal;

                    foreach (
                        (array) (
                            $completedSession
                                ->discounts
                            ?? []
                        )
                        as $discount
                    ) {
                        if (!is_object($discount)) {
                            continue;
                        }

                        $promotionCode =
                            $discount
                                ->promotion_code
                            ?? null;

                        $stripePromotionCodeId = '';

                        if (
                            is_string(
                                $promotionCode
                            )
                        ) {
                            $stripePromotionCodeId =
                                trim(
                                    $promotionCode
                                );
                        } elseif (
                            is_object(
                                $promotionCode
                            )
                            && isset(
                                $promotionCode->id
                            )
                        ) {
                            $stripePromotionCodeId =
                                trim(
                                    (string)
                                    $promotionCode->id
                                );
                        }

                        if (
                            $stripePromotionCodeId
                            === ''
                        ) {
                            continue;
                        }

                        llama_record_membership_promotion_code_redemption(
                            $db,
                            $stripePromotionCodeId,
                            $userId,
                            $interval,
                            $sessionId,
                            $subscriptionId,
                            $amountTotal
                        );
                    }
                }

                break;


            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $eventSubscription =
                    $object;

                if (
                    !llama_stripe_subscription_is_llama_scout(
                        $db,
                        $eventSubscription
                    )
                ) {
                    llama_membership_stripe_event_finish(
                        $db,
                        $eventId,
                        'ignored'
                    );

                    http_response_code(200);

                    echo json_encode([
                        'received' => true,
                        'ignored' => true,
                        'owner' => 'other_subscription',
                    ]);

                    exit;
                }

                $subscriptionId =
                    trim(
                        (string) (
                            $eventSubscription->id
                            ?? ''
                        )
                    );

                if ($subscriptionId === '') {
                    throw new RuntimeException(
                        'Stripe subscription event is missing its subscription ID.'
                    );
                }

                /*
                 * Stripe does not guarantee webhook ordering.
                 * Re-fetch current subscription state so an older
                 * delivery cannot overwrite a newer local state.
                 */
                try {
                    $subscription =
                        llama_stripe_client()
                            ->subscriptions
                            ->retrieve(
                                $subscriptionId,
                                []
                            );
                } catch (Throwable $refreshException) {
                    if (
                        $eventType
                        !== 'customer.subscription.deleted'
                    ) {
                        throw $refreshException;
                    }

                    /*
                     * A deleted/canceled subscription event itself is
                     * authoritative if Stripe no longer returns it.
                     */
                    $subscription =
                        $eventSubscription;
                }

                llama_sync_stripe_subscription(
                    $db,
                    $subscription
                );

                break;


            case 'invoice.paid':
            case 'invoice.payment_failed':
                $invoice = $object;

                $subscriptionId =
                    trim(
                        (string) (
                            $invoice
                                ->subscription
                            ?? $invoice
                                ->parent
                                ->subscription_details
                                ->subscription
                            ?? ''
                        )
                    );

                if ($subscriptionId === '') {
                    /*
                     * A Stripe invoice without a subscription is not
                     * a Llama Scout membership invoice.
                     */
                    llama_membership_stripe_event_finish(
                        $db,
                        $eventId,
                        'ignored'
                    );

                    http_response_code(200);

                    echo json_encode([
                        'received' => true,
                        'ignored' => true,
                        'owner' => 'non_subscription_invoice',
                    ]);

                    exit;
                }

                $subscription =
                    llama_stripe_client()
                        ->subscriptions
                        ->retrieve(
                            $subscriptionId,
                            []
                        );

                if (
                    !llama_stripe_subscription_is_llama_scout(
                        $db,
                        $subscription
                    )
                ) {
                    llama_membership_stripe_event_finish(
                        $db,
                        $eventId,
                        'ignored'
                    );

                    http_response_code(200);

                    echo json_encode([
                        'received' => true,
                        'ignored' => true,
                        'owner' => 'other_subscription',
                    ]);

                    exit;
                }

                llama_sync_stripe_subscription(
                    $db,
                    $subscription
                );

                break;
        }

        llama_membership_stripe_event_finish(
            $db,
            $eventId,
            'processed'
        );
    } catch (Throwable $exception) {
        llama_membership_stripe_event_finish(
            $db,
            $eventId,
            'failed',
            $exception->getMessage()
        );

        throw $exception;
    }

    http_response_code(200);

    echo json_encode([
        'received' => true,
        'processed' => true,
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'stripe_webhook_processing',
        [
            'event_type' =>
                $eventType
                ?? null,
            'event_id' =>
                $eventId
                ?? null,
        ]
    );

    /*
     * Returning 500 asks Stripe to retry.
     */
    http_response_code(500);

    echo json_encode([
        'received' => false,
        'error' => 'Webhook processing failed.',
    ]);
}

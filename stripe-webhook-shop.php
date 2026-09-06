<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/shop-checkout.php';
require_once __DIR__ . '/app/shop-fulfillment-routing.php';
require_once __DIR__ . '/app/shop-order-mail.php';
require_once __DIR__ . '/app/shop-refunds.php';
require_once __DIR__ . '/app/stripe-webhook-events.php';


/* =========================================================
   LLAMA SCOUT SHOP STRIPE WEBHOOK
   ========================================================= */

header(
    'Content-Type: text/plain; charset=utf-8'
);
header(
    'Cache-Control: no-store, max-age=0'
);


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);

    echo 'method not allowed';

    exit;
}


try {
    $payload =
        llama_stripe_webhook_payload();

    $signature =
        llama_stripe_webhook_signature();

    $webhookSecret =
        shop_checkout_webhook_secret();

    $event =
        \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );
} catch (LengthException $exception) {
    http_response_code(413);

    echo 'payload too large';

    exit;
} catch (
    \Stripe\Exception\SignatureVerificationException
    $exception
) {
    http_response_code(400);

    error_log(
        'Llama Scout Shop Stripe signature error: ' .
        $exception->getMessage()
    );

    echo 'invalid signature';

    exit;
} catch (UnexpectedValueException $exception) {
    http_response_code(400);

    error_log(
        'Llama Scout Shop Stripe payload error: ' .
        $exception->getMessage()
    );

    echo 'invalid payload';

    exit;
} catch (Throwable $exception) {
    if (
        function_exists(
            'llama_log_caught_exception'
        )
    ) {
        llama_log_caught_exception(
            $exception,
            'shop.stripe_webhook_setup'
        );
    }

    http_response_code(500);

    echo 'webhook configuration error';

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
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'refund.created',
        'refund.updated',
        'refund.failed',
    ];

    if (
        !in_array(
            $eventType,
            $allowedEventTypes,
            true
        )
    ) {
        http_response_code(200);

        echo 'ignored';

        exit;
    }

    $object =
        $event->data->object
        ?? null;

    if (!is_object($object)) {
        throw new UnexpectedValueException(
            'Stripe Shop webhook event object is missing.'
        );
    }

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

    $refundEvent =
        in_array(
            $eventType,
            [
                'refund.created',
                'refund.updated',
                'refund.failed',
            ],
            true
        );

    if ($refundEvent) {
        if (
            $checkoutType
            !== 'shop_refund'
        ) {
            http_response_code(200);

            echo 'ignored';

            exit;
        }
    } elseif ($checkoutType !== 'shop') {
        http_response_code(200);

        echo 'ignored';

        exit;
    }

    $orderId =
        (int) (
            $object
                ->metadata
                ->llama_order_id
            ?? 0
        );

    if ($orderId < 1) {
        throw new RuntimeException(
            'Stripe Shop event is missing its order ID.'
        );
    }

    $db = db();

    /*
     * Production checkout schema is deliberate and never created by
     * webhook traffic. This also catches incomplete deployments.
     */
    shop_checkout_ensure_storage(
        $db
    );

    $order =
        shop_checkout_order(
            $db,
            $orderId
        );

    if (!$order) {
        throw new RuntimeException(
            'Stripe Shop event references an order that does not exist.'
        );
    }

    if (!$refundEvent) {
        $sessionId =
            trim(
                (string) (
                    $object->id
                    ?? ''
                )
            );

        $storedSessionId =
            trim(
                (string) (
                    $order[
                        'stripe_checkout_session_id'
                    ]
                    ?? ''
                )
            );

        if (
            $sessionId !== ''
            && $storedSessionId !== ''
            && !hash_equals(
                $storedSessionId,
                $sessionId
            )
        ) {
            throw new RuntimeException(
                'Stripe Checkout Session does not match the Llama Scout Shop order.'
            );
        }

        $metadataOrderNumber =
            trim(
                (string) (
                    $object
                        ->metadata
                        ->llama_order_number
                    ?? ''
                )
            );

        $localOrderNumber =
            trim(
                (string) (
                    $order[
                        'order_number'
                    ]
                    ?? ''
                )
            );

        if (
            $metadataOrderNumber !== ''
            && $localOrderNumber !== ''
            && !hash_equals(
                $localOrderNumber,
                $metadataOrderNumber
            )
        ) {
            throw new RuntimeException(
                'Stripe order number does not match the Llama Scout Shop order.'
            );
        }

        $clientReference =
            trim(
                (string) (
                    $object
                        ->client_reference_id
                    ?? ''
                )
            );

        if (
            $clientReference !== ''
            && $localOrderNumber !== ''
            && !hash_equals(
                $localOrderNumber,
                $clientReference
            )
        ) {
            throw new RuntimeException(
                'Stripe Checkout client reference does not match the Llama Scout Shop order.'
            );
        }
    }

    if (
        !llama_shop_stripe_event_claim(
            $db,
            $eventId,
            $eventType,
            $orderId
        )
    ) {
        http_response_code(200);

        echo 'duplicate';

        exit;
    }

    try {
        if ($refundEvent) {
            shop_sync_refund_from_stripe(
                $db,
                $object
            );
        } elseif (
            in_array(
                $eventType,
                [
                    'checkout.session.completed',
                    'checkout.session.async_payment_succeeded',
                ],
                true
            )
        ) {
            $sessionId =
                trim(
                    (string) (
                        $object->id
                        ?? ''
                    )
                );

            $session =
                $sessionId !== ''
                    ? llama_stripe_client()
                        ->checkout
                        ->sessions
                        ->retrieve(
                            $sessionId,
                            []
                        )
                    : $object;

            shop_checkout_commit_paid_session(
                $db,
                $session
            );

            /*
             * CRITICAL:
             *
             * checkout.session.completed does not always mean money
             * has settled. commit_paid_session deliberately leaves an
             * unpaid order pending. Only route fulfillment and send a
             * paid-order confirmation after local financial state is
             * confirmed as paid.
             */
            $paidOrder =
                shop_checkout_order(
                    $db,
                    $orderId
                );

            $localPaymentStatus =
                strtolower(
                    trim(
                        (string) (
                            $paidOrder[
                                'payment_status'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                $localPaymentStatus
                === 'paid'
            ) {
                shop_fulfillment_route_paid_order(
                    $db,
                    $orderId
                );

                shop_send_order_confirmation(
                    $db,
                    $orderId
                );
            }
        } elseif (
            $eventType
            === 'checkout.session.async_payment_failed'
        ) {
            shop_checkout_mark_failed(
                $db,
                $orderId,
                'failed'
            );
        } elseif (
            $eventType
            === 'checkout.session.expired'
        ) {
            shop_checkout_cancel_pending_order(
                $db,
                $orderId
            );
        }

        llama_shop_stripe_event_finish(
            $db,
            $eventId,
            'processed'
        );
    } catch (Throwable $exception) {
        llama_shop_stripe_event_finish(
            $db,
            $eventId,
            'failed',
            $exception->getMessage()
        );

        throw $exception;
    }

    http_response_code(200);

    echo 'ok';
} catch (Throwable $exception) {
    http_response_code(500);

    if (
        function_exists(
            'llama_log_caught_exception'
        )
    ) {
        llama_log_caught_exception(
            $exception,
            'shop.stripe_webhook',
            [
                'event_type' =>
                    $eventType
                    ?? null,
                'event_id' =>
                    $eventId
                    ?? null,
                'order_id' =>
                    $orderId
                    ?? null,
            ]
        );
    } else {
        error_log(
            'Llama Scout Shop Stripe webhook: ' .
            $exception->getMessage()
        );
    }

    echo 'webhook error';
}

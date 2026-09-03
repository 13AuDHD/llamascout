<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/shop-checkout.php';
require_once __DIR__ . '/app/shop-fulfillment-routing.php';

$db = db();

try {
    shop_checkout_ensure_storage($db);

    $payload = file_get_contents('php://input');
    $signature = trim((string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));

    if (!is_string($payload) || $payload === '' || $signature === '') {
        throw new RuntimeException('Stripe webhook payload or signature is missing.');
    }

    /*
     * Load the Shop webhook secret first. llama_stripe_config()
     * loads Stripe's init.php as part of that call, so Stripe\Webhook
     * exists before PHP resolves the static class reference below.
     */
    $webhookSecret = shop_checkout_webhook_secret();

    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $signature,
        $webhookSecret
    );

    $eventId = trim((string) ($event->id ?? ''));
    $eventType = trim((string) ($event->type ?? ''));
    $object = $event->data->object ?? null;

    if ($eventId === '' || $eventType === '' || !$object) {
        throw new RuntimeException('Stripe webhook event is incomplete.');
    }

    $checkoutType = trim((string) ($object->metadata->llama_checkout_type ?? ''));

    if ($checkoutType !== 'shop') {
        http_response_code(200);
        echo 'ignored';
        exit;
    }

    $orderId = (int) ($object->metadata->llama_order_id ?? 0);
    if ($orderId < 1) {
        throw new RuntimeException('Stripe Shop event is missing its order ID.');
    }

    if (!shop_checkout_record_event($db, $eventId, $eventType, $orderId)) {
        http_response_code(200);
        echo 'duplicate';
        exit;
    }

    try {
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
            $sessionId = trim((string) ($object->id ?? ''));
            $session = $sessionId !== ''
                ? llama_stripe_client()->checkout->sessions->retrieve($sessionId, [])
                : $object;

            shop_checkout_commit_paid_session(
                $db,
                $session
            );

            /*
             * Once payment is committed, automatically split every
             * physical order into provider-specific fulfillments.
             *
             * Example:
             *   Printful shirt -> Printful fulfillment
             *   Bandanna      -> Llama Scout Fulfillment
             *
             * The router is idempotent, so Stripe webhook retries
             * cannot duplicate already-linked order items.
             */
            shop_fulfillment_route_paid_order(
                $db,
                $orderId
            );

        } elseif ($eventType === 'checkout.session.async_payment_failed') {
            shop_checkout_mark_failed($db, $orderId, 'failed');
        } elseif ($eventType === 'checkout.session.expired') {
            shop_checkout_cancel_pending_order($db, $orderId);
        }

        shop_checkout_finish_event($db, $eventId, 'processed');
    } catch (Throwable $exception) {
        shop_checkout_finish_event($db, $eventId, 'failed', $exception->getMessage());
        throw $exception;
    }

    http_response_code(200);
    echo 'ok';
} catch (\Stripe\Exception\SignatureVerificationException $exception) {
    http_response_code(400);
    error_log('Llama Scout Shop Stripe signature error: ' . $exception->getMessage());
    echo 'invalid signature';
} catch (UnexpectedValueException $exception) {
    http_response_code(400);
    error_log('Llama Scout Shop Stripe payload error: ' . $exception->getMessage());
    echo 'invalid payload';
} catch (Throwable $exception) {
    http_response_code(500);

    if (function_exists('llama_log_caught_exception')) {
        llama_log_caught_exception(
            $exception,
            'shop.stripe_webhook',
            ['event_type' => $eventType ?? null, 'order_id' => $orderId ?? null]
        );
    } else {
        error_log('Llama Scout Shop Stripe webhook: ' . $exception->getMessage());
    }

    echo 'webhook error';
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once dirname(__DIR__) . '/app/promotion-events.php';


/*
 * Llama Scout Stripe Webhook
 *
 * Public endpoint:
 * https://account.llamascout.com/stripe-webhook.php
 *
 * Stripe authenticates requests with the webhook signature.
 * This endpoint intentionally does not expose internal errors.
 */


if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    http_response_code(405);

    exit(
        'Method not allowed.'
    );
}


$payload =
    file_get_contents(
        'php://input'
    );


$signature =
    $_SERVER[
        'HTTP_STRIPE_SIGNATURE'
    ]
    ?? '';


if (
    !is_string(
        $payload
    )
    ||
    $payload === ''
) {

    http_response_code(400);

    exit(
        'Missing request body.'
    );
}


if (
    !is_string(
        $signature
    )
    ||
    $signature === ''
) {

    http_response_code(400);

    exit(
        'Missing Stripe signature.'
    );
}


/*
 * IMPORTANT:
 * Load the private webhook secret first.
 * That call also loads Stripe's init.php before
 * PHP tries to resolve Stripe\Webhook.
 */

try {

    $webhookSecret =
        llama_stripe_webhook_secret();


    $event =
        \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );


} catch (
    \UnexpectedValueException
    $exception
) {

    error_log(
        'Llama Scout Stripe webhook invalid payload: ' .
        $exception->getMessage()
    );


    http_response_code(400);

    exit(
        'Invalid payload.'
    );


} catch (
    \Stripe\Exception\SignatureVerificationException
    $exception
) {

    error_log(
        'Llama Scout Stripe webhook signature error: ' .
        $exception->getMessage()
    );


    http_response_code(400);

    exit(
        'Invalid signature.'
    );


} catch (
    Throwable
    $exception
) {

    llama_log_caught_exception(
        $exception,
        'stripe_webhook_setup',
        []
    );


    http_response_code(500);

    exit(
        'Webhook configuration error.'
    );
}


$db = db();


try {

    switch (
        $event->type
    ) {


        /* =================================================
           CHECKOUT COMPLETED
           ================================================= */

        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':

            $session =
                $event
                    ->data
                    ->object;


            /*
             * Shop Checkout events can also be delivered to this
             * membership endpoint when Stripe event destinations
             * overlap. They are processed by /stripe-webhook-shop.php
             * and are not membership failures.
             */
            $checkoutType =
                strtolower(
                    trim(
                        (string) (
                            $session
                                ->metadata
                                ->llama_checkout_type
                            ?? ''
                        )
                    )
                );


            if (
                $checkoutType === 'shop'
            ) {

                break;
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
                ||
                $subscriptionId === ''
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


            llama_sync_stripe_subscription(
                $db,
                $subscription,
                $userId
            );


            /*
             * Campaign attribution is taken from metadata Llama
             * Scout placed on the Checkout Session itself.
             * Stripe's signed webhook is authoritative for the
             * completed conversion.
             */
            $promotionId =
                (int) (
                    $session
                        ->metadata
                        ->membership_promotion_id
                    ?? 0
                );

            if ($promotionId > 0) {
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
                            $session
                                ->id
                            ?? ''
                        )
                    );

                $amountTotal =
                    isset($session->amount_total)
                        ? (int) $session->amount_total
                        : null;

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
                            (string) ($event->id ?? ''),
                        'payment_status' =>
                            (string) ($session->payment_status ?? ''),
                    ]
                );
            }


            break;


        /* =================================================
           SUBSCRIPTION STATE CHANGED
           ================================================= */

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':

            $subscription =
                $event
                    ->data
                    ->object;


            llama_sync_stripe_subscription(
                $db,
                $subscription
            );


            break;


        /* =================================================
           INVOICE PAID / FAILED
           ================================================= */

        case 'invoice.paid':
        case 'invoice.payment_failed':

            $invoice =
                $event
                    ->data
                    ->object;


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


            if (
                $subscriptionId !== ''
            ) {

                $subscription =
                    llama_stripe_client()
                        ->subscriptions
                        ->retrieve(
                            $subscriptionId,
                            []
                        );


                llama_sync_stripe_subscription(
                    $db,
                    $subscription
                );
            }


            break;


        default:

            /*
             * Ignore events we do not currently use.
             * Returning 200 tells Stripe they were received.
             */

            break;
    }


} catch (
    Throwable
    $exception
) {

    llama_log_caught_exception(
        $exception,
        'stripe_webhook_processing',
        [
            'event_type' => (string) ($event->type ?? ''),
            'event_id' => (string) ($event->id ?? ''),
        ]
    );


    /*
     * Return 500 so Stripe retries the event.
     */

    http_response_code(500);

    exit(
        'Webhook processing failed.'
    );
}


http_response_code(200);


header(
    'Content-Type: application/json'
);


echo json_encode([
    'received' => true,
]);

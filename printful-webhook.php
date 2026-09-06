<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/admin-shop.php';
require_once __DIR__ . '/app/admin-fulfillment.php';
require_once __DIR__ . '/app/printful-sync.php';
require_once __DIR__ . '/app/printful-webhook-security.php';
require_once __DIR__ . '/app/shop-order-mail.php';

header(
    'Content-Type: application/json; charset=utf-8'
);
header(
    'Cache-Control: no-store, max-age=0'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ]);

    exit;
}

/*
 * Printful v1 webhooks are not signed. Require the private keyed
 * callback URL configured through Admin instead.
 */
$providedWebhookKey =
    trim(
        (string) (
            $_GET['key']
            ?? ''
        )
    );

if (
    $providedWebhookKey === ''
    || !llama_printful_verify_webhook_key(
        $providedWebhookKey
    )
) {
    /*
     * Return 404 instead of explaining that a valid webhook endpoint
     * exists at this URL.
     */
    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'error' => 'Not found.',
    ]);

    exit;
}

$contentLength =
    (int) (
        $_SERVER['CONTENT_LENGTH']
        ?? 0
    );

if ($contentLength > 262144) {
    http_response_code(413);

    echo json_encode([
        'ok' => false,
        'error' => 'Payload too large.',
    ]);

    exit;
}

try {
    $raw =
        file_get_contents(
            'php://input'
        );

    if (
        !is_string($raw)
        || $raw === ''
        || strlen($raw) > 262144
    ) {
        throw new InvalidArgumentException(
            'Invalid Printful webhook payload.'
        );
    }

    $event =
        json_decode(
            $raw,
            true,
            64,
            JSON_THROW_ON_ERROR
        );

    if (!is_array($event)) {
        throw new InvalidArgumentException(
            'Invalid Printful webhook payload.'
        );
    }

    $type =
        strtolower(
            trim(
                (string) (
                    $event['type']
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $type,
            [
                'package_shipped',
                'package_returned',
                'order_created',
                'order_failed',
                'order_canceled',
                'order_put_hold',
                'order_remove_hold',
            ],
            true
        )
    ) {
        http_response_code(200);

        echo json_encode([
            'ok' => true,
            'ignored' => true,
        ]);

        exit;
    }

    $db = db();

    $result =
        llama_printful_process_webhook(
            $db,
            $event
        );

    try {
        shop_send_pending_shipment_notifications(
            $db,
            10
        );
    } catch (Throwable $mailException) {
        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $mailException,
                'printful.shipment_email'
            );
        }
    }

    http_response_code(200);

    echo json_encode([
        'ok' => true,
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES);

} catch (
    JsonException
    | InvalidArgumentException
    $exception
) {
    http_response_code(200);

    echo json_encode([
        'ok' => false,
        'ignored' => true,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'printful.webhook',
            [],
            [
                InvalidArgumentException::class,
                JsonException::class,
            ]
        );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'reference' => $reference,
    ], JSON_UNESCAPED_SLASHES);
}

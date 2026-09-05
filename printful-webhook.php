<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/admin-shop.php';
require_once __DIR__ . '/app/admin-fulfillment.php';
require_once __DIR__ . '/app/printful-sync.php';
require_once __DIR__ . '/app/shop-order-mail.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ]);

    exit;
}

try {
    $raw = file_get_contents(
        'php://input'
    );

    $event = json_decode(
        (string) $raw,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($event)) {
        throw new InvalidArgumentException(
            'Invalid Printful webhook payload.'
        );
    }

    /*
     * The webhook body itself is not trusted as authoritative.
     * app/printful-sync.php uses it only to identify a known
     * fulfillment, then re-fetches the real state from Printful.
     */
    $db = db();

    $result =
        llama_printful_process_webhook(
            $db,
            $event
        );

    /*
     * If Printful's authoritative refresh moved a package to
     * shipped/delivered, send any newly eligible tracking email.
     *
     * Mail failure must not make Printful retry an otherwise
     * successfully processed fulfillment webhook. Failed emails
     * remain retryable through normal authenticated maintenance.
     */
    try {
        shop_send_pending_shipment_notifications(
            $db,
            10
        );
    } catch (Throwable $mailException) {
        if (function_exists('llama_log_caught_exception')) {
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
    /*
     * Malformed or irrelevant requests should not create an
     * endless Printful retry loop.
     */
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

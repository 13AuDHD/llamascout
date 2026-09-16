<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/admin-shop.php';
require_once __DIR__ . '/app/admin-fulfillment.php';
require_once __DIR__ . '/app/printify-sync.php';
require_once __DIR__ . '/app/printify-webhook-security.php';
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

$providedVersion = trim(
    (string) (
        $_GET['v']
        ?? ''
    )
);

$expectedVersion =
    llama_printify_webhook_version_key();

if (
    $expectedVersion === ''
    || $providedVersion === ''
    || !hash_equals(
        $expectedVersion,
        $providedVersion
    )
) {
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
    $raw = file_get_contents(
        'php://input'
    );

    if (
        !is_string($raw)
        || $raw === ''
        || strlen($raw) > 262144
    ) {
        throw new InvalidArgumentException(
            'Invalid Printify webhook payload.'
        );
    }

    $providedSignature = trim(
        (string) (
            $_SERVER['HTTP_X_PFY_SIGNATURE']
            ?? ''
        )
    );

    if (
        !llama_printify_verify_webhook_signature(
            $raw,
            $providedSignature
        )
    ) {
        /*
         * Do not parse or act on an unauthenticated payload.
         */
        http_response_code(401);

        echo json_encode([
            'ok' => false,
            'error' => 'Invalid webhook signature.',
        ]);

        exit;
    }

    $event = json_decode(
        $raw,
        true,
        64,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($event)) {
        throw new InvalidArgumentException(
            'Invalid Printify webhook payload.'
        );
    }

    $type = strtolower(
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
            llama_printify_webhook_topics(),
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
        llama_printify_process_webhook(
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
                'printify.shipment_email'
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
            'printify.webhook',
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

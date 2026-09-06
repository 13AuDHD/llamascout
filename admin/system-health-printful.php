<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printful.php';
require_once dirname(__DIR__) . '/app/printful-webhook-security.php';
require_once __DIR__ . '/_dashboard.php';

moderation_require_admin();

header(
    'Content-Type: application/json; charset=utf-8'
);
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
header(
    'Pragma: no-cache'
);

$status = 'down';
$value = 'Unavailable';
$detail = 'Printful webhook security could not be verified.';

try {
    if (!llama_printful_configured()) {
        $status = 'down';
        $value = 'Not configured';
        $detail =
            'The private Printful API token is missing. '
            . 'Printful fulfillment and webhook verification cannot operate.';
    } else {
        $secureActive =
            llama_printful_secure_webhook_active();

        if ($secureActive) {
            $status = 'good';
            $value = 'Protected';
            $detail =
                'Printful is reachable and its configured webhook '
                . 'matches the protected keyed Llama Scout callback.';
        } else {
            $status = 'attention';
            $value = 'Needs protection';
            $detail =
                'Printful is connected, but its configured webhook '
                . 'does not match the protected Llama Scout callback. '
                . 'Open Integrations > Printful Webhook and activate protection.';
        }
    }
} catch (Throwable $exception) {
    $status = 'down';
    $value = 'Check failed';
    $detail =
        'Llama Scout could not verify the Printful webhook with Printful. '
        . 'Check the Printful connection in Integrations.';

    if (
        function_exists(
            'llama_log_caught_exception'
        )
    ) {
        llama_log_caught_exception(
            $exception,
            'admin.system_health.printful_webhook'
        );
    }
}

echo json_encode(
    [
        'ok' => true,
        'card' => [
            'key' => 'printful_webhook',
            'label' => 'Printful webhook',
            'status' => $status,
            'value' => $value,
            'detail' => $detail,
            'icon' => 'fa-shield-halved',
        ],
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

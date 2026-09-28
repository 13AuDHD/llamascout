<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-v2.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');

function admin_cell_v2_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    moderation_require_admin();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        admin_cell_v2_json([
            'ok' => false,
            'error' => 'POST is required.',
        ], 405);
    }

    if (!moderation_verify_csrf(
        (string) ($_POST['csrf_token'] ?? '')
    )) {
        admin_cell_v2_json([
            'ok' => false,
            'error' => 'Your session token expired. Reload and try again.',
        ], 403);
    }

    $action = strtolower(trim(
        (string) ($_POST['action'] ?? 'status')
    ));

    if (!llama_fcc_v2_tables_ready()) {
        admin_cell_v2_json([
            'ok' => false,
            'error' => 'Cell Coverage V2 database tables are not installed yet.',
        ], 503);
    }

    if ($action === 'latest') {
        $check = llama_fcc_v2_check_latest();

        admin_cell_v2_json([
            'ok' => true,
            'check' => $check,
            'snapshot' => llama_fcc_v2_snapshot(),
        ]);
    }

    if ($action === 'toggle_banner') {
        $enabled = (string) ($_POST['enabled'] ?? '0') === '1';
        llama_fcc_v2_set_banner_enabled($enabled);

        llama_fcc_v2_log_event(
            null,
            null,
            'info',
            'map_banner_changed',
            $enabled
                ? 'Public map coverage-update banner enabled.'
                : 'Public map coverage-update banner disabled.'
        );

        admin_cell_v2_json([
            'ok' => true,
            'snapshot' => llama_fcc_v2_snapshot(),
        ]);
    }

    if ($action === 'sync' || $action === 'check_errors') {
        admin_cell_v2_json([
            'ok' => false,
            'error' => 'The V2 background worker engine is not installed in this foundation package yet.',
        ], 409);
    }

    admin_cell_v2_json([
        'ok' => true,
        'snapshot' => llama_fcc_v2_snapshot(),
    ]);

} catch (Throwable $e) {
    llama_log_caught_exception(
        $e,
        'admin.cell_coverage_v2_api'
    );

    admin_cell_v2_json([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}

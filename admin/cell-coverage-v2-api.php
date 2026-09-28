<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-v2.php';
require_once dirname(__DIR__) . '/app/fcc-cell-v2-worker.php';

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

function admin_cell_v2_background_finisher(): ?string
{
    if (function_exists('litespeed_finish_request')) {
        return 'litespeed_finish_request';
    }

    if (function_exists('fastcgi_finish_request')) {
        return 'fastcgi_finish_request';
    }

    return null;
}

function admin_cell_v2_finish_response(
    array $payload,
    string $finisher
): void {
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        throw new RuntimeException(
            'The Cell Coverage V2 response could not be encoded.'
        );
    }

    http_response_code(200);
    ignore_user_abort(true);
    @set_time_limit(0);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
    }

    echo $json;

    while (ob_get_level() > 0) {
        if (!@ob_end_flush()) {
            break;
        }
    }

    flush();

    if ($finisher === 'litespeed_finish_request') {
        litespeed_finish_request();
        return;
    }

    fastcgi_finish_request();
}

try {
    $adminUser = moderation_require_admin();
    $adminUserId = (int) ($adminUser['id'] ?? 0);

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

    if ($action === 'sync') {
        $result = llama_fcc_v2_create_run($adminUserId);

        admin_cell_v2_json([
            'ok' => true,
            'run_result' => $result,
            'snapshot' => llama_fcc_v2_snapshot(),
        ]);
    }

    if ($action === 'check_errors') {
        $result = llama_fcc_v2_recover_errors($adminUserId);

        admin_cell_v2_json([
            'ok' => true,
            'recovery' => $result,
            'snapshot' => llama_fcc_v2_snapshot(),
        ]);
    }

    if ($action === 'worker') {
        $runId = max(0, (int) ($_POST['run_id'] ?? 0));
        $role = strtolower(trim((string) ($_POST['role'] ?? '')));
        $workerSlot = max(0, (int) ($_POST['worker_slot'] ?? 0));

        if ($runId <= 0) {
            admin_cell_v2_json([
                'ok' => false,
                'error' => 'A valid V2 run ID is required.',
            ], 400);
        }

        if (!in_array($role, llama_fcc_v2_worker_roles(), true)) {
            admin_cell_v2_json([
                'ok' => false,
                'error' => 'Unknown V2 worker role.',
            ], 400);
        }

        if (
            $workerSlot < 1
            || $workerSlot > llama_fcc_v2_worker_limit($role)
        ) {
            admin_cell_v2_json([
                'ok' => false,
                'error' => 'Invalid V2 worker slot.',
            ], 400);
        }

        $run = llama_fcc_v2_run_row($runId);

        if (!$run || (string) ($run['status'] ?? '') !== 'running') {
            admin_cell_v2_json([
                'ok' => true,
                'worker_started' => false,
                'snapshot' => llama_fcc_v2_snapshot(),
            ]);
        }

        $finisher = admin_cell_v2_background_finisher();

        if ($finisher === null) {
            admin_cell_v2_json([
                'ok' => false,
                'error' => 'This server does not expose a supported background-response finisher.',
            ], 500);
        }

        $lockPath = llama_fcc_v2_worker_lock_path(
            $runId,
            $role,
            $workerSlot
        );
        $lock = fopen($lockPath, 'c');

        if (!$lock) {
            throw new RuntimeException(
                'The V2 worker lock could not be opened.'
            );
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            admin_cell_v2_json([
                'ok' => true,
                'worker_started' => false,
                'already_active' => true,
                'snapshot' => llama_fcc_v2_snapshot(),
            ]);
        }

        llama_fcc_v2_worker_touch(
            $runId,
            $role,
            $workerSlot,
            'starting',
            null,
            'Worker launch accepted.'
        );

        admin_cell_v2_finish_response([
            'ok' => true,
            'worker_started' => true,
            'run_id' => $runId,
            'role' => $role,
            'worker_slot' => $workerSlot,
        ], $finisher);

        try {
            llama_fcc_v2_run_worker(
                $runId,
                $role,
                $workerSlot
            );
        } catch (Throwable $workerError) {
            llama_log_caught_exception(
                $workerError,
                'admin.cell_coverage_v2_worker',
                [
                    'run_id' => $runId,
                    'role' => $role,
                    'worker_slot' => $workerSlot,
                ]
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        exit;
    }

    $latestRun = llama_fcc_v2_latest_run();
    if ($latestRun) {
        llama_fcc_v2_refresh_run_counts(
            (int) $latestRun['id']
        );
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

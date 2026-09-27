<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-sync.php';
require_once dirname(__DIR__) . '/app/fcc-cell-download-chunks.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');


function admin_cell_sync_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


function admin_cell_sync_public_state(
    ?array $state
): array {
    $public =
        llama_fcc_sync_public_state(
            $state
        );

    if (is_array($state)) {
        $public['downloaded_bytes'] =
            max(
                0,
                (int) (
                    $state['downloaded_bytes']
                    ?? 0
                )
            );

        $public['download_total_bytes'] =
            max(
                0,
                (int) (
                    $state['download_total_bytes']
                    ?? 0
                )
            );
    }

    return $public;
}


try {
    moderation_require_admin();

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !== 'POST'
    ) {
        admin_cell_sync_json(
            [
                'ok' => false,
                'error' =>
                    'POST is required.',
            ],
            405
        );
    }

    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        admin_cell_sync_json(
            [
                'ok' => false,
                'error' =>
                    'Your session token expired. Reload and try again.',
            ],
            403
        );
    }

    $action =
        strtolower(
            trim(
                (string) (
                    $_POST['action']
                    ?? 'status'
                )
            )
        );

    if ($action === 'plan') {
        $previous =
            llama_fcc_sync_load_state();

        llama_fcc_chunk_cleanup_state(
            $previous
        );

        llama_fcc_sync_reset_state();

        $state =
            llama_fcc_sync_create_plan();

    } elseif ($action === 'resume') {
        $state =
            llama_fcc_sync_resume();

    } elseif ($action === 'step') {
        $state =
            llama_fcc_chunked_download_step();

    } elseif ($action === 'status') {
        $state =
            llama_fcc_sync_load_state();

    } elseif ($action === 'reset') {
        $previous =
            llama_fcc_sync_load_state();

        llama_fcc_chunk_cleanup_state(
            $previous
        );

        $state =
            llama_fcc_sync_reset_state();

    } else {
        throw new InvalidArgumentException(
            'Unknown FCC sync action.'
        );
    }

    admin_cell_sync_json(
        [
            'ok' => true,
            'sync' =>
                admin_cell_sync_public_state(
                    is_array($state)
                        ? $state
                        : null
                ),
        ]
    );

} catch (Throwable $e) {
    $reference =
        llama_log_caught_exception(
            $e,
            'admin.cell_coverage_sync'
        );

    admin_cell_sync_json(
        [
            'ok' => false,
            'error' =>
                $e instanceof
                    InvalidArgumentException
                    ? $e->getMessage()
                    : llama_error_message_with_reference(
                        'The FCC cell coverage sync failed.',
                        $reference
                    ),
            'reference' =>
                $reference,
        ],
        500
    );
}

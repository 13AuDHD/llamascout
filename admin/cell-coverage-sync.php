<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-sync.php';

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

    $state =
        match ($action) {
            'plan' =>
                llama_fcc_sync_create_plan(),

            'resume' =>
                llama_fcc_sync_resume(),

            'step' =>
                llama_fcc_sync_step(),

            'status' =>
                llama_fcc_sync_load_state(),

            'reset' =>
                llama_fcc_sync_reset_state(),

            default =>
                throw new InvalidArgumentException(
                    'Unknown FCC sync action.'
                ),
        };

    admin_cell_sync_json(
        [
            'ok' => true,
            'sync' =>
                llama_fcc_sync_public_state(
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

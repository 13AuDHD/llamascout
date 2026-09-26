<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/cell-coverage-import.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');


function admin_cell_import_json(
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
        admin_cell_import_json(
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
        admin_cell_import_json(
            [
                'ok' => false,
                'error' =>
                    'Your session token expired. Reload and try again.',
            ],
            403
        );
    }

    $result =
        llama_cell_import_batch(
            db(),
            (string) (
                $_POST['filename']
                ?? ''
            ),
            (string) (
                $_POST['state_fips']
                ?? ''
            ),
            (string) (
                $_POST['as_of_date']
                ?? ''
            ),
            (int) (
                $_POST['offset']
                ?? 0
            ),
            1500
        );

    admin_cell_import_json(
        [
            'ok' => true,
            'result' => $result,
        ]
    );

} catch (Throwable $e) {
    $reference =
        llama_log_caught_exception(
            $e,
            'admin.cell_coverage_import'
        );

    admin_cell_import_json(
        [
            'ok' => false,
            'error' =>
                $e instanceof
                    InvalidArgumentException
                    ? $e->getMessage()
                    : llama_error_message_with_reference(
                        'The FCC coverage import failed.',
                        $reference
                    ),
            'reference' =>
                $reference,
        ],
        500
    );
}

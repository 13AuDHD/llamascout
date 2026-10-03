<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: private, no-store, max-age=0'
);


function admin_pad_us_sync_json(
    array $payload,
    int $status = 200
): never {
    http_response_code(
        $status
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


try {
    $adminUser =
        moderation_require_admin();

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !== 'POST'
    ) {
        admin_pad_us_sync_json(
            [
                'ok' =>
                    false,
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
        admin_pad_us_sync_json(
            [
                'ok' =>
                    false,
                'error' =>
                    'Your session token expired. Reload and try again.',
            ],
            403
        );
    }

    $stateCode =
        strtoupper(
            trim(
                (string) (
                    $_POST['state_code']
                    ?? ''
                )
            )
        );

    $runId =
        max(
            0,
            (int) (
                $_POST['run_id']
                ?? 0
            )
        );

    $offset =
        array_key_exists(
            'offset',
            $_POST
        )
            ? max(
                0,
                (int) $_POST['offset']
            )
            : null;

    $result =
        llama_pad_us_import_batch(
            db(),
            (int) (
                $adminUser['id']
                ?? 0
            ),
            $stateCode,
            $runId,
            $offset,
            500
        );

    admin_pad_us_sync_json(
        [
            'ok' =>
                true,
            'result' =>
                $result,
        ]
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.pad_us_sync',
            [
                'state_code' =>
                    $stateCode
                    ?? '',
                'run_id' =>
                    $runId
                    ?? 0,
            ],
            [
                InvalidArgumentException::class,
                RuntimeException::class,
            ]
        );

    admin_pad_us_sync_json(
        [
            'ok' =>
                false,
            'error' =>
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The PAD-US synchronization failed.',
                        $reference
                    ),
            'reference' =>
                $reference,
        ],
        500
    );
}

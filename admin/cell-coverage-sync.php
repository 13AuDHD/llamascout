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

        $remainingSeconds =
            max(
                0,
                (int) (
                    $state['fcc_next_request_at']
                    ?? 0
                )
                - time()
            );

        $public['retry_after_ms'] =
            max(
                (int) (
                    $state['retry_after_ms']
                    ?? 0
                ),
                $remainingSeconds * 1000
            );
    }

    return $public;
}


function admin_cell_sync_background_finisher(): ?string
{
    if (function_exists('litespeed_finish_request')) {
        return 'litespeed_finish_request';
    }

    if (function_exists('fastcgi_finish_request')) {
        return 'fastcgi_finish_request';
    }

    return null;
}


function admin_cell_sync_download_lock_path(): string
{
    return
        llama_fcc_sync_directory()
        . '/fcc-cell-sync-download.lock';
}


function admin_cell_sync_finish_response(
    array $payload,
    string $finisher
): void {
    $json =
        json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

    if ($json === false) {
        throw new RuntimeException(
            'The FCC sync response could not be encoded.'
        );
    }

    http_response_code(200);

    if (!headers_sent()) {
        header(
            'Content-Type: application/json; charset=UTF-8'
        );
        header(
            'Cache-Control: private, no-store, max-age=0'
        );
        header(
            'Content-Length: '
            . strlen($json)
        );
    }

    /*
     * Release the PHP session before the background download.
     * Otherwise the status-poll requests from Safari can block
     * behind this worker for the entire file transfer.
     */
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        session_write_close();
    }

    echo $json;

    ignore_user_abort(true);
    @set_time_limit(0);

    if ($finisher === 'litespeed_finish_request') {
        litespeed_finish_request();
        return;
    }

    fastcgi_finish_request();
}


function admin_cell_sync_prepare_download_state(
    array $state
): array {
    $index =
        max(
            0,
            (int) (
                $state['index']
                ?? 0
            )
        );

    /*
     * The old Range-based downloader may have left a partial
     * file and retry timer behind. Clear those once when the
     * full-file downloader takes over.
     */
    if (
        (string) (
            $state['download_mode']
            ?? ''
        ) !== 'full-file'
        || (int) (
            $state['download_index']
            ?? -1
        ) !== $index
    ) {
        $state =
            llama_fcc_chunk_reset_download_state(
                $state
            );

        llama_fcc_sync_save_state(
            $state
        );
    }

    return $state;
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

    } elseif ($action === 'download') {
        $state =
            llama_fcc_sync_load_state();

        if (!$state) {
            $state =
                llama_fcc_sync_create_plan();
        }

        if (
            ($state['status'] ?? '')
            === 'error'
        ) {
            $state =
                llama_fcc_sync_resume();
        }

        if (
            (string) (
                $state['phase']
                ?? ''
            ) !== 'download'
            || (string) (
                $state['status']
                ?? ''
            ) !== 'running'
        ) {
            admin_cell_sync_json(
                [
                    'ok' => true,
                    'sync' =>
                        admin_cell_sync_public_state(
                            $state
                        ),
                ]
            );
        }

        $state =
            admin_cell_sync_prepare_download_state(
                $state
            );

        $remainingWait =
            llama_fcc_chunk_remaining_wait(
                $state
            );

        if ($remainingWait > 0) {
            $state['retry_after_ms'] =
                $remainingWait * 1000;

            llama_fcc_sync_save_state(
                $state
            );

            admin_cell_sync_json(
                [
                    'ok' => true,
                    'sync' =>
                        admin_cell_sync_public_state(
                            $state
                        ),
                ]
            );
        }

        $finisher =
            admin_cell_sync_background_finisher();

        if ($finisher === null) {
            admin_cell_sync_json(
                [
                    'ok' => false,
                    'error' =>
                        'This server does not expose fastcgi_finish_request() or litespeed_finish_request(), so the FCC download cannot be detached from the browser request.',
                ],
                500
            );
        }

        $lock =
            fopen(
                admin_cell_sync_download_lock_path(),
                'c'
            );

        if (!$lock) {
            throw new RuntimeException(
                'The FCC download worker lock could not be opened.'
            );
        }

        /*
         * Another request may already be downloading the current
         * FCC file. In that case return the latest status instead
         * of launching a second worker.
         */
        if (
            !flock(
                $lock,
                LOCK_EX | LOCK_NB
            )
        ) {
            fclose($lock);

            $state =
                llama_fcc_sync_load_state()
                ?? $state;

            admin_cell_sync_json(
                [
                    'ok' => true,
                    'sync' =>
                        admin_cell_sync_public_state(
                            $state
                        ),
                ]
            );
        }

        $state['message'] =
            'FCC download started in the background.';

        llama_fcc_sync_save_state(
            $state
        );

        /*
         * Return JSON to Safari NOW. The long FCC transfer keeps
         * running in this PHP worker after the browser request has
         * been completed by PHP-FPM/LiteSpeed.
         */
        admin_cell_sync_finish_response(
            [
                'ok' => true,
                'sync' =>
                    admin_cell_sync_public_state(
                        $state
                    ),
            ],
            $finisher
        );

        try {
            llama_fcc_chunked_download_step();

        } catch (Throwable $workerError) {
            llama_log_caught_exception(
                $workerError,
                'admin.cell_coverage_background_download'
            );

            $latest =
                llama_fcc_sync_load_state();

            if (is_array($latest)) {
                $latest['status'] =
                    'error';

                $latest['error'] =
                    $workerError->getMessage();

                $latest['message'] =
                    'FCC background download stopped on an error.';

                llama_fcc_sync_save_state(
                    $latest
                );
            }

        } finally {
            flock(
                $lock,
                LOCK_UN
            );

            fclose($lock);
        }

        exit;

    } elseif ($action === 'step') {
        /*
         * Import work remains short and request-driven. Downloads
         * are handled only by the detached "download" action.
         */
        $state =
            llama_fcc_sync_load_state();

        if (
            is_array($state)
            && (string) (
                $state['phase']
                ?? ''
            ) === 'download'
        ) {
            admin_cell_sync_json(
                [
                    'ok' => true,
                    'sync' =>
                        admin_cell_sync_public_state(
                            $state
                        ),
                ]
            );
        }

        $state =
            llama_fcc_sync_step();

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

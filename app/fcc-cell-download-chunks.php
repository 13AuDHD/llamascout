<?php

declare(strict_types=1);

require_once __DIR__ . '/fcc-cell-sync.php';


/* =========================================================
   LLAMA SCOUT
   FCC FILE DOWNLOADS

   The FCC binary download endpoint does not reliably support
   HTTP Range requests. Each dataset is therefore downloaded as
   one normal streaming GET. If a transfer is interrupted, only
   that dataset restarts from byte zero. Completed datasets and
   import offsets remain resumable through the sync state.
   ========================================================= */

const LLAMA_FCC_DOWNLOAD_TIMEOUT = 1800;
const LLAMA_FCC_DOWNLOAD_LOW_SPEED_LIMIT = 1024;
const LLAMA_FCC_DOWNLOAD_LOW_SPEED_TIME = 90;
const LLAMA_FCC_PROGRESS_SAVE_SECONDS = 2.0;
const LLAMA_FCC_HEARTBEAT_SECONDS = 5.0;
const LLAMA_FCC_HEARTBEAT_BYTES = 2048;
const LLAMA_FCC_RETRY_BASE_SECONDS = 10;
const LLAMA_FCC_RETRY_MAX_SECONDS = 60;


function llama_fcc_chunk_paths(
    array $state
): array {
    $syncId = preg_replace(
        '/[^a-z0-9]/i',
        '',
        (string) ($state['sync_id'] ?? 'sync')
    );

    if ($syncId === '') {
        $syncId = 'sync';
    }

    $index = max(
        0,
        (int) ($state['index'] ?? 0)
    );

    $prefix =
        'fccsync_'
        . $syncId
        . '_'
        . $index;

    $directory = llama_fcc_sync_directory();

    return [
        'prefix' => $prefix,
        'partial' =>
            $directory
            . '/'
            . $prefix
            . '.download',
        'gpkg_name' =>
            $prefix
            . '.gpkg',
        'gpkg' =>
            $directory
            . '/'
            . $prefix
            . '.gpkg',
        'extract' =>
            $directory
            . '/'
            . $prefix
            . '_extract',
    ];
}


function llama_fcc_chunk_current_dataset(
    array $state
): ?array {
    $queue = is_array($state['queue'] ?? null)
        ? $state['queue']
        : [];

    $index = max(
        0,
        (int) ($state['index'] ?? 0)
    );

    return is_array($queue[$index] ?? null)
        ? $queue[$index]
        : null;
}


function llama_fcc_chunk_download_url(
    array $dataset
): string {
    $fileId = trim(
        (string) ($dataset['file_id'] ?? '')
    );

    if ($fileId === '') {
        throw new RuntimeException(
            'The FCC catalog entry is missing a file ID.'
        );
    }

    /*
     * Use the National Broadband Map public-data host specifically
     * for binary downloads. Catalog discovery can continue using
     * the configured BDC API base.
     */
    return
        'https://broadbandmap.fcc.gov/api/public/map'
        . '/downloads/downloadFile/availability/'
        . rawurlencode($fileId)
        . '/'
        . LLAMA_FCC_DOWNLOAD_FORMAT_GEOPACKAGE;
}


function llama_fcc_chunk_cleanup_state(
    ?array $state
): void {
    if (!$state) {
        return;
    }

    $paths = llama_fcc_chunk_paths($state);

    if (is_file($paths['partial'])) {
        @unlink($paths['partial']);
    }

    llama_fcc_sync_remove_tree(
        $paths['extract']
    );
}


function llama_fcc_chunk_format_bytes(
    int $bytes
): string {
    if ($bytes >= 1073741824) {
        return number_format(
            $bytes / 1073741824,
            1
        ) . ' GB';
    }

    if ($bytes >= 1048576) {
        return number_format(
            $bytes / 1048576,
            1
        ) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format(
            $bytes / 1024,
            1
        ) . ' KB';
    }

    return number_format($bytes) . ' B';
}


function llama_fcc_chunk_dataset_label(
    array $dataset
): string {
    return trim(
        (string) ($dataset['state_name'] ?? '')
        . ' '
        . (string) ($dataset['provider_label'] ?? '')
        . ' '
        . strtoupper(
            (string) ($dataset['technology'] ?? '')
        )
    );
}


function llama_fcc_chunk_finalize_download(
    array $state,
    array $dataset
): array {
    $paths = llama_fcc_chunk_paths($state);
    $download = $paths['partial'];

    if (!is_file($download)) {
        throw new RuntimeException(
            'The FCC download disappeared before it could be finalized.'
        );
    }

    $expectedBytes = max(
        0,
        (int) ($state['download_total_bytes'] ?? 0)
    );

    $actualBytes = (int) filesize($download);

    if (
        $expectedBytes > 0
        && $actualBytes < $expectedBytes
    ) {
        throw new RuntimeException(
            'The FCC download is incomplete.'
        );
    }

    $head = file_get_contents(
        $download,
        false,
        null,
        0,
        16
    );

    $gpkgName = $paths['gpkg_name'];
    $gpkgPath = $paths['gpkg'];

    if (
        is_string($head)
        && str_starts_with(
            $head,
            'SQLite format 3'
        )
    ) {
        @unlink($gpkgPath);

        if (!rename($download, $gpkgPath)) {
            throw new RuntimeException(
                'The downloaded GeoPackage could not be finalized.'
            );
        }

    } elseif (
        is_string($head)
        && str_starts_with($head, 'PK')
    ) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'PHP ZipArchive is required for automatic FCC downloads.'
            );
        }

        $extractDirectory = $paths['extract'];

        llama_fcc_sync_remove_tree(
            $extractDirectory
        );

        if (!mkdir(
            $extractDirectory,
            0750,
            true
        )) {
            throw new RuntimeException(
                'The FCC ZIP extraction directory could not be created.'
            );
        }

        $zip = new ZipArchive();
        $opened = $zip->open($download);

        if ($opened !== true) {
            llama_fcc_sync_remove_tree(
                $extractDirectory
            );

            throw new RuntimeException(
                'The FCC ZIP archive could not be opened.'
            );
        }

        if (!$zip->extractTo($extractDirectory)) {
            $zip->close();

            llama_fcc_sync_remove_tree(
                $extractDirectory
            );

            throw new RuntimeException(
                'The FCC ZIP archive could not be extracted.'
            );
        }

        $zip->close();
        @unlink($download);

        $sourceGpkg = null;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $extractDirectory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && strtolower($file->getExtension())
                    === 'gpkg'
            ) {
                $sourceGpkg = $file->getPathname();
                break;
            }
        }

        if (!$sourceGpkg) {
            llama_fcc_sync_remove_tree(
                $extractDirectory
            );

            throw new RuntimeException(
                'The FCC ZIP archive did not contain a GeoPackage.'
            );
        }

        @unlink($gpkgPath);

        if (!rename($sourceGpkg, $gpkgPath)) {
            llama_fcc_sync_remove_tree(
                $extractDirectory
            );

            throw new RuntimeException(
                'The extracted FCC GeoPackage could not be moved.'
            );
        }

        llama_fcc_sync_remove_tree(
            $extractDirectory
        );

    } else {
        throw new RuntimeException(
            'The FCC download was not a GeoPackage or ZIP archive.'
        );
    }

    $state['local_filename'] = $gpkgName;
    $state['phase'] = 'import';
    $state['offset'] = 0;
    $state['current_total_rows'] = 0;
    $state['current_imported_rows'] = 0;
    $state['message'] =
        'Download complete. Preparing import.';

    unset(
        $state['downloaded_bytes'],
        $state['download_total_bytes'],
        $state['download_started_at'],
        $state['download_retry_count'],
        $state['retry_after_ms'],
        $state['fcc_next_request_at'],
        $state['download_mode'],
        $state['download_index']
    );

    llama_fcc_sync_save_state($state);

    return $state;
}


function llama_fcc_chunk_retry_delay(
    int $attempt
): int {
    $attempt = max(1, $attempt);

    $delay =
        LLAMA_FCC_RETRY_BASE_SECONDS
        * (2 ** min(
            3,
            $attempt - 1
        ));

    return min(
        LLAMA_FCC_RETRY_MAX_SECONDS,
        (int) $delay
    );
}


function llama_fcc_chunk_wait_state(
    array $state,
    int $seconds,
    string $message
): array {
    $seconds = max(1, $seconds);

    $state['status'] = 'running';
    $state['error'] = null;
    $state['fcc_next_request_at'] =
        time() + $seconds;
    $state['retry_after_ms'] =
        $seconds * 1000;
    $state['message'] = $message;

    llama_fcc_sync_save_state($state);

    return $state;
}


function llama_fcc_chunk_remaining_wait(
    array $state
): int {
    $next = max(
        0,
        (int) ($state['fcc_next_request_at'] ?? 0)
    );

    if ($next <= 0) {
        return 0;
    }

    return max(
        0,
        $next - time()
    );
}


function llama_fcc_chunk_should_heartbeat(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    $script = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $action = strtolower(
        trim(
            (string) ($_POST['action'] ?? '')
        )
    );

    return
        $action === 'step'
        && str_ends_with(
            $script,
            '/cell-coverage-sync.php'
        );
}


function llama_fcc_chunk_prepare_heartbeat(): bool
{
    if (!llama_fcc_chunk_should_heartbeat()) {
        return false;
    }

    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');

    if (!headers_sent()) {
        header('X-Accel-Buffering: no');
        header('Content-Encoding: identity');
    }

    /*
     * PHP and the web server must be allowed to actually send the
     * heartbeat bytes instead of holding them until the request is
     * finished. This endpoint returns JSON only, so releasing output
     * buffers here is safe. Leading whitespace remains valid JSON.
     */
    while (ob_get_level() > 0) {
        if (!@ob_end_flush()) {
            break;
        }
    }

    return true;
}


function llama_fcc_chunk_emit_heartbeat(): void
{
    echo str_repeat(
        ' ',
        LLAMA_FCC_HEARTBEAT_BYTES
    ) . "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
}


function llama_fcc_chunk_reset_download_state(
    array $state
): array {
    $paths = llama_fcc_chunk_paths($state);

    if (is_file($paths['partial'])) {
        @unlink($paths['partial']);
    }

    unset(
        $state['downloaded_bytes'],
        $state['download_total_bytes'],
        $state['download_started_at'],
        $state['download_retry_count'],
        $state['retry_after_ms'],
        $state['fcc_next_request_at']
    );

    $state['download_mode'] = 'full-file';
    $state['download_index'] =
        max(
            0,
            (int) ($state['index'] ?? 0)
        );

    return $state;
}


function llama_fcc_chunk_fetch(
    array $state,
    array $dataset
): array {
    $index = max(
        0,
        (int) ($state['index'] ?? 0)
    );

    /*
     * Any state created by the old byte-range downloader is cleared
     * once. This removes stale retry timers and partial chunk files.
     */
    if (
        (string) ($state['download_mode'] ?? '')
            !== 'full-file'
        || (int) ($state['download_index'] ?? -1)
            !== $index
    ) {
        $state = llama_fcc_chunk_reset_download_state(
            $state
        );
        llama_fcc_sync_save_state($state);
    }

    $remainingWait =
        llama_fcc_chunk_remaining_wait($state);

    if ($remainingWait > 0) {
        $state['retry_after_ms'] =
            $remainingWait * 1000;

        llama_fcc_sync_save_state($state);

        return $state;
    }

    unset(
        $state['retry_after_ms'],
        $state['fcc_next_request_at']
    );

    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is required for automatic FCC downloads.'
        );
    }

    $paths = llama_fcc_chunk_paths($state);
    $partial = $paths['partial'];

    /*
     * FCC does not honor Range consistently. Never append to a
     * previous partial file. A resumed dataset starts this file over.
     */
    @unlink($partial);

    $stream = fopen($partial, 'wb');

    if (!$stream) {
        throw new RuntimeException(
            'The temporary FCC download file could not be created.'
        );
    }

    $label = llama_fcc_chunk_dataset_label(
        $dataset
    );

    $state['downloaded_bytes'] = 0;
    $state['download_total_bytes'] = 0;
    $state['download_started_at'] = gmdate('c');
    $state['message'] =
        'Downloading '
        . $label
        . '...';

    llama_fcc_sync_save_state($state);

    $heartbeatEnabled =
        llama_fcc_chunk_prepare_heartbeat();

    $config = llama_fcc_sync_config();
    $curl = llama_fcc_sync_curl_base(
        llama_fcc_chunk_download_url($dataset)
    );

    $retryAfterHeader = null;
    $lastStateSave = microtime(true);
    $lastHeartbeat = microtime(true);
    $callbackError = null;

    curl_setopt_array(
        $curl,
        [
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'username: ' . $config['username'],
                'hash_value: ' . $config['hash_value'],
            ],
            CURLOPT_FILE => $stream,
            CURLOPT_TIMEOUT =>
                LLAMA_FCC_DOWNLOAD_TIMEOUT,
            CURLOPT_LOW_SPEED_LIMIT =>
                LLAMA_FCC_DOWNLOAD_LOW_SPEED_LIMIT,
            CURLOPT_LOW_SPEED_TIME =>
                LLAMA_FCC_DOWNLOAD_LOW_SPEED_TIME,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_HEADERFUNCTION =>
                static function (
                    CurlHandle $handle,
                    string $line
                ) use (
                    &$retryAfterHeader
                ): int {
                    $length = strlen($line);
                    $trimmed = trim($line);

                    if (
                        preg_match(
                            '/^HTTP\\/\\S+\\s+\\d{3}/i',
                            $trimmed
                        )
                    ) {
                        $retryAfterHeader = null;
                        return $length;
                    }

                    if (
                        stripos(
                            $trimmed,
                            'Retry-After:'
                        ) === 0
                    ) {
                        $candidate = trim(
                            substr(
                                $trimmed,
                                strlen('Retry-After:')
                            )
                        );

                        if (ctype_digit($candidate)) {
                            $retryAfterHeader = max(
                                1,
                                (int) $candidate
                            );
                        }
                    }

                    return $length;
                },
            CURLOPT_XFERINFOFUNCTION =>
                static function (
                    CurlHandle $handle,
                    $downloadTotal,
                    $downloadNow,
                    $uploadTotal,
                    $uploadNow
                ) use (
                    &$state,
                    $dataset,
                    $heartbeatEnabled,
                    &$lastStateSave,
                    &$lastHeartbeat,
                    &$callbackError
                ): int {
                    $now = microtime(true);

                    $downloadedBytes = max(
                        0,
                        (int) round($downloadNow)
                    );

                    $totalBytes = max(
                        0,
                        (int) round($downloadTotal)
                    );

                    $state['downloaded_bytes'] =
                        $downloadedBytes;

                    if ($totalBytes > 0) {
                        $state['download_total_bytes'] =
                            $totalBytes;
                    }

                    if (
                        ($now - $lastStateSave)
                        >= LLAMA_FCC_PROGRESS_SAVE_SECONDS
                    ) {
                        $state['message'] =
                            'Downloading '
                            . llama_fcc_chunk_dataset_label(
                                $dataset
                            )
                            . ': '
                            . llama_fcc_chunk_format_bytes(
                                $downloadedBytes
                            )
                            . ($totalBytes > 0
                                ? ' / '
                                    . llama_fcc_chunk_format_bytes(
                                        $totalBytes
                                    )
                                : '')
                            . '.';

                        try {
                            llama_fcc_sync_save_state(
                                $state
                            );
                        } catch (Throwable $e) {
                            $callbackError = $e;
                            return 1;
                        }

                        $lastStateSave = $now;
                    }

                    if (
                        $heartbeatEnabled
                        && ($now - $lastHeartbeat)
                            >= LLAMA_FCC_HEARTBEAT_SECONDS
                    ) {
                        llama_fcc_chunk_emit_heartbeat();
                        $lastHeartbeat = $now;
                    }

                    return 0;
                },
        ]
    );

    $ok = curl_exec($curl);

    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_RESPONSE_CODE
    );

    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);

    $reportedTotal = 0;

    if (defined('CURLINFO_CONTENT_LENGTH_DOWNLOAD_T')) {
        $candidateTotal = curl_getinfo(
            $curl,
            CURLINFO_CONTENT_LENGTH_DOWNLOAD_T
        );

        if (is_int($candidateTotal)) {
            $reportedTotal = max(
                0,
                $candidateTotal
            );
        }
    }

    curl_close($curl);
    fflush($stream);
    fclose($stream);

    if ($callbackError instanceof Throwable) {
        @unlink($partial);
        throw $callbackError;
    }

    clearstatcache(true, $partial);

    $downloadedBytes = is_file($partial)
        ? (int) filesize($partial)
        : 0;

    $state['downloaded_bytes'] =
        $downloadedBytes;

    if ($reportedTotal > 0) {
        $state['download_total_bytes'] =
            $reportedTotal;
    }

    $transientStatuses = [
        408,
        425,
        429,
        500,
        502,
        503,
        504,
    ];

    $transientCurlErrors = [
        CURLE_OPERATION_TIMEDOUT,
        CURLE_COULDNT_CONNECT,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_RECV_ERROR,
        CURLE_PARTIAL_FILE,
    ];

    $transientFailure =
        in_array(
            $status,
            $transientStatuses,
            true
        )
        || in_array(
            $curlErrno,
            $transientCurlErrors,
            true
        );

    if ($transientFailure) {
        @unlink($partial);

        $attempt = max(
            1,
            (int) ($state['download_retry_count'] ?? 0)
                + 1
        );

        $state['download_retry_count'] =
            $attempt;

        unset(
            $state['downloaded_bytes'],
            $state['download_total_bytes'],
            $state['download_started_at']
        );

        $delay =
            $retryAfterHeader
            ?? llama_fcc_chunk_retry_delay($attempt);

        $diagnostic = [];

        if ($status > 0) {
            $statusName = match ($status) {
                408 => 'Request Timeout',
                425 => 'Too Early',
                429 => 'Too Many Requests',
                500 => 'Internal Server Error',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                default => '',
            };

            $diagnostic[] =
                'HTTP '
                . $status
                . ($statusName !== ''
                    ? ' ' . $statusName
                    : '');
        }

        if ($curlErrno !== 0) {
            $diagnostic[] =
                'cURL '
                . $curlErrno
                . ($curlError !== ''
                    ? ': ' . $curlError
                    : '');
        }

        if ($retryAfterHeader !== null) {
            $diagnostic[] =
                'Retry-After '
                . $retryAfterHeader
                . ' seconds';
        }

        if (!$diagnostic) {
            $diagnostic[] =
                'unknown transport failure';
        }

        return llama_fcc_chunk_wait_state(
            $state,
            $delay,
            'FCC download retry for '
            . $label
            . ': '
            . implode(' | ', $diagnostic)
            . '. Restarting this file in '
            . $delay
            . ' seconds.'
        );
    }

    if (
        $ok === false
        || $status !== 200
    ) {
        @unlink($partial);

        throw new RuntimeException(
            'FCC file download failed'
            . ($status > 0
                ? ' with HTTP ' . $status
                : '')
            . ($status === 206
                ? ': the FCC server returned an unexpected partial response.'
                : ($curlError !== ''
                    ? ': ' . $curlError
                    : '.'))
        );
    }

    $state['download_retry_count'] = 0;
    $state['downloaded_bytes'] =
        $downloadedBytes;

    if (
        (int) ($state['download_total_bytes'] ?? 0)
        <= 0
    ) {
        $state['download_total_bytes'] =
            $downloadedBytes;
    }

    $state['message'] =
        'Downloaded '
        . $label
        . ': '
        . llama_fcc_chunk_format_bytes(
            $downloadedBytes
        )
        . '.';

    llama_fcc_sync_save_state($state);

    return llama_fcc_chunk_finalize_download(
        $state,
        $dataset
    );
}


function llama_fcc_chunked_download_step(): array
{
    $state = llama_fcc_sync_load_state();

    if (!$state) {
        $state = llama_fcc_sync_create_plan();
    }

    if (
        in_array(
            (string) ($state['status'] ?? ''),
            [
                'complete',
                'error',
            ],
            true
        )
    ) {
        return $state;
    }

    if (
        (string) ($state['phase'] ?? 'download')
        !== 'download'
    ) {
        return llama_fcc_sync_step();
    }

    $dataset = llama_fcc_chunk_current_dataset(
        $state
    );

    if (!$dataset) {
        return llama_fcc_sync_step();
    }

    try {
        $state['status'] = 'running';
        $state['error'] = null;

        llama_fcc_sync_save_state($state);

        return llama_fcc_chunk_fetch(
            $state,
            $dataset
        );

    } catch (Throwable $e) {
        $state['status'] = 'error';
        $state['error'] = $e->getMessage();
        $state['message'] =
            'FCC sync stopped on an error.';

        llama_fcc_sync_save_state($state);

        return $state;
    }
}

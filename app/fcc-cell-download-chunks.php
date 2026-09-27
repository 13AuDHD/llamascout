<?php

declare(strict_types=1);

require_once __DIR__ . '/fcc-cell-sync.php';


/* =========================================================
   LLAMA SCOUT
   RESUMABLE FCC FILE DOWNLOADS
   ========================================================= */

const LLAMA_FCC_DOWNLOAD_CHUNK_BYTES = 8388608; // 8 MiB
const LLAMA_FCC_DOWNLOAD_STEP_TIMEOUT = 40;
const LLAMA_FCC_MIN_REQUEST_INTERVAL = 7;
const LLAMA_FCC_RETRY_BASE_SECONDS = 10;
const LLAMA_FCC_RETRY_MAX_SECONDS = 60;


function llama_fcc_chunk_paths(
    array $state
): array {
    $syncId =
        preg_replace(
            '/[^a-z0-9]/i',
            '',
            (string) (
                $state['sync_id']
                ?? 'sync'
            )
        );

    if ($syncId === '') {
        $syncId = 'sync';
    }

    $index =
        max(
            0,
            (int) (
                $state['index']
                ?? 0
            )
        );

    $prefix =
        'fccsync_'
        . $syncId
        . '_'
        . $index;

    $directory =
        llama_fcc_sync_directory();

    return [
        'prefix' =>
            $prefix,

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
    $queue =
        is_array(
            $state['queue']
            ?? null
        )
            ? $state['queue']
            : [];

    $index =
        max(
            0,
            (int) (
                $state['index']
                ?? 0
            )
        );

    return is_array(
        $queue[$index]
        ?? null
    )
        ? $queue[$index]
        : null;
}


function llama_fcc_chunk_download_url(
    array $dataset
): string {
    $config =
        llama_fcc_sync_config();

    $fileId =
        trim(
            (string) (
                $dataset['file_id']
                ?? ''
            )
        );

    if ($fileId === '') {
        throw new RuntimeException(
            'The FCC catalog entry is missing a file ID.'
        );
    }

    return
        $config['api_base']
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

    $paths =
        llama_fcc_chunk_paths(
            $state
        );

    if (is_file($paths['partial'])) {
        @unlink(
            $paths['partial']
        );
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


function llama_fcc_chunk_finalize_download(
    array $state,
    array $dataset
): array {
    $paths =
        llama_fcc_chunk_paths(
            $state
        );

    $download =
        $paths['partial'];

    if (!is_file($download)) {
        throw new RuntimeException(
            'The partial FCC download disappeared before it could be finalized.'
        );
    }

    $expectedBytes =
        max(
            0,
            (int) (
                $state['download_total_bytes']
                ?? 0
            )
        );

    $actualBytes =
        (int) filesize(
            $download
        );

    if (
        $expectedBytes > 0
        && $actualBytes < $expectedBytes
    ) {
        throw new RuntimeException(
            'The FCC download is incomplete.'
        );
    }

    $head =
        file_get_contents(
            $download,
            false,
            null,
            0,
            16
        );

    $gpkgName =
        $paths['gpkg_name'];

    $gpkgPath =
        $paths['gpkg'];

    if (
        is_string($head)
        && str_starts_with(
            $head,
            'SQLite format 3'
        )
    ) {
        @unlink($gpkgPath);

        if (!rename(
            $download,
            $gpkgPath
        )) {
            throw new RuntimeException(
                'The downloaded GeoPackage could not be finalized.'
            );
        }

    } elseif (
        is_string($head)
        && str_starts_with(
            $head,
            'PK'
        )
    ) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'PHP ZipArchive is required for automatic FCC downloads.'
            );
        }

        $extractDirectory =
            $paths['extract'];

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

        $zip =
            new ZipArchive();

        $opened =
            $zip->open(
                $download
            );

        if ($opened !== true) {
            llama_fcc_sync_remove_tree(
                $extractDirectory
            );

            throw new RuntimeException(
                'The FCC ZIP archive could not be opened.'
            );
        }

        if (!$zip->extractTo(
            $extractDirectory
        )) {
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

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $extractDirectory,
                    FilesystemIterator::SKIP_DOTS
                )
            );

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && strtolower(
                    $file->getExtension()
                ) === 'gpkg'
            ) {
                $sourceGpkg =
                    $file->getPathname();

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

        if (!rename(
            $sourceGpkg,
            $gpkgPath
        )) {
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

    $state['local_filename'] =
        $gpkgName;

    $state['phase'] =
        'import';

    $state['offset'] =
        0;

    $state['current_total_rows'] =
        0;

    $state['current_imported_rows'] =
        0;

    $state['message'] =
        'Download complete. Preparing import.';

    unset(
        $state['downloaded_bytes'],
        $state['download_total_bytes'],
        $state['download_started_at'],
        $state['download_retry_count'],
        $state['retry_after_ms'],
        $state['fcc_next_request_at']
    );

    llama_fcc_sync_save_state(
        $state
    );

    return $state;
}


function llama_fcc_chunk_retry_delay(
    int $attempt
): int {
    $attempt =
        max(
            1,
            $attempt
        );

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
    $seconds =
        max(
            1,
            $seconds
        );

    $state['status'] =
        'running';

    $state['error'] =
        null;

    $state['fcc_next_request_at'] =
        time() + $seconds;

    $state['retry_after_ms'] =
        $seconds * 1000;

    $state['message'] =
        $message;

    llama_fcc_sync_save_state(
        $state
    );

    return $state;
}


function llama_fcc_chunk_remaining_wait(
    array $state
): int {
    $next =
        max(
            0,
            (int) (
                $state['fcc_next_request_at']
                ?? 0
            )
        );

    if ($next <= 0) {
        return 0;
    }

    return max(
        0,
        $next - time()
    );
}


function llama_fcc_chunk_fetch(
    array $state,
    array $dataset
): array {
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

        return $state;
    }

    unset(
        $state['retry_after_ms']
    );

    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is required for automatic FCC downloads.'
        );
    }

    $paths =
        llama_fcc_chunk_paths(
            $state
        );

    $partial =
        $paths['partial'];

    $offset =
        is_file($partial)
            ? max(
                0,
                (int) filesize($partial)
            )
            : 0;

    $chunkBytes =
        LLAMA_FCC_DOWNLOAD_CHUNK_BYTES;

    $rangeEnd =
        $offset
        + $chunkBytes
        - 1;

    $stream =
        fopen(
            $partial,
            'c+b'
        );

    if (!$stream) {
        throw new RuntimeException(
            'The partial FCC download file could not be opened.'
        );
    }

    if (fseek(
        $stream,
        $offset,
        SEEK_SET
    ) !== 0) {
        fclose($stream);

        throw new RuntimeException(
            'The partial FCC download file could not be resumed.'
        );
    }

    $config =
        llama_fcc_sync_config();

    $curl =
        llama_fcc_sync_curl_base(
            llama_fcc_chunk_download_url(
                $dataset
            )
        );

    $httpStatus = 0;
    $contentRange = '';
    $contentLength = null;
    $retryAfterHeader = null;
    $writtenThisRequest = 0;
    $writeLimitReached = false;

    curl_setopt_array(
        $curl,
        [
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'username: ' . $config['username'],
                'hash_value: ' . $config['hash_value'],
            ],

            CURLOPT_RANGE =>
                $offset
                . '-'
                . $rangeEnd,

            CURLOPT_TIMEOUT =>
                LLAMA_FCC_DOWNLOAD_STEP_TIMEOUT,

            CURLOPT_LOW_SPEED_LIMIT =>
                1024,

            CURLOPT_LOW_SPEED_TIME =>
                15,

            CURLOPT_HEADERFUNCTION =>
                static function (
                    CurlHandle $handle,
                    string $line
                ) use (
                    &$httpStatus,
                    &$contentRange,
                    &$contentLength,
                    &$retryAfterHeader
                ): int {
                    $length =
                        strlen($line);

                    $trimmed =
                        trim($line);

                    if (
                        preg_match(
                            '/^HTTP\\/\\S+\\s+(\\d{3})/i',
                            $trimmed,
                            $match
                        )
                    ) {
                        $httpStatus =
                            (int) $match[1];

                        $contentRange = '';
                        $contentLength = null;
                        $retryAfterHeader = null;

                        return $length;
                    }

                    if (
                        stripos(
                            $trimmed,
                            'Content-Range:'
                        ) === 0
                    ) {
                        $contentRange =
                            trim(
                                substr(
                                    $trimmed,
                                    strlen(
                                        'Content-Range:'
                                    )
                                )
                            );
                    }

                    if (
                        stripos(
                            $trimmed,
                            'Content-Length:'
                        ) === 0
                    ) {
                        $candidate =
                            trim(
                                substr(
                                    $trimmed,
                                    strlen(
                                        'Content-Length:'
                                    )
                                )
                            );

                        if (ctype_digit($candidate)) {
                            $contentLength =
                                (int) $candidate;
                        }
                    }

                    if (
                        stripos(
                            $trimmed,
                            'Retry-After:'
                        ) === 0
                    ) {
                        $candidate =
                            trim(
                                substr(
                                    $trimmed,
                                    strlen(
                                        'Retry-After:'
                                    )
                                )
                            );

                        if (ctype_digit($candidate)) {
                            $retryAfterHeader =
                                max(
                                    1,
                                    (int) $candidate
                                );
                        }
                    }

                    return $length;
                },

            CURLOPT_WRITEFUNCTION =>
                static function (
                    CurlHandle $handle,
                    string $data
                ) use (
                    $stream,
                    $chunkBytes,
                    &$writtenThisRequest,
                    &$writeLimitReached
                ): int {
                    $remaining =
                        $chunkBytes
                        - $writtenThisRequest;

                    if ($remaining <= 0) {
                        $writeLimitReached = true;
                        return 0;
                    }

                    $dataLength =
                        strlen($data);

                    $toWrite =
                        min(
                            $remaining,
                            $dataLength
                        );

                    $written =
                        fwrite(
                            $stream,
                            $toWrite === $dataLength
                                ? $data
                                : substr(
                                    $data,
                                    0,
                                    $toWrite
                                )
                        );

                    if ($written === false) {
                        return 0;
                    }

                    $writtenThisRequest +=
                        $written;

                    if ($written < $dataLength) {
                        $writeLimitReached = true;
                    }

                    return $written;
                },
        ]
    );

    $ok =
        curl_exec($curl);

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

    if ($status <= 0) {
        $status =
            $httpStatus;
    }

    $curlErrno =
        curl_errno($curl);

    $curlError =
        curl_error($curl);

    curl_close($curl);

    fflush($stream);

    /*
     * The BDC Public Data API has a small per-account request
     * budget. Pace successful chunks and treat temporary upstream
     * failures as retryable instead of stopping the entire sync.
     */
    $state['fcc_next_request_at'] =
        time()
        + LLAMA_FCC_MIN_REQUEST_INTERVAL;

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

    $transientTransportFailure =
        !in_array(
            $status,
            [
                200,
                206,
            ],
            true
        )
        && in_array(
            $curlErrno,
            $transientCurlErrors,
            true
        );

    if (
        in_array(
            $status,
            $transientStatuses,
            true
        )
        || $transientTransportFailure
    ) {
        ftruncate(
            $stream,
            $offset
        );

        fclose($stream);

        $attempt =
            max(
                1,
                (int) (
                    $state['download_retry_count']
                    ?? 0
                ) + 1
            );

        $state['download_retry_count'] =
            $attempt;

        $delay =
            $retryAfterHeader
            ?? llama_fcc_chunk_retry_delay(
                $attempt
            );

        $label =
            trim(
                (string) (
                    $dataset['state_name']
                    ?? ''
                )
                . ' '
                . (string) (
                    $dataset['provider_label']
                    ?? ''
                )
                . ' '
                . strtoupper(
                    (string) (
                        $dataset['technology']
                        ?? ''
                    )
                )
            );

         $diagnostic = [];
         
         if ($status > 0) {
             $statusName =
                 match ($status) {
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
                 . (
                     $statusName !== ''
                         ? ' ' . $statusName
                         : ''
                 );
         }
         
         if ($curlErrno !== 0) {
             $diagnostic[] =
                 'cURL '
                 . $curlErrno
                 . (
                     $curlError !== ''
                         ? ': ' . $curlError
                         : ''
                 );
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
             . implode(
                 ' | ',
                 $diagnostic
             )
             . '. Retrying automatically in '
             . $delay
             . ' seconds.'
         );
    }

    $totalBytes =
        max(
            0,
            (int) (
                $state['download_total_bytes']
                ?? 0
            )
        );

    $rangeStart = null;
    $rangeLast = null;

    if (
        preg_match(
            '/^bytes\\s+(\\d+)-(\\d+)\\/(\\d+|\\*)$/i',
            $contentRange,
            $match
        )
    ) {
        $rangeStart =
            (int) $match[1];

        $rangeLast =
            (int) $match[2];

        if ($match[3] !== '*') {
            $totalBytes =
                (int) $match[3];
        }
    } elseif (
        preg_match(
            '/^bytes\\s+\\*\\/(\\d+)$/i',
            $contentRange,
            $match
        )
    ) {
        $totalBytes =
            (int) $match[1];
    }

    $progressMade =
        $writtenThisRequest > 0;

    $intentionalWriteStop =
        $writeLimitReached
        && $writtenThisRequest >= $chunkBytes;

    $recoverableTransportStop =
        $progressMade
        && in_array(
            $curlErrno,
            [
                CURLE_OPERATION_TIMEDOUT,
                CURLE_PARTIAL_FILE,
                CURLE_RECV_ERROR,
            ],
            true
        );

    $valid = false;
    $complete = false;

    if ($status === 206) {
        if (
            $rangeStart !== null
            && $rangeStart !== $offset
        ) {
            ftruncate(
                $stream,
                $offset
            );

            fclose($stream);

            throw new RuntimeException(
                'The FCC server returned the wrong byte range while resuming the download.'
            );
        }

        $valid =
            $ok !== false
            || $intentionalWriteStop
            || $recoverableTransportStop;

    } elseif ($status === 200) {
        if ($offset > 0) {
            ftruncate(
                $stream,
                $offset
            );

            fclose($stream);

            throw new RuntimeException(
                'The FCC download server ignored the resume byte range.'
            );
        }

        if (
            $contentLength !== null
            && $contentLength > 0
        ) {
            $totalBytes =
                $contentLength;
        }

        if ($intentionalWriteStop) {
            ftruncate(
                $stream,
                0
            );

            fclose($stream);

            @unlink($partial);

            throw new RuntimeException(
                'The FCC download server did not provide a resumable byte range for this file.'
            );
        }

        $valid =
            $ok !== false
            || $recoverableTransportStop;

    } elseif ($status === 416) {
        $currentSize =
            (int) filesize(
                $partial
            );

        $valid =
            $totalBytes > 0
            && $currentSize >= $totalBytes;

        $complete =
            $valid;
    }

    if (!$valid) {
        ftruncate(
            $stream,
            $offset
        );

        fclose($stream);

        throw new RuntimeException(
            'FCC file download failed'
            . ($status > 0
                ? ' with HTTP '
                    . $status
                : '')
            . ($curlError !== ''
                ? ': '
                    . $curlError
                : '.')
        );
    }

    $state['download_retry_count'] =
        0;

    fclose($stream);

    clearstatcache(
        true,
        $partial
    );

    $downloadedBytes =
        is_file($partial)
            ? (int) filesize(
                $partial
            )
            : 0;

    if (
        !$complete
        && $totalBytes > 0
        && $downloadedBytes >= $totalBytes
    ) {
        $complete = true;
    }

    if (
        !$complete
        && $status === 200
        && $ok !== false
        && !$intentionalWriteStop
        && !$recoverableTransportStop
    ) {
        $complete = true;

        if ($totalBytes <= 0) {
            $totalBytes =
                $downloadedBytes;
        }
    }

    if (
        !$complete
        && $status === 206
        && $ok !== false
        && $totalBytes <= 0
        && $writtenThisRequest < $chunkBytes
    ) {
        $complete = true;
        $totalBytes =
            $downloadedBytes;
    }

    unset(
        $state['retry_after_ms']
    );

    $state['downloaded_bytes'] =
        $downloadedBytes;

    $state['download_total_bytes'] =
        $totalBytes;

    $state['download_started_at'] =
        $state['download_started_at']
        ?? gmdate('c');

    if ($complete) {
        llama_fcc_sync_save_state(
            $state
        );

        return llama_fcc_chunk_finalize_download(
            $state,
            $dataset
        );
    }

    $label =
        trim(
            (string) (
                $dataset['state_name']
                ?? ''
            )
            . ' '
            . (string) (
                $dataset['provider_label']
                ?? ''
            )
            . ' '
            . strtoupper(
                (string) (
                    $dataset['technology']
                    ?? ''
                )
            )
        );

    $state['message'] =
        'Downloading '
        . $label
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

    llama_fcc_sync_save_state(
        $state
    );

    return $state;
}


function llama_fcc_chunked_download_step(): array
{
    $state =
        llama_fcc_sync_load_state();

    if (!$state) {
        $state =
            llama_fcc_sync_create_plan();
    }

    if (
        in_array(
            (string) (
                $state['status']
                ?? ''
            ),
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
        (string) (
            $state['phase']
            ?? 'download'
        ) !== 'download'
    ) {
        return llama_fcc_sync_step();
    }

    $dataset =
        llama_fcc_chunk_current_dataset(
            $state
        );

    if (!$dataset) {
        return llama_fcc_sync_step();
    }

    try {
        $state['status'] =
            'running';

        $state['error'] =
            null;

        llama_fcc_sync_save_state(
            $state
        );

        return llama_fcc_chunk_fetch(
            $state,
            $dataset
        );

    } catch (Throwable $e) {
        $state['status'] =
            'error';

        $state['error'] =
            $e->getMessage();

        $state['message'] =
            'FCC sync stopped on an error.';

        llama_fcc_sync_save_state(
            $state
        );

        return $state;
    }
}

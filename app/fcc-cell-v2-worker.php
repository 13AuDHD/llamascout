<?php

declare(strict_types=1);

require_once __DIR__ . '/fcc-cell-v2.php';

/* =========================================================
   LLAMA SCOUT
   CELL COVERAGE V2 BACKGROUND FACTORY

   Manual start, server-driven execution. No cron required.
   Each worker is launched by the authenticated Admin page,
   detaches from the browser request, and works from durable DB
   queues until the run finishes.
   ========================================================= */

const LLAMA_FCC_V2_DOWNLOAD_TIMEOUT = 1800;
const LLAMA_FCC_V2_LOW_SPEED_LIMIT = 1024;
const LLAMA_FCC_V2_LOW_SPEED_TIME = 90;
const LLAMA_FCC_V2_PROGRESS_SECONDS = 2.0;
const LLAMA_FCC_V2_MAX_DOWNLOAD_ATTEMPTS = 5;
const LLAMA_FCC_V2_STALE_WORKER_SECONDS = 120;
const LLAMA_FCC_V2_IMPORT_BATCH = 10000;

function llama_fcc_v2_worker_roles(): array
{
    return [
        'download',
        'process',
        'unpack',
        'import',
        'cleanup',
    ];
}

function llama_fcc_v2_role_queued_stage(string $role): string
{
    return match ($role) {
        'download' => 'download_queued',
        'process' => 'process_queued',
        'unpack' => 'unpack_queued',
        'import' => 'import_queued',
        'cleanup' => 'cleanup_queued',
        default => throw new InvalidArgumentException('Unknown V2 worker role.'),
    };
}

function llama_fcc_v2_role_waiting_stage(string $role): string
{
    return match ($role) {
        'download' => 'waiting_download',
        'process' => 'process_waiting',
        'unpack' => 'unpack_waiting',
        'import' => 'import_waiting',
        'cleanup' => 'cleanup_waiting',
        default => throw new InvalidArgumentException('Unknown V2 worker role.'),
    };
}

function llama_fcc_v2_role_active_stage(string $role): string
{
    return match ($role) {
        'download' => 'downloading',
        'process' => 'processing',
        'unpack' => 'unpacking',
        'import' => 'importing',
        'cleanup' => 'cleanup',
        default => throw new InvalidArgumentException('Unknown V2 worker role.'),
    };
}

function llama_fcc_v2_role_attempt_column(string $role): string
{
    return match ($role) {
        'download' => 'download_attempts',
        'process' => 'process_attempts',
        'unpack' => 'unpack_attempts',
        'import' => 'import_attempts',
        'cleanup' => 'cleanup_attempts',
        default => throw new InvalidArgumentException('Unknown V2 worker role.'),
    };
}

function llama_fcc_v2_role_display_state(string $role): string
{
    return match ($role) {
        'download' => 'downloading',
        'process' => 'processing',
        'unpack' => 'unpacking',
        'import' => 'importing',
        'cleanup' => 'cleanup',
        default => 'preparing',
    };
}

function llama_fcc_v2_next_waiting_stage(string $role): ?string
{
    return match ($role) {
        'download' => 'process_waiting',
        'process' => 'unpack_waiting',
        'unpack' => 'import_waiting',
        'import' => 'cleanup_waiting',
        'cleanup' => null,
        default => null,
    };
}

function llama_fcc_v2_worker_limit(string $role): int
{
    return max(
        1,
        min(
            8,
            (int) llama_fcc_v2_setting($role . '_workers', '1')
        )
    );
}

function llama_fcc_v2_queue_limit(string $role): int
{
    $default = match ($role) {
        'download' => 2,
        'process' => 2,
        'unpack' => 6,
        'import' => 2,
        'cleanup' => 3,
        default => 2,
    };

    return max(
        1,
        min(
            24,
            (int) llama_fcc_v2_setting(
                $role . '_queue_limit',
                (string) $default
            )
        )
    );
}

function llama_fcc_v2_work_directory(): string
{
    $directory = llama_fcc_sync_directory() . '/v2';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The Cell Coverage V2 working directory could not be created.'
        );
    }

    return $directory;
}

function llama_fcc_v2_remove_tree(string $path): void
{
    if ($path === '' || !file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
}

function llama_fcc_v2_job_paths(array $job): array
{
    $runId = max(1, (int) ($job['run_id'] ?? 0));
    $jobId = max(1, (int) ($job['id'] ?? 0));

    $runDirectory =
        llama_fcc_v2_work_directory()
        . '/run_'
        . $runId;

    if (
        !is_dir($runDirectory)
        && !mkdir($runDirectory, 0750, true)
        && !is_dir($runDirectory)
    ) {
        throw new RuntimeException(
            'The V2 run working directory could not be created.'
        );
    }

    $base = 'job_' . $jobId;
    $gpkgName =
        'fccv2_r'
        . $runId
        . '_j'
        . $jobId
        . '.gpkg';

    return [
        'run_directory' => $runDirectory,
        'download' => $runDirectory . '/' . $base . '.download',
        'extract' => $runDirectory . '/' . $base . '_extract',
        'gpkg_name' => $gpkgName,
        'gpkg' => llama_fcc_sync_directory() . '/' . $gpkgName,
    ];
}

function llama_fcc_v2_worker_lock_path(
    int $runId,
    string $role,
    int $slot
): string {
    return
        llama_fcc_v2_work_directory()
        . '/worker_'
        . $runId
        . '_'
        . preg_replace('/[^a-z]/', '', strtolower($role))
        . '_'
        . $slot
        . '.lock';
}

function llama_fcc_v2_download_url(array $job): string
{
    $fileId = trim((string) ($job['fcc_file_id'] ?? ''));

    if ($fileId === '') {
        throw new RuntimeException(
            'The FCC catalog entry does not contain a downloadable file ID.'
        );
    }

    return
        'https://broadbandmap.fcc.gov/api/public/map'
        . '/downloads/downloadFile/availability/'
        . rawurlencode($fileId)
        . '/'
        . LLAMA_FCC_DOWNLOAD_FORMAT_GEOPACKAGE;
}

function llama_fcc_v2_job_label(array $job): string
{
    return trim(
        (string) ($job['state_name'] ?? '')
        . ' · '
        . (string) ($job['provider_label'] ?? '')
        . ' · '
        . strtoupper((string) ($job['technology'] ?? ''))
    );
}

function llama_fcc_v2_worker_touch(
    int $runId,
    string $role,
    int $slot,
    string $status,
    ?int $jobId,
    string $message
): void {
    $allowed = [
        'idle',
        'starting',
        'working',
        'waiting',
        'error',
    ];

    if (!in_array($status, $allowed, true)) {
        $status = 'working';
    }

    $workerKey = $runId . ':' . $role . ':' . $slot;

    $stmt = cell_db()->prepare(
        'INSERT INTO cell_coverage_v2_workers (
            worker_key,
            run_id,
            role,
            worker_slot,
            status,
            current_job_id,
            message,
            heartbeat_at,
            started_at,
            finished_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL)
         ON DUPLICATE KEY UPDATE
            started_at = CASE
                WHEN cell_coverage_v2_workers.status IN ("idle", "error")
                    THEN UTC_TIMESTAMP()
                ELSE cell_coverage_v2_workers.started_at
            END,
            run_id = VALUES(run_id),
            role = VALUES(role),
            worker_slot = VALUES(worker_slot),
            status = VALUES(status),
            current_job_id = VALUES(current_job_id),
            message = VALUES(message),
            heartbeat_at = UTC_TIMESTAMP(),
            finished_at = CASE
                WHEN VALUES(status) = "idle" THEN UTC_TIMESTAMP()
                ELSE NULL
            END'
    );

    $stmt->execute([
        $workerKey,
        $runId,
        $role,
        $slot,
        $status,
        $jobId,
        mb_substr($message, 0, 255),
    ]);
}

function llama_fcc_v2_run_row(int $runId): ?array
{
    $stmt = cell_db()->prepare(
        'SELECT *
         FROM cell_coverage_v2_runs
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$runId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_fcc_v2_job_row(int $jobId): ?array
{
    $stmt = cell_db()->prepare(
        'SELECT *
         FROM cell_coverage_v2_jobs
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$jobId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_fcc_v2_refresh_run_counts(int $runId): array
{
    $db = cell_db();

    /*
     * A catalog slot that has no FCC file is not a worker failure.
     * V2 originally stored those terminal "Missing" slots as stage=error,
     * which left a finished 303/306 run looking stuck at 99%.
     *
     * Normalize old V2 runs in place so this hotfix repairs the current
     * run automatically the next time the dashboard polls status.
     */
    $normalizeMissing = $db->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET stage = "missing",
             needs_work = 0,
             worker_role = NULL,
             worker_slot = NULL,
             completed_at = COALESCE(completed_at, UTC_TIMESTAMP())
         WHERE run_id = ?
           AND stage = "error"
           AND fcc_file_id IS NULL'
    );
    $normalizeMissing->execute([$runId]);

    $stmt = $db->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(stage = "complete") AS completed,
            SUM(stage = "error") AS errors,
            SUM(stage = "missing") AS missing,
            SUM(stage NOT IN ("complete", "error", "missing")) AS pending
         FROM cell_coverage_v2_jobs
         WHERE run_id = ?'
    );
    $stmt->execute([$runId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int) ($counts['total'] ?? 0);
    $completed = (int) ($counts['completed'] ?? 0);
    $errors = (int) ($counts['errors'] ?? 0);
    $missing = (int) ($counts['missing'] ?? 0);
    $pending = (int) ($counts['pending'] ?? 0);

    $run = llama_fcc_v2_run_row($runId);
    $previousStatus = (string) ($run['status'] ?? 'planning');

    if ($pending > 0) {
        $status = 'running';
        $completedAt = null;
    } else {
        /*
         * Missing means FCC did not provide/resolve an expected slot.
         * It is a completed audit result, not a broken worker.
         *
         * IMPORTANT: do not stamp a new completion time on every status
         * poll. The old behavior made the completed-run timer keep
         * counting forever. Derive the terminal time from the jobs so an
         * already-completed run also repairs itself after this update.
         */
        $status = $errors > 0 ? 'error' : 'complete';

        $completionStmt = $db->prepare(
            'SELECT MAX(completed_at)
             FROM cell_coverage_v2_jobs
             WHERE run_id = ?
               AND stage IN ("complete", "error", "missing")'
        );
        $completionStmt->execute([$runId]);

        $jobCompletedAt = trim(
            (string) ($completionStmt->fetchColumn() ?: '')
        );

        $completedAt = $jobCompletedAt !== ''
            ? $jobCompletedAt
            : (
                trim((string) ($run['completed_at'] ?? '')) !== ''
                    ? trim((string) $run['completed_at'])
                    : gmdate('Y-m-d H:i:s')
            );
    }

    $update = $db->prepare(
        'UPDATE cell_coverage_v2_runs
         SET expected_jobs = ?,
             queued_jobs = ?,
             completed_jobs = ?,
             error_jobs = ?,
             missing_jobs = ?,
             status = ?,
             started_at = COALESCE(started_at, UTC_TIMESTAMP()),
             completed_at = ?
         WHERE id = ?'
    );

    $update->execute([
        $total,
        $pending,
        $completed,
        $errors,
        $missing,
        $status,
        $completedAt,
        $runId,
    ]);

    if ($status !== $previousStatus) {
        if ($status === 'complete') {
            llama_fcc_v2_log_event(
                $runId,
                null,
                $missing > 0 ? 'warning' : 'success',
                $missing > 0
                    ? 'run_complete_with_missing'
                    : 'run_complete',
                $missing > 0
                    ? (
                        'Cell Coverage V2 finished: '
                        . $completed . ' of ' . $total
                        . ' datasets current, '
                        . $missing . ' FCC catalog slot'
                        . ($missing === 1 ? '' : 's')
                        . ' unresolved.'
                    )
                    : (
                        'Cell Coverage V2 sync completed successfully: '
                        . $completed . ' of ' . $total . ' datasets current.'
                    )
            );
        } elseif ($status === 'error' && $pending === 0) {
            llama_fcc_v2_log_event(
                $runId,
                null,
                'warning',
                'run_finished_with_errors',
                'Cell Coverage V2 finished with '
                    . $errors . ' dataset error'
                    . ($errors === 1 ? '' : 's')
                    . ($missing > 0
                        ? ' and ' . $missing . ' unresolved FCC catalog slot'
                            . ($missing === 1 ? '' : 's')
                        : '')
                    . '. Use Check Errors to retry them.'
            );
        }
    }

    return [
        'total' => $total,
        'completed' => $completed,
        'errors' => $errors,
        'missing' => $missing,
        'pending' => $pending,
        'status' => $status,
    ];
}

function llama_fcc_v2_fill_queue(
    int $runId,
    string $role
): int {
    $waiting = llama_fcc_v2_role_waiting_stage($role);
    $queued = llama_fcc_v2_role_queued_stage($role);
    $limit = llama_fcc_v2_queue_limit($role);

    $stmt = cell_db()->prepare(
        'SELECT COUNT(*)
         FROM cell_coverage_v2_jobs
         WHERE run_id = ?
           AND stage = ?'
    );
    $stmt->execute([$runId, $queued]);
    $queuedCount = (int) $stmt->fetchColumn();

    $open = max(0, $limit - $queuedCount);

    if ($open <= 0) {
        return 0;
    }

    $sql =
        'UPDATE cell_coverage_v2_jobs
         SET stage = ?
         WHERE run_id = ?
           AND stage = ?
         ORDER BY queue_order
         LIMIT ' . $open;

    $update = cell_db()->prepare($sql);
    $update->execute([
        $queued,
        $runId,
        $waiting,
    ]);

    return $update->rowCount();
}

function llama_fcc_v2_fill_queues(int $runId): void
{
    foreach (llama_fcc_v2_worker_roles() as $role) {
        llama_fcc_v2_fill_queue($runId, $role);
    }
}

function llama_fcc_v2_downstream_has_capacity(
    int $runId,
    string $role
): bool {
    $nextRole = match ($role) {
        'download' => 'process',
        'process' => 'unpack',
        'unpack' => 'import',
        'import' => 'cleanup',
        default => null,
    };

    if ($nextRole === null) {
        return true;
    }

    $stages = [
        llama_fcc_v2_role_waiting_stage($nextRole),
        llama_fcc_v2_role_queued_stage($nextRole),
        llama_fcc_v2_role_active_stage($nextRole),
    ];

    $placeholders = implode(',', array_fill(0, count($stages), '?'));
    $stmt = cell_db()->prepare(
        'SELECT COUNT(*)
         FROM cell_coverage_v2_jobs
         WHERE run_id = ?
           AND stage IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge([$runId], $stages));

    $backlog = (int) $stmt->fetchColumn();
    $capacity =
        llama_fcc_v2_queue_limit($nextRole)
        + llama_fcc_v2_worker_limit($nextRole);

    return $backlog < $capacity;
}

function llama_fcc_v2_claim_job(
    int $runId,
    string $role,
    int $workerSlot
): ?array {
    if (!llama_fcc_v2_downstream_has_capacity($runId, $role)) {
        return null;
    }

    llama_fcc_v2_fill_queues($runId);

    $queued = llama_fcc_v2_role_queued_stage($role);
    $active = llama_fcc_v2_role_active_stage($role);
    $attemptColumn = llama_fcc_v2_role_attempt_column($role);

    $sql =
        'UPDATE cell_coverage_v2_jobs
         SET stage = ?,
             worker_role = ?,
             worker_slot = ?,
             ' . $attemptColumn . ' = ' . $attemptColumn . ' + 1,
             stage_started_at = UTC_TIMESTAMP(),
             stage_completed_at = NULL,
             started_at = COALESCE(started_at, UTC_TIMESTAMP()),
             last_error = NULL
         WHERE run_id = ?
           AND stage = ?
         ORDER BY queue_order
         LIMIT 1';

    $update = cell_db()->prepare($sql);
    $update->execute([
        $active,
        $role,
        $workerSlot,
        $runId,
        $queued,
    ]);

    if ($update->rowCount() < 1) {
        return null;
    }

    $stmt = cell_db()->prepare(
        'SELECT *
         FROM cell_coverage_v2_jobs
         WHERE run_id = ?
           AND worker_role = ?
           AND worker_slot = ?
           AND stage = ?
         ORDER BY stage_started_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        $runId,
        $role,
        $workerSlot,
        $active,
    ]);

    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        return null;
    }

    $slotState = llama_fcc_v2_role_display_state($role);
    $slot = cell_db()->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = ?,
             active_run_id = ?,
             active_job_id = ?,
             last_error = NULL,
             state_changed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $slot->execute([
        $slotState,
        $runId,
        (int) $job['id'],
        (int) $job['slot_id'],
    ]);

    return $job;
}

function llama_fcc_v2_release_job(
    array $job,
    string $nextStage,
    string $slotState,
    ?string $diagnostic = null
): void {
    $db = cell_db();

    $stmt = $db->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET stage = ?,
             worker_role = NULL,
             worker_slot = NULL,
             diagnostic = ?,
             stage_completed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $nextStage,
        $diagnostic,
        (int) $job['id'],
    ]);

    $slot = $db->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = ?,
             last_diagnostic = ?,
             state_changed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $slot->execute([
        $slotState,
        $diagnostic,
        (int) $job['slot_id'],
    ]);

    llama_fcc_v2_fill_queues((int) $job['run_id']);
    llama_fcc_v2_refresh_run_counts((int) $job['run_id']);
}

function llama_fcc_v2_mark_job_error(
    array $job,
    Throwable|string $error,
    ?string $diagnostic = null
): void {
    $message = $error instanceof Throwable
        ? $error->getMessage()
        : (string) $error;

    $message = trim($message) !== ''
        ? trim($message)
        : 'Unknown Cell Coverage V2 worker error.';

    $db = cell_db();
    $stmt = $db->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET stage = "error",
             worker_role = NULL,
             worker_slot = NULL,
             last_error = ?,
             diagnostic = ?,
             stage_completed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        mb_substr($message, 0, 4000),
        $diagnostic,
        (int) $job['id'],
    ]);

    $slot = $db->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = "error",
             last_error = ?,
             last_diagnostic = ?,
             state_changed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $slot->execute([
        mb_substr($message, 0, 4000),
        $diagnostic,
        (int) $job['slot_id'],
    ]);

    llama_fcc_v2_log_event(
        (int) $job['run_id'],
        (int) $job['id'],
        'error',
        'job_error',
        llama_fcc_v2_job_label($job) . ': ' . $message,
        [
            'stage' => (string) ($job['stage'] ?? ''),
            'diagnostic' => $diagnostic,
        ]
    );

    llama_fcc_v2_refresh_run_counts((int) $job['run_id']);
}

function llama_fcc_v2_format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes) . ' B';
}

function llama_fcc_v2_download_job(
    array $job,
    int $workerSlot
): void {
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is required for Cell Coverage V2 downloads.'
        );
    }

    $paths = llama_fcc_v2_job_paths($job);
    $label = llama_fcc_v2_job_label($job);
    $jobId = (int) $job['id'];
    $runId = (int) $job['run_id'];
    $attempt = max(1, (int) ($job['download_attempts'] ?? 1));

    llama_fcc_v2_log_event(
        $runId,
        $jobId,
        'info',
        'download_started',
        'Downloading ' . $label . '.'
    );

    while ($attempt <= LLAMA_FCC_V2_MAX_DOWNLOAD_ATTEMPTS) {
        @unlink($paths['download']);

        $stream = fopen($paths['download'], 'wb');
        if (!$stream) {
            throw new RuntimeException(
                'The V2 temporary download file could not be created.'
            );
        }

        $config = llama_fcc_sync_config();
        $curl = llama_fcc_sync_curl_base(
            llama_fcc_v2_download_url($job)
        );

        $retryAfter = null;
        $lastProgress = microtime(true);

        $progressStmt = cell_db()->prepare(
            'UPDATE cell_coverage_v2_jobs
             SET downloaded_bytes = ?,
                 download_total_bytes = CASE
                    WHEN ? > 0 THEN ?
                    ELSE download_total_bytes
                 END,
                 diagnostic = ?
             WHERE id = ?'
        );

        curl_setopt_array(
            $curl,
            [
                CURLOPT_HTTPHEADER => [
                    'Accept: application/octet-stream',
                    'username: ' . $config['username'],
                    'hash_value: ' . $config['hash_value'],
                ],
                CURLOPT_FILE => $stream,
                CURLOPT_TIMEOUT => LLAMA_FCC_V2_DOWNLOAD_TIMEOUT,
                CURLOPT_LOW_SPEED_LIMIT => LLAMA_FCC_V2_LOW_SPEED_LIMIT,
                CURLOPT_LOW_SPEED_TIME => LLAMA_FCC_V2_LOW_SPEED_TIME,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_HEADERFUNCTION =>
                    static function (
                        CurlHandle $handle,
                        string $line
                    ) use (&$retryAfter): int {
                        $length = strlen($line);
                        $trimmed = trim($line);

                        if (preg_match('/^HTTP\/\S+\s+\d{3}/i', $trimmed)) {
                            $retryAfter = null;
                            return $length;
                        }

                        if (stripos($trimmed, 'Retry-After:') === 0) {
                            $value = trim(substr($trimmed, 12));
                            if (ctype_digit($value)) {
                                $retryAfter = max(1, (int) $value);
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
                        &$lastProgress,
                        $progressStmt,
                        $jobId,
                        $runId,
                        $workerSlot,
                        $label
                    ): int {
                        $now = microtime(true);

                        if (($now - $lastProgress) < LLAMA_FCC_V2_PROGRESS_SECONDS) {
                            return 0;
                        }

                        $downloaded = max(0, (int) round($downloadNow));
                        $total = max(0, (int) round($downloadTotal));
                        $message =
                            'Downloading '
                            . $label
                            . ': '
                            . llama_fcc_v2_format_bytes($downloaded)
                            . ($total > 0
                                ? ' / ' . llama_fcc_v2_format_bytes($total)
                                : '');

                        try {
                            $progressStmt->execute([
                                $downloaded,
                                $total,
                                $total,
                                $message,
                                $jobId,
                            ]);

                            llama_fcc_v2_worker_touch(
                                $runId,
                                'download',
                                $workerSlot,
                                'working',
                                $jobId,
                                $message
                            );
                        } catch (Throwable) {
                            return 1;
                        }

                        $lastProgress = $now;
                        return 0;
                    },
            ]
        );

        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $reportedTotal = 0;

        if (defined('CURLINFO_CONTENT_LENGTH_DOWNLOAD_T')) {
            $candidate = curl_getinfo(
                $curl,
                CURLINFO_CONTENT_LENGTH_DOWNLOAD_T
            );
            if (is_int($candidate)) {
                $reportedTotal = max(0, $candidate);
            }
        }

        curl_close($curl);
        fflush($stream);
        fclose($stream);
        clearstatcache(true, $paths['download']);

        $downloaded = is_file($paths['download'])
            ? (int) filesize($paths['download'])
            : 0;

        $transientStatus = in_array(
            $status,
            [408, 425, 429, 500, 502, 503, 504],
            true
        );
        $transientCurl = in_array(
            $curlErrno,
            [
                CURLE_OPERATION_TIMEDOUT,
                CURLE_COULDNT_CONNECT,
                CURLE_COULDNT_RESOLVE_HOST,
                CURLE_RECV_ERROR,
                CURLE_PARTIAL_FILE,
            ],
            true
        );

        if ($ok !== false && $status === 200 && $downloaded > 0) {
            $total = $reportedTotal > 0 ? $reportedTotal : $downloaded;

            $stmt = cell_db()->prepare(
                'UPDATE cell_coverage_v2_jobs
                 SET downloaded_bytes = ?,
                     download_total_bytes = ?,
                     download_filename = ?,
                     diagnostic = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $downloaded,
                $total,
                basename($paths['download']),
                'Download complete: ' . llama_fcc_v2_format_bytes($downloaded) . '.',
                $jobId,
            ]);

            llama_fcc_v2_log_event(
                $runId,
                $jobId,
                'success',
                'download_complete',
                'Downloaded ' . $label . ': '
                    . llama_fcc_v2_format_bytes($downloaded) . '.'
            );

            llama_fcc_v2_release_job(
                $job,
                'process_waiting',
                'processing',
                'Download complete. Waiting for verification.'
            );
            return;
        }

        @unlink($paths['download']);

        if (
            ($transientStatus || $transientCurl)
            && $attempt < LLAMA_FCC_V2_MAX_DOWNLOAD_ATTEMPTS
        ) {
            $delay = $retryAfter
                ?? min(60, 10 * (2 ** min(3, $attempt - 1)));

            $diagnostic =
                'Temporary FCC download failure'
                . ($status > 0 ? ' HTTP ' . $status : '')
                . ($curlErrno > 0 ? ' cURL ' . $curlErrno : '')
                . ($curlError !== '' ? ': ' . $curlError : '')
                . '. Retrying in ' . $delay . ' seconds.';

            $update = cell_db()->prepare(
                'UPDATE cell_coverage_v2_jobs
                 SET download_attempts = download_attempts + 1,
                     diagnostic = ?
                 WHERE id = ?'
            );
            $update->execute([$diagnostic, $jobId]);

            llama_fcc_v2_worker_touch(
                $runId,
                'download',
                $workerSlot,
                'waiting',
                $jobId,
                $diagnostic
            );

            sleep($delay);
            $attempt++;
            continue;
        }

        throw new RuntimeException(
            'FCC download failed'
            . ($status > 0 ? ' with HTTP ' . $status : '')
            . ($curlError !== '' ? ': ' . $curlError : '.')
        );
    }

    throw new RuntimeException(
        'FCC download retry limit reached.'
    );
}

function llama_fcc_v2_process_job(array $job): void
{
    $paths = llama_fcc_v2_job_paths($job);

    if (!is_file($paths['download'])) {
        throw new RuntimeException(
            'The downloaded FCC file is missing before verification.'
        );
    }

    $actual = (int) filesize($paths['download']);
    $expected = max(0, (int) ($job['download_total_bytes'] ?? 0));

    if ($actual <= 0 || ($expected > 0 && $actual < $expected)) {
        throw new RuntimeException(
            'The FCC download is incomplete.'
        );
    }

    $head = file_get_contents(
        $paths['download'],
        false,
        null,
        0,
        16
    );

    if (!is_string($head)) {
        throw new RuntimeException(
            'The downloaded FCC file could not be inspected.'
        );
    }

    if (str_starts_with($head, 'SQLite format 3')) {
        $format = 'GeoPackage';
    } elseif (str_starts_with($head, 'PK')) {
        $format = 'ZIP archive';
    } else {
        throw new RuntimeException(
            'The FCC download is not a GeoPackage or ZIP archive.'
        );
    }

    llama_fcc_v2_log_event(
        (int) $job['run_id'],
        (int) $job['id'],
        'success',
        'download_verified',
        llama_fcc_v2_job_label($job) . ': verified ' . $format . '.'
    );

    llama_fcc_v2_release_job(
        $job,
        'unpack_waiting',
        'unpacking',
        'Verified ' . $format . '. Waiting to unpack.'
    );
}

function llama_fcc_v2_unpack_job(array $job): void
{
    $paths = llama_fcc_v2_job_paths($job);

    if (is_file($paths['gpkg'])) {
        $head = file_get_contents($paths['gpkg'], false, null, 0, 16);
        if (is_string($head) && str_starts_with($head, 'SQLite format 3')) {
            llama_fcc_v2_release_job(
                $job,
                'import_waiting',
                'importing',
                'GeoPackage already unpacked. Waiting for import.'
            );
            return;
        }
        @unlink($paths['gpkg']);
    }

    if (!is_file($paths['download'])) {
        throw new RuntimeException(
            'The verified FCC download is missing before unpacking.'
        );
    }

    $head = file_get_contents($paths['download'], false, null, 0, 16);

    if (is_string($head) && str_starts_with($head, 'SQLite format 3')) {
        if (!rename($paths['download'], $paths['gpkg'])) {
            throw new RuntimeException(
                'The downloaded GeoPackage could not be moved into the import queue.'
            );
        }
    } elseif (is_string($head) && str_starts_with($head, 'PK')) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'PHP ZipArchive is required to unpack FCC downloads.'
            );
        }

        llama_fcc_v2_remove_tree($paths['extract']);

        if (!mkdir($paths['extract'], 0750, true)) {
            throw new RuntimeException(
                'The V2 FCC extraction directory could not be created.'
            );
        }

        $zip = new ZipArchive();
        $opened = $zip->open($paths['download']);

        if ($opened !== true) {
            llama_fcc_v2_remove_tree($paths['extract']);
            throw new RuntimeException(
                'The FCC ZIP archive could not be opened.'
            );
        }

        if (!$zip->extractTo($paths['extract'])) {
            $zip->close();
            llama_fcc_v2_remove_tree($paths['extract']);
            throw new RuntimeException(
                'The FCC ZIP archive could not be extracted.'
            );
        }
        $zip->close();

        $source = null;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $paths['extract'],
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && strtolower($file->getExtension()) === 'gpkg'
            ) {
                $source = $file->getPathname();
                break;
            }
        }

        if (!$source) {
            llama_fcc_v2_remove_tree($paths['extract']);
            throw new RuntimeException(
                'The FCC ZIP archive does not contain a GeoPackage.'
            );
        }

        if (!rename($source, $paths['gpkg'])) {
            llama_fcc_v2_remove_tree($paths['extract']);
            throw new RuntimeException(
                'The extracted GeoPackage could not be moved into the import queue.'
            );
        }

        llama_fcc_v2_remove_tree($paths['extract']);
        @unlink($paths['download']);
    } else {
        throw new RuntimeException(
            'The verified FCC file changed before unpacking.'
        );
    }

    $verify = file_get_contents($paths['gpkg'], false, null, 0, 16);
    if (!is_string($verify) || !str_starts_with($verify, 'SQLite format 3')) {
        @unlink($paths['gpkg']);
        throw new RuntimeException(
            'The unpacked FCC file is not a readable GeoPackage.'
        );
    }

    $stmt = cell_db()->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET gpkg_filename = ?,
             diagnostic = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $paths['gpkg_name'],
        'GeoPackage ready for import.',
        (int) $job['id'],
    ]);

    llama_fcc_v2_log_event(
        (int) $job['run_id'],
        (int) $job['id'],
        'success',
        'unpack_complete',
        llama_fcc_v2_job_label($job) . ': GeoPackage ready for import.'
    );

    llama_fcc_v2_release_job(
        $job,
        'import_waiting',
        'importing',
        'GeoPackage ready. Waiting for import.'
    );
}

function llama_fcc_v2_import_job(
    array $job,
    int $workerSlot
): void {
    $paths = llama_fcc_v2_job_paths($job);
    $filename = trim((string) ($job['gpkg_filename'] ?? ''));

    if ($filename === '') {
        $filename = $paths['gpkg_name'];
    }

    if (!is_file(llama_fcc_sync_directory() . '/' . basename($filename))) {
        throw new RuntimeException(
            'The unpacked GeoPackage is missing before import.'
        );
    }

    $offset = max(0, (int) ($job['import_offset'] ?? 0));
    $total = max(0, (int) ($job['import_total_rows'] ?? 0));
    $runId = (int) $job['run_id'];
    $jobId = (int) $job['id'];
    $label = llama_fcc_v2_job_label($job);

    llama_fcc_v2_log_event(
        $runId,
        $jobId,
        'info',
        'import_started',
        'Importing ' . $label
            . ($offset > 0 ? ' from row ' . number_format($offset) : '')
            . '.'
    );

    while (true) {
        llama_fcc_v2_worker_touch(
            $runId,
            'import',
            $workerSlot,
            'working',
            $jobId,
            'Importing ' . $label
                . ($total > 0
                    ? ': ' . number_format($offset) . ' / ' . number_format($total)
                    : '...')
        );

        $result = llama_cell_import_batch(
            cell_db(),
            basename($filename),
            (string) $job['state_fips'],
            (string) $job['target_as_of_date'],
            $offset,
            LLAMA_FCC_V2_IMPORT_BATCH
        );

        $nextOffset = max(0, (int) ($result['next_offset'] ?? $offset));
        $total = max(0, (int) ($result['total_rows'] ?? $total));
        $batchCells = max(0, (int) ($result['cells_written'] ?? 0));
        $done = !empty($result['done']);

        $stmt = cell_db()->prepare(
            'UPDATE cell_coverage_v2_jobs
             SET import_offset = ?,
                 import_total_rows = ?,
                 imported_rows = ?,
                 cells_written = cells_written + ?,
                 diagnostic = ?
             WHERE id = ?'
        );

        $diagnostic = $done
            ? 'Import complete.'
            : 'Imported source rows '
                . number_format($nextOffset)
                . ' / '
                . number_format($total)
                . '.';

        $stmt->execute([
            $nextOffset,
            $total,
            $nextOffset,
            $batchCells,
            $diagnostic,
            $jobId,
        ]);

        if ($done) {
            llama_fcc_v2_log_event(
                $runId,
                $jobId,
                'success',
                'import_complete',
                'Imported ' . $label . ': '
                    . number_format($total) . ' source rows processed.'
            );

            llama_fcc_v2_release_job(
                $job,
                'cleanup_waiting',
                'cleanup',
                'Import complete. Waiting for cleanup.'
            );
            return;
        }

        if ($nextOffset <= $offset) {
            throw new RuntimeException(
                'The FCC importer did not advance to the next batch.'
            );
        }

        $offset = $nextOffset;
    }
}

function llama_fcc_v2_cleanup_job(array $job): void
{
    $paths = llama_fcc_v2_job_paths($job);
    $leftovers = [];

    if (is_file($paths['download']) && !@unlink($paths['download'])) {
        $leftovers[] = basename($paths['download']);
    }

    if (is_file($paths['gpkg']) && !@unlink($paths['gpkg'])) {
        $leftovers[] = basename($paths['gpkg']);
    }

    llama_fcc_v2_remove_tree($paths['extract']);

    $db = cell_db();
    $stmt = $db->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET stage = "complete",
             worker_role = NULL,
             worker_slot = NULL,
             completed_at = UTC_TIMESTAMP(),
             stage_completed_at = UTC_TIMESTAMP(),
             diagnostic = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $leftovers
            ? 'Coverage imported. Temporary file cleanup left: ' . implode(', ', $leftovers)
            : 'Coverage imported and temporary files removed.',
        (int) $job['id'],
    ]);

    $slot = $db->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = "current",
             installed_as_of_date = ?,
             active_run_id = NULL,
             active_job_id = NULL,
             last_error = NULL,
             last_diagnostic = ?,
             state_changed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $slot->execute([
        (string) $job['target_as_of_date'],
        $leftovers
            ? 'Coverage current; temporary cleanup warning.'
            : 'Coverage current.',
        (int) $job['slot_id'],
    ]);

    llama_fcc_v2_log_event(
        (int) $job['run_id'],
        (int) $job['id'],
        $leftovers ? 'warning' : 'success',
        'job_complete',
        llama_fcc_v2_job_label($job)
            . ' is current.'
            . ($leftovers
                ? ' Temporary cleanup left ' . implode(', ', $leftovers) . '.'
                : '')
    );

    llama_fcc_v2_fill_queues((int) $job['run_id']);
    llama_fcc_v2_refresh_run_counts((int) $job['run_id']);
}

function llama_fcc_v2_execute_job(
    array $job,
    string $role,
    int $workerSlot
): void {
    match ($role) {
        'download' => llama_fcc_v2_download_job($job, $workerSlot),
        'process' => llama_fcc_v2_process_job($job),
        'unpack' => llama_fcc_v2_unpack_job($job),
        'import' => llama_fcc_v2_import_job($job, $workerSlot),
        'cleanup' => llama_fcc_v2_cleanup_job($job),
        default => throw new InvalidArgumentException('Unknown V2 worker role.'),
    };
}

function llama_fcc_v2_requeue_owned_active_job(
    int $runId,
    string $role,
    int $workerSlot
): int {
    $active = llama_fcc_v2_role_active_stage($role);
    $queued = llama_fcc_v2_role_queued_stage($role);

    $stmt = cell_db()->prepare(
        'UPDATE cell_coverage_v2_jobs
         SET stage = ?,
             worker_role = NULL,
             worker_slot = NULL,
             diagnostic = "Recovered after a worker restart."
         WHERE run_id = ?
           AND worker_role = ?
           AND worker_slot = ?
           AND stage = ?'
    );
    $stmt->execute([
        $queued,
        $runId,
        $role,
        $workerSlot,
        $active,
    ]);

    return $stmt->rowCount();
}

function llama_fcc_v2_run_worker(
    int $runId,
    string $role,
    int $workerSlot
): void {
    if (!in_array($role, llama_fcc_v2_worker_roles(), true)) {
        throw new InvalidArgumentException('Unknown V2 worker role.');
    }

    if (
        $workerSlot < 1
        || $workerSlot > llama_fcc_v2_worker_limit($role)
    ) {
        throw new InvalidArgumentException('Invalid V2 worker slot.');
    }

    ignore_user_abort(true);
    @set_time_limit(0);

    $requeued = llama_fcc_v2_requeue_owned_active_job(
        $runId,
        $role,
        $workerSlot
    );

    llama_fcc_v2_worker_touch(
        $runId,
        $role,
        $workerSlot,
        'starting',
        null,
        $requeued > 0
            ? 'Worker restarted and recovered its interrupted job.'
            : 'Worker started.'
    );

    try {
        while (true) {
            $run = llama_fcc_v2_run_row($runId);

            if (!$run || (string) ($run['status'] ?? '') !== 'running') {
                break;
            }

            llama_fcc_v2_fill_queues($runId);

            if (!llama_fcc_v2_downstream_has_capacity($runId, $role)) {
                llama_fcc_v2_worker_touch(
                    $runId,
                    $role,
                    $workerSlot,
                    'waiting',
                    null,
                    'Waiting for the next pipeline queue to make room.'
                );
                sleep(1);
                continue;
            }

            $job = llama_fcc_v2_claim_job(
                $runId,
                $role,
                $workerSlot
            );

            if (!$job) {
                $counts = llama_fcc_v2_refresh_run_counts($runId);

                if ($counts['status'] !== 'running') {
                    break;
                }

                llama_fcc_v2_worker_touch(
                    $runId,
                    $role,
                    $workerSlot,
                    'waiting',
                    null,
                    'Waiting for work.'
                );
                sleep(1);
                continue;
            }

            $label = llama_fcc_v2_job_label($job);
            llama_fcc_v2_worker_touch(
                $runId,
                $role,
                $workerSlot,
                'working',
                (int) $job['id'],
                ucfirst($role) . ': ' . $label
            );

            try {
                llama_fcc_v2_execute_job(
                    $job,
                    $role,
                    $workerSlot
                );
            } catch (Throwable $jobError) {
                llama_log_caught_exception(
                    $jobError,
                    'cell_coverage_v2.worker.' . $role,
                    [
                        'run_id' => $runId,
                        'job_id' => (int) $job['id'],
                        'worker_slot' => $workerSlot,
                    ]
                );

                llama_fcc_v2_mark_job_error(
                    $job,
                    $jobError,
                    ucfirst($role) . ' worker ' . $workerSlot . ' stopped this dataset.'
                );
            }
        }

        llama_fcc_v2_worker_touch(
            $runId,
            $role,
            $workerSlot,
            'idle',
            null,
            'Worker finished.'
        );
    } catch (Throwable $workerError) {
        llama_fcc_v2_worker_touch(
            $runId,
            $role,
            $workerSlot,
            'error',
            null,
            $workerError->getMessage()
        );
        throw $workerError;
    }
}

function llama_fcc_v2_create_run(int $startedBy): array
{
    llama_fcc_v2_require_schema();
    llama_fcc_v2_seed_slots();

    $active = cell_db()->query(
        'SELECT *
         FROM cell_coverage_v2_runs
         WHERE status IN ("planning", "running")
         ORDER BY id DESC
         LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    if ($active) {
        return [
            'run' => $active,
            'already_running' => true,
        ];
    }

    $latest = llama_fcc_v2_latest_catalog();
    $targetDate = (string) $latest['as_of_date'];
    $catalog = (array) $latest['catalog'];
    $current = llama_fcc_v2_current_ledger();

    llama_fcc_v2_set_setting('fcc_latest_date', $targetDate);
    llama_fcc_v2_set_setting('fcc_latest_checked_at', gmdate('c'));
    llama_fcc_v2_refresh_slots($targetDate, $catalog);

    $db = cell_db();
    $db->beginTransaction();

    try {
        $token = bin2hex(random_bytes(16));
        $runStmt = $db->prepare(
            'INSERT INTO cell_coverage_v2_runs (
                run_token,
                mode,
                target_as_of_date,
                status,
                expected_jobs,
                started_by,
                started_at
             ) VALUES (?, "sync", ?, "planning", ?, ?, UTC_TIMESTAMP())'
        );
        $runStmt->execute([
            $token,
            $targetDate,
            LLAMA_FCC_V2_EXPECTED_SLOT_COUNT,
            $startedBy > 0 ? $startedBy : null,
        ]);

        $runId = (int) $db->lastInsertId();

        $slots = $db->query(
            'SELECT *
             FROM cell_coverage_v2_slots
             ORDER BY display_order'
        )->fetchAll(PDO::FETCH_ASSOC);

        $insert = $db->prepare(
            'INSERT INTO cell_coverage_v2_jobs (
                run_id,
                slot_id,
                queue_order,
                state_fips,
                state_name,
                provider_key,
                provider_label,
                technology,
                target_as_of_date,
                fcc_file_id,
                fcc_file_name,
                stage,
                needs_work,
                last_error,
                diagnostic,
                completed_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $slotUpdate = $db->prepare(
            'UPDATE cell_coverage_v2_slots
             SET display_state = ?,
                 target_as_of_date = ?,
                 catalog_available = ?,
                 target_file_id = ?,
                 target_file_name = ?,
                 active_run_id = ?,
                 active_job_id = ?,
                 last_error = ?,
                 last_diagnostic = ?,
                 state_changed_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );

        foreach ($slots as $slot) {
            $key = (string) $slot['slot_key'];
            $installed = $current[$key] ?? null;
            $installedDate = is_array($installed)
                ? trim((string) ($installed['fcc_as_of_date'] ?? ''))
                : '';
            $candidate = $catalog[$key] ?? null;
            $isCurrent = $installedDate !== '' && $installedDate >= $targetDate;

            if ($isCurrent) {
                $stage = 'complete';
                $needsWork = 0;
                $error = null;
                $diagnostic = 'Already current for FCC ' . $targetDate . '.';
                $completedAt = gmdate('Y-m-d H:i:s');
                $displayState = 'current';
            } elseif (!is_array($candidate)) {
                $stage = 'missing';
                $needsWork = 0;
                $error = 'No matching FCC file was recognized for this expected dataset.';
                $diagnostic = 'Catalog audit could not resolve this slot.';
                $completedAt = gmdate('Y-m-d H:i:s');
                $displayState = $installedDate !== '' ? 'outdated' : 'missing';
            } else {
                $stage = 'waiting_download';
                $needsWork = 1;
                $error = null;
                $diagnostic = 'Waiting for the download queue.';
                $completedAt = null;
                $displayState = 'preparing';
            }

            $fileId = is_array($candidate)
                ? trim((string) ($candidate['file_id'] ?? ''))
                : '';
            $fileName = is_array($candidate)
                ? trim((string) ($candidate['file_name'] ?? ''))
                : '';

            $insert->execute([
                $runId,
                (int) $slot['id'],
                (int) $slot['display_order'],
                (string) $slot['state_fips'],
                (string) $slot['state_name'],
                (string) $slot['provider_key'],
                (string) $slot['provider_label'],
                (string) $slot['technology'],
                $targetDate,
                $fileId !== '' ? $fileId : null,
                $fileName !== '' ? $fileName : null,
                $stage,
                $needsWork,
                $error,
                $diagnostic,
                $completedAt,
            ]);

            $jobId = (int) $db->lastInsertId();

            $slotUpdate->execute([
                $displayState,
                $targetDate,
                is_array($candidate) ? 1 : 0,
                $fileId !== '' ? $fileId : null,
                $fileName !== '' ? $fileName : null,
                in_array($stage, ['complete', 'missing'], true) ? null : $runId,
                in_array($stage, ['complete', 'missing'], true) ? null : $jobId,
                $error,
                $diagnostic,
                (int) $slot['id'],
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    llama_fcc_v2_fill_queues($runId);
    $counts = llama_fcc_v2_refresh_run_counts($runId);

    llama_fcc_v2_log_event(
        $runId,
        null,
        $counts['errors'] > 0 ? 'warning' : 'info',
        'run_started',
        'Cell Coverage V2 run started for FCC '
            . $targetDate
            . ': '
            . $counts['completed']
            . ' already current, '
            . $counts['pending']
            . ' queued, '
            . $counts['errors']
            . ' unresolved.'
    );

    return [
        'run' => llama_fcc_v2_run_row($runId),
        'already_running' => false,
        'counts' => $counts,
    ];
}

function llama_fcc_v2_file_signature(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $head = file_get_contents($path, false, null, 0, 16);

    if (!is_string($head)) {
        return '';
    }

    if (str_starts_with($head, 'SQLite format 3')) {
        return 'gpkg';
    }

    if (str_starts_with($head, 'PK')) {
        return 'zip';
    }

    return '';
}

function llama_fcc_v2_manifest_diagnostic(
    array $manifest,
    array $job,
    string $targetDate
): array {
    $expectedState = trim((string) ($job['state_fips'] ?? ''));
    $expectedProvider = trim((string) ($job['provider_key'] ?? ''));
    $expectedTechnology = trim((string) ($job['technology'] ?? ''));
    $stateName = trim((string) ($job['state_name'] ?? $expectedState));
    $providerLabel = trim((string) ($job['provider_label'] ?? $expectedProvider));

    $providers = llama_fcc_v2_providers();
    $expectedProviderId = (int) ($providers[$expectedProvider]['provider_id'] ?? 0);
    $expectedTechnologyCode = $expectedTechnology === '5g' ? 500 : 400;

    $dimensionRows = 0;
    $mobileRows = 0;
    $h3Rows = 0;
    $sampleFiles = [];

    foreach ($manifest as $row) {
        if (!is_array($row)) {
            continue;
        }

        $fileName = trim((string) (
            llama_fcc_sync_row_value($row, ['file_name', 'filename', 'name'])
            ?? ''
        ));

        $stateFips = llama_fcc_sync_state_fips_from_row($row);
        if (
            $stateFips === ''
            && preg_match('/(?:^|[^0-9])bdc[_-](\\d{2})[_-]/i', $fileName, $match)
        ) {
            $stateFips = $match[1];
        }

        if ($stateFips !== $expectedState) {
            continue;
        }

        $providerId = (int) (
            llama_fcc_sync_row_value(
                $row,
                ['provider_id', 'providerid', 'providerId']
            )
            ?? 0
        );

        if (
            $providerId <= 0
            && preg_match('/(?:^|[^0-9])bdc[_-]\\d{2}[_-](\\d{6})[_-]/i', $fileName, $match)
        ) {
            $providerId = (int) $match[1];
        }

        $providerName = strtolower(trim((string) (
            llama_fcc_sync_row_value(
                $row,
                ['provider_name', 'brand_name', 'brandname']
            )
            ?? ''
        )));

        $providerMatches = $providerId === $expectedProviderId;

        if (!$providerMatches && $expectedProvider === 'tmobile') {
            $providerMatches = str_contains($providerName, 't-mobile')
                || str_contains($providerName, 'tmobile');
        } elseif (!$providerMatches && $expectedProvider === 'verizon') {
            $providerMatches = str_contains($providerName, 'verizon');
        } elseif (!$providerMatches && $expectedProvider === 'att') {
            $providerMatches = str_contains($providerName, 'at&t')
                || str_contains($providerName, 'att mobility');
        }

        if (!$providerMatches) {
            continue;
        }

        $searchText = strtolower(implode(' ', array_filter([
            $fileName,
            (string) ($row['category'] ?? ''),
            (string) ($row['subcategory'] ?? ''),
            (string) ($row['technology_type'] ?? ''),
            (string) ($row['technology_name'] ?? ''),
            (string) ($row['technology_code_desc'] ?? ''),
            (string) ($row['speed_tier'] ?? ''),
            (string) ($row['speed_tier_desc'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== '')));

        $technologyCode = 0;
        $numericTechnology = llama_fcc_sync_row_value(
            $row,
            ['technology_code', 'technologyCode']
        );

        if (is_numeric($numericTechnology)) {
            $value = (int) $numericTechnology;
            if ($value === 400 || $value === 500) {
                $technologyCode = $value;
            }
        }

        if ($technologyCode === 0) {
            if (
                str_contains($searchText, '5gnr')
                || str_contains($searchText, '5g-nr')
                || str_contains($searchText, '5g nr')
            ) {
                $technologyCode = 500;
            } elseif (
                str_contains($searchText, '4glte')
                || str_contains($searchText, '4g lte')
                || str_contains($searchText, '4g-lte')
            ) {
                $technologyCode = 400;
            }
        }

        if ($technologyCode !== $expectedTechnologyCode) {
            continue;
        }

        $dimensionRows++;

        if ($fileName !== '' && count($sampleFiles) < 3) {
            $sampleFiles[] = $fileName;
        }

        $looksMobile = str_contains($searchText, 'mobile_broadband')
            || str_contains($searchText, 'mobile broadband');
        $looksH3 = str_contains($searchText, '_h3_')
            || str_contains($searchText, ' h3 ')
            || str_contains($searchText, 'hexagon');

        if ($looksMobile) {
            $mobileRows++;
        }

        if ($looksMobile && $looksH3) {
            $h3Rows++;
        }
    }

    if ($dimensionRows === 0) {
        return [
            'code' => 'not_published',
            'message' => sprintf(
                'FCC did not publish a %s %s dataset for %s for %s.',
                $providerLabel,
                strtoupper($expectedTechnology),
                $stateName,
                $targetDate
            ),
            'dimension_rows' => 0,
            'mobile_rows' => 0,
            'h3_rows' => 0,
            'sample_files' => [],
        ];
    }

    if ($h3Rows === 0) {
        return [
            'code' => 'no_mobile_h3',
            'message' => sprintf(
                'FCC published %d related %s %s record%s for %s, but no mobile broadband H3 file for %s.',
                $dimensionRows,
                $providerLabel,
                strtoupper($expectedTechnology),
                $dimensionRows === 1 ? '' : 's',
                $stateName,
                $targetDate
            ),
            'dimension_rows' => $dimensionRows,
            'mobile_rows' => $mobileRows,
            'h3_rows' => 0,
            'sample_files' => $sampleFiles,
        ];
    }

    return [
        'code' => 'no_supported_product',
        'message' => sprintf(
            'FCC published %d mobile H3 candidate%s for %s %s in %s, but none match the FCC product Llama Scout imports for %s.',
            $h3Rows,
            $h3Rows === 1 ? '' : 's',
            $providerLabel,
            strtoupper($expectedTechnology),
            $stateName,
            $targetDate
        ),
        'dimension_rows' => $dimensionRows,
        'mobile_rows' => $mobileRows,
        'h3_rows' => $h3Rows,
        'sample_files' => $sampleFiles,
    ];
}


function llama_fcc_v2_recover_errors(int $startedBy): array
{
    llama_fcc_v2_require_schema();

    $run = llama_fcc_v2_latest_run();
    if (!$run) {
        return [
            'run' => null,
            'checked' => 0,
            'recovered' => 0,
            'still_missing' => 0,
            'not_published' => 0,
            'no_mobile_h3' => 0,
            'no_supported_product' => 0,
            'stale_requeued' => 0,
        ];
    }

    $runId = (int) $run['id'];
    $targetDate = trim((string) ($run['target_as_of_date'] ?? ''));

    if ($targetDate === '') {
        throw new RuntimeException(
            'The latest V2 run does not have a target FCC filing date.'
        );
    }

    $manifest = llama_fcc_sync_manifest($targetDate);
    $catalog = llama_fcc_sync_select_catalog($manifest, $targetDate);
    $current = llama_fcc_v2_current_ledger();
    $db = cell_db();
    $now = time();
    $staleRequeued = 0;

    $workers = $db->prepare(
        'SELECT role, worker_slot, heartbeat_at
         FROM cell_coverage_v2_workers
         WHERE run_id = ?'
    );
    $workers->execute([$runId]);
    $heartbeat = [];

    foreach ($workers->fetchAll(PDO::FETCH_ASSOC) as $worker) {
        $key = (string) $worker['role'] . ':' . (int) $worker['worker_slot'];
        $stamp = strtotime((string) ($worker['heartbeat_at'] ?? ''));
        $heartbeat[$key] = $stamp ?: 0;
    }

    foreach (llama_fcc_v2_worker_roles() as $role) {
        $active = llama_fcc_v2_role_active_stage($role);
        $queued = llama_fcc_v2_role_queued_stage($role);

        $jobs = $db->prepare(
            'SELECT id, worker_slot
             FROM cell_coverage_v2_jobs
             WHERE run_id = ?
               AND stage = ?'
        );
        $jobs->execute([$runId, $active]);

        foreach ($jobs->fetchAll(PDO::FETCH_ASSOC) as $activeJob) {
            $slot = (int) ($activeJob['worker_slot'] ?? 0);
            $key = $role . ':' . $slot;
            $seen = $heartbeat[$key] ?? 0;

            if ($seen > 0 && ($now - $seen) <= LLAMA_FCC_V2_STALE_WORKER_SECONDS) {
                continue;
            }

            $update = $db->prepare(
                'UPDATE cell_coverage_v2_jobs
                 SET stage = ?,
                     worker_role = NULL,
                     worker_slot = NULL,
                     diagnostic = "Recovered stale worker assignment."
                 WHERE id = ?'
            );
            $update->execute([
                $queued,
                (int) $activeJob['id'],
            ]);
            $staleRequeued += $update->rowCount();
        }
    }

    $errorsStmt = $db->prepare(
        'SELECT *
         FROM cell_coverage_v2_jobs
         WHERE run_id = ?
           AND stage IN ("error", "missing")
         ORDER BY queue_order'
    );
    $errorsStmt->execute([$runId]);
    $errorJobs = $errorsStmt->fetchAll(PDO::FETCH_ASSOC);

    $recovered = 0;
    $stillMissing = 0;
    $checked = 0;
    $notPublished = 0;
    $noMobileH3 = 0;
    $noSupportedProduct = 0;

    foreach ($errorJobs as $job) {
        $checked++;
        $key = llama_fcc_v2_slot_key(
            (string) $job['state_fips'],
            (string) $job['provider_key'],
            (string) $job['technology']
        );

        $installed = $current[$key] ?? null;
        $installedDate = is_array($installed)
            ? trim((string) ($installed['fcc_as_of_date'] ?? ''))
            : '';

        if ($installedDate !== '' && $installedDate >= $targetDate) {
            $complete = $db->prepare(
                'UPDATE cell_coverage_v2_jobs
                 SET stage = "complete",
                     needs_work = 0,
                     worker_role = NULL,
                     worker_slot = NULL,
                     last_error = NULL,
                     diagnostic = "Coverage is already current.",
                     completed_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $complete->execute([(int) $job['id']]);

            $slotUpdate = $db->prepare(
                'UPDATE cell_coverage_v2_slots
                 SET display_state = "current",
                     installed_as_of_date = ?,
                     active_run_id = NULL,
                     active_job_id = NULL,
                     last_error = NULL,
                     last_diagnostic = "Coverage is already current.",
                     state_changed_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $slotUpdate->execute([
                $installedDate,
                (int) $job['slot_id'],
            ]);
            $recovered++;

            llama_fcc_v2_log_event(
                $runId,
                (int) $job['id'],
                'success',
                'error_check_already_current',
                (string) $job['state_name']
                    . ' · ' . (string) $job['provider_label']
                    . ' · ' . strtoupper((string) $job['technology'])
                    . ' is already current.'
            );
            continue;
        }

        $candidate = $catalog[$key] ?? null;

        if (!is_array($candidate)) {
            $stillMissing++;
            $display = $installedDate !== '' ? 'outdated' : 'missing';
            $diagnostic = llama_fcc_v2_manifest_diagnostic(
                $manifest,
                $job,
                $targetDate
            );
            $message = (string) ($diagnostic['message'] ?? 'FCC file is not available.');
            $diagnosticCode = (string) ($diagnostic['code'] ?? 'not_published');

            if ($diagnosticCode === 'not_published') {
                $notPublished++;
            } elseif ($diagnosticCode === 'no_mobile_h3') {
                $noMobileH3++;
            } else {
                $noSupportedProduct++;
            }

            $jobUpdate = $db->prepare(
                'UPDATE cell_coverage_v2_jobs
                 SET fcc_file_id = NULL,
                     fcc_file_name = NULL,
                     stage = "missing",
                     needs_work = 0,
                     worker_role = NULL,
                     worker_slot = NULL,
                     last_error = NULL,
                     diagnostic = ?,
                     completed_at = COALESCE(completed_at, UTC_TIMESTAMP())
                 WHERE id = ?'
            );
            $jobUpdate->execute([
                $message,
                (int) $job['id'],
            ]);

            $slotUpdate = $db->prepare(
                'UPDATE cell_coverage_v2_slots
                 SET display_state = ?,
                     catalog_available = 0,
                     target_file_id = NULL,
                     target_file_name = NULL,
                     last_error = NULL,
                     last_diagnostic = ?,
                     state_changed_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $slotUpdate->execute([
                $display,
                $message,
                (int) $job['slot_id'],
            ]);

            llama_fcc_v2_log_event(
                $runId,
                (int) $job['id'],
                'warning',
                'missing_rechecked',
                $message,
                $diagnostic
            );
            continue;
        }

        $paths = llama_fcc_v2_job_paths($job);
        $gpkgSig = llama_fcc_v2_file_signature($paths['gpkg']);
        $downloadSig = llama_fcc_v2_file_signature($paths['download']);

        if ($gpkgSig === 'gpkg') {
            $nextStage = 'import_waiting';
            $display = 'importing';
            $diagnostic = 'Recovered existing GeoPackage and returned it to the import queue.';
        } elseif ($downloadSig === 'gpkg' || $downloadSig === 'zip') {
            $nextStage = 'process_waiting';
            $display = 'processing';
            $diagnostic = 'Recovered existing download and returned it to verification.';
        } else {
            @unlink($paths['download']);
            @unlink($paths['gpkg']);
            llama_fcc_v2_remove_tree($paths['extract']);
            $nextStage = 'waiting_download';
            $display = 'preparing';
            $diagnostic = 'No reusable local file remained; returned dataset to the download queue.';
        }

        $fileId = trim((string) ($candidate['file_id'] ?? ''));
        $fileName = trim((string) ($candidate['file_name'] ?? ''));

        $jobUpdate = $db->prepare(
            'UPDATE cell_coverage_v2_jobs
             SET fcc_file_id = ?,
                 fcc_file_name = ?,
                 stage = ?,
                 needs_work = 1,
                 worker_role = NULL,
                 worker_slot = NULL,
                 last_error = NULL,
                 diagnostic = ?,
                 completed_at = NULL,
                 download_filename = CASE
                    WHEN ? = "waiting_download" THEN NULL
                    ELSE download_filename
                 END,
                 downloaded_bytes = CASE
                    WHEN ? = "waiting_download" THEN 0
                    ELSE downloaded_bytes
                 END,
                 download_total_bytes = CASE
                    WHEN ? = "waiting_download" THEN 0
                    ELSE download_total_bytes
                 END,
                 import_offset = CASE
                    WHEN ? = "waiting_download" THEN 0
                    ELSE import_offset
                 END,
                 import_total_rows = CASE
                    WHEN ? = "waiting_download" THEN 0
                    ELSE import_total_rows
                 END,
                 imported_rows = CASE
                    WHEN ? = "waiting_download" THEN 0
                    ELSE imported_rows
                 END
             WHERE id = ?'
        );
        $jobUpdate->execute([
            $fileId !== '' ? $fileId : null,
            $fileName !== '' ? $fileName : null,
            $nextStage,
            $diagnostic,
            $nextStage,
            $nextStage,
            $nextStage,
            $nextStage,
            $nextStage,
            $nextStage,
            (int) $job['id'],
        ]);

        $slotUpdate = $db->prepare(
            'UPDATE cell_coverage_v2_slots
             SET display_state = ?,
                 catalog_available = 1,
                 target_file_id = ?,
                 target_file_name = ?,
                 last_error = NULL,
                 last_diagnostic = ?,
                 state_changed_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $slotUpdate->execute([
            $display,
            $fileId !== '' ? $fileId : null,
            $fileName !== '' ? $fileName : null,
            $diagnostic,
            (int) $job['slot_id'],
        ]);

        $recovered++;

        llama_fcc_v2_log_event(
            $runId,
            (int) $job['id'],
            'success',
            'error_check_recovered',
            (string) $job['state_name']
                . ' · ' . (string) $job['provider_label']
                . ' · ' . strtoupper((string) $job['technology'])
                . ' is now available from FCC and was returned to the factory.'
        );
    }

    llama_fcc_v2_fill_queues($runId);
    $counts = llama_fcc_v2_refresh_run_counts($runId);

    llama_fcc_v2_log_event(
        $runId,
        null,
        $stillMissing > 0 ? 'warning' : 'info',
        'error_check',
        'Check Errors checked '
            . $checked
            . ' unresolved dataset'
            . ($checked === 1 ? '' : 's')
            . ': '
            . $recovered
            . ' recovered, '
            . $notPublished
            . ' not published by FCC, '
            . $noMobileH3
            . ' without a mobile H3 file, '
            . $noSupportedProduct
            . ' without the supported FCC product, and '
            . $staleRequeued
            . ' stale worker assignment'
            . ($staleRequeued === 1 ? '' : 's')
            . ' requeued.'
    );

    return [
        'run' => llama_fcc_v2_run_row($runId),
        'checked' => $checked,
        'recovered' => $recovered,
        'still_missing' => $stillMissing,
        'not_published' => $notPublished,
        'no_mobile_h3' => $noMobileH3,
        'no_supported_product' => $noSupportedProduct,
        'stale_requeued' => $staleRequeued,
        'counts' => $counts,
    ];
}

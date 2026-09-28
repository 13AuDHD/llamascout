<?php

declare(strict_types=1);

require_once __DIR__ . '/fcc-cell-sync.php';

/* =========================================================
   LLAMA SCOUT
   CELL COVERAGE V2 FOUNDATION

   This layer is intentionally separate from the V1 sync state.
   V2 models all 306 expected state/provider/network slots even
   when the FCC manifest omits a file.
   ========================================================= */

const LLAMA_FCC_V2_EXPECTED_SLOT_COUNT = 306;

function llama_fcc_v2_tables(): array
{
    return [
        'cell_coverage_v2_settings',
        'cell_coverage_v2_runs',
        'cell_coverage_v2_slots',
        'cell_coverage_v2_jobs',
        'cell_coverage_v2_events',
        'cell_coverage_v2_workers',
    ];
}

function llama_fcc_v2_tables_ready(?PDO $db = null): bool
{
    $db ??= cell_db();

    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?'
        );

        foreach (llama_fcc_v2_tables() as $table) {
            $stmt->execute([$table]);

            if ((int) $stmt->fetchColumn() !== 1) {
                return false;
            }
        }

        return true;
    } catch (Throwable) {
        return false;
    }
}

function llama_fcc_v2_require_schema(): void
{
    if (!llama_fcc_v2_tables_ready()) {
        throw new RuntimeException(
            'Cell Coverage V2 is not initialized. Run cell-coverage-v2-foundation.sql against the dedicated cell coverage database first.'
        );
    }
}

function llama_fcc_v2_providers(): array
{
    return [
        'att' => [
            'label' => 'AT&T',
            'provider_id' => 130077,
        ],
        'tmobile' => [
            'label' => 'T-Mobile',
            'provider_id' => 130403,
        ],
        'verizon' => [
            'label' => 'Verizon',
            'provider_id' => 131425,
        ],
    ];
}

function llama_fcc_v2_slot_key(
    string $stateFips,
    string $provider,
    string $technology
): string {
    return $stateFips . ':' . $provider . ':' . $technology;
}

function llama_fcc_v2_expected_slots(): array
{
    $slots = [];
    $order = 0;

    foreach (llama_fcc_sync_states() as $fips => $stateName) {
        foreach (llama_fcc_v2_providers() as $providerKey => $provider) {
            foreach (['4g', '5g'] as $technology) {
                $order++;
                $slots[] = [
                    'slot_key' => llama_fcc_v2_slot_key(
                        (string) $fips,
                        $providerKey,
                        $technology
                    ),
                    'state_fips' => (string) $fips,
                    'state_name' => (string) $stateName,
                    'provider_key' => $providerKey,
                    'provider_label' => $provider['label'],
                    'technology' => $technology,
                    'display_order' => $order,
                ];
            }
        }
    }

    return $slots;
}

function llama_fcc_v2_seed_slots(): void
{
    llama_fcc_v2_require_schema();

    $db = cell_db();
    $stmt = $db->prepare(
        'INSERT INTO cell_coverage_v2_slots (
            slot_key,
            state_fips,
            state_name,
            provider_key,
            provider_label,
            technology,
            display_order,
            display_state,
            state_changed_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, "missing", UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            state_name = VALUES(state_name),
            provider_label = VALUES(provider_label),
            display_order = VALUES(display_order)'
    );

    foreach (llama_fcc_v2_expected_slots() as $slot) {
        $stmt->execute([
            $slot['slot_key'],
            $slot['state_fips'],
            $slot['state_name'],
            $slot['provider_key'],
            $slot['provider_label'],
            $slot['technology'],
            $slot['display_order'],
        ]);
    }
}

function llama_fcc_v2_setting(
    string $key,
    string $default = ''
): string {
    if (!llama_fcc_v2_tables_ready()) {
        return $default;
    }

    try {
        $stmt = cell_db()->prepare(
            'SELECT setting_value
             FROM cell_coverage_v2_settings
             WHERE setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false
            ? $default
            : (string) $value;
    } catch (Throwable) {
        return $default;
    }
}

function llama_fcc_v2_set_setting(
    string $key,
    string $value
): void {
    llama_fcc_v2_require_schema();

    $stmt = cell_db()->prepare(
        'INSERT INTO cell_coverage_v2_settings (
            setting_key,
            setting_value
         ) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function llama_fcc_v2_banner_enabled(): bool
{
    return llama_fcc_v2_setting(
        'map_update_banner_enabled',
        '0'
    ) === '1';
}

function llama_fcc_v2_set_banner_enabled(bool $enabled): void
{
    llama_fcc_v2_set_setting(
        'map_update_banner_enabled',
        $enabled ? '1' : '0'
    );
}

function llama_fcc_v2_log_event(
    ?int $runId,
    ?int $jobId,
    string $level,
    string $eventType,
    string $message,
    array $details = []
): void {
    if (!llama_fcc_v2_tables_ready()) {
        return;
    }

    $allowedLevels = [
        'info',
        'success',
        'warning',
        'error',
    ];

    if (!in_array($level, $allowedLevels, true)) {
        $level = 'info';
    }

    $detailsJson = $details
        ? json_encode(
            $details,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
        : null;

    $stmt = cell_db()->prepare(
        'INSERT INTO cell_coverage_v2_events (
            run_id,
            job_id,
            level,
            event_type,
            message,
            details_json
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $runId,
        $jobId,
        $level,
        mb_substr($eventType, 0, 64),
        mb_substr($message, 0, 500),
        $detailsJson,
    ]);

    llama_fcc_v2_prune_events();
}

function llama_fcc_v2_prune_events(): void
{
    $limit = max(
        500,
        min(
            10000,
            (int) llama_fcc_v2_setting('activity_log_limit', '3000')
        )
    );

    try {
        $threshold = cell_db()->query(
            'SELECT id
             FROM cell_coverage_v2_events
             ORDER BY id DESC
             LIMIT 1 OFFSET ' . $limit
        )->fetchColumn();

        if ($threshold !== false) {
            $stmt = cell_db()->prepare(
                'DELETE FROM cell_coverage_v2_events
                 WHERE id <= ?'
            );
            $stmt->execute([(int) $threshold]);
        }
    } catch (Throwable) {
        // Log pruning must never stop coverage work.
    }
}

function llama_fcc_v2_latest_catalog(): array
{
    if (!llama_fcc_sync_is_configured()) {
        throw new RuntimeException(
            'FCC API credentials have not been configured.'
        );
    }

    $attempts = [];

    foreach (
        array_slice(
            llama_fcc_sync_availability_dates(),
            0,
            6
        ) as $asOfDate
    ) {
        $manifest = llama_fcc_sync_manifest($asOfDate);
        $catalog = $manifest
            ? llama_fcc_sync_select_catalog($manifest, $asOfDate)
            : [];

        $attempts[] = [
            'date' => $asOfDate,
            'manifest_rows' => count($manifest),
            'recognized_slots' => count($catalog),
        ];

        if ($catalog) {
            return [
                'as_of_date' => $asOfDate,
                'manifest' => $manifest,
                'catalog' => $catalog,
                'attempts' => $attempts,
            ];
        }
    }

    throw new RuntimeException(
        'The FCC API did not return a usable mobile H3 catalog for the latest filing dates.'
    );
}

function llama_fcc_v2_current_ledger(): array
{
    try {
        return llama_fcc_sync_current_ledger(cell_db());
    } catch (Throwable) {
        return [];
    }
}

function llama_fcc_v2_refresh_slots(
    ?string $targetDate = null,
    ?array $catalog = null
): array {
    llama_fcc_v2_seed_slots();

    $db = cell_db();
    $targetDate = trim((string) $targetDate);
    $catalogProvided = is_array($catalog);
    $catalog ??= [];
    $current = llama_fcc_v2_current_ledger();

    $select = $db->query(
        'SELECT id, slot_key, active_job_id, display_state
         FROM cell_coverage_v2_slots
         ORDER BY display_order'
    );
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $updateWithCatalog = $db->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = ?,
             installed_as_of_date = ?,
             target_as_of_date = ?,
             catalog_available = ?,
             target_file_id = ?,
             target_file_name = ?,
             last_diagnostic = ?,
             state_changed_at = CASE
                WHEN display_state <> ? THEN UTC_TIMESTAMP()
                ELSE state_changed_at
             END
         WHERE id = ?'
    );

    $updateBaseline = $db->prepare(
        'UPDATE cell_coverage_v2_slots
         SET display_state = ?,
             installed_as_of_date = ?,
             target_as_of_date = ?,
             state_changed_at = CASE
                WHEN display_state <> ? THEN UTC_TIMESTAMP()
                ELSE state_changed_at
             END
         WHERE id = ?'
    );

    $counts = [
        'current' => 0,
        'outdated' => 0,
        'missing' => 0,
        'catalog_available' => 0,
        'catalog_missing' => 0,
    ];

    foreach ($rows as $row) {
        $slotKey = (string) $row['slot_key'];
        $installed = $current[$slotKey] ?? null;
        $candidate = $catalog[$slotKey] ?? null;

        $installedDate = is_array($installed)
            ? trim((string) ($installed['fcc_as_of_date'] ?? ''))
            : '';

        if ($installedDate !== '') {
            if ($targetDate === '' || $installedDate >= $targetDate) {
                $state = 'current';
            } else {
                $state = 'outdated';
            }
        } else {
            $state = 'missing';
        }

        if ($catalogProvided) {
            if (is_array($candidate)) {
                $counts['catalog_available']++;
                $fileId = trim((string) ($candidate['file_id'] ?? ''));
                $fileName = trim((string) ($candidate['file_name'] ?? ''));
                $diagnostic = null;
            } else {
                $counts['catalog_missing']++;
                $fileId = null;
                $fileName = null;
                $diagnostic = $targetDate !== ''
                    ? 'No matching FCC catalog entry was recognized for this expected slot.'
                    : null;
            }
        } else {
            $fileId = null;
            $fileName = null;
            $diagnostic = null;
        }

        $counts[$state]++;

        /*
         * Once the V2 worker engine owns a slot, do not overwrite its
         * live stage color with baseline catalog/ledger state.
         */
        if (!empty($row['active_job_id'])) {
            continue;
        }

        if ($catalogProvided) {
            $updateWithCatalog->execute([
                $state,
                $installedDate !== '' ? $installedDate : null,
                $targetDate !== '' ? $targetDate : null,
                is_array($candidate) ? 1 : 0,
                $fileId,
                $fileName,
                $diagnostic,
                $state,
                (int) $row['id'],
            ]);
        } else {
            $updateBaseline->execute([
                $state,
                $installedDate !== '' ? $installedDate : null,
                $targetDate !== '' ? $targetDate : null,
                $state,
                (int) $row['id'],
            ]);
        }
    }

    $counts['expected'] = LLAMA_FCC_V2_EXPECTED_SLOT_COUNT;

    return $counts;
}

function llama_fcc_v2_check_latest(): array
{
    llama_fcc_v2_require_schema();
    llama_fcc_v2_seed_slots();

    $latest = llama_fcc_v2_latest_catalog();
    $asOfDate = (string) $latest['as_of_date'];

    llama_fcc_v2_set_setting('fcc_latest_date', $asOfDate);
    llama_fcc_v2_set_setting('fcc_latest_checked_at', gmdate('c'));

    $counts = llama_fcc_v2_refresh_slots(
        $asOfDate,
        (array) $latest['catalog']
    );

    llama_fcc_v2_log_event(
        null,
        null,
        $counts['catalog_missing'] > 0 ? 'warning' : 'success',
        'fcc_latest_check',
        'FCC latest check found ' . $asOfDate
            . ': ' . $counts['catalog_available']
            . ' of ' . LLAMA_FCC_V2_EXPECTED_SLOT_COUNT
            . ' expected datasets recognized.',
        [
            'as_of_date' => $asOfDate,
            'counts' => $counts,
            'attempts' => $latest['attempts'],
        ]
    );

    return [
        'as_of_date' => $asOfDate,
        'counts' => $counts,
        'attempts' => $latest['attempts'],
    ];
}

function llama_fcc_v2_table_stats(): array
{
    $coverageCells = 0;
    $databaseBytes = 0;

    try {
        $table = cell_db()->query(
            "SHOW TABLE STATUS LIKE 'cell_coverage_cells'"
        )->fetch(PDO::FETCH_ASSOC);

        if ($table) {
            $coverageCells = max(
                0,
                (int) ($table['Rows'] ?? 0)
            );
        }

        $databaseBytes = (int) cell_db()->query(
            'SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()'
        )->fetchColumn();
    } catch (Throwable) {
        // Dashboard metrics are non-critical.
    }

    return [
        'coverage_cells' => $coverageCells,
        'database_bytes' => max(0, $databaseBytes),
    ];
}

function llama_fcc_v2_latest_installed_date(): ?string
{
    try {
        $date = cell_db()->query(
            'SELECT MAX(fcc_as_of_date)
             FROM cell_coverage_datasets
             WHERE status = "current"'
        )->fetchColumn();

        $date = trim((string) $date);
        return $date !== '' ? $date : null;
    } catch (Throwable) {
        return null;
    }
}

function llama_fcc_v2_latest_run(): ?array
{
    try {
        $row = cell_db()->query(
            'SELECT *
             FROM cell_coverage_v2_runs
             ORDER BY id DESC
             LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

function llama_fcc_v2_worker_snapshot(): array
{
    try {
        return cell_db()->query(
            'SELECT
                worker_key,
                run_id,
                role,
                worker_slot,
                status,
                current_job_id,
                message,
                heartbeat_at
             FROM cell_coverage_v2_workers
             ORDER BY FIELD(role, "download", "process", "unpack", "import", "cleanup"), worker_slot'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function llama_fcc_v2_queue_snapshot(?int $runId): array
{
    $counts = [];

    if (!$runId) {
        return $counts;
    }

    try {
        $stmt = cell_db()->prepare(
            'SELECT stage, COUNT(*) AS total
             FROM cell_coverage_v2_jobs
             WHERE run_id = ?
             GROUP BY stage'
        );
        $stmt->execute([$runId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['stage']] = (int) $row['total'];
        }
    } catch (Throwable) {
        // Dashboard queue counters are non-critical.
    }

    return $counts;
}


function llama_fcc_v2_run_work_progress(?int $runId): array
{
    if (!$runId) {
        return [
            'work_total' => 0,
            'work_complete' => 0,
            'work_errors' => 0,
            'work_pending' => 0,
        ];
    }

    try {
        $stmt = cell_db()->prepare(
            'SELECT
                SUM(needs_work = 1) AS work_total,
                SUM(needs_work = 1 AND stage = "complete") AS work_complete,
                SUM(needs_work = 1 AND stage = "error") AS work_errors,
                SUM(needs_work = 1 AND stage NOT IN ("complete", "error")) AS work_pending
             FROM cell_coverage_v2_jobs
             WHERE run_id = ?'
        );
        $stmt->execute([$runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'work_total' => (int) ($row['work_total'] ?? 0),
            'work_complete' => (int) ($row['work_complete'] ?? 0),
            'work_errors' => (int) ($row['work_errors'] ?? 0),
            'work_pending' => (int) ($row['work_pending'] ?? 0),
        ];
    } catch (Throwable) {
        return [
            'work_total' => 0,
            'work_complete' => 0,
            'work_errors' => 0,
            'work_pending' => 0,
        ];
    }
}

function llama_fcc_v2_recent_events(int $limit = 80): array
{
    $limit = max(1, min(200, $limit));

    try {
        return cell_db()->query(
            'SELECT
                id,
                run_id,
                job_id,
                level,
                event_type,
                message,
                created_at
             FROM cell_coverage_v2_events
             ORDER BY id DESC
             LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function llama_fcc_v2_slot_rows(): array
{
    try {
        return cell_db()->query(
            'SELECT
                id,
                slot_key,
                state_fips,
                state_name,
                provider_key,
                provider_label,
                technology,
                display_order,
                display_state,
                installed_as_of_date,
                target_as_of_date,
                catalog_available,
                active_run_id,
                active_job_id,
                last_error,
                last_diagnostic,
                state_changed_at
             FROM cell_coverage_v2_slots
             ORDER BY display_order'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function llama_fcc_v2_slot_counts(array $slots): array
{
    $counts = [
        'current' => 0,
        'downloading' => 0,
        'working' => 0,
        'importing' => 0,
        'error' => 0,
        'missing' => 0,
        'outdated' => 0,
    ];

    foreach ($slots as $slot) {
        $state = (string) ($slot['display_state'] ?? 'missing');

        if (isset($counts[$state])) {
            $counts[$state]++;
        } elseif (in_array(
            $state,
            [
                'preparing',
                'processing',
                'unpacking',
                'cleanup',
                'download_queued',
                'process_queued',
                'unpack_queued',
                'import_queued',
                'cleanup_queued',
            ],
            true
        )) {
            $counts['working']++;
        }
    }

    return $counts;
}

function llama_fcc_v2_snapshot(): array
{
    llama_fcc_v2_require_schema();
    llama_fcc_v2_seed_slots();

    $latestDate = trim(
        llama_fcc_v2_setting('fcc_latest_date', '')
    );

    /*
     * Keep the V2 matrix synchronized with the already-installed V1
     * ledger until a future V2 worker actively owns a slot.
     */
    llama_fcc_v2_refresh_slots(
        $latestDate !== '' ? $latestDate : null,
        null
    );

    $slots = llama_fcc_v2_slot_rows();
    $run = llama_fcc_v2_latest_run();
    $stats = llama_fcc_v2_table_stats();
    $runId = is_array($run) ? (int) ($run['id'] ?? 0) : 0;

    return [
        'ready' => true,
        'fcc_latest' => $latestDate !== '' ? $latestDate : null,
        'fcc_latest_checked_at' =>
            llama_fcc_v2_setting('fcc_latest_checked_at', '') ?: null,
        'installed_latest' => llama_fcc_v2_latest_installed_date(),
        'coverage_cells' => $stats['coverage_cells'],
        'database_bytes' => $stats['database_bytes'],
        'sync_status' => is_array($run)
            ? (string) ($run['status'] ?? 'idle')
            : 'idle',
        'banner_enabled' => llama_fcc_v2_banner_enabled(),
        'expected_slots' => LLAMA_FCC_V2_EXPECTED_SLOT_COUNT,
        'slot_counts' => llama_fcc_v2_slot_counts($slots),
        'slots' => $slots,
        'run' => $run,
        'workers' => llama_fcc_v2_worker_snapshot(),
        'queues' => llama_fcc_v2_queue_snapshot($runId ?: null),
        'work_progress' => llama_fcc_v2_run_work_progress($runId ?: null),
        'events' => llama_fcc_v2_recent_events(80),
        'worker_config' => [
            'download' => (int) llama_fcc_v2_setting('download_workers', '1'),
            'process' => (int) llama_fcc_v2_setting('process_workers', '1'),
            'unpack' => (int) llama_fcc_v2_setting('unpack_workers', '3'),
            'import' => (int) llama_fcc_v2_setting('import_workers', '1'),
            'cleanup' => (int) llama_fcc_v2_setting('cleanup_workers', '1'),
        ],
    ];
}

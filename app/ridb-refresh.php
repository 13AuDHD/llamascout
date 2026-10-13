<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-catalog.php';

function llama_ridb_refresh_active(PDO $ridbDb): ?array
{
    $row = $ridbDb->query(
        'SELECT * FROM ridb_refresh_runs WHERE status = "running" ORDER BY id DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_ridb_refresh_latest(PDO $ridbDb): ?array
{
    $row = $ridbDb->query(
        'SELECT * FROM ridb_refresh_runs ORDER BY id DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function llama_ridb_refresh_begin(PDO $ridbDb): array
{
    $active = llama_ridb_refresh_active($ridbDb);

    if ($active) {
        return $active;
    }

    $baselineCount = (int) $ridbDb->query(
        'SELECT COUNT(*) FROM ridb_facilities'
    )->fetchColumn();

    $ridbDb->beginTransaction();

    try {
        $stmt = $ridbDb->prepare(
            'INSERT INTO ridb_refresh_runs
            (status, started_at, baseline_count, seen_count, new_count, changed_count, missing_count, unchanged_count)
            VALUES ("running", UTC_TIMESTAMP(), ?, 0, 0, 0, 0, 0)'
        );
        $stmt->execute([$baselineCount]);

        $runId = (int) $ridbDb->lastInsertId();

        $snapshot = $ridbDb->prepare(
            'INSERT INTO ridb_refresh_snapshot (run_id, ridb_facility_id, record_hash)
             SELECT ?, ridb_facility_id, SHA2(source_json, 256)
             FROM ridb_facilities'
        );
        $snapshot->execute([$runId]);

        llama_ridb_catalog_reset_sync($ridbDb);
        $ridbDb->commit();
    } catch (Throwable $exception) {
        if ($ridbDb->inTransaction()) {
            $ridbDb->rollBack();
        }
        throw $exception;
    }

    return llama_ridb_refresh_active($ridbDb) ?? [
        'id' => $runId,
        'status' => 'running',
        'baseline_count' => $baselineCount,
    ];
}

function llama_ridb_refresh_finalize(PDO $ridbDb, int $runId): array
{
    $stmt = $ridbDb->prepare(
        'SELECT * FROM ridb_refresh_runs WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$runId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$run) {
        throw new RuntimeException('RIDB refresh run was not found.');
    }

    if ((string) ($run['status'] ?? '') === 'complete') {
        return $run;
    }

    $catalogState = llama_ridb_catalog_sync_state($ridbDb);
    if ((int) ($catalogState['complete'] ?? 0) !== 1) {
        throw new RuntimeException('The RIDB facility refresh is not complete yet.');
    }

    $startedAt = trim((string) ($run['started_at'] ?? ''));
    if ($startedAt === '') {
        throw new RuntimeException('RIDB refresh start time is missing.');
    }

    $ridbDb->beginTransaction();

    try {
        $ridbDb->prepare(
            'DELETE FROM ridb_refresh_changes WHERE run_id = ?'
        )->execute([$runId]);

        $new = $ridbDb->prepare(
            'INSERT INTO ridb_refresh_changes
            (run_id, ridb_facility_id, change_type, facility_name, old_hash, new_hash, source_last_updated, detected_at)
            SELECT ?, f.ridb_facility_id, "new", f.facility_name, NULL,
                   SHA2(f.source_json, 256), f.last_updated_date, UTC_TIMESTAMP()
            FROM ridb_facilities f
            LEFT JOIN ridb_refresh_snapshot s
              ON s.run_id = ? AND s.ridb_facility_id = f.ridb_facility_id
            WHERE f.last_seen_at >= ? AND s.ridb_facility_id IS NULL'
        );
        $new->execute([$runId, $runId, $startedAt]);

        $changed = $ridbDb->prepare(
            'INSERT INTO ridb_refresh_changes
            (run_id, ridb_facility_id, change_type, facility_name, old_hash, new_hash, source_last_updated, detected_at)
            SELECT ?, f.ridb_facility_id, "changed", f.facility_name,
                   s.record_hash, SHA2(f.source_json, 256), f.last_updated_date, UTC_TIMESTAMP()
            FROM ridb_facilities f
            INNER JOIN ridb_refresh_snapshot s
              ON s.run_id = ? AND s.ridb_facility_id = f.ridb_facility_id
            WHERE f.last_seen_at >= ? AND s.record_hash <> SHA2(f.source_json, 256)'
        );
        $changed->execute([$runId, $runId, $startedAt]);

        $missing = $ridbDb->prepare(
            'INSERT INTO ridb_refresh_changes
            (run_id, ridb_facility_id, change_type, facility_name, old_hash, new_hash, source_last_updated, detected_at)
            SELECT ?, s.ridb_facility_id, "missing", f.facility_name,
                   s.record_hash, NULL, f.last_updated_date, UTC_TIMESTAMP()
            FROM ridb_refresh_snapshot s
            LEFT JOIN ridb_facilities f ON f.ridb_facility_id = s.ridb_facility_id
            WHERE s.run_id = ? AND (f.ridb_facility_id IS NULL OR f.last_seen_at < ?)'
        );
        $missing->execute([$runId, $runId, $startedAt]);

        $seenStmt = $ridbDb->prepare(
            'SELECT COUNT(*) FROM ridb_facilities WHERE last_seen_at >= ?'
        );
        $seenStmt->execute([$startedAt]);
        $seen = (int) $seenStmt->fetchColumn();

        $counts = ['new' => 0, 'changed' => 0, 'missing' => 0];
        $countStmt = $ridbDb->prepare(
            'SELECT change_type, COUNT(*) total
             FROM ridb_refresh_changes WHERE run_id = ? GROUP BY change_type'
        );
        $countStmt->execute([$runId]);
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $type = (string) ($row['change_type'] ?? '');
            if (isset($counts[$type])) {
                $counts[$type] = (int) ($row['total'] ?? 0);
            }
        }

        $unchanged = max(0, $seen - $counts['new'] - $counts['changed']);

        $update = $ridbDb->prepare(
            'UPDATE ridb_refresh_runs
             SET status = "complete", completed_at = UTC_TIMESTAMP(), seen_count = ?,
                 new_count = ?, changed_count = ?, missing_count = ?, unchanged_count = ?
             WHERE id = ?'
        );
        $update->execute([
            $seen,
            $counts['new'],
            $counts['changed'],
            $counts['missing'],
            $unchanged,
            $runId,
        ]);

        $ridbDb->prepare(
            'DELETE FROM ridb_refresh_snapshot WHERE run_id = ?'
        )->execute([$runId]);

        $ridbDb->commit();
    } catch (Throwable $exception) {
        if ($ridbDb->inTransaction()) {
            $ridbDb->rollBack();
        }
        throw $exception;
    }

    llama_ridb_log_sync_run(
        $ridbDb,
        'facility_source_refresh',
        'success',
        $seen,
        $seen,
        'Facility refresh complete. New: ' . $counts['new']
            . '; changed: ' . $counts['changed']
            . '; missing: ' . $counts['missing'] . '.'
    );

    return llama_ridb_refresh_latest($ridbDb) ?? [];
}

function llama_ridb_refresh_batch(PDO $ridbDb, int $batchSize = 500): array
{
    $active = llama_ridb_refresh_active($ridbDb);
    if (!$active) {
        throw new RuntimeException('No RIDB facility source refresh is active.');
    }

    $result = llama_ridb_catalog_sync_batch($ridbDb, $batchSize);

    if (!empty($result['complete'])) {
        $result['refresh'] = llama_ridb_refresh_finalize(
            $ridbDb,
            (int) ($active['id'] ?? 0)
        );
    }

    return $result;
}

function llama_ridb_refresh_changes(
    PDO $ridbDb,
    int $runId,
    string $type = '',
    string $query = '',
    int $limit = 100
): array {
    $limit = max(10, min(250, $limit));
    $where = ['c.run_id = ?'];
    $params = [$runId];

    if (in_array($type, ['new', 'changed', 'missing'], true)) {
        $where[] = 'c.change_type = ?';
        $params[] = $type;
    }

    $query = trim($query);
    if ($query !== '') {
        $where[] = '(c.ridb_facility_id LIKE ? OR c.facility_name LIKE ?)';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $stmt = $ridbDb->prepare(
        'SELECT c.ridb_facility_id, c.change_type, c.facility_name,
                c.source_last_updated, c.detected_at,
                f.facility_type_description, f.city, f.state_code
         FROM ridb_refresh_changes c
         LEFT JOIN ridb_facilities f ON f.ridb_facility_id = c.ridb_facility_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY FIELD(c.change_type, "new", "changed", "missing"),
                  c.facility_name ASC, c.ridb_facility_id ASC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

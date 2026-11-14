<?php

declare(strict_types=1);

require_once __DIR__ . '/place-pricing.php';
require_once __DIR__ . '/photo-staging.php';
require_once __DIR__ . '/photo-gallery-management.php';

/**
 * Permanently delete exactly one canonical Site and all its owned records.
 * This is an Owner/Admin-only operation, called AFTER authentication and CSRF.
 *
 * Physical image files are removed after COMMIT only if no other gallery or
 * historical report still references them. It never deletes Place media.
 */

/** Metadata is used only for known column patterns on our own place_* tables. */
function llama_site_delete_columns(PDO $db): array
{
    $stmt = $db->query(
        "SELECT TABLE_NAME, COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND (TABLE_NAME LIKE 'place\\_%' OR TABLE_NAME LIKE 'scout\\_%')"
    );
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $table = (string) $row['TABLE_NAME'];
        $column = (string) $row['COLUMN_NAME'];
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $column) !== 1) {
            continue;
        }
        $columns[$table][$column] = true;
    }
    return $columns;
}

function llama_site_delete_photos_in_use(PDO $db, string $path): bool
{
    if (llama_photo_gallery_path_in_use($db, $path)) {
        return true;
    }
    $stmt = $db->prepare(
        'SELECT 1 FROM scout_report_photos WHERE photo_path = ? LIMIT 1'
    );
    $stmt->execute([$path]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Remove Site-specific rate matches without changing rate rows for other
 * Sites, class matches, or the Place's shared holiday/season calendar.
 */
function llama_site_delete_rate_references(PDO $db, int $placeId, int $siteId): void
{
    $stmt = $db->prepare(
        'SELECT rate_cards_json FROM place_rules WHERE place_id = ? FOR UPDATE'
    );
    $stmt->execute([$placeId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || trim((string) $raw) === '') {
        return;
    }

    $doc = llama_pricing_decode((string) $raw);
    if (!empty($doc['invalid'])) {
        // Never rewrite malformed or unknown versions of shared pricing.
        // The obsolete Site ID cannot publish without a Site record.
        error_log('Site delete skipped invalid pricing document for Place #' . $placeId);
        return;
    }

    $changed = false;
    $rows = [];
    foreach ((array) ($doc['rows'] ?? []) as $row) {
        $match = (array) ($row['match'] ?? []);
        $ids = (array) ($match['site_ids'] ?? []);
        if (!in_array($siteId, $ids, true)) {
            $rows[] = $row;
            continue;
        }

        $newIds = array_values(array_filter(
            $ids,
            static fn ($id): bool => (int) $id !== $siteId
        ));
        $changed = true;
        if (!$newIds) {
            // Never turn a formerly Site-specific rate into a Place-wide rate.
            continue;
        }
        $row['match']['site_ids'] = $newIds;
        $rows[] = $row;
    }
    if ($changed) {
        $doc['rows'] = $rows;
        $doc = llama_pricing_validate($doc);
        $write = $db->prepare(
            'UPDATE place_rules SET rate_cards_json = ? WHERE place_id = ?'
        );
        $write->execute([
            json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $placeId,
        ]);
    }
}

/**
 * The M2-006 history triggers forbid ordinary DELETE. Our migration narrows
 * an exception to the Site ID and report ID set in this DB session.
 */
function llama_site_delete_history_ready(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND EVENT_OBJECT_TABLE = 'scout_reports'
           AND EVENT_MANIPULATION = 'DELETE'"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $body) {
        if (str_contains((string) $body, 'llama_site_delete_report_id')) {
            return true;
        }
    }
    return false;
}

/** Intentionally no Site soft-delete; DELETE removes the real Site row. */
function llama_site_delete_permanently(
    PDO $db, int $placeId, int $siteId, int $actorId
): array {
    if ($placeId < 1 || $siteId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('Choose a real Site to delete.');
    }
    if ($db->inTransaction()) {
        throw new LogicException('A permanent Site deletion cannot be nested in another save.');
    }

    $db->beginTransaction();
    $photoPaths = [];
    $siteName = '';
    try {
        $placeLock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $placeLock->execute([$placeId]);
        if (!$placeLock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }

        $siteLock = $db->prepare(
            'SELECT id, site_code, site_name, map_feature_id
             FROM place_campsites
             WHERE id = ? AND place_id = ? AND is_active = 1
             FOR UPDATE'
        );
        $siteLock->execute([$siteId, $placeId]);
        $site = $siteLock->fetch(PDO::FETCH_ASSOC);
        if (!$site) {
            throw new InvalidArgumentException('This Site no longer exists. Reload the map.');
        }
        $siteName = trim((string) ($site['site_code'] ?: $site['site_name']));
        $mapFeatureId = (int) ($site['map_feature_id'] ?? 0);

        $columns = llama_site_delete_columns($db);
        $has = static fn (string $table, string $key): bool =>
            isset($columns[$table][$key]);

        $reportIds = [];
        if ($has('scout_reports', 'target_id')) {
            $getReports = $db->prepare(
                "SELECT id FROM scout_reports
                 WHERE place_id = ? AND target_scope = 'site'
                   AND target_source = 'campsite_record' AND target_id = ?
                 FOR UPDATE"
            );
            $getReports->execute([$placeId, $siteId]);
            $reportIds = array_map('intval', $getReports->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if ($reportIds && !llama_site_delete_history_ready($db)) {
            throw new InvalidArgumentException(
                'This Site has protected Scout Report history. Install sql/2026-11-14-permanent-site-deletion.sql before deleting it.'
            );
        }

        if ($has('place_campsite_images', 'src')) {
            $photos = $db->prepare(
                'SELECT src FROM place_campsite_images WHERE place_id = ? AND campsite_id = ?'
            );
            $photos->execute([$placeId, $siteId]);
            $photoPaths = array_merge($photoPaths, $photos->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if ($reportIds && $has('scout_report_photos', 'photo_path')) {
            $getHistoryPhotos = $db->prepare(
                'SELECT photo_path FROM scout_report_photos WHERE report_id = ?'
            );
            foreach ($reportIds as $reportId) {
                $getHistoryPhotos->execute([$reportId]);
                $photoPaths = array_merge(
                    $photoPaths, $getHistoryPhotos->fetchAll(PDO::FETCH_COLUMN) ?: []
                );
            }
        }

        // Purge immutable report children under a narrowly scoped, per-report
        // trigger exception. A failed statement rolls the entire deletion back.
        try {
            if ($reportIds) {
                $setScope = $db->prepare(
                    'SET @llama_site_delete_report_id = ?,
                         @llama_site_delete_place_id = ?,
                         @llama_site_delete_site_id = ?'
                );
                foreach ($reportIds as $reportId) {
                    $setScope->execute([$reportId, $placeId, $siteId]);
                    foreach ([
                        'scout_report_answers', 'scout_report_photos',
                        'scout_report_moderation_events',
                    ] as $child) {
                        if (!$has($child, 'report_id')) {
                            continue;
                        }
                        $stmt = $db->prepare('DELETE FROM `' . $child . '` WHERE report_id = ?');
                        $stmt->execute([$reportId]);
                    }
                    $stmt = $db->prepare(
                        'DELETE FROM scout_reports WHERE id = ? AND place_id = ?'
                    );
                    $stmt->execute([$reportId, $placeId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('A Site history record could not be deleted.');
                    }
                }
            }
        } finally {
            $db->exec(
                'SET @llama_site_delete_report_id = NULL,
                     @llama_site_delete_place_id = NULL,
                     @llama_site_delete_site_id = NULL'
            );
        }

        // Remove current/seasonal/scoped answers whose canonical target is
        // this Site. These tables use polymorphic IDs rather than foreign keys.
        foreach ($columns as $table => $fields) {
            if ($table === 'scout_reports'
                || !isset($fields['place_id'], $fields['target_source'], $fields['target_id'])) {
                continue;
            }
            if (!str_starts_with($table, 'place_')) {
                continue;
            }
            $targetFilter = isset($fields['target_scope'])
                ? " AND target_scope = 'site'" : '';
            $stmt = $db->prepare(
                'DELETE FROM `' . $table . '`
                 WHERE place_id = ? AND target_source = ? AND target_id = ?'
                 . $targetFilter
            );
            $stmt->execute([$placeId, 'campsite_record', $siteId]);
        }

        // Older tables use campsite_id, rather than a polymorphic target.
        // Discover all owned place_* tables to avoid silently leaving an
        // old gallery, visibility record, fact, or import-specific Site row.
        foreach ($columns as $table => $fields) {
            if ($table === 'place_campsites'
                || !str_starts_with($table, 'place_')
                || !isset($fields['campsite_id'])) {
                continue;
            }
            $query = 'DELETE FROM `' . $table . '` WHERE campsite_id = ?';
            $params = [$siteId];
            if (isset($fields['place_id'])) {
                $query .= ' AND place_id = ?';
                $params[] = $placeId;
            }
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }

        // Dedicated pricing-review records use site_id rather than
        // campsite_id; do not touch unrelated Place pricing rows.
        if ($has('place_pricing_reviews', 'site_id')) {
            $stmt = $db->prepare(
                'DELETE FROM place_pricing_reviews WHERE site_id = ? AND place_id = ?'
            );
            $stmt->execute([$siteId, $placeId]);
        }

        llama_site_delete_rate_references($db, $placeId, $siteId);

        $delete = $db->prepare(
            'DELETE FROM place_campsites WHERE id = ? AND place_id = ? AND is_active = 1'
        );
        $delete->execute([$siteId, $placeId]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The Site record could not be deleted.');
        }

        // A linked historical Site polygon is never a separate Site. Retire
        // that geometry only if it is not shared by another Site identity.
        if ($mapFeatureId > 0) {
            $checkOther = $db->prepare(
                'SELECT 1 FROM place_campsites WHERE map_feature_id = ? LIMIT 1'
            );
            $checkOther->execute([$mapFeatureId]);
            if (!$checkOther->fetchColumn()) {
                $retire = $db->prepare(
                    "UPDATE place_map_features
                     SET is_active = 0, updated_by = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ? AND place_id = ? AND feature_type = 'camping_site'"
                );
                $retire->execute([$actorId, $mapFeatureId, $placeId]);
            }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    // Only remove owned, local photo files after COMMIT and after checking
    // every surviving gallery + immutable report reference. External images
    // are neither owned nor deleted by this application.
    $deletedFiles = 0;
    foreach (array_unique(array_filter(array_map('strval', $photoPaths))) as $path) {
        if (llama_site_delete_photos_in_use($db, $path)) {
            continue;
        }
        if (llama_photo_delete_owned_permanent_path($path, ['uploads/places'])) {
            $deletedFiles++;
        }
    }

    return [
        'site_id' => $siteId,
        'site_name' => $siteName,
        'reports_deleted' => count($reportIds),
        'files_removed_or_absent' => $deletedFiles,
    ];
}

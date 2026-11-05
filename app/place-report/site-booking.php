<?php

declare(strict_types=1);

/**
 * Public Site reservation-method labels, never a live availability claim.
 *
 * Precedence: selected season's approved Site override > current approved
 * canonical Site answer > positive RIDB CampsiteReservable flag.
 *
 * RIDB "not reservable" does NOT prove first-come/first-served; never show an
 * FF badge from a negative/unknown imported flag.
 */
function llama_site_booking_method_label(mixed $value): ?string
{
    return match ((string) $value) {
        'reservable' => 'reservable',
        'first_come' => 'first_come',
        default => null,
    };
}

function llama_site_booking_ridb_reservable(mixed $value): bool
{
    return in_array(
        strtolower(trim((string) ($value ?? ''))),
        ['true', 'yes', 'y', '1'],
        true
    );
}

/** Caller MUST filter to authorized/visible Site records before this read. */
function llama_public_site_booking_methods(
    PDO $db,
    int $placeId,
    ?int $seasonId,
    array $visibleSiteIds
): array {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $visibleSiteIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($placeId < 1 || !$ids) {
        return [];
    }
    $methods = [];
    foreach (array_chunk($ids, 400) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $ridb = $db->prepare(
            'SELECT campsite_id, feature_value
             FROM place_campsite_features
             WHERE feature_key = ? AND source_provider = ?
               AND is_active = 1 AND campsite_id IN (' . $marks . ')'
        );
        $ridb->execute([
            'ridb_reservable', 'Recreation.gov / RIDB', ...$chunk,
        ]);
        foreach ($ridb->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (llama_site_booking_ridb_reservable($row['feature_value'] ?? null)) {
                $methods[(int) $row['campsite_id']] = 'reservable';
            }
        }

        $normal = $db->prepare(
            'SELECT target_id, answer_state, value_json
             FROM place_report_current_answers
             WHERE place_id = ? AND target_scope = ? AND target_source = ?
               AND question_key = ? AND target_id IN (' . $marks . ')'
        );
        $normal->execute([
            $placeId, 'site', 'campsite_record', 'site_reservation_method', ...$chunk,
        ]);
        foreach ($normal->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) $row['target_id'];
            if ((string) $row['answer_state'] !== 'answered') {
                unset($methods[$id]);
                continue;
            }
            $method = llama_site_booking_method_label(
                json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR)
            );
            if ($method === null) {
                unset($methods[$id]);
            } else {
                $methods[$id] = $method;
            }
        }

        if ($seasonId !== null && $seasonId > 0) {
            $season = $db->prepare(
                'SELECT target_id, answer_state, value_json
                 FROM place_season_answers
                 WHERE place_id = ? AND season_id = ? AND target_scope = ?
                   AND target_source = ? AND question_key = ?
                   AND target_id IN (' . $marks . ')'
            );
            $season->execute([
                $placeId, $seasonId, 'site', 'campsite_record',
                'site_reservation_method', ...$chunk,
            ]);
            foreach ($season->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) $row['target_id'];
                if ((string) $row['answer_state'] !== 'answered') {
                    unset($methods[$id]);
                    continue;
                }
                $method = llama_site_booking_method_label(
                    json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR)
                );
                if ($method === null) {
                    unset($methods[$id]);
                } else {
                    $methods[$id] = $method;
                }
            }
        }
    }
    return $methods;
}


/**
 * Only use community-submitted accessibility designations where the caller
 * already has Complete Place access. Public catalog badges use the official
 * imported accessible_status field, preserving existing data-access rules.
 */
function llama_authorized_site_accessibility_overrides(
    PDO $db,
    int $placeId,
    array $authorizedSiteIds
): array {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $authorizedSiteIds),
        static fn (int $id): bool => $id > 0
    )));
    $out = [];
    foreach (array_chunk($ids, 400) as $batch) {
        $marks = implode(',', array_fill(0, count($batch), '?'));
        $stmt = $db->prepare(
            'SELECT target_id, answer_state, value_json
             FROM place_report_current_answers
             WHERE place_id = ? AND target_scope = ? AND target_source = ?
               AND question_key = ? AND target_id IN (' . $marks . ')'
        );
        $stmt->execute([
            $placeId, 'site', 'campsite_record', 'site_accessible', ...$batch,
        ]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) $row['target_id'];
            $out[$id] = (string) $row['answer_state'] === 'answered'
                && json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR) === true;
        }
    }
    return $out;
}

<?php

declare(strict_types=1);

/**
 * Phase 3E: verified report targets for an existing Place.
 * This is a read-only target discovery/validation layer, NOT an editor.
 * The caller is still responsible for authorizing the active Scout.
 */
function llama_report_targets(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        throw new InvalidArgumentException('A valid Place ID is required.');
    }

    $targets = [[
        'scope' => 'place',
        'source' => 'place',
        'id' => $placeId,
        'parent_area_id' => null,
        'label' => 'Entire Place',
    ]];

    $stmt = $db->prepare(
        "SELECT id, feature_type, label, camping_area_feature_id
         FROM (
            SELECT mf.id, mf.feature_type, mf.label,
                   sd.camping_area_feature_id
            FROM place_map_features mf
            LEFT JOIN place_map_camping_sites sd
                ON sd.feature_id = mf.id
            WHERE mf.place_id = ? AND mf.is_active = 1
              AND mf.feature_type IN ('camping_area','parking_area','camping_site')
         ) mapped
         ORDER BY CASE feature_type
             WHEN 'camping_area' THEN 0 WHEN 'parking_area' THEN 1 ELSE 2 END,
             label, id"
    );
    $stmt->execute([$placeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $area = in_array((string) $row['feature_type'], ['camping_area', 'parking_area'], true);
        $id = (int) $row['id'];
        $targets[] = [
            'scope' => $area ? 'area' : 'site',
            'source' => 'map_feature',
            'id' => $id,
            'parent_area_id' => $area ? null : (($row['camping_area_feature_id'] ?? null) === null ? null : (int) $row['camping_area_feature_id']),
            'label' => trim((string) ($row['label'] ?? '')) ?: (($area ? 'Area' : 'Site') . ' #' . $id),
        ];
    }

    $stmt = $db->prepare(
        'SELECT id, site_code, site_name FROM place_campsites WHERE place_id = ? ORDER BY id'
    );
    $stmt->execute([$placeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $label = trim((string) ($row['site_name'] ?? ''));
        if ($label === '') {
            $number = trim((string) ($row['site_code'] ?? ''));
            $label = $number !== '' ? 'Site ' . $number : 'Imported Site #' . $id;
        }
        $targets[] = [
            'scope' => 'site',
            'source' => 'campsite_record',
            'id' => $id,
            'parent_area_id' => null,
            'label' => $label,
        ];
    }

    return $targets;
}

/**
 * Validate an incoming target against a fresh, Place-scoped database read.
 * Never trust a browser-supplied scope ID or parent relationship.
 */
function llama_report_verified_target(PDO $db, int $placeId, string $scope, string $source, int $id): array
{
    foreach (llama_report_targets($db, $placeId) as $target) {
        if ($target['scope'] === $scope && $target['source'] === $source && $target['id'] === $id) {
            return $target;
        }
    }
    throw new InvalidArgumentException('The requested reporting target does not belong to this Place.');
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-place-areas.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/admin-resources.php';
require_once __DIR__ . '/place-map-features.php';

/**
 * One Facility list for Admin regardless of the historical storage owner.
 *
 * Old Area records remain their existing map_feature/scoped-answer IDs;
 * newer Facilities remain their existing parent_place_id/resource IDs.
 * No migration, duplication, or change to how Sites inherit map Areas.
 */
function admin_place_facility_catalog(array $areas, array $facilities): array
{
    $result = [];
    foreach ($areas as $area) {
        $id = (int) ($area['scope_id'] ?? 0);
        if ($id < 1) continue;
        $result[] = [
            'id' => 'map_feature:' . $id,
            'label' => (string) ($area['label'] ?? ('Facility #' . $id)),
            'source' => 'map_feature',
            'source_id' => $id,
        ];
    }
    foreach ($facilities as $facility) {
        $id = (int) ($facility['id'] ?? 0);
        if ($id < 1) continue;
        $result[] = [
            'id' => 'resource:' . $id,
            'label' => (string) ($facility['name'] ?? ('Facility #' . $id)),
            'source' => 'resource',
            'source_id' => $id,
        ];
    }
    usort($result, static fn (array $a, array $b): int =>
        strcasecmp($a['label'], $b['label']) ?: strcmp($a['id'], $b['id'])
    );
    return $result;
}

/** Project current map geometry into the Location controls. */
function admin_place_facility_map_details(array $feature): array
{
    $geometry = $feature['geometry'] ?? null;
    $point = is_array($geometry) && ($geometry['type'] ?? '') === 'Point'
        ? ($geometry['coordinates'] ?? []) : [];
    $lat = isset($point[1]) && is_numeric($point[1]) ? (float) $point[1] : null;
    $lng = isset($point[0]) && is_numeric($point[0]) ? (float) $point[0] : null;
    $metadata = is_array($feature['metadata'] ?? null) ? $feature['metadata'] : [];
    $pointEditable = in_array((string) ($feature['geometry_type'] ?? ''), ['point'], true)
        || ((string) ($feature['geometry_type'] ?? '') === 'unmapped'
            && (string) ($feature['feature_type'] ?? '') === 'facility_area');
    return [
        'id' => (int) ($feature['id'] ?? 0),
        'name' => (string) ($feature['label'] ?? ''),
        'feature_type' => (string) ($feature['feature_type'] ?? ''),
        'facility_kind' => (string) ($metadata['facility_kind'] ?? ''),
        'notes' => (string) ($metadata['description'] ?? ''),
        'geometry_type' => (string) ($feature['geometry_type'] ?? ''),
        'coordinates' => ($lat !== null && $lng !== null)
            ? sprintf('%.7f, %.7f', $lat, $lng) : '',
        'coordinates_editable' => $pointEditable,
    ];
}

/**
 * Save legacy Facility identity/location in the same transaction as its
 * canonical Area answers. Existing Site links, scoped answers and photos
 * retain their target and map feature IDs.
 */
function admin_place_save_map_facility(
    PDO $db, int $actorUserId, int $placeId, int $featureId, array $input
): void {
    $statement = $db->prepare('SELECT id, place_id, feature_type, geometry_type,
        label, metadata_json FROM place_map_features
        WHERE id = ? AND place_id = ? AND is_active = 1
          AND feature_type IN ("camping_area", "parking_area", "facility_area")
        FOR UPDATE');
    $statement->execute([$featureId, $placeId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('The selected Facility no longer belongs to this Place.');

    $name = mb_substr(trim((string) ($input['facility_name'] ?? '')), 0, 160);
    if ($name === '') throw new InvalidArgumentException('Enter a Facility name.');
    $metadata = json_decode((string) ($row['metadata_json'] ?? 'null'), true);
    if (!is_array($metadata)) $metadata = [];
    $featureType = (string) $row['feature_type'];
    if ($featureType === 'facility_area') {
        $kind = trim((string) ($input['facility_kind'] ?? ''));
        if ($kind !== '' && !array_key_exists($kind, llama_place_facility_kind_options())) {
            throw new InvalidArgumentException('Choose a valid Facility type.');
        }
        if ($kind !== '') $metadata['facility_kind'] = $kind;
        else unset($metadata['facility_kind']);
    }
    // Keep all existing metadata; only update fields owned by this editor.
    if (array_key_exists('facility_notes', $input)) {
        $notes = mb_substr(trim((string) $input['facility_notes']), 0, 4000);
        if ($notes === '') unset($metadata['description']);
        else $metadata['description'] = $notes;
    }
    $json = $metadata
        ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        : null;

    $geometryType = (string) $row['geometry_type'];
    $isEditable = $geometryType === 'point'
        || ($geometryType === 'unmapped' && $featureType === 'facility_area');
    $oldCoordinateWasChanged = (string) ($input['facility_coordinates_changed'] ?? '') === '1';
    if ($oldCoordinateWasChanged && !$isEditable) {
        throw new InvalidArgumentException(
            'This Facility uses an Area boundary. Edit its location in Edit Map.'
        );
    }
    if ($oldCoordinateWasChanged) {
        $value = trim((string) ($input['facility_coordinates'] ?? ''));
        if ($value === '') {
            // Point geometry is mandatory on an already-mapped Feature.
            throw new InvalidArgumentException(
                'Enter Facility coordinates, or use Edit Map to remove its map point.'
            );
        }
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 2 || preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)$/D', $parts[0]) !== 1
            || preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)$/D', $parts[1]) !== 1) {
            throw new InvalidArgumentException('Enter Facility coordinates as latitude, longitude.');
        }
        [$lat, $lng] = array_map('floatval', $parts);
        if (!is_finite($lat) || !is_finite($lng)
            || abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
            throw new InvalidArgumentException('Enter valid Facility coordinates.');
        }
        $pointWkt = llama_place_map_feature_point_wkt([
            'type' => 'Point', 'coordinates' => [round($lng, 7), round($lat, 7)],
        ]);
        $update = $db->prepare('UPDATE place_map_features
            SET label = ?, metadata_json = ?, geometry_type = "point",
                geometry = ST_GeomFromText(?, ?),
                updated_by = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND place_id = ? AND is_active = 1');
        $update->execute([$name, $json, $pointWkt, LLAMA_PLACE_MAP_FEATURE_SRID,
            $actorUserId, $featureId, $placeId]);
    } else {
        $update = $db->prepare('UPDATE place_map_features
            SET label = ?, metadata_json = ?,
                updated_by = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND place_id = ? AND is_active = 1');
        $update->execute([$name, $json, $actorUserId, $featureId, $placeId]);
    }
    admin_users_audit($db, $actorUserId, null, 'place.facility_metadata_updated',
        'Updated Facility location and information.',
        ['place_id' => $placeId, 'facility_map_id' => $featureId,
         'coordinates_changed' => $oldCoordinateWasChanged]);
}

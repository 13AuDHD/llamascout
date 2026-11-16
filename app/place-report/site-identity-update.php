<?php

declare(strict_types=1);

/**
 * Site identity corrections are proposals in a scoped Update report.
 * The existing moderation transaction applies them only on approval.
 * They never share storage with Place coordinates or Site observations.
 */
function llama_update_site_identity_types(): array
{
    return [
        'rv_site' => 'RV site',
        'tent_site' => 'Tent site',
        'mixed_site' => 'Tent / RV',
        'vehicle_site' => 'Vehicle / parking site',
        'group_site' => 'Group site',
    ];
}

function llama_update_site_identity_current(
    PDO $db, int $placeId, array $target, bool $lock = false
): array {
    if (($target['scope'] ?? '') !== 'site' || $placeId < 1) {
        return [];
    }
    $id = (int) ($target['id'] ?? 0);
    $source = (string) ($target['source'] ?? '');
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($source === 'campsite_record') {
        $stmt = $db->prepare(
            'SELECT site_name, site_code, site_type, latitude, longitude,
                    map_feature_id
               FROM place_campsites
              WHERE place_id = ? AND id = ? AND is_active = 1 LIMIT 1' . $suffix
        );
        $stmt->execute([$placeId, $id]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($site)) {
            throw new InvalidArgumentException('The selected Site is no longer available.');
        }
        $hasCoordinates = is_numeric($site['latitude'])
            && is_numeric($site['longitude']);
        return [
            'name' => (string) ($site['site_name'] ?? ''),
            'label' => (string) ($target['label'] ?? $site['site_code'] ?? ''),
            'type' => (string) ($site['site_type'] ?? ''),
            'coordinates' => $hasCoordinates
                ? sprintf('%.7f, %.7f', (float) $site['latitude'], (float) $site['longitude'])
                : '',
            'editable_coordinates' => true,
            'editable_type' => true,
        ];
    }
    if ($source === 'map_feature') {
        $stmt = $db->prepare(
            "SELECT label, geometry_type, ST_AsText(geometry) AS point_wkt
               FROM place_map_features
              WHERE place_id = ? AND id = ? AND is_active = 1
                AND feature_type = 'camping_site' LIMIT 1" . $suffix
        );
        $stmt->execute([$placeId, $id]);
        $feature = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($feature)) {
            throw new InvalidArgumentException('The selected Site is no longer available.');
        }
        $coords = '';
        $point = (string) ($feature['point_wkt'] ?? '');
        if (preg_match('/^POINT\s*\(\s*([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s*\)$/i', $point, $m)) {
            $coords = sprintf('%.7f, %.7f', (float) $m[2], (float) $m[1]);
        }
        return [
            'name' => (string) ($feature['label'] ?? ''),
            'label' => (string) ($target['label'] ?? ''),
            'type' => '',
            'coordinates' => $coords,
            'editable_coordinates' => $coords !== '',
            'editable_type' => false,
        ];
    }
    return [];
}

function llama_update_site_identity_coordinate_pair(string $coordinates): array
{
    $parts = array_map('trim', explode(',', $coordinates));
    if (count($parts) !== 2
        || !preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $parts[0])
        || !preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $parts[1])) {
        throw new InvalidArgumentException('Enter Site coordinates as latitude, longitude.');
    }
    $lat = (float) $parts[0];
    $lng = (float) $parts[1];
    if (!is_finite($lat) || !is_finite($lng)
        || abs($lat) > 90 || abs($lng) > 180
        || ($lat === 0.0 && $lng === 0.0)) {
        throw new InvalidArgumentException('Enter valid Site GPS coordinates.');
    }
    return [$lat, $lng];
}

/**
 * Only include explicit, materially changed Site facts.
 *
 * @return array{changes:array,original:array}
 */
function llama_update_site_identity_proposal(
    PDO $db, int $placeId, array $target, mixed $input
): array {
    if (!is_string($input) || trim($input) === '') {
        return ['changes' => [], 'original' => []];
    }
    if (strlen($input) > 1500) {
        throw new InvalidArgumentException('The Site identity correction is too large.');
    }
    $values = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($values) || array_is_list($values)) {
        throw new InvalidArgumentException('Invalid Site identity correction.');
    }
    $current = llama_update_site_identity_current($db, $placeId, $target);
    if (!$current) throw new InvalidArgumentException('Choose a valid Site.');
    $changes = [];
    $original = [];
    foreach ($values as $key => $raw) {
        if (!in_array($key, ['name', 'type', 'coordinates'], true)
            || !is_string($raw)) {
            throw new InvalidArgumentException('Invalid Site identity field.');
        }
        $new = trim($raw);
        if ($key === 'name') {
            if ($new === '' || mb_strlen($new) > 160) {
                throw new InvalidArgumentException('Enter a Site name (160 characters maximum).');
            }
        } elseif ($key === 'type') {
            if (empty($current['editable_type'])
                || !array_key_exists($new, llama_update_site_identity_types())) {
                throw new InvalidArgumentException('Choose a valid Site type.');
            }
        } else {
            if (empty($current['editable_coordinates'])) {
                throw new InvalidArgumentException('This Site location must be edited in Edit Map.');
            }
            [$lat, $lng] = llama_update_site_identity_coordinate_pair($new);
            $new = sprintf('%.7f, %.7f', $lat, $lng);
        }
        $previous = (string) ($current[$key] ?? '');
        if ($new === $previous) continue;
        $changes[$key] = $new;
        $original[$key] = $previous;
    }
    return ['changes' => $changes, 'original' => $original];
}

/** Called only inside the moderator's existing approval transaction. */
function llama_update_site_identity_apply(
    PDO $db, int $placeId, array $target, array $changes, array $original
): void {
    if (!$changes) return;
    if (!$db->inTransaction()) {
        throw new RuntimeException('Site identity approval requires a transaction.');
    }
    $current = llama_update_site_identity_current($db, $placeId, $target, true);
    foreach ($changes as $key => $value) {
        if (!array_key_exists($key, $original)
            || (string) ($current[$key] ?? '') !== (string) $original[$key]) {
            throw new RuntimeException('Site details changed since submission. Review the latest Site before approving.');
        }
    }
    $source = (string) ($target['source'] ?? '');
    $id = (int) ($target['id'] ?? 0);
    if ($source === 'campsite_record') {
        $columns = ['name' => 'site_name', 'type' => 'site_type'];
        foreach ($columns as $field => $column) {
            if (!array_key_exists($field, $changes)) continue;
            $stmt = $db->prepare("UPDATE place_campsites SET $column = ? WHERE id = ? AND place_id = ? AND is_active = 1");
            $stmt->execute([$changes[$field], $id, $placeId]);
        }
        if (isset($changes['coordinates'])) {
            [$lat, $lng] = llama_update_site_identity_coordinate_pair($changes['coordinates']);
            $stmt = $db->prepare('UPDATE place_campsites SET latitude = ?, longitude = ?
                WHERE id = ? AND place_id = ? AND is_active = 1');
            $stmt->execute([$lat, $lng, $id, $placeId]);
        }
        // Keep the existing mapped point consistent with its canonical Site.
        $mapStmt = $db->prepare("SELECT map_feature_id FROM place_campsites
            WHERE id = ? AND place_id = ? AND is_active = 1");
        $mapStmt->execute([$id, $placeId]);
        $featureId = (int) $mapStmt->fetchColumn();
        if ($featureId > 0) {
            if (isset($changes['name'])) {
                $stmt = $db->prepare("UPDATE place_map_features SET label = ?
                    WHERE id = ? AND place_id = ? AND feature_type = 'camping_site'");
                $stmt->execute([$changes['name'], $featureId, $placeId]);
            }
            if (isset($changes['coordinates'])) {
                [$lat, $lng] = llama_update_site_identity_coordinate_pair($changes['coordinates']);
                $stmt = $db->prepare("UPDATE place_map_features SET geometry = ST_GeomFromText(?, 4326),
                    geometry_type = 'point' WHERE id = ? AND place_id = ?
                    AND feature_type = 'camping_site' AND geometry_type = 'point'");
                $stmt->execute([sprintf('POINT(%.7f %.7f)', $lng, $lat), $featureId, $placeId]);
            }
        }
    } elseif ($source === 'map_feature') {
        if (isset($changes['type'])) {
            throw new InvalidArgumentException('This mapped Site type cannot be edited here.');
        }
        if (isset($changes['name'])) {
            $stmt = $db->prepare("UPDATE place_map_features SET label = ?
                WHERE id = ? AND place_id = ? AND feature_type = 'camping_site'");
            $stmt->execute([$changes['name'], $id, $placeId]);
        }
        if (isset($changes['coordinates'])) {
            [$lat, $lng] = llama_update_site_identity_coordinate_pair($changes['coordinates']);
            $stmt = $db->prepare("UPDATE place_map_features SET geometry = ST_GeomFromText(?, 4326)
                WHERE id = ? AND place_id = ? AND feature_type = 'camping_site'
                  AND geometry_type = 'point'");
            $stmt->execute([sprintf('POINT(%.7f %.7f)', $lng, $lat), $id, $placeId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('The Site map point has changed. Review its location.');
            }
        }
    } else {
        throw new InvalidArgumentException('Unsupported Site identity source.');
    }
}

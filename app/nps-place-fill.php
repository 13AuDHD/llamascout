<?php

declare(strict_types=1);

/**
 * NPS campground enrichment for a previously confirmed RIDB Place.
 *
 * Fill only null/empty canonical values. Never create Campsites from NPS
 * campground totals, replace RIDB/Scout photos, or select fuzzy identities.
 * Differences remain visible through the live reconciliation preview.
 */

function llama_nps_fill_text(mixed $value): ?string
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $text === '' ? null : $text;
}

function llama_nps_fill_coordinates(mixed $value, bool $latitude): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    if (!is_finite($number) || ($latitude ? abs($number) > 90 : abs($number) > 180)) {
        return null;
    }
    return $number;
}

function llama_nps_fill_first_contact(mixed $items): ?string
{
    if (!is_array($items)) {
        return null;
    }
    foreach ($items as $item) {
        if (is_array($item)) {
            $value = llama_nps_fill_text($item['phoneNumber'] ?? $item['emailAddress'] ?? null);
            if ($value !== null) {
                return $value;
            }
        }
    }
    return null;
}

function llama_nps_fill_address(array $nps): array
{
    $addresses = $nps['addresses'] ?? [];
    if (!is_array($addresses)) {
        return [];
    }
    foreach ($addresses as $address) {
        if (is_array($address) && strtolower((string) ($address['type'] ?? '')) === 'physical') {
            return $address;
        }
    }
    // Mailing addresses may point to a distant park office, not the campground.
    return [];
}

/** Only real, mapped canonical columns are eligible for automatic filling. */
function llama_nps_fill_fields(array $nps): array
{
    $address = llama_nps_fill_address($nps);
    $contacts = is_array($nps['contacts'] ?? null) ? $nps['contacts'] : [];
    $lat = llama_nps_fill_coordinates($nps['latitude'] ?? null, true);
    $lon = llama_nps_fill_coordinates($nps['longitude'] ?? null, false);
    if ($lat === 0.0 && $lon === 0.0) {
        $lat = null;
        $lon = null;
    }
    $reservation = llama_nps_fill_text($nps['reservationUrl'] ?? null);
    if ($reservation !== null && !preg_match('~^https://~i', $reservation)) {
        $reservation = null;
    }

    return [
        'places' => [
            'description' => llama_nps_fill_text($nps['description'] ?? null),
            'latitude' => $lat,
            'longitude' => $lon,
            'public_latitude' => $lat === null ? null : round($lat, 1),
            'public_longitude' => $lon === null ? null : round($lon, 1),
            'city' => llama_nps_fill_text($address['city'] ?? null),
            'state' => llama_nps_fill_text($address['stateCode'] ?? null),
        ],
        'place_facility_facts' => [
            'official_address_1' => llama_nps_fill_text($address['line1'] ?? null),
            'official_address_2' => llama_nps_fill_text($address['line2'] ?? null),
            'official_city' => llama_nps_fill_text($address['city'] ?? null),
            'official_state' => llama_nps_fill_text($address['stateCode'] ?? null),
            'official_postal_code' => llama_nps_fill_text($address['postalCode'] ?? null),
            'official_phone' => llama_nps_fill_first_contact($contacts['phoneNumbers'] ?? []),
            'official_email' => llama_nps_fill_first_contact($contacts['emailAddresses'] ?? []),
            'reservation_url' => $reservation,
        ],
    ];
}

function llama_nps_fill_images(array $nps): array
{
    $images = [];
    foreach ((array) ($nps['images'] ?? []) as $image) {
        if (!is_array($image)) {
            continue;
        }
        $url = trim((string) ($image['url'] ?? ''));
        if (strlen($url) > 2048 || !preg_match('~^https://[^\s]+$~i', $url)) {
            continue;
        }
        $images[$url] = [
            'url' => $url,
            'alt' => llama_nps_fill_text($image['altText'] ?? $image['title'] ?? $nps['name'] ?? null),
        ];
        if (count($images) >= 50) {
            break;
        }
    }
    return array_values($images);
}

/**
 * Resolve a human-confirmed, still-current source pair to an actual RIDB Place.
 * Do not use names, coordinates or a shared reservation URL alone as identity.
 */
function llama_nps_fill_pair(PDO $npsDb, PDO $mainDb, string $park, string $npsId, string $ridbId): array
{
    $source = $npsDb->prepare(
        "SELECT n.source_json, n.source_hash, n.source_url, n.record_name,
                r.ridb_facility_id, r.nps_source_hash
           FROM nps_raw_records n
           JOIN nps_ridb_review_events r
             ON r.nps_park_code = n.park_code AND r.nps_record_id = n.record_id
      LEFT JOIN nps_ridb_review_events newer
             ON newer.nps_park_code = r.nps_park_code
            AND newer.nps_record_id = r.nps_record_id AND newer.id > r.id
          WHERE n.resource_type = 'campgrounds' AND n.park_code = ? AND n.record_id = ?
            AND r.decision = 'confirmed_match' AND newer.id IS NULL
          LIMIT 1"
    );
    $source->execute([$park, $npsId]);
    $row = $source->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string) $row['ridb_facility_id'] !== $ridbId
        || !hash_equals((string) $row['source_hash'], (string) $row['nps_source_hash'])) {
        throw new RuntimeException('This campground has no current, confirmed NPS/RIDB pairing. Review its source identity first.');
    }
    $nps = json_decode((string) $row['source_json'], true);
    if (!is_array($nps) || (string) ($nps['id'] ?? '') !== $npsId) {
        throw new RuntimeException('The NPS source record does not have the confirmed identity.');
    }
    $placeStmt = $mainDb->prepare(
        "SELECT p.id, p.name FROM place_external_sources s
           JOIN places p ON p.id = s.place_id
          WHERE s.source_provider = 'ridb' AND s.source_external_id = ? LIMIT 1"
    );
    $placeStmt->execute([$ridbId]);
    $place = $placeStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($place)) {
        throw new RuntimeException('This RIDB facility has not yet been published as a Llama Scout Place.');
    }
    $prior = $mainDb->prepare(
        "SELECT place_id FROM place_external_sources
          WHERE source_provider = 'nps' AND source_external_id = ? LIMIT 1"
    );
    $prior->execute([$npsId]);
    $priorId = $prior->fetchColumn();
    if ($priorId !== false && (int) $priorId !== (int) $place['id']) {
        throw new RuntimeException('This NPS campground is already linked to a different Place. No changes were made.');
    }
    return ['place_id' => (int) $place['id'], 'place_name' => (string) $place['name'],
        'nps' => $nps, 'source_url' => (string) ($row['source_url'] ?? ''),
        'nps_id' => $npsId, 'ridb_id' => $ridbId];
}

function llama_nps_fill_is_empty(mixed $value): bool
{
    return $value === null || (is_string($value) && trim($value) === '');
}

/** Read the actual canonical data so previews and writes use identical rules. */
function llama_nps_fill_preview(PDO $db, array $pair): array
{
    $id = $pair['place_id'];
    $fields = llama_nps_fill_fields($pair['nps']);
    $result = ['fill' => [], 'conflict' => [], 'same' => [], 'unavailable' => [],
        'images_new' => [], 'images_existing' => 0];

    foreach ($fields as $table => $candidates) {
        $select = $db->prepare('SELECT ' . implode(', ', array_keys($candidates))
            . ' FROM ' . $table . ' WHERE ' . ($table === 'places' ? 'id' : 'place_id') . ' = ? LIMIT 1');
        $select->execute([$id]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($existing)) {
            throw new RuntimeException('The imported Place has no ' . $table . ' record. The RIDB import must be completed first.');
        }
        foreach ($candidates as $column => $value) {
            $key = $table . '.' . $column;
            if ($value === null || $value === '') {
                $result['unavailable'][$key] = null;
            } elseif (llama_nps_fill_is_empty($existing[$column] ?? null)) {
                $result['fill'][$key] = $value;
            } elseif (trim((string) $existing[$column]) === trim((string) $value)
                || (is_numeric($existing[$column]) && is_numeric($value)
                    && abs((float) $existing[$column] - (float) $value) < 0.0000001)) {
                $result['same'][$key] = $value;
            } else {
                $result['conflict'][$key] = ['existing' => $existing[$column], 'nps' => $value];
            }
        }
    }
    $select = $db->prepare('SELECT src FROM place_images WHERE place_id = ?');
    $select->execute([$id]);
    $existing = array_fill_keys(array_map('strval', $select->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
    foreach (llama_nps_fill_images($pair['nps']) as $image) {
        if (isset($existing[$image['url']])) {
            $result['images_existing']++;
        } else {
            $result['images_new'][] = $image;
        }
    }
    return $result;
}

/** Transactional, idempotent NPS fill. This never overwrites nonempty facts. */
function llama_nps_fill_apply(PDO $npsDb, PDO $db, string $park, string $npsId, string $ridbId, int $userId): array
{
    $pair = llama_nps_fill_pair($npsDb, $db, $park, $npsId, $ridbId);
    $db->beginTransaction();
    try {
        // Serialize re-imports for this Place; preview is recalculated under lock.
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$pair['place_id']]);
        if ($lock->fetchColumn() === false) {
            throw new RuntimeException('The destination Place disappeared.');
        }
        $preview = llama_nps_fill_preview($db, $pair);
        foreach ($preview['fill'] as $path => $value) {
            [$table, $column] = explode('.', $path, 2);
            $key = $table === 'places' ? 'id' : 'place_id';
            // The condition protects existing data even if another editor saved
            // a value after the preview was generated.
            $emptyPredicate = is_float($value) || is_int($value)
                ? $column . ' IS NULL'
                : '(' . $column . " IS NULL OR " . $column . " = '')";
            $stmt = $db->prepare('UPDATE ' . $table . ' SET ' . $column . ' = ? WHERE '
                . $key . ' = ? AND ' . $emptyPredicate);
            $stmt->execute([$value, $pair['place_id']]);
        }
        $link = $db->prepare(
            'INSERT INTO place_external_sources
              (place_id, source_provider, source_type, source_external_id, source_url, synced_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE synced_at = UTC_TIMESTAMP()'
        );
        $url = preg_match('~^https://~i', $pair['source_url']) ? $pair['source_url'] : null;
        $link->execute([$pair['place_id'], 'nps', 'campground', $pair['nps_id'], $url]);

        $sort = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM place_images WHERE place_id = ?');
        $sort->execute([$pair['place_id']]);
        $order = (int) $sort->fetchColumn() + 1;
        $featured = $db->prepare('SELECT COUNT(*) FROM place_images WHERE place_id = ? AND is_featured = 1');
        $featured->execute([$pair['place_id']]);
        $hasFeatured = (int) $featured->fetchColumn() > 0;
        $insert = $db->prepare('INSERT INTO place_images
            (place_id, src, alt_text, is_featured, sort_order, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($preview['images_new'] as $image) {
            $insert->execute([$pair['place_id'], $image['url'], $image['alt'] ?? $pair['place_name'],
                $hasFeatured ? 0 : 1, $order++, $userId > 0 ? $userId : null]);
            $hasFeatured = true;
        }
        $db->commit();
        return ['place_id' => $pair['place_id'], 'place_name' => $pair['place_name'],
            'fields_filled' => count($preview['fill']), 'fields_skipped' => count($preview['conflict']),
            'photos_added' => count($preview['images_new']), 'photos_already_present' => $preview['images_existing']];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

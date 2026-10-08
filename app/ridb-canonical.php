<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';
require_once __DIR__ . '/ridb-normalization.php';

function llama_ridb_canonical_bool(mixed $value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (float) $value !== 0.0;
    }

    $key = llama_ridb_normalization_key((string) $value);

    if (in_array($key, [
        'Y',
        'YES',
        'TRUE',
        '1',
        'AVAILABLE',
        'ALLOWED',
        'PRESENT',
    ], true)) {
        return true;
    }

    if (in_array($key, [
        'N',
        'NO',
        'FALSE',
        '0',
        'NONE',
        'NOT AVAILABLE',
        'NOT ALLOWED',
        'NOT PRESENT',
    ], true)) {
        return false;
    }

    return null;
}

function llama_ridb_canonical_presence(mixed $value): ?bool
{
    $bool = llama_ridb_canonical_bool($value);

    if ($bool !== null) {
        return $bool;
    }

    $key = llama_ridb_normalization_key((string) $value);

    if ($key === '') {
        return null;
    }

    if (in_array($key, [
        'UNKNOWN',
        'N A',
        'NA',
        'NOT APPLICABLE',
    ], true)) {
        return null;
    }

    return true;
}

function llama_ridb_canonical_time(mixed $value): ?string
{
    $text = strtoupper(trim((string) $value));

    if ($text === '') {
        return null;
    }

    foreach ([
        'g:i A',
        'g A',
        'h:i A',
        'H:i:s',
        'H:i',
    ] as $format) {
        $date = DateTimeImmutable::createFromFormat(
            '!' . $format,
            $text
        );

        if ($date instanceof DateTimeImmutable) {
            return $date->format('H:i');
        }
    }

    return null;
}

function llama_ridb_canonical_cached_facility(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt = $ridbDb->prepare(
        'SELECT source_json
         FROM ridb_facilities
         WHERE ridb_facility_id = ?
         LIMIT 1'
    );

    $stmt->execute([$facilityId]);

    $json = $stmt->fetchColumn();

    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_ridb_canonical_attribute_rows(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt = $ridbDb->prepare(
        'SELECT
            a.ridb_campsite_id,
            a.attribute_name,
            a.attribute_value
         FROM ridb_campsite_attributes a
         INNER JOIN ridb_campsites c
            ON c.ridb_campsite_id =
               a.ridb_campsite_id
         WHERE c.ridb_facility_id = ?
         ORDER BY
            a.ridb_campsite_id ASC,
            a.id ASC'
    );

    $stmt->execute([$facilityId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_ridb_canonical_site_rows(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt = $ridbDb->prepare(
        'SELECT
            ridb_campsite_id,
            campsite_name,
            campsite_type,
            campsite_accessible
         FROM ridb_campsites
         WHERE ridb_facility_id = ?'
    );

    $stmt->execute([$facilityId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_ridb_canonical_evidence(
    PDO $ridbDb,
    string $facilityId
): array {
    $evidence = [];

    foreach (
        llama_ridb_canonical_attribute_rows(
            $ridbDb,
            $facilityId
        )
        as $row
    ) {
        $name = trim(
            (string) (
                $row['attribute_name']
                ?? ''
            )
        );

        if ($name === '') {
            continue;
        }

        $match = llama_ridb_normalization_match($name);

        if (!$match) {
            continue;
        }

        $canonical = (string) $match['canonical'];

        $evidence[$canonical][] = [
            'name' => $name,
            'value' => trim(
                (string) (
                    $row['attribute_value']
                    ?? ''
                )
            ),
            'site_id' => (string) (
                $row['ridb_campsite_id']
                ?? ''
            ),
        ];
    }

    return $evidence;
}

function llama_ridb_canonical_any_positive(
    array $rows
): bool {
    foreach ($rows as $row) {
        if (
            llama_ridb_canonical_presence(
                $row['value']
                ?? null
            ) === true
        ) {
            return true;
        }
    }

    return false;
}

function llama_ridb_canonical_consensus_bool(
    array $rows
): ?bool {
    $yes = false;
    $no = false;

    foreach ($rows as $row) {
        $value = llama_ridb_canonical_bool(
            $row['value']
            ?? null
        );

        if ($value === true) {
            $yes = true;
        } elseif ($value === false) {
            $no = true;
        }
    }

    if ($yes) {
        return true;
    }

    if ($no) {
        return false;
    }

    return null;
}

function llama_ridb_canonical_consistent_time(
    array $rows
): ?string {
    $times = [];

    foreach ($rows as $row) {
        $time = llama_ridb_canonical_time(
            $row['value']
            ?? null
        );

        if ($time !== null) {
            $times[$time] = true;
        }
    }

    if (count($times) !== 1) {
        return null;
    }

    return (string) array_key_first($times);
}

function llama_ridb_canonical_has_accessible_named_item(
    array $rows
): bool {
    foreach ($rows as $row) {
        $name = llama_ridb_normalization_key(
            (string) (
                $row['name']
                ?? ''
            )
        );

        if (
            str_contains($name, 'ACCESSIBLE')
            && llama_ridb_canonical_presence(
                $row['value']
                ?? null
            ) !== false
        ) {
            return true;
        }
    }

    return false;
}

function llama_ridb_canonical_upsert_values(
    PDO $db,
    string $table,
    int $placeId,
    array $values
): void {
    $allowedTables = [
        'place_amenities',
        'place_details',
        'place_rules',
    ];

    if (
        !in_array($table, $allowedTables, true)
        || $placeId < 1
        || !$values
    ) {
        return;
    }

    $clean = [];

    foreach ($values as $column => $value) {
        if (
            !preg_match(
                '/^[a-z0-9_]+$/',
                (string) $column
            )
            || $value === null
        ) {
            continue;
        }

        $clean[(string) $column] =
            is_bool($value)
                ? ($value ? 1 : 0)
                : $value;
    }

    if (!$clean) {
        return;
    }

    $exists = $db->prepare(
        'SELECT place_id
         FROM `' . $table . '`
         WHERE place_id = ?
         LIMIT 1'
    );

    $exists->execute([$placeId]);

    if (!$exists->fetchColumn()) {
        $columns = array_keys($clean);

        $stmt = $db->prepare(
            'INSERT INTO `' . $table . '` (
                place_id,
                '
            . implode(
                ', ',
                array_map(
                    static fn (string $column): string =>
                        '`' . $column . '`',
                    $columns
                )
            )
            . '
            ) VALUES (
                ?,
                '
            . implode(
                ', ',
                array_fill(
                    0,
                    count($columns),
                    '?'
                )
            )
            . '
            )'
        );

        $stmt->execute(
            array_merge(
                [$placeId],
                array_values($clean)
            )
        );

        return;
    }

    foreach ($clean as $column => $value) {
        $currentStmt = $db->prepare(
            'SELECT `' . $column . '`
             FROM `' . $table . '`
             WHERE place_id = ?
             LIMIT 1'
        );

        $currentStmt->execute([$placeId]);

        $current = $currentStmt->fetchColumn();

        $shouldWrite =
            $current === false
            || $current === null
            || $current === ''
            || (
                $table === 'place_amenities'
                && (int) $current === 0
                && (int) $value === 1
            );

        if (!$shouldWrite) {
            continue;
        }

        $update = $db->prepare(
            'UPDATE `' . $table . '`
             SET `' . $column . '` = ?
             WHERE place_id = ?'
        );

        $update->execute([
            $value,
            $placeId,
        ]);
    }
}

function llama_ridb_canonical_sync_place(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): array {
    $facilityId = trim($facilityId);

    if (
        $placeId < 1
        || $facilityId === ''
    ) {
        return [
            'answer_count' => 0,
        ];
    }

    $facility = llama_ridb_canonical_cached_facility(
        $ridbDb,
        $facilityId
    );

    $evidence = llama_ridb_canonical_evidence(
        $ridbDb,
        $facilityId
    );

    $sites = llama_ridb_canonical_site_rows(
        $ridbDb,
        $facilityId
    );

    $amenities = [];
    $details = [];
    $rules = [];

    $positiveAmenities = [
        'toilet' => 'toilets',
        'flush_toilet' => 'toilets',
        'potable_water' => 'potable_water',
        'drinking_water' => 'potable_water',
        'trash_collection' => 'trash',
        'fire_ring' => 'fire_ring',
        'campfire_circle' => 'fire_ring',
        'picnic_table' => 'picnic_table',
        'food_storage' => 'bear_box',
        'electricity_available' => 'electricity',
        'electric_hookup' => 'electricity',
        'full_hookup' => 'electricity',
    ];

    foreach (
        $positiveAmenities
        as $canonical => $column
    ) {
        if (
            llama_ridb_canonical_any_positive(
                $evidence[$canonical]
                ?? []
            )
        ) {
            $amenities[$column] = 1;
        }
    }

    $toiletEvidence = array_merge(
        $evidence['toilet'] ?? [],
        $evidence['flush_toilet'] ?? []
    );

    if (
        llama_ridb_canonical_has_accessible_named_item(
            $toiletEvidence
        )
    ) {
        $amenities['toilets'] = 1;
        $details['accessible_toilet'] = 1;
    }

    if (
        llama_ridb_canonical_has_accessible_named_item(
            $evidence['picnic_table']
            ?? []
        )
    ) {
        $amenities['picnic_table'] = 1;
        $details['accessible_picnic_table'] = 1;
    }

    $pets = llama_ridb_canonical_consensus_bool(
        $evidence['pets_allowed']
        ?? []
    );

    if ($pets !== null) {
        $rules['pets_allowed'] =
            $pets
                ? 1
                : 0;
    }

    $campfire = llama_ridb_canonical_consensus_bool(
        $evidence['campfire_allowed']
        ?? []
    );

    if ($campfire !== null) {
        $rules['campfire_allowed'] =
            $campfire
                ? 1
                : 0;
    }

    $checkin = llama_ridb_canonical_consistent_time(
        $evidence['checkin_time']
        ?? []
    );

    if ($checkin !== null) {
        $rules['check_in_required'] = 1;
        $rules['check_in_begins'] = $checkin;
    }

    $checkout = llama_ridb_canonical_consistent_time(
        $evidence['checkout_time']
        ?? []
    );

    if ($checkout !== null) {
        $rules['check_out_required'] = 1;
        $rules['checkout_ends'] = $checkout;
    }

    $reservationUrl = trim(
        (string) llama_ridb_record_value(
            $facility,
            [
                'FacilityReservationURL',
                'facilityReservationURL',
            ],
            ''
        )
    );

    if ($reservationUrl !== '') {
        $rules['reservation_url'] = $reservationUrl;
    }

    if ($sites) {
        $details['campsite_count'] = count($sites);
    }

    $hasTentSite = false;
    $hasRvSite = false;

    foreach ($sites as $site) {
        $type = llama_ridb_normalization_key(
            (string) (
                $site['campsite_type']
                ?? ''
            )
        );

        if (str_contains($type, 'TENT')) {
            $hasTentSite = true;
        }

        if (
            str_contains($type, 'RV')
            || str_contains($type, 'MOTORHOME')
        ) {
            $hasRvSite = true;
        }
    }

    if ($hasTentSite) {
        $details['tent_camping_suitable'] = 1;
    }

    if ($hasRvSite) {
        $details['rv_suitable'] = 1;
    }

    llama_ridb_canonical_upsert_values(
        $mainDb,
        'place_amenities',
        $placeId,
        $amenities
    );

    llama_ridb_canonical_upsert_values(
        $mainDb,
        'place_details',
        $placeId,
        $details
    );

    llama_ridb_canonical_upsert_values(
        $mainDb,
        'place_rules',
        $placeId,
        $rules
    );

    return [
        'amenities' => array_keys($amenities),
        'details' => array_keys($details),
        'rules' => array_keys($rules),
        'answer_count' =>
            count($amenities)
            + count($details)
            + count($rules),
    ];
}

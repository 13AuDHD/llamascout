<?php
declare(strict_types=1);

require_once __DIR__ . '/ridb.php';
require_once __DIR__ . '/ridb-normalization.php';

function llama_ridb_import_text(mixed $value): ?string {
    $value = trim(
        html_entity_decode(
            strip_tags((string) $value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )
    );

    return $value !== '' ? $value : null;
}

function llama_ridb_import_number(mixed $value): ?float {
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    $text = str_replace(',', '', trim((string) $value));

    if (
        $text === ''
        || preg_match('/-?\d+(?:\.\d+)?/', $text, $match) !== 1
    ) {
        return null;
    }

    return (float) $match[0];
}

function llama_ridb_import_int(mixed $value): ?int {
    $number = llama_ridb_import_number($value);

    return $number === null
        ? null
        : max(0, (int) round($number));
}

function llama_ridb_import_bool(mixed $value): ?bool {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (float) $value !== 0.0;
    }

    $key = llama_ridb_normalization_key((string) $value);

    if (in_array($key, ['Y', 'YES', 'TRUE', '1', 'AVAILABLE', 'ALLOWED'], true)) {
        return true;
    }

    if (in_array($key, ['N', 'NO', 'FALSE', '0', 'NONE', 'NOT AVAILABLE', 'NOT ALLOWED'], true)) {
        return false;
    }

    return null;
}

function llama_ridb_import_presence_bool(mixed $value): ?bool {
    $bool = llama_ridb_import_bool($value);

    if ($bool !== null) {
        return $bool;
    }

    $key = llama_ridb_normalization_key((string) $value);

    if ($key === '') {
        return null;
    }

    return !in_array(
        $key,
        ['NONE', 'NO', 'N A', 'NA', 'NOT APPLICABLE', 'UNKNOWN'],
        true
    );
}

function llama_ridb_import_time(mixed $value): ?string {
    $text = strtoupper(trim((string) $value));

    if ($text === '') {
        return null;
    }

    foreach (['g:i A', 'g A', 'h:i A', 'H:i:s', 'H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $text);

        if ($date instanceof DateTimeImmutable) {
            return $date->format('H:i:s');
        }
    }

    return null;
}


function llama_ridb_import_datetime(mixed $value): ?string {
    $text = trim((string) $value);

    if ($text === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($text);

        return $date->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function llama_ridb_import_site_type(string $rawType): ?string {
    $key = llama_ridb_normalization_key($rawType);

    if ($key === '') {
        return null;
    }

    if (str_contains($key, 'GROUP')) {
        return 'group_site';
    }

    if (
        str_contains($key, 'TENT ONLY')
        || (
            str_contains($key, 'TENT')
            && !str_contains($key, 'RV')
        )
    ) {
        return 'tent_site';
    }

    if (
        str_contains($key, 'RV')
        || str_contains($key, 'MOTORHOME')
    ) {
        return 'rv_site';
    }

    return null;
}

function llama_ridb_import_parking_style(mixed $value): ?string {
    $key = llama_ridb_normalization_key((string) $value);

    return match (true) {
        str_contains($key, 'PULL THROUGH') => 'pull_through',
        str_contains($key, 'BACK IN') => 'back_in',
        str_contains($key, 'PULL IN') => 'pull_in',
        str_contains($key, 'PARALLEL') => 'parallel',
        default => null,
    };
}

function llama_ridb_import_attribute_map(array $attributes): array {
    $mapped = [];

    foreach ($attributes as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }

        $name = trim(
            (string) llama_ridb_record_value(
                $attribute,
                ['AttributeName', 'AttributeKey', 'attributeName'],
                ''
            )
        );

        $value = trim(
            (string) llama_ridb_record_value(
                $attribute,
                ['AttributeValue', 'AttributeText', 'attributeValue'],
                ''
            )
        );

        if ($name === '') {
            continue;
        }

        $match = llama_ridb_normalization_match($name);

        if (!$match) {
            continue;
        }

        $mapped[(string) $match['canonical']][] = [
            'name' => $name,
            'value' => $value,
        ];
    }

    return $mapped;
}

function llama_ridb_import_first(array $mapped, string $canonical): ?string {
    foreach ($mapped[$canonical] ?? [] as $row) {
        $value = trim((string) ($row['value'] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function llama_ridb_import_address(array $addresses): array {
    if (!$addresses) {
        return [];
    }

    $address = $addresses[0];

    return [
        'address_1' => llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                ['FacilityStreetAddress1', 'StreetAddress1', 'AddressLine1']
            )
        ),
        'address_2' => llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                ['FacilityStreetAddress2', 'StreetAddress2', 'AddressLine2']
            )
        ),
        'city' => llama_ridb_import_text(
            llama_ridb_record_value($address, ['City'])
        ),
        'state' => llama_ridb_import_text(
            llama_ridb_record_value($address, ['AddressStateCode', 'State'])
        ),
        'postal_code' => llama_ridb_import_text(
            llama_ridb_record_value($address, ['PostalCode', 'ZipCode'])
        ),
    ];
}

function llama_ridb_import_find_place(PDO $db, string $facilityId): ?int {
    $stmt = $db->prepare(
        'SELECT place_id
         FROM place_external_sources
         WHERE source_provider = ?
           AND source_external_id = ?
         LIMIT 1'
    );

    $stmt->execute(['ridb', $facilityId]);
    $placeId = $stmt->fetchColumn();

    return $placeId === false
        ? null
        : (int) $placeId;
}

function llama_ridb_import_create_place(
    PDO $db,
    int $userId,
    string $facilityId,
    array $facility,
    array $address
): int {
    $name = trim(
        (string) llama_ridb_record_value(
            $facility,
            ['FacilityName', 'facilityName'],
            'RIDB Campground'
        )
    );

    if ($name === '') {
        $name = 'RIDB Campground ' . $facilityId;
    }

    $slug = moderation_unique_slug($db, $name);

    $latitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLatitude', 'facilityLatitude']
        )
    );

    $longitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLongitude', 'facilityLongitude']
        )
    );

    $description = llama_ridb_import_text(
        llama_ridb_record_value(
            $facility,
            ['FacilityDescription', 'facilityDescription']
        )
    );

    $stmt = $db->prepare(
        'INSERT INTO places
        (
            slug,
            name,
            type,
            status,
            status_changed_at,
            status_changed_by,
            source_type,
            created_by,
            description,
            public_latitude,
            public_longitude,
            latitude,
            longitude,
            city,
            state,
            published_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            CURRENT_TIMESTAMP,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            CURRENT_TIMESTAMP
        )'
    );

    $stmt->execute([
        $slug,
        $name,
        'developed-campground',
        'active',
        $userId,
        'ridb',
        $userId,
        $description,
        $latitude !== null ? round($latitude, 1) : null,
        $longitude !== null ? round($longitude, 1) : null,
        $latitude,
        $longitude,
        $address['city'] ?? null,
        $address['state'] ?? null,
    ]);

    $placeId = (int) $db->lastInsertId();

    $history = $db->prepare(
        'INSERT INTO place_status_history
        (
            place_id,
            old_status,
            new_status,
            reason,
            changed_by
        )
        VALUES (?, NULL, ?, ?, ?)'
    );

    $history->execute([
        $placeId,
        'active',
        'Imported from Recreation.gov / RIDB facility ' . $facilityId . '.',
        $userId,
    ]);

    return $placeId;
}

function llama_ridb_import_update_place(
    PDO $db,
    int $placeId,
    array $facility,
    array $address
): void {
    $latitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLatitude', 'facilityLatitude']
        )
    );

    $longitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLongitude', 'facilityLongitude']
        )
    );

    $name = llama_ridb_import_text(
        llama_ridb_record_value(
            $facility,
            ['FacilityName', 'facilityName']
        )
    );

    $description = llama_ridb_import_text(
        llama_ridb_record_value(
            $facility,
            ['FacilityDescription', 'facilityDescription']
        )
    );

    $stmt = $db->prepare(
        'UPDATE places
         SET
            name = COALESCE(?, name),
            type = ?,
            source_type = ?,
            description = COALESCE(?, description),
            public_latitude = ?,
            public_longitude = ?,
            latitude = ?,
            longitude = ?,
            city = COALESCE(?, city),
            state = COALESCE(?, state)
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $name,
        'developed-campground',
        'ridb',
        $description,
        $latitude !== null ? round($latitude, 1) : null,
        $longitude !== null ? round($longitude, 1) : null,
        $latitude,
        $longitude,
        $address['city'] ?? null,
        $address['state'] ?? null,
        $placeId,
    ]);
}

function llama_ridb_import_link_source(
    PDO $db,
    int $placeId,
    string $facilityId,
    array $facility
): void {
    $sourceUpdated = llama_ridb_import_datetime(
        llama_ridb_record_value(
            $facility,
            ['LastUpdatedDate', 'lastUpdatedDate']
        )
    );

    $stmt = $db->prepare(
        'INSERT INTO place_external_sources
        (
            place_id,
            source_provider,
            source_type,
            source_external_id,
            source_url,
            source_updated_at,
            synced_at
        )
        VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE
            place_id = VALUES(place_id),
            source_type = VALUES(source_type),
            source_url = VALUES(source_url),
            source_updated_at = VALUES(source_updated_at),
            synced_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $placeId,
        'ridb',
        'facility',
        $facilityId,
        'https://www.recreation.gov/',
        $sourceUpdated,
    ]);
}

function llama_ridb_import_facility_facts(
    PDO $db,
    int $placeId,
    string $facilityId,
    array $facility,
    array $address
): void {
    $stmt = $db->prepare(
        'INSERT INTO place_facility_facts
        (
            place_id,
            official_address_1,
            official_address_2,
            official_city,
            official_state,
            official_postal_code,
            official_phone,
            official_email,
            reservation_url,
            fee_description,
            source_provider,
            source_external_id,
            source_updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            official_address_1 = VALUES(official_address_1),
            official_address_2 = VALUES(official_address_2),
            official_city = VALUES(official_city),
            official_state = VALUES(official_state),
            official_postal_code = VALUES(official_postal_code),
            official_phone = VALUES(official_phone),
            official_email = VALUES(official_email),
            reservation_url = VALUES(reservation_url),
            fee_description = VALUES(fee_description),
            source_provider = VALUES(source_provider),
            source_external_id = VALUES(source_external_id),
            source_updated_at = VALUES(source_updated_at)'
    );

    $stmt->execute([
        $placeId,
        $address['address_1'] ?? null,
        $address['address_2'] ?? null,
        $address['city'] ?? null,
        $address['state'] ?? null,
        $address['postal_code'] ?? null,
        llama_ridb_import_text(
            llama_ridb_record_value($facility, ['FacilityPhone', 'facilityPhone'])
        ),
        llama_ridb_import_text(
            llama_ridb_record_value($facility, ['FacilityEmail', 'facilityEmail'])
        ),
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                ['FacilityReservationURL', 'facilityReservationURL']
            )
        ),
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                ['FacilityUseFeeDescription', 'facilityUseFeeDescription']
            )
        ),
        'Recreation.gov / RIDB',
        $facilityId,
        llama_ridb_import_datetime(
            llama_ridb_record_value($facility, ['LastUpdatedDate', 'lastUpdatedDate'])
        ),
    ]);
}

function llama_ridb_import_replace_facility_features(
    PDO $db,
    int $placeId,
    string $facilityId,
    array $activities
): void {
    $delete = $db->prepare(
        'DELETE FROM place_facility_features
         WHERE place_id = ?
           AND source_provider = ?'
    );
    $delete->execute([$placeId, 'Recreation.gov / RIDB']);

    if (!$activities) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO place_facility_features
        (
            place_id,
            feature_key,
            feature_value,
            qualifier,
            source_provider,
            source_external_id,
            sort_order,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    $sort = 10;

    foreach ($activities as $activity) {
        $name = llama_ridb_import_text(
            llama_ridb_record_value(
                $activity,
                ['ActivityName', 'activityName']
            )
        );

        if ($name === null) {
            continue;
        }

        $insert->execute([
            $placeId,
            'activity',
            $name,
            null,
            'Recreation.gov / RIDB',
            $facilityId,
            $sort,
        ]);

        $sort += 10;
    }
}

function llama_ridb_import_site_payload(
    array $site,
    array $attributes
): array {
    $mapped = llama_ridb_import_attribute_map($attributes);

    $parkingRaw = llama_ridb_import_first($mapped, 'parking_type');
    $electricRaw = llama_ridb_import_first($mapped, 'electric_hookup');
    $waterRaw = llama_ridb_import_first($mapped, 'water_hookup');
    $sewerRaw = llama_ridb_import_first($mapped, 'sewer_hookup');

    $electric = llama_ridb_import_presence_bool($electricRaw);
    $water = llama_ridb_import_bool($waterRaw);
    $sewer = llama_ridb_import_bool($sewerRaw);

    $hookupStatus = null;

    if ($electric === true && $water === true && $sewer === true) {
        $hookupStatus = 'full';
    } elseif ($electric === true && $water === true) {
        $hookupStatus = 'electric_water';
    } elseif ($electric === true) {
        $hookupStatus = 'electric_only';
    } elseif ($water === true) {
        $hookupStatus = 'water_only';
    } elseif ($electric === false && $water === false && $sewer === false) {
        $hookupStatus = 'none';
    }

    $accessibleRaw = llama_ridb_record_value(
        $site,
        ['CampsiteAccessible', 'campsiteAccessible']
    );

    $accessibleStatus =
        $accessibleRaw === null
            ? 'unknown'
            : (!empty($accessibleRaw) ? 'yes' : 'no');

    $rawType = trim(
        (string) llama_ridb_record_value(
            $site,
            ['CampsiteType', 'CampsiteTypeDescription', 'campsiteType'],
            ''
        )
    );

    $siteName = trim(
        (string) llama_ridb_record_value(
            $site,
            ['CampsiteName', 'campsiteName'],
            ''
        )
    );

    $electricService = null;

    if ($electricRaw !== null && preg_match('/\d/', $electricRaw) === 1) {
        $electricService = $electricRaw;
    }

    $facts = [
        'site_length_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'site_length')),
        'site_width_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'site_width')),
        'driveway_length_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'parking_length')),
        'driveway_grade' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'parking_grade')),
        'driveway_surface' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'parking_surface')),
        'overhead_clearance_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'overhead_clearance')),
        'max_vehicle_length_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'max_vehicle_length')),
        'max_people' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'max_people')),
        'max_vehicles' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'max_vehicles')),
        'min_people' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'min_people')),
        'min_vehicles' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'min_vehicles')),
        'tent_pad' => llama_ridb_import_bool(llama_ridb_import_first($mapped, 'tent_pad_present')),
        'tent_pad_length_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'tent_pad_length')),
        'tent_pad_width_ft' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'tent_pad_width')),
        'electric_hookup' => $electric,
        'electric_service' => $electricService,
        'water_hookup' => $water,
        'sewer_hookup' => $sewer,
        'checkin_time' => llama_ridb_import_time(llama_ridb_import_first($mapped, 'checkin_time')),
        'checkout_time' => llama_ridb_import_time(llama_ridb_import_first($mapped, 'checkout_time')),
        'proximity_to_water' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'proximity_to_water')),
        'shade_source_value' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'shade')),
        'privacy_source_value' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'privacy')),
        'quiet_area_source_value' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'quiet_area')),
        'max_horses' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'max_horses')),
        'bed_type' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'bed_type')),
        'bed_count' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'bed_count')),
        'bedroom_count' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'bedroom_count')),
        'room_count' => llama_ridb_import_int(llama_ridb_import_first($mapped, 'room_count')),
        'shower_bath_type' => llama_ridb_import_text(llama_ridb_import_first($mapped, 'shower_bath_type')),
        'internal_map_x' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'internal_map_x')),
        'internal_map_y' => llama_ridb_import_number(llama_ridb_import_first($mapped, 'internal_map_y')),
        'internal_map_placed' => llama_ridb_import_bool(llama_ridb_import_first($mapped, 'placed_on_map')),
    ];

    $features = [];

    $featureCanonicals = [
        'site_access',
        'double_driveway',
        'campfire_allowed',
        'fire_ring',
        'grill',
        'picnic_table',
        'food_storage',
        'toilet',
        'trash_collection',
        'pets_allowed',
        'equipment_mandatory',
        'lantern_post',
        'lake_access',
        'river_access',
        'trailhead',
        'trailhead_parking',
        'accessibility',
        'accessible_occupant_message',
        'accessible_boat_ramp',
        'accessible_boat_dock',
        'accessible_campsites',
        'recycling',
        'amphitheater',
        'geological_attractions',
        'scenic_overlooks',
        'visitor_center',
        'self_pay_station',
        'day_use_area',
        'fishing_pier',
        'picnic_shelter',
        'playground',
        'full_hookup',
        'electricity_available',
        'potable_water',
        'flush_toilet',
        'campfire_circle',
        'paved_parking',
        'platform',
        'site_rating',
        'condition_rating',
        'location_rating',
        'capacity_size_rating',
        'hike_in_distance',
    ];

    foreach ($featureCanonicals as $canonical) {
        foreach ($mapped[$canonical] ?? [] as $row) {
            $value = trim((string) ($row['value'] ?? ''));

            if ($value !== '') {
                $features[] = [
                    'feature_key' => $canonical,
                    'feature_value' => $value,
                    'qualifier' => (string) ($row['name'] ?? ''),
                ];
            }
        }
    }

    return [
        'identity' => [
            'site_code' => $siteName !== '' ? $siteName : null,
            'site_name' => $siteName !== '' ? $siteName : null,
            'raw_site_type' => $rawType !== '' ? $rawType : null,
            'site_type' => llama_ridb_import_site_type($rawType),
            'parking_style' => llama_ridb_import_parking_style($parkingRaw),
            'hookup_status' => $hookupStatus,
            'accessible_status' => $accessibleStatus,
            'latitude' => llama_ridb_import_number(
                llama_ridb_record_value(
                    $site,
                    ['CampsiteLatitude', 'Latitude', 'latitude']
                )
            ),
            'longitude' => llama_ridb_import_number(
                llama_ridb_record_value(
                    $site,
                    ['CampsiteLongitude', 'Longitude', 'longitude']
                )
            ),
        ],
        'facts' => $facts,
        'features' => $features,
    ];
}

function llama_ridb_import_upsert_site(
    PDO $db,
    int $placeId,
    string $siteId,
    array $payload
): int {
    $identity = $payload['identity'] ?? [];

    $stmt = $db->prepare(
        'INSERT INTO place_campsites
        (
            place_id,
            site_code,
            site_name,
            raw_site_type,
            site_type,
            parking_style,
            hookup_status,
            accessible_status,
            latitude,
            longitude,
            source_provider,
            source_external_id,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            place_id = VALUES(place_id),
            site_code = VALUES(site_code),
            site_name = VALUES(site_name),
            raw_site_type = VALUES(raw_site_type),
            site_type = VALUES(site_type),
            parking_style = VALUES(parking_style),
            hookup_status = VALUES(hookup_status),
            accessible_status = VALUES(accessible_status),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            is_active = 1,
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $placeId,
        $identity['site_code'] ?? null,
        $identity['site_name'] ?? null,
        $identity['raw_site_type'] ?? null,
        $identity['site_type'] ?? null,
        $identity['parking_style'] ?? null,
        $identity['hookup_status'] ?? null,
        $identity['accessible_status'] ?? 'unknown',
        $identity['latitude'] ?? null,
        $identity['longitude'] ?? null,
        'ridb',
        $siteId,
    ]);

    $idStmt = $db->prepare(
        'SELECT id
         FROM place_campsites
         WHERE source_provider = ?
           AND source_external_id = ?
         LIMIT 1'
    );
    $idStmt->execute(['ridb', $siteId]);

    $campsiteId = (int) $idStmt->fetchColumn();

    if ($campsiteId < 1) {
        throw new RuntimeException(
            'The imported campsite could not be resolved.'
        );
    }

    return $campsiteId;
}

function llama_ridb_import_upsert_site_facts(
    PDO $db,
    int $campsiteId,
    string $siteId,
    array $facts
): void {
    $columns = [
        'site_length_ft',
        'site_width_ft',
        'driveway_length_ft',
        'driveway_grade',
        'driveway_surface',
        'overhead_clearance_ft',
        'max_vehicle_length_ft',
        'max_people',
        'max_vehicles',
        'min_people',
        'min_vehicles',
        'tent_pad',
        'tent_pad_length_ft',
        'tent_pad_width_ft',
        'electric_hookup',
        'electric_service',
        'water_hookup',
        'sewer_hookup',
        'checkin_time',
        'checkout_time',
        'proximity_to_water',
        'shade_source_value',
        'privacy_source_value',
        'quiet_area_source_value',
        'max_horses',
        'bed_type',
        'bed_count',
        'bedroom_count',
        'room_count',
        'shower_bath_type',
        'internal_map_x',
        'internal_map_y',
        'internal_map_placed',
    ];

    $insertColumns = ['campsite_id'];
    $values = [$campsiteId];
    $placeholders = ['?'];
    $updates = [];

    foreach ($columns as $column) {
        $insertColumns[] = $column;

        $value = array_key_exists($column, $facts)
            ? $facts[$column]
            : null;

        $values[] = is_bool($value)
            ? ($value ? 1 : 0)
            : $value;

        $placeholders[] = '?';
        $updates[] = '`' . $column . '` = VALUES(`' . $column . '`)';
    }

    $insertColumns[] = 'source_provider';
    $values[] = 'Recreation.gov / RIDB';
    $placeholders[] = '?';

    $insertColumns[] = 'source_external_id';
    $values[] = $siteId;
    $placeholders[] = '?';

    $updates[] = 'source_provider = VALUES(source_provider)';
    $updates[] = 'source_external_id = VALUES(source_external_id)';

    $stmt = $db->prepare(
        'INSERT INTO place_campsite_facts ('
        . implode(
            ',',
            array_map(
                static fn(string $column): string =>
                    '`' . $column . '`',
                $insertColumns
            )
        )
        . ') VALUES ('
        . implode(',', $placeholders)
        . ') ON DUPLICATE KEY UPDATE '
        . implode(',', $updates)
    );

    $stmt->execute($values);
}

function llama_ridb_import_replace_site_features(
    PDO $db,
    int $campsiteId,
    string $siteId,
    array $features
): void {
    $delete = $db->prepare(
        'DELETE FROM place_campsite_features
         WHERE campsite_id = ?
           AND source_provider = ?'
    );
    $delete->execute([$campsiteId, 'Recreation.gov / RIDB']);

    if (!$features) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO place_campsite_features
        (
            campsite_id,
            feature_key,
            feature_value,
            qualifier,
            source_provider,
            source_external_id,
            sort_order,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    $sort = 10;

    foreach ($features as $feature) {
        $insert->execute([
            $campsiteId,
            $feature['feature_key'] ?? '',
            $feature['feature_value'] ?? null,
            $feature['qualifier'] ?? null,
            'Recreation.gov / RIDB',
            $siteId,
            $sort,
        ]);

        $sort += 10;
    }
}

function llama_ridb_import_facility(
    PDO $mainDb,
    PDO $ridbDb,
    int $userId,
    string $facilityId
): array {
    if ($userId < 1) {
        throw new InvalidArgumentException(
            'An admin user is required.'
        );
    }

    $facilityId = trim($facilityId);

    if ($facilityId === '') {
        throw new InvalidArgumentException(
            'A RIDB facility ID is required.'
        );
    }

    $facilityResponse = llama_ridb_facility($facilityId);
    $facility = llama_ridb_response_record($facilityResponse);

    if (!$facility) {
        throw new RuntimeException(
            'RIDB did not return this facility.'
        );
    }

    llama_ridb_store_facilities($ridbDb, [$facility]);

    $addressResponse = llama_ridb_facility_addresses($facilityId);
    $addresses = $addressResponse['records'] ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_facility_addresses',
        $facilityId,
        $addresses,
        'FacilityAddressID'
    );

    $activityResponse = llama_ridb_facility_activities($facilityId);
    $activities = $activityResponse['records'] ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_activities',
        $facilityId,
        $activities,
        'ActivityID'
    );

    $campsiteResponse = llama_ridb_facility_campsites($facilityId, 500);
    $campsites = $campsiteResponse['records'] ?? [];

    llama_ridb_store_campsites(
        $ridbDb,
        $facilityId,
        $campsites
    );

    $address = llama_ridb_import_address($addresses);

    $mainDb->beginTransaction();

    try {
        $placeId = llama_ridb_import_find_place(
            $mainDb,
            $facilityId
        );

        $created = $placeId === null;

        if ($created) {
            $placeId = llama_ridb_import_create_place(
                $mainDb,
                $userId,
                $facilityId,
                $facility,
                $address
            );
        } else {
            llama_ridb_import_update_place(
                $mainDb,
                $placeId,
                $facility,
                $address
            );
        }

        llama_ridb_import_link_source(
            $mainDb,
            $placeId,
            $facilityId,
            $facility
        );

        llama_ridb_import_facility_facts(
            $mainDb,
            $placeId,
            $facilityId,
            $facility,
            $address
        );

        llama_ridb_import_replace_facility_features(
            $mainDb,
            $placeId,
            $facilityId,
            $activities
        );

        $mainDb
            ->prepare(
                'UPDATE place_campsites
                 SET is_active = 0
                 WHERE place_id = ?
                   AND source_provider = ?'
            )
            ->execute([$placeId, 'ridb']);

        $siteCount = 0;
        $attributeCount = 0;

        foreach ($campsites as $site) {
            $siteId = trim(
                (string) llama_ridb_record_value(
                    $site,
                    ['CampsiteID', 'campsiteID', 'campsite_id'],
                    ''
                )
            );

            if ($siteId === '') {
                continue;
            }

            $attributeResponse = llama_ridb_campsite_attributes($siteId);
            $attributes = $attributeResponse['records'] ?? [];

            llama_ridb_replace_campsite_attributes(
                $ridbDb,
                $siteId,
                $attributes
            );

            $payload = llama_ridb_import_site_payload(
                $site,
                $attributes
            );

            $campsiteId = llama_ridb_import_upsert_site(
                $mainDb,
                $placeId,
                $siteId,
                $payload
            );

            llama_ridb_import_upsert_site_facts(
                $mainDb,
                $campsiteId,
                $siteId,
                $payload['facts'] ?? []
            );

            llama_ridb_import_replace_site_features(
                $mainDb,
                $campsiteId,
                $siteId,
                $payload['features'] ?? []
            );

            $siteCount++;
            $attributeCount += count($attributes);
        }

        $mainDb->commit();

        llama_ridb_log_sync_run(
            $ridbDb,
            'production_import',
            'success',
            1 + $siteCount + $attributeCount,
            1 + $siteCount + $attributeCount,
            'Imported facility '
                . $facilityId
                . ' to Place #'
                . $placeId
        );

        $placeStmt = $mainDb->prepare(
            'SELECT slug
             FROM places
             WHERE id = ?
             LIMIT 1'
        );
        $placeStmt->execute([$placeId]);

        return [
            'place_id' => $placeId,
            'slug' => (string) ($placeStmt->fetchColumn() ?: ''),
            'created' => $created,
            'campsites' => $siteCount,
            'attributes' => $attributeCount,
        ];
    } catch (Throwable $exception) {
        if ($mainDb->inTransaction()) {
            $mainDb->rollBack();
        }

        llama_ridb_log_sync_run(
            $ridbDb,
            'production_import',
            'failed',
            0,
            0,
            'Facility '
                . $facilityId
                . ': '
                . $exception->getMessage()
        );

        throw $exception;
    }
}

<?php
declare(strict_types=1);

function llama_place_experience_mode(array $place): string {
    $type = strtolower(trim((string) ($place['type'] ?? '')));

    if (in_array($type, ['developed-campground', 'rv-park-resort'], true)) {
        return 'campground';
    }

    if (in_array($type, ['dispersed-camping', 'camping-area', 'membership-host', 'private-property', 'fairgrounds'], true)) {
        return 'camping';
    }

    if (in_array($type, ['rest-area', 'public-parking', 'travel-center', 'truck-stop', 'retail-parking', 'restaurant-parking', 'casino-parking', 'medical-office-parking', 'hospital-parking', 'church-parking', 'street-parking', 'other-parking', 'vehicle-pulloff'], true)) {
        return 'parking';
    }

    return 'place';
}

function llama_place_campsite_type_label(?string $value): string {
    return match (strtolower(trim((string) $value))) {
        'rv_site' => 'RV site',
        'tent_site' => 'Tent site',
        'mixed_site' => 'Tent / RV',
        'vehicle_site' => 'Vehicle site',
        'group_site' => 'Group site',
        default => trim((string) $value),
    };
}

function llama_place_campsite_parking_label(?string $value): string {
    return match (strtolower(trim((string) $value))) {
        'pull_through' => 'Pull-through',
        'back_in' => 'Back-in',
        'pull_in' => 'Pull-in',
        'parallel' => 'Parallel',
        'unknown' => 'Unknown',
        default => trim((string) $value),
    };
}

function llama_place_campsite_hookup_label(?string $value): string {
    return match (strtolower(trim((string) $value))) {
        'full' => 'Full hookups',
        'electric_water' => 'Electric + water',
        'electric_only' => 'Electric only',
        'water_only' => 'Water only',
        'none' => 'No hookups',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
        default => trim((string) $value),
    };
}

function llama_place_facility_facts(PDO $db, int $placeId): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT * FROM place_facility_facts WHERE place_id = ? LIMIT 1'
    );
    $stmt->execute([$placeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

function llama_place_facility_features(PDO $db, int $placeId): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            feature_key,
            feature_value,
            qualifier,
            source_provider,
            source_external_id,
            source_updated_at
         FROM place_facility_features
         WHERE place_id = ?
           AND is_active = 1
         ORDER BY sort_order ASC, feature_key ASC, id ASC'
    );
    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_place_campsites(PDO $db, int $placeId): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            cs.id,
            cs.id AS feature_id,
            cs.map_feature_id,
            cs.site_code,
            cs.site_name,
            cs.raw_site_type,
            cs.site_type,
            cs.parking_style,
            cs.hookup_status,
            cs.accessible_status,
            cs.latitude,
            cs.longitude,

            cf.site_length_ft,
            cf.site_width_ft,
            cf.driveway_length_ft,
            cf.driveway_grade,
            cf.driveway_surface,
            cf.overhead_clearance_ft,
            cf.max_vehicle_length_ft,
            cf.max_people,
            cf.max_vehicles,
            cf.min_people,
            cf.min_vehicles,

            cf.tent_pad,
            cf.tent_pad_length_ft,
            cf.tent_pad_width_ft,

            cf.electric_hookup,
            cf.electric_service,
            cf.water_hookup,
            cf.sewer_hookup,

            cf.checkin_time,
            cf.checkout_time,

            cf.proximity_to_water,
            cf.shade_source_value,
            cf.privacy_source_value,
            cf.quiet_area_source_value,

            cf.max_horses,
            cf.bed_type,
            cf.bed_count,
            cf.bedroom_count,
            cf.room_count,
            cf.shower_bath_type,

            cf.internal_map_x,
            cf.internal_map_y,
            cf.internal_map_placed,

            cf.source_provider,
            cf.source_external_id,
            cf.source_updated_at,
            cf.source_url

         FROM place_campsites cs
         LEFT JOIN place_campsite_facts cf
            ON cf.campsite_id = cs.id
         WHERE cs.place_id = ?
           AND cs.is_active = 1
         ORDER BY
            COALESCE(
                NULLIF(cs.site_code, ""),
                NULLIF(cs.site_name, ""),
                CAST(cs.id AS CHAR)
            ) ASC,
            cs.id ASC'
    );
    $stmt->execute([$placeId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$rows) {
        return [];
    }

    $siteIds = array_values(
        array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        )
    );

    $featuresBySite = [];

    if ($siteIds) {
        $placeholders = implode(',', array_fill(0, count($siteIds), '?'));

        $featureStmt = $db->prepare(
            'SELECT
                campsite_id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                source_updated_at
             FROM place_campsite_features
             WHERE campsite_id IN (' . $placeholders . ')
               AND is_active = 1
             ORDER BY sort_order ASC, feature_key ASC, id ASC'
        );
        $featureStmt->execute($siteIds);

        foreach ($featureStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $feature) {
            $siteId = (int) ($feature['campsite_id'] ?? 0);

            if ($siteId > 0) {
                $featuresBySite[$siteId][] = $feature;
            }
        }
    }

    foreach ($rows as &$row) {
        $siteId = (int) $row['id'];
        $row['features'] = $featuresBySite[$siteId] ?? [];

        $row['display_name'] = trim((string) ($row['site_code'] ?? ''));

        if ($row['display_name'] === '') {
            $row['display_name'] = trim((string) ($row['site_name'] ?? ''));
        }

        if ($row['display_name'] === '') {
            $row['display_name'] = 'Site ' . $siteId;
        }
    }
    unset($row);

    return $rows;
}

function llama_place_campsite(PDO $db, int $placeId, int $campsiteId): array {
    if ($placeId < 1 || $campsiteId < 1) {
        return [];
    }

    foreach (llama_place_campsites($db, $placeId) as $site) {
        if ((int) ($site['id'] ?? 0) === $campsiteId) {
            return $site;
        }
    }

    return [];
}

function llama_place_campsite_browser_state(array $place, array $sites): array {
    $mode = llama_place_experience_mode($place);
    $siteCount = count($sites);
    $siteCapableMode = in_array($mode, ['campground', 'camping'], true);

    $showBrowser = $siteCapableMode && $siteCount > 1;
    $showSingleSite = $siteCapableMode && $siteCount === 1;

    return [
        'mode' => $mode,
        'site_count' => $siteCount,
        'show_browser' => $showBrowser,
        'show_single_site' => $showSingleSite,
        'show_site_layer' => $showBrowser || $showSingleSite,
    ];
}

function llama_place_campsite_summary(array $site): array {
    $summary = [];

    $siteType = llama_place_campsite_type_label((string) ($site['site_type'] ?? ''));
    if ($siteType !== '') {
        $summary[] = $siteType;
    }

    $parking = llama_place_campsite_parking_label((string) ($site['parking_style'] ?? ''));
    if ($parking !== '' && $parking !== 'Unknown') {
        $summary[] = $parking;
    }

    if (
        isset($site['max_vehicle_length_ft'])
        && is_numeric($site['max_vehicle_length_ft'])
        && (float) $site['max_vehicle_length_ft'] > 0
    ) {
        $summary[] =
            rtrim(
                rtrim(number_format((float) $site['max_vehicle_length_ft'], 1), '0'),
                '.'
            ) . ' ft max vehicle';
    }

    $hookups = llama_place_campsite_hookup_label((string) ($site['hookup_status'] ?? ''));
    if ($hookups !== '' && $hookups !== 'Unknown') {
        $summary[] = $hookups;
    }

    return $summary;
}

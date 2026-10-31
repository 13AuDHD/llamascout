<?php

declare(strict_types=1);

/**
 * Sensitive Place types are absent from public discovery unless the viewer
 * has Complete Access or durable access to that specific Place.
 */
function llama_place_is_member_only(array $place): bool
{
    return in_array(
        strtolower(trim((string) ($place['type'] ?? ''))),
        ['dispersed-camping', 'vehicle-pulloff'],
        true
    );
}

function llama_place_viewer_can_read_member_only(
    int $placeId,
    ?int $viewerUserId
): bool {
    return $placeId > 0
        && ($viewerUserId ?? 0) > 0
        && user_has_place_complete_access($placeId, $viewerUserId);
}

/**
 * Build the Place restriction portion of a database query.
 *
 * Values interpolated here are fixed application types or integer Place IDs,
 * never request text.
 */
function llama_place_visibility_sql(
    string $placeAlias = 'p',
    ?int $viewerUserId = null
): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $placeAlias)) {
        throw new InvalidArgumentException('Invalid Place query alias.');
    }

    if ($viewerUserId === null) {
        $viewer = current_user();
        $viewerUserId = is_array($viewer)
            ? (int) ($viewer['id'] ?? 0)
            : 0;
    }

    $viewerUserId = max(0, $viewerUserId);

    if (
        $viewerUserId > 0
        && user_has_member_access($viewerUserId)
    ) {
        return '1 = 1';
    }

    $placeAccessSql = '';

    if ($viewerUserId > 0) {
        $placeIds = array_values(array_filter(
            array_map(
                'intval',
                user_original_contributed_place_ids($viewerUserId)
            ),
            static fn(int $placeId): bool => $placeId > 0
        ));

        if ($placeIds) {
            $placeAccessSql =
                ' OR ' . $placeAlias . '.id IN ('
                . implode(', ', $placeIds)
                . ')';
        }
    }

    return '('
        . 'COALESCE(' . $placeAlias . ".type, '') NOT IN "
        . '("dispersed-camping", "vehicle-pulloff")'
        . $placeAccessSql
        . ')';
}

function llama_coordinate_status(
    mixed $latitude,
    mixed $longitude,
    mixed $verifiedAt = null
): string {
    if (
        $latitude === null
        || $longitude === null
        || trim((string) $latitude) === ''
        || trim((string) $longitude) === ''
        || !is_numeric($latitude)
        || !is_numeric($longitude)
        || (float) $latitude < -90
        || (float) $latitude > 90
        || (float) $longitude < -180
        || (float) $longitude > 180
        || ((float) $latitude === 0.0 && (float) $longitude === 0.0)
    ) {
        return 'missing';
    }

    return trim((string) $verifiedAt) === ''
        ? 'unverified'
        : 'verified';
}

/**
 * V3 Site privacy is derived from the canonical Place type.
 *
 * Ordinary Sites at developed campgrounds and RV parks have public basic
 * catalog/map identity. Sites at dispersed and other Places are member-only.
 * Site-specific records in place_site_visibility are RESTRICTIONS only.
 * They can never make a dispersed Place or Site public.
 *
 * The existing table is retained for mixed-use exceptions and old reviews.
 * Missing table/schema is an error, not permission to publish.
 */
function llama_site_restricted_visibility_ids(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT campsite_id FROM place_site_visibility
         WHERE place_id = ?
           AND (visibility = "members"
                OR site_kind IN ("dispersed", "unreviewed"))'
    );
    $stmt->execute([$placeId]);
    return array_fill_keys(
        array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
        true
    );
}

/**
 * The third argument contains restricted Site IDs. Absence of an override
 * follows Place type.
 */
function llama_site_is_public(array $place, array $site, array $restrictedSiteIds): bool
{
    $placeType = strtolower(trim((string) ($place['type'] ?? '')));
    if (!in_array($placeType, ['developed-campground', 'rv-park-resort'], true)) {
        return false;
    }

    // Legacy imports do not always use the word "dispersed".
    // Treat primitive/backcountry labels as restricted until explicitly
    // reconciled; a public developed Place cannot silently publish them.
    foreach (['raw_site_type', 'site_type'] as $field) {
        $siteType = strtolower((string) ($site[$field] ?? ''));
        foreach ([
            'dispersed',
            'primitive',
            'backcountry',
            'boondock',
            'vehicle-pulloff',
            'vehicle_pulloff',
            'vehicle pulloff',
            'vehicle pull-off',
        ] as $marker) {
            if (str_contains($siteType, $marker)) {
                return false;
            }
        }
    }

    $siteId = (int) ($site['id'] ?? 0);
    return $siteId > 0 && !isset($restrictedSiteIds[$siteId]);
}

function llama_site_can_read(
    array $place,
    array $site,
    bool $hasCompletePlaceAccess,
    array $restrictedSiteIds
): bool {
    if ($hasCompletePlaceAccess) {
        return true;
    }
    return llama_site_is_public($place, $site, $restrictedSiteIds);
}


/**
 * A narrow public Site detail payload for ordinary developed campground Sites.
 *
 * The caller must first enforce llama_site_can_read(). Never return Site GPS,
 * member Scout Reports, photos, or arbitrary imported feature attributes.
 * Extra capacity/size facts require a recognized public official source.
 */
function llama_site_public_catalog_details(array $site): array
{
    if (trim((string) ($site['source_provider'] ?? ''))
        !== 'Recreation.gov / RIDB') {
        return [];
    }

    $details = [];
    foreach ([
        'site_length_ft' => 'Site length',
        'site_width_ft' => 'Site width',
        'max_vehicle_length_ft' => 'Maximum vehicle length',
        'max_people' => 'Maximum people',
        'max_vehicles' => 'Maximum vehicles',
        'tent_pad_length_ft' => 'Tent pad length',
        'tent_pad_width_ft' => 'Tent pad width',
    ] as $key => $label) {
        $raw = $site[$key] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            continue;
        }

        $number = (float) $raw;
        if (!is_finite($number) || $number <= 0 || $number > 100000) {
            continue;
        }

        $value = rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
        $details[] = [
            'source_key' => $key,
            'label' => $label,
            'value' => $value . (str_contains($key, '_ft') ? ' ft' : ''),
        ];
    }

    foreach ([
        'water_hookup' => 'Water hookup',
        'sewer_hookup' => 'Sewer hookup',
        'tent_pad' => 'Tent pad',
    ] as $key => $label) {
        if (in_array(strtolower(trim((string) ($site[$key] ?? ''))),
            ['1', 'yes', 'true', 'y'], true)) {
            $details[] = [
                'source_key' => $key,
                'label' => $label,
                'value' => 'Available',
            ];
        }
    }

    return $details;
}

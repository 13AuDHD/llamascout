<?php

declare(strict_types=1);

/**
 * Shared location discoverability rules.
 *
 * Dispersed camping and vehicle pull-off locations are not discoverable by
 * guests or ordinary Free Members. Complete Access and durable Place-specific
 * contributor access continue to unlock the affected Place and its Sites.
 */
function llama_restricted_location_types(): array
{
    return [
        'dispersed-camping',
        'vehicle-pulloff',
    ];
}

function llama_location_type_key(?string $value): string
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function llama_location_type_is_restricted(?string $value): bool
{
    return in_array(
        llama_location_type_key($value),
        llama_restricted_location_types(),
        true
    );
}

function llama_location_viewer_user_id(?int $viewerUserId = null): int
{
    if ($viewerUserId !== null) {
        return max(0, $viewerUserId);
    }

    $viewer = current_user();

    return is_array($viewer)
        ? max(0, (int) ($viewer['id'] ?? 0))
        : 0;
}

function llama_place_visibility_allows(
    int $placeId,
    ?string $placeType,
    ?int $viewerUserId = null
): bool {
    if (!llama_location_type_is_restricted($placeType)) {
        return true;
    }

    $viewerUserId = llama_location_viewer_user_id($viewerUserId);

    return $viewerUserId > 0
        && user_has_place_complete_access($placeId, $viewerUserId);
}

/**
 * Build the Place portion of a server-side query visibility rule.
 *
 * The values interpolated here are fixed application constants or integers,
 * never request text.
 */
function llama_place_visibility_sql(
    string $placeAlias = 'p',
    ?int $viewerUserId = null
): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $placeAlias)) {
        throw new InvalidArgumentException('Invalid Place query alias.');
    }

    $viewerUserId = llama_location_viewer_user_id($viewerUserId);

    if (
        $viewerUserId > 0
        && user_has_member_access($viewerUserId)
    ) {
        return '1 = 1';
    }

    $types = implode(
        ', ',
        array_map(
            static fn (string $type): string =>
                "'" . str_replace("'", "''", $type) . "'",
            llama_restricted_location_types()
        )
    );

    $placeAccessSql = '';

    if ($viewerUserId > 0) {
        $placeIds = array_values(
            array_filter(
                array_map(
                    'intval',
                    user_original_contributed_place_ids($viewerUserId)
                ),
                static fn (int $placeId): bool => $placeId > 0
            )
        );

        if ($placeIds) {
            $placeAccessSql =
                ' OR ' . $placeAlias . '.id IN ('
                . implode(', ', $placeIds)
                . ')';
        }
    }

    return '('
        . 'COALESCE(' . $placeAlias . ".type, '') NOT IN (" . $types . ')'
        . $placeAccessSql
        . ')';
}

function llama_site_location_is_restricted(
    array $site,
    ?string $placeType = null
): bool {
    if (llama_location_type_is_restricted($placeType)) {
        return true;
    }

    foreach (['site_type', 'raw_site_type'] as $field) {
        if (llama_location_type_is_restricted((string) ($site[$field] ?? ''))) {
            return true;
        }
    }

    return llama_location_type_key(
        (string) ($site['parent_area_use'] ?? '')
    ) === 'dispersed-camping';
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

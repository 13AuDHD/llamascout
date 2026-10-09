<?php

declare(strict_types=1);

/**
 * Phase 3C: canonical Place / Area / Site scope definitions.
 *
 * These annotations deliberately do not modify existing answers,
 * applicability, scoring, public rendering, or database tables. They allow
 * the next persistence layer to validate which level owns an answer.
 *
 * A missing value inherits. A recorded No, Unknown, 0, or empty multiselect
 * is an explicit answer and must not silently inherit an ancestor's value.
 */
function llama_place_report_scope_groups(): array
{
    return [
        'place' => ['place'],
        'place_area' => ['place', 'area'],
        'all' => ['place', 'area', 'site'],
        'site' => ['site'],
    ];
}

function llama_place_report_scope_overrides(): array
{
    return [
        // Place identity and geography cannot be overridden by a campsite.
        'name' => 'place',
        'type' => 'place',
        'description' => 'place',
        'visited_at' => 'place',
        'latitude' => 'place',
        'longitude' => 'place',
        'elevation_feet' => 'place',
        'road' => 'place',
        'city' => 'place',
        'county' => 'place',
        'state' => 'place',
        'region' => 'place',
        'land_type' => 'place',
        'land_manager' => 'place',

        // Property access costs: not individual campsite rates.
        'entrance_facility_fee' => 'place',
        'parking_fee' => 'place',
        'campsite_count' => 'place_area',
        'nearest_town' => 'place',
        'nearest_fuel' => 'place',
        'nearest_ev_charging' => 'place',
        'nearest_alcohol_sales' => 'place',
        'nearest_propane' => 'place',
        'nearest_grocery' => 'place',
        'nearest_water' => 'place',
        'nearest_toilet' => 'place',
        'nearest_hospital' => 'place',

        // Physical campsite layout and utilities cannot be Place defaults.
        'site_number' => 'site',
        'site_accessible' => 'site',
        'max_people' => 'site',
        'overhead_clearance_feet' => 'site',
        'parking_length_feet' => 'site',
        'parking_grade' => 'site',
        'site_length_feet' => 'site',
        'site_width_feet' => 'site',
        'tent_pad' => 'site',
        'tent_pad_length_feet' => 'site',
        'tent_pad_width_feet' => 'site',
        'double_driveway' => 'site',
        'hike_in_distance_feet' => 'site',
        'site_hookups_available' => 'site',
        'hookup_electric' => 'site',
        'hookup_electric_service' => 'site',
        'hookup_water' => 'site',
        'hookup_sewer' => 'site',
        'capacity_size_rating' => 'site',
        'site_rating' => 'site',
        'condition_rating' => 'site',
        'location_rating' => 'site',

        // Shared facilities describe Place or Area, not a hookup at a site.
        'amenity_none' => 'place_area',
        'amenity_toilets' => 'place_area',
        'amenity_potable_water' => 'place_area',
        'amenity_trash' => 'place_area',
        'amenity_showers' => 'place_area',
        'amenity_dump_station' => 'place_area',
        'amenity_wifi' => 'place_area',
        'amenity_laundry' => 'place_area',
        'amenity_electricity' => 'place_area',
        'amenity_recycling' => 'place_area',
        'accessible_toilet' => 'place_area',
        'accessible_picnic_table' => 'place_area',

        // Official restrictions are always discoverable independently.
        'current_fire_restrictions_url' => 'place_area',
        'contributor_notes' => 'all',
    ];
}

function llama_place_report_apply_flow_scopes(array &$fields): void
{
    $groups = llama_place_report_scope_groups();
    $overrides = llama_place_report_scope_overrides();
    foreach ($fields as $key => &$field) {
        $group = $overrides[$key] ?? 'all';
        // Other amenities are normally shared facilities as well.
        if (!isset($overrides[$key]) && str_starts_with((string) $key, 'amenity_')) {
            $group = 'place_area';
        }
        $field['report_scopes'] = $groups[$group];
        $field['report_scope_group'] = $group;
        $field['scope_inheritance'] = $group === 'site' ? 'none' : 'nearest_recorded';
    }
    unset($field);
}

/**
 * Resolve a field from a set of flat answer maps keyed by canonical field ID.
 * Arrays use field keys, not storage paths. No DB operations occur here.
 *
 * Recorded answers must be represented by array_key_exists, even when
 * their value is null, empty, '0', or the canonical unknown token.
 * A 'site' only field never falls back to the Place by accident.
 */
function llama_place_report_resolve_scoped_answer(
    array $field,
    array $placeAnswers,
    array $areaAnswers = [],
    array $siteAnswers = [],
    string $target = 'place'
): array {
    if (!in_array($target, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException('Invalid reporting target.');
    }
    $key = (string) ($field['key'] ?? '');
    if ($key === '') {
        throw new InvalidArgumentException('A canonical field key is required.');
    }
    $allowed = (array) ($field['report_scopes'] ?? ['place']);
    $sources = match ($target) {
        'site' => ['site' => $siteAnswers, 'area' => $areaAnswers, 'place' => $placeAnswers],
        'area' => ['area' => $areaAnswers, 'place' => $placeAnswers],
        default => ['place' => $placeAnswers],
    };
    foreach ($sources as $scope => $answers) {
        if (in_array($scope, $allowed, true) && array_key_exists($key, $answers)) {
            return [
                'recorded' => true,
                'value' => $answers[$key],
                'source_scope' => $scope,
                'inherited' => $scope !== $target,
            ];
        }
    }
    return [
        'recorded' => false,
        'value' => null,
        'source_scope' => null,
        'inherited' => false,
    ];
}

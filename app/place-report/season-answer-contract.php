<?php

declare(strict_types=1);

/**
 * M4 season-overrides contract. A season changes operating conditions, not
 * geometry, dimensions, layout, access rights, or the immutable report ledger.
 *
 * Seasonal Site open/closed is separate operating metadata, never a substitute
 * for Admin-verified Place/Site closures.
 */

/** Only these EXISTING canonical questions may have a seasonal value. */
function llama_season_allowed_question_keys(): array
{
    return [
        // Shared Place/Area operations.
        'amenity_toilets',
        'amenity_potable_water',
        'amenity_showers',
        'amenity_trash',
        'amenity_dump_station',
        'amenity_laundry',
        'amenity_wifi',
        'amenity_campground_host',
        'water_filling_station',

        // Fire and cooking permissions (never physical equipment).
        'campfire_allowed',
        'place_charcoal_grills_allowed',
        'place_propane_fire_pits_allowed',
        'collecting_firewood',

        // Additional seasonal operating rules.
        'generator_restrictions',
        'generator_prohibited',
        'generator_quiet_hours',
        'generator_quiet_hours_begin',
        'generator_quiet_hours_end',
        'generator_run_restrictions',
        'generator_restriction_details',
        'place_quiet_hours',
        'place_quiet_hours_begin',
        'place_quiet_hours_end',
        'place_additional_rules',
        'reservation_required',
        'stay_limit_days',

        // Services at an individual Site (not permanent equipment dimensions).
        'site_hookups_available',
        'hookup_electric_service',
        'hookup_water',
        'hookup_sewer',
        'site_potable_water',
    ];
}

/**
 * Resolve using the ORIGINAL question contract and its report scopes.
 * This prevents a seasonal writer from inventing extra keys or weakening
 * Place/Area/Site ownership rules.
 */
function llama_season_question_for_scope(string $key, string $scope): array
{
    if (!in_array($scope, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException('Invalid seasonal reporting scope.');
    }
    if (!in_array($key, llama_season_allowed_question_keys(), true)) {
        throw new InvalidArgumentException(
            'This question is permanent or cannot be changed by season.'
        );
    }
    $field = llama_report_question($key);
    if (!is_array($field)
        || !in_array($scope, (array) ($field['report_scopes'] ?? []), true)
        || !empty($field['derived'])
        || !empty($field['location_field'])) {
        throw new InvalidArgumentException(
            'This seasonal question does not belong to that reporting target.'
        );
    }
    return $field;
}

/** For Admin presentation, only offer valid questions for a selected target. */
function llama_season_questions_for_scope(string $scope): array
{
    $allowed = [];
    foreach (llama_season_allowed_question_keys() as $key) {
        try {
            $allowed[$key] = llama_season_question_for_scope($key, $scope);
        } catch (InvalidArgumentException) {
            continue;
        }
    }
    return $allowed;
}

/**
 * Seasonal editor groups use the shared canonical question catalog.
 * A group never changes question definitions, ownership, or answer parsing.
 */
function llama_season_question_groups(string $scope): array
{
    $available = llama_season_questions_for_scope($scope);
    $definitions = [
        'Services & amenities' => [
            'amenity_toilets', 'amenity_potable_water', 'amenity_showers',
            'amenity_trash', 'amenity_dump_station', 'amenity_laundry',
            'amenity_wifi', 'amenity_campground_host',
            'water_filling_station',
        ],
        'Fire & cooking rules' => [
            'campfire_allowed', 'place_charcoal_grills_allowed',
            'place_propane_fire_pits_allowed', 'collecting_firewood',
        ],
        'Other seasonal rules' => [
            'generator_restrictions', 'generator_prohibited',
            'generator_quiet_hours', 'generator_quiet_hours_begin',
            'generator_quiet_hours_end', 'generator_run_restrictions',
            'generator_restriction_details',
            'place_quiet_hours', 'place_quiet_hours_begin',
            'place_quiet_hours_end', 'place_additional_rules',
            'reservation_required', 'stay_limit_days',
        ],
        'Site services' => [
            'site_hookups_available', 'hookup_electric_service',
            'hookup_water', 'hookup_sewer', 'site_potable_water',
        ],
    ];
    $groups = [];
    foreach ($definitions as $heading => $keys) {
        $items = array_intersect_key($available, array_flip($keys));
        if ($items) {
            // Display in approved group order, independent of catalog internals.
            $groups[$heading] = [];
            foreach ($keys as $key) {
                if (isset($items[$key])) {
                    $groups[$heading][$key] = $items[$key];
                }
            }
        }
    }
    return $groups;
}

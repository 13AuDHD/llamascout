<?php

declare(strict_types=1);

function llama_place_report_apply_parking_profiles(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    $travelCenterNotApplicable = [
        'region',
        'vehicle_capacity',
        'tree_cover',
        'road_surface',
        'road_width',
        'environment_water_nearby',
        'environment_wildlife',
        'environment_bugs',
        'critter_activity',
        'sensory_dust_from_traffic',
        'sensory_wildlife_noise',
        'flash_flood_risk',
        'wildfire_risk',
        'cliff_exposure',
        'rockfall_risk',
        'wildlife_risk',
        'emergency_access',
        'warning_possible_downed_trees',
        'warning_passing_vehicle_dust',
        'warning_motorized_recreation_traffic',
        'warning_blind_turn_traffic_nearby',
        'road_exposure',
        'best_months',
        'winter_access',
        'hurricane_risk',
        'snow_risk',
        'mud_season_risk',
        'monsoon_risk',
        'seasonal_access_note',
        'dispersed_camping_allowed',
        'collecting_firewood',
        'campfire_allowed',
        'drone_use_legal',
        'target_shooting_allowed',
        'designated_sites_only',
        'food_storage_required',
        'existing_sites_encouraged',
        'pack_it_in_pack_it_out',
        'current_fire_restrictions_url',
        'nearest_fuel',
        'experience_sunrise_view',
        'experience_sunset_view',
        'experience_overall_scenery',
        'experience_night_sky',
        'experience_stargazing',
        'experience_extended_stay_comfort',
        'experience_sensory_retreat',
    ];

    llama_place_report_append_applicable(
        $fields,
        $travelCenterNotApplicable,
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['travel_center'],
        ]]
    );

    $parkingTypes =
        array_merge(
            $types['commercial_parking'],
            $types['roadside_parking']
        );

    llama_place_report_append_applicable(
        $fields,
        [
            'vehicle_capacity',
            'designated_sites_only',
            'existing_sites_encouraged',
            'dispersed_camping_allowed',
            'collecting_firewood',
            'target_shooting_allowed',
            'food_storage_required',
            'warning_possible_downed_trees',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $parkingTypes,
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        [
            'region',
            'tree_cover',
            'environment_wildlife',
            'environment_bugs',
            'critter_activity',
            'sensory_wildlife_noise',
            'cliff_exposure',
            'rockfall_risk',
            'wildlife_risk',
            'warning_motorized_recreation_traffic',
            'warning_passing_vehicle_dust',
            'current_fire_restrictions_url',
            'campfire_allowed',
            'drone_use_legal',
            'experience_sensory_retreat',
            'experience_extended_stay_comfort',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['commercial_parking'],
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        [
            'best_months',
            'winter_access',
            'mud_season_risk',
            'snow_risk',
            'monsoon_risk',
            'hurricane_risk',
            'heat_season_risk',
            'seasonal_access_note',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['commercial_parking'],
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        [
            'turnaround_space',
            'pull_through',
            'back_in',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'street-parking',
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        [
            'vehicle_capacity',
            'pull_through',
            'back_in',
            'target_shooting_allowed',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['natural_day_use'],
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        ['collecting_firewood'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'scenic-overlook',
        ]]
    );

    $overnightUseForNaturalDayUse = [[
        'operator' => 'any',
        'rules' => [
            [
                'field' => 'type',
                'operator' => 'not_in',
                'value' => $types['natural_day_use'],
            ],
            [
                'field' => 'overnight_camping_allowed',
                'operator' => 'in',
                'value' => [
                    '1',
                    '2',
                    '3',
                ],
            ],
        ],
    ]];

    llama_place_report_append_applicable(
        $fields,
        [
            'fee',
            'experience_overnight_comfort',
        ],
        $overnightUseForNaturalDayUse
    );
}

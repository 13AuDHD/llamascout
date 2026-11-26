<?php

declare(strict_types=1);

function llama_place_report_apply_parking_profiles(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    $travelCenterNotApplicable = [
        'environment_wildlife',
        'environment_bugs',
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
        'winter_access',
        'seasonal_access_note',
        'existing_sites_encouraged',
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
            'existing_sites_encouraged',
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
            'environment_wildlife',
            'environment_bugs',
            'cliff_exposure',
            'rockfall_risk',
            'wildlife_risk',
            'warning_motorized_recreation_traffic',
            'warning_passing_vehicle_dust',
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
            'winter_access',
            'seasonal_access_note',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['commercial_parking'],
        ]]
    );

}

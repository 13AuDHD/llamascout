<?php

declare(strict_types=1);

/**
 * Site -> Parking & Dimensions organization.
 *
 * This pass does not change storage or persistence. It only places the
 * existing canonical campsite geometry/vehicle-fit questions together and
 * improves a few labels so the section reads as one coherent site profile.
 */
function llama_place_report_apply_site_parking_metadata(
    array &$fields
): void {
    $parkingFields = [
        'parking_surface',
        'ground_condition',
        'parking_grade',
        'levelness',
        'parking_length_feet',
        'site_length_feet',
        'site_width_feet',
        'overhead_clearance_feet',
        'max_vehicle_length_feet',
        'max_rv_length_feet',
        'turnaround_space',
        'pull_through',
        'back_in',
        'double_driveway',
        'hike_in_distance_feet',
        'site_access_difficulty',
    ];

    foreach ($parkingFields as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['layout_section'] =
            'site';

        $fields[$key]['layout_group'] =
            'parking_dimensions';
    }

    $labels = [
        'parking_surface' =>
            'Parking / driveway surface',

        'ground_condition' =>
            'Campsite ground condition',

        'parking_grade' =>
            'Parking / driveway grade',

        'levelness' =>
            'Campsite levelness',

        'parking_length_feet' =>
            'Parking / driveway length (ft)',

        'site_length_feet' =>
            'Usable campsite length (ft)',

        'site_width_feet' =>
            'Usable campsite width (ft)',

        'overhead_clearance_feet' =>
            'Overhead clearance (ft)',

        'max_vehicle_length_feet' =>
            'Maximum vehicle length',

        'max_rv_length_feet' =>
            'Maximum RV / trailer length',

        'turnaround_space' =>
            'Turnaround space?',

        'pull_through' =>
            'Pull-through?',

        'back_in' =>
            'Back-in?',

        'double_driveway' =>
            'Double-width driveway?',

        'hike_in_distance_feet' =>
            'Hike-in distance (ft)',

        'site_access_difficulty' =>
            'Campsite access difficulty',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'parking_surface' =>
            'Surface where the vehicle or RV actually parks at this campsite.',

        'ground_condition' =>
            'Ground condition of the usable camping area, not the access road.',

        'parking_length_feet' =>
            'Approximate usable parking or driveway length at this campsite.',

        'site_length_feet' =>
            'Approximate usable length of the overall campsite area.',

        'site_width_feet' =>
            'Approximate usable width of the overall campsite area.',

        'overhead_clearance_feet' =>
            'Lowest practical overhead clearance at the campsite or its immediate parking space.',

        'max_vehicle_length_feet' =>
            'Largest single vehicle that can reasonably fit without blocking access or leaving the usable site.',

        'max_rv_length_feet' =>
            'Largest RV, travel trailer, fifth wheel, motorhome, or similar camping rig that can reasonably fit.',

        'turnaround_space' =>
            'Whether a vehicle can turn around at or immediately beside this campsite without backing a long distance.',

        'pull_through' =>
            'Whether the campsite can be entered and exited without backing out.',

        'back_in' =>
            'Whether the primary parking position is a back-in space.',

        'double_driveway' =>
            'Whether the parking/driveway area is wide enough for two vehicles side by side.',

        'hike_in_distance_feet' =>
            'Approximate walking distance from the nearest vehicle parking point to the campsite.',

        'site_access_difficulty' =>
            'Difficulty of getting a vehicle into the campsite itself. Road difficulty belongs under Getting There.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }

    /*
     * The combined RV/trailer length should only appear when the combined
     * RV/trailer suitability answer is Yes.
     */
    if (isset($fields['max_rv_length_feet'])) {
        $fields['max_rv_length_feet']['applicable_if'] = [[
            'field' => 'rv_suitable',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }
}

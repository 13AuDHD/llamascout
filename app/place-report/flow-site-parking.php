<?php

declare(strict_types=1);

/**
 * Site -> Parking & Dimensions organization.
 *
 * Physical-fit questions are objective Site facts. They remain independent of
 * camping permission and of historical subjective suitability answers.
 *
 * "Getting There" describes the route to the Place. "Final Site entry"
 * describes only the last maneuver into the selected Site or overnight space.
 */
function llama_place_report_apply_site_parking_metadata(
    array &$fields
): void {
    $labels = [
        'parking_surface' =>
            'Site parking / driveway surface',

        'ground_condition' =>
            'Site ground condition',

        'parking_grade' =>
            'Site parking / driveway grade',

        'levelness' =>
            'Site levelness',

        'parking_length_feet' =>
            'Site parking / driveway length (ft)',

        'site_length_feet' =>
            'Usable Site length (ft)',

        'site_width_feet' =>
            'Usable Site width (ft)',

        'overhead_clearance_feet' =>
            'Site overhead clearance (ft)',

        'max_vehicle_length_feet' =>
            'Maximum vehicle length',

        'max_rv_length_feet' =>
            'Maximum RV / trailer length',

        'turnaround_space' =>
            'Turnaround at Site?',

        'pull_through' =>
            'Pull-through Site?',

        'back_in' =>
            'Back-in Site?',

        'parking_size' =>
            'Parking Size',

        'double_driveway' =>
            'Double-width Site driveway?',

        'hike_in_distance_feet' =>
            'Distance from vehicle parking to Site (ft)',

        'site_access_difficulty' =>
            'Final Site entry difficulty',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'parking_surface' =>
            'Surface where the vehicle or RV actually parks at this Site. The approach-road surface belongs under Getting There.',

        'ground_condition' =>
            'Ground condition of the usable Site area, not the approach road.',

        'parking_grade' =>
            'Slope of the parking or driveway area within this Site.',

        'parking_length_feet' =>
            'Approximate usable parking or driveway length within this Site.',

        'site_length_feet' =>
            'Approximate usable length of the individual Site or overnight space.',

        'site_width_feet' =>
            'Approximate usable width of the individual Site or overnight space.',

        'overhead_clearance_feet' =>
            'Lowest practical overhead clearance within the Site or its immediate entry.',

        'max_vehicle_length_feet' =>
            'Largest single vehicle that can physically use this Site based on the parking space, maneuvering room, clearance, and immediate Site entry.',

        'max_rv_length_feet' =>
            'Largest RV, travel trailer, fifth wheel, motorhome, or similar camping rig that can physically use this Site. Record physical fit even when overnight RV use is prohibited or unknown.',

        'turnaround_space' =>
            'Whether a vehicle can turn around at or immediately beside this Site without backing a long distance.',

        'pull_through' =>
            'Whether the Site can be entered and exited without backing out.',

        'back_in' =>
            'Whether the primary parking position is a back-in space.',

        'parking_size' =>
            'Select Single, Double, Triple, or Group based on the parking area available at this Site.',

        'double_driveway' =>
            'Whether the Site parking or driveway area is wide enough for two vehicles side by side.',

        'hike_in_distance_feet' =>
            'Approximate distance from the nearest practical vehicle parking point to the usable Site area.',

        'site_access_difficulty' =>
            'Difficulty of the final maneuver or route into this individual Site. Road and route difficulty before reaching the Place belongs under Getting There.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }

    if (isset($fields['max_rv_length_feet'])) {
        $fields['max_rv_length_feet']['applicable_if'] =
            [];
    }
}

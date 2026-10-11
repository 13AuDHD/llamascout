<?php

declare(strict_types=1);

/** Approved road-condition facts, using the existing road-access scoring group. */
function llama_place_report_add_road_condition_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];

    // One stored selection controls which of the existing severity fields appear.
    // Existing severity answers remain in their original storage paths.
    $add('approach_road_hazards', 'Road hazards present', 'road_access',
        'multiselect', 'details.approach_road_hazards', $common + [
            'options' => [
                'rocks' => 'Rocks',
                'washboards' => 'Washboards',
                'potholes' => 'Potholes',
                'mud_risk' => 'Mud',
                'steep_grades' => 'Steep grades',
                'drop_off_exposure' => 'Drop-offs',
                'water_crossings' => 'Water crossings',
                'downed_tree_risk' => 'Downed trees',
                'approach_deep_ruts' => 'Deep ruts',
                'none' => 'None',
                'unknown' => 'Unknown',
            ],
            'summary' => 'Select hazards',
            'wide' => true,
        ]);

    $add('approach_road_condition', 'Road condition', 'road_access', 'rating',
        'details.approach_road_condition', $common + [
            'low' => 'Very poor',
            'high' => 'Excellent',
        ]);

    $add('approach_deep_ruts', 'Deep ruts', 'road_access', 'rating',
        'details.approach_deep_ruts', $common + [
            'low' => 'None',
            'high' => 'Severe',
        ]);
}

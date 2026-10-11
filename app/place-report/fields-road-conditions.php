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

/**
 * Use the same canonical applicability rules for the form, live completion,
 * server completion, and scoring eligibility. The selection is authoritative;
 * an unselected severity is not an unanswered required question.
 *
 * Existing answers remain in their original storage paths. Changing the
 * hazard selector does not erase them; reselecting a hazard exposes its
 * previously recorded detail.
 */
function llama_place_report_apply_road_hazard_dependencies(array &$fields): void
{
    $hazards = [
        'rocks',
        'washboards',
        'potholes',
        'mud_risk',
        'steep_grades',
        'drop_off_exposure',
        'water_crossings',
        'downed_tree_risk',
        'approach_deep_ruts',
    ];

    foreach ($hazards as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $existing = (array) ($fields[$key]['applicable_if'] ?? []);
        $existing[] = [
            'field' => 'approach_road_hazards',
            'operator' => 'in',
            'value' => [$key],
        ];
        $fields[$key]['applicable_if'] = $existing;
    }
}

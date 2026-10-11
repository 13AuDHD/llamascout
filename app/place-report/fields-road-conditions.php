<?php

declare(strict_types=1);

/** Approved road-condition facts, using the existing road-access scoring group. */
function llama_place_report_add_road_condition_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];

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

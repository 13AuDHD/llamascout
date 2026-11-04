<?php

declare(strict_types=1);

/** Additional approved Getting There questions. */
function llama_place_report_add_road_access_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];

    $add('approach_turnaround_available', 'Turnaround available?',
        'road_access', 'tri', 'details.approach_turnaround_available', $common);

    $add('approach_overhead_obstacles', 'Low branches or overhead obstacles?',
        'road_access', 'tri', 'details.approach_overhead_obstacles', $common);

    $add('approach_access_restrictions', 'Road accessibility restrictions',
        'road_access', 'textarea', 'details.approach_access_restrictions', $common + [
            'rows' => 3,
            'wide' => true,
            'counts_toward_completion' => false,
            'applicable_if' => [
                ['any' => [
                    ['field' => 'approach_overhead_obstacles', 'operator' => 'equals', 'value' => '1'],
                    ['field' => 'approach_turnaround_available', 'operator' => 'equals', 'value' => '0'],
                ]],
            ],
        ]);
}

<?php

declare(strict_types=1);

/** Additional approved Getting There questions. */
function llama_place_report_add_road_access_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];

    $add('approach_width_restricted', 'Narrow sections restrict larger vehicles?',
        'road_access', 'tri', 'details.approach_width_restricted', $common);

    $add('approach_narrowest_width_feet', 'Narrowest usable road width',
        'road_access', 'select', 'details.approach_narrowest_width_feet', $common + [
            'options' => [
                '6' => 'About 6 ft',
                '7' => 'About 7 ft',
                '8' => 'About 8 ft',
                '9' => 'About 9 ft',
                '10' => 'About 10 ft',
                '11' => 'About 11 ft',
                '12' => 'About 12 ft',
                '14' => 'About 14 ft',
                '16' => 'About 16 ft',
                '20' => '20+ ft',
            ],
            'applicable_if' => [
                ['field' => 'approach_width_restricted', 'operator' => 'equals', 'value' => '1'],
            ],
        ]);

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
                    ['field' => 'approach_width_restricted', 'operator' => 'equals', 'value' => '1'],
                    ['field' => 'approach_overhead_obstacles', 'operator' => 'equals', 'value' => '1'],
                    ['field' => 'approach_turnaround_available', 'operator' => 'equals', 'value' => '0'],
                ]],
            ],
        ]);
}

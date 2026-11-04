<?php

declare(strict_types=1);

/** Road access seasonality, separate from the property's operating season. */
function llama_place_report_add_road_seasonal_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];
    $add('approach_winter_maintenance', 'Winter Road Maintenance', 'road_access',
        'select', 'details.approach_winter_maintenance', $common + [
            'options' => [
                'not_applicable' => 'Not Applicable',
                'occasional' => 'Occasionally Plowed',
                'regular' => 'Frequently Plowed',
                'none' => 'Not Maintained',
            ],
        ]);
    $add('approach_snow_chains', 'Snow Chains', 'road_access',
        'select', 'details.approach_snow_chains', $common + [
            'options' => [
                'not_applicable' => 'Not Applicable',
                'no' => 'No',
                'conditional' => 'Recommended',
                'required' => 'Required',
            ],
        ]);
}

<?php

declare(strict_types=1);

/** Road access seasonality, separate from the property's operating season. */
function llama_place_report_add_road_seasonal_questions(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ];
    // Retained for historical data; the active form asks about seasonal road closure.
    $add('approach_year_round', 'Road accessible year-round?', 'road_access',
        'tri', 'details.approach_year_round', $common + [
            'counts_toward_completion' => false,
        ]);
    $add('approach_seasonal_closure', 'Seasonal Road Closure', 'road_access',
        'tri', 'details.approach_seasonal_closure', $common);

    $months = [];
    foreach (range(1, 12) as $month) {
        $months[(string) $month] = date('F', mktime(0, 0, 0, $month, 1));
    }
    $days = [];
    foreach (range(1, 31) as $day) {
        $days[(string) $day] = (string) $day;
    }
    $whenClosed = [
        'applicable_if' => [[
            'field' => 'approach_seasonal_closure',
            'operator' => 'equals',
            'value' => '1',
        ]],
    ];
    foreach (['closure' => 'Season Begins', 'reopening' => 'Season Ends'] as $key => $label) {
        $add('approach_' . $key . '_month', $label . ': month', 'road_access',
            'select', 'details.approach_' . $key . '_month',
            $common + $whenClosed + ['options' => $months]);
        $add('approach_' . $key . '_day', $label . ': day', 'road_access',
            'select', 'details.approach_' . $key . '_day',
            $common + $whenClosed + ['options' => $days]);
    }
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
    $add('approach_seasonal_restrictions', 'Seasonal access restrictions', 'road_access',
        'textarea', 'details.approach_seasonal_restrictions', $common + [
            'rows' => 3, 'wide' => true, 'counts_toward_completion' => false,
        ]);
    $add('approach_road_conditions_url', 'Official road conditions / closure URL',
        'road_access', 'url', 'details.approach_road_conditions_url', $common + [
            'counts_toward_completion' => false,
        ]);
}

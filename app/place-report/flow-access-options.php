<?php

declare(strict_types=1);

/**
 * Three-state access advice for road vehicle clearance and 4WD.
 * Existing stored false/true values map to No/Recommended. A new
 * value of "2" means Required, including a legally enforced requirement.
 * Keep the existing canonical keys and storage paths for old reports.
 */
function llama_place_report_apply_access_options(array &$fields): void
{
    $options = [
        '0' => 'No',
        '1' => 'Recommended',
        '2' => 'Required',
    ];

    $questions = [
        'high_clearance_recommended' => [
            'High clearance',
            'No = ordinary clearance is sufficient. Recommended = extra ground clearance is advisable. Required = standard-clearance vehicles cannot safely or legally access this location.',
        ],
        'four_wheel_drive_recommended' => [
            '4WD',
            'No = 4WD is not necessary. Recommended = helpful under expected conditions. Required = mandatory for access, whether due to road conditions or an enforced restriction. AWD does not automatically meet a 4WD-only requirement.',
        ],
    ];

    foreach ($questions as $key => [$label, $help]) {
        if (!isset($fields[$key])) {
            continue;
        }
        $fields[$key]['label'] = $label;
        $fields[$key]['type'] = 'select';
        $fields[$key]['options'] = $options;
        $fields[$key]['allow_unknown'] = true;
        $fields[$key]['help'] = $help;
    }
}

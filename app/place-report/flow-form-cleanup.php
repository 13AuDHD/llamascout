<?php

declare(strict_types=1);

/**
 * Final active-form cleanup. Historical and calculated fields remain
 * registered for existing answers but do not participate in completion.
 * Questions based on a Scout's observation remain editable in Issue #6.
 */
function llama_place_report_apply_form_cleanup_metadata(array &$fields): void
{
    $derivedCompatibility = [
        'recommended_overnight_stop',
        'recommended_quiet_evening',
        'recommended_extended_stay',
        'recommended_sensory_retreat',
        'recommended_stargazing',
        'recommended_remote_work',
    ];

    $legacySeasonCompatibility = [
        'season_begins',
        'season_ends',
        'seasonal_closure',
    ];

    foreach (array_merge($derivedCompatibility, $legacySeasonCompatibility) as $key) {
        if (!isset($fields[$key])) {
            continue;
        }
        $fields[$key]['layout_section'] = 'compatibility';
        $fields[$key]['layout_group'] = 'compatibility';
        $fields[$key]['counts_toward_completion'] = false;
        $fields[$key]['points_categories'] = [];
    }

    foreach ($derivedCompatibility as $key) {
        if (isset($fields[$key])) {
            $fields[$key]['derived'] = true;
        }
    }

    // The previous Double Driveway question remains stored as legacy data.
    if (isset($fields['double_driveway'])) {
        $fields['double_driveway']['layout_section'] = 'compatibility';
        $fields['double_driveway']['layout_group'] = 'compatibility';
        $fields['double_driveway']['counts_toward_completion'] = false;
        $fields['double_driveway']['points_categories'] = [];
    }

    // Felt safe at night is a separate observed answer, not a calculation
    // from daytime safety. It belongs beside the daytime question.
    if (isset($fields['felt_safe_nighttime'])) {
        $fields['felt_safe_nighttime']['layout_section'] = 'site';
        $fields['felt_safe_nighttime']['layout_group'] = 'safety_hazards';
        $fields['felt_safe_nighttime']['derived'] = false;
    }
}

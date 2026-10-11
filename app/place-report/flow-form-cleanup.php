<?php

declare(strict_types=1);

/**
 * Final active-form cleanup.
 *
 * Some historical/derived keys must remain in the canonical registry because
 * older reports, imports, and read paths still know about them. They should
 * not, however, render as editable contributor questions.
 */
function llama_place_report_apply_form_cleanup_metadata(
    array &$fields
): void {
    /*
     * Derived values are calculated from other questions. The shared form
     * renderer does not have a dedicated "derived" input type, so leaving
     * these in an active layout group can accidentally produce a plain text
     * box for a value the Scout should never type manually.
     */
    $derivedCompatibility = [
        'leveling_required',
        'felt_safe_nighttime',
        'recommended_overnight_stop',
        'recommended_quiet_evening',
        'recommended_extended_stay',
        'recommended_sensory_retreat',
        'recommended_stargazing',
        'recommended_remote_work',
    ];

    /*
     * These are older generic operating-season keys. The current Place form
     * has explicit Open Year-Round plus operating start/end month/day fields,
     * and Getting There has its own approach-road seasonal closure fields.
     * Keep historical/source data readable, but do not ask the Scout the same
     * seasonal question twice.
     */
    $legacySeasonCompatibility = [
        'season_begins',
        'season_ends',
        'seasonal_closure',
    ];

    foreach (
        array_merge(
            $derivedCompatibility,
            $legacySeasonCompatibility
        )
        as $key
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['layout_section'] =
            'compatibility';

        $fields[$key]['layout_group'] =
            'compatibility';

        $fields[$key]['counts_toward_completion'] =
            false;

        $fields[$key]['points_categories'] =
            [];
    }

    /*
     * Explicitly preserve the canonical derived flag for true derived keys.
     * Legacy season fields are historical compatibility data, not derived.
     */
    foreach ($derivedCompatibility as $key) {
        if (isset($fields[$key])) {
            $fields[$key]['derived'] = true;
        }
    }
}

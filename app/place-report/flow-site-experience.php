<?php

declare(strict_types=1);

/**
 * Site experience and recommendation terminology.
 *
 * Scout experience ratings are deliberately subjective and are kept separate
 * from objective Site facts such as dimensions, surface, clearance, hookups,
 * and access. Imported/source classifications remain fallback source data and
 * are not reinterpreted as Scout ratings.
 */
function llama_place_report_apply_site_experience_metadata(
    array &$fields
): void {
    /* Official classifications remain separate from Scout opinions. */
    foreach ([
        'recommended_remote_work' => 'Remote Work',
        'recommended_overnight_stop' => 'Overnight Stop',
        'recommended_extended_stay' => 'Extended Stay',
        'recommended_solo_travel' => 'Solo Travelers',
        'recommended_families' => 'Families',
        'recommended_large_groups' => 'Large Groups',
    ] as $key => $label) {
        if (!isset($fields[$key])) {
            continue;
        }
        $fields[$key]['label'] = $label;
        $fields[$key]['derived'] = false;
        unset($fields[$key]['derived_from']);
    }

    $recommendationHelp = [
        'recommended_solo_travel' =>
            'Whether this Place, Area, or Site felt like a good match for solo travel based on your observed experience. Use objective safety, access, and facility fields separately.',

        'recommended_families' =>
            'Whether this Place, Area, or Site felt like a good match for families based on your observed experience. This is a recommendation, not a safety certification.',

        'recommended_large_groups' =>
            'Whether this Place, Area, or Site felt like a good match for larger groups. Use capacity limits and Site dimensions for objective limits.',

        'not_recommended_for' =>
            'Describe travel styles, equipment, sensitivities, or group needs that were a poor match in your observed experience. Do not use this instead of recording objective restrictions, dimensions, hazards, or accessibility facts.',
    ];

    foreach ($recommendationHelp as $key => $help) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $help;
        }
    }
}

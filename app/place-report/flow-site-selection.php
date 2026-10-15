<?php

declare(strict_types=1);

/**
 * Site identity and legacy suitability cleanup.
 *
 * Permission answers whether an overnight use is allowed.
 * Objective Site facts describe the actual physical space.
 *
 * Historical suitability fields remain registered so older reports/imports
 * stay readable, but they are not active contributor questions because they
 * collapse several measurable facts into a subjective verdict.
 */
function llama_place_report_apply_site_selection_metadata(
    array &$fields
): void {
    $individualSiteTypes = [
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    if (isset($fields['site_number'])) {
        $fields['site_number']['label'] =
            'Campsite number / name';

        $fields['site_number']['applicable_if'] = [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $individualSiteTypes,
        ]];

        $fields['site_number']['help'] =
            'Use the posted campsite number or name. For an existing Llama Scout Place, select the matching stored campsite whenever one exists so the identifier stays consistent.';
    }

    if (isset($fields['max_rv_length_feet'])) {
        $fields['max_rv_length_feet']['label'] =
            'Maximum RV / trailer length';

        $fields['max_rv_length_feet']['help'] =
            'Record the longest RV or trailer that can physically use this campsite based on the usable parking space, turning room, clearance, and immediate Site access. This is a physical-fit fact, not a permission question.';
    }

    /*
     * Compatibility-only subjective/duplicate Site questions.
     *
     * Keep their storage keys intact. Do not reinterpret old answers as new
     * objective facts and do not award completion or points for them.
     */
    foreach (
        [
            'rv_suitable',
            'tent_camping_suitable',
            'trailer_suitable',
            'max_trailer_length_feet',
        ]
        as $legacyKey
    ) {
        if (!isset($fields[$legacyKey])) {
            continue;
        }

        $fields[$legacyKey]['counts_toward_completion'] =
            false;

        $fields[$legacyKey]['points_categories'] =
            [];

        $fields[$legacyKey]['section'] =
            'compatibility';

        $fields[$legacyKey]['display_section'] =
            'compatibility';

        $fields[$legacyKey]['layout_section'] =
            'compatibility';

        $fields[$legacyKey]['layout_group'] =
            'compatibility';

        $fields[$legacyKey]['hide_public'] =
            true;
    }
}

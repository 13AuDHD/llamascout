<?php

declare(strict_types=1);

/**
 * Site identity and legacy suitability cleanup.
 *
 * "Site" is the canonical target term. A Site may be a campground campsite,
 * an individually mapped designated campsite, an RV space, or another
 * individually identified overnight space.
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

    if (isset($fields['campsite_count'])) {
        $fields['campsite_count']['label'] =
            'Number of campsites / overnight spaces';

        $fields['campsite_count']['help'] =
            'Enter the number of individual overnight Sites when this Place or Area has defined campsites, RV spaces, or other assigned overnight spaces. Leave unanswered when there are no individually defined Sites.';
    }

    if (isset($fields['site_number'])) {
        $fields['site_number']['label'] =
            'Site / space number or name';

        $fields['site_number']['applicable_if'] = [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $individualSiteTypes,
        ]];

        $fields['site_number']['placeholder'] =
            'Example: 14, B-27, Loop C #8, RV Space 4';

        $fields['site_number']['help'] =
            'Use the posted identifier for this individual overnight Site. This may be a campground campsite number, an RV-space number, or another assigned Site name. For an existing Llama Scout Place, select the matching stored Site whenever one exists.';
    }

    if (isset($fields['max_rv_length_feet'])) {
        $fields['max_rv_length_feet']['label'] =
            'Maximum RV / trailer length';

        $fields['max_rv_length_feet']['help'] =
            'Record the longest RV or trailer that can physically use this Site based on the usable parking space, turning room, clearance, and immediate Site access. This is a physical-fit fact, not a permission question.';
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

<?php

declare(strict_types=1);

/**
 * Site-selection cleanup.
 *
 * New Places should only ask for a campsite identifier when the Place type
 * normally contains individually identified campsites. Existing Places can
 * still use their stored campsite catalog through the browser-side selector.
 *
 * RV and trailer suitability are presented as one question. The older
 * trailer-only fields remain registered only as compatibility data so older
 * reports/imports are not destroyed.
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

    if (isset($fields['rv_suitable'])) {
        $fields['rv_suitable']['label'] =
            'RV / trailer suitable?';

        $fields['rv_suitable']['help'] =
            'Answer for towable campers, fifth wheels, motorhomes, truck campers, and similar RVs. Use the size/length question for the actual fit limit.';
    }

    if (isset($fields['max_rv_length_feet'])) {
        $fields['max_rv_length_feet']['label'] =
            'Maximum RV / trailer length';
    }

    foreach (
        [
            'trailer_suitable',
            'max_trailer_length_feet',
        ]
        as $legacyKey
    ) {
        if (!isset($fields[$legacyKey])) {
            continue;
        }

        $fields[$legacyKey]['derived'] = true;
        $fields[$legacyKey]['counts_toward_completion'] = false;
        $fields[$legacyKey]['points_categories'] = [];

        // Keep the legacy storage key readable by older data/import code, but
        // remove the duplicate contributor/moderator question from the form.
        $fields[$legacyKey]['section'] = 'compatibility';
        $fields[$legacyKey]['display_section'] = 'compatibility';
        $fields[$legacyKey]['layout_section'] = 'compatibility';
        $fields[$legacyKey]['layout_group'] = 'compatibility';
        $fields[$legacyKey]['hide_public'] = true;
    }
}

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
    $ratings = [
        'site_rating' => [
            'Scout Site rating',
            'Your overall Scout rating of this individual Site based on the experience you observed. This is subjective and must not replace objective Site facts.',
        ],

        'condition_rating' => [
            'Scout condition rating',
            'Your Scout rating of the Site condition and upkeep when observed. Record measurable condition facts elsewhere when a specific field exists.',
        ],

        'location_rating' => [
            'Scout location rating',
            'Your Scout rating of this Site position within the surrounding Place, including privacy, convenience, views, and nearby activity.',
        ],
    ];

    foreach ($ratings as $key => [$label, $help]) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['label'] =
            $label;

        $fields[$key]['help'] =
            $help;

        $fields[$key]['layout_section'] =
            'site';

        $fields[$key]['layout_group'] =
            'experience_recommendations';
    }

    /*
     * These recommendation flags duplicate a paired 1-5 experience rating.
     * Keep historical keys readable, but do not ask the Scout the same
     * subjective question twice.
     */
    foreach (
        [
            'recommended_overnight_stop',
            'recommended_quiet_evening',
            'recommended_extended_stay',
            'recommended_sensory_retreat',
            'recommended_stargazing',
            'recommended_remote_work',
        ]
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

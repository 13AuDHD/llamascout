<?php

declare(strict_types=1);

/**
 * Site sensory, safety, and experience cleanup.
 *
 * The prior fallback placed sensory, safety, and recommendation fields into
 * one oversized Sensory & Environment subsection. This pass keeps the
 * individual campsite observations but separates them into coherent groups.
 */
function llama_place_report_apply_site_experience_metadata(
    array &$fields
): void {
    foreach (
        [
            'site_rating' =>
                'Overall campsite rating',

            'condition_rating' =>
                'Campsite condition rating',

            'location_rating' =>
                'Campsite location rating',
        ]
        as $key => $label
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['label'] =
            $label;

        $fields[$key]['layout_section'] =
            'site';

        $fields[$key]['layout_group'] =
            'experience_recommendations';
    }

    /*
     * These recommendations are already derived from their paired 1-5
     * experience ratings. Keep the historical keys available to readers and
     * imports, but do not show duplicate contributor questions.
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

    $help = [
        'site_rating' =>
            'Your overall rating of this individual campsite.',

        'condition_rating' =>
            'Condition and upkeep of this individual campsite when observed.',

        'location_rating' =>
            'How good this individual campsite is within the surrounding Place, including position, convenience, privacy, views, and nearby activity.',

        'not_recommended_for' =>
            'Anything that makes this campsite a poor match for certain vehicles, equipment, sensitivities, group sizes, or travel styles.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }
}

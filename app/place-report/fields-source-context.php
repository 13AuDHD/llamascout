<?php

declare(strict_types=1);

/**
 * Place-level facts that belong in the canonical Scout Report instead of
 * parallel RIDB-only presentation blocks.
 */
function llama_place_report_add_source_context_fields(
    callable $add
): void {
    $add(
        'place_accessible',
        'Officially marked accessible Place?',
        'accessibility',
        'tri',
        'details.place_accessible',
        [
            'allow_unknown' => true,
            'points_categories' => [
                'accessibility',
            ],
            'help' =>
                'This is only the official accessibility designation for the Place. A No answer does not mean the Place is unusable for every disabled traveler. Use the other accessibility questions to describe the actual route, surface, distance, and facilities.',
        ]
    );

    $add(
        'accessibility_notes',
        'Accessibility notes',
        'accessibility',
        'textarea',
        'details.accessibility_notes',
        [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'points_categories' => [
                'accessibility',
            ],
            'placeholder' =>
                'Describe practical access details that a Yes/No designation cannot capture.',
            'help' =>
                'Describe what actually matters in practice: surface firmness, slopes, steps, distance from parking, bathroom proximity, room to transfer, obstacles, or other access details. Do not treat an official accessible/not-accessible designation as the final answer for every person.',
        ]
    );

    $add(
        'facility_map_url',
        'Official facility map',
        'campground',
        'url',
        'details.facility_map_url',
        [
            'wide' => true,
            'counts_toward_completion' => false,
            'points_categories' => [],
            'placeholder' => 'https://...',
        ]
    );

    $add(
        'activities',
        'Activities',
        'campground',
        'multiselect',
        'details.activities',
        [
            'wide' => true,
            'summary' => '+ Add activity',
            'search_placeholder' => 'Search activities...',
            'options' => [
                'camping' => 'Camping',
                'hiking' => 'Hiking',
                'horseback-riding' => 'Horseback riding',
                'historic-cultural' => 'Historic & cultural sites',
                'wildlife-viewing' => 'Wildlife viewing',
                'birding' => 'Birding',
                'fishing' => 'Fishing',
                'boating' => 'Boating',
                'paddling' => 'Paddling / kayaking',
                'swimming' => 'Swimming',
                'biking' => 'Biking',
                'mountain-biking' => 'Mountain biking',
                'ohv' => 'OHV / motorized recreation',
                'climbing' => 'Climbing',
                'hunting' => 'Hunting',
                'picnicking' => 'Picnicking',
                'photography' => 'Photography',
                'stargazing' => 'Stargazing',
                'winter-sports' => 'Winter sports',
                'interpretive' => 'Interpretive / educational',
            ],
            'allow_unknown' => false,
            'points_categories' => [
                'experience_recommendations',
            ],
        ]
    );
}

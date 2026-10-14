<?php

declare(strict_types=1);

/**
 * Site -> Accessibility cleanup.
 *
 * An official accessible / not-accessible designation is one fact, not a
 * verdict about whether a particular traveler can use the site. Practical
 * access questions remain available regardless of that designation.
 */
function llama_place_report_apply_site_accessibility_metadata(
    array &$fields
): void {
    $siteAccessibilityFields = [
        'site_accessible',
        'wheelchair_friendly',
        'mobility_device_friendly',
        'flat_walking_surface',
        'step_free_access',
        'walking_distance_from_vehicle',
    ];

    foreach ($siteAccessibilityFields as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['layout_section'] =
            'site';

        $fields[$key]['layout_group'] =
            'site_accessibility';

        $fields[$key]['report_scopes'] =
            ['site'];

        $fields[$key]['report_scope_group'] =
            'site';

        $fields[$key]['scope_inheritance'] =
            'none';

        /*
         * Do not make practical access questions conditional on the official
         * designation. "Not marked accessible" must never hide them.
         */
        if ($key !== 'site_accessible') {
            $fields[$key]['applicable_if'] =
                [];
        }
    }

    if (isset($fields['site_accessible'])) {
        $fields['site_accessible']['display_section'] =
            'accessibility';
    }

    $labels = [
        'site_accessible' =>
            'Officially marked accessible campsite?',

        'wheelchair_friendly' =>
            'Wheelchair usable?',

        'mobility_device_friendly' =>
            'Outdoor mobility device usable?',

        'flat_walking_surface' =>
            'Firm / mostly level route?',

        'step_free_access' =>
            'Step-free route into campsite?',

        'walking_distance_from_vehicle' =>
            'Distance from vehicle to campsite',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'site_accessible' =>
            'Whether this individual campsite is officially marked or designated as accessible. A No answer only describes the designation. It does not mean the route, surface, bathroom access, or campsite layout will be unusable for every disabled traveler.',

        'wheelchair_friendly' =>
            'Whether a wheelchair can reasonably move from the parking position into and around the usable campsite area, regardless of whether the campsite is officially designated accessible.',

        'mobility_device_friendly' =>
            'Whether an outdoor mobility device such as an all-terrain mobility chair can reasonably use the route and campsite, regardless of the official designation.',

        'flat_walking_surface' =>
            'Whether the route from the vehicle into the usable campsite is firm and mostly level rather than loose, steep, deeply rutted, or highly uneven.',

        'step_free_access' =>
            'Whether the campsite can be reached from the vehicle without steps, curbs, or similar vertical barriers.',

        'walking_distance_from_vehicle' =>
            'Approximate distance from the nearest practical vehicle parking position to the usable campsite area.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }
}

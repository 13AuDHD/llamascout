<?php

declare(strict_types=1);

/**
 * Site -> Accessibility cleanup.
 *
 * An official accessible / not-accessible designation is one fact, not a
 * verdict about whether a particular traveler can use the Site. Practical
 * access questions remain available regardless of that designation.
 *
 * Ownership is defined only by app/place-report/flow-scopes.php.
 */
function llama_place_report_apply_site_accessibility_metadata(
    array &$fields
): void {
    if (isset($fields['site_accessible'])) {
        $fields['site_accessible']['display_section'] =
            'site_vehicle';
    }

    $labels = [
        'site_accessible' =>
            'Officially marked accessible Site?',

        'wheelchair_friendly' =>
            'Wheelchair usable?',

        'mobility_device_friendly' =>
            'Outdoor mobility device usable?',

        'flat_walking_surface' =>
            'Firm / mostly level route?',

        'step_free_access' =>
            'Step-free route into Site?',

        'walking_distance_from_vehicle' =>
            'Distance from vehicle to Site',

        'accessible_picnic_table' =>
            'Accessible picnic table at this Site?',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'site_accessible' =>
            'Whether this individual Site is officially marked or designated as accessible. A No answer only describes the designation. It does not mean the route, surface, bathroom access, or Site layout will be unusable for every disabled traveler.',

        'wheelchair_friendly' =>
            'Whether a wheelchair can reasonably move from the parking position into and around the usable Site area, regardless of whether the Site is officially designated accessible.',

        'mobility_device_friendly' =>
            'Whether an outdoor mobility device such as an all-terrain mobility chair can reasonably use the route and Site, regardless of the official designation.',

        'flat_walking_surface' =>
            'Whether the route from the vehicle into the usable Site is firm and mostly level rather than loose, steep, deeply rutted, or highly uneven.',

        'step_free_access' =>
            'Whether the Site can be reached from the vehicle without steps, curbs, or similar vertical barriers.',

        'walking_distance_from_vehicle' =>
            'Approximate distance from the nearest practical vehicle parking position to the usable Site area.',

        'accessible_picnic_table' =>
            'Whether the picnic table assigned to this Site is designed or arranged for accessible use.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }
}

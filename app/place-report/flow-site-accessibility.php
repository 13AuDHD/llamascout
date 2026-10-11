<?php

declare(strict_types=1);

/**
 * Site -> Accessibility cleanup.
 *
 * These questions describe the path from the vehicle into the individual
 * campsite and the usability of that campsite itself. Shared accessible
 * toilets, showers, tables, and other campground facilities remain under
 * Place -> Facilities & Amenities.
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
    }

    $labels = [
        'site_accessible' =>
            'Marked accessible campsite?',

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
            'Whether this individual campsite is officially marked or designated as accessible.',

        'wheelchair_friendly' =>
            'Whether a wheelchair can reasonably move from the parking position into and around the usable campsite area.',

        'mobility_device_friendly' =>
            'Whether an outdoor mobility device such as an all-terrain mobility chair can reasonably use the route and campsite.',

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

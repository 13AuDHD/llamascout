<?php

declare(strict_types=1);

/**
 * Final report notes and summary placement.
 *
 * Approach Access Summary belongs with Getting There and describes reaching
 * the Place. Site access facts describe only the selected Site.
 */
function llama_place_report_apply_site_notes_metadata(
    array &$fields
): void {
    if (isset($fields['access_summary'])) {
        $fields['access_summary']['layout_section'] =
            'getting_there';

        $fields['access_summary']['layout_group'] =
            'vehicle_access';

        $fields['access_summary']['label'] =
            'Approach access summary';

        $fields['access_summary']['placeholder'] =
            'Summarize the route to the Place, road condition, vehicle requirements, difficult turns, gates, restrictions, and other important approach information.';

        $fields['access_summary']['help'] =
            'Summarize reaching the Place from the normal road network. Do not use this for the final maneuver into an individual Site; Site entry and Site accessibility have their own fields.';
    }

    if (isset($fields['sensory_summary'])) {
        $fields['sensory_summary']['layout_section'] =
            'site';

        $fields['sensory_summary']['layout_group'] =
            'sensory_environment';
    }

    foreach (
        [
            'scout_note_1',
            'scout_note_2',
            'scout_note_3',
            'contributor_notes',
        ]
        as $key
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['layout_section'] =
            'site';

        $fields[$key]['layout_group'] =
            'site_photos';
    }

    foreach (
        [
            'scout_note_1',
            'scout_note_2',
            'scout_note_3',
        ]
        as $key
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['help'] =
            'Use this for a short observation that does not fit a more specific canonical field. Prefer the specific field whenever one exists.';
    }

    if (isset($fields['contributor_notes'])) {
        $fields['contributor_notes']['label'] =
            'Notes for the moderator';

        $fields['contributor_notes']['help'] =
            'Private moderation context for anything uncertain, temporary, contradictory, unusual, or needing verification. This is not shown publicly.';
    }
}

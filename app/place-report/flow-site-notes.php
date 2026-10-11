<?php

declare(strict_types=1);

/**
 * Final report notes and summary placement.
 *
 * The Access Summary belongs with Getting There, not in the Site photo group.
 * Sensory Summary already lives with Site -> Sensory & Environment.
 * Scout notes and private reviewer notes belong beside the report photos.
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
            'Access summary';

        $fields['access_summary']['help'] =
            'Summarize the complete approach and access experience, including road condition, vehicle requirements, difficult turns, gates or restrictions, parking approach, and anything important someone should know before trying to reach the Place.';
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
            'Use this for a short useful observation that does not fit cleanly into another question. Keep it specific and useful to someone evaluating this Place or campsite.';
    }

    if (isset($fields['contributor_notes'])) {
        $fields['contributor_notes']['label'] =
            'Notes for the moderator';

        $fields['contributor_notes']['help'] =
            'Private moderation context for anything uncertain, temporary, contradictory, unusual, or needing verification. This is not shown publicly.';
    }
}

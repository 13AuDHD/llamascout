<?php

declare(strict_types=1);

/**
 * Recurring operating-season dates. These are month/day facts without a year.
 * The two canonical keys are season_begins and season_ends.
 */
function llama_place_report_add_season_fields(array &$fields, callable $add): void
{
    $extra = [
        'allow_unknown' => true,
        'points_categories' => [],
        'counts_toward_completion' => false,
        'placeholder' => 'MM-DD',
        'help' => 'Enter the recurring month and day (MM-DD), without a year. Example: 05-15 for May 15. Leave unanswered if the date is not known.',
    ];

    $add('season_begins', 'Season Begins', 'rules', 'text', 'rules.season_begins', $extra);
    $add('season_ends', 'Season Ends', 'rules', 'text', 'rules.season_ends', $extra);

    $begins = $fields['season_begins'];
    $ends = $fields['season_ends'];
    unset($fields['season_begins'], $fields['season_ends']);
    $ordered = [];
    $inserted = false;
    foreach ($fields as $key => $field) {
        if (!$inserted && $key === 'best_months') {
            $ordered['season_begins'] = $begins;
            $ordered['season_ends'] = $ends;
            $inserted = true;
        }
        $ordered[$key] = $field;
    }
    if (!$inserted) {
        $ordered['season_begins'] = $begins;
        $ordered['season_ends'] = $ends;
    }
    $fields = $ordered;
}

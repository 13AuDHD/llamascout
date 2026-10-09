<?php

declare(strict_types=1);

/**
 * Standardized operating-season questions.
 *
 * These represent published recurring months, not a real-time open/closed
 * determination. Unknown or imprecise source data must not be guessed into
 * a definite operating schedule.
 */
function llama_place_report_add_season_fields(array &$fields, callable $add): void
{
    $months = [
        'january' => 'January',
        'february' => 'February',
        'march' => 'March',
        'april' => 'April',
        'may' => 'May',
        'june' => 'June',
        'july' => 'July',
        'august' => 'August',
        'september' => 'September',
        'october' => 'October',
        'november' => 'November',
        'december' => 'December',
    ];

    $extra = [
        'allow_unknown' => true,
        // These are source-reported facts, not Scout observation points.
        'points_categories' => [],
        'counts_toward_completion' => false,
        'options' => $months,
    ];

    $add('season_begins', 'Season Begins', 'rules', 'select', 'rules.season_begins', $extra + [
        'help' => 'First month of the published operating season. Leave Unknown when dates vary or are not confirmed.',
    ]);
    $add('season_ends', 'Season Ends', 'rules', 'select', 'rules.season_ends', $extra + [
        'help' => 'Last month of the published operating season. Leave Unknown when dates vary or are not confirmed.',
    ]);

    // Maintain the established Rules card order without editing the large
    // experience field registry. Keep both new cards adjacent before Best months.
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

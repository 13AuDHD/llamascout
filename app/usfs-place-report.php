<?php

declare(strict_types=1);

/**
 * Read-only overlay for confirmed USFS links.
 * The existing published Scout answers always win. No records are updated,
 * no source labels are inserted in the Scout cards, and unclear seasons or
 * prices are not inferred.
 */
function llama_usfs_report_enrich(PDO $db, int $placeId, array $report): array
{
    if ($placeId < 1) {
        return $report;
    }

    try {
        $statement = $db->prepare(
            'SELECT camping_fee_candidate, camping_rate_candidate,
                    fee_description, operational_hours, open_season
             FROM place_usfs_enrichment
             WHERE place_id = ? LIMIT 1'
        );
        $statement->execute([$placeId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        // The report must remain available before enrichment migrations.
        if (in_array((int) ($exception->errorInfo[1] ?? 0), [1146], true)) {
            return $report;
        }
        throw $exception;
    }

    if (!$record) {
        return $report;
    }

    $rules = is_array($report['rules'] ?? null) ? $report['rules'] : [];
    $unknown = is_array($report['_unknown_fields'] ?? null)
        ? $report['_unknown_fields']
        : [];

    // Do not override a deliberately recorded Scout answer or Unknown.
    $canFill = static function (string $field) use ($rules, $unknown): bool {
        return !array_key_exists($field, $rules)
            || ($rules[$field] === null || $rules[$field] === '');
    };

    $season = trim((string) ($record['operational_hours'] ?? ''));
    if ($season === '') {
        $season = trim((string) ($record['open_season'] ?? ''));
    }

    $monthPattern = '(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)';
    $matches = [];
    if (preg_match('/^\s*' . $monthPattern . '\s*(?:-|â|â|to|through|thru)\s*' . $monthPattern . '\s*$/iu', $season, $matches)) {
        $months = [
            'jan' => 'january', 'feb' => 'february', 'mar' => 'march',
            'apr' => 'april', 'may' => 'may', 'jun' => 'june',
            'jul' => 'july', 'aug' => 'august', 'sep' => 'september',
            'oct' => 'october', 'nov' => 'november', 'dec' => 'december',
        ];
        $start = $months[substr(strtolower($matches[1]), 0, 3)] ?? null;
        $end = $months[substr(strtolower($matches[2]), 0, 3)] ?? null;
        if ($start !== null && $end !== null) {
            if ($canFill('season_begins')) {
                $rules['season_begins'] = $start;
            }
            if ($canFill('season_ends')) {
                $rules['season_ends'] = $end;
            }
        }
    }

    // Fee is the campground's documented standard overnight rate, not an
    // asserted price for every individual campsite or a reservation charge.
    $candidate = (string) ($record['camping_fee_candidate'] ?? '');
    $amount = $record['camping_rate_candidate'] ?? null;
    if (
        $candidate === 'paid_candidate'
        && is_numeric($amount)
        && (float) $amount > 0
        && $canFill('fee')
    ) {
        $rules['fee'] = (float) $amount;
    }

    $report['rules'] = $rules;
    return $report;
}

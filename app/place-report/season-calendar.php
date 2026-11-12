<?php

declare(strict_types=1);

/**
 * Single calendar contract for Place seasons used by Campsite Pricing and
 * public Site availability. Existing MM-DD records remain yearly recurring.
 */

function llama_season_year_columns_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $query = $db->query("SHOW COLUMNS FROM place_seasons LIKE 'is_recurring'");
        $ready = $query && $query->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        $ready = false;
    }
    return $ready;
}

function llama_season_absolute_date(mixed $value): ?string
{
    $date = trim((string) ($value ?? ''));
    if ($date === '') return null;
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $m)
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        throw new InvalidArgumentException('Enter a valid season date as YYYY-MM-DD.');
    }
    return $date;
}

function llama_season_calendar_repeats(array $season): bool
{
    return !array_key_exists('is_recurring', $season)
        || (int) $season['is_recurring'] === 1;
}

/**
 * A manually selected undated season never opens a campground by default.
 * With no matching dated season, Sites are seasonally unavailable.
 */
function llama_season_applies_on(array $season, string $date): bool
{
    $date = llama_season_absolute_date($date);
    if ($date === null || (int) ($season['is_active'] ?? 1) !== 1) {
        return false;
    }
    if (!llama_season_calendar_repeats($season)) {
        $begin = llama_season_absolute_date($season['starts_on'] ?? null);
        $end = llama_season_absolute_date($season['ends_on'] ?? null);
        return $begin !== null && $end !== null
            && $date >= $begin && $date <= $end;
    }
    if (empty($season['start_mmdd']) || empty($season['end_mmdd'])) return false;
    return llama_season_contains($season, substr($date, 5));
}

/**
 * Correct overlap detection between dated periods and annual repeating
 * periods, including ranges that cross December 31 and leap years.
 */
function llama_seasons_calendar_overlap(array $a, array $b): bool
{
    $annualA = llama_season_calendar_repeats($a);
    $annualB = llama_season_calendar_repeats($b);
    if ($annualA && $annualB) {
        return llama_seasons_overlap_recurring($a, $b);
    }

    if (!$annualA && !$annualB) {
        $aStart = llama_season_absolute_date($a['starts_on'] ?? null);
        $aEnd = llama_season_absolute_date($a['ends_on'] ?? null);
        $bStart = llama_season_absolute_date($b['starts_on'] ?? null);
        $bEnd = llama_season_absolute_date($b['ends_on'] ?? null);
        return $aStart !== null && $aEnd !== null
            && $bStart !== null && $bEnd !== null
            && $aStart <= $bEnd && $bStart <= $aEnd;
    }
    $annual = $annualA ? $a : $b;
    $dated = $annualA ? $b : $a;
    $start = llama_season_absolute_date($dated['starts_on'] ?? null);
    $end = llama_season_absolute_date($dated['ends_on'] ?? null);
    if ($start === null || $end === null
        || empty($annual['start_mmdd']) || empty($annual['end_mmdd'])) {
        return false;
    }

    // A dated period is bounded at validation, so checking its days is
    // straightforward and avoids errors at leap days or year boundaries.
    $cursor = new DateTimeImmutable($start);
    $last = new DateTimeImmutable($end);
    while ($cursor <= $last) {
        if (llama_season_contains($annual, $cursor->format('m-d'))) {
            return true;
        }
        $cursor = $cursor->modify('+1 day');
    }
    return false;
}

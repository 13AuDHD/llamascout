<?php

declare(strict_types=1);

/**
 * Source-neutral, read-only official-fact resolution.
 *
 * Never use this preview alone to write published Places. Each caller must
 * first establish record identity, canonical field ownership, measurement
 * semantics, effective dates, and source validity. Source provenance is
 * retained even when the canonical preview is withheld.
 */
function llama_official_fact_resolution(array $facts, string $type): array
{
    if (!in_array($type, ['integer', 'money', 'string'], true)) {
        throw new InvalidArgumentException('Unsupported official fact type.');
    }

    $valid = [];
    $distinct = [];
    $providers = [];
    foreach ($facts as $fact) {
        if (!is_array($fact) || trim((string) ($fact['provider'] ?? '')) === '') {
            throw new InvalidArgumentException('Official facts require a source provider.');
        }
        $value = $fact['value'] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            continue;
        }
        $canonical = null;
        if ($type === 'integer') {
            if (is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/D', trim($value)))) {
                $canonical = (int) $value;
            }
            if ($canonical !== null && $canonical < 0) {
                $canonical = null;
            }
        } elseif ($type === 'money') {
            $text = is_string($value) ? trim($value) : (string) $value;
            if (preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $text, $parts)) {
                $whole = ltrim($parts[1], '0');
                $canonical = ($whole === '' ? '0' : $whole) . '.'
                    . str_pad((string) ($parts[2] ?? ''), 2, '0');
            }
        } else {
            if (is_string($value) || is_int($value)) {
                $canonical = trim((string) $value);
                if ($canonical === '') {
                    $canonical = null;
                }
            }
        }
        if ($canonical === null) {
            continue;
        }
        $valid[] = $fact + ['value' => $canonical];
        $providers[trim((string) $fact['provider'])] = true;
        $normalized = $type === 'string'
            ? (function_exists('mb_strtolower')
                ? mb_strtolower((string) $canonical, 'UTF-8')
                : strtolower((string) $canonical))
            : (string) $canonical;
        $distinct[$normalized] = $canonical;
    }
    $count = count($valid);
    $state = $count === 0 ? 'missing'
        : (count($distinct) > 1
            ? (count($providers) > 1 ? 'conflict' : 'ambiguous_source')
            : (count($providers) < 2 ? 'single_source' : 'agreement'));
    return [
        'state' => $state,
        'canonical_preview' => in_array($state, ['missing', 'conflict', 'ambiguous_source'], true)
            ? null : reset($distinct),
        'facts' => $valid,
        'needs_review' => in_array($state, ['conflict', 'ambiguous_source'], true),
    ];
}

/** Only unambiguous site-total phrases; narrative results remain review evidence. */
function llama_official_ridb_site_count_evidence(string $description): ?int
{
    $text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $patterns = [
        '/\bthere are a total of\s+([0-9]{1,5})\s+campsites\b/i',
        '/\bthe campground offers\s+([0-9]{1,5})\s+campsites\b/i',
        '/\bwith\s+([0-9]{1,5})\s+campsites\b/i',
        '/\bhas a total of\s+([0-9]{1,5})\s+sites\b/i',
    ];
    $found = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $count) {
                $found[(string) (int) $count] = (int) $count;
            }
        }
    }
    return count($found) === 1 ? reset($found) : null;
}

/**
 * NPS fee arrays often mix normal, discounted, group and equipment rates.
 * Only recognize an ordinary named base rate; never use the first price.
 */
function llama_official_nps_regular_nightly_fee(array $record): ?string
{
    $found = [];
    foreach ((array) ($record['fees'] ?? []) as $fee) {
        if (!is_array($fee)) {
            continue;
        }
        $title = trim((string) ($fee['title'] ?? ''));
        $raw = trim((string) ($fee['cost'] ?? ''));
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $raw)) {
            continue;
        }
        if (preg_match('/\b(senior|access pass|discount|group|horse|hiker|biker|electric|non-electric|tent only|rv only|permit|booking)\b/i', $title)) {
            continue;
        }
        if (!preg_match('/\b(campsite|camping fee|individual site|regular site|all sites|family site)\b/i', $title)) {
            continue;
        }
        $number = number_format((float) $raw, 2, '.', '');
        $found[$number] = true;
    }
    return count($found) === 1 ? (string) array_key_first($found) : null;
}

/**
 * Narrow extraction of explicit campground-wide *nightly* prices only.
 * An entrance fee, reservation surcharge, or per-person fee is not a base
 * campsite rate and must never be compared to one.
 */
function llama_official_ridb_regular_nightly_fee(string $feeDescription): ?string
{
    $text = html_entity_decode(strip_tags($feeDescription), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $patterns = [
        '/\bcampsites at the\s+.{1,130}?\s+are\s+\$([0-9]+(?:\.[0-9]{1,2})?)\s+per night\b/i',
        '/\bfee\s*[-–]\s*all sites\s+\$([0-9]+(?:\.[0-9]{1,2})?)\b/i',
    ];
    $found = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $number = number_format((float) $raw, 2, '.', '');
                $found[$number] = true;
            }
        }
    }
    return count($found) === 1 ? (string) array_key_first($found) : null;
}

function llama_official_geographic_distance_miles(
    mixed $lat1, mixed $lon1, mixed $lat2, mixed $lon2
): ?float {
    foreach ([$lat1, $lon1, $lat2, $lon2] as $value) {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
    }
    [$a, $b, $c, $d] = array_map('floatval', [$lat1, $lon1, $lat2, $lon2]);
    if (abs($a) > 90 || abs($c) > 90 || abs($b) > 180 || abs($d) > 180) {
        return null;
    }
    $aRad = deg2rad($a);
    $cRad = deg2rad($c);
    $latDelta = deg2rad($c - $a);
    $lonDelta = deg2rad($d - $b);
    $h = sin($latDelta / 2) ** 2
        + cos($aRad) * cos($cRad) * sin($lonDelta / 2) ** 2;
    return 3958.7613 * 2 * asin(min(1.0, sqrt(max(0.0, $h))));
}

/**
 * An assessment is a source-data preview; it never mutates a Place.
 * Free-text RIDB evidence is retained as evidence, not treated as a
 * structured counterpart unless the phrase is unambiguous.
 */
function llama_official_nps_ridb_assessment(array $nps, array $ridb): array
{
    $npsId = trim((string) ($nps['id'] ?? ''));
    $ridbId = trim((string) ($ridb['FacilityID'] ?? ''));
    if ($npsId === '' || $ridbId === '') {
        throw new InvalidArgumentException('Both original source IDs are required.');
    }
    $source = static function (string $provider, string $id, string $path, mixed $value): array {
        return ['provider' => $provider, 'source_id' => $id,
                'path' => $path, 'value' => $value];
    };
    $npsTotal = $nps['campsites']['totalSites'] ?? null;
    $ridbCount = llama_official_ridb_site_count_evidence((string) ($ridb['FacilityDescription'] ?? ''));
    $siteCount = llama_official_fact_resolution([
        $source('nps', $npsId, 'campsites.totalSites', $npsTotal),
        $source('ridb', $ridbId, 'FacilityDescription [narrative]', $ridbCount),
    ], 'integer');
    $fee = llama_official_fact_resolution([
        $source('nps', $npsId, 'fees[] [regular nightly]', llama_official_nps_regular_nightly_fee($nps)),
        $source('ridb', $ridbId, 'FacilityUseFeeDescription [narrative]',
            llama_official_ridb_regular_nightly_fee((string) ($ridb['FacilityUseFeeDescription'] ?? ''))),
    ], 'money');

    $reservable = llama_official_fact_resolution([
        $source('nps', $npsId, 'numberOfSitesReservable', $nps['numberOfSitesReservable'] ?? null),
    ], 'integer');
    // An internally contradictory aggregate is unsafe even if only one
    // source provides it. The raw data remains in provenance.
    $totalValue = $siteCount['canonical_preview'];
    if ($reservable['canonical_preview'] !== null
        && $npsTotal !== null && is_numeric($npsTotal)
        && (int) $reservable['canonical_preview'] > (int) $npsTotal) {
        $reservable['state'] = 'invalid_source';
        $reservable['canonical_preview'] = null;
        $reservable['needs_review'] = true;
    }

    $pointDistance = llama_official_geographic_distance_miles(
        $nps['latitude'] ?? null, $nps['longitude'] ?? null,
        $ridb['FacilityLatitude'] ?? null, $ridb['FacilityLongitude'] ?? null
    );
    $coords = [
        'state' => $pointDistance === null ? 'missing'
            : ($pointDistance > 1.0 ? 'needs_review' : 'compatible_candidates'),
        'canonical_preview' => null,
        'needs_review' => $pointDistance !== null && $pointDistance > 1.0,
        'distance_miles' => $pointDistance,
        'facts' => [
            $source('nps', $npsId, 'latitude,longitude',
                (string) ($nps['latitude'] ?? '') . ',' . (string) ($nps['longitude'] ?? '')),
            $source('ridb', $ridbId, 'FacilityLatitude,FacilityLongitude',
                (string) ($ridb['FacilityLatitude'] ?? '') . ',' . (string) ($ridb['FacilityLongitude'] ?? '')),
        ],
    ];

    // NPS's RV max value is Place-wide policy; NEVER a Site measurement.
    $rvAllowed = (string) ($nps['accessibility']['rvAllowed'] ?? '');
    $rvMax = $nps['accessibility']['rvMaxLength'] ?? null;
    $rv = llama_official_fact_resolution([
        $source('nps', $npsId, 'accessibility.rvMaxLength',
            $rvAllowed === '0' || $rvMax === '0' || $rvMax === 0 ? null : $rvMax),
    ], 'integer');
    $rv['scope'] = 'place_policy';
    $rv['not_applicable'] = $rvAllowed === '0';

    return [
        'site_total' => $siteCount,
        'regular_nightly_fee_usd' => $fee,
        'reservable_sites' => $reservable,
        'location' => $coords,
        'place_rv_max_feet' => $rv,
    ];
}

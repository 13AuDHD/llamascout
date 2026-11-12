<?php

declare(strict_types=1);

/** One published rate card, shared by reports, Site readers and discovery. */
function llama_pricing_empty(): array
{
    return ['version' => 2, 'currency' => 'USD', 'weekend_days' => [5, 6], 'holidays' => [], 'rows' => []];
}

function llama_pricing_amount(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999.99) {
        throw new InvalidArgumentException('Prices must be a finite amount between 0 and 999999.99.');
    }
    return round((float) $value, 2);
}

function llama_pricing_date(string $value, bool $recurring = false): string
{
    $full = $recurring ? '2000-' . $value : $value;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $full);
    if (!$date || $date->format('Y-m-d') !== $full) throw new InvalidArgumentException('Enter valid pricing dates.');
    return $value;
}

function llama_pricing_period(array $period): array
{
    $recurring = !empty($period['recurring']);
    $start = llama_pricing_date((string) ($period['start'] ?? ''), $recurring);
    $end = llama_pricing_date((string) ($period['end'] ?? ''), $recurring);
    if (!$recurring && $end < $start) throw new InvalidArgumentException('Pricing end date must follow its start date.');
    return ['name' => mb_substr(trim((string) ($period['name'] ?? '')), 0, 120), 'start' => $start, 'end' => $end, 'recurring' => $recurring];
}

function llama_pricing_in_period(array $period, string $date): bool
{
    $day = !empty($period['recurring']) ? substr($date, 5) : $date;
    $a = (string) $period['start']; $b = (string) $period['end'];
    return $a <= $b ? ($day >= $a && $day <= $b) : ($day >= $a || $day <= $b);
}

/** Strict on every write path; never coerce bad numbers into Free. */
function llama_pricing_validate(mixed $raw): array
{
    if (is_string($raw)) {
        if (strlen($raw) > 60000) throw new InvalidArgumentException('The rate card is too large.');
        try { $raw = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new InvalidArgumentException('Invalid rate card data.'); }
    }
    if (!is_array($raw)) throw new InvalidArgumentException('Invalid rate card data.');
    if (array_is_list($raw)) $raw = llama_pricing_legacy_cards($raw);
    if (($raw['version'] ?? null) !== 2 || ($raw['currency'] ?? 'USD') !== 'USD') {
        throw new InvalidArgumentException('Unsupported rate card version or currency.');
    }
    $doc = llama_pricing_empty();
    $days = $raw['weekend_days'] ?? [5, 6];
    if (!is_array($days) || !array_is_list($days) || count($days) > 7) throw new InvalidArgumentException('Invalid weekend nights.');
    foreach ($days as $day) {
        if (!is_int($day) || $day < 0 || $day > 6) throw new InvalidArgumentException('Invalid weekend night.');
    }
    if (isset($raw['legacy_cards'])) {
        if (!is_array($raw['legacy_cards']) || !array_is_list($raw['legacy_cards'])) throw new InvalidArgumentException('Invalid legacy price archive.');
        $doc['legacy_cards'] = $raw['legacy_cards'];
    }
    $doc['weekend_days'] = array_values(array_unique($days));
    foreach (['rows' => 500, 'holidays' => 60] as $key => $limit) {
        if (!is_array($raw[$key] ?? null) || !array_is_list($raw[$key]) || count($raw[$key]) > $limit) {
            throw new InvalidArgumentException('Invalid or excessive pricing ' . $key . '.');
        }
    }
    foreach ($raw['holidays'] as $holidayIndex => $holiday) {
        if (!is_array($holiday)) throw new InvalidArgumentException('Invalid holiday dates.');
        $period = llama_pricing_period($holiday);
        $period['id'] = mb_substr((string) ($holiday['id'] ?? 'holiday-' . $holidayIndex), 0, 80);
        if (in_array($period['id'], array_column($doc['holidays'], 'id'), true)) throw new InvalidArgumentException('Duplicate holiday identifier.');
        $doc['holidays'][] = $period;
    }
    foreach ($raw['rows'] as $index => $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid rate row.');
        $match = $row['match'] ?? [];
        if (!is_array($match)) throw new InvalidArgumentException('Invalid Site matching conditions.');
        $cleanMatch = [];
        foreach (['category' => ['', 'single', 'double', 'group'], 'layout' => ['', 'pull_through', 'back_in', 'pull_in', 'back_pull_in', 'parallel', 'other'], 'hookups' => ['', 'none', 'electric', 'full']] as $key => $allowed) {
            $v = (string) ($match[$key] ?? '');
            if (!in_array($v, $allowed, true)) throw new InvalidArgumentException('Invalid Site ' . $key . '.');
            $cleanMatch[$key] = $v;
        }
        foreach (['raw_type', 'legacy_category', 'legacy_site', 'loop'] as $key) $cleanMatch[$key] = mb_substr(trim((string) ($match[$key] ?? '')), 0, 120);
        foreach (['site_ids', 'feature_ids', 'area_ids'] as $key) {
            $ids = $match[$key] ?? [];
            if (!is_array($ids) || !array_is_list($ids) || count($ids) > 1000) throw new InvalidArgumentException('Invalid pricing Site selection.');
            foreach ($ids as $id) if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid pricing Site ID.');
            $cleanMatch[$key] = array_values(array_unique($ids));
        }
        $cleanMatch['site_selection'] = !empty($match['site_selection']);
        if ($cleanMatch['site_selection'] && !$cleanMatch['site_ids']) throw new InvalidArgumentException('Choose at least one Site, or select All matching Sites.');
        $unit = (string) ($row['unit'] ?? 'night');
        if (!in_array($unit, ['night','week','month','stay','season','person_night','vehicle_night'], true)) throw new InvalidArgumentException('Invalid rate unit.');
        $out = ['id' => mb_substr((string) ($row['id'] ?? 'rate-' . $index), 0, 80), 'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 120), 'match' => $cleanMatch, 'unit' => $unit];
        foreach (['price', 'weekend_price', 'holiday_price'] as $key) $out[$key] = llama_pricing_amount($row[$key] ?? null);
        $out['holiday_prices'] = [];
        $holidayPrices = $row['holiday_prices'] ?? [];
        if (!is_array($holidayPrices)) throw new InvalidArgumentException('Invalid holiday prices.');
        foreach ($holidayPrices as $holidayId => $amount) {
            if (!in_array((string) $holidayId, array_column($doc['holidays'], 'id'), true)) throw new InvalidArgumentException('Holiday price refers to missing dates.');
            $out['holiday_prices'][(string) $holidayId] = llama_pricing_amount($amount);
        }
        $out['seasons'] = [];
        $seasons = $row['seasons'] ?? [];
        if (!is_array($seasons) || !array_is_list($seasons) || count($seasons) > 30) throw new InvalidArgumentException('Invalid seasonal prices.');
        foreach ($seasons as $season) {
            if (!is_array($season)) throw new InvalidArgumentException('Invalid seasonal price.');
            $seasonId = filter_var($season['season_id'] ?? null, FILTER_VALIDATE_INT);
            if (array_key_exists('season_id', $season) && (!$seasonId || $seasonId < 1)) {
                throw new InvalidArgumentException('Invalid operating season.');
            }
            $period = $seasonId ? ['season_id' => $seasonId] : llama_pricing_period($season);
            if ($seasonId && in_array($seasonId, array_column($out['seasons'], 'season_id'), true)) {
                throw new InvalidArgumentException('Duplicate price for an operating season.');
            }
            foreach (['price', 'weekend_price', 'holiday_price'] as $key) $period[$key] = llama_pricing_amount($season[$key] ?? null);
            $out['seasons'][] = $period;
        }
        // Legacy conditions remain visible and unresolved instead of becoming flat prices.
        $out['needs_review'] = !empty($row['needs_review']);
        $out['notes'] = mb_substr(trim((string) ($row['notes'] ?? '')), 0, 1000);
        $url = trim((string) ($row['source_url'] ?? ''));
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true))) throw new InvalidArgumentException('Invalid price source URL.');
        $out['source_url'] = $url;
        $out['verified_on'] = ($row['verified_on'] ?? '') === '' ? '' : llama_pricing_date((string) $row['verified_on']);
        if (in_array($out['id'], array_column($doc['rows'], 'id'), true)) throw new InvalidArgumentException('Duplicate rate row identifier.');
        $doc['rows'][] = $out;
    }
    if (strlen(json_encode($doc, JSON_THROW_ON_ERROR)) > 60000) throw new InvalidArgumentException('The rate card is too large.');
    return $doc;
}

function llama_pricing_legacy_cards(array $cards): array
{
    $doc = llama_pricing_empty();
    $doc['legacy_cards'] = $cards;
    foreach ($cards as $i => $card) {
        if (!is_array($card)) throw new InvalidArgumentException('Invalid legacy rate card.');
        $scope = (string) ($card['scope'] ?? 'entire_place');
        $category = trim((string) ($card['category'] ?? ''));
        $match = ['layout' => (string) ($card['parking_style'] ?? '')];
        if (in_array(strtolower($category), ['single','double','group'], true)) $match['category'] = strtolower($category);
        elseif ($scope === 'site_category') $match['legacy_category'] = $category;
        if ($scope === 'individual_site') $match['legacy_site'] = (string) ($card['site'] ?? '');
        $doc['rows'][] = ['id' => 'legacy-' . $i, 'name' => (string) ($card['name'] ?? ''), 'match' => $match, 'price' => $card['price'] ?? null, 'unit' => $card['unit'] ?? 'night', 'seasons' => [], 'needs_review' => !in_array((string) ($card['season'] ?? 'year_round'), ['', 'year_round'], true) || $scope === 'mixed_other' || ($scope === 'site_category' && $category === '') || $scope === 'individual_site', 'notes' => 'Imported legacy card. ' . (string) ($card['season'] ?? ''), 'source_url' => $card['source_url'] ?? '', 'verified_on' => $card['verified_on'] ?? ''];
    }
    return $doc;
}

/** Invalid persisted content must never fall back to a misleading old price. */
function llama_pricing_decode(mixed $raw): array
{
    if ($raw === null || $raw === '') return llama_pricing_empty();
    try { return llama_pricing_validate($raw); }
    catch (Throwable) { return array_merge(llama_pricing_empty(), ['invalid' => true]); }
}

/**
 * Authoritative published pricing document.
 *
 * The Campsite Pricing Admin editor is the only write interface. Place-wide
 * flat fees, imported map-feature rates and historical rate cards are not
 * synthesized into Site prices; an unpriced Site remains unpriced.
 */
function llama_pricing_document(PDO $db, array $place): array
{
    $rules = $place['rules'] ?? null;
    if (!is_array($rules)) {
        $query = $db->prepare(
            'SELECT rate_cards_json FROM place_rules WHERE place_id = ?'
        );
        $query->execute([(int) $place['id']]);
        $rules = $query->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return llama_pricing_decode($rules['rate_cards_json'] ?? null);
}

function llama_pricing_token(mixed $value): string
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $value)) ?? '');
}

function llama_pricing_site_context(array $site): array
{
    $raw = strtoupper((string) ($site['raw_site_type'] ?? ''));
    $category = (string) ($site['pricing_category'] ?? '');
    $explicitCategory = array_key_exists('pricing_category', $site);
    $loop = '';
    foreach ((array) ($site['features'] ?? []) as $f) {
        $key = llama_pricing_token($f['feature_key'] ?? $f['name'] ?? '');
        if ($key === 'parking type' && empty($site['parking_style'])) {
            $layout = llama_pricing_token($f['feature_value'] ?? '');
            $site['parking_style'] = match ($layout) { 'front in', 'pull in' => 'pull_in', 'back in' => 'back_in', 'pull through' => 'pull_through', default => '' };
        }
        if ($key === 'ridb loop') $loop = (string) ($f['feature_value'] ?? '');
        if (!$explicitCategory && in_array($key, ['campsite size','site size','campsite capacity type','capacity size rating'], true)) {
            $v = llama_pricing_token($f['feature_value'] ?? '');
            if (in_array($v, ['single','double','group'], true)) $category = $v;
        }
    }
    if (!$explicitCategory && $category === '' && preg_match('/\b(DOUBLE|GROUP|SINGLE)\b/', $raw, $m)) $category = strtolower($m[1]);
    if (!$explicitCategory && $category === '' && ($site['site_type'] ?? '') === 'group_site') $category = 'group';
    // STANDARD does not establish Single; double driveway and occupancy do not establish Double.
    return array_merge($site, ['pricing_category' => $category, 'pricing_loop' => $site['pricing_loop'] ?? $loop]);
}

/** null = definitely not matching, integer = specificity, -1 = missing attributes. */
function llama_pricing_match(array $match, array $site): ?int
{
    $score = 0; $missing = false;
    $selectors = array_merge($match['feature_ids'] ?? [], $match['area_ids'] ?? []);
    if ($selectors) {
        $map = (int) ($site['map_feature_id'] ?? 0); $area = (int) ($site['parent_feature_id'] ?? 0);
        if (!in_array($map, $match['feature_ids'] ?? [], true) && !in_array($area, $match['area_ids'] ?? [], true)) return null;
        $score += in_array($map, $match['feature_ids'] ?? [], true) ? 100 : 10;
    }
    if (!empty($match['site_ids'])) {
        if (!in_array((int) ($site['id'] ?? 0), $match['site_ids'], true)) return null;
        // An individually selected Site rate must outrank a shared
        // Place, Area or multi-Site rate. That is the intended precedence
        // of per-Site seasonal overrides in the canonical v2 document.
        $score += count($match['site_ids']) === 1 ? 1100 : 1000;
    }
    foreach (['category' => 'pricing_category', 'layout' => 'parking_style', 'raw_type' => 'raw_site_type', 'loop' => 'pricing_loop', 'legacy_site' => 'site_code', 'legacy_category' => 'raw_site_type'] as $key => $source) {
        $wanted = (string) ($match[$key] ?? '');
        if ($wanted === '') continue;
        $actual = llama_pricing_token($site[$source] ?? '');
        if ($actual === '' || $actual === 'unknown') { $missing = true; continue; }
        if ($key === 'layout' && $wanted === 'back_pull_in') {
            if (!in_array($actual, ['back in','pull in','front in'], true)) return null;
        } elseif ($actual !== llama_pricing_token($wanted)) return null;
        $score += $key === 'legacy_site' ? 100 : 1;
    }
    $hookups = (string) ($match['hookups'] ?? '');
    if ($hookups !== '') {
        $bool = static fn($v): ?bool => $v === null || $v === '' ? null : (in_array(strtolower((string) $v), ['1','y','yes','true'], true) ? true : (in_array(strtolower((string) $v), ['0','n','no','false'], true) ? false : null));
        $power = $bool($site['electric_hookup'] ?? null); $water = $bool($site['water_hookup'] ?? null); $sewer = $bool($site['sewer_hookup'] ?? null);
        $ok = match ($hookups) { 'electric' => $power, 'full' => ($power === false || $water === false || $sewer === false) ? false : (($power && $water && $sewer) ? true : null), 'none' => ($power || $water || $sewer) ? false : (($power === false && $water === false && $sewer === false) ? true : null) };
        if ($ok === false) return null;
        if ($ok === null) $missing = true;
        $score++;
    }
    return $missing ? -1 : $score;
}

function llama_pricing_row_price(array $doc, array $row, ?string $date): array
{
    if (!empty($row['needs_review'])) return ['state' => 'needs_review', 'amount' => null];
    $prices = array_intersect_key($row, array_flip(['price','weekend_price','holiday_price']));
    $label = 'Base';
    if ($date !== null) {
        $seasons = array_values(array_filter($row['seasons'] ?? [], static fn($p) => empty($doc['_base_only']) && !isset($p['season_id']) && llama_pricing_in_period($p, $date)));
        foreach ($row['seasons'] ?? [] as $period) {
            if (isset($period['season_id']) && $period['season_id'] === ($doc['_season_id'] ?? null)) {
                $seasons = [$period];
                break;
            }
        }
        if (count($seasons) > 1) return ['state' => 'conflict', 'amount' => null];
        $replaceEventPrice = false;
        if ($seasons) {
            $seasonPrices = $seasons[0];
            $replaceEventPrice = isset($seasonPrices['season_id'])
                && ($seasonPrices['price'] !== null || $seasonPrices['holiday_price'] !== null);
            if (isset($seasonPrices['season_id']) && $seasonPrices['price'] !== null) {
                // A seasonal weekday amount becomes the fallback for its weekend and holiday.
                $prices = [];
            }
            foreach (['price','weekend_price','holiday_price'] as $k) {
                if (($seasonPrices[$k] ?? null) !== null) $prices[$k] = $seasonPrices[$k];
            }
            $label = ($seasonPrices['name'] ?? '') ?: 'Seasonal';
        }
        $isWeekend = in_array((int) (new DateTimeImmutable($date))->format('w'), $doc['weekend_days'], true);
        $holidays = array_filter($doc['holidays'], static fn($p) => llama_pricing_in_period($p, $date));
        $amount = $prices['price'] ?? null;
        if ($isWeekend && ($prices['weekend_price'] ?? null) !== null) { $amount = $prices['weekend_price']; $label .= ' · Weekend'; }
        if ($holidays && ($prices['holiday_price'] ?? null) !== null) { $amount = $prices['holiday_price']; $label .= ' · Holiday'; }
        $holidayAmounts = [];
        foreach ($replaceEventPrice ? [] : $holidays as $h) if (($row['holiday_prices'][$h['id']] ?? null) !== null) $holidayAmounts[] = $row['holiday_prices'][$h['id']];
        if (count(array_unique($holidayAmounts, SORT_REGULAR)) > 1) return ['state' => 'conflict', 'amount' => null];
        if ($holidayAmounts) { $amount = $holidayAmounts[0]; $label .= ' · Event override'; }
        return ['state' => $amount === null ? 'unknown' : 'priced', 'amount' => $amount, 'label' => $label];
    }
    $values = array_filter(array_values($prices), static fn($v) => $v !== null);
    foreach ($row['seasons'] ?? [] as $p) foreach (['price','weekend_price','holiday_price'] as $k) if (($p[$k] ?? null) !== null) $values[] = $p[$k];
    foreach ($row['holiday_prices'] ?? [] as $amount) if ($amount !== null) $values[] = $amount;
    return ['state' => $values ? 'priced' : 'unknown', 'amount' => null, 'minimum' => $values ? min($values) : null, 'maximum' => $values ? max($values) : null, 'label' => 'Published rates'];
}

function llama_pricing_money(float $value): string
{
    $amount = number_format($value, 2, '.', '');
    return '$' . (str_ends_with($amount, '.00') ? substr($amount, 0, -3) : $amount);
}

function llama_pricing_label(array $price, string $unit = 'night'): string
{
    if (($price['state'] ?? '') !== 'priced') return match ($price['state'] ?? '') { 'conflict', 'needs_review' => 'Needs pricing review', default => 'Price unknown' };
    $min = $price['amount'] ?? $price['minimum'] ?? null; $max = $price['amount'] ?? $price['maximum'] ?? $min;
    if ($min === null) return 'Price unknown';
    $suffix = match ($unit) { 'person_night' => 'person / night', 'vehicle_night' => 'vehicle / night', default => $unit };
    if ((float) $min === 0.0 && (float) $max === 0.0) return $unit === 'night' ? 'Free' : '$0 / ' . $suffix;
    return llama_pricing_money((float) $min) . ($max !== $min ? ' to ' . llama_pricing_money((float) $max) : '') . ' / ' . $suffix;
}

/** Site conditions resolve once, then that row's own date schedule is applied. */
function llama_pricing_for_site(array $doc, array $site, ?string $date = null, string $unit = 'night'): array
{
    if ($date !== null) llama_pricing_date($date);
    if (!empty($doc['invalid'])) return ['state' => 'needs_review', 'label' => 'Needs pricing review'];
    if (preg_match('/PICNIC|SHELTER|DAY USE/i', (string) ($site['raw_site_type'] ?? ''))) return ['state' => 'unknown', 'label' => 'No overnight rate', 'amount' => null];
    $site = llama_pricing_site_context($site); $best = -1; $bestSeason = false; $matches = []; $uncertain = false;
    foreach ($doc['rows'] as $row) {
        if (($row['unit'] ?? 'night') !== $unit) continue;
        $score = llama_pricing_match($row['match'] ?? [], $site);
        if ($score === null) continue;
        $rowPrice = llama_pricing_row_price($doc, $row, $date);
        if ($rowPrice['state'] === 'unknown' && !empty($row['seasons'])) {
            // A row that only applies in another season must not mask the base rate.
            continue;
        }
        if ($score === -1) { $uncertain = true; continue; }
        $seasonal = $date !== null && ($doc['_season_id'] ?? null) !== null
            && in_array($doc['_season_id'], array_column($row['seasons'] ?? [], 'season_id'), true);
        if ($score > $best || ($score === $best && $seasonal && !$bestSeason)) {
            $best = $score;
            $bestSeason = $seasonal;
            $matches = [];
        }
        if ($score === $best && $seasonal === $bestSeason) $matches[] = [$row, $rowPrice];
    }
    $state = count($matches) > 1 ? 'conflict' : (!$matches ? ($uncertain ? 'needs_review' : 'unknown') : '');
    // An unresolved possible category must not silently fall through to a flat price.
    if ($uncertain && $best < 100) $state = 'needs_review';
    [$row, $resolvedPrice] = $matches[0] ?? [[], []];
    $price = $state !== '' ? ['state' => $state, 'amount' => null] : $resolvedPrice;
    $price['schedule_label'] = (string) ($price['label'] ?? '');
    $price['row_id'] = count($matches) === 1 ? (string) ($row['id'] ?? '') : '';
    $price['rate_name'] = (string) ($row['name'] ?? '');
    if ($price['rate_name'] === '') $price['rate_name'] = ucfirst((string) ($row['match']['category'] ?? '')) ?: 'All Sites';
    $price['label'] = llama_pricing_label($price, $unit);
    $price['unit'] = $unit; $price['currency'] = 'USD';
    return $price;
}

function llama_pricing_row_targets_sites(array $row): bool
{
    $match = $row['match'] ?? [];
    return !empty($match['site_ids']) || !empty($match['feature_ids'])
        || !empty($match['area_ids']) || !empty($match['legacy_site']);
}

/** Without public Site results, only use the Place's general rates. */
function llama_pricing_place_overview(array $doc, ?array $sitePrices = null): array
{
    if ($sitePrices === null) {
        $sitePrices = [];
        foreach ($doc['rows'] as $row) {
            if (($row['unit'] ?? 'night') !== 'night' || llama_pricing_row_targets_sites($row)) continue;
            $sitePrices[] = llama_pricing_row_price($doc, $row, null);
        }
    }

    $values = [];
    foreach ($sitePrices as $price) {
        if ($price['state'] !== 'priced') continue;
        $values[] = $price['minimum'];
        $values[] = $price['maximum'];
    }
    if (!$values) return ['label' => '', 'status' => 'unknown'];

    $minimum = (float) min($values);
    $maximum = (float) max($values);
    if ($maximum === 0.0) return ['label' => 'Free', 'status' => 'free'];

    return [
        'label' => 'From: ' . llama_pricing_money($minimum) . ' / night',
        'status' => $minimum === 0.0 ? 'mixed' : 'paid',
    ];
}

function llama_pricing_place_summary(array $doc): string
{
    return llama_pricing_place_overview($doc)['label'];
}

/** Resolve Site-targeted rates only against Sites anyone can see. */
function llama_pricing_public_place_overview(PDO $db, array $place, array $doc): array
{
    $linked = array_filter($doc['rows'], static fn ($row) => array_column($row['seasons'] ?? [], 'season_id'));
    if ($linked) {
        require_once __DIR__ . '/place-report/season-definitions.php';
        $active = array_column(llama_seasons_for_place($db, (int) $place['id']), null, 'id');
        foreach ($doc['rows'] as &$row) {
            $row['seasons'] = array_values(array_filter(
                $row['seasons'] ?? [],
                static fn ($period) => !isset($period['season_id']) || isset($active[$period['season_id']])
            ));
        }
        unset($row);
    }
    foreach ($doc['rows'] as $row) {
        if (($row['unit'] ?? 'night') !== 'night' || !llama_pricing_row_targets_sites($row)
            || llama_pricing_row_price($doc, $row, null)['state'] !== 'priced') continue;

        require_once __DIR__ . '/place-campsites.php';
        $placeId = (int) $place['id'];
        $sites = llama_place_campsites($db, $placeId);
        if (!$sites) break;

        // The same visibility policy used by the Site API also gates prices.
        $restricted = llama_site_restricted_visibility_ids($db, $placeId);
        $sites = array_filter(
            $sites,
            static fn (array $site): bool => llama_site_is_public($place, $site, $restricted)
        );
        if (!$sites) break;

        $pricedSites = llama_pricing_enrich_sites($db, $place, $sites, $doc);
        return llama_pricing_place_overview($doc, array_column($pricedSites, 'pricing'));
    }

    return llama_pricing_place_overview($doc);
}

/** Keep public price and booking lookups on the same selected operating season. */
function llama_pricing_public_night(array $doc, ?array $season, mixed $requested): array
{
    $date = date('Y-m-d');
    $doc['_base_only'] = (string) $requested === '0';
    $doc['_season_id'] = isset($season['id']) ? (int) $season['id'] : null;
    if ($season !== null && (string) $requested === (string) $season['id']
        && !empty($season['start_mmdd']) && !empty($season['end_mmdd'])) {
        $period = ['start' => $season['start_mmdd'], 'end' => $season['end_mmdd'], 'recurring' => true];
        if (!llama_pricing_in_period($period, $date)) {
            $date = date('Y') . '-' . $period['start'];
            if (str_ends_with($date, '-02-29') && !checkdate(2, 29, (int) date('Y'))) {
                $date = date('Y') . '-03-01';
            }
        }
    }
    return [$doc, $date];
}

/** Enrich already-authorized Sites. Never returns raw selectors or source URLs. */
function llama_pricing_enrich_sites(PDO $db, array $place, array $sites, ?array $doc = null, ?string $date = null): array
{
    $doc ??= llama_pricing_document($db, $place);
    $overrides = [];
    $ids = array_values(array_filter(array_map('intval', array_column($sites, 'id'))));
    foreach (array_chunk($ids, 400) as $batch) {
        try {
            $marks = implode(',', array_fill(0, count($batch), '?'));
            $q = $db->prepare('SELECT target_id, question_key, answer_state, value_json FROM place_report_current_answers WHERE place_id = ? AND target_scope = ? AND target_source = ? AND question_key IN ("capacity_size_rating", "site_loop", "hookup_water", "hookup_sewer", "hookup_electric_service", "back_in", "pull_through") AND target_id IN (' . $marks . ')');
            $q->execute([(int) $place['id'], 'site', 'campsite_record', ...$batch]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $answer) {
                $value = $answer['answer_state'] === 'answered' ? json_decode((string) $answer['value_json'], true) : null;
                if ($answer['answer_state'] === 'unanswered') continue;
                $overrides[(int) $answer['target_id']][$answer['question_key']] = $value;
            }
        } catch (PDOException $e) {
            if (!in_array((string) ($e->errorInfo[1] ?? ''), ['1146','1054'], true)) throw $e;
        }
    }
    // Old Area selectors need the existing Site -> mapped Area relationship.
    $areas = [];
    if ($ids && array_filter($doc['rows'], static fn($r) => !empty($r['match']['area_ids']))) {
        $q = $db->prepare('SELECT feature_id, camping_area_feature_id FROM place_map_camping_sites WHERE feature_id IN (SELECT map_feature_id FROM place_campsites WHERE place_id = ?)');
        $q->execute([(int) $place['id']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $areas[(int) $r['feature_id']] = (int) $r['camping_area_feature_id'];
    }
    foreach ($sites as &$site) {
        $context = $site;
        $context['parent_feature_id'] = $areas[(int) ($site['map_feature_id'] ?? 0)] ?? 0;
        $o = $overrides[(int) ($site['id'] ?? 0)] ?? [];
        if (array_key_exists('capacity_size_rating', $o)) $context['pricing_category'] = strtolower((string) $o['capacity_size_rating']);
        if (array_key_exists('hookup_water', $o)) $context['water_hookup'] = $o['hookup_water'];
        if (array_key_exists('hookup_sewer', $o)) $context['sewer_hookup'] = $o['hookup_sewer'];
        if (array_key_exists('hookup_electric_service', $o)) $context['electric_hookup'] = $o['hookup_electric_service'] === null ? null : !in_array($o['hookup_electric_service'], ['', 'none', '0'], true);
        if (($o['pull_through'] ?? null) === true) $context['parking_style'] = 'pull_through';
        elseif (($o['back_in'] ?? null) === true) $context['parking_style'] = 'back_in';
        if (array_key_exists('site_loop', $o)) $context['pricing_loop'] = (string) $o['site_loop'];
        $site['pricing'] = llama_pricing_for_site($doc, $context, $date);
        $priceLabels = [];
        $closed = !empty($site['is_closed']) || !empty($site['place_verified_closed'])
            || ($site['seasonal_operating_status'] ?? '') === 'closed';
        if ($closed) {
            $site['pricing_summary'] = '';
            continue;
        }
        if ($site['pricing']['state'] === 'priced') $priceLabels[] = $site['pricing']['label'];
        foreach (['week','month','stay','season','person_night','vehicle_night'] as $unit) {
            $other = llama_pricing_for_site($doc, $context, $date, $unit);
            if ($other['state'] === 'priced') $priceLabels[] = $other['label'];
        }
        $site['pricing_summary'] = implode(' · ', $priceLabels);
        $site['summary'] = array_values(array_unique([...$priceLabels, ...($site['summary'] ?? [])]));
    }
    unset($site);
    return $sites;
}

/** Decorate authorized mapped geometry using the same canonical Site prices. */
function llama_pricing_enrich_features(PDO $db, array $place, array $features): array
{
    require_once __DIR__ . '/place-campsites.php';
    require_once __DIR__ . '/place-report/season-public.php';
    $placeId = (int) $place['id'];
    $requested = $_GET['season'] ?? null;
    $season = llama_public_season_selection($db, $placeId, $requested)['selected'];
    [$doc, $date] = llama_pricing_public_night(llama_pricing_document($db, $place), $season, $requested);
    $byMap = [];
    $sites = llama_place_campsites($db, $placeId);
    $seasonId = (int) ($season['id'] ?? 0);
    $status = $seasonId ? llama_public_season_site_statuses($db, $placeId, $seasonId, array_column($sites, 'id')) : [];
    $placeClosed = llama_public_place_verified_closed($db, $placeId);
    foreach (llama_pricing_enrich_sites($db, $place, $sites, $doc, $date) as $site) {
        if (!empty($site['map_feature_id'])) $byMap[(int) $site['map_feature_id']] = $site;
    }
    foreach ($features as &$feature) {
        $site = $byMap[(int) ($feature['id'] ?? 0)] ?? null;
        $closed = $placeClosed || ($status[(int) ($site['id'] ?? 0)] ?? '') === 'closed';
        $feature['pricing_summary'] = $closed ? '' : ($site['pricing_summary'] ?? '');
        // Retain legacy storage for rollback, never expose a competing public price.
        foreach (['rates','effective_rates','rate_summary','effective_rate_summary','site_class_rate_summary','site_classes'] as $key) $feature[$key] = [];
    }
    unset($feature);
    return $features;
}

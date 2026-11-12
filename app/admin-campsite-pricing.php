<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-campsites.php';
require_once __DIR__ . '/place-pricing.php';
require_once __DIR__ . '/place-report/season-answers.php';

/**
 * Single Site editor adapter for the existing Place pricing v2 document and
 * M4 seasonal Site answers. No additional pricing tables or Site identities.
 * It intentionally does NOT define real-time booking availability.
 */
function admin_cpr_snapshot(PDO $db, int $placeId, int $siteId): array
{
    $stmt = $db->prepare('SELECT id FROM places WHERE id = ? LIMIT 1');
    $stmt->execute([$placeId]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Place not found.');
    }
    $site = llama_place_campsites($db, $placeId, $siteId)[0] ?? null;
    if (!is_array($site)) {
        throw new InvalidArgumentException('The Site does not belong to this Place.');
    }
    $document = llama_pricing_document($db, ['id' => $placeId]);
    if (!empty($document['invalid'])) {
        throw new InvalidArgumentException(
            'Existing pricing needs review before this Site can be edited.'
        );
    }
    $seasons = llama_seasons_for_place($db, $placeId);
    $statuses = [];
    $statusStmt = $db->prepare(
        'SELECT season_id, operating_status FROM place_season_site_status
         WHERE place_id = ? AND target_source = ? AND target_id = ?'
    );
    $statusStmt->execute([$placeId, 'campsite_record', $siteId]);
    foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $statuses[(string) $row['season_id']] = (string) $row['operating_status'];
    }

    $bookings = [];
    $bookingStmt = $db->prepare(
        "SELECT season_id, answer_state, value_json
         FROM place_season_answers
         WHERE place_id = ? AND target_scope = 'site'
           AND target_source = 'campsite_record' AND target_id = ?
           AND question_key = 'site_reservation_method'"
    );
    $bookingStmt->execute([$placeId, $siteId]);
    foreach ($bookingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if ((string) $row['answer_state'] !== 'answered') {
            continue;
        }
        $value = json_decode((string) ($row['value_json'] ?? 'null'), true);
        if (is_string($value)) {
            $bookings[(string) $row['season_id']] = $value;
        }
    }

    $availability = [];
    foreach ($seasons as $season) {
        $id = (string) $season['id'];
        $availability[$id] = match (true) {
            ($statuses[$id] ?? '') === 'closed' => 'unavailable',
            ($bookings[$id] ?? '') === 'reservable' => 'reservable',
            ($bookings[$id] ?? '') === 'first_come' => 'fcfs',
            ($bookings[$id] ?? '') === 'not_reservable' => 'available',
            ($statuses[$id] ?? '') === 'open' => 'available',
            default => '',
        };
    }

    $context = llama_pricing_site_context($site);
    $matched = [];
    $best = -1;
    foreach ($document['rows'] as $row) {
        if (($row['unit'] ?? 'night') !== 'night') {
            continue;
        }
        $score = llama_pricing_match($row['match'] ?? [], $context);
        if ($score === null || $score < 0) {
            continue;
        }
        if ($score > $best) {
            $best = $score;
            $matched = [$row];
        } elseif ($score === $best) {
            $matched[] = $row;
        }
    }
    // Select the effective weekday/weekend/holiday row separately for each
    // Place season. A Site-only row for Peak must not hide an inherited
    // general rate in Off-Season.
    $rateConflict = false;
    $ratesBySeason = [];
    foreach ($seasons as $season) {
        $seasonId = (int) $season['id'];
        $topScore = -1;
        $bestRates = [];
        foreach ($document['rows'] as $row) {
            if (($row['unit'] ?? 'night') !== 'night') continue;
            $score = llama_pricing_match($row['match'] ?? [], $context);
            if ($score === null || $score < 0) continue;

            $base = [
                'weekday' => $row['price'] ?? null,
                'weekend' => $row['weekend_price'] ?? null,
                'holiday' => $row['holiday_price'] ?? null,
            ];
            $period = null;
            foreach ((array) ($row['seasons'] ?? []) as $candidate) {
                if ((int) ($candidate['season_id'] ?? 0) === $seasonId) {
                    $period = $candidate;
                    break;
                }
            }
            if ($period !== null) {
                if (($period['price'] ?? null) !== null) {
                    // Follow the public price resolver: an explicit seasonal
                    // weekday amount replaces the old base fallback.
                    $base = ['weekday' => $period['price'], 'weekend' => null, 'holiday' => null];
                }
                foreach (['price' => 'weekday', 'weekend_price' => 'weekend',
                    'holiday_price' => 'holiday'] as $source => $key) {
                    if (($period[$source] ?? null) !== null) {
                        $base[$key] = $period[$source];
                    }
                }
            }
            if (array_filter($base, static fn ($value): bool => $value !== null) === []) {
                continue;
            }
            $rank = $score * 10 + ($period !== null ? 1 : 0);
            if ($rank > $topScore) {
                $topScore = $rank;
                $bestRates = [$base];
            } elseif ($rank === $topScore) {
                $bestRates[] = $base;
            }
            if (!empty($row['needs_review']) && $rank >= $topScore) {
                $rateConflict = true;
            }
        }
        if (count($bestRates) > 1) {
            $rateConflict = true;
        }
        $ratesBySeason[(string) $seasonId] = count($bestRates) === 1
            ? $bestRates[0] : ['weekday' => null, 'weekend' => null, 'holiday' => null];
    }
    $rate = count($matched) === 1 ? $matched[0] : null;

    $version = hash('sha256', json_encode([
        'document' => $document,
        'statuses' => $statuses,
        'bookings' => $bookings,
        'seasons' => array_map(static fn (array $s): array => [
            (int) $s['id'],
            (string) $s['start_mmdd'],
            (string) $s['end_mmdd'],
            (int) ($s['is_recurring'] ?? 1),
            (string) ($s['starts_on'] ?? ''),
            (string) ($s['ends_on'] ?? ''),
        ], $seasons),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    return [
        'site' => $site,
        'document' => $document,
        'seasons' => $seasons,
        'availability' => $availability,
        'rates_by_season' => $ratesBySeason,
        'vehicle_by_season' => admin_cpr_vehicle_amounts($document, $context, $seasons),
        'rate_conflict' => $rateConflict,
        'rate' => $rate,
        'version' => $version,
    ];
}

/**
 * Extra vehicle pricing uses the same canonical rate rows as nightly prices,
 * with unit = vehicle_night. It is a surcharge, never another campsite night.
 */
function admin_cpr_vehicle_amounts(
    array $doc, array $context, array $seasons
): array {
    $values = [];
    foreach ($seasons as $season) {
        $id = (int) $season['id'];
        $best = -1;
        $matched = [];
        foreach ((array) ($doc['rows'] ?? []) as $row) {
            if (($row['unit'] ?? 'night') !== 'vehicle_night') continue;
            $score = llama_pricing_match((array) ($row['match'] ?? []), $context);
            if ($score === null || $score < 0) continue;
            $amount = $row['price'] ?? null;
            $inPeriod = false;
            foreach ((array) ($row['seasons'] ?? []) as $period) {
                if ((int) ($period['season_id'] ?? 0) === $id) {
                    $amount = $period['price'] ?? null;
                    $inPeriod = true;
                    break;
                }
            }
            if ($amount === null) continue;
            $rank = $score * 10 + (int) $inPeriod;
            if ($rank > $best) {
                $best = $rank;
                $matched = [$amount];
            } elseif ($rank === $best) {
                $matched[] = $amount;
            }
        }
        $values[(string) $id] = count($matched) === 1 ? $matched[0] : null;
    }
    return $values;
}

/** Add/update only the vehicle_night row owned by one Site or group. */
function admin_cpr_merge_vehicle(
    array $document, string $rowId, array $match,
    string $name, array $changes
): array {
    $index = null;
    foreach ($document['rows'] as $i => $row) {
        if ((string) ($row['id'] ?? '') !== $rowId) continue;
        if (($row['unit'] ?? '') !== 'vehicle_night'
            || (array) ($row['match'] ?? []) != $match) {
            throw new InvalidArgumentException('Extra vehicle rate row ownership conflicts.');
        }
        $index = $i;
        break;
    }
    if ($index === null) {
        $hasAmount = false;
        foreach ($changes as $change) {
            if (array_key_exists('vehicle', $change) && $change['vehicle'] !== null) {
                $hasAmount = true;
                break;
            }
        }
        if (!$hasAmount) return $document;
        $document['rows'][] = [
            'id' => $rowId, 'name' => $name . ' · Extra vehicle',
            'match' => $match, 'unit' => 'vehicle_night',
            'price' => null, 'weekend_price' => null, 'holiday_price' => null,
            'seasons' => [],
        ];
        $index = array_key_last($document['rows']);
    }
    $periods = (array) ($document['rows'][$index]['seasons'] ?? []);
    foreach ($changes as $change) {
        if (!array_key_exists('vehicle', $change)) continue;
        $sid = (int) $change['season_id'];
        $matchIndex = null;
        foreach ($periods as $n => $period) {
            if ((int) ($period['season_id'] ?? 0) === $sid) {
                $matchIndex = $n;
                break;
            }
        }
        if ($change['vehicle'] === null) {
            if ($matchIndex !== null) unset($periods[$matchIndex]);
        } else {
            $period = [
                'season_id' => $sid,
                'price' => $change['vehicle'],
                'weekend_price' => null,
                'holiday_price' => null,
            ];
            if ($matchIndex === null) $periods[] = $period;
            else $periods[$matchIndex] = $period;
        }
    }
    $document['rows'][$index]['seasons'] = array_values($periods);
    if (!$periods && ($document['rows'][$index]['price'] ?? null) === null) {
        unset($document['rows'][$index]);
        $document['rows'] = array_values($document['rows']);
    }
    return llama_pricing_validate($document);
}

/**
 * Mutate at most one explicit Site-priced row and its per-season prices.
 * Existing shared Place, Area and other Site rows stay byte-for-byte
 * unchanged in the validated document.
 */
function admin_cpr_merge_rates(
    array $document,
    int $siteId,
    array $site,
    array $changes
): array {
    $rowIndex = null;
    $dedicatedId = 'cpr-site-' . $siteId;
    $siteRows = [];

    foreach ($document['rows'] as $index => $row) {
        if (($row['unit'] ?? 'night') !== 'night') {
            continue;
        }
        $match = (array) ($row['match'] ?? []);
        if ((string) ($row['id'] ?? '') === $dedicatedId) {
            if (!in_array($siteId, $match['site_ids'] ?? [], true)
                || count($match['site_ids'] ?? []) !== 1) {
                throw new InvalidArgumentException('The Site pricing row has a conflicting owner.');
            }
            $rowIndex = $index;
        }
        // Shared multi-Site rate rows are valid fallbacks and must coexist
        // with one dedicated Site override. Only multiple competing exact
        // single-Site rows are ambiguous.
        if (($match['site_ids'] ?? []) === [$siteId]) {
            $siteRows[] = $index;
        }
    }

    // Reuse an unambiguous existing, individually assigned Site row.
    if ($rowIndex === null && count($siteRows) === 1) {
        $candidate = $document['rows'][$siteRows[0]];
        if (($candidate['match']['site_ids'] ?? []) === [$siteId]
            && empty($candidate['needs_review'])) {
            $rowIndex = $siteRows[0];
        }
    }
    if (count($siteRows) > 1) {
        throw new InvalidArgumentException(
            'More than one Site-specific pricing row exists. Resolve the conflict before saving.'
        );
    }

    $hasRateEdit = false;
    foreach ($changes as $item) {
        if (array_key_exists('rates', $item)) {
            $hasRateEdit = true;
            break;
        }
    }
    if (!$hasRateEdit) {
        return $document;
    }

    // Do not create an empty pricing rule when only an unrelated booking
    // status changed or when the user cleared already-unset amounts.
    if ($rowIndex === null && !array_filter(
        $changes,
        static fn (array $item): bool => isset($item['rates'])
            && count(array_filter($item['rates'], static fn ($v): bool => $v !== null)) > 0
    )) {
        return $document;
    }

    if ($rowIndex === null) {
        $document['rows'][] = [
            'id' => $dedicatedId,
            'name' => trim((string) ($site['site_name'] ?? '')) ?: 'Site #' . $siteId,
            'match' => [
                'category' => '', 'layout' => '', 'hookups' => '',
                'raw_type' => '', 'legacy_category' => '', 'legacy_site' => '', 'loop' => '',
                'site_ids' => [$siteId], 'feature_ids' => [], 'area_ids' => [],
                'site_selection' => true,
            ],
            'unit' => 'night',
            'price' => null,
            'weekend_price' => null,
            'holiday_price' => null,
            'seasons' => [],
        ];
        $rowIndex = array_key_last($document['rows']);
    }

    $row =& $document['rows'][$rowIndex];
    if (!empty($row['needs_review'])) {
        throw new InvalidArgumentException('This pricing row requires review before editing.');
    }
    $periods = (array) ($row['seasons'] ?? []);
    foreach ($changes as $item) {
        if (!isset($item['rates'])) {
            continue;
        }
        $seasonId = (int) $item['season_id'];
        $price = [
            'season_id' => $seasonId,
            'price' => $item['rates']['weekday'],
            'weekend_price' => $item['rates']['weekend'],
            'holiday_price' => $item['rates']['holiday'],
        ];
        $found = null;
        foreach ($periods as $index => $period) {
            if ((int) ($period['season_id'] ?? 0) === $seasonId) {
                $found = $index;
                break;
            }
        }
        if ($found === null) {
            if (count(array_filter(
                [$price['price'], $price['weekend_price'], $price['holiday_price']],
                static fn ($v): bool => $v !== null
            ))) {
                $periods[] = $price;
            }
        } elseif ($price['price'] === null && $price['weekend_price'] === null
            && $price['holiday_price'] === null) {
            unset($periods[$found]);
        } else {
            $periods[$found] = $price;
        }
    }
    $row['seasons'] = array_values($periods);
    unset($row);

    return llama_pricing_validate($document);
}

/** One authorized, CSRF-checked caller wraps all edits in one transaction. */
function admin_cpr_save(
    PDO $db,
    int $placeId,
    int $siteId,
    int $actorId,
    string $expectedVersion,
    array $rawChanges
): array {
    if ($placeId < 1 || $siteId < 1 || $actorId < 1 || !$rawChanges
        || count($rawChanges) > 30 || strlen($expectedVersion) !== 64) {
        throw new InvalidArgumentException('Choose a Site, season, and valid changes.');
    }
    if ($db->inTransaction()) {
        throw new LogicException('Unexpected nested pricing save.');
    }
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }

        $before = admin_cpr_snapshot($db, $placeId, $siteId);
        if (!hash_equals($before['version'], $expectedVersion)) {
            throw new RuntimeException('Pricing or seasonal availability changed in another tab. Reload before saving.', 409);
        }
        if ($before['rate_conflict']) {
            throw new InvalidArgumentException(
                'Conflicting published rates need review before this Site can be edited.'
            );
        }

        $seasonIds = array_fill_keys(
            array_map('intval', array_column($before['seasons'], 'id')),
            true
        );
        $changes = [];
        foreach ($rawChanges as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid pricing change.');
            }
            $id = filter_var($item['season_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || !isset($seasonIds[$id]) || isset($changes[$id])) {
                throw new InvalidArgumentException('Pricing refers to a missing or duplicate Place season.');
            }
            $change = ['season_id' => $id];
            if (array_key_exists('rates', $item)) {
                if (!is_array($item['rates'])
                    || array_keys($item['rates']) !== ['weekday','weekend','holiday']) {
                    throw new InvalidArgumentException('Provide weekday, weekend, and holiday prices.');
                }
                $rates = [];
                foreach (['weekday','weekend','holiday'] as $key) {
                    $rates[$key] = llama_pricing_amount($item['rates'][$key]);
                }
                $change['rates'] = $rates;
            }
            if (array_key_exists('vehicle', $item)) {
                $change['vehicle'] = llama_pricing_amount($item['vehicle']);
            }
            if (array_key_exists('availability', $item)) {
                $available = (string) $item['availability'];
                if (!in_array($available, [
                    'available', 'reservable', 'fcfs', 'unavailable',
                ], true)) {
                    throw new InvalidArgumentException('Choose a valid seasonal Site availability.');
                }
                $change['availability'] = $available;
            }
            if (count($change) < 2) {
                throw new InvalidArgumentException('There is nothing to save for this season.');
            }
            $changes[$id] = $change;
        }

        $updated = admin_cpr_merge_rates(
            $before['document'], $siteId, $before['site'], array_values($changes)
        );
        $match = [
            'category' => '', 'layout' => '', 'hookups' => '',
            'raw_type' => '', 'legacy_category' => '', 'legacy_site' => '',
            'loop' => '', 'site_ids' => [$siteId],
            'feature_ids' => [], 'area_ids' => [], 'site_selection' => true,
        ];
        $updated = admin_cpr_merge_vehicle(
            $updated, 'cpr-vehicle-site-' . $siteId, $match,
            trim((string) ($before['site']['site_name'] ?? '')) ?: 'Site #' . $siteId,
            array_values($changes)
        );
        $ratesChanged = $updated !== $before['document'];
        if ($ratesChanged) {
            // The canonical existing pricing owner remains place_rules.
            // Do not create a second Site pricing table or change other rows.
            $json = json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $existing = $db->prepare('SELECT place_id FROM place_rules WHERE place_id = ? FOR UPDATE');
            $existing->execute([$placeId]);
            if ($existing->fetchColumn()) {
                $write = $db->prepare('UPDATE place_rules SET rate_cards_json = ? WHERE place_id = ?');
                $write->execute([$json, $placeId]);
            } else {
                $write = $db->prepare('INSERT INTO place_rules (place_id, rate_cards_json) VALUES (?, ?)');
                $write->execute([$placeId, $json]);
            }
        }

        $target = ['scope' => 'site', 'source' => 'campsite_record', 'id' => $siteId];
        foreach ($changes as $change) {
            if (!array_key_exists('availability', $change)) {
                continue;
            }
            $status = $change['availability'];
            $modes = match ($status) {
                'unavailable' => ['site_reservation_method' => 'inherit'],
                'available', 'reservable', 'fcfs' => ['site_reservation_method' => 'answer'],
            };
            $values = match ($status) {
                'available' => ['site_reservation_method' => 'not_reservable'],
                'reservable' => ['site_reservation_method' => 'reservable'],
                'fcfs' => ['site_reservation_method' => 'first_come'],
                'unavailable' => [],
            };
            llama_season_save_target_answers(
                $db, $placeId, (int) $change['season_id'], $target,
                $actorId, $modes, $values,
                $status === 'unavailable' ? 'closed' : 'open'
            );
        }

        admin_users_audit(
            $db, $actorId, null, 'place.site_pricing_updated',
            'Updated seasonal pricing for one Site.',
            ['place_id' => $placeId, 'site_id' => $siteId,
                'season_ids' => array_keys($changes), 'rates_changed' => $ratesChanged]
        );
        $db->commit();
        return ['success' => true, 'seasons_saved' => count($changes)];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}


/**
 * Place holiday date calendar for the single authoritative pricing document.
 * Holiday dates are Place-wide; the holiday price remains per Site and season.
 */
function admin_cpr_save_holidays(
    PDO $db,
    int $placeId,
    int $siteId,
    int $actorId,
    string $expectedVersion,
    array $rawHolidays
): array {
    if ($placeId < 1 || $siteId < 1 || $actorId < 1
        || strlen($expectedVersion) !== 64 || count($rawHolidays) > 60) {
        throw new InvalidArgumentException('Invalid Place holiday dates.');
    }
    if ($db->inTransaction()) {
        throw new LogicException('Unexpected nested holiday save.');
    }

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }
        $before = admin_cpr_snapshot($db, $placeId, $siteId);
        if (!hash_equals($expectedVersion, $before['version'])) {
            throw new RuntimeException(
                'Holiday dates or Site pricing changed in another tab. Reload and review before saving.',
                409
            );
        }

        $holidays = [];
        $seen = [];
        foreach ($rawHolidays as $index => $raw) {
            if (!is_array($raw)) {
                throw new InvalidArgumentException('Invalid holiday row.');
            }
            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new InvalidArgumentException('Give each holiday a name of 1 to 100 characters.');
            }
            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '') {
                $id = 'cpr-holiday-' . bin2hex(random_bytes(8));
            }
            if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or duplicated holiday identifier.');
            }
            $seen[$id] = true;
            $holidays[] = [
                'id' => $id,
                'name' => $name,
                'start' => trim((string) ($raw['start'] ?? '')),
                'end' => trim((string) ($raw['end'] ?? '')),
                'recurring' => !empty($raw['recurring']),
            ];
        }

        $document = $before['document'];
        $document['holidays'] = $holidays;
        $document = llama_pricing_validate($document);
        if ($document !== $before['document']) {
            $json = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $existing = $db->prepare('SELECT place_id FROM place_rules WHERE place_id = ? FOR UPDATE');
            $existing->execute([$placeId]);
            if ($existing->fetchColumn()) {
                $write = $db->prepare(
                    'UPDATE place_rules SET rate_cards_json = ? WHERE place_id = ?'
                );
                $write->execute([$json, $placeId]);
            } else {
                $write = $db->prepare(
                    'INSERT INTO place_rules (place_id, rate_cards_json) VALUES (?, ?)'
                );
                $write->execute([$placeId, $json]);
            }
            admin_users_audit(
                $db, $actorId, null, 'place.pricing_holidays_updated',
                'Updated Place holiday dates for Campsite Pricing.',
                ['place_id' => $placeId, 'holiday_count' => count($document['holidays'])]
            );
        }
        $db->commit();
        return ['success' => true, 'holidays_saved' => count($document['holidays'])];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}


/** Revision token for the full Place season schedule, including inactive rows. */
function admin_cpr_season_version(PDO $db, int $placeId): string
{
    return hash('sha256', json_encode(
        llama_seasons_for_place($db, $placeId, true),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ));
}

/**
 * Add or edit a Place season without leaving the Campsite Pricing screen.
 * Place, Site and seasonal pricing use the same Place lock for serialization.
 * The existing canonical season definition and overlap checks remain active.
 */
function admin_cpr_save_season(
    PDO $db,
    int $placeId,
    int $actorId,
    string $expectedVersion,
    array $rawSeason
): array {
    if ($placeId < 1 || $actorId < 1 || strlen($expectedVersion) !== 64) {
        throw new InvalidArgumentException('Choose a Place and reload the season schedule.');
    }
    if ($db->inTransaction()) {
        throw new LogicException('Unexpected nested season update.');
    }
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }
        if (!hash_equals(admin_cpr_season_version($db, $placeId), $expectedVersion)) {
            throw new RuntimeException(
                'Someone changed this Place\'s seasons. Reload the pricing page before saving.',
                409
            );
        }
        $seasonId = llama_season_save($db, $placeId, $actorId, [
            'id' => (int) ($rawSeason['id'] ?? 0),
            'name' => (string) ($rawSeason['name'] ?? ''),
            'is_recurring' => !empty($rawSeason['recurring']) ? 1 : 0,
            'start_mmdd' => !empty($rawSeason['recurring'])
                ? (string) ($rawSeason['start'] ?? '') : '',
            'end_mmdd' => !empty($rawSeason['recurring'])
                ? (string) ($rawSeason['end'] ?? '') : '',
            'starts_on' => empty($rawSeason['recurring'])
                ? (string) ($rawSeason['start'] ?? '') : '',
            'ends_on' => empty($rawSeason['recurring'])
                ? (string) ($rawSeason['end'] ?? '') : '',
            'is_default' => (int) (!empty($rawSeason['default'])),
            'is_active' => (int) (!empty($rawSeason['active'])),
            'sort_order' => (int) ($rawSeason['sort_order'] ?? 0),
            'notes' => (string) ($rawSeason['notes'] ?? ''),
            'source_url' => (string) ($rawSeason['source_url'] ?? ''),
        ]);
        admin_users_audit(
            $db, $actorId, null, 'place.pricing_season_updated',
            'Updated a Place operating season from Campsite Pricing.',
            ['place_id' => $placeId, 'season_id' => $seasonId]
        );
        $db->commit();
        return ['success' => true, 'season_id' => $seasonId];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}


/** Review reminders are metadata, not closures or booking availability. */
function admin_cpr_reviews_ready(PDO $db): bool
{
    try {
        $statement = $db->query("SHOW TABLES LIKE 'place_pricing_reviews'");
        return $statement && $statement->fetch(PDO::FETCH_NUM) !== false;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Summarize paid Sites for a Place using actual pricing rule matches. A Site
 * with an explicit free rule is not counted as paid merely because a less
 * specific rule also matches. The closure review alarm is read, not copied.
 */
function admin_cpr_paid_site_queue(
    PDO $db, int $placeId, array $rawSites, array $seasons
): array {
    $doc = llama_pricing_document($db, ['id' => $placeId]);
    $reviewRows = [];
    if (admin_cpr_reviews_ready($db)) {
        $q = $db->prepare(
            'SELECT site_id, next_review_on, availability_release_on,
                    last_checked_on, source_url
             FROM place_pricing_reviews WHERE place_id = ?'
        );
        $q->execute([$placeId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
            $reviewRows[(int) ($item['site_id'] ?? 0)] = $item;
        }
    }

    $closureRows = [];
    try {
        $q = $db->prepare(
            "SELECT campsite_id, review_date, expected_reopening
             FROM place_operating_closures
             WHERE place_id = ? AND status <> 'open'"
        );
        $q->execute([$placeId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
            $key = (int) ($item['campsite_id'] ?? 0);
            $closureRows[$key] = $item;
        }
    } catch (PDOException) {
        // The pricing queue remains available before the closure module.
    }

    $seasonSummary = [];
    foreach ($seasons as $season) {
        $repeat = (int) ($season['is_recurring'] ?? 1) === 1;
        $start = $repeat
            ? (string) ($season['start_mmdd'] ?? '')
            : (string) ($season['starts_on'] ?? '');
        $end = $repeat
            ? (string) ($season['end_mmdd'] ?? '')
            : (string) ($season['ends_on'] ?? '');
        $seasonSummary[] = trim((string) ($season['name'] ?? '')
            . ': ' . $start . ' to ' . $end . ($repeat ? ' (annual)' : ''));
    }
    $sites = [];
    $contexts = llama_pricing_effective_site_contexts($db, $placeId, $rawSites, $doc);
    foreach ($rawSites as $site) {
        $siteId = (int) ($site['id'] ?? 0);
        if ($siteId < 1) continue;
        $context = llama_pricing_site_context($contexts[$siteId] ?? $site);
        $top = -1;
        $matching = [];
        foreach ((array) ($doc['rows'] ?? []) as $row) {
            if (($row['unit'] ?? 'night') !== 'night') continue;
            $score = llama_pricing_match((array) ($row['match'] ?? []), $context);
            if ($score === null || $score < 0) continue;
            if ($score > $top) {
                $top = $score;
                $matching = [$row];
            } elseif ($score === $top) {
                $matching[] = $row;
            }
        }
        if (count($matching) !== 1) continue;
        $rule = $matching[0];
        $prices = [
            $rule['price'] ?? null,
            $rule['weekend_price'] ?? null,
            $rule['holiday_price'] ?? null,
        ];
        foreach ((array) ($rule['seasons'] ?? []) as $period) {
            $prices[] = $period['price'] ?? null;
            $prices[] = $period['weekend_price'] ?? null;
            $prices[] = $period['holiday_price'] ?? null;
        }
        $positive = array_values(array_filter(
            $prices, static fn ($v): bool => $v !== null && (float) $v > 0
        ));
        if (!$positive) continue;

        $siteReview = (array) ($reviewRows[$siteId] ?? []);
        $placeReview = (array) ($reviewRows[0] ?? []);
        $closure = (array) ($closureRows[$siteId] ?? []);
        if (!$closure && isset($closureRows[0])) {
            $closure = (array) $closureRows[0];
        }
        $reviewOn = (string) ($siteReview['next_review_on']
            ?? $placeReview['next_review_on'] ?? '');
        $closureReview = (string) ($closure['review_date'] ?? '');
        $nextCheck = $reviewOn;
        if ($closureReview !== '' && ($nextCheck === '' || $closureReview < $nextCheck)) {
            $nextCheck = $closureReview;
        }
        $releaseOn = (string) ($siteReview['availability_release_on']
            ?? $placeReview['availability_release_on'] ?? '');
        $earliest = $nextCheck;
        if ($releaseOn !== '') {
            $releaseDate = substr($releaseOn, 0, 10);
            if ($earliest === '' || $releaseDate < $earliest) {
                $earliest = $releaseDate;
            }
        }
        $sites[] = [
            'id' => $siteId,
            'label' => trim((string) ($site['site_name'] ?? ''))
                ?: ('Site ' . (string) ($site['site_code'] ?? $siteId)),
            'price_min' => min($positive),
            'price_max' => max($positive),
            'season_summary' => $seasonSummary,
            'review_on' => $reviewOn,
            'release_on' => $releaseOn,
            'closure_review_on' => $closureReview,
            'next_check_on' => $earliest,
            'last_checked_on' => (string) ($siteReview['last_checked_on']
                ?? $placeReview['last_checked_on'] ?? ''),
            'source_url' => (string) ($siteReview['source_url']
                ?? $placeReview['source_url'] ?? ''),
        ];
    }
    usort($sites, static function (array $a, array $b): int {
        $at = $a['next_check_on'] ?: '9999-12-31';
        $bt = $b['next_check_on'] ?: '9999-12-31';
        return [$at, $a['label']] <=> [$bt, $b['label']];
    });
    return $sites;
}

/** Update one Site's review reminder, never its actual closure status. */
function admin_cpr_save_review(
    PDO $db, int $placeId, int $siteId, int $actorId, array $details
): array {
    if ($placeId < 1 || $siteId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('Choose a valid Place and Site.');
    }
    if (!admin_cpr_reviews_ready($db)) {
        throw new InvalidArgumentException(
            'Apply the Campsite Pricing review-table migration before scheduling reviews.'
        );
    }
    $site = llama_place_campsites($db, $placeId, $siteId)[0] ?? null;
    if (!is_array($site)) {
        throw new InvalidArgumentException('The selected Site does not belong to this Place.');
    }
    $review = llama_season_absolute_date($details['next_review_on'] ?? null);
    $releaseRaw = trim((string) ($details['availability_release_on'] ?? ''));
    $release = null;
    if ($releaseRaw !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $releaseRaw)) {
            throw new InvalidArgumentException('Enter a valid availability release date and time.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $releaseRaw);
        if (!$date || $date->format('Y-m-d\TH:i') !== $releaseRaw) {
            throw new InvalidArgumentException('Invalid availability release date.');
        }
        $release = $date->format('Y-m-d H:i:s');
    }
    $source = trim((string) ($details['source_url'] ?? ''));
    if ($source !== '' && (strlen($source) > 2048
        || !filter_var($source, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)),
            ['http', 'https'], true))) {
        throw new InvalidArgumentException('Enter a valid source URL.');
    }
    $markChecked = !empty($details['checked_today']);
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place no longer exists.');
        }
        $q = $db->prepare(
            'INSERT INTO place_pricing_reviews
              (place_id, site_id, next_review_on, availability_release_on,
               last_checked_on, source_url)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE next_review_on = VALUES(next_review_on),
               availability_release_on = VALUES(availability_release_on),
               last_checked_on = COALESCE(VALUES(last_checked_on), last_checked_on),
               source_url = VALUES(source_url)'
        );
        $q->execute([
            $placeId, $siteId, $review, $release,
            $markChecked ? date('Y-m-d') : null, $source !== '' ? $source : null,
        ]);
        admin_users_audit(
            $db, $actorId, null, 'place.site_pricing_review_updated',
            'Updated pricing review reminder for one Site.',
            ['place_id' => $placeId, 'site_id' => $siteId]
        );
        $db->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}


/** Group rates reuse the existing category / hookups / layout selectors. */
function admin_cpr_group_key(string $category, string $hookups, string $layout): string
{
    return 'cpr-group-' . $category . '-' .
        ($hookups ?: 'any') . '-' . ($layout ?: 'any');
}

/** Read all reusable campground rate groups and their actual Site coverage. */
function admin_cpr_group_catalog(PDO $db, int $placeId, array $sites): array
{
    $doc = llama_pricing_document($db, ['id' => $placeId]);
    $groups = [];
    $covered = [];
    $vehicleRows = [];
    foreach ((array) ($doc['rows'] ?? []) as $row) {
        if (str_starts_with((string) ($row['id'] ?? ''), 'cpr-vehicle-cpr-group-')) {
            $vehicleRows[substr((string) $row['id'], strlen('cpr-vehicle-'))] = $row;
        }
    }
    $contexts = llama_pricing_effective_site_contexts($db, $placeId, $sites, $doc);
    foreach ((array) ($doc['rows'] ?? []) as $row) {
        if (!str_starts_with((string) ($row['id'] ?? ''), 'cpr-group-')) {
            continue;
        }
        $match = (array) ($row['match'] ?? []);
        $matched = 0;
        foreach ($sites as $site) {
            $context = llama_pricing_site_context(
                $contexts[(int) ($site['id'] ?? 0)] ?? $site
            );
            $score = llama_pricing_match($match, $context);
            if ($score === null || $score < 0) continue;
            $matched++;
            $covered[(int) $site['id']] = true;
        }
        $groups[] = [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'category' => (string) ($match['category'] ?? ''),
            'hookups' => (string) ($match['hookups'] ?? ''),
            'layout' => (string) ($match['layout'] ?? ''),
            'matched_sites' => $matched,
            'seasons' => array_values((array) ($row['seasons'] ?? [])),
            'vehicle_seasons' => array_values((array) (
                $vehicleRows[(string) $row['id']]['seasons'] ?? []
            )),
        ];
    }
    // A rule may contain only an Extra Vehicle fee, with no nightly amount.
    foreach ($vehicleRows as $id => $vehicleRow) {
        if (in_array($id, array_column($groups, 'id'), true)) continue;
        $match = (array) ($vehicleRow['match'] ?? []);
        $matched = 0;
        foreach ($sites as $site) {
            $context = llama_pricing_site_context(
                $contexts[(int) ($site['id'] ?? 0)] ?? $site
            );
            if (($score = llama_pricing_match($match, $context)) !== null && $score >= 0) {
                $matched++;
                $covered[(int) $site['id']] = true;
            }
        }
        $groups[] = [
            'id' => $id, 'name' => (string) ($vehicleRow['name'] ?? ''),
            'category' => (string) ($match['category'] ?? ''),
            'hookups' => (string) ($match['hookups'] ?? ''),
            'layout' => (string) ($match['layout'] ?? ''),
            'matched_sites' => $matched, 'seasons' => [],
            'vehicle_seasons' => (array) ($vehicleRow['seasons'] ?? []),
        ];
    }
    return [
        'version' => hash('sha256', json_encode($doc, JSON_THROW_ON_ERROR)),
        'groups' => $groups,
        'unmatched_sites' => count($sites) - count($covered),
    ];
}

/** Return only Sites whose imported/recorded attributes definitively match. */
function admin_cpr_matching_sites(
    PDO $db, int $placeId,
    string $category, string $hookups, string $layout
): array {
    $match = [
        'category' => $category,
        'hookups' => $hookups,
        'layout' => $layout,
        'site_ids' => [], 'feature_ids' => [], 'area_ids' => [],
    ];
    $matching = [];
    $uncertain = 0;
    $sites = llama_place_campsites($db, $placeId);
    $doc = llama_pricing_document($db, ['id' => $placeId]);
    $contexts = llama_pricing_effective_site_contexts($db, $placeId, $sites, $doc);
    foreach ($sites as $site) {
        $context = $contexts[(int) ($site['id'] ?? 0)] ?? $site;
        $score = llama_pricing_match($match, llama_pricing_site_context($context));
        if ($score === -1) {
            $uncertain++;
        } elseif ($score !== null && $score >= 0) {
            $matching[] = $site;
        }
    }
    return ['sites' => $matching, 'uncertain' => $uncertain];
}

/** Save one category/utility/parking rule across matching Sites, not 300 rows. */
function admin_cpr_save_rate_group(
    PDO $db, int $placeId, int $actorId,
    string $expectedVersion, array $request
): array {
    if ($placeId < 1 || $actorId < 1 || strlen($expectedVersion) !== 64) {
        throw new InvalidArgumentException('Select a Place before saving a rate group.');
    }
    $category = (string) ($request['category'] ?? '');
    $hookups = (string) ($request['hookups'] ?? '');
    $layout = (string) ($request['layout'] ?? '');
    if (!in_array($category, ['', 'single', 'double', 'group'], true)
        || !in_array($hookups, ['', 'none', 'electric', 'full'], true)
        || !in_array($layout, ['', 'pull_through', 'back_in', 'pull_in', 'parallel'], true)) {
        throw new InvalidArgumentException('Select a valid Site size, utility type, and parking style.');
    }
    $seasonId = (int) ($request['season_id'] ?? 0);
    if ($seasonId < 1) {
        throw new InvalidArgumentException('Select an operating season for this rate.');
    }
    $rawRates = (array) ($request['rates'] ?? []);
    if (array_keys($rawRates) !== ['weekday', 'weekend', 'holiday', 'vehicle']) {
        throw new InvalidArgumentException('Enter weekday, weekend, holiday, and extra vehicle prices.');
    }
    $rates = [];
    foreach ($rawRates as $key => $raw) {
        $rates[$key] = llama_pricing_amount($raw);
    }
    if (!array_filter($rates, static fn ($v): bool => $v !== null)
        && empty($request['apply_availability'])) {
        throw new InvalidArgumentException(
            'Enter at least one rate or choose a seasonal availability status.'
        );
    }

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }
        $seasons = llama_seasons_for_place($db, $placeId);
        if (!in_array($seasonId, array_map('intval', array_column($seasons, 'id')), true)) {
            throw new InvalidArgumentException('The season does not belong to this Place.');
        }
        $doc = llama_pricing_document($db, ['id' => $placeId]);
        if (!hash_equals(
            hash('sha256', json_encode($doc, JSON_THROW_ON_ERROR)),
            $expectedVersion
        )) {
            throw new RuntimeException('Another rate was updated. Reload before saving.', 409);
        }
        if (!empty($doc['invalid'])) {
            throw new InvalidArgumentException('Review invalid Place pricing before adding groups.');
        }

        $id = admin_cpr_group_key($category, $hookups, $layout);
        $match = [
            'category' => $category, 'layout' => $layout, 'hookups' => $hookups,
            'raw_type' => '', 'legacy_category' => '', 'legacy_site' => '',
            'loop' => '', 'site_ids' => [], 'feature_ids' => [],
            'area_ids' => [], 'site_selection' => false,
        ];
        $hasNightRate = $rates['weekday'] !== null
            || $rates['weekend'] !== null || $rates['holiday'] !== null;
        $index = null;
        foreach ($doc['rows'] as $i => $row) {
            if ($row['id'] === $id) {
                $index = $i;
                break;
            }
        }
        // Updating an existing rule with blank amounts clears those prices.
        // A status-only edit never creates an empty nightly rate row.
        if ($hasNightRate || $index !== null) {
            if ($index === null) {
                $doc['rows'][] = [
                    'id' => $id,
                    'name' => ucfirst($category ?: 'All Sites') . ' · '
                        . ($hookups === '' ? 'All utilities' : ucfirst($hookups))
                        . ($layout === '' ? '' : ' · ' . $layout),
                    'match' => $match,
                    'unit' => 'night', 'price' => null,
                    'weekend_price' => null, 'holiday_price' => null,
                    'seasons' => [],
                ];
                $index = array_key_last($doc['rows']);
            }
            $row =& $doc['rows'][$index];
            if (($row['unit'] ?? '') !== 'night'
                || (array) ($row['match'] ?? []) != $match) {
                throw new InvalidArgumentException(
                    'An unrelated pricing rule already uses this identifier.'
                );
            }
            $period = [
                'season_id' => $seasonId, 'price' => $rates['weekday'],
                'weekend_price' => $rates['weekend'],
                'holiday_price' => $rates['holiday'],
            ];
            $found = false;
            foreach ($row['seasons'] as &$existing) {
                if ((int) ($existing['season_id'] ?? 0) === $seasonId) {
                    $existing = $period;
                    $found = true;
                    break;
                }
            }
            unset($existing);
            if (!$found && $hasNightRate) $row['seasons'][] = $period;
            if (!$hasNightRate) {
                $row['seasons'] = array_values(array_filter(
                    $row['seasons'],
                    static fn (array $item): bool =>
                        (int) ($item['season_id'] ?? 0) !== $seasonId
                ));
            }
            $removeEmpty = !$row['seasons']
                && ($row['price'] ?? null) === null
                && ($row['weekend_price'] ?? null) === null
                && ($row['holiday_price'] ?? null) === null;
            unset($row);
            if ($removeEmpty) {
                unset($doc['rows'][$index]);
                $doc['rows'] = array_values($doc['rows']);
            }
        }
        $doc = admin_cpr_merge_vehicle(
            $doc, 'cpr-vehicle-' . $id, $match,
            ucfirst($category ?: 'All Sites'), [
                ['season_id' => $seasonId, 'vehicle' => $rates['vehicle']],
            ]
        );
        $doc = llama_pricing_validate($doc);
        $appliedStatusCount = 0;
        if (!empty($request['apply_availability'])) {
            $status = (string) ($request['availability'] ?? '');
            if (!in_array($status, [
                'available', 'reservable', 'fcfs', 'unavailable',
            ], true)) {
                throw new InvalidArgumentException('Choose an availability status to apply to matched Sites.');
            }
            $matched = admin_cpr_matching_sites(
                $db, $placeId, $category, $hookups, $layout
            );
            if (!$matched['sites']) {
                throw new InvalidArgumentException(
                    'No Sites definitively match these imported attributes. Correct the Site facts before applying availability.'
                );
            }
            if (count($matched['sites']) > 1000) {
                throw new InvalidArgumentException(
                    'Too many Sites match one batch. Narrow the rate group first.'
                );
            }
            $modes = match ($status) {
                'unavailable' => ['site_reservation_method' => 'inherit'],
                default => ['site_reservation_method' => 'answer'],
            };
            $values = match ($status) {
                'available' => ['site_reservation_method' => 'not_reservable'],
                'reservable' => ['site_reservation_method' => 'reservable'],
                'fcfs' => ['site_reservation_method' => 'first_come'],
                default => [],
            };
            foreach ($matched['sites'] as $site) {
                llama_season_save_target_answers(
                    $db, $placeId, $seasonId,
                    ['scope' => 'site', 'source' => 'campsite_record',
                        'id' => (int) $site['id']],
                    $actorId, $modes, $values,
                    $status === 'unavailable' ? 'closed' : 'open'
                );
                $appliedStatusCount++;
            }
        }
        $encoded = json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $old = $db->prepare('SELECT place_id FROM place_rules WHERE place_id = ? FOR UPDATE');
        $old->execute([$placeId]);
        if ($old->fetchColumn()) {
            $write = $db->prepare(
                'UPDATE place_rules SET rate_cards_json = ? WHERE place_id = ?'
            );
            $write->execute([$encoded, $placeId]);
        } else {
            $write = $db->prepare(
                'INSERT INTO place_rules (place_id, rate_cards_json) VALUES (?, ?)'
            );
            $write->execute([$placeId, $encoded]);
        }
        admin_users_audit(
            $db, $actorId, null, 'place.pricing_group_updated',
            'Saved a matched Site pricing group.',
            ['place_id' => $placeId, 'rate_group_id' => $id,
                'season_id' => $seasonId, 'sites_status_updated' => $appliedStatusCount]
        );
        $db->commit();
        return ['success' => true, 'group_id' => $id,
            'sites_status_updated' => $appliedStatusCount,
            'version' => hash('sha256', json_encode($doc, JSON_THROW_ON_ERROR))];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

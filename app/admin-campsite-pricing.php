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
        ], $seasons),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    return [
        'site' => $site,
        'document' => $document,
        'seasons' => $seasons,
        'availability' => $availability,
        'rates_by_season' => $ratesBySeason,
        'rate_conflict' => $rateConflict,
        'rate' => $rate,
        'version' => $version,
    ];
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

<?php
// Only show rates whose selectors can be published at Place level.
require_once dirname(__DIR__, 2) . '/app/place-pricing.php';
$rateDoc = llama_pricing_document(db(), $placeReportSource ?? $place);
$rateSeasons = array_column($publicReportSeasons ?? [], null, 'id');
$rateGroups = ['base' => ['name' => '', 'cards' => []]];
$ratePublicSites = null;
foreach ($rateDoc['rows'] as $rate) {
    if (!empty($rate['needs_review'])) continue;
    $match = $rate['match'] ?? [];
    $scope = '';
    if (llama_pricing_row_targets_sites($rate)) {
        if (empty($match['site_ids']) || !empty($match['area_ids'])
            || !empty($match['feature_ids']) || !empty($match['legacy_site'])) continue;

        if ($ratePublicSites === null) {
            require_once dirname(__DIR__, 2) . '/app/place-campsites.php';
            $ratePlace = $placeReportSource ?? $place;
            $ratePlaceId = (int) $ratePlace['id'];
            $restricted = llama_site_restricted_visibility_ids(db(), $ratePlaceId);
            $ratePublicSites = [];
            foreach (llama_place_campsites(db(), $ratePlaceId) as $site) {
                if (llama_site_is_public($ratePlace, $site, $restricted)) {
                    $ratePublicSites[(int) $site['id']] = $site['display_name'];
                }
            }
        }
        if (array_diff($match['site_ids'], array_keys($ratePublicSites))) continue;
        $scope = count($match['site_ids']) <= 3
            ? implode(', ', array_intersect_key($ratePublicSites, array_flip($match['site_ids'])))
            : count($match['site_ids']) . ' selected Sites';
    }
    $category = ucfirst((string) ($match['category'] ?? ''));
    $conditions = array_filter([
        $category,
        match ($match['hookups'] ?? '') {
            'none' => 'No hookups', 'electric' => 'Electric', 'full' => 'Full hookups', default => '',
        },
        ucwords(str_replace('_', ' ', (string) ($match['layout'] ?? ''))),
        $match['raw_type'] ?? '',
        $match['loop'] ?? '',
    ]);
    $label = (string) ($rate['name'] ?? '');
    if ($label === '' || strcasecmp($label, 'Place base price') === 0 || strcasecmp($label, 'Camping') === 0) {
        $label = implode(' · ', $conditions) ?: 'Base Price';
    }
    // The extra vehicle charge is a surcharge per additional vehicle,
    // not part of a Site's nightly base rate.
    if (($rate['unit'] ?? '') === 'vehicle_night'
        && stripos($label, 'Extra vehicle') === false) {
        $label .= ' · Extra vehicle';
    }
    $rateGroups['base']['cards'][] = [$label, $rate, $rate['unit'], $rate['holiday_prices'] ?? [], $scope];
    foreach ($rate['seasons'] ?? [] as $period) {
        if (isset($period['season_id'])) {
            $season = $rateSeasons[$period['season_id']] ?? null;
            if ($season === null) continue;
            $key = 'season:' . $period['season_id'];
            $heading = $season['name'];
        } else {
            $key = 'legacy:' . json_encode([$period['name'], $period['start'], $period['end'], $period['recurring']]);
            $heading = ($period['name'] ?: 'Season') . ' · ' . $period['start'] . ' to ' . $period['end'];
        }
        $rateGroups[$key]['name'] = $heading;
        $rateGroups[$key]['cards'][] = [$label, $period, $rate['unit'], [], $scope];
    }
}

$renderedRates = false;
$showWeekendNights = false;
foreach ($rateGroups as $groupKey => $group):
    $cards = [];
    foreach ($group['cards'] as [$label, $prices, $unit, $events, $scope]) {
        $values = [];
        foreach (['price' => 'Weekday', 'weekend_price' => 'Weekend', 'holiday_price' => 'Holiday'] as $key => $dayType) {
            if (($prices[$key] ?? null) !== null) {
                $values[$dayType] = llama_pricing_label(['state' => 'priced', 'amount' => $prices[$key]], $unit);
            }
        }
        foreach ($rateDoc['holidays'] as $event) {
            if (($events[$event['id']] ?? null) !== null) {
                $values[$event['name'] ?: 'Holiday / event'] = llama_pricing_label(
                    ['state' => 'priced', 'amount' => $events[$event['id']]], $unit
                );
            }
        }
        if (isset($values['Weekend'])) $showWeekendNights = true;
        if ($values) $cards[] = [$label, $values, $scope, $unit];
    }
    if (!$cards) continue;
    $renderedRates = true;
    ?>
    <div class="scout-report-subsection">
        <?php if ($group['name'] !== ''): ?>
            <h4><?= $e($group['name']) ?></h4>
        <?php elseif (count($rateGroups) > 1): ?>
            <h4>Base schedule</h4>
        <?php endif; ?>
        <div class="scout-report-grid">
            <?php foreach ($cards as [$label, $values, $scope, $unit]): ?>
                <article class="scout-report-item scout-report-value-item">
                    <div class="scout-report-value-content">
                        <span><?= $e($label) ?></span>
                        <?php if ($scope !== ''): ?>
                            <small><?= $e($scope) ?></small>
                        <?php endif; ?>
                        <?php foreach ($values as $dayType => $amount): ?>
                            <?php $dayLabel = $unit === 'vehicle_night' && $dayType === 'Weekday'
                                ? 'Per extra vehicle' : $dayType; ?>
                            <strong><?= $e(
                                count($values) === 1 && $dayLabel === 'Weekday'
                                    ? $amount : $dayLabel . ' · ' . $amount
                            ) ?></strong>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($showWeekendNights): ?>
    <?php $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>
    <p>Weekend nights: <?= $e(implode(', ', array_map(static fn ($day) => $dayNames[$day], $rateDoc['weekend_days'])) ?: 'None') ?></p>
<?php elseif (!$renderedRates): ?>
    <p>No campsite rates have been published in Campsite Pricing.</p>
<?php endif; ?>

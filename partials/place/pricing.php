<?php
// Caller has already authorized the Place. Individual/Area selectors never
// appear in the Place-wide table; their prices are resolved on authorized Sites.
require_once dirname(__DIR__, 2) . '/app/place-pricing.php';
$publishedPricing = llama_pricing_document(db(), $place);
$publicPriceRows = array_values(array_filter($publishedPricing['rows'], static function ($row): bool {
    $m = $row['match'] ?? [];
    return empty($m['site_ids']) && empty($m['feature_ids']) && empty($m['area_ids']) && empty($m['legacy_site']) && empty($row['needs_review']);
}));
?>
<?php if ($publicPriceRows): ?>
<section class="place-section" aria-labelledby="place-pricing-heading">
    <h2 id="place-pricing-heading">Campsite rates</h2>
    <p>Published USD rates. Additional fees may apply. Confirm current prices with the operator.</p>
    <?php foreach ($publicPriceRows as $rate): ?>
        <details>
            <summary><?= place_h(($rate['name'] ?: 'Camping') . ' · ' . llama_pricing_label(llama_pricing_row_price($publishedPricing, $rate, null), $rate['unit'])) ?></summary>
            <dl>
                <?php foreach (['price' => 'Base', 'weekend_price' => 'Weekend', 'holiday_price' => 'Holiday / event'] as $key => $label): ?>
                    <?php if (($rate[$key] ?? null) !== null): ?>
                        <dt><?= place_h($label) ?></dt><dd><?= place_h(llama_pricing_label(['state' => 'priced', 'amount' => $rate[$key]], $rate['unit'])) ?></dd>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach ($publishedPricing['holidays'] as $holiday): ?>
                    <?php if (($rate['holiday_prices'][$holiday['id']] ?? null) !== null): ?>
                        <dt><?= place_h($holiday['name'] ?: 'Holiday / event') ?></dt>
                        <dd><?= place_h(llama_pricing_label(['state' => 'priced', 'amount' => $rate['holiday_prices'][$holiday['id']]], $rate['unit'])) ?></dd>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach ($rate['seasons'] ?? [] as $period): ?>
                    <dt><?= place_h(($period['name'] ?: 'Season') . ': ' . $period['start'] . ' to ' . $period['end'] . (!empty($period['recurring']) ? ' annually' : '')) ?></dt>
                    <dd><?php
                    $parts = [];
                    foreach (['price' => 'Base', 'weekend_price' => 'Weekend', 'holiday_price' => 'Holiday'] as $key => $label) {
                        if (($period[$key] ?? null) !== null) $parts[] = $label . ': ' . llama_pricing_label(['state' => 'priced', 'amount' => $period[$key]], $rate['unit']);
                    }
                    echo place_h(implode(' · ', $parts) ?: 'Uses base schedule');
                    ?></dd>
                <?php endforeach; ?>
            </dl>
        </details>
    <?php endforeach; ?>
    <p>Weekend nights: <?= place_h(implode(', ', array_map(static fn($d) => ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$d], $publishedPricing['weekend_days'])) ?: 'None') ?></p>
    <?php if ($publishedPricing['holidays']): ?>
        <p>Holiday nights: <?= place_h(implode('; ', array_map(static fn($p) => $p['name'] . ': ' . $p['start'] . ' to ' . $p['end'], $publishedPricing['holidays']))) ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>

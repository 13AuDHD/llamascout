<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-map-features.php';

$stayFeatures = [];
try {
    $stayFeatures = llama_place_map_features(db(), (int) ($place['id'] ?? 0), true);
} catch (Throwable $exception) {
    error_log('Llama Scout stay options: ' . $exception->getMessage());
}


// Map-drawn campsite polygons belong to place_map_features, not necessarily
// place_campsites. Include them even on Travel Center / parking Places.
$stayMappedSites = [];
foreach ($stayFeatures as $feature) {
    if ((string) ($feature['feature_type'] ?? '') !== 'camping_site') {
        continue;
    }
    $id = (int) ($feature['id'] ?? 0);
    if ($id < 1) {
        continue;
    }
    $details = (array) ($feature['site_details'] ?? []);
    $code = trim((string) ($details['site_code'] ?? ''));
    $name = trim((string) ($feature['label'] ?? ''));
    $label = $code !== '' ? $code : ($name !== '' ? $name : 'Campsite ' . $id);
    $style = trim((string) ($details['parking_style'] ?? ''));
    $type = trim((string) ($details['site_type'] ?? ''));
    $description = implode(' Â· ', array_filter([
        $type !== '' ? ucwords(str_replace('_', ' ', $type)) : 'Campsite',
        $style !== '' ? ucwords(str_replace('_', ' ', $style)) : '',
    ]));
    $stayMappedSites[] = [
        'id' => $id,
        'name' => $label,
        'summary' => $description,
        'area' => trim((string) ($feature['parent_area_label'] ?? '')),
    ];
}

$stayParking = [];
foreach ($stayFeatures as $feature) {
    if (($feature['feature_type'] ?? '') !== 'parking_area') {
        continue;
    }
    $details = (array) ($feature['area_details'] ?? []);
    $use = (string) ($details['area_use'] ?? '');
    $overnight = (string) ($details['overnight_status'] ?? '');
    if ($use !== 'overnight_vehicle_parking' && $overnight === '') {
        continue;
    }
    // Explicitly prohibited parking is not an overnight option.
    if ($overnight === 'prohibited') {
        continue;
    }
    $id = (int) ($feature['id'] ?? 0);
    if ($id < 1) {
        continue;
    }
    $name = trim((string) ($feature['label'] ?? ''));
    if ($name === '') {
        $name = $use === 'overnight_vehicle_parking'
            ? 'Overnight vehicle parking'
            : 'Parking area';
    }
    $fee = (string) ($details['fee_status'] ?? '');
    $feeLabel = match ($fee) {
        'paid' => 'Paid',
        'free' => 'Free',
        'varies' => 'Cost varies',
        default => 'Cost unknown',
    };
    $statusLabel = match ($overnight) {
        'allowed' => 'Overnight allowed',
        'varies' => 'Rules vary',
        default => 'Overnight status unknown',
    };
    $stayParking[] = [
        'id' => $id,
        'name' => $name,
        'summary' => $feeLabel . ' Â· ' . $statusLabel,
        'cost' => $feeLabel,
        'status' => $statusLabel,
    ];
}
?>
<script src="/js/place-campsite-horizontal-scroll.js"></script>
<link rel="stylesheet" href="/css/site/features/place-overnight-options.css">
<section class="place-section place-overnight-options" data-stay-options aria-labelledby="place-overnight-options-heading">
    <header class="place-overnight-options-heading">
        <div>
            <p class="eyebrow">Overnight options</p>
            <h2 id="place-overnight-options-heading">Where you can stay</h2>
            <p class="place-stay-intro">Browse campsites and overnight parking areas. Select an area to see its details in the Scout Report.</p>
        </div>
        <a class="place-overnight-options-map-link" href="#place-map-heading">
            <?= llama_icon('map') ?> View on map
        </a>
    </header>
    <div data-stay-browser-slot></div>
    <div class="place-stay-parking-list" data-stay-parking-list aria-label="Mapped campsites and overnight parking areas" <?= ($stayParking || $stayMappedSites) ? '' : 'hidden' ?>>
        <?php foreach ($stayMappedSites as $site): ?>
            <button type="button" class="place-campsite-row place-stay-map-site-row"
                data-stay-map-site-id="<?= (int) $site['id'] ?>"
                data-stay-map-site-name="<?= place_h($site['name']) ?>"
                data-stay-map-site-area="<?= place_h($site['area']) ?>"
                data-stay-map-site-summary="<?= place_h($site['summary']) ?>">
                <span class="place-campsite-row-name"><?= place_h($site['name']) ?></span>
                <span class="place-campsite-row-summary"><?= place_h($site['summary']) ?></span>
            </button>
        <?php endforeach; ?>
        <?php foreach ($stayParking as $parking): ?>
            <button type="button" class="place-campsite-row place-stay-parking-row"
                data-stay-parking-id="<?= (int) $parking['id'] ?>"
                data-stay-parking-name="<?= place_h($parking['name']) ?>"
                data-stay-parking-cost="<?= place_h($parking['cost']) ?>"
                data-stay-parking-status="<?= place_h($parking['status']) ?>">
                <span class="place-campsite-row-name"><?= place_h($parking['name']) ?></span>
                <span class="place-campsite-row-summary"><?= place_h($parking['summary']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</section>
<script src="/js/place-overnight-options.js" defer></script>
<script src="/js/place-stay-selection-map.js" defer></script>

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-map-features.php';

$stayFeatures = [];
try {
    $stayFeatures = llama_place_map_features(db(), (int) ($place['id'] ?? 0), true);
} catch (Throwable $exception) {
    error_log('Llama Scout stay options: ' . $exception->getMessage());
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
        'summary' => $feeLabel . ' · ' . $statusLabel,
        'cost' => $feeLabel,
        'status' => $statusLabel,
    ];
}
?>
<link rel="stylesheet" href="/css/site/features/place-overnight-options.css">
<section class="place-section place-overnight-options" data-stay-options aria-labelledby="place-overnight-options-heading">
    <header class="place-overnight-options-heading">
        <div>
            <p class="eyebrow">Overnight options</p>
            <h2 id="place-overnight-options-heading">Where you can stay</h2>
            <p class="place-stay-intro">Browse campsites and overnight parking areas. Select a campsite to see its details in the Scout Report.</p>
        </div>
        <a class="place-overnight-options-map-link" href="#place-map-heading">
            <?= llama_icon('map') ?> View on map
        </a>
    </header>
    <div data-stay-browser-slot></div>
    <div class="place-stay-parking-list" data-stay-parking-list aria-label="Overnight parking areas" <?= $stayParking ? '' : 'hidden' ?>>
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
    <div class="place-stay-selection" data-stay-selection hidden aria-live="polite"></div>
    <?php if (!empty($placeMapCanEditAreas)): ?>
        <p class="place-overnight-options-editor-note">
            <a href="/place-map-editor.php?slug=<?= rawurlencode((string) ($place['slug'] ?? '')) ?>">Edit mapped areas</a>
        </p>
    <?php endif; ?>
</section>
<script src="/js/place-overnight-options.js" defer></script>

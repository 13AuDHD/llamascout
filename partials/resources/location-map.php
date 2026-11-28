<?php
declare(strict_types=1);

$resourceMapEscape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$resourceMapName = trim((string) ($resource['name'] ?? '')) ?: 'Resource';
$resourceMapLocation = implode(', ', $locationParts);
$resourceMapPhoto = llama_resource_image_url($heroImage['src'] ?? '');
$resourceMapCoordinateStatus = llama_coordinate_status(
    $resource['latitude'] ?? null,
    $resource['longitude'] ?? null,
    $resource['verified_at'] ?? null
);
?>
<section class="place-map-section resource-map-section resource-detail-section" aria-labelledby="resource-map-heading">
    <div class="place-map-heading">
        <div>
            <p class="place-detail-eyebrow">Map</p>
            <h2 id="resource-map-heading">Explore around this Resource</h2>
        </div>
        <?php if ($resourceMapCoordinateStatus !== 'missing'): ?>
            <div class="place-map-heading-actions">
                <span class="place-map-precision">Stored Resource location<?= $resourceMapCoordinateStatus === 'unverified' ? ' · Verification pending' : '' ?></span>
                <button type="button" class="place-map-edit-areas" data-resource-map-local>Zoom to Resource</button>
                <button type="button" class="place-map-edit-areas" data-resource-map-reset>Reset view</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($resourceMapCoordinateStatus === 'missing'): ?>
        <p class="place-map-note">This Resource does not have usable stored coordinates yet.</p>
    <?php else: ?>
        <div class="place-map-card resource-location-map-card" data-resource-map-card>
            <div class="place-inline-map" id="resource-location-map"
                role="region" aria-label="<?= $resourceMapEscape('Map showing ' . $resourceMapName) ?>"
                data-latitude="<?= $resourceMapEscape($resource['latitude']) ?>"
                data-longitude="<?= $resourceMapEscape($resource['longitude']) ?>"
                data-name="<?= $resourceMapEscape($resourceMapName) ?>"
                data-initial-zoom="3"></div>
            <template data-resource-map-popup>
                <article class="place-explorer-map-card resource-map-popup-card">
                    <?php if ($resourceMapPhoto !== ''): ?>
                        <img src="<?= $resourceMapEscape($resourceMapPhoto) ?>" alt="">
                    <?php endif; ?>
                    <div class="place-explorer-map-card-body">
                        <span><?= $resourceMapEscape($typeLabel) ?> · <?= $resourceMapEscape($statusLabel) ?></span>
                        <strong><?= $resourceMapEscape($resourceMapName) ?></strong>
                        <?php if ($resourceMapLocation !== ''): ?><span><?= $resourceMapEscape($resourceMapLocation) ?></span><?php endif; ?>
                        <?php if (!empty($resource['street_address'])): ?><span><?= $resourceMapEscape($resource['street_address']) ?></span><?php endif; ?>
                        <?php if (!empty($resource['hours_text'])): ?><span><?= $resourceMapEscape($resource['hours_text']) ?></span><?php endif; ?>
                    </div>
                </article>
            </template>
        </div>
        <p class="place-map-note" data-resource-map-status>Zoom or pan to see the surrounding area. Select the teal pin for Resource details.</p>
        <noscript><p class="place-map-note">Enable JavaScript to use the interactive map.</p></noscript>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
        <script src="/js/resource-location-map.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/js/resource-location-map.js') ?>" defer></script>
    <?php endif; ?>
</section>

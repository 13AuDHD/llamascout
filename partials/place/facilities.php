<?php

declare(strict_types=1);

// A Facility is a Place-owned Resource-style record. The Place page has
// already passed server-side visibility checks before this partial runs.
require_once dirname(__DIR__, 2) . '/app/resources.php';

$parentPlaceId = (int) ($place['id'] ?? 0);
$placeFacilities = llama_place_facilities($db, $parentPlaceId);
if (!$placeFacilities) {
    return;
}
?>
<link rel="stylesheet" href="/css/site/features/place-linked-facilities.css">
<section class="place-section place-linked-facilities" aria-labelledby="place-linked-facilities-heading">
    <header class="scout-report-header">
        <div>
            <p class="eyebrow">Inside this Place</p>
            <h2 id="place-linked-facilities-heading">Facilities</h2>
        </div>
    </header>
    <div class="place-linked-facilities-grid">
        <?php foreach ($placeFacilities as $facility): ?>
            <?php
            $facilityServices = array_values(array_filter(
                (array) ($facility['services'] ?? []),
                static fn (mixed $service): bool => is_array($service)
                    && !empty($service['is_active'])
            ));
            $facilityHero = (string) ($facility['featured_image']['src'] ?? '');
            ?>
            <article class="place-linked-facility">
                <?php if ($facilityHero !== ''): ?>
                    <img src="<?= place_h(llama_resource_image_url($facilityHero)) ?>"
                        alt="<?= place_h((string) ($facility['name'] ?? 'Facility')) ?>"
                        loading="lazy">
                <?php endif; ?>
                <div class="place-linked-facility-content">
                    <h3><?= place_h((string) $facility['name']) ?></h3>
                    <p><?= place_h(llama_resource_type_label((string) ($facility['resource_type'] ?? ''))) ?></p>
                    <?php if ($facilityServices): ?>
                        <p><?= place_h(implode(' · ', array_map(
                            static fn(array $service): string =>
                                llama_resource_service_type_label((string) ($service['service_type'] ?? '')),
                            $facilityServices
                        ))) ?></p>
                    <?php endif; ?>
                    <a href="/resource.php?slug=<?= rawurlencode((string) $facility['slug']) ?>">
                        View Facility details
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

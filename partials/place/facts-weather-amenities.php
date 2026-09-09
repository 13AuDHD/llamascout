<section class="place-facts" aria-label="Place details">
    <?php if (!empty($place['elevation_feet'])): ?>
        <div class="place-fact">
            <i class="fa-solid fa-mountain" aria-hidden="true"></i>
            <span>Elevation</span>
            <strong><?= number_format((int) $place['elevation_feet']) ?> ft</strong>
        </div>
    <?php endif; ?>

    <?php if ($hasMemberAccess && !empty($place['road'])): ?>
        <div class="place-fact">
            <i class="fa-solid fa-road" aria-hidden="true"></i>
            <span>Road</span>
            <strong><?= place_h($place['road']) ?></strong>
        </div>
    <?php endif; ?>

    <?php if (
        $hasMemberAccess
        && ($place['latitude'] ?? null) !== null
        && ($place['longitude'] ?? null) !== null
    ): ?>
        <div class="place-fact">
            <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
            <span>GPS coordinates</span>
            <strong>
                <?= place_h($place['latitude']) ?>,
                <?= place_h($place['longitude']) ?>
            </strong>
        </div>
    <?php endif; ?>
</section>

<section
    class="place-section place-weather"
    aria-labelledby="weather-heading"
    data-place-weather
    data-place-slug="<?= place_h($place['slug']) ?>"
>
    <div class="place-weather-heading">
        <div>
            <p class="eyebrow">Weather</p>
            <h2 id="weather-heading">
                <?= $hasMemberAccess ? 'Campsite weather' : 'Local weather' ?>
            </h2>
        </div>
        <i class="fa-solid fa-cloud-sun place-weather-heading-icon" aria-hidden="true"></i>
    </div>

    <div class="place-weather-content" data-place-weather-content aria-live="polite">
        <p class="place-weather-loading">Loading weather…</p>
    </div>
</section>

<?php if (!empty($place['amenities'])): ?>
    <section class="place-section">
        <h2>Amenities</h2>
        <div class="amenity-grid">
            <?php foreach ($amenityLabels as $key => [$icon, $label]): ?>
                <?php
                $value = $place['amenities'][$key] ?? null;
                if ($value === null) continue;
                ?>
                <div class="amenity-item <?= $value ? 'is-available' : 'is-unavailable' ?>">
                    <i class="fa-solid <?= place_h($icon) ?>" aria-hidden="true"></i>
                    <span><?= place_h($label) ?></span>
                    <strong><?= $value ? 'Yes' : 'No' ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

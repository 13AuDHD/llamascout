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
        <p class="place-weather-loading">Loading weatherâ¦</p>
    </div>
</section>

<?php
/*
 * Amenities are public information on Llama Scout.
 *
 * "No amenities" is stored as the existing
 * place_details.warning_no_amenities flag. Read only that
 * single public-safe fact here. Other place_details data stays
 * behind the normal member-access path.
 */
$placeReportsNoAmenities = false;

try {
    $noAmenitiesStmt =
        db()->prepare(
            'SELECT warning_no_amenities
             FROM place_details
             WHERE place_id = ?
             LIMIT 1'
        );

    $noAmenitiesStmt->execute([
        (int) ($place['id'] ?? 0),
    ]);

    $noAmenitiesValue =
        $noAmenitiesStmt->fetchColumn();

    $placeReportsNoAmenities =
        $noAmenitiesValue !== false
        && (int) $noAmenitiesValue === 1;

} catch (Throwable) {
    $placeReportsNoAmenities = false;
}
?>

<?php if ($placeReportsNoAmenities): ?>

    <section class="place-section">
        <h2>Amenities</h2>

        <div class="amenity-grid">
            <div class="amenity-item is-unavailable">
                <i
                    class="fa-solid fa-circle-xmark"
                    aria-hidden="true"
                ></i>

                <span>No amenities</span>
                <strong>Reported</strong>
            </div>
        </div>
    </section>

<?php elseif (!empty($place['amenities'])): ?>

    <?php
    $availableAmenities = [];

    foreach ($amenityLabels as $key => [$icon, $label]) {
        if (!empty($place['amenities'][$key])) {
            $availableAmenities[$key] = [
                $icon,
                $label,
            ];
        }
    }
    ?>

    <?php if ($availableAmenities): ?>
        <section class="place-section">
            <h2>Amenities</h2>

            <div class="amenity-grid">
                <?php foreach ($availableAmenities as $key => [$icon, $label]): ?>
                    <div class="amenity-item is-available">
                        <i
                            class="fa-solid <?= place_h($icon) ?>"
                            aria-hidden="true"
                        ></i>

                        <span><?= place_h($label) ?></span>
                        <strong>Yes</strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

<?php endif; ?>

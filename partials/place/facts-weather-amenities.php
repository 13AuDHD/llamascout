<?php

$hasExactPlaceCoordinates =
    $hasMemberAccess
    && ($place['latitude'] ?? null) !== null
    && ($place['longitude'] ?? null) !== null
    && is_numeric($place['latitude'])
    && is_numeric($place['longitude']);

$placeLatitude =
    $hasExactPlaceCoordinates
        ? trim((string) $place['latitude'])
        : '';

$placeLongitude =
    $hasExactPlaceCoordinates
        ? trim((string) $place['longitude'])
        : '';

$placeCoordinateText =
    $hasExactPlaceCoordinates
        ? $placeLatitude . ', ' . $placeLongitude
        : '';

$placeRoad =
    trim(
        (string) (
            $place['road']
            ?? ''
        )
    );

if (
    $hasMemberAccess
    && $placeRoad === ''
    && !empty($place['id'])
) {
    try {
        $roadStmt =
            db()->prepare(
                'SELECT official_address_1
                 FROM place_facility_facts
                 WHERE place_id = ?
                 LIMIT 1'
            );

        $roadStmt->execute([
            (int) $place['id'],
        ]);

        $fallbackRoad =
            trim(
                (string) (
                    $roadStmt->fetchColumn()
                    ?: ''
                )
            );

        if ($fallbackRoad !== '') {
            $placeRoad =
                $fallbackRoad;
        }
    } catch (Throwable) {
        $placeRoad = '';
    }
}

$placeElevation =
    isset($place['elevation_feet'])
    && is_numeric($place['elevation_feet'])
        ? (int) $place['elevation_feet']
        : null;

$googleMapsUrl =
    $hasExactPlaceCoordinates
        ? 'https://www.google.com/maps/dir/?api=1&destination='
            . rawurlencode($placeLatitude . ',' . $placeLongitude)
        : '';

$appleMapsUrl =
    $hasExactPlaceCoordinates
        ? 'https://maps.apple.com/?daddr='
            . rawurlencode($placeLatitude . ',' . $placeLongitude)
        : '';

$onxMapsUrl =
    $hasExactPlaceCoordinates
        ? 'https://webmap.onxmaps.com/offroad/map/query/'
            . rawurlencode($placeLatitude)
            . ','
            . rawurlencode($placeLongitude)
            . ',14/overview?mode=dirt'
        : '';
?>

<section
    class="place-facts<?= $hasMemberAccess ? ' place-facts--three' : '' ?>"
    aria-label="Place details"
>
    <?php if ($hasMemberAccess || $placeElevation !== null): ?>
        <div class="place-fact">
            <i aria-hidden="true">
                <?= llama_icon('mountain') ?>
            </i>

            <span>Elevation</span>

            <strong>
                <?= $placeElevation !== null
                    ? number_format($placeElevation) . ' ft'
                    : 'Unknown' ?>
            </strong>
        </div>
    <?php endif; ?>

    <?php if ($hasMemberAccess): ?>
        <div class="place-fact<?= $hasExactPlaceCoordinates ? ' place-fact--with-action' : '' ?>">
            <i aria-hidden="true">
                <?= llama_icon('road') ?>
            </i>

            <span>Street / road</span>

            <strong>
                <?= $placeRoad !== ''
                    ? place_h($placeRoad)
                    : 'Unknown' ?>
            </strong>

            <?php if ($hasExactPlaceCoordinates): ?>
                <div
                    class="place-fact-action place-navigation"
                    data-place-navigation
                >
                    <button
                        type="button"
                        class="place-fact-icon-button"
                        data-place-navigation-toggle
                        aria-expanded="false"
                        aria-controls="place-navigation-menu"
                        aria-label="Open navigation options"
                        title="Open navigation options"
                    >
                        <?= llama_icon('directions') ?>
                    </button>

                    <div
                        id="place-navigation-menu"
                        class="place-navigation-menu"
                        data-place-navigation-menu
                        hidden
                    >
                        <p class="place-navigation-heading">
                            Navigate with
                        </p>

                        <a
                            href="<?= place_h($googleMapsUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span
                                class="place-navigation-brand"
                                aria-hidden="true"
                            >
                                <?= llama_icon('brand-google-maps') ?>
                            </span>

                            <span>
                                <strong>Google Maps</strong>
                                <small>Directions to this Place</small>
                            </span>

                            <?= llama_icon(
                                'external-link',
                                [
                                    'class' =>
                                        'place-navigation-external',
                                ]
                            ) ?>
                        </a>

                        <a
                            href="<?= place_h($appleMapsUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span
                                class="place-navigation-brand"
                                aria-hidden="true"
                            >
                                <?= llama_icon('brand-apple-filled') ?>
                            </span>

                            <span>
                                <strong>Apple Maps</strong>
                                <small>Directions to this Place</small>
                            </span>

                            <?= llama_icon(
                                'external-link',
                                [
                                    'class' =>
                                        'place-navigation-external',
                                ]
                            ) ?>
                        </a>

                        <a
                            href="<?= place_h($onxMapsUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span
                                class="place-navigation-brand place-navigation-brand--onx"
                                aria-hidden="true"
                            >
                                <?= llama_icon('brand-onx') ?>
                            </span>

                            <span>
                                <strong>onX Offroad</strong>
                                <small>Open this location in onX</small>
                            </span>

                            <?= llama_icon(
                                'external-link',
                                [
                                    'class' =>
                                        'place-navigation-external',
                                ]
                            ) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="place-fact place-fact--with-action">
            <i aria-hidden="true">
                <?= llama_icon('current-location') ?>
            </i>

            <span>GPS coordinates</span>

            <strong>
                <?= $hasExactPlaceCoordinates
                    ? place_h($placeCoordinateText)
                    : 'Unknown' ?>
            </strong>

            <?php if ($hasExactPlaceCoordinates): ?>
                <div class="place-fact-action place-coordinate-copy">
                    <button
                        type="button"
                        class="place-fact-icon-button"
                        data-copy-place-coordinates="<?= place_h($placeCoordinateText) ?>"
                        aria-label="Copy GPS coordinates"
                        title="Copy GPS coordinates"
                    >
                        <?= llama_icon('copy') ?>
                    </button>

                    <span
                        class="place-copy-feedback"
                        data-place-copy-feedback
                        role="status"
                        aria-live="polite"
                        hidden
                    >
                        Copied
                    </span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php
require __DIR__ . '/map.php';
?>

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
                <?= $hasMemberAccess
                    ? 'Campsite weather'
                    : 'Local weather' ?>
            </h2>
        </div>

        <div class="place-weather-heading-actions">
            <div
                class="place-weather-unit-control"
                data-weather-unit-control
                aria-label="Weather units"
            >
                <span
                    class="place-weather-unit-label"
                    data-weather-unit-label="F"
                    aria-hidden="true"
                >°F</span>

                <button
                    type="button"
                    class="place-weather-unit-toggle"
                    data-weather-unit-toggle
                    role="switch"
                    aria-checked="false"
                    aria-label="Use Celsius"
                >
                    <span
                        class="place-weather-unit-toggle-thumb"
                        aria-hidden="true"
                    ></span>
                </button>

                <span
                    class="place-weather-unit-label"
                    data-weather-unit-label="C"
                    aria-hidden="true"
                >°C</span>
            </div>

            <i aria-hidden="true">
                <?= llama_icon(
                    'temperature-sun',
                    [
                        'class' =>
                            'place-weather-heading-icon',
                    ]
                ) ?>
            </i>
        </div>
    </div>

    <div
        class="place-weather-content"
        data-place-weather-content
        aria-live="polite"
    >
        <p class="place-weather-loading">
            Loading weather:
        </p>
    </div>
</section>

<?php

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

$hasAmenityRecord =
    !empty($place['amenities']);

?>

<?php if (
    $placeReportsNoAmenities
    || $hasAmenityRecord
): ?>
    <section class="place-section">
        <h2>Amenities</h2>

        <div class="amenity-grid">
            <?php foreach (
                $amenityLabels
                as $key => [$icon, $label]
            ): ?>
                <?php

                $available =
                    !$placeReportsNoAmenities
                    && !empty(
                        $place['amenities'][$key]
                    );

                ?>

                <div
                    class="amenity-item <?= $available
                        ? 'is-available'
                        : 'is-unavailable' ?>"
                >
                    <i aria-hidden="true">
                        <?= llama_icon($icon) ?>
                    </i>

                    <span>
                        <?= place_h($label) ?>
                    </span>

                    <strong>
                        <?= $available
                            ? 'Yes'
                            : 'No' ?>
                    </strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<script src="/js/place-actions.js"></script>

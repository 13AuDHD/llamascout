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
    class="place-section place-weather"
    aria-labelledby="weather-heading"
    data-place-weather
    data-place-slug="<?= place_h($place['slug']) ?>"
>
    <div class="place-weather-heading">
        <div>
            <p class="eyebrow">Weather</p>
            <h2 id="weather-heading">
                <?= place_h(trim((string) ($place['name'] ?? 'Place')) . ' weather') ?>
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

<?php require __DIR__ . '/map.php'; ?>

<script src="/js/place-actions.js"></script>

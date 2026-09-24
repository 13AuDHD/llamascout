<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/moderation.php';
require_once dirname(__DIR__) . '/app/place-report.php';

$adminUser = moderation_require_admin();
$db = db();

$submissionId = (int) ($_GET['id'] ?? 0);

header('Cache-Control: private, no-store, max-age=0');

$item = $submissionId > 0
    ? moderation_submission($db, $submissionId)
    : null;

$data = is_array($item['data'] ?? null)
    ? $item['data']
    : [];

$fields = llama_place_report_fields();

$latitudeField = $fields['latitude'] ?? [];
$longitudeField = $fields['longitude'] ?? [];

$latitudeRaw = llama_place_report_get_path(
    $data,
    (string) ($latitudeField['storage'] ?? 'latitude')
);

$longitudeRaw = llama_place_report_get_path(
    $data,
    (string) ($longitudeField['storage'] ?? 'longitude')
);

$latitude = is_numeric($latitudeRaw)
    ? (float) $latitudeRaw
    : null;

$longitude = is_numeric($longitudeRaw)
    ? (float) $longitudeRaw
    : null;

$validCoordinates =
    $latitude !== null
    && $longitude !== null
    && $latitude >= -90
    && $latitude <= 90
    && $longitude >= -180
    && $longitude <= 180;

$nearbyPlaces = [];

if ($validCoordinates) {
    /*
     * This is a duplicate-inspection map, not a regional explorer.
     * One mile keeps the view focused on Places close enough to be
     * mistaken for the submitted location.
     */
    $radiusMiles = 1.0;

    $latitudeDelta = $radiusMiles / 69.0;

    $cosLatitude = max(
        0.2,
        abs(cos(deg2rad((float) $latitude)))
    );

    $longitudeDelta =
        $radiusMiles
        / (69.172 * $cosLatitude);

    $sql = <<<'SQL'
SELECT
    p.id,
    p.slug,
    p.name,
    p.type,
    p.status,
    p.latitude,
    p.longitude,
    (
        3958.7613
        * 2
        * ASIN(
            SQRT(
                POWER(
                    SIN(
                        RADIANS(p.latitude - ?) / 2
                    ),
                    2
                )
                +
                COS(RADIANS(?))
                * COS(RADIANS(p.latitude))
                * POWER(
                    SIN(
                        RADIANS(p.longitude - ?) / 2
                    ),
                    2
                )
            )
        )
    ) AS distance_miles
FROM places p
WHERE p.status IN ('active', 'featured')
  AND p.latitude IS NOT NULL
  AND p.longitude IS NOT NULL
  AND p.latitude BETWEEN ? AND ?
  AND p.longitude BETWEEN ? AND ?
HAVING distance_miles <= ?
ORDER BY distance_miles ASC, p.name ASC
LIMIT 50
SQL;

    $stmt = $db->prepare($sql);

    $stmt->execute([
        $latitude,
        $latitude,
        $longitude,
        $latitude - $latitudeDelta,
        $latitude + $latitudeDelta,
        $longitude - $longitudeDelta,
        $longitude + $longitudeDelta,
        $radiusMiles,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (
            !is_numeric($row['latitude'] ?? null)
            || !is_numeric($row['longitude'] ?? null)
        ) {
            continue;
        }

        $nearbyPlaces[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => trim((string) ($row['name'] ?? 'Published Place')),
            'type' => trim((string) ($row['type'] ?? '')),
            'status' => (string) ($row['status'] ?? 'active'),
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'distance_miles' => max(
                0.0,
                (float) ($row['distance_miles'] ?? 0)
            ),
        ];
    }
}

/*
 * Reuse the Geoapify key already used by Llama Scout's member map.
 * This page is Admin-only, so exact Places and private map tiles never
 * enter a public response.
 */
$config = llama_config();

$geoapifyCandidates = [
    $config['geoapify']['api_key'] ?? null,
    $config['geoapify']['key'] ?? null,
    $config['geoapify_api_key'] ?? null,
    $config['services']['geoapify']['api_key'] ?? null,
    $config['services']['geoapify']['key'] ?? null,
    $config['apis']['geoapify']['api_key'] ?? null,
    $config['apis']['geoapify']['key'] ?? null,
];

$geoapifyKey = '';

foreach ($geoapifyCandidates as $candidate) {
    $candidate = trim((string) $candidate);

    if ($candidate !== '') {
        $geoapifyKey = $candidate;
        break;
    }
}

$encodedGeoapifyKey = rawurlencode($geoapifyKey);

$lightTiles = $geoapifyKey !== ''
    ? 'https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png'
        . '?apiKey=' . $encodedGeoapifyKey
    : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

$darkTiles = $geoapifyKey !== ''
    ? 'https://maps.geoapify.com/v1/tile/dark-matter/{z}/{x}/{y}.png'
        . '?apiKey=' . $encodedGeoapifyKey
    : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

$payload = [
    'latitude' => $latitude,
    'longitude' => $longitude,
    'valid_coordinates' => $validCoordinates,
    'nearby_places' => $nearbyPlaces,
    'light_tiles' => $lightTiles,
    'dark_tiles' => $darkTiles,
    'satellite_tiles' =>
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
];

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex,nofollow,noarchive"
    >

    <title>Nearby Place Check</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        :root {
            color-scheme: light dark;
            --surface: #ffffff;
            --text: #171717;
            --muted: #666666;
            --border: rgba(0, 0, 0, .18);
            --floating: rgba(255, 255, 255, .93);
            --shadow: rgba(0, 0, 0, .18);
            --submitted: #e0aa22;
            --published: #2f7dd1;
        }

        html[data-theme="dark"] {
            --surface: #151515;
            --text: #f5f5f5;
            --muted: #b7b7b7;
            --border: rgba(255, 255, 255, .20);
            --floating: rgba(25, 25, 25, .93);
            --shadow: rgba(0, 0, 0, .42);
            --submitted: #f0bd35;
            --published: #68a8eb;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            background: var(--surface);
            color: var(--text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .map-shell {
            position: relative;
            width: 100%;
            height: 100%;
        }

        #nearby-map {
            width: 100%;
            height: 100%;
            background: var(--surface);
        }

        .map-style {
            position: absolute;
            z-index: 800;
            top: 10px;
            right: 10px;
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--floating);
            box-shadow: 0 3px 12px var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .map-style button {
            min-height: 34px;
            padding: 6px 10px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--text);
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .map-style button.is-active {
            background: var(--text);
            color: var(--surface);
        }

        .map-status {
            position: absolute;
            z-index: 800;
            left: 10px;
            bottom: 10px;
            max-width: calc(100% - 90px);
            padding: 7px 9px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--floating);
            color: var(--text);
            box-shadow: 0 3px 12px var(--shadow);
            font-size: 11px;
            font-weight: 750;
            line-height: 1.35;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .map-empty {
            height: 100%;
            display: grid;
            place-items: center;
            padding: 20px;
            box-sizing: border-box;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .submitted-pin,
        .published-pin {
            display: block;
            border-radius: 999px;
            box-sizing: border-box;
        }

        .submitted-pin {
            width: 22px;
            height: 22px;
            border: 4px solid #ffffff;
            background: var(--submitted);
            box-shadow:
                0 0 0 3px var(--submitted),
                0 3px 10px rgba(0, 0, 0, .42);
        }

        .published-pin {
            width: 16px;
            height: 16px;
            border: 3px solid #ffffff;
            background: var(--published);
            box-shadow:
                0 0 0 2px var(--published),
                0 2px 8px rgba(0, 0, 0, .38);
        }

        .leaflet-popup-content-wrapper,
        .leaflet-popup-tip {
            background: var(--surface);
            color: var(--text);
        }

        .leaflet-popup-content {
            min-width: 150px;
            margin: 11px 13px;
            line-height: 1.4;
        }

        .leaflet-popup-content strong,
        .leaflet-popup-content span,
        .leaflet-popup-content a {
            display: block;
        }

        .leaflet-popup-content span {
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
        }

        .leaflet-popup-content a {
            margin-top: 7px;
            color: inherit;
            font-size: 11px;
            font-weight: 800;
        }

        .leaflet-control-zoom a {
            background: var(--floating);
            color: var(--text);
            border-color: var(--border);
        }

        .leaflet-control-attribution {
            background: var(--floating) !important;
            color: var(--muted);
            font-size: 8px;
        }
    </style>
</head>

<body>

<?php if (!$validCoordinates): ?>

    <div class="map-empty">
        This submission does not have valid coordinates to inspect.
    </div>

<?php else: ?>

    <div class="map-shell">
        <div
            id="nearby-map"
            role="region"
            aria-label="Nearby published Places around this submitted Place"
        ></div>

        <div
            class="map-style"
            role="group"
            aria-label="Map style"
        >
            <button
                type="button"
                class="is-active"
                data-map-style="auto"
                aria-pressed="true"
            >
                Auto
            </button>

            <button
                type="button"
                data-map-style="satellite"
                aria-pressed="false"
            >
                Satellite
            </button>
        </div>

        <div
            class="map-status"
            id="map-status"
        ></div>
    </div>

    <script
        id="map-data"
        type="application/json"
    ><?= json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) ?></script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        (() => {
            'use strict';

            const mapElement =
                document.getElementById('nearby-map');

            const dataElement =
                document.getElementById('map-data');

            if (
                !mapElement
                || !dataElement
                || typeof L === 'undefined'
            ) {
                return;
            }

            let data;

            try {
                data =
                    JSON.parse(
                        dataElement.textContent || '{}'
                    );
            } catch (error) {
                return;
            }

            const latitude = Number(data.latitude);
            const longitude = Number(data.longitude);

            if (
                !Number.isFinite(latitude)
                || !Number.isFinite(longitude)
            ) {
                return;
            }

            const places =
                Array.isArray(data.nearby_places)
                    ? data.nearby_places
                    : [];

            const escapeHtml = (value) => {
                const node =
                    document.createElement('div');

                node.textContent =
                    String(value ?? '');

                return node.innerHTML;
            };

            const storedTheme = () => {
                try {
                    return (
                        localStorage.getItem('llama-theme')
                        || 'system'
                    );
                } catch (error) {
                    return 'system';
                }
            };

            const resolvedTheme = () => {
                const stored = storedTheme();

                if (
                    stored === 'dark'
                    || stored === 'light'
                ) {
                    return stored;
                }

                try {
                    const parentTheme =
                        window.parent
                            ?.document
                            ?.documentElement
                            ?.dataset
                            ?.theme;

                    if (
                        parentTheme === 'dark'
                        || parentTheme === 'light'
                    ) {
                        return parentTheme;
                    }
                } catch (error) {
                }

                return window.matchMedia?.(
                    '(prefers-color-scheme: dark)'
                ).matches
                    ? 'dark'
                    : 'light';
            };

            const syncTheme = () => {
                document.documentElement.dataset.theme =
                    resolvedTheme();
            };

            syncTheme();

            const map =
                L.map(
                    mapElement,
                    {
                        zoomControl: false,
                        minZoom: 3,
                        maxZoom: 20,
                        zoomSnap: .5
                    }
                ).setView(
                    [latitude, longitude],
                    17
                );

            L.control.zoom({
                position: 'bottomright'
            }).addTo(map);

            const sources = {
                light: {
                    url: String(data.light_tiles || ''),
                    options: {
                        maxNativeZoom: 20,
                        maxZoom: 20,
                        attribution:
                            'Map data and tiles by their respective providers'
                    }
                },

                dark: {
                    url: String(data.dark_tiles || ''),
                    options: {
                        maxNativeZoom: 20,
                        maxZoom: 20,
                        attribution:
                            'Map data and tiles by their respective providers'
                    }
                },

                satellite: {
                    url: String(data.satellite_tiles || ''),
                    options: {
                        maxNativeZoom: 19,
                        maxZoom: 20,
                        attribution:
                            'Tiles &copy; Esri and imagery contributors'
                    }
                }
            };

            let selectedStyle = 'auto';
            let tileLayer = null;

            const buttons =
                Array.from(
                    document.querySelectorAll(
                        '[data-map-style]'
                    )
                );

            const applyTiles = () => {
                syncTheme();

                const key =
                    selectedStyle === 'satellite'
                        ? 'satellite'
                        : (
                            resolvedTheme() === 'dark'
                                ? 'dark'
                                : 'light'
                        );

                const source = sources[key];

                if (!source?.url) {
                    return;
                }

                if (tileLayer) {
                    map.removeLayer(tileLayer);
                }

                tileLayer =
                    L.tileLayer(
                        source.url,
                        source.options
                    )
                    .addTo(map);

                buttons.forEach((button) => {
                    const active =
                        button.dataset.mapStyle
                        === selectedStyle;

                    button.classList.toggle(
                        'is-active',
                        active
                    );

                    button.setAttribute(
                        'aria-pressed',
                        active ? 'true' : 'false'
                    );
                });
            };

            applyTiles();

            buttons.forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        selectedStyle =
                            button.dataset.mapStyle
                            || 'auto';

                        applyTiles();
                    }
                );
            });

            const submittedIcon =
                L.divIcon({
                    className: '',
                    html:
                        '<span class="submitted-pin"></span>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

            const publishedIcon =
                L.divIcon({
                    className: '',
                    html:
                        '<span class="published-pin"></span>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11]
                });

            const submittedMarker =
                L.marker(
                    [latitude, longitude],
                    {
                        icon: submittedIcon,
                        zIndexOffset: 1000
                    }
                )
                .addTo(map)
                .bindPopup(
                    '<strong>Submitted Place</strong>'
                    + '<span>Coordinates being reviewed</span>'
                );

            const bounds =
                L.latLngBounds(
                    submittedMarker.getLatLng(),
                    submittedMarker.getLatLng()
                );

            const formatDistance = (miles) => {
                const value = Number(miles);

                if (!Number.isFinite(value)) {
                    return '';
                }

                if (value < .2) {
                    return (
                        Math.max(
                            1,
                            Math.round(value * 5280)
                        ).toLocaleString()
                        + ' ft away'
                    );
                }

                return value.toFixed(2) + ' mi away';
            };

            places.forEach((place) => {
                const placeLatitude =
                    Number(place.latitude);

                const placeLongitude =
                    Number(place.longitude);

                if (
                    !Number.isFinite(placeLatitude)
                    || !Number.isFinite(placeLongitude)
                ) {
                    return;
                }

                const marker =
                    L.marker(
                        [placeLatitude, placeLongitude],
                        {
                            icon: publishedIcon
                        }
                    )
                    .addTo(map);

                bounds.extend(
                    marker.getLatLng()
                );

                const name =
                    escapeHtml(
                        place.name || 'Published Place'
                    );

                const type =
                    escapeHtml(
                        place.type || 'Published Place'
                    );

                const distance =
                    escapeHtml(
                        formatDistance(
                            place.distance_miles
                        )
                    );

                const slug =
                    String(place.slug || '').trim();

                const link =
                    slug !== ''
                        ? (
                            '<a '
                            + 'href="https://llamascout.com/place.php?slug='
                            + encodeURIComponent(slug)
                            + '" '
                            + 'target="_blank" '
                            + 'rel="noopener noreferrer">'
                            + 'Open published Place'
                            + '</a>'
                        )
                        : '';

                marker.bindPopup(
                    '<strong>' + name + '</strong>'
                    + '<span>'
                    + [type, distance]
                        .filter(Boolean)
                        .join(' · ')
                    + '</span>'
                    + link
                );
            });

            /*
             * If a nearby Place exists, keep all potentially duplicate
             * pins in frame. maxZoom 17 prevents the map from zooming
             * closer than the intended inspection level.
             */
            if (places.length > 0) {
                map.fitBounds(
                    bounds,
                    {
                        padding: [44, 44],
                        maxZoom: 17,
                        animate: false
                    }
                );
            }

            const status =
                document.getElementById('map-status');

            if (status) {
                const count = places.length;

                status.textContent =
                    count === 0
                        ? 'No published Places within 1 mile'
                        : (
                            count.toLocaleString()
                            + ' published '
                            + (
                                count === 1
                                    ? 'Place'
                                    : 'Places'
                            )
                            + ' within 1 mile'
                        );
            }

            const onThemeChange = () => {
                if (selectedStyle === 'auto') {
                    applyTiles();
                } else {
                    syncTheme();
                }
            };

            const colorScheme =
                window.matchMedia?.(
                    '(prefers-color-scheme: dark)'
                );

            if (colorScheme?.addEventListener) {
                colorScheme.addEventListener(
                    'change',
                    onThemeChange
                );
            } else if (colorScheme?.addListener) {
                colorScheme.addListener(
                    onThemeChange
                );
            }

            try {
                const parentRoot =
                    window.parent
                        .document
                        .documentElement;

                const observer =
                    new MutationObserver(
                        onThemeChange
                    );

                observer.observe(
                    parentRoot,
                    {
                        attributes: true,
                        attributeFilter: ['data-theme']
                    }
                );
            } catch (error) {
            }
        })();
    </script>

<?php endif; ?>

</body>
</html>

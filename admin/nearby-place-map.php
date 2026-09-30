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
     * This map is a duplicate / historical-location inspection tool.
     * Every canonical Place with coordinates matters here, including
     * Draft, Unlisted, Archived, and Removed records.
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
        SELECT h.reason
        FROM place_status_history h
        WHERE h.place_id = p.id
        ORDER BY h.changed_at DESC, h.id DESC
        LIMIT 1
    ) AS latest_status_reason,
    (
        SELECT h.changed_at
        FROM place_status_history h
        WHERE h.place_id = p.id
        ORDER BY h.changed_at DESC, h.id DESC
        LIMIT 1
    ) AS latest_status_changed_at,
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
WHERE p.latitude IS NOT NULL
  AND p.longitude IS NOT NULL
  AND p.latitude BETWEEN ? AND ?
  AND p.longitude BETWEEN ? AND ?
HAVING distance_miles <= ?
ORDER BY distance_miles ASC, p.name ASC
LIMIT 100
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

        $statusChangedAt =
            trim((string) ($row['latest_status_changed_at'] ?? ''));

        $nearbyPlaces[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => trim((string) ($row['name'] ?? 'Canonical Place')),
            'type' => trim((string) ($row['type'] ?? '')),
            'status' => (string) ($row['status'] ?? 'draft'),
            'status_reason' => trim((string) ($row['latest_status_reason'] ?? '')),
            'status_changed' => $statusChangedAt !== ''
                ? llama_format_viewer_datetime($statusChangedAt)
                : '',
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'distance_miles' => max(
                0.0,
                (float) ($row['distance_miles'] ?? 0)
            ),
        ];
    }
}

/* Reuse the same tile configuration as the existing member/admin maps. */
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Nearby Place Check</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >
    <link
        rel="stylesheet"
        href="https://llamascout.com/css/admin/features/nearby-place-map.css"
    >
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
            aria-label="Nearby canonical Places around this submitted Place"
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
            >Auto</button>

            <button
                type="button"
                data-map-style="satellite"
                aria-pressed="false"
            >Satellite</button>
        </div>

        <div
            class="map-legend"
            aria-label="Canonical Place status legend"
        >
            <span class="is-current">Active / Featured</span>
            <span class="is-draft">Draft</span>
            <span class="is-unlisted">Unlisted</span>
            <span class="is-archived">Archived</span>
            <span class="is-removed">Removed</span>
        </div>

        <div class="map-status" id="map-status"></div>
    </div>

    <script id="map-data" type="application/json"><?= json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) ?></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/js/admin-nearby-place-map.js"></script>
<?php endif; ?>

</body>
</html>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

$latRaw = trim((string) ($_GET['lat'] ?? ''));
$lngRaw = trim((string) ($_GET['lng'] ?? ''));

if (
    $latRaw === ''
    || $lngRaw === ''
    || !is_numeric($latRaw)
    || !is_numeric($lngRaw)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid latitude and longitude are required.',
    ]);
    exit;
}

$lat = (float) $latRaw;
$lng = (float) $lngRaw;

if (
    $lat < -90
    || $lat > 90
    || $lng < -180
    || $lng > 180
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Coordinates are outside the valid range.',
    ]);
    exit;
}

function location_lookup_json(
    string $url,
    array $headers = []
): ?array {
    $curl = curl_init($url);

    if ($curl === false) {
        return null;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_merge(
                [
                    'Accept: application/json',
                    'User-Agent: LlamaScout/1.0 (https://llamascout.com)',
                ],
                $headers
            ),
        ]
    );

    $body = curl_exec($curl);
    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_RESPONSE_CODE
    );

    curl_close($curl);

    if (
        !is_string($body)
        || $body === ''
        || $status < 200
        || $status >= 300
    ) {
        return null;
    }

    $decoded = json_decode(
        $body,
        true
    );

    return is_array($decoded)
        ? $decoded
        : null;
}

$reverseUrl =
    'https://nominatim.openstreetmap.org/reverse?' .
    http_build_query(
        [
            'format' => 'jsonv2',
            'lat' => number_format($lat, 7, '.', ''),
            'lon' => number_format($lng, 7, '.', ''),
            'zoom' => 18,
            'addressdetails' => 1,
        ]
    );

$reverse =
    location_lookup_json(
        $reverseUrl,
        ['Accept-Language: en-US,en;q=0.9']
    );

$address =
    is_array($reverse['address'] ?? null)
        ? $reverse['address']
        : [];

$road =
    $address['road']
    ?? $address['pedestrian']
    ?? $address['path']
    ?? $address['track']
    ?? $address['highway']
    ?? null;

$city =
    $address['city']
    ?? $address['town']
    ?? $address['village']
    ?? $address['hamlet']
    ?? $address['municipality']
    ?? $address['locality']
    ?? null;

$county =
    $address['county']
    ?? null;

$state =
    $address['state']
    ?? $address['region']
    ?? null;

$elevationUrl =
    'https://api.open-meteo.com/v1/elevation?' .
    http_build_query(
        [
            'latitude' => number_format($lat, 7, '.', ''),
            'longitude' => number_format($lng, 7, '.', ''),
        ]
    );

$elevationData =
    location_lookup_json(
        $elevationUrl
    );

$meters = null;

if (isset($elevationData['elevation'])) {
    if (is_array($elevationData['elevation'])) {
        $meters = $elevationData['elevation'][0] ?? null;
    } else {
        $meters = $elevationData['elevation'];
    }
}

$elevationFeet =
    is_numeric($meters)
        ? (int) round(
            ((float) $meters) * 3.28084
        )
        : null;

echo json_encode(
    [
        'success' => true,
        'location' => [
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
            'elevation_feet' => $elevationFeet,
            'road' => $road,
            'city' => $city,
            'county' => $county,
            'state' => $state,
        ],
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

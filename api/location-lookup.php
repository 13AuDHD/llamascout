<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, max-age=0'
);

$latRaw =
    trim(
        (string) (
            $_GET['lat']
            ?? ''
        )
    );

$lngRaw =
    trim(
        (string) (
            $_GET['lng']
            ?? ''
        )
    );

if (
    $latRaw === ''
    || $lngRaw === ''
    || !is_numeric($latRaw)
    || !is_numeric($lngRaw)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Valid latitude and longitude are required.',
    ]);

    exit;
}

$lat =
    (float) $latRaw;

$lng =
    (float) $lngRaw;

if (
    $lat < -90
    || $lat > 90
    || $lng < -180
    || $lng > 180
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Coordinates are outside the valid range.',
    ]);

    exit;
}


function location_lookup_json(
    string $url,
    array $headers = [],
    int $timeout = 10
): ?array {
    $curl =
        curl_init($url);

    if ($curl === false) {
        return null;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                5,

            CURLOPT_TIMEOUT =>
                max(
                    3,
                    min(
                        15,
                        $timeout
                    )
                ),

            CURLOPT_HTTPHEADER =>
                array_merge(
                    [
                        'Accept: application/json',

                        'User-Agent: LlamaScout/1.0 (https://llamascout.com)',
                    ],
                    $headers
                ),
        ]
    );

    $body =
        curl_exec($curl);

    $status =
        (int) curl_getinfo(
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

    $decoded =
        json_decode(
            $body,
            true
        );

    return is_array($decoded)
        ? $decoded
        : null;
}


function location_haversine_meters(
    float $lat1,
    float $lng1,
    float $lat2,
    float $lng2
): float {
    $earthRadius =
        6371000.0;

    $lat1Rad =
        deg2rad($lat1);

    $lat2Rad =
        deg2rad($lat2);

    $deltaLat =
        deg2rad(
            $lat2 - $lat1
        );

    $deltaLng =
        deg2rad(
            $lng2 - $lng1
        );

    $a =
        sin($deltaLat / 2)
        * sin($deltaLat / 2)
        +
        cos($lat1Rad)
        * cos($lat2Rad)
        *
        sin($deltaLng / 2)
        * sin($deltaLng / 2);

    $c =
        2
        * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

    return $earthRadius * $c;
}


function location_nearest_named_road(
    float $lat,
    float $lng
): ?array {
    /*
     * Nominatim is excellent for an address-like reverse lookup,
     * but it can sometimes attach a coordinate in a parking lot
     * or large parcel to a nearby higher-ranked road.
     *
     * Overpass lets us inspect the actual named highway geometry
     * close to the GPS point. We keep the search radius small so
     * a farther arterial does not win over a nearby local road.
     */

    $radiusMeters =
        120;

    $query =
        '[out:json][timeout:7];'
        . '('
        . 'way(around:'
        . $radiusMeters
        . ','
        . number_format(
            $lat,
            7,
            '.',
            ''
        )
        . ','
        . number_format(
            $lng,
            7,
            '.',
            ''
        )
        . ')[highway][name];'
        . ');'
        . 'out tags geom;';

    $url =
        'https://overpass-api.de/api/interpreter?'
        . http_build_query([
            'data' => $query,
        ]);

    $result =
        location_lookup_json(
            $url,
            [],
            9
        );

    $elements =
        is_array(
            $result['elements']
            ?? null
        )
            ? $result['elements']
            : [];

    $best = null;

    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }

        $name =
            trim(
                (string) (
                    $element['tags']['name']
                    ?? ''
                )
            );

        if ($name === '') {
            continue;
        }

        $geometry =
            is_array(
                $element['geometry']
                ?? null
            )
                ? $element['geometry']
                : [];

        $nearestDistance =
            null;

        foreach ($geometry as $point) {
            if (
                !is_array($point)
                || !is_numeric(
                    $point['lat']
                    ?? null
                )
                || !is_numeric(
                    $point['lon']
                    ?? null
                )
            ) {
                continue;
            }

            $distance =
                location_haversine_meters(
                    $lat,
                    $lng,
                    (float) $point['lat'],
                    (float) $point['lon']
                );

            if (
                $nearestDistance === null
                || $distance
                    < $nearestDistance
            ) {
                $nearestDistance =
                    $distance;
            }
        }

        if ($nearestDistance === null) {
            continue;
        }

        if (
            $best === null
            || $nearestDistance
                < $best['distance_meters']
        ) {
            $best = [
                'name' => $name,
                'distance_meters' =>
                    $nearestDistance,
                'highway' =>
                    trim(
                        (string) (
                            $element['tags']['highway']
                            ?? ''
                        )
                    ),
            ];
        }
    }

    /*
     * Do not replace Nominatim's road with something that is
     * not actually close to the device. 80 m is enough for
     * parking lots and roadside properties but prevents a
     * nearby arterial several blocks away from taking over.
     */
    if (
        $best === null
        || $best['distance_meters'] > 80
    ) {
        return null;
    }

    return $best;
}


$reverseUrl =
    'https://nominatim.openstreetmap.org/reverse?'
    . http_build_query(
        [
            'format' =>
                'jsonv2',

            'lat' =>
                number_format(
                    $lat,
                    7,
                    '.',
                    ''
                ),

            'lon' =>
                number_format(
                    $lng,
                    7,
                    '.',
                    ''
                ),

            'zoom' =>
                18,

            'addressdetails' =>
                1,
        ]
    );

$reverse =
    location_lookup_json(
        $reverseUrl,
        [
            'Accept-Language: en-US,en;q=0.9',
        ]
    );

$address =
    is_array(
        $reverse['address']
        ?? null
    )
        ? $reverse['address']
        : [];

$nominatimRoad =
    $address['road']
    ?? $address['pedestrian']
    ?? $address['path']
    ?? $address['track']
    ?? $address['highway']
    ?? null;

$nearestRoad =
    location_nearest_named_road(
        $lat,
        $lng
    );

$road =
    is_array($nearestRoad)
    && trim(
        (string) (
            $nearestRoad['name']
            ?? ''
        )
    ) !== ''
        ? trim(
            (string) $nearestRoad['name']
        )
        : $nominatimRoad;

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
    'https://api.open-meteo.com/v1/elevation?'
    . http_build_query(
        [
            'latitude' =>
                number_format(
                    $lat,
                    7,
                    '.',
                    ''
                ),

            'longitude' =>
                number_format(
                    $lng,
                    7,
                    '.',
                    ''
                ),
        ]
    );

$elevationData =
    location_lookup_json(
        $elevationUrl
    );

$meters = null;

if (
    isset(
        $elevationData['elevation']
    )
) {
    if (
        is_array(
            $elevationData['elevation']
        )
    ) {
        $meters =
            $elevationData['elevation'][0]
            ?? null;
    } else {
        $meters =
            $elevationData['elevation'];
    }
}

$elevationFeet =
    is_numeric($meters)
        ? (int) round(
            ((float) $meters)
            * 3.28084
        )
        : null;


echo json_encode(
    [
        'success' =>
            true,

        'location' => [
            'latitude' =>
                round(
                    $lat,
                    7
                ),

            'longitude' =>
                round(
                    $lng,
                    7
                ),

            'elevation_feet' =>
                $elevationFeet,

            'road' =>
                $road,

            'city' =>
                $city,

            'county' =>
                $county,

            'state' =>
                $state,

            'road_lookup' =>
                is_array($nearestRoad)
                    ? 'nearest_named_road'
                    : 'reverse_geocode',
        ],
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

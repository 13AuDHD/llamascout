<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/geography.php';

require_once
    dirname(__DIR__)
    . '/app/pad-us-location.php';


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


/*
 * =========================================================
 * SHARED JSON LOOKUP
 * =========================================================
 */

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

    return
        is_array($decoded)
            ? $decoded
            : null;
}


/*
 * =========================================================
 * DISTANCE
 * =========================================================
 */

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

    return
        $earthRadius
        * $c;
}


/*
 * =========================================================
 * NEAREST NAMED ROAD
 * =========================================================
 */

function location_nearest_named_road(
    float $lat,
    float $lng
): ?array {
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

    $best =
        null;

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
                'name' =>
                    $name,

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

    if (
        $best === null
        || $best['distance_meters']
            > 80
    ) {
        return null;
    }

    return $best;
}


/*
 * =========================================================
 * NEAREST USEFUL CITY / TOWN
 *
 * Geoapify is the primary locality search because proximity
 * queries answer the question this field actually asks:
 * which named city, town or village is nearest this point?
 *
 * Overpass remains an independent fallback, followed by the
 * existing Nominatim reverse-geocode fallback.
 * =========================================================
 */

function location_geoapify_api_key(): string
{
    $config = llama_config();

    $candidates = [
        $config['geoapify']['api_key'] ?? null,
        $config['geoapify']['key'] ?? null,
        $config['geoapify_api_key'] ?? null,
        $config['services']['geoapify']['api_key'] ?? null,
        $config['services']['geoapify']['key'] ?? null,
        $config['apis']['geoapify']['api_key'] ?? null,
        $config['apis']['geoapify']['key'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);

        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function location_geoapify_nearest_locality(
    float $lat,
    float $lng
): ?array {
    $apiKey = location_geoapify_api_key();

    if ($apiKey === '') {
        return null;
    }

    $radiusMeters = 75000;

    $url =
        'https://api.geoapify.com/v2/places?'
        . http_build_query([
            'categories' =>
                'populated_place.city,populated_place.town,populated_place.village',
            'filter' =>
                'circle:'
                . number_format($lng, 7, '.', '')
                . ','
                . number_format($lat, 7, '.', '')
                . ','
                . $radiusMeters,
            'bias' =>
                'proximity:'
                . number_format($lng, 7, '.', '')
                . ','
                . number_format($lat, 7, '.', ''),
            'limit' =>
                30,
            'apiKey' =>
                $apiKey,
        ]);

    $result = location_lookup_json(
        $url,
        [
            'Accept-Language: en-US,en;q=0.9',
        ],
        8
    );

    $features =
        is_array($result['features'] ?? null)
            ? $result['features']
            : [];

    $typePenaltyMeters = [
        'city' => 0,
        'town' => 0,
        'village' => 5000,
    ];

    $candidates = [];

    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }

        $properties =
            is_array($feature['properties'] ?? null)
                ? $feature['properties']
                : [];

        $geometry =
            is_array($feature['geometry'] ?? null)
                ? $feature['geometry']
                : [];

        $coordinates =
            is_array($geometry['coordinates'] ?? null)
                ? $geometry['coordinates']
                : [];

        $name = trim(
            (string) (
                $properties['name']
                ?? $properties['city']
                ?? $properties['town']
                ?? $properties['village']
                ?? ''
            )
        );

        if (
            $name === ''
            || !isset($coordinates[0], $coordinates[1])
            || !is_numeric($coordinates[0])
            || !is_numeric($coordinates[1])
        ) {
            continue;
        }

        $categories = array_map(
            static fn (mixed $value): string =>
                strtolower(trim((string) $value)),
            (array) ($properties['categories'] ?? [])
        );

        $placeType = 'town';

        foreach (['city', 'town', 'village'] as $candidateType) {
            foreach ($categories as $category) {
                if (str_contains($category, 'populated_place.' . $candidateType)) {
                    $placeType = $candidateType;
                    break 2;
                }
            }
        }

        $distance = location_haversine_meters(
            $lat,
            $lng,
            (float) $coordinates[1],
            (float) $coordinates[0]
        );

        if ($distance > $radiusMeters) {
            continue;
        }

        $population = null;
        if (is_numeric($properties['population'] ?? null)) {
            $population = max(0, (int) $properties['population']);
        }

        $populationBonus =
            $population !== null && $population > 0
                ? min(3000, log10(max(10, $population)) * 600)
                : 0;

        $score =
            $distance
            + ($typePenaltyMeters[$placeType] ?? 0)
            - $populationBonus;

        $candidates[] = [
            'name' => $name,
            'place_type' => $placeType,
            'distance_meters' => $distance,
            'population' => $population,
            'lookup' => 'geoapify_places',
            'score' => $score,
        ];
    }

    if (!$candidates) {
        return null;
    }

    usort(
        $candidates,
        static fn (array $a, array $b): int =>
            $a['score'] <=> $b['score']
    );

    $best = $candidates[0];
    unset($best['score']);

    return $best;
}

function location_nearest_locality(
    float $lat,
    float $lng
): ?array {
    $geoapify = location_geoapify_nearest_locality(
        $lat,
        $lng
    );

    if ($geoapify !== null) {
        return $geoapify;
    }

    /*
     * First try the lightweight OSM place-node lookup.
     *
     * Do not use nwr here. Searching every node, way and relation
     * across a large radius made this request much slower and caused
     * frequent Overpass timeouts.
     */
    $radiusMeters =
        60000;

    $query =
        '[out:json][timeout:7];'
        . '('
        . 'node(around:'
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
        . ')[place~"^(city|town|village)$"][name];'
        . ');'
        . 'out tags;';

    $url =
        'https://overpass-api.de/api/interpreter?'
        . http_build_query([
            'data' =>
                $query,
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

    $typePenaltyMeters = [
        'city' =>
            0,

        'town' =>
            0,

        'village' =>
            9000,
    ];

    $candidates = [];

    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }

        $tags =
            is_array(
                $element['tags']
                ?? null
            )
                ? $element['tags']
                : [];

        $name =
            trim(
                (string) (
                    $tags['name']
                    ?? ''
                )
            );

        $placeType =
            trim(
                (string) (
                    $tags['place']
                    ?? ''
                )
            );

        if (
            $name === ''
            || !isset(
                $typePenaltyMeters[
                    $placeType
                ]
            )
            || !is_numeric(
                $element['lat']
                ?? null
            )
            || !is_numeric(
                $element['lon']
                ?? null
            )
        ) {
            continue;
        }

        $distance =
            location_haversine_meters(
                $lat,
                $lng,
                (float) $element['lat'],
                (float) $element['lon']
            );

        $population =
            0;

        $populationRaw =
            preg_replace(
                '/[^0-9]/',
                '',
                (string) (
                    $tags['population']
                    ?? ''
                )
            );

        if (
            is_string($populationRaw)
            && $populationRaw !== ''
            && is_numeric($populationRaw)
        ) {
            $population =
                (int) $populationRaw;
        }

        /*
         * Population only nudges close candidates.
         * Distance is still the dominant factor.
         */
        $populationBonus =
            $population > 0
                ? min(
                    4000,
                    log10(
                        max(
                            10,
                            $population
                        )
                    )
                    * 800
                )
                : 0;

        $score =
            $distance
            +
            $typePenaltyMeters[
                $placeType
            ]
            -
            $populationBonus;

        $candidates[] = [
            'name' =>
                $name,

            'place_type' =>
                $placeType,

            'distance_meters' =>
                $distance,

            'population' =>
                $population > 0
                    ? $population
                    : null,

            'lookup' =>
                'overpass',

            'score' =>
                $score,
        ];
    }

    if ($candidates) {
        usort(
            $candidates,
            static fn (
                array $a,
                array $b
            ): int =>
                $a['score']
                <=>
                $b['score']
        );

        $best =
            $candidates[0];

        unset(
            $best['score']
        );

        return $best;
    }

    /*
     * Overpass occasionally times out.
     *
     * Ask Nominatim for a city-level reverse lookup as a fallback.
     * zoom=10 intentionally asks for locality-level information
     * instead of a road/building address.
     */
    $fallbackUrl =
        'https://nominatim.openstreetmap.org/reverse?'
        . http_build_query([
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
                10,

            'addressdetails' =>
                1,
        ]);

    $fallback =
        location_lookup_json(
            $fallbackUrl,
            [
                'Accept-Language: en-US,en;q=0.9',
            ],
            8
        );

    $fallbackAddress =
        is_array(
            $fallback['address']
            ?? null
        )
            ? $fallback['address']
            : [];

    $fallbackName =
        trim(
            (string) (
                $fallbackAddress['city']
                ?? $fallbackAddress['town']
                ?? $fallbackAddress['village']
                ?? $fallbackAddress['municipality']
                ?? ''
            )
        );

    if ($fallbackName === '') {
        return null;
    }

    return [
        'name' =>
            $fallbackName,

        'place_type' =>
            isset(
                $fallbackAddress['city']
            )
                ? 'city'
                : (
                    isset(
                        $fallbackAddress['town']
                    )
                        ? 'town'
                        : 'locality'
                ),

        'distance_meters' =>
            null,

        'population' =>
            null,

        'lookup' =>
            'nominatim_city_level',
    ];
}


/*
 * =========================================================
 * USFS RANGER DISTRICT
 * =========================================================
 */

function location_usfs_ranger_district(
    float $lat,
    float $lng
): ?array {
    $url =
        'https://apps.fs.usda.gov/arcx/rest/services/EDW/EDW_RangerDistricts_03/MapServer/1/query?'
        . http_build_query([
            'f' =>
                'json',

            'where' =>
                '1=1',

            'geometry' =>
                number_format(
                    $lng,
                    7,
                    '.',
                    ''
                )
                . ','
                . number_format(
                    $lat,
                    7,
                    '.',
                    ''
                ),

            'geometryType' =>
                'esriGeometryPoint',

            'inSR' =>
                '4326',

            'spatialRel' =>
                'esriSpatialRelIntersects',

            'outFields' =>
                implode(
                    ',',
                    [
                        'rangerdistrictid',
                        'region',
                        'forestnumber',
                        'districtnumber',
                        'districtorgcode',
                        'forestname',
                        'districtname',
                        'gis_acres',
                    ]
                ),

            'returnGeometry' =>
                'false',
        ]);

    $result =
        location_lookup_json(
            $url,
            [],
            10
        );

    $features =
        is_array(
            $result['features']
            ?? null
        )
            ? $result['features']
            : [];

    if (!$features) {
        return null;
    }

    $attributes =
        is_array(
            $features[0]['attributes']
            ?? null
        )
            ? $features[0]['attributes']
            : [];

    $district =
        trim(
            (string) (
                $attributes['districtname']
                ?? ''
            )
        );

    if ($district === '') {
        return null;
    }

    return [
        'district' =>
            $district,

        'forest' =>
            trim(
                (string) (
                    $attributes['forestname']
                    ?? ''
                )
            ),

        'region' =>
            trim(
                (string) (
                    $attributes['region']
                    ?? ''
                )
            ),

        'district_org_code' =>
            trim(
                (string) (
                    $attributes['districtorgcode']
                    ?? ''
                )
            ),

        'district_number' =>
            trim(
                (string) (
                    $attributes['districtnumber']
                    ?? ''
                )
            ),

        'forest_number' =>
            trim(
                (string) (
                    $attributes['forestnumber']
                    ?? ''
                )
            ),

        'ranger_district_id' =>
            trim(
                (string) (
                    $attributes['rangerdistrictid']
                    ?? ''
                )
            ),
    ];
}


/*
 * =========================================================
 * BLM FIELD OFFICE
 *
 * Official national BLM Administrative Unit Field Boundary
 * layer. The field-office polygons are the smallest BLM
 * administrative land units.
 * =========================================================
 */

function location_blm_field_office(
    float $lat,
    float $lng
): ?array {
    $url =
        'https://gis.blm.gov/arcgis/rest/services/admin_boundaries/BLM_Natl_AdminUnit/MapServer/3/query?'
        . http_build_query([
            'f' =>
                'json',

            'where' =>
                '1=1',

            'geometry' =>
                number_format(
                    $lng,
                    7,
                    '.',
                    ''
                )
                . ','
                . number_format(
                    $lat,
                    7,
                    '.',
                    ''
                ),

            'geometryType' =>
                'esriGeometryPoint',

            'inSR' =>
                '4326',

            'spatialRel' =>
                'esriSpatialRelIntersects',

            'outFields' =>
                implode(
                    ',',
                    [
                        'ADM_UNIT_CD',
                        'ADMU_NAME',
                        'BLM_ORG_TYPE',
                        'PARENT_CD',
                        'PARENT_NAME',
                        'ADMIN_ST',
                        'ADMU_ST_URL',
                    ]
                ),

            'returnGeometry' =>
                'false',
        ]);

    $result =
        location_lookup_json(
            $url,
            [],
            10
        );

    $features =
        is_array(
            $result['features']
            ?? null
        )
            ? $result['features']
            : [];

    if (!$features) {
        return null;
    }

    $attributes =
        is_array(
            $features[0]['attributes']
            ?? null
        )
            ? $features[0]['attributes']
            : [];

    $fieldOffice =
        trim(
            (string) (
                $attributes['ADMU_NAME']
                ?? ''
            )
        );

    if ($fieldOffice === '') {
        return null;
    }

    return [
        'field_office' =>
            $fieldOffice,

        'administrative_unit_code' =>
            trim(
                (string) (
                    $attributes['ADM_UNIT_CD']
                    ?? ''
                )
            ),

        'organization_type' =>
            trim(
                (string) (
                    $attributes['BLM_ORG_TYPE']
                    ?? ''
                )
            ),

        'parent_name' =>
            trim(
                (string) (
                    $attributes['PARENT_NAME']
                    ?? ''
                )
            ),

        'parent_code' =>
            trim(
                (string) (
                    $attributes['PARENT_CD']
                    ?? ''
                )
            ),

        'administrative_state' =>
            trim(
                (string) (
                    $attributes['ADMIN_ST']
                    ?? ''
                )
            ),

        'state_office_url' =>
            trim(
                (string) (
                    $attributes['ADMU_ST_URL']
                    ?? ''
                )
            ),
    ];
}


/*
 * =========================================================
 * ADDRESS / ROAD
 * =========================================================
 */

$reverseUrl =
    'https://nominatim.openstreetmap.org/reverse?'
    . http_build_query([
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
    ]);


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


/*
 * =========================================================
 * NEAREST CITY / TOWN
 *
 * Always run the useful-locality lookup now. Reverse geocode
 * is retained only as a fallback if Overpass cannot return a
 * usable city/town/village.
 * =========================================================
 */

$nearestLocality =
    location_nearest_locality(
        $lat,
        $lng
    );


if (
    is_array($nearestLocality)
    && trim(
        (string) (
            $nearestLocality['name']
            ?? ''
        )
    ) !== ''
) {
    $city =
        trim(
            (string) $nearestLocality['name']
        );

    $cityLookup =
        'nearest_locality';

} else {
    $city =
        $address['city']
        ?? $address['town']
        ?? $address['village']
        ?? $address['municipality']
        ?? null;

    $cityLookup =
        'reverse_geocode';
}


/*
 * =========================================================
 * COUNTY / STATE
 * =========================================================
 */

$nominatimCounty =
    $address['county']
    ?? null;


$censusCounty =
    llama_official_county_from_coordinates(
        $lat,
        $lng
    );


$county =
    is_array($censusCounty)
    && trim(
        (string) (
            $censusCounty['name']
            ?? ''
        )
    ) !== ''
        ? trim(
            (string) $censusCounty['name']
        )
        : $nominatimCounty;


$state =
    $address['state']
    ?? $address['region']
    ?? null;


/*
 * =========================================================
 * ELEVATION
 * =========================================================
 */

$elevationUrl =
    'https://api.open-meteo.com/v1/elevation?'
    . http_build_query([
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
    ]);


$elevationData =
    location_lookup_json(
        $elevationUrl
    );


$meters =
    null;


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
            $elevationData[
                'elevation'
            ][0]
            ?? null;
    } else {
        $meters =
            $elevationData[
                'elevation'
            ];
    }
}


$elevationFeet =
    is_numeric($meters)
        ? (int) round(
            ((float) $meters)
            * 3.28084
        )
        : null;


/*
 * =========================================================
 * PAD-US LAND LOOKUP
 * =========================================================
 */

$padUs = [
    'best_match' =>
        null,

    'matches' =>
        [],
];


try {
    $padUs =
        llama_pad_us_point_lookup(
            reference_db(),
            db(),
            $lat,
            $lng
        );

} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'location_lookup.pad_us',
        [
            'latitude' =>
                $lat,

            'longitude' =>
                $lng,
        ]
    );
}


$padUsBest =
    is_array(
        $padUs['best_match']
        ?? null
    )
        ? $padUs['best_match']
        : null;


/*
 * =========================================================
 * DETERMINE MANAGING AGENCY
 * =========================================================
 */

$propertyType =
    trim(
        (string) (
            $padUsBest['property_type']
            ?? ''
        )
    );


$managerName =
    trim(
        (string) (
            $padUsBest['manager']
            ?? ''
        )
    );


$isForestServiceLand =
    is_array($padUsBest)
    && (
        strcasecmp(
            $propertyType,
            'National Forest'
        ) === 0

        ||

        strcasecmp(
            $propertyType,
            'National Grassland'
        ) === 0

        ||

        stripos(
            $managerName,
            'Forest Service'
        ) !== false
    );


$isBlmLand =
    is_array($padUsBest)
    && (
        strcasecmp(
            $propertyType,
            'BLM Land'
        ) === 0

        ||

        stripos(
            $managerName,
            'Bureau of Land Management'
        ) !== false

        ||

        preg_match(
            '/\bBLM\b/i',
            $managerName
        ) === 1
    );


/*
 * =========================================================
 * USFS RANGER DISTRICT
 * =========================================================
 */

$usfsDistrict =
    null;


if ($isForestServiceLand) {
    try {
        $usfsDistrict =
            location_usfs_ranger_district(
                $lat,
                $lng
            );

    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'location_lookup.usfs_ranger_district',
            [
                'latitude' =>
                    $lat,

                'longitude' =>
                    $lng,
            ]
        );
    }
}


/*
 * =========================================================
 * BLM FIELD OFFICE
 * =========================================================
 */

$blmFieldOffice =
    null;


if ($isBlmLand) {
    try {
        $blmFieldOffice =
            location_blm_field_office(
                $lat,
                $lng
            );

    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'location_lookup.blm_field_office',
            [
                'latitude' =>
                    $lat,

                'longitude' =>
                    $lng,
            ]
        );
    }
}


/*
 * =========================================================
 * UNIFIED LOCAL MANAGEMENT AREA
 * =========================================================
 */

$localManagementArea =
    null;


if (
    is_array($usfsDistrict)
    && trim(
        (string) (
            $usfsDistrict['district']
            ?? ''
        )
    ) !== ''
) {
    $localManagementArea =
        [
            'name' =>
                trim(
                    (string) $usfsDistrict[
                        'district'
                    ]
                ),

            'type' =>
                'ranger_district',

            'agency' =>
                'U.S. Forest Service',
        ];

} elseif (
    is_array($blmFieldOffice)
    && trim(
        (string) (
            $blmFieldOffice['field_office']
            ?? ''
        )
    ) !== ''
) {
    $localManagementArea =
        [
            'name' =>
                trim(
                    (string) $blmFieldOffice[
                        'field_office'
                    ]
                ),

            'type' =>
                'field_office',

            'agency' =>
                'Bureau of Land Management',
        ];
}


/*
 * =========================================================
 * RESPONSE
 * =========================================================
 */

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

            'city_lookup' =>
                $cityLookup,

            'city_details' =>
                $nearestLocality,

            'county' =>
                $county,

            'state' =>
                $state,

            'road_lookup' =>
                is_array(
                    $nearestRoad
                )
                    ? 'nearest_named_road'
                    : 'reverse_geocode',

            'county_lookup' =>
                is_array(
                    $censusCounty
                )
                    ? 'us_census'
                    : 'reverse_geocode',

            'county_geoid' =>
                is_array(
                    $censusCounty
                )
                    ? (
                        $censusCounty[
                            'geoid'
                        ]
                        ?? null
                    )
                    : null,
        ],

        'pad_us' =>
            $padUs,

        'usfs' => [
            'ranger_district' =>
                $usfsDistrict,
        ],

        'blm' => [
            'field_office' =>
                $blmFieldOffice,
        ],

        'local_management_area' =>
            $localManagementArea,
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

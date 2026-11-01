<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $respond(
        ['ok' => false, 'error' => 'Method not allowed.'],
        405
    );
}

$slug =
    trim(
        (string) (
            $_GET['slug']
            ?? ''
        )
    );

if ($slug === '') {
    $respond(
        ['ok' => false, 'error' => 'A Place is required.'],
        400
    );
}

$place =
    place_public_by_slug(
        $slug
    );

if (!$place) {
    $respond(
        ['ok' => false, 'error' => 'Place not found.'],
        404
    );
}

try {
    $features =
        array_values(
            array_filter(
                llama_place_map_features(
                    db(),
                    (int) ($place['id'] ?? 0),
                    true,
                    true
                ),
                static fn (array $feature): bool =>
                    in_array(
                        (string) (
                            $feature['feature_type']
                            ?? ''
                        ),
                        [
                            'camping_area',
                            'parking_area',
                        ],
                        true
                    )
            )
        );

    $publicAreas =
        array_map(
            static function (array $feature): array {
                return [
                    'id' =>
                        (int) (
                            $feature['id']
                            ?? 0
                        ),

                    'feature_type' =>
                        (string) (
                            $feature['feature_type']
                            ?? ''
                        ),

                    'label' =>
                        (string) (
                            $feature['label']
                            ?? ''
                        ),

                    'geometry_type' =>
                        (string) (
                            $feature['geometry_type']
                            ?? 'polygon'
                        ),

                    'crs' =>
                        (string) (
                            $feature['crs']
                            ?? LLAMA_PLACE_MAP_FEATURE_CRS
                        ),

                    'srid' =>
                        (int) (
                            $feature['srid']
                            ?? LLAMA_PLACE_MAP_FEATURE_SRID
                        ),

                    'area_details' =>
                        (array) (
                            $feature['area_details']
                            ?? []
                        ),

                    'geometry' =>
                        (array) (
                            $feature['geometry']
                            ?? []
                        ),
                ];
            },
            $features
        );

    $respond([
        'ok' => true,
        'place_id' =>
            (int) ($place['id'] ?? 0),
        'features' =>
            $publicAreas,
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.public_area_map'
    );

    $respond(
        [
            'ok' => false,
            'error' =>
                'Public Area map data could not be loaded.',
        ],
        500
    );
}

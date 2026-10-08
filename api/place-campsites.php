<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

function llama_place_campsites_api_json(
    array $payload,
    int $status = 200
): never {
    http_response_code(
        $status
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'GET'
) {
    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'Method not allowed.',
        ],
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
    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'A Place slug is required.',
        ],
        400
    );
}

$publicPlace =
    place_public_by_slug(
        $slug
    );

if (!$publicPlace) {
    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'Place not found.',
        ],
        404
    );
}

$placeId =
    (int) (
        $publicPlace['id']
        ?? 0
    );

$user =
    current_user();

$userId =
    (int) (
        $user['id']
        ?? 0
    );

$canRead =
    $userId > 0
    && user_has_place_complete_access(
        $placeId,
        $userId
    );

if (!$canRead) {
    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'Complete Place access is required.',
        ],
        403
    );
}

$place =
    place_member_by_slug(
        $slug
    );

if (!$place) {
    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'Place not found.',
        ],
        404
    );
}

try {
    $sites =
        llama_place_campsites(
            db(),
            $placeId
        );

    $facilityFacts =
        llama_place_facility_facts(
            db(),
            $placeId
        );

    $facilityFeatures =
        llama_place_facility_features(
            db(),
            $placeId
        );

    $state =
        llama_place_campsite_browser_state(
            $place,
            $sites
        );

    $selectedFeatureId =
        max(
            0,
            (int) (
                $_GET['site']
                ?? 0
            )
        );

    $selectedSite =
        $selectedFeatureId > 0
            ? llama_place_campsite(
                db(),
                $placeId,
                $selectedFeatureId
            )
            : [];

    llama_place_campsites_api_json([
        'ok' => true,

        'place' => [
            'id' =>
                $placeId,

            'slug' =>
                (string) $place[
                    'slug'
                ],

            'name' =>
                (string) $place[
                    'name'
                ],

            'type' =>
                (string) (
                    $place['type']
                    ?? ''
                ),

            'experience_mode' =>
                $state['mode'],
        ],

        'browser' =>
            $state,

        'facility_facts' =>
            $facilityFacts,

        'facility_features' =>
            $facilityFeatures,

        'sites' =>
            array_map(
                static function (
                    array $site
                ): array {
                    $site['summary'] =
                        llama_place_campsite_summary(
                            $site
                        );

                    return $site;
                },
                $sites
            ),

        'selected_site' =>
            $selectedSite
                ? array_merge(
                    $selectedSite,
                    [
                        'summary' =>
                            llama_place_campsite_summary(
                                $selectedSite
                            ),
                    ]
                )
                : null,
    ]);

} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.campsites.api',
        [
            'place_id' =>
                $placeId,

            'user_id' =>
                $userId,
        ]
    );

    llama_place_campsites_api_json(
        [
            'ok' => false,
            'error' =>
                'Campsite data could not be loaded.',
        ],
        500
    );
}

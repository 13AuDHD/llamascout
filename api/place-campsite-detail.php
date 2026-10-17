<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-campsite-media.php';

header('Content-Type: application/json; charset=utf-8');

function llama_place_campsite_detail_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

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
    llama_place_campsite_detail_json(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
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

$campsiteId =
    max(
        0,
        (int) (
            $_GET['site']
            ?? 0
        )
    );

if ($slug === '' || $campsiteId < 1) {
    llama_place_campsite_detail_json(
        [
            'ok' => false,
            'error' => 'A Place and Site are required.',
        ],
        400
    );
}

$publicPlace =
    place_public_by_slug(
        $slug
    );

if (!$publicPlace) {
    llama_place_campsite_detail_json(
        [
            'ok' => false,
            'error' => 'Place not found.',
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
    llama_place_campsite_detail_json(
        [
            'ok' => false,
            'error' => 'Complete Place access is required.',
        ],
        403
    );
}

try {
    $db =
        db();

    $site =
        llama_place_campsite(
            $db,
            $placeId,
            $campsiteId
        );

    if (!$site) {
        llama_place_campsite_detail_json(
            [
                'ok' => false,
                'error' => 'Site not found.',
            ],
            404
        );
    }

    $images =
        llama_place_campsite_images(
            $db,
            $placeId,
            $campsiteId
        );

    llama_place_campsite_detail_json([
        'ok' => true,
        'site' => $site,
        'images' => $images,
    ]);

} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.campsite.detail.api',
        [
            'place_id' => $placeId,
            'campsite_id' => $campsiteId,
            'user_id' => $userId,
        ]
    );

    llama_place_campsite_detail_json(
        [
            'ok' => false,
            'error' => 'Site details could not be loaded.',
        ],
        500
    );
}

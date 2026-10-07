<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';

header('Content-Type: application/json; charset=utf-8');

function llama_place_map_api_json(
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

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if (
    $userId < 1
    || !llama_contributor_can(
        db(),
        $userId,
        'field_report'
    )
) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'Scout access is required.',
        ],
        403
    );
}

$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');

    if (is_string($rawInput) && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);

        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    if (!$input) {
        $input = $_POST;
    }
}

$slug = trim(
    (string) (
        $input['slug']
        ?? $_GET['slug']
        ?? ''
    )
);

if ($slug === '') {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'A Place slug is required.',
        ],
        400
    );
}

$place = place_member_by_slug($slug);

if (!$place) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'Place not found.',
        ],
        404
    );
}

$placeId = (int) ($place['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    llama_place_map_api_json([
        'ok' => true,
        'place' => [
            'id' => $placeId,
            'slug' => (string) $place['slug'],
            'name' => (string) $place['name'],
            'latitude' =>
                isset($place['latitude'])
                && is_numeric($place['latitude'])
                    ? (float) $place['latitude']
                    : null,
            'longitude' =>
                isset($place['longitude'])
                && is_numeric($place['longitude'])
                    ? (float) $place['longitude']
                    : null,
        ],
        'feature_types' =>
            llama_place_map_feature_types(),
        'features' =>
            llama_place_map_features(
                db(),
                $placeId,
                true
            ),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
        ],
        405
    );
}

$csrfToken = trim(
    (string) (
        $input['csrf_token']
        ?? ''
    )
);

if (
    $csrfToken === ''
    || !community_verify_csrf($csrfToken)
) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'Your session expired. Refresh and try again.',
        ],
        403
    );
}

$action = strtolower(
    trim(
        (string) (
            $input['action']
            ?? 'save'
        )
    )
);

try {
    if ($action === 'delete') {
        llama_place_map_feature_delete(
            db(),
            $placeId,
            (int) ($input['feature_id'] ?? 0),
            $userId
        );

        llama_place_map_api_json([
            'ok' => true,
            'features' =>
                llama_place_map_features(
                    db(),
                    $placeId,
                    true
                ),
        ]);
    }

    if ($action !== 'save') {
        throw new InvalidArgumentException(
            'Unknown mapped-area action.'
        );
    }

    $geometry = $input['geometry'] ?? null;

    if (!is_array($geometry)) {
        throw new InvalidArgumentException(
            'Mapped-area geometry is required.'
        );
    }

    $featureId = llama_place_map_feature_save(
        db(),
        $placeId,
        $userId,
        (string) ($input['feature_type'] ?? ''),
        (string) ($input['label'] ?? ''),
        $geometry,
        (int) ($input['feature_id'] ?? 0)
    );

    llama_place_map_api_json([
        'ok' => true,
        'feature_id' => $featureId,
        'features' =>
            llama_place_map_features(
                db(),
                $placeId,
                true
            ),
    ]);

} catch (InvalidArgumentException $exception) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => $exception->getMessage(),
        ],
        422
    );

} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.map_features.api',
        [
            'place_id' => $placeId,
            'user_id' => $userId,
            'action' => $action,
        ]
    );

    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'The mapped area could not be saved.',
        ],
        500
    );
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';

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

function llama_place_map_api_features(
    PDO $db,
    int $placeId
): array {
    $features =
        llama_place_map_features(
            $db,
            $placeId,
            true
        );

    if (!$features) {
        return [];
    }

    $associations =
        llama_place_campsite_map_associations(
            $db,
            $placeId
        );

    foreach ($features as &$feature) {
        $featureType =
            (string) (
                $feature['feature_type']
                ?? ''
            );

        if ($featureType !== 'camping_site') {
            continue;
        }

        $featureId =
            (int) (
                $feature['id']
                ?? 0
            );

        if (
            $featureId < 1
            || !isset(
                $associations[
                    $featureId
                ]
            )
        ) {
            continue;
        }

        if (
            !isset($feature['site_details'])
            || !is_array(
                $feature['site_details']
            )
        ) {
            $feature['site_details'] = [];
        }

        $association =
            $associations[
                $featureId
            ];

        $feature['site_details']['canonical_campsite_id'] =
            (int) $association['campsite_id'];

        $feature['site_details']['canonical_campsite_label'] =
            (string) $association['label'];

        $feature['site_details']['canonical_campsite_conflict'] =
            !empty(
                $association['conflict']
            );
    }
    unset($feature);

    return $features;
}

function llama_place_map_api_campsites(
    PDO $db,
    int $placeId
): array {
    return llama_place_campsite_map_options(
        $db,
        $placeId
    );
}

$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput =
        file_get_contents(
            'php://input'
        );

    if (
        is_string($rawInput)
        && trim($rawInput) !== ''
    ) {
        $decoded =
            json_decode(
                $rawInput,
                true
            );

        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    if (!$input) {
        $input = $_POST;
    }
}

$slug =
    trim(
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

$publicPlace =
    place_public_by_slug(
        $slug
    );

if (!$publicPlace) {
    llama_place_map_api_json(
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
    llama_place_map_api_json(
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
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' => 'Place not found.',
        ],
        404
    );
}

$db = db();

$canEdit =
    $userId > 0
    && llama_contributor_can(
        $db,
        $userId,
        'field_report'
    );

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    llama_place_map_api_json([
        'ok' => true,

        'coordinate_system' => [
            'name' => 'WGS 84',
            'crs' => LLAMA_PLACE_MAP_FEATURE_CRS,
            'srid' => LLAMA_PLACE_MAP_FEATURE_SRID,
            'geojson_order' => 'longitude,latitude',
        ],

        'can_edit' => $canEdit,

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

        'campsites' =>
            llama_place_map_api_campsites(
                $db,
                $placeId
            ),

        'features' =>
            llama_place_map_api_features(
                $db,
                $placeId
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

if (!$canEdit) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' =>
                'Scout access is required to edit mapped areas.',
        ],
        403
    );
}

$csrfToken =
    trim(
        (string) (
            $input['csrf_token']
            ?? ''
        )
    );

if (
    $csrfToken === ''
    || !community_verify_csrf(
        $csrfToken
    )
) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' =>
                'Your session expired. Refresh and try again.',
        ],
        403
    );
}

$action =
    strtolower(
        trim(
            (string) (
                $input['action']
                ?? 'save'
            )
        )
    );

try {
    if ($action === 'delete') {
        $featureId =
            (int) (
                $input['feature_id']
                ?? 0
            );

        $ownsTransaction =
            !$db->inTransaction();

        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            llama_place_map_feature_delete(
                $db,
                $placeId,
                $featureId,
                $userId
            );

            llama_place_campsite_map_association_clear(
                $db,
                $placeId,
                $featureId
            );

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $db->inTransaction()
            ) {
                $db->rollBack();
            }

            throw $exception;
        }

        llama_place_map_api_json([
            'ok' => true,

            'campsites' =>
                llama_place_map_api_campsites(
                    $db,
                    $placeId
                ),

            'features' =>
                llama_place_map_api_features(
                    $db,
                    $placeId
                ),
        ]);
    }

    if ($action !== 'save') {
        throw new InvalidArgumentException(
            'Unknown mapped-area action.'
        );
    }

    $geometry =
        $input['geometry']
        ?? null;

    if (!is_array($geometry)) {
        throw new InvalidArgumentException(
            'Mapped-area geometry is required.'
        );
    }

    $featureType =
        strtolower(
            trim(
                (string) (
                    $input['feature_type']
                    ?? ''
                )
            )
        );

    $areaDetails =
        $input['area_details']
        ?? [];

    $siteDetails =
        is_array(
            $input['site_details']
            ?? null
        )
            ? $input['site_details']
            : [];

    $canonicalCampsiteId =
        $featureType === 'camping_site'
            ? max(
                0,
                (int) (
                    $siteDetails[
                        'canonical_campsite_id'
                    ]
                    ?? 0
                )
            )
            : 0;

    /*
     * canonical_campsite_id belongs to the canonical campsite record, not the
     * place_map_camping_sites table. Remove it before passing site details to
     * the existing map-feature service.
     */
    unset(
        $siteDetails[
            'canonical_campsite_id'
        ]
    );

    $ownsTransaction =
        !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $featureId =
            llama_place_map_feature_save(
                $db,
                $placeId,
                $userId,
                $featureType,
                (string) (
                    $input['label']
                    ?? ''
                ),
                $geometry,
                (int) (
                    $input['feature_id']
                    ?? 0
                ),
                (string) (
                    $input['source_type']
                    ?? 'manual'
                ),
                $input['accuracy_m']
                    ?? null,
                $input['metadata']
                    ?? [],
                $areaDetails,
                $siteDetails,
                $input['rates']
                    ?? [],
                $input['site_classes']
                    ?? []
            );

        if ($featureType === 'camping_site') {
            llama_place_campsite_map_association_save(
                $db,
                $placeId,
                $featureId,
                $canonicalCampsiteId
            );
        } else {
            /*
             * If an existing mapped Site is deliberately converted into an
             * Area/boundary, it can no longer remain attached to a canonical
             * campsite.
             */
            llama_place_campsite_map_association_clear(
                $db,
                $placeId,
                $featureId
            );
        }

        if ($ownsTransaction) {
            $db->commit();
        }
    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }

    llama_place_map_api_json([
        'ok' => true,

        'feature_id' =>
            $featureId,

        'campsites' =>
            llama_place_map_api_campsites(
                $db,
                $placeId
            ),

        'features' =>
            llama_place_map_api_features(
                $db,
                $placeId
            ),
    ]);

} catch (
    InvalidArgumentException
    $exception
) {
    llama_place_map_api_json(
        [
            'ok' => false,
            'error' =>
                $exception->getMessage(),
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
            'error' =>
                'The mapped area could not be saved.',
        ],
        500
    );
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-site-identity.php';

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
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function llama_place_map_api_features(
    PDO $db,
    int $placeId
): array {
    /*
     * The map editor only needs map features here. Site identity is persisted
     * by the save path, and the production editor no longer exposes a separate
     * campsite-to-geometry linker.
     *
     * Avoid loading every active campsite and association just to decorate the
     * feature payload. On Places with imported or duplicated Site rows that
     * redundant work can be dramatically more expensive than the map itself.
     */
    return llama_place_map_features(
        $db,
        $placeId,
        true,
        true
    );
}

function llama_place_map_api_stats(
    PDO $db,
    int $placeId
): array {
    $featureStmt = $db->prepare(
        'SELECT
            COUNT(*) AS active_features,
            SUM(feature_type = "place_boundary")
                AS place_boundaries,
            SUM(feature_type IN ("camping_area", "parking_area"))
                AS areas,
            SUM(feature_type = "camping_site")
                AS site_features,
            COALESCE(
                SUM(
                    ST_NumPoints(
                        ST_ExteriorRing(geometry)
                    )
                ),
                0
            ) AS total_polygon_points,
            COALESCE(
                MAX(
                    ST_NumPoints(
                        ST_ExteriorRing(geometry)
                    )
                ),
                0
            ) AS max_polygon_points,
            COALESCE(
                SUM(
                    OCTET_LENGTH(metadata_json)
                ),
                0
            ) AS metadata_bytes,
            COALESCE(
                MAX(
                    OCTET_LENGTH(metadata_json)
                ),
                0
            ) AS max_metadata_bytes
         FROM place_map_features
         WHERE place_id = ?
           AND is_active = 1'
    );

    $featureStmt->execute([
        $placeId,
    ]);

    $featureStats =
        $featureStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $linkedStmt = $db->prepare(
        'SELECT
            COUNT(*) AS active_sites,
            COUNT(DISTINCT map_feature_id)
                AS linked_site_features
         FROM place_campsites
         WHERE place_id = ?
           AND is_active = 1'
    );

    $linkedStmt->execute([
        $placeId,
    ]);

    $siteStats =
        $linkedStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $pricingStmt = $db->prepare(
        'SELECT
            (
                SELECT COUNT(*)
                FROM place_map_feature_rates AS r
                INNER JOIN place_map_features AS f
                    ON f.id = r.feature_id
                WHERE f.place_id = ?
                  AND f.is_active = 1
                  AND r.is_active = 1
            ) AS feature_rates,
            (
                SELECT COUNT(*)
                FROM place_map_camping_site_classes AS c
                INNER JOIN place_map_features AS f
                    ON f.id = c.camping_area_feature_id
                WHERE f.place_id = ?
                  AND f.is_active = 1
                  AND c.is_active = 1
            ) AS site_classes,
            (
                SELECT COUNT(*)
                FROM place_map_camping_site_class_rates AS cr
                INNER JOIN place_map_camping_site_classes AS c
                    ON c.id = cr.site_class_id
                INNER JOIN place_map_features AS f
                    ON f.id = c.camping_area_feature_id
                WHERE f.place_id = ?
                  AND f.is_active = 1
                  AND c.is_active = 1
                  AND cr.is_active = 1
            ) AS site_class_rates'
    );

    $pricingStmt->execute([
        $placeId,
        $placeId,
        $placeId,
    ]);

    $pricingStats =
        $pricingStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $siteFeatures =
        max(
            0,
            (int) (
                $featureStats['site_features']
                ?? 0
            )
        );

    $linkedSiteFeatures =
        max(
            0,
            (int) (
                $siteStats['linked_site_features']
                ?? 0
            )
        );

    return [
        'active_features' =>
            max(
                0,
                (int) (
                    $featureStats['active_features']
                    ?? 0
                )
            ),

        'place_boundaries' =>
            max(
                0,
                (int) (
                    $featureStats['place_boundaries']
                    ?? 0
                )
            ),

        'areas' =>
            max(
                0,
                (int) (
                    $featureStats['areas']
                    ?? 0
                )
            ),

        'site_features' =>
            $siteFeatures,

        'active_sites' =>
            max(
                0,
                (int) (
                    $siteStats['active_sites']
                    ?? 0
                )
            ),

        'linked_site_features' =>
            $linkedSiteFeatures,

        'orphan_site_features' =>
            max(
                0,
                $siteFeatures
                - $linkedSiteFeatures
            ),

        'total_polygon_points' =>
            max(
                0,
                (int) (
                    $featureStats['total_polygon_points']
                    ?? 0
                )
            ),

        'max_polygon_points' =>
            max(
                0,
                (int) (
                    $featureStats['max_polygon_points']
                    ?? 0
                )
            ),

        'metadata_bytes' =>
            max(
                0,
                (int) (
                    $featureStats['metadata_bytes']
                    ?? 0
                )
            ),

        'max_metadata_bytes' =>
            max(
                0,
                (int) (
                    $featureStats['max_metadata_bytes']
                    ?? 0
                )
            ),

        'feature_rates' =>
            max(
                0,
                (int) (
                    $pricingStats['feature_rates']
                    ?? 0
                )
            ),

        'site_classes' =>
            max(
                0,
                (int) (
                    $pricingStats['site_classes']
                    ?? 0
                )
            ),

        'site_class_rates' =>
            max(
                0,
                (int) (
                    $pricingStats['site_class_rates']
                    ?? 0
                )
            ),
    ];
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

$mapStats =
    llama_place_map_api_stats(
        $db,
        $placeId
    );

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['diagnostic'])
) {
    llama_place_map_api_json([
        'ok' => true,
        'place_id' => $placeId,
        'slug' => (string) $place['slug'],
        'map' => $mapStats,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    /*
     * Protect the shared host from pathological historical data. Normal
     * Places are far below this threshold. If legitimate linked geometry ever
     * grows beyond it, the editor should be paged/lazy-loaded rather than
     * attempting to create hundreds of Leaflet layers in one request.
     */
    $editorFeatureCount =
        (int) $mapStats['place_boundaries']
        + (int) $mapStats['areas']
        + (int) $mapStats['linked_site_features'];

    if ($editorFeatureCount > 300) {
        llama_place_map_api_json(
            [
                'ok' => false,
                'error' =>
                    'This Place has '
                    . $editorFeatureCount
                    . ' active mapped records. The editor stopped before loading them all to protect site resources.',
                'map' => $mapStats,
            ],
            409
        );
    }
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

        'map' =>
            $mapStats,

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
        $featureLabel =
            trim(
                (string) (
                    $input['label']
                    ?? ''
                )
            );

        $featureId =
            llama_place_map_feature_save(
                $db,
                $placeId,
                $userId,
                $featureType,
                $featureLabel,
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
            /*
             * Selecting an existing canonical campsite still wins. When no
             * campsite was selected, create/reuse the canonical Site from the
             * mapped Site code so map-only pseudo-Sites do not accumulate.
             */
            if ($canonicalCampsiteId < 1) {
                $canonicalCampsiteId =
                    llama_place_site_identity_ensure(
                        $db,
                        $placeId,
                        $featureId,
                        $featureLabel,
                        $siteDetails
                    );
            } else {
                /*
                 * Compatibility path for an older client that explicitly
                 * supplies an existing Site. Normal production UI no longer
                 * exposes Site-to-geometry linking.
                 */
                llama_place_campsite_map_association_save(
                    $db,
                    $placeId,
                    $featureId,
                    $canonicalCampsiteId
                );
            }

        } else {
            /*
             * If an existing mapped Site is deliberately converted into an
             * Area/boundary, it can no longer remain attached to a canonical
             * campsite. The canonical Site itself is preserved.
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

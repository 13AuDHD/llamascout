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

/**
 * Every mapped Individual campsite must resolve to one canonical Site.
 *
 * The map feature owns geometry. place_campsites owns Site identity.
 * This helper reuses an existing Site when possible and creates the canonical
 * record only when the mapped Site does not already have one.
 */
function llama_place_map_api_ensure_canonical_campsite(
    PDO $db,
    int $placeId,
    int $featureId,
    string $label,
    array $siteDetails
): int {
    if (
        $placeId < 1
        || $featureId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid mapped Site is required.'
        );
    }

    /*
     * If this mapped feature already has an active canonical Site, preserve
     * that identity. Editing the polygon or Site details must never generate
     * another campsite record.
     */
    $existingAssociation =
        $db->prepare(
            'SELECT id
             FROM place_campsites
             WHERE place_id = ?
               AND map_feature_id = ?
               AND is_active = 1
             ORDER BY id ASC'
        );

    $existingAssociation->execute([
        $placeId,
        $featureId,
    ]);

    $associatedIds =
        array_values(
            array_map(
                'intval',
                $existingAssociation->fetchAll(
                    PDO::FETCH_COLUMN
                )
                ?: []
            )
        );

    if (count($associatedIds) > 1) {
        throw new InvalidArgumentException(
            'This mapped Site is linked to multiple active campsite records. Resolve that identity conflict before editing it.'
        );
    }

    if (count($associatedIds) === 1) {
        return $associatedIds[0];
    }

    $siteCode =
        trim(
            (string) (
                $siteDetails['site_code']
                ?? ''
            )
        );

    if ($siteCode === '') {
        throw new InvalidArgumentException(
            'Enter a Site number or Site code for an Individual campsite.'
        );
    }

    /*
     * Prefer an existing unlinked canonical Site with the exact same Site
     * code. This lets imported campground Sites acquire map geometry without
     * creating a duplicate identity.
     */
    $matching =
        $db->prepare(
            'SELECT
                id,
                map_feature_id
             FROM place_campsites
             WHERE place_id = ?
               AND site_code = ?
               AND is_active = 1
             ORDER BY id ASC
             FOR UPDATE'
        );

    $matching->execute([
        $placeId,
        $siteCode,
    ]);

    $matches =
        $matching->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    if (count($matches) > 1) {
        throw new InvalidArgumentException(
            'More than one active Site uses Site code "'
            . $siteCode
            . '". Resolve the duplicate Site identities before mapping this campsite.'
        );
    }

    if (count($matches) === 1) {
        $matchId =
            (int) (
                $matches[0]['id']
                ?? 0
            );

        $matchFeatureId =
            max(
                0,
                (int) (
                    $matches[0]['map_feature_id']
                    ?? 0
                )
            );

        if (
            $matchFeatureId > 0
            && $matchFeatureId !== $featureId
        ) {
            throw new InvalidArgumentException(
                'Site '
                . $siteCode
                . ' is already associated with another mapped Site.'
            );
        }

        if ($matchId > 0) {
            return $matchId;
        }
    }

    $siteName =
        trim(
            $label
        );

    $siteType =
        trim(
            (string) (
                $siteDetails['site_type']
                ?? ''
            )
        );

    $parkingStyle =
        trim(
            (string) (
                $siteDetails['parking_style']
                ?? ''
            )
        );

    $hookupStatus =
        trim(
            (string) (
                $siteDetails['hookup_status']
                ?? ''
            )
        );

    $accessibleStatus =
        trim(
            (string) (
                $siteDetails['accessible_status']
                ?? ''
            )
        );

    $insert =
        $db->prepare(
            'INSERT INTO place_campsites
            (
                place_id,
                map_feature_id,
                site_code,
                site_name,
                raw_site_type,
                site_type,
                parking_style,
                hookup_status,
                accessible_status,
                latitude,
                longitude,
                source_provider,
                source_external_id,
                is_active
            )
            VALUES
            (
                ?,
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NULL,
                NULL,
                ?,
                ?,
                1
            )'
        );

    $insert->execute([
        $placeId,
        $siteCode,
        $siteName !== ''
            ? $siteName
            : null,
        'Mapped Individual campsite',
        $siteType !== ''
            ? $siteType
            : null,
        $parkingStyle !== ''
            ? $parkingStyle
            : null,
        $hookupStatus !== ''
            ? $hookupStatus
            : null,
        $accessibleStatus !== ''
            ? $accessibleStatus
            : 'unknown',
        'Llama Scout',
        'mapped-site:' . $featureId,
    ]);

    $campsiteId =
        (int) $db->lastInsertId();

    if ($campsiteId < 1) {
        throw new RuntimeException(
            'The canonical Site record could not be created.'
        );
    }

    return $campsiteId;
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
                    llama_place_map_api_ensure_canonical_campsite(
                        $db,
                        $placeId,
                        $featureId,
                        $featureLabel,
                        $siteDetails
                    );
            }

            /*
             * The existing association service merges any map-only scoped
             * answers into the canonical Site before linking it.
             */
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

<?php

declare(strict_types=1);

require_once __DIR__ . '/place-campsites.php';

/**
 * Resolve one mapped camping_site to the single Site record that owns it.
 *
 * Geometry remains on place_map_features. Site identity remains on
 * place_campsites. This service is the only place allowed to bridge the two
 * automatically.
 *
 * Existing/imported Sites are preserved whenever they can be identified
 * safely. Historical map-only Scout Report answers are migrated by the
 * existing association service before the Site is linked.
 */
function llama_place_site_identity_ensure(
    PDO $db,
    int $placeId,
    int $featureId,
    ?string $label = null,
    ?array $siteDetails = null
): int {
    if (
        $placeId < 1
        || $featureId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid Place and Site are required.'
        );
    }

    $ownsTransaction =
        !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $featureStmt =
            $db->prepare(
                'SELECT
                    mf.id,
                    mf.label,
                    ms.site_code,
                    ms.site_type,
                    ms.parking_style,
                    ms.hookup_status,
                    ms.accessible_status
                 FROM place_map_features AS mf
                 LEFT JOIN place_map_camping_sites AS ms
                    ON ms.feature_id = mf.id
                 WHERE mf.id = ?
                   AND mf.place_id = ?
                   AND mf.feature_type = "camping_site"
                   AND mf.is_active = 1
                 LIMIT 1
                 FOR UPDATE'
            );

        $featureStmt->execute([
            $featureId,
            $placeId,
        ]);

        $feature =
            $featureStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($feature)) {
            throw new InvalidArgumentException(
                'The selected Site is not available for this Place.'
            );
        }

        $associationStmt =
            $db->prepare(
                'SELECT id
                 FROM place_campsites
                 WHERE place_id = ?
                   AND map_feature_id = ?
                   AND is_active = 1
                 ORDER BY id ASC
                 FOR UPDATE'
            );

        $associationStmt->execute([
            $placeId,
            $featureId,
        ]);

        $associatedIds =
            array_values(
                array_map(
                    'intval',
                    $associationStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    ?: []
                )
            );

        if (count($associatedIds) > 1) {
            throw new InvalidArgumentException(
                'This Site has more than one active Site record. Resolve the duplicate before continuing.'
            );
        }

        if (count($associatedIds) === 1) {
            $campsiteId =
                $associatedIds[0];

            if ($ownsTransaction) {
                $db->commit();
            }

            return $campsiteId;
        }

        $details =
            is_array($siteDetails)
                ? $siteDetails
                : [];

        $siteCode =
            trim(
                (string) (
                    $details['site_code']
                    ?? $feature['site_code']
                    ?? ''
                )
            );

        $siteLabel =
            trim(
                (string) (
                    $label
                    ?? $feature['label']
                    ?? ''
                )
            );

        $siteType =
            trim(
                (string) (
                    $details['site_type']
                    ?? $feature['site_type']
                    ?? ''
                )
            );

        $parkingStyle =
            trim(
                (string) (
                    $details['parking_style']
                    ?? $feature['parking_style']
                    ?? ''
                )
            );

        $hookupStatus =
            trim(
                (string) (
                    $details['hookup_status']
                    ?? $feature['hookup_status']
                    ?? ''
                )
            );

        $accessibleStatus =
            trim(
                (string) (
                    $details['accessible_status']
                    ?? $feature['accessible_status']
                    ?? ''
                )
            );

        $campsiteId = 0;

        /*
         * Records created from this exact mapped Site have the strongest
         * identity match even if a historical association was lost.
         */
        $sourceStmt =
            $db->prepare(
                'SELECT id
                 FROM place_campsites
                 WHERE place_id = ?
                   AND source_provider = ?
                   AND source_external_id = ?
                   AND is_active = 1
                 ORDER BY id ASC
                 FOR UPDATE'
            );

        $sourceStmt->execute([
            $placeId,
            'Llama Scout',
            'mapped-site:' . $featureId,
        ]);

        $sourceIds =
            array_values(
                array_map(
                    'intval',
                    $sourceStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    ?: []
                )
            );

        if (count($sourceIds) > 1) {
            throw new InvalidArgumentException(
                'This Site has duplicate internal Site records.'
            );
        }

        if (count($sourceIds) === 1) {
            $campsiteId =
                $sourceIds[0];
        }

        /*
         * Imported/existing Sites are reused by exact Site code only when that
         * code identifies one active Site unambiguously.
         */
        if (
            $campsiteId < 1
            && $siteCode !== ''
        ) {
            $matchingStmt =
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

            $matchingStmt->execute([
                $placeId,
                $siteCode,
            ]);

            $matches =
                $matchingStmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [];

            if (count($matches) > 1) {
                throw new InvalidArgumentException(
                    'More than one active Site uses Site code "'
                    . $siteCode
                    . '". Resolve the duplicate before continuing.'
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
                        . ' is already attached to another Site shape.'
                    );
                }

                $campsiteId =
                    $matchId;
            }
        }

        if ($campsiteId < 1) {
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
                $siteCode !== ''
                    ? $siteCode
                    : null,
                $siteLabel !== ''
                    ? $siteLabel
                    : null,
                'Llama Scout Site',
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
                    'The Site record could not be created.'
                );
            }
        }

        llama_place_campsite_map_association_save(
            $db,
            $placeId,
            $featureId,
            $campsiteId
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return $campsiteId;

    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }
}

/**
 * Convert a reporting target to the Site record used by persistence.
 *
 * This is used at write time so old map-only targets repair themselves
 * transparently instead of sending the Admin to another screen.
 */
function llama_place_site_identity_ensure_target(
    PDO $db,
    int $placeId,
    string $source,
    int $targetId
): int {
    if (
        $source === 'campsite_record'
        && $targetId > 0
    ) {
        if (
            !llama_place_campsite(
                $db,
                $placeId,
                $targetId
            )
        ) {
            throw new InvalidArgumentException(
                'The selected Site is not available for this Place.'
            );
        }

        return $targetId;
    }

    if (
        $source !== 'map_feature'
        || $targetId < 1
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Site.'
        );
    }

    return llama_place_site_identity_ensure(
        $db,
        $placeId,
        $targetId
    );
}

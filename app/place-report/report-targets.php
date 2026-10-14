<?php

declare(strict_types=1);

/**
 * Verified reporting targets for an existing Place.
 *
 * A physical Site can exist in two forms:
 *
 * 1. A canonical campsite record in place_campsites.
 * 2. A map-only camping_site feature in place_map_features.
 *
 * When a canonical campsite is associated with a mapped feature through
 * place_campsites.map_feature_id, the canonical campsite remains the Site
 * identity and the map feature is its geometry. That associated Site must
 * appear only once in the reporting target list.
 */

function llama_report_campsite_label(
    array $row,
    int $campsiteId,
    string $fallback = ''
): string {
    $siteName =
        trim(
            (string) (
                $row['site_name']
                ?? ''
            )
        );

    if ($siteName !== '') {
        return $siteName;
    }

    $siteCode =
        trim(
            (string) (
                $row['site_code']
                ?? ''
            )
        );

    if ($siteCode !== '') {
        return 'Site ' . $siteCode;
    }

    $fallback =
        trim(
            $fallback
        );

    if ($fallback !== '') {
        return $fallback;
    }

    return 'Site #' . $campsiteId;
}


function llama_report_targets(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'A valid Place ID is required.'
        );
    }

    $targets = [[
        'scope' =>
            'place',

        'source' =>
            'place',

        'id' =>
            $placeId,

        'parent_area_id' =>
            null,

        'map_feature_id' =>
            null,

        'label' =>
            'Entire Place',
    ]];

    /*
     * Track canonical campsite IDs already represented by an active mapped
     * Site. Those records must not be added a second time by the canonical
     * campsite query below.
     */
    $mappedCanonicalCampsiteIds = [];

    $stmt =
        $db->prepare(
            "SELECT
                mf.id,
                mf.feature_type,
                mf.label,

                sd.camping_area_feature_id,

                cs.id AS campsite_id,
                cs.site_code,
                cs.site_name

             FROM place_map_features AS mf

             LEFT JOIN place_map_camping_sites AS sd
                ON sd.feature_id = mf.id

             LEFT JOIN place_campsites AS cs
                ON cs.place_id = mf.place_id
               AND cs.map_feature_id = mf.id
               AND cs.is_active = 1

             WHERE mf.place_id = ?
               AND mf.is_active = 1
               AND mf.feature_type IN (
                    'camping_area',
                    'parking_area',
                    'camping_site'
               )

             ORDER BY
                CASE mf.feature_type
                    WHEN 'camping_area' THEN 0
                    WHEN 'parking_area' THEN 1
                    ELSE 2
                END,
                mf.label,
                mf.id"
        );

    $stmt->execute([
        $placeId,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $featureId =
            (int) (
                $row['id']
                ?? 0
            );

        if ($featureId < 1) {
            continue;
        }

        $featureType =
            (string) (
                $row['feature_type']
                ?? ''
            );

        $isArea =
            in_array(
                $featureType,
                [
                    'camping_area',
                    'parking_area',
                ],
                true
            );

        $parentAreaId =
            $isArea
                ? null
                : (
                    $row['camping_area_feature_id']
                    === null
                        ? null
                        : (int) $row[
                            'camping_area_feature_id'
                        ]
                );

        $mappedLabel =
            trim(
                (string) (
                    $row['label']
                    ?? ''
                )
            );

        if ($isArea) {
            $targets[] = [
                'scope' =>
                    'area',

                'source' =>
                    'map_feature',

                'id' =>
                    $featureId,

                'parent_area_id' =>
                    null,

                'map_feature_id' =>
                    $featureId,

                'label' =>
                    $mappedLabel !== ''
                        ? $mappedLabel
                        : 'Area #' . $featureId,
            ];

            continue;
        }

        /*
         * A mapped Site associated with place_campsites keeps the canonical
         * campsite as its reporting identity. The map feature remains attached
         * as geometry through map_feature_id.
         */
        $campsiteId =
            (int) (
                $row['campsite_id']
                ?? 0
            );

        if ($campsiteId > 0) {
            $mappedCanonicalCampsiteIds[
                $campsiteId
            ] = true;

            $targets[] = [
                'scope' =>
                    'site',

                'source' =>
                    'campsite_record',

                'id' =>
                    $campsiteId,

                'parent_area_id' =>
                    $parentAreaId,

                'map_feature_id' =>
                    $featureId,

                'label' =>
                    llama_report_campsite_label(
                        $row,
                        $campsiteId,
                        $mappedLabel
                    ),
            ];

            continue;
        }

        /*
         * A map-only Site has no canonical campsite record yet, so the mapped
         * feature itself remains the reporting identity.
         */
        $targets[] = [
            'scope' =>
                'site',

            'source' =>
                'map_feature',

            'id' =>
                $featureId,

            'parent_area_id' =>
                $parentAreaId,

            'map_feature_id' =>
                $featureId,

            'label' =>
                $mappedLabel !== ''
                    ? $mappedLabel
                    : 'Site #' . $featureId,
        ];
    }

    /*
     * Add canonical campsites that are not already represented above through
     * an active mapped Site.
     */
    $stmt =
        $db->prepare(
            'SELECT
                id,
                map_feature_id,
                site_code,
                site_name

             FROM place_campsites

             WHERE place_id = ?
               AND is_active = 1

             ORDER BY
                COALESCE(
                    NULLIF(site_code, ""),
                    NULLIF(site_name, ""),
                    CAST(id AS CHAR)
                ) ASC,
                id ASC'
        );

    $stmt->execute([
        $placeId,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $campsiteId =
            (int) (
                $row['id']
                ?? 0
            );

        if (
            $campsiteId < 1
            || isset(
                $mappedCanonicalCampsiteIds[
                    $campsiteId
                ]
            )
        ) {
            continue;
        }

        $mapFeatureId =
            max(
                0,
                (int) (
                    $row['map_feature_id']
                    ?? 0
                )
            );

        $targets[] = [
            'scope' =>
                'site',

            'source' =>
                'campsite_record',

            'id' =>
                $campsiteId,

            'parent_area_id' =>
                null,

            /*
             * This may reference an inactive/missing feature from historical
             * data. Consumers must still verify the feature before using it as
             * geometry.
             */
            'map_feature_id' =>
                $mapFeatureId > 0
                    ? $mapFeatureId
                    : null,

            'label' =>
                llama_report_campsite_label(
                    $row,
                    $campsiteId
                ),
        ];
    }

    return $targets;
}


/**
 * Validate an incoming target against a fresh, Place-scoped database read.
 *
 * Never trust browser-supplied scope IDs, sources, map associations, or parent
 * relationships.
 */
function llama_report_verified_target(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $id
): array {
    foreach (
        llama_report_targets(
            $db,
            $placeId
        )
        as $target
    ) {
        if (
            $target['scope'] === $scope
            && $target['source'] === $source
            && $target['id'] === $id
        ) {
            return $target;
        }
    }

    throw new InvalidArgumentException(
        'The requested reporting target does not belong to this Place.'
    );
}

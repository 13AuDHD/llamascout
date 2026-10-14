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

    $mappedCanonicalCampsiteIds = [];
    $activeMappedSiteIds = [];

    /*
     * Read all active mapped reporting features first. Multiple active
     * canonical campsite rows pointing at one mapped Site are an identity
     * conflict, not two legitimate report targets. Reject that state instead
     * of silently duplicating one physical Site in reporting.
     */
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
                mf.id,
                cs.id"
        );

    $stmt->execute([
        $placeId,
    ]);

    $rowsByFeature = [];

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

        if (!isset($rowsByFeature[$featureId])) {
            $rowsByFeature[$featureId] = [];
        }

        $rowsByFeature[$featureId][] = $row;
    }

    foreach ($rowsByFeature as $featureId => $rows) {
        $row = $rows[0];
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
                    (int) $featureId,

                'parent_area_id' =>
                    null,

                'map_feature_id' =>
                    (int) $featureId,

                'label' =>
                    $mappedLabel !== ''
                        ? $mappedLabel
                        : 'Area #' . $featureId,
            ];

            continue;
        }

        $activeMappedSiteIds[
            (int) $featureId
        ] = true;

        $canonicalRows =
            array_values(
                array_filter(
                    $rows,
                    static fn (array $candidate): bool =>
                        (int) (
                            $candidate['campsite_id']
                            ?? 0
                        ) > 0
                )
            );

        if (count($canonicalRows) > 1) {
            throw new RuntimeException(
                'Mapped Site #'
                . (int) $featureId
                . ' is linked to multiple active campsite records. '
                . 'Resolve the Site association conflict before using scoped reporting.'
            );
        }

        $parentAreaId =
            $row['camping_area_feature_id']
            === null
                ? null
                : (int) $row[
                    'camping_area_feature_id'
                ];

        if ($canonicalRows) {
            $canonicalRow =
                $canonicalRows[0];

            $campsiteId =
                (int) (
                    $canonicalRow['campsite_id']
                    ?? 0
                );

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
                    (int) $featureId,

                'label' =>
                    llama_report_campsite_label(
                        $canonicalRow,
                        $campsiteId,
                        $mappedLabel
                    ),
            ];

            continue;
        }

        $targets[] = [
            'scope' =>
                'site',

            'source' =>
                'map_feature',

            'id' =>
                (int) $featureId,

            'parent_area_id' =>
                $parentAreaId,

            'map_feature_id' =>
                (int) $featureId,

            'label' =>
                $mappedLabel !== ''
                    ? $mappedLabel
                    : 'Site #' . $featureId,
        ];
    }

    /*
     * Add canonical Sites that are not represented by an active mapped Site.
     * A stale or wrong-type stored map_feature_id is deliberately not exposed
     * as report geometry.
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

        $storedMapFeatureId =
            max(
                0,
                (int) (
                    $row['map_feature_id']
                    ?? 0
                )
            );

        /*
         * If this points at an active mapped Site it would already have been
         * represented above. Anything reaching this branch is canonical-only
         * for reporting purposes, even if a stale raw reference remains.
         */
        $mapFeatureId =
            isset(
                $activeMappedSiteIds[
                    $storedMapFeatureId
                ]
            )
                ? $storedMapFeatureId
                : 0;

        $targets[] = [
            'scope' =>
                'site',

            'source' =>
                'campsite_record',

            'id' =>
                $campsiteId,

            'parent_area_id' =>
                null,

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

<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report/scoped-answers.php';

function llama_place_experience_mode(array $place): string
{
    $type =
        strtolower(
            trim(
                (string) (
                    $place['type']
                    ?? ''
                )
            )
        );

    if (
        in_array(
            $type,
            [
                'developed-campground',
                'rv-park-resort',
            ],
            true
        )
    ) {
        return 'campground';
    }

    if (
        in_array(
            $type,
            [
                'dispersed-camping',
                'camping-area',
                'membership-host',
                'private-property',
                'fairgrounds',
            ],
            true
        )
    ) {
        return 'camping';
    }

    if (
        in_array(
            $type,
            [
                'rest-area',
                'public-parking',
                'travel-center',
                'truck-stop',
                'retail-parking',
                'restaurant-parking',
                'casino-parking',
                'medical-office-parking',
                'hospital-parking',
                'church-parking',
                'street-parking',
                'other-parking',
                'vehicle-pulloff',
            ],
            true
        )
    ) {
        return 'parking';
    }

    return 'place';
}

function llama_place_campsite_type_label(?string $value): string
{
    return match (
        strtolower(
            trim(
                (string) $value
            )
        )
    ) {
        'rv_site' =>
            'RV site',

        'tent_site' =>
            'Tent site',

        'mixed_site' =>
            'Tent / RV',

        'vehicle_site' =>
            'Vehicle site',

        'group_site' =>
            'Group site',

        default =>
            trim(
                (string) $value
            ),
    };
}

function llama_place_campsite_parking_label(?string $value): string
{
    return match (
        strtolower(
            trim(
                (string) $value
            )
        )
    ) {
        'pull_through' =>
            'Pull-through',

        'back_in' =>
            'Back-in',

        'pull_in' =>
            'Pull-in',

        'parallel' =>
            'Parallel',

        'unknown' =>
            'Unknown',

        default =>
            trim(
                (string) $value
            ),
    };
}

function llama_place_campsite_hookup_label(?string $value): string
{
    return match (
        strtolower(
            trim(
                (string) $value
            )
        )
    ) {
        'full' =>
            'Full hookups',

        'electric_water' =>
            'Electric + water',

        'electric_only' =>
            'Electric only',

        'water_only' =>
            'Water only',

        'none' =>
            'No hookups',

        'varies' =>
            'Varies',

        'unknown' =>
            'Unknown',

        default =>
            trim(
                (string) $value
            ),
    };
}

function llama_place_facility_facts(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_facility_facts
             WHERE place_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $placeId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        is_array($row)
            ? $row
            : [];
}

function llama_place_facility_features(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt =
        $db->prepare(
            'SELECT
                id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                source_updated_at
             FROM place_facility_features
             WHERE place_id = ?
               AND is_active = 1
             ORDER BY
                sort_order ASC,
                feature_key ASC,
                id ASC'
        );

    $stmt->execute([
        $placeId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}

function llama_place_campsites(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt =
        $db->prepare(
            'SELECT
                cs.id,
                cs.id AS feature_id,
                cs.map_feature_id,
                cs.site_code,
                cs.site_name,
                cs.raw_site_type,
                cs.site_type,
                cs.parking_style,
                cs.hookup_status,
                cs.accessible_status,
                cs.latitude,
                cs.longitude,

                cf.site_length_ft,
                cf.site_width_ft,
                cf.driveway_length_ft,
                cf.driveway_grade,
                cf.driveway_surface,
                cf.overhead_clearance_ft,
                cf.max_vehicle_length_ft,
                cf.max_people,
                cf.max_vehicles,
                cf.min_people,
                cf.min_vehicles,

                cf.tent_pad,
                cf.tent_pad_length_ft,
                cf.tent_pad_width_ft,

                cf.electric_hookup,
                cf.electric_service,
                cf.water_hookup,
                cf.sewer_hookup,

                cf.checkin_time,
                cf.checkout_time,

                cf.proximity_to_water,
                cf.shade_source_value,
                cf.privacy_source_value,
                cf.quiet_area_source_value,

                cf.max_horses,
                cf.bed_type,
                cf.bed_count,
                cf.bedroom_count,
                cf.room_count,
                cf.shower_bath_type,

                cf.internal_map_x,
                cf.internal_map_y,
                cf.internal_map_placed,

                cf.source_provider,
                cf.source_external_id,
                cf.source_updated_at,
                cf.source_url

             FROM place_campsites cs
             LEFT JOIN place_campsite_facts cf
                ON cf.campsite_id = cs.id
             WHERE cs.place_id = ?
               AND cs.is_active = 1
             ORDER BY
                COALESCE(
                    NULLIF(cs.site_code, ""),
                    NULLIF(cs.site_name, ""),
                    CAST(cs.id AS CHAR)
                ) ASC,
                cs.id ASC'
        );

    $stmt->execute([
        $placeId,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    if (!$rows) {
        return [];
    }

    $siteIds =
        array_values(
            array_map(
                static fn (
                    array $row
                ): int =>
                    (int) $row['id'],
                $rows
            )
        );

    $featuresBySite = [];

    if ($siteIds) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($siteIds),
                    '?'
                )
            );

        $featureStmt =
            $db->prepare(
                'SELECT
                    campsite_id,
                    feature_key,
                    feature_value,
                    qualifier,
                    source_provider,
                    source_external_id,
                    source_updated_at
                 FROM place_campsite_features
                 WHERE campsite_id IN ('
                    . $placeholders
                    . ')
                   AND is_active = 1
                 ORDER BY
                    sort_order ASC,
                    feature_key ASC,
                    id ASC'
            );

        $featureStmt->execute(
            $siteIds
        );

        foreach (
            $featureStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as $feature
        ) {
            $siteId =
                (int) (
                    $feature['campsite_id']
                    ?? 0
                );

            if ($siteId > 0) {
                $featuresBySite[
                    $siteId
                ][] =
                    $feature;
            }
        }
    }

    foreach ($rows as &$row) {
        $siteId =
            (int) $row['id'];

        $row['features'] =
            $featuresBySite[
                $siteId
            ]
            ?? [];

        $row['display_name'] =
            trim(
                (string) (
                    $row['site_code']
                    ?? ''
                )
            );

        if ($row['display_name'] === '') {
            $row['display_name'] =
                trim(
                    (string) (
                        $row['site_name']
                        ?? ''
                    )
                );
        }

        if ($row['display_name'] === '') {
            $row['display_name'] =
                'Site ' . $siteId;
        }
    }

    unset(
        $row
    );

    return $rows;
}

function llama_place_campsite(
    PDO $db,
    int $placeId,
    int $campsiteId
): array {
    if (
        $placeId < 1
        || $campsiteId < 1
    ) {
        return [];
    }

    foreach (
        llama_place_campsites(
            $db,
            $placeId
        )
        as $site
    ) {
        if (
            (int) (
                $site['id']
                ?? 0
            )
            === $campsiteId
        ) {
            return $site;
        }
    }

    return [];
}

function llama_place_campsite_map_options(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    /*
     * map_feature_id is the ACTIVE Site association exposed to consumers.
     * stored_map_feature_id preserves the raw database reference so Admin can
     * surface stale historical links without treating them as live geometry.
     */
    $stmt =
        $db->prepare(
            'SELECT
                cs.id,
                cs.map_feature_id
                    AS stored_map_feature_id,
                active_map.id
                    AS active_map_feature_id,
                cs.site_code,
                cs.site_name
             FROM place_campsites AS cs
             LEFT JOIN place_map_features AS active_map
                ON active_map.id = cs.map_feature_id
               AND active_map.place_id = cs.place_id
               AND active_map.feature_type = "camping_site"
               AND active_map.is_active = 1
             WHERE cs.place_id = ?
               AND cs.is_active = 1
             ORDER BY
                COALESCE(
                    NULLIF(cs.site_code, ""),
                    NULLIF(cs.site_name, ""),
                    CAST(cs.id AS CHAR)
                ) ASC,
                cs.id ASC'
        );

    $stmt->execute([
        $placeId,
    ]);

    $options = [];

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

        if ($campsiteId < 1) {
            continue;
        }

        $siteCode =
            trim(
                (string) (
                    $row['site_code']
                    ?? ''
                )
            );

        $siteName =
            trim(
                (string) (
                    $row['site_name']
                    ?? ''
                )
            );

        if (
            $siteCode !== ''
            && $siteName !== ''
        ) {
            $label =
                'Site '
                . $siteCode
                . ' Â· '
                . $siteName;

        } elseif ($siteCode !== '') {
            $label =
                'Site '
                . $siteCode;

        } elseif ($siteName !== '') {
            $label =
                $siteName;

        } else {
            $label =
                'Site #'
                . $campsiteId;
        }

        $activeMapFeatureId =
            max(
                0,
                (int) (
                    $row['active_map_feature_id']
                    ?? 0
                )
            );

        $storedMapFeatureId =
            max(
                0,
                (int) (
                    $row['stored_map_feature_id']
                    ?? 0
                )
            );

        $options[] = [
            'id' =>
                $campsiteId,

            'label' =>
                $label,

            'site_code' =>
                $siteCode,

            'site_name' =>
                $siteName,

            'map_feature_id' =>
                $activeMapFeatureId > 0
                    ? $activeMapFeatureId
                    : null,

            'stored_map_feature_id' =>
                $storedMapFeatureId > 0
                    ? $storedMapFeatureId
                    : null,

            'stale_map_feature_id' =>
                $storedMapFeatureId > 0
                && $activeMapFeatureId < 1
                    ? $storedMapFeatureId
                    : null,
        ];
    }

    return $options;
}

function llama_place_campsite_map_associations(
    PDO $db,
    int $placeId
): array {
    $associations = [];

    foreach (
        llama_place_campsite_map_options(
            $db,
            $placeId
        )
        as $site
    ) {
        $mapFeatureId =
            (int) (
                $site['map_feature_id']
                ?? 0
            );

        if ($mapFeatureId < 1) {
            continue;
        }

        if (
            !isset(
                $associations[
                    $mapFeatureId
                ]
            )
        ) {
            $associations[
                $mapFeatureId
            ] = [
                'campsite_id' =>
                    (int) $site['id'],

                'label' =>
                    (string) $site['label'],

                'conflict' =>
                    false,
            ];

            continue;
        }

        /*
         * Historical data may predate one-to-one enforcement. Surface that
         * state instead of silently pretending the association is clean.
         */
        $associations[
            $mapFeatureId
        ]['conflict'] =
            true;
    }

    return $associations;
}

function llama_place_campsite_map_association_clear(
    PDO $db,
    int $placeId,
    int $featureId
): void {
    if (
        $placeId < 1
        || $featureId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid Place and mapped Site are required.'
        );
    }

    $stmt =
        $db->prepare(
            'UPDATE place_campsites
             SET map_feature_id = NULL
             WHERE place_id = ?
               AND map_feature_id = ?'
        );

    $stmt->execute([
        $placeId,
        $featureId,
    ]);
}

function llama_place_campsite_map_association_save(
    PDO $db,
    int $placeId,
    int $featureId,
    int $campsiteId
): void {
    if (
        $placeId < 1
        || $featureId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid Place and mapped Site are required.'
        );
    }

    if ($campsiteId < 1) {
        llama_place_campsite_map_association_clear(
            $db,
            $placeId,
            $featureId
        );

        return;
    }

    $ownsTransaction =
        !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        /*
         * Lock and validate the mapped feature itself. Association is only
         * valid for an active camping_site belonging to this Place.
         */
        $featureStmt =
            $db->prepare(
                'SELECT
                    id,
                    feature_type
                 FROM place_map_features
                 WHERE id = ?
                   AND place_id = ?
                   AND is_active = 1
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

        if (
            !is_array($feature)
            || (string) (
                $feature['feature_type']
                ?? ''
            ) !== 'camping_site'
        ) {
            throw new InvalidArgumentException(
                'The selected mapped feature is not an active Site for this Place.'
            );
        }

        $stmt =
            $db->prepare(
                'SELECT
                    id,
                    map_feature_id
                 FROM place_campsites
                 WHERE id = ?
                   AND place_id = ?
                   AND is_active = 1
                 LIMIT 1
                 FOR UPDATE'
            );

        $stmt->execute([
            $campsiteId,
            $placeId,
        ]);

        $site =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($site)) {
            throw new InvalidArgumentException(
                'The selected campsite is not available for this Place.'
            );
        }

        $currentFeatureId =
            max(
                0,
                (int) (
                    $site['map_feature_id']
                    ?? 0
                )
            );

        if (
            $currentFeatureId > 0
            && $currentFeatureId
                !== $featureId
        ) {
            $currentFeatureStmt =
                $db->prepare(
                    'SELECT id
                     FROM place_map_features
                     WHERE id = ?
                       AND place_id = ?
                       AND is_active = 1
                     LIMIT 1
                     FOR UPDATE'
                );

            $currentFeatureStmt->execute([
                $currentFeatureId,
                $placeId,
            ]);

            if (
                $currentFeatureStmt
                    ->fetchColumn()
            ) {
                throw new InvalidArgumentException(
                    'That campsite is already associated with another active mapped Site.'
                );
            }
        }

        /*
         * Historical data may contain more than one active canonical campsite
         * pointing at the same mapped Site. Do not silently pick a winner.
         */
        $existingStmt =
            $db->prepare(
                'SELECT id
                 FROM place_campsites
                 WHERE place_id = ?
                   AND map_feature_id = ?
                   AND is_active = 1
                 ORDER BY id ASC
                 FOR UPDATE'
            );

        $existingStmt->execute([
            $placeId,
            $featureId,
        ]);

        $existingIds =
            array_values(
                array_map(
                    'intval',
                    $existingStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    ?: []
                )
            );

        if (count($existingIds) > 1) {
            throw new InvalidArgumentException(
                'This mapped Site is linked to multiple active campsite records. Resolve that historical conflict before changing the association.'
            );
        }

        /*
         * Once linked, the canonical campsite becomes the Site identity used
         * by reporting. Move any direct map-only Site answers first so the
         * association cannot make existing Scout Report data disappear.
         *
         * The merge throws before writing if the two identities disagree on
         * any field. Because this runs inside the same transaction, the map
         * feature and campsite association remain unchanged on conflict.
         */
        llama_scoped_report_merge_site_identity(
            $db,
            $placeId,
            $featureId,
            $campsiteId
        );

        /*
         * A map feature can represent only one canonical campsite. Selecting
         * a different single campsite deliberately moves the association.
         */
        $clearStmt =
            $db->prepare(
                'UPDATE place_campsites
                 SET map_feature_id = NULL
                 WHERE place_id = ?
                   AND map_feature_id = ?
                   AND id <> ?'
            );

        $clearStmt->execute([
            $placeId,
            $featureId,
            $campsiteId,
        ]);

        $saveStmt =
            $db->prepare(
                'UPDATE place_campsites
                 SET map_feature_id = ?
                 WHERE id = ?
                   AND place_id = ?
                   AND is_active = 1
                 LIMIT 1'
            );

        $saveStmt->execute([
            $featureId,
            $campsiteId,
            $placeId,
        ]);

        if (
            $saveStmt->rowCount() < 1
            && $currentFeatureId
                !== $featureId
        ) {
            throw new RuntimeException(
                'The campsite association could not be saved.'
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
}

function llama_place_campsite_browser_state(
    array $place,
    array $sites
): array {
    $mode =
        llama_place_experience_mode(
            $place
        );

    $siteCount =
        count(
            $sites
        );

    /*
     * Site existence determines Site visibility.
     *
     * A Place may contain legitimate Sites regardless of whether its overall
     * experience mode is campground, camping, parking, or another Place type.
     * The mode still describes the Place and can influence presentation, but
     * it must never hide real Site records.
     */
    $showBrowser =
        $siteCount > 1;

    $showSingleSite =
        $siteCount === 1;

    return [
        'mode' =>
            $mode,

        'site_count' =>
            $siteCount,

        'show_browser' =>
            $showBrowser,

        'show_single_site' =>
            $showSingleSite,

        'show_site_layer' =>
            $siteCount > 0,
    ];
}

function llama_place_campsite_summary(
    array $site
): array {
    $summary = [];

    $siteType =
        llama_place_campsite_type_label(
            (string) (
                $site['site_type']
                ?? ''
            )
        );

    if ($siteType !== '') {
        $summary[] =
            $siteType;
    }

    $parking =
        llama_place_campsite_parking_label(
            (string) (
                $site['parking_style']
                ?? ''
            )
        );

    if (
        $parking !== ''
        && $parking !== 'Unknown'
    ) {
        $summary[] =
            $parking;
    }

    if (
        isset(
            $site[
                'max_vehicle_length_ft'
            ]
        )
        && is_numeric(
            $site[
                'max_vehicle_length_ft'
            ]
        )
        && (float) $site[
            'max_vehicle_length_ft'
        ] > 0
    ) {
        $summary[] =
            rtrim(
                rtrim(
                    number_format(
                        (float) $site[
                            'max_vehicle_length_ft'
                        ],
                        1
                    ),
                    '0'
                ),
                '.'
            )
            . ' ft max vehicle';
    }

    $hookups =
        llama_place_campsite_hookup_label(
            (string) (
                $site['hookup_status']
                ?? ''
            )
        );

    if (
        $hookups !== ''
        && $hookups !== 'Unknown'
    ) {
        $summary[] =
            $hookups;
    }

    return $summary;
}

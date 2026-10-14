<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-import.php';
require_once __DIR__ . '/ridb-public-presentation.php';
require_once __DIR__ . '/ridb-report-consolidation.php';

/**
 * Promote useful RIDB fields that do not belong in Scout-owned Place answers.
 *
 * Everything written here remains source-owned/source-attributed.
 */

function llama_ridb_useful_decode(
    mixed $json
): array {
    if (
        !is_string($json)
        || trim($json) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_ridb_useful_cached_facility(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM ridb_facilities
             WHERE ridb_facility_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $facilityId,
    ]);

    return llama_ridb_useful_decode(
        $stmt->fetchColumn()
    );
}

function llama_ridb_useful_related(
    PDO $ridbDb,
    string $table,
    string $facilityId
): array {
    $allowed = [
        'ridb_facility_addresses',
        'ridb_media',
        'ridb_links',
        'ridb_activities',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Unsupported RIDB useful-field source.'
        );
    }

    $stmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM '
            . $table
            . '
             WHERE ridb_facility_id = ?
             ORDER BY source_record_id ASC'
        );

    $stmt->execute([
        $facilityId,
    ]);

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
        ?: []
        as $json
    ) {
        $row =
            llama_ridb_useful_decode(
                $json
            );

        if ($row) {
            $rows[] =
                $row;
        }
    }

    return $rows;
}

function llama_ridb_useful_address_priority(
    array $address
): int {
    $type =
        strtoupper(
            trim(
                (string) llama_ridb_record_value(
                    $address,
                    [
                        'FacilityAddressType',
                        'AddressType',
                    ],
                    ''
                )
            )
        );

    return match ($type) {
        'PHYSICAL' => 0,
        'DEFAULT' => 1,
        'MAILING' => 2,
        default => 3,
    };
}

function llama_ridb_useful_preferred_address(
    array $addresses
): array {
    if (!$addresses) {
        return [];
    }

    usort(
        $addresses,
        static fn (
            array $a,
            array $b
        ): int =>
            llama_ridb_useful_address_priority($a)
            <=>
            llama_ridb_useful_address_priority($b)
    );

    return $addresses[0]
        ?? [];
}

function llama_ridb_useful_upsert_facility_facts(
    PDO $mainDb,
    int $placeId,
    string $facilityId,
    array $facility,
    array $addresses
): int {
    if ($placeId < 1) {
        return 0;
    }

    $address =
        llama_ridb_useful_preferred_address(
            $addresses
        );

    $address1 =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                [
                    'FacilityStreetAddress1',
                    'StreetAddress1',
                    'AddressLine1',
                ]
            )
        );

    $address2 =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                [
                    'FacilityStreetAddress2',
                    'StreetAddress2',
                    'AddressLine2',
                ]
            )
        );

    $address3 =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                [
                    'FacilityStreetAddress3',
                    'StreetAddress3',
                    'AddressLine3',
                ]
            )
        );

    $addressType =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $address,
                [
                    'FacilityAddressType',
                    'AddressType',
                ]
            )
        );

    $addressUpdated =
        llama_ridb_import_datetime(
            llama_ridb_record_value(
                $address,
                [
                    'LastUpdatedDate',
                    'lastUpdatedDate',
                ]
            )
        );

    $accessibilityText =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityAccessibilityText',
                    'facilityAccessibilityText',
                ]
            )
        );

    $adaAccess =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityAdaAccess',
                    'FacilityADAAccess',
                    'facilityAdaAccess',
                ]
            )
        );

    $mapUrl =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityMapURL',
                    'facilityMapURL',
                    'FacilityMapUrl',
                ]
            )
        );

    if (
        $mapUrl !== null
        && preg_match(
            '#^https?://#i',
            $mapUrl
        ) !== 1
    ) {
        $mapUrl = null;
    }

    $stayLimit =
        llama_ridb_import_text(
            llama_ridb_record_value(
                $facility,
                [
                    'StayLimit',
                    'stayLimit',
                ]
            )
        );

    $stmt =
        $mainDb->prepare(
            'UPDATE place_facility_facts
             SET
                official_address_1 =
                    COALESCE(
                        ?,
                        official_address_1
                    ),
                official_address_2 =
                    COALESCE(
                        ?,
                        official_address_2
                    ),
                official_address_3 = ?,
                official_address_type = ?,
                official_address_updated_at = ?,
                accessibility_text = ?,
                ada_access = ?,
                facility_map_url = ?,
                stay_limit = ?
             WHERE place_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $address1,
        $address2,
        $address3,
        $addressType,
        $addressUpdated,
        $accessibilityText,
        $adaAccess,
        $mapUrl,
        $stayLimit,
        $placeId,
    ]);

    $count = 0;

    foreach (
        [
            $address3,
            $addressType,
            $addressUpdated,
            $accessibilityText,
            $adaAccess,
            $mapUrl,
            $stayLimit,
        ]
        as $value
    ) {
        if ($value !== null && $value !== '') {
            $count++;
        }
    }

    return $count;
}

function llama_ridb_useful_replace_activity_details(
    PDO $mainDb,
    int $placeId,
    string $facilityId,
    array $activities
): int {
    $delete =
        $mainDb->prepare(
            'DELETE FROM place_facility_features
             WHERE place_id = ?
               AND source_provider = ?
               AND feature_key IN (
                    "activity_description",
                    "activity_fee_description"
               )'
        );

    $delete->execute([
        $placeId,
        'Recreation.gov / RIDB',
    ]);

    if (!$activities) {
        return 0;
    }

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_facility_features
            (
                place_id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                sort_order,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );

    $count = 0;
    $sort = 100;

    foreach ($activities as $index => $activity) {
        if (!is_array($activity)) {
            continue;
        }

        $name =
            llama_ridb_import_text(
                llama_ridb_record_value(
                    $activity,
                    [
                        'ActivityName',
                        'activityName',
                    ]
                )
            );

        $externalId =
            trim(
                (string) llama_ridb_record_value(
                    $activity,
                    [
                        'ActivityID',
                        'activityID',
                    ],
                    ''
                )
            );

        if ($externalId === '') {
            $externalId =
                $facilityId
                . ':activity:'
                . ($index + 1);
        }

        $description =
            llama_ridb_import_text(
                llama_ridb_record_value(
                    $activity,
                    [
                        'FacilityActivityDescription',
                        'facilityActivityDescription',
                    ]
                )
            );

        $fee =
            llama_ridb_import_text(
                llama_ridb_record_value(
                    $activity,
                    [
                        'FacilityActivityFeeDescription',
                        'facilityActivityFeeDescription',
                    ]
                )
            );

        foreach (
            [
                'activity_description' => $description,
                'activity_fee_description' => $fee,
            ]
            as $key => $value
        ) {
            if ($value === null) {
                continue;
            }

            $insert->execute([
                $placeId,
                $key,
                $value,
                $name,
                'Recreation.gov / RIDB',
                $externalId,
                $sort,
            ]);

            $sort += 10;
            $count++;
        }
    }

    return $count;
}

function llama_ridb_useful_replace_link_descriptions(
    PDO $mainDb,
    int $placeId,
    string $facilityId,
    array $links
): int {
    $delete =
        $mainDb->prepare(
            'DELETE FROM place_facility_features
             WHERE place_id = ?
               AND source_provider = ?
               AND feature_key = ?'
        );

    $delete->execute([
        $placeId,
        'Recreation.gov / RIDB',
        'source_link_description',
    ]);

    if (!$links) {
        return 0;
    }

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_facility_features
            (
                place_id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                sort_order,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );

    $count = 0;
    $sort = 1200;

    foreach ($links as $index => $link) {
        if (!is_array($link)) {
            continue;
        }

        $description =
            llama_ridb_import_text(
                llama_ridb_record_value(
                    $link,
                    [
                        'Description',
                        'LinkDescription',
                        'description',
                    ]
                )
            );

        if ($description === null) {
            continue;
        }

        $url =
            trim(
                (string) llama_ridb_record_value(
                    $link,
                    [
                        'URL',
                        'Url',
                        'LinkURL',
                        'linkURL',
                    ],
                    ''
                )
            );

        $externalId =
            trim(
                (string) llama_ridb_record_value(
                    $link,
                    [
                        'EntityLinkID',
                        'LinkID',
                        'linkID',
                    ],
                    ''
                )
            );

        if ($externalId === '') {
            $externalId =
                $facilityId
                . ':link-description:'
                . ($index + 1);
        }

        $insert->execute([
            $placeId,
            'source_link_description',
            $description,
            $url !== ''
                ? $url
                : null,
            'Recreation.gov / RIDB',
            $externalId,
            $sort,
        ]);

        $sort += 10;
        $count++;
    }

    return $count;
}

function llama_ridb_useful_media_rank(
    array $media
): int {
    if (!empty(
        $media['IsPrimary']
        ?? $media['isPrimary']
        ?? false
    )) {
        return 0;
    }

    if (!empty(
        $media['IsPreview']
        ?? $media['isPreview']
        ?? false
    )) {
        return 1;
    }

    if (!empty(
        $media['IsGallery']
        ?? $media['isGallery']
        ?? false
    )) {
        return 2;
    }

    return 3;
}

function llama_ridb_useful_apply_place_media_flags(
    PDO $mainDb,
    int $placeId,
    array $media
): int {
    $records = [];

    foreach ($media as $item) {
        if (!is_array($item)) {
            continue;
        }

        $url =
            llama_ridb_public_media_url(
                $item
            );

        if (
            $url === null
            || !llama_ridb_public_media_is_image(
                $item,
                $url
            )
        ) {
            continue;
        }

        $records[] = [
            'url' => $url,
            'rank' =>
                llama_ridb_useful_media_rank(
                    $item
                ),
        ];
    }

    if (!$records) {
        return 0;
    }

    usort(
        $records,
        static fn (
            array $a,
            array $b
        ): int =>
            $a['rank']
            <=>
            $b['rank']
    );

    $sourceUrls =
        array_values(
            array_unique(
                array_column(
                    $records,
                    'url'
                )
            )
        );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($sourceUrls),
                '?'
            )
        );

    $featuredStmt =
        $mainDb->prepare(
            'SELECT src
             FROM place_images
             WHERE place_id = ?
               AND is_featured = 1
             LIMIT 1'
        );

    $featuredStmt->execute([
        $placeId,
    ]);

    $featuredUrl =
        trim(
            (string) (
                $featuredStmt->fetchColumn()
                ?: ''
            )
        );

    /*
     * Do not replace a Scout/non-RIDB featured photo.
     */
    $featuredIsRidb =
        $featuredUrl !== ''
        && in_array(
            $featuredUrl,
            $sourceUrls,
            true
        );

    $updated = 0;

    if (
        $featuredUrl === ''
        || $featuredIsRidb
    ) {
        $preferred =
            (string) (
                $records[0]['url']
                ?? ''
            );

        if ($preferred !== '') {
            $clear =
                $mainDb->prepare(
                    'UPDATE place_images
                     SET is_featured = 0
                     WHERE place_id = ?
                       AND src IN ('
                    . $placeholders
                    . ')'
                );

            $clear->execute(
                array_merge(
                    [$placeId],
                    $sourceUrls
                )
            );

            $set =
                $mainDb->prepare(
                    'UPDATE place_images
                     SET is_featured = 1
                     WHERE place_id = ?
                       AND src = ?
                     LIMIT 1'
                );

            $set->execute([
                $placeId,
                $preferred,
            ]);

            $updated +=
                $set->rowCount();
        }
    }

    /*
     * Reorder only source images relative to their existing source-image
     * positions. This does not move or renumber Scout uploads.
     */
    $positionsStmt =
        $mainDb->prepare(
            'SELECT sort_order
             FROM place_images
             WHERE place_id = ?
               AND src IN ('
            . $placeholders
            . ')
             ORDER BY sort_order ASC, id ASC'
        );

    $positionsStmt->execute(
        array_merge(
            [$placeId],
            $sourceUrls
        )
    );

    $positions =
        array_map(
            'intval',
            $positionsStmt->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: []
        );

    $update =
        $mainDb->prepare(
            'UPDATE place_images
             SET sort_order = ?
             WHERE place_id = ?
               AND src = ?
             LIMIT 1'
        );

    foreach ($records as $index => $record) {
        if (!isset($positions[$index])) {
            break;
        }

        $update->execute([
            $positions[$index],
            $placeId,
            $record['url'],
        ]);

        $updated +=
            $update->rowCount();
    }

    return $updated;
}

function llama_ridb_useful_sync(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): array {
    $facility =
        llama_ridb_useful_cached_facility(
            $ridbDb,
            $facilityId
        );

    if (!$facility) {
        throw new RuntimeException(
            'RIDB facility source record is missing from the local cache.'
        );
    }

    $addresses =
        llama_ridb_useful_related(
            $ridbDb,
            'ridb_facility_addresses',
            $facilityId
        );

    $activities =
        llama_ridb_useful_related(
            $ridbDb,
            'ridb_activities',
            $facilityId
        );

    $links =
        llama_ridb_useful_related(
            $ridbDb,
            'ridb_links',
            $facilityId
        );

    $media =
        llama_ridb_useful_related(
            $ridbDb,
            'ridb_media',
            $facilityId
        );

    $reportConsolidation =
        llama_ridb_report_consolidation_sync(
            $mainDb,
            $ridbDb,
            $placeId,
            $facilityId
        );

    return [
        'report_fields_imported' =>
            (int) (
                $reportConsolidation[
                    'report_fields_imported'
                ]
                ?? 0
            ),

        'useful_facility_facts' =>
            llama_ridb_useful_upsert_facility_facts(
                $mainDb,
                $placeId,
                $facilityId,
                $facility,
                $addresses
            ),

        'activity_details' =>
            llama_ridb_useful_replace_activity_details(
                $mainDb,
                $placeId,
                $facilityId,
                $activities
            ),

        'link_descriptions' =>
            llama_ridb_useful_replace_link_descriptions(
                $mainDb,
                $placeId,
                $facilityId,
                $links
            ),

        'media_priority_updates' =>
            llama_ridb_useful_apply_place_media_flags(
                $mainDb,
                $placeId,
                $media
            ),
    ];
}

function llama_ridb_useful_public_data(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    $factsStmt =
        $db->prepare(
            'SELECT
                official_address_3,
                official_address_type,
                official_address_updated_at,
                accessibility_text,
                ada_access,
                facility_map_url,
                stay_limit
             FROM place_facility_facts
             WHERE place_id = ?
             LIMIT 1'
        );

    $factsStmt->execute([
        $placeId,
    ]);

    $facts =
        $factsStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: [];

    $featureStmt =
        $db->prepare(
            'SELECT
                feature_key,
                feature_value,
                qualifier,
                source_external_id
             FROM place_facility_features
             WHERE place_id = ?
               AND source_provider = ?
               AND feature_key IN (
                    "activity_description",
                    "activity_fee_description",
                    "source_link_description"
               )
               AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );

    $featureStmt->execute([
        $placeId,
        'Recreation.gov / RIDB',
    ]);

    $activityDetails = [];
    $linkDescriptions = [];

    foreach (
        $featureStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $key =
            (string) (
                $row['feature_key']
                ?? ''
            );

        if (
            $key === 'activity_description'
            || $key === 'activity_fee_description'
        ) {
            $id =
                (string) (
                    $row['source_external_id']
                    ?? ''
                );

            $activityDetails[$id] ??= [
                'name' =>
                    trim(
                        (string) (
                            $row['qualifier']
                            ?? ''
                        )
                    ),
                'description' => '',
                'fee_description' => '',
            ];

            $value =
                trim(
                    (string) (
                        $row['feature_value']
                        ?? ''
                    )
                );

            $name =
                trim(
                    (string) (
                        $activityDetails[$id]['name']
                        ?? ''
                    )
                );

            /*
             * RIDB often repeats the activity name as its description or fee
             * description (for example "Hiking" beneath HIKING). That is not
             * additional information and should not create a second rendering
             * of the same activity.
             */
            if (
                $value !== ''
                && $name !== ''
                && strcasecmp(
                    preg_replace('/\s+/u', ' ', $value) ?? $value,
                    preg_replace('/\s+/u', ' ', $name) ?? $name
                ) === 0
            ) {
                $value = '';
            }

            if ($key === 'activity_description') {
                $activityDetails[$id]['description'] =
                    $value;
            } else {
                $activityDetails[$id]['fee_description'] =
                    $value;
            }

            continue;
        }

        if ($key === 'source_link_description') {
            $url =
                trim(
                    (string) (
                        $row['qualifier']
                        ?? ''
                    )
                );

            if ($url !== '') {
                $linkDescriptions[$url] =
                    trim(
                        (string) (
                            $row['feature_value']
                            ?? ''
                        )
                    );
            }
        }
    }

    $activityDetails =
        array_values(
            array_filter(
                $activityDetails,
                static function (array $detail): bool {
                    return
                        trim(
                            (string) (
                                $detail['description']
                                ?? ''
                            )
                        ) !== ''
                        || trim(
                            (string) (
                                $detail['fee_description']
                                ?? ''
                            )
                        ) !== '';
                }
            )
        );

    return [
        'official_address_3' =>
            trim(
                (string) (
                    $facts['official_address_3']
                    ?? ''
                )
            ),
        'official_address_type' =>
            trim(
                (string) (
                    $facts['official_address_type']
                    ?? ''
                )
            ),
        'official_address_updated_at' =>
            trim(
                (string) (
                    $facts['official_address_updated_at']
                    ?? ''
                )
            ),
        'accessibility_text' =>
            trim(
                (string) (
                    $facts['accessibility_text']
                    ?? ''
                )
            ),
        'ada_access' =>
            trim(
                (string) (
                    $facts['ada_access']
                    ?? ''
                )
            ),
        'facility_map_url' =>
            trim(
                (string) (
                    $facts['facility_map_url']
                    ?? ''
                )
            ),
        'stay_limit' =>
            trim(
                (string) (
                    $facts['stay_limit']
                    ?? ''
                )
            ),
        'activity_details' =>
            $activityDetails,
        'link_descriptions' =>
            $linkDescriptions,
    ];
}

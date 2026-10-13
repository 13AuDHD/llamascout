<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';
require_once __DIR__ . '/ridb-mirror.php';

/**
 * Local-first RIDB facility/campsite inspection helpers.
 *
 * Ordinary page loads read the local RIDB database only. Live RIDB HTTP
 * requests happen only when an admin explicitly presses a refresh button.
 */

function llama_ridb_inspector_decode_json(
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

function llama_ridb_inspector_pretty_json(
    mixed $value
): string {
    return
        json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        )
        ?: '{}';
}

function llama_ridb_inspector_embedded_list(
    array $record,
    array $keys
): array {
    foreach ($keys as $key) {
        $value =
            $record[$key]
            ?? null;

        if (!is_array($value)) {
            continue;
        }

        if (!$value) {
            return [];
        }

        if (
            isset($value[0])
            && is_array($value[0])
        ) {
            return array_values(
                array_filter(
                    $value,
                    'is_array'
                )
            );
        }

        return [$value];
    }

    return [];
}

function llama_ridb_inspector_related_rows(
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

    if (
        !in_array(
            $table,
            $allowed,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unsupported RIDB related-record table.'
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

    $records = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
        ?: []
        as $json
    ) {
        $record =
            llama_ridb_inspector_decode_json(
                $json
            );

        if ($record) {
            $records[] =
                $record;
        }
    }

    return $records;
}

function llama_ridb_inspector_load_facility(
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

    $facility =
        llama_ridb_inspector_decode_json(
            $stmt->fetchColumn()
        );

    if (!$facility) {
        throw new RuntimeException(
            'This facility is not present in the local RIDB cache.'
        );
    }

    $campsiteStmt =
        $ridbDb->prepare(
            'SELECT
                ridb_campsite_id,
                ridb_facility_id,
                campsite_name,
                campsite_type,
                campsite_accessible,
                latitude,
                longitude,
                source_json,
                first_seen_at,
                last_seen_at
             FROM ridb_campsites
             WHERE ridb_facility_id = ?
             ORDER BY
                campsite_name ASC,
                ridb_campsite_id ASC'
        );

    $campsiteStmt->execute([
        $facilityId,
    ]);

    return [
        'facility' =>
            $facility,

        'addresses' =>
            llama_ridb_inspector_related_rows(
                $ridbDb,
                'ridb_facility_addresses',
                $facilityId
            ),

        'media' =>
            llama_ridb_inspector_related_rows(
                $ridbDb,
                'ridb_media',
                $facilityId
            ),

        'links' =>
            llama_ridb_inspector_related_rows(
                $ridbDb,
                'ridb_links',
                $facilityId
            ),

        'activities' =>
            llama_ridb_inspector_related_rows(
                $ridbDb,
                'ridb_activities',
                $facilityId
            ),

        'campsites' =>
            $campsiteStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [],
    ];
}

function llama_ridb_inspector_load_campsite(
    PDO $ridbDb,
    string $facilityId,
    string $campsiteId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT
                c.*,
                f.facility_name,
                f.facility_type_description
             FROM ridb_campsites c
             LEFT JOIN ridb_facilities f
               ON f.ridb_facility_id =
                    c.ridb_facility_id
             WHERE c.ridb_campsite_id = ?
               AND c.ridb_facility_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $campsiteId,
        $facilityId,
    ]);

    $selected =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    $rawStmt =
        $ridbDb->prepare(
            'SELECT
                id,
                source_record_id,
                source_endpoint,
                entity_id,
                entity_type,
                parent_id,
                parent_type,
                source_json,
                first_seen_at,
                last_seen_at
             FROM ridb_raw_records
             WHERE feed = ?
               AND source_record_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );

    $rawStmt->execute([
        'campsites',
        $campsiteId,
    ]);

    $rawRow =
        $rawStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    $rawRecord =
        $rawRow
            ? llama_ridb_inspector_decode_json(
                $rawRow['source_json']
                ?? ''
            )
            : [];

    /*
     * If the lossless mirror row is unavailable, the normalized campsite
     * cache still retains the full campsite JSON.
     */
    if (
        !$rawRecord
        && is_array($selected)
    ) {
        $rawRecord =
            llama_ridb_inspector_decode_json(
                $selected['source_json']
                ?? ''
            );
    }

    $embeddedAttributes =
        llama_ridb_inspector_embedded_list(
            $rawRecord,
            [
                'ATTRIBUTES',
                'Attributes',
                'attributes',
            ]
        );

    $embeddedEquipment =
        llama_ridb_inspector_embedded_list(
            $rawRecord,
            [
                'PERMITTEDEQUIPMENT',
                'PermittedEquipment',
                'permittedEquipment',
            ]
        );

    $embeddedMedia =
        llama_ridb_inspector_embedded_list(
            $rawRecord,
            [
                'ENTITYMEDIA',
                'EntityMedia',
                'entityMedia',
            ]
        );

    $relatedStmt =
        $ridbDb->prepare(
            'SELECT
                feed,
                source_record_id,
                source_endpoint,
                entity_id,
                entity_type,
                parent_id,
                parent_type,
                source_json,
                first_seen_at,
                last_seen_at
             FROM ridb_raw_records
             WHERE
                (
                    feed = ?
                    AND (
                        entity_id = ?
                        OR parent_id = ?
                    )
                    AND LOWER(
                        COALESCE(
                            NULLIF(entity_type, ""),
                            parent_type
                        )
                    ) = "campsite"
                )
                OR (
                    feed IN (
                        "campsite_attributes",
                        "campsite_permitted_equipment"
                    )
                    AND parent_id = ?
                    AND LOWER(parent_type) = "campsite"
                )
             ORDER BY
                feed ASC,
                source_record_id ASC'
        );

    $relatedStmt->execute([
        'media',
        $campsiteId,
        $campsiteId,
        $campsiteId,
    ]);

    $mirroredAttributes = [];
    $mirroredEquipment = [];
    $mirroredMedia = [];

    foreach (
        $relatedStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $item = [
            'meta' => [
                'feed' =>
                    (string) (
                        $row['feed']
                        ?? ''
                    ),
                'source_record_id' =>
                    (string) (
                        $row[
                            'source_record_id'
                        ]
                        ?? ''
                    ),
                'source_endpoint' =>
                    (string) (
                        $row[
                            'source_endpoint'
                        ]
                        ?? ''
                    ),
                'entity_id' =>
                    (string) (
                        $row['entity_id']
                        ?? ''
                    ),
                'entity_type' =>
                    (string) (
                        $row['entity_type']
                        ?? ''
                    ),
                'parent_id' =>
                    (string) (
                        $row['parent_id']
                        ?? ''
                    ),
                'parent_type' =>
                    (string) (
                        $row['parent_type']
                        ?? ''
                    ),
            ],
            'record' =>
                llama_ridb_inspector_decode_json(
                    $row['source_json']
                    ?? ''
                ),
        ];

        $feed =
            (string) (
                $row['feed']
                ?? ''
            );

        if ($feed === 'media') {
            $mirroredMedia[] =
                $item;
        } elseif (
            $feed
            === 'campsite_attributes'
        ) {
            $mirroredAttributes[] =
                $item;
        } elseif (
            $feed
            === 'campsite_permitted_equipment'
        ) {
            $mirroredEquipment[] =
                $item;
        }
    }

    $normalizedAttributeStmt =
        $ridbDb->prepare(
            'SELECT
                source_record_id,
                attribute_name,
                attribute_value,
                source_json,
                synced_at
             FROM ridb_campsite_attributes
             WHERE ridb_campsite_id = ?
             ORDER BY
                attribute_name ASC,
                source_record_id ASC'
        );

    $normalizedAttributeStmt->execute([
        $campsiteId,
    ]);

    return [
        'selected' =>
            $selected,
        'raw_row' =>
            $rawRow,
        'raw_record' =>
            $rawRecord,
        'embedded_attributes' =>
            $embeddedAttributes,
        'embedded_equipment' =>
            $embeddedEquipment,
        'embedded_media' =>
            $embeddedMedia,
        'mirrored_attributes' =>
            $mirroredAttributes,
        'mirrored_equipment' =>
            $mirroredEquipment,
        'mirrored_media' =>
            $mirroredMedia,
        'normalized_attributes' =>
            $normalizedAttributeStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [],
    ];
}

function llama_ridb_inspector_refresh_facility(
    PDO $ridbDb,
    string $facilityId
): array {
    $facilityResponse =
        llama_ridb_facility(
            $facilityId
        );

    $facility =
        llama_ridb_response_record(
            $facilityResponse
        );

    if (!$facility) {
        throw new RuntimeException(
            'RIDB did not return this facility.'
        );
    }

    llama_ridb_store_facilities(
        $ridbDb,
        [$facility]
    );

    $addressResponse =
        llama_ridb_facility_addresses(
            $facilityId
        );

    $addresses =
        $addressResponse['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_facility_addresses',
        $facilityId,
        $addresses,
        'FacilityAddressID'
    );

    $mediaResponse =
        llama_ridb_facility_media(
            $facilityId
        );

    $media =
        $mediaResponse['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_media',
        $facilityId,
        $media,
        'MediaID'
    );

    $linkResponse =
        llama_ridb_facility_links(
            $facilityId
        );

    $links =
        $linkResponse['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_links',
        $facilityId,
        $links,
        'LinkID'
    );

    $activityResponse =
        llama_ridb_facility_activities(
            $facilityId
        );

    $activities =
        $activityResponse['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_activities',
        $facilityId,
        $activities,
        'ActivityID'
    );

    $campsiteResponse =
        llama_ridb_facility_campsites(
            $facilityId,
            500
        );

    $campsites =
        $campsiteResponse['records']
        ?? [];

    llama_ridb_store_campsites(
        $ridbDb,
        $facilityId,
        $campsites
    );

    $seen =
        1
        + count($addresses)
        + count($media)
        + count($links)
        + count($activities)
        + count($campsites);

    llama_ridb_log_sync_run(
        $ridbDb,
        'facility_manual_refresh',
        'success',
        $seen,
        $seen,
        'Explicitly refreshed facility '
        . $facilityId
        . ' from RIDB.'
    );

    return [
        'seen' =>
            $seen,
        'campsites' =>
            count($campsites),
        'truncated' =>
            !empty(
                $campsiteResponse['truncated']
            ),
    ];
}

function llama_ridb_inspector_refresh_campsite(
    PDO $ridbDb,
    string $facilityId,
    string $campsiteId
): array {
    $selectedResponse =
        llama_ridb_campsite(
            $campsiteId
        );

    $site =
        llama_ridb_response_record(
            $selectedResponse
        );

    if (!$site) {
        throw new RuntimeException(
            'RIDB did not return this campsite.'
        );
    }

    llama_ridb_store_campsites(
        $ridbDb,
        $facilityId,
        [$site]
    );

    $attributeResponse =
        llama_ridb_campsite_attributes(
            $campsiteId
        );

    $attributes =
        $attributeResponse['records']
        ?? [];

    llama_ridb_replace_campsite_attributes(
        $ridbDb,
        $campsiteId,
        $attributes
    );

    llama_ridb_log_sync_run(
        $ridbDb,
        'campsite_manual_refresh',
        'success',
        1 + count($attributes),
        1 + count($attributes),
        'Explicitly refreshed campsite '
        . $campsiteId
        . ' for facility '
        . $facilityId
        . ' from RIDB.'
    );

    return [
        'attributes' =>
            count($attributes),
    ];
}

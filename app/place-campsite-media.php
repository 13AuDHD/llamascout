<?php

declare(strict_types=1);

require_once __DIR__ . '/place-campsites.php';
require_once __DIR__ . '/place-report/scoped-answers.php';

/**
 * Canonical campsite + campsite-photo foundation.
 *
 * This file deliberately does not alter contributor UI by itself.
 * It provides the canonical persistence layer that the separated
 * Site Photos uploader and moderation approval flow can safely call.
 */

function llama_campsite_photo_path(mixed $photo): string
{
    if (!is_array($photo)) {
        return '';
    }

    return '/' . ltrim(
        trim(
            (string) (
                $photo['path']
                ?? $photo['url']
                ?? ''
            )
        ),
        '/'
    );
}

/**
 * Return campsite images only after verifying the campsite belongs to Place.
 */
function llama_place_campsite_images(
    PDO $db,
    int $placeId,
    int $campsiteId
): array {
    if ($placeId < 1 || $campsiteId < 1) {
        return [];
    }

    $site = llama_place_campsite(
        $db,
        $placeId,
        $campsiteId
    );

    if (!$site) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            place_id,
            campsite_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at,
            created_at,
            updated_at
         FROM place_campsite_images
         WHERE place_id = ?
           AND campsite_id = ?
         ORDER BY
            is_featured DESC,
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([
        $placeId,
        $campsiteId,
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

/**
 * Look up a single campsite image while enforcing the Place relationship.
 */
function llama_place_campsite_image(
    PDO $db,
    int $placeId,
    int $campsiteId,
    int $imageId
): array {
    if (
        $placeId < 1
        || $campsiteId < 1
        || $imageId < 1
    ) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            place_id,
            campsite_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at,
            created_at,
            updated_at
         FROM place_campsite_images
         WHERE id = ?
           AND place_id = ?
           AND campsite_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $imageId,
        $placeId,
        $campsiteId,
    ]);

    $row = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($row)
        ? $row
        : [];
}

/**
 * Insert one already-permanent campsite image.
 *
 * File movement/copying remains the responsibility of the moderation or
 * importer workflow so database work can stay transactional.
 */
function llama_place_campsite_image_insert(
    PDO $db,
    int $placeId,
    int $campsiteId,
    string $src,
    ?string $altText = null,
    ?string $caption = null,
    ?int $uploadedBy = null,
    ?string $sourceProvider = null,
    ?string $sourceExternalId = null,
    ?string $sourceUrl = null,
    ?string $sourceCredit = null,
    ?string $sourceUpdatedAt = null
): int {
    if ($placeId < 1 || $campsiteId < 1) {
        throw new InvalidArgumentException(
            'A valid Place and campsite are required.'
        );
    }

    if (
        !llama_place_campsite(
            $db,
            $placeId,
            $campsiteId
        )
    ) {
        throw new InvalidArgumentException(
            'That campsite does not belong to this Place.'
        );
    }

    $src = '/' . ltrim(
        trim($src),
        '/'
    );

    if (
        $src === '/'
        || str_contains(
            $src,
            '..'
        )
    ) {
        throw new InvalidArgumentException(
            'A valid campsite photo path is required.'
        );
    }

    $orderStmt = $db->prepare(
        'SELECT
            COALESCE(MAX(sort_order), -1),
            COUNT(*)
         FROM place_campsite_images
         WHERE place_id = ?
           AND campsite_id = ?'
    );

    $orderStmt->execute([
        $placeId,
        $campsiteId,
    ]);

    $row = $orderStmt->fetch(
        PDO::FETCH_NUM
    ) ?: [-1, 0];

    $sortOrder =
        ((int) ($row[0] ?? -1))
        + 1;

    $isFeatured =
        ((int) ($row[1] ?? 0)) === 0
            ? 1
            : 0;

    $stmt = $db->prepare(
        'INSERT INTO place_campsite_images
        (
            place_id,
            campsite_id,
            src,
            alt_text,
            caption,
            is_featured,
            sort_order,
            uploaded_by,
            source_provider,
            source_external_id,
            source_url,
            source_credit,
            source_updated_at
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )'
    );

    $stmt->execute([
        $placeId,
        $campsiteId,
        $src,
        $altText !== null
            ? mb_substr(
                trim($altText),
                0,
                300
            )
            : null,
        $caption !== null
            ? mb_substr(
                trim($caption),
                0,
                500
            )
            : null,
        $isFeatured,
        $sortOrder,
        $uploadedBy !== null
        && $uploadedBy > 0
            ? $uploadedBy
            : null,
        $sourceProvider !== null
            ? trim($sourceProvider)
            : null,
        $sourceExternalId !== null
            ? trim($sourceExternalId)
            : null,
        $sourceUrl !== null
            ? trim($sourceUrl)
            : null,
        $sourceCredit !== null
            ? trim($sourceCredit)
            : null,
        $sourceUpdatedAt !== null
            ? trim($sourceUpdatedAt)
            : null,
    ]);

    return (int) $db->lastInsertId();
}

/**
 * Make a different campsite image the campsite hero.
 */
function llama_place_campsite_image_set_featured(
    PDO $db,
    int $placeId,
    int $campsiteId,
    int $imageId
): void {
    if (
        !llama_place_campsite_image(
            $db,
            $placeId,
            $campsiteId,
            $imageId
        )
    ) {
        throw new InvalidArgumentException(
            'That campsite photo could not be found.'
        );
    }

    $clear = $db->prepare(
        'UPDATE place_campsite_images
         SET is_featured = 0
         WHERE place_id = ?
           AND campsite_id = ?'
    );

    $clear->execute([
        $placeId,
        $campsiteId,
    ]);

    $set = $db->prepare(
        'UPDATE place_campsite_images
         SET is_featured = 1
         WHERE id = ?
           AND place_id = ?
           AND campsite_id = ?'
    );

    $set->execute([
        $imageId,
        $placeId,
        $campsiteId,
    ]);
}

/**
 * Reorder a campsite gallery. Photo #1 is canonical hero.
 *
 * Unknown IDs, duplicates, or cross-campsite IDs are rejected instead of
 * being silently ignored.
 */
function llama_place_campsite_images_reorder(
    PDO $db,
    int $placeId,
    int $campsiteId,
    array $imageIds
): void {
    $current =
        llama_place_campsite_images(
            $db,
            $placeId,
            $campsiteId
        );

    $currentIds = array_map(
        static fn (array $image): int =>
            (int) $image['id'],
        $current
    );

    $requestedIds = array_map(
        'intval',
        array_values(
            $imageIds
        )
    );

    if (
        count($requestedIds)
        !== count(
            array_unique(
                $requestedIds
            )
        )
        || $requestedIds !== array_values(
            array_filter(
                $requestedIds,
                static fn (int $id): bool =>
                    $id > 0
            )
        )
    ) {
        throw new InvalidArgumentException(
            'The campsite photo order is invalid.'
        );
    }

    $sortedCurrent = $currentIds;
    $sortedRequested = $requestedIds;

    sort($sortedCurrent);
    sort($sortedRequested);

    if ($sortedCurrent !== $sortedRequested) {
        throw new InvalidArgumentException(
            'The campsite photo order does not match this campsite gallery.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE place_campsite_images
         SET
            sort_order = ?,
            is_featured = ?
         WHERE id = ?
           AND place_id = ?
           AND campsite_id = ?'
    );

    foreach (
        $requestedIds
        as $index => $imageId
    ) {
        $stmt->execute([
            $index,
            $index === 0 ? 1 : 0,
            $imageId,
            $placeId,
            $campsiteId,
        ]);
    }
}

/**
 * Read a value from canonical Place Report data using a dot-separated storage
 * path and report whether the path existed.
 */
function llama_campsite_submission_path_value(
    array $data,
    string $path,
    bool &$found = false
): mixed {
    $cursor = $data;

    foreach (
        explode(
            '.',
            $path
        )
        as $part
    ) {
        if (
            !is_array($cursor)
            || !array_key_exists(
                $part,
                $cursor
            )
        ) {
            $found = false;
            return null;
        }

        $cursor =
            $cursor[$part];
    }

    $found = true;
    return $cursor;
}

function llama_campsite_submission_bool(
    mixed $value
): ?bool {
    if (is_bool($value)) {
        return $value;
    }

    if (
        $value === 1
        || $value === '1'
        || strtolower(
            trim(
                (string) $value
            )
        ) === 'true'
    ) {
        return true;
    }

    if (
        $value === 0
        || $value === '0'
        || strtolower(
            trim(
                (string) $value
            )
        ) === 'false'
    ) {
        return false;
    }

    return null;
}

/**
 * Infer canonical campsite identity from Scout answers.
 */
function llama_campsite_submission_identity(
    array $data
): array {
    $details =
        is_array(
            $data['details']
            ?? null
        )
            ? $data['details']
            : [];

    $siteNumber =
        trim(
            (string) (
                $details['site_number']
                ?? ''
            )
        );

    $tent =
        llama_campsite_submission_bool(
            $details[
                'tent_camping_suitable'
            ]
            ?? null
        );

    $rv =
        llama_campsite_submission_bool(
            $details[
                'rv_suitable'
            ]
            ?? null
        );

    $siteType = null;

    if ($tent === true && $rv === true) {
        $siteType = 'mixed_site';
    } elseif (
        $tent === true
        && $rv === false
    ) {
        $siteType = 'tent_site';
    } elseif (
        $rv === true
        && $tent === false
    ) {
        $siteType = 'rv_site';
    }

    $pullThrough =
        llama_campsite_submission_bool(
            $details[
                'pull_through'
            ]
            ?? null
        );

    $backIn =
        llama_campsite_submission_bool(
            $details[
                'back_in'
            ]
            ?? null
        );

    $parkingStyle = null;

    if ($pullThrough === true) {
        $parkingStyle =
            'pull_through';
    } elseif ($backIn === true) {
        $parkingStyle =
            'back_in';
    }

    $electric =
        llama_campsite_submission_bool(
            $details[
                'hookup_electric'
            ]
            ?? null
        );

    $water =
        llama_campsite_submission_bool(
            $details[
                'hookup_water'
            ]
            ?? null
        );

    $sewer =
        llama_campsite_submission_bool(
            $details[
                'hookup_sewer'
            ]
            ?? null
        );

    $hookupStatus = null;

    if (
        $electric === true
        && $water === true
        && $sewer === true
    ) {
        $hookupStatus = 'full';
    } elseif (
        $electric === true
        && $water === true
    ) {
        $hookupStatus =
            'electric_water';
    } elseif ($electric === true) {
        $hookupStatus =
            'electric_only';
    } elseif ($water === true) {
        $hookupStatus =
            'water_only';
    } elseif (
        $electric === false
        && $water === false
        && $sewer === false
    ) {
        $hookupStatus = 'none';
    }

    $accessible =
        llama_campsite_submission_bool(
            $details[
                'site_accessible'
            ]
            ?? null
        );

    return [
        'site_code' =>
            $siteNumber !== ''
                ? $siteNumber
                : null,
        'site_name' =>
            $siteNumber !== ''
                ? $siteNumber
                : null,
        'site_type' =>
            $siteType,
        'parking_style' =>
            $parkingStyle,
        'hookup_status' =>
            $hookupStatus,
        'accessible_status' =>
            $accessible === true
                ? 'yes'
                : (
                    $accessible === false
                        ? 'no'
                        : 'unknown'
                ),
    ];
}

/**
 * Normalize the Scout fields that already have first-class columns in
 * place_campsite_facts.
 */
function llama_campsite_submission_facts(
    array $data
): array {
    $details =
        is_array(
            $data['details']
            ?? null
        )
            ? $data['details']
            : [];

    $bool =
        static fn (mixed $value): ?int =>
            ($parsed =
                llama_campsite_submission_bool(
                    $value
                )
            ) === null
                ? null
                : ($parsed ? 1 : 0);

    return [
        'site_length_ft' =>
            $details[
                'site_length_feet'
            ]
            ?? null,
        'site_width_ft' =>
            $details[
                'site_width_feet'
            ]
            ?? null,
        'driveway_length_ft' =>
            $details[
                'parking_length_feet'
            ]
            ?? null,
        'driveway_grade' =>
            $details[
                'parking_grade'
            ]
            ?? null,
        'driveway_surface' =>
            $details[
                'parking_surface'
            ]
            ?? null,
        'overhead_clearance_ft' =>
            $details[
                'overhead_clearance_feet'
            ]
            ?? null,
        'max_vehicle_length_ft' =>
            $details[
                'max_vehicle_length_feet'
            ]
            ?? null,
        'max_people' =>
            $details[
                'max_people'
            ]
            ?? null,
        'max_vehicles' =>
            $details[
                'vehicle_capacity'
            ]
            ?? null,
        'tent_pad' =>
            $bool(
                $details[
                    'tent_pad'
                ]
                ?? null
            ),
        'tent_pad_length_ft' =>
            $details[
                'tent_pad_length_feet'
            ]
            ?? null,
        'tent_pad_width_ft' =>
            $details[
                'tent_pad_width_feet'
            ]
            ?? null,
        'electric_hookup' =>
            $bool(
                $details[
                    'hookup_electric'
                ]
                ?? null
            ),
        'electric_service' =>
            $details[
                'hookup_electric_service'
            ]
            ?? null,
        'water_hookup' =>
            $bool(
                $details[
                    'hookup_water'
                ]
                ?? null
            ),
        'sewer_hookup' =>
            $bool(
                $details[
                    'hookup_sewer'
                ]
                ?? null
            ),
    ];
}

/**
 * Create the canonical campsite represented by an approved new-Place report.
 *
 * This is idempotent for the same Place/submission pair.
 */
function llama_campsite_create_from_submission(
    PDO $db,
    int $placeId,
    int $submissionId,
    array $data
): ?int {
    if (
        $placeId < 1
        || $submissionId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid Place and submission are required.'
        );
    }

    $identity =
        llama_campsite_submission_identity(
            $data
        );

    $siteCode =
        trim(
            (string) (
                $identity['site_code']
                ?? ''
            )
        );

    /*
     * A general Place report without a campsite identifier must not create
     * an anonymous fake campsite.
     */
    if ($siteCode === '') {
        return null;
    }

    $sourceProvider =
        'Llama Scout';

    $sourceExternalId =
        'community-submission:'
        . $submissionId;

    $existing = $db->prepare(
        'SELECT id
         FROM place_campsites
         WHERE place_id = ?
           AND source_provider = ?
           AND source_external_id = ?
         LIMIT 1'
    );

    $existing->execute([
        $placeId,
        $sourceProvider,
        $sourceExternalId,
    ]);

    $existingId =
        (int) $existing->fetchColumn();

    if ($existingId > 0) {
        return $existingId;
    }

    $insert = $db->prepare(
        'INSERT INTO place_campsites
        (
            place_id,
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
            ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, 1
        )'
    );

    $insert->execute([
        $placeId,
        $identity['site_code']
            ?? null,
        $identity['site_name']
            ?? null,
        'Community scouted',
        $identity['site_type']
            ?? null,
        $identity['parking_style']
            ?? null,
        $identity['hookup_status']
            ?? null,
        $identity['accessible_status']
            ?? 'unknown',
        $sourceProvider,
        $sourceExternalId,
    ]);

    $campsiteId =
        (int) $db->lastInsertId();

    if ($campsiteId < 1) {
        throw new RuntimeException(
            'The canonical campsite could not be created.'
        );
    }

    $facts =
        llama_campsite_submission_facts(
            $data
        );

    $factColumns =
        array_keys(
            $facts
        );

    $insertColumns =
        array_merge(
            [
                'campsite_id',
            ],
            $factColumns,
            [
                'source_provider',
                'source_external_id',
            ]
        );

    $factValues = [
        $campsiteId,
    ];

    foreach ($factColumns as $column) {
        $factValues[] =
            $facts[$column];
    }

    $factValues[] =
        'Llama Scout';
    $factValues[] =
        $sourceExternalId;

    $factStmt = $db->prepare(
        'INSERT INTO place_campsite_facts
        (
            '
            . implode(
                ', ',
                array_map(
                    static fn (string $column): string =>
                        '`'
                        . $column
                        . '`',
                    $insertColumns
                )
            )
            . '
        )
        VALUES
        (
            '
            . implode(
                ', ',
                array_fill(
                    0,
                    count(
                        $insertColumns
                    ),
                    '?'
                )
            )
            . '
        )'
    );

    $factStmt->execute(
        $factValues
    );

    llama_campsite_save_submission_answers(
        $db,
        $placeId,
        $campsiteId,
        $data
    );

    return $campsiteId;
}

/**
 * Persist every answered field that the form explicitly assigned to the Site
 * section. This keeps sensory/accessibility/experience answers canonical even
 * when they do not have dedicated columns in place_campsite_facts.
 */
function llama_campsite_save_submission_answers(
    PDO $db,
    int $placeId,
    int $campsiteId,
    array $data
): int {
    $fields =
        llama_place_report_fields();

    $unknownFields =
        array_fill_keys(
            array_map(
                'strval',
                is_array(
                    $data[
                        '_answer_state'
                    ]
                    ?? null
                )
                    ? $data[
                        '_answer_state'
                    ]
                    : []
            ),
            true
        );

    $saved = 0;

    foreach ($fields as $key => $field) {
        if (
            (string) (
                $field[
                    'layout_section'
                ]
                ?? ''
            ) !== 'site'
            || !in_array(
                'site',
                (array) (
                    $field[
                        'report_scopes'
                    ]
                    ?? []
                ),
                true
            )
            || !empty(
                $field['derived']
            )
            || !empty(
                $field[
                    'location_field'
                ]
            )
        ) {
            continue;
        }

        if (
            isset(
                $unknownFields[$key]
            )
        ) {
            llama_scoped_report_save(
                $db,
                $placeId,
                'site',
                $campsiteId,
                'campsite_record',
                (string) $key,
                llama_place_report_unknown_token()
            );

            $saved++;
            continue;
        }

        $storage =
            trim(
                (string) (
                    $field['storage']
                    ?? ''
                )
            );

        if ($storage === '') {
            continue;
        }

        $found = false;

        $value =
            llama_campsite_submission_path_value(
                $data,
                $storage,
                $found
            );

        if (!$found) {
            continue;
        }

        /*
         * Null means the contributor never supplied a value.
         * False, zero, and empty arrays remain explicit answers.
         */
        if ($value === null) {
            continue;
        }

        llama_scoped_report_save(
            $db,
            $placeId,
            'site',
            $campsiteId,
            'campsite_record',
            (string) $key,
            $value
        );

        $saved++;
    }

    return $saved;
}

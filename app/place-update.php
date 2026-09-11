<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';


/*
 * =========================================================
 * PLACE UPDATE SYSTEM
 *
 * Suggest an Update uses the shared Place Report schema.
 *
 * There is no separate list of editable Place fields here.
 * Storage information is derived from the same field
 * definitions used by Add Place and moderation.
 * =========================================================
 */


/*
 * =========================================================
 * SCHEMA
 * =========================================================
 */


function llama_place_update_field_by_storage(
    string $storage
): ?array {
    foreach (
        llama_place_report_fields()
        as $field
    ) {
        if (
            (string) (
                $field['storage']
                ?? ''
            ) === $storage
        ) {
            return $field;
        }
    }

    return null;
}


function llama_place_update_storage_definition(
    array $field
): ?array {
    $storage =
        trim(
            (string) (
                $field['storage']
                ?? ''
            )
        );

    if ($storage === '') {
        return null;
    }


    /*
     * Submission metadata is not part of the published Place.
     */
    if (
        in_array(
            $storage,
            [
                'visited_at',
                'contributor_notes',
            ],
            true
        )
    ) {
        return null;
    }


    /*
     * Fields stored directly on places.
     */
    $placeColumns = [
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'elevation_feet',
        'road',
        'city',
        'county',
        'state',
        'region',
        'land_manager',
        'land_type',
        'access_summary',
        'sensory_summary',
    ];

    if (
        in_array(
            $storage,
            $placeColumns,
            true
        )
    ) {
        return [
            'table' =>
                'places',

            'column' =>
                $storage,
        ];
    }


    /*
     * place_details
     */
    if (
        str_starts_with(
            $storage,
            'details.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen('details.')
            );

        return [
            'table' =>
                'place_details',

            'column' =>
                $column,
        ];
    }


    /*
     * place_amenities
     */
    if (
        str_starts_with(
            $storage,
            'amenities.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen('amenities.')
            );

        return [
            'table' =>
                'place_amenities',

            'column' =>
                $column,
        ];
    }


    /*
     * place_connectivity
     */
    if (
        str_starts_with(
            $storage,
            'connectivity.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen('connectivity.')
            );

        return [
            'table' =>
                'place_connectivity',

            'column' =>
                $column,
        ];
    }


    /*
     * Day and night sensory values.
     */
    foreach (
        [
            'daytime',
            'nighttime',
        ]
        as $period
    ) {
        $prefix =
            'sensory.'
            . $period
            . '.';

        if (
            str_starts_with(
                $storage,
                $prefix
            )
        ) {
            $column =
                substr(
                    $storage,
                    strlen($prefix)
                );

            return [
                'table' =>
                    'place_sensory',

                'column' =>
                    $column,

                'period' =>
                    $period,
            ];
        }
    }


    /*
     * Additional sensory conditions.
     */
    if (
        str_starts_with(
            $storage,
            'sensory.details.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen(
                    'sensory.details.'
                )
            );

        return [
            'table' =>
                'place_sensory_details',

            'column' =>
                $column,
        ];
    }


    /*
     * Rules and seasonal information.
     */
    if (
        str_starts_with(
            $storage,
            'rules.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen('rules.')
            );

        return [
            'table' =>
                'place_rules',

            'column' =>
                $column,
        ];
    }


    /*
     * Experience and recommendations.
     */
    if (
        str_starts_with(
            $storage,
            'experience.'
        )
    ) {
        $column =
            substr(
                $storage,
                strlen('experience.')
            );

        return [
            'table' =>
                'place_experience',

            'column' =>
                $column,
        ];
    }

    return null;
}


function llama_place_update_definitions(): array
{
    $definitions = [];

    foreach (
        llama_place_report_fields()
        as $key => $field
    ) {
        $storageDefinition =
            llama_place_update_storage_definition(
                $field
            );

        if (!$storageDefinition) {
            continue;
        }

        $storage =
            (string) $field['storage'];

        $definitions[$storage] =
            array_merge(
                $storageDefinition,
                [
                    'shared' =>
                        $field,

                    'key' =>
                        (string) $key,

                    'label' =>
                        (string) (
                            $field['label']
                            ?? $key
                        ),

                    'section' =>
                        (string) (
                            $field['section']
                            ?? 'other'
                        ),

                    'type' =>
                        (string) (
                            $field['type']
                            ?? 'text'
                        ),
                ]
            );
    }

    return $definitions;
}


function llama_place_update_sections(): array
{
    $sharedSections =
        llama_place_report_sections();

    $sections = [];

    foreach (
        llama_place_update_definitions()
        as $path => $definition
    ) {
        $sectionKey =
            (string) (
                $definition['section']
                ?? 'other'
            );

        if (
            !isset(
                $sections[$sectionKey]
            )
        ) {
            $sections[$sectionKey] =
                $sharedSections[$sectionKey]
                ?? [
                    'label' =>
                        'Other',

                    'description' =>
                        'Additional Place information',

                    'icon' =>
                        'fa-pen-to-square',
                ];

            $sections[$sectionKey]['fields'] =
                [];
        }

        $sections[$sectionKey]['fields'][$path] =
            $definition;
    }

    return $sections;
}


/*
 * =========================================================
 * CURRENT PUBLISHED VALUES
 * =========================================================
 */


function llama_place_update_valid_identifier(
    string $value
): bool {
    return
        preg_match(
            '/^[a-z0-9_]+$/',
            $value
        ) === 1;
}


function llama_place_update_current_values(
    PDO $db,
    int $placeId,
    bool $lock = false
): array {
    $definitions =
        llama_place_update_definitions();

    $values = [];
    $cache = [];

    foreach (
        $definitions
        as $path => $definition
    ) {
        $table =
            (string) (
                $definition['table']
                ?? ''
            );

        $column =
            (string) (
                $definition['column']
                ?? ''
            );

        if (
            !llama_place_update_valid_identifier(
                $table
            )
            || !llama_place_update_valid_identifier(
                $column
            )
        ) {
            continue;
        }


        /*
         * places
         */
        if ($table === 'places') {
            if (
                !array_key_exists(
                    'places',
                    $cache
                )
            ) {
                $sql =
                    'SELECT *
                     FROM places
                     WHERE id = ?
                     LIMIT 1';

                if ($lock) {
                    $sql .= ' FOR UPDATE';
                }

                $stmt =
                    $db->prepare(
                        $sql
                    );

                $stmt->execute([
                    $placeId,
                ]);

                $cache['places'] =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    )
                    ?: [];
            }

            $values[$path] =
                $cache['places'][$column]
                ?? null;

            continue;
        }


        /*
         * place_sensory uses one row per period.
         */
        if (
            $table === 'place_sensory'
        ) {
            $period =
                (string) (
                    $definition['period']
                    ?? ''
                );

            $cacheKey =
                'place_sensory:'
                . $period;

            if (
                !array_key_exists(
                    $cacheKey,
                    $cache
                )
            ) {
                $sql =
                    'SELECT *
                     FROM place_sensory
                     WHERE place_id = ?
                       AND period = ?
                     LIMIT 1';

                if ($lock) {
                    $sql .= ' FOR UPDATE';
                }

                $stmt =
                    $db->prepare(
                        $sql
                    );

                $stmt->execute([
                    $placeId,
                    $period,
                ]);

                $cache[$cacheKey] =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    )
                    ?: [];
            }

            $values[$path] =
                $cache[$cacheKey][$column]
                ?? null;

            continue;
        }


        /*
         * All remaining Place child tables have one row
         * identified by place_id.
         */
        if (
            !array_key_exists(
                $table,
                $cache
            )
        ) {
            $sql =
                "SELECT *
                 FROM `$table`
                 WHERE place_id = ?
                 LIMIT 1";

            if ($lock) {
                $sql .= ' FOR UPDATE';
            }

            $stmt =
                $db->prepare(
                    $sql
                );

            $stmt->execute([
                $placeId,
            ]);

            $cache[$table] =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
                ?: [];
        }

        $values[$path] =
            $cache[$table][$column]
            ?? null;
    }

    return $values;
}


function llama_place_update_current_value(
    PDO $db,
    int $placeId,
    string $path,
    bool $lock = false
): mixed {
    $definitions =
        llama_place_update_definitions();

    if (
        !isset(
            $definitions[$path]
        )
    ) {
        throw new RuntimeException(
            'Unsupported Place update field: '
            . $path
        );
    }

    $definition =
        $definitions[$path];

    $table =
        (string) $definition['table'];

    $column =
        (string) $definition['column'];

    if (
        !llama_place_update_valid_identifier(
            $table
        )
        || !llama_place_update_valid_identifier(
            $column
        )
    ) {
        throw new RuntimeException(
            'Invalid Place update storage definition.'
        );
    }


    if ($table === 'places') {
        $sql =
            "SELECT `$column`
             FROM places
             WHERE id = ?
             LIMIT 1";

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt =
            $db->prepare(
                $sql
            );

        $stmt->execute([
            $placeId,
        ]);

        $value =
            $stmt->fetchColumn();

        return $value === false
            ? null
            : $value;
    }


    if (
        $table === 'place_sensory'
    ) {
        $period =
            (string) (
                $definition['period']
                ?? ''
            );

        $sql =
            "SELECT `$column`
             FROM place_sensory
             WHERE place_id = ?
               AND period = ?
             LIMIT 1";

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $stmt =
            $db->prepare(
                $sql
            );

        $stmt->execute([
            $placeId,
            $period,
        ]);

        $value =
            $stmt->fetchColumn();

        return $value === false
            ? null
            : $value;
    }


    $sql =
        "SELECT `$column`
         FROM `$table`
         WHERE place_id = ?
         LIMIT 1";

    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt =
        $db->prepare(
            $sql
        );

    $stmt->execute([
        $placeId,
    ]);

    $value =
        $stmt->fetchColumn();

    return $value === false
        ? null
        : $value;
}


/*
 * =========================================================
 * FORM VALUES
 * =========================================================
 */


function llama_place_update_shared_form_values(
    array $currentValues,
    array $unknownFields = [],
    array $proposedValues = []
): array {
    $values = [];

    $unknownLookup =
        array_fill_keys(
            array_map(
                'strval',
                $unknownFields
            ),
            true
        );

    foreach (
        llama_place_update_definitions()
        as $storage => $definition
    ) {
        $field =
            $definition['shared'];

        $key =
            (string) $field['key'];

        $type =
            (string) $field['type'];

        $hasProposed =
            array_key_exists(
                $storage,
                $proposedValues
            );

        $hasCurrent =
            array_key_exists(
                $storage,
                $currentValues
            );

        if (
            !$hasProposed
            && !$hasCurrent
        ) {
            continue;
        }

        $value =
            $hasProposed
                ? $proposedValues[$storage]
                : $currentValues[$storage];


        if ($type === 'checkbox') {
            if ((bool) $value) {
                $values[$key] = '1';
            }

            continue;
        }


        if (
            !$hasProposed
            && isset(
                $unknownLookup[$key]
            )
            && !empty(
                $field['allow_unknown']
            )
        ) {
            $values[$key] =
                llama_place_report_unknown_token();

            continue;
        }


        if (
            $hasProposed
            && $value === null
            && !empty(
                $field['allow_unknown']
            )
        ) {
            $values[$key] =
                llama_place_report_unknown_token();

            continue;
        }


        if (
            in_array(
                $type,
                [
                    'tri',
                    'rating',
                ],
                true
            )
            && $value === null
        ) {
            $values[$key] =
                llama_place_report_unanswered_token();

            continue;
        }


        if ($value === null) {
            $values[$key] = '';
            continue;
        }


        if (is_bool($value)) {
            $values[$key] =
                $value
                    ? '1'
                    : '0';

            continue;
        }


        $values[$key] =
            (string) $value;
    }

    return $values;
}


/*
 * =========================================================
 * CHANGE DETECTION
 * =========================================================
 */


function llama_place_update_value_equal(
    mixed $a,
    mixed $b
): bool {
    if (is_bool($a)) {
        $a =
            $a
                ? '1'
                : '0';
    }

    if (is_bool($b)) {
        $b =
            $b
                ? '1'
                : '0';
    }


    if (
        $a === null
        || $b === null
    ) {
        return $a === $b;
    }


    /*
     * Database drivers frequently return numeric columns as
     * strings. Compare normalized scalar values so "4" and 4
     * are not treated as a change.
     */
    return (string) $a
        === (string) $b;
}


function llama_place_update_build_changes(
    array $input,
    array $current
): array {
    $proposed = [];
    $original = [];
    $unknownFields = [];

    foreach (
        llama_place_update_definitions()
        as $path => $definition
    ) {
        $field =
            $definition['shared'];

        $key =
            (string) $field['key'];

        $type =
            (string) $field['type'];


        /*
         * Unchecked checkboxes are valid False values.
         */
        if ($type === 'checkbox') {
            $raw =
                !empty(
                    $input[$key]
                )
                    ? '1'
                    : '0';
        } else {
            if (
                !array_key_exists(
                    $key,
                    $input
                )
            ) {
                continue;
            }

            $raw =
                $input[$key];
        }


        if (is_array($raw)) {
            continue;
        }


        $newValue =
            llama_place_report_parse_field(
                $field,
                $raw,
                $unknownFields
            );

        $oldValue =
            $current[$path]
            ?? null;


        if (
            llama_place_update_value_equal(
                $oldValue,
                $newValue
            )
        ) {
            continue;
        }


        $proposed[$path] =
            $newValue;

        $original[$path] =
            $oldValue;
    }

    return [
        'proposed' =>
            $proposed,

        'original' =>
            $original,

        'unknown_fields' =>
            array_values(
                array_keys(
                    $unknownFields
                )
            ),
    ];
}


/*
 * =========================================================
 * JSON / HISTORY
 * =========================================================
 */


function llama_place_update_decode_json(
    mixed $json,
    array $fallback = []
): array {
    if (is_array($json)) {
        return $json;
    }

    if (
        !is_string($json)
        || trim($json) === ''
    ) {
        return $fallback;
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    return is_array($decoded)
        ? $decoded
        : $fallback;
}


function llama_place_update_fetch_row(
    PDO $db,
    int $updateId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_update_submissions
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $updateId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


function llama_place_update_history(
    array $row
): array {
    return
        llama_place_update_decode_json(
            $row['revision_history']
            ?? null
        );
}


function llama_place_update_append_history(
    PDO $db,
    int $updateId,
    array $event
): void {
    $row =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if (!$row) {
        throw new RuntimeException(
            'Place update history could not be found.'
        );
    }

    $history =
        llama_place_update_history(
            $row
        );

    $event['at'] =
        $event['at']
        ?? gmdate(
            'Y-m-d H:i:s'
        );

    $history[] = $event;

    $stmt =
        $db->prepare(
            'UPDATE place_update_submissions
             SET revision_history = ?
             WHERE id = ?'
        );

    $stmt->execute([
        json_encode(
            $history,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $updateId,
    ]);
}


function llama_place_update_changed_proposals(
    array $before,
    array $after
): array {
    $paths =
        array_unique(
            array_merge(
                array_keys($before),
                array_keys($after)
            )
        );

    $changes = [];

    foreach ($paths as $path) {
        $old =
            $before[$path]
            ?? null;

        $new =
            $after[$path]
            ?? null;

        $hadOld =
            array_key_exists(
                $path,
                $before
            );

        $hasNew =
            array_key_exists(
                $path,
                $after
            );


        if (
            $hadOld === $hasNew
            && llama_place_update_value_equal(
                $old,
                $new
            )
        ) {
            continue;
        }


        $changes[$path] = [
            'before_present' =>
                $hadOld,

            'before' =>
                $old,

            'after_present' =>
                $hasNew,

            'after' =>
                $new,
        ];
    }

    return $changes;
}


/*
 * =========================================================
 * SUBMIT NEW UPDATE
 * =========================================================
 */


function llama_place_update_submit(
    int $userId,
    array $place,
    array $input
): int {
    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    if (
        $userId < 1
        || $placeId < 1
    ) {
        throw new InvalidArgumentException(
            'Invalid Place update.'
        );
    }


    if (
        community_open_update_for_user(
            $userId,
            $placeId
        )
    ) {
        throw new RuntimeException(
            'You already have an open update for this Place.'
        );
    }


    $db = db();

    $current =
        llama_place_update_current_values(
            $db,
            $placeId
        );

    $changes =
        llama_place_update_build_changes(
            $input,
            $current
        );

    $proposed =
        $changes['proposed'];

    $original =
        $changes['original'];


    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );


    if (
        !$proposed
        && !$submittedPhotos
    ) {
        throw new InvalidArgumentException(
            'Nothing changed. Edit at least one Place value or add a current photo before submitting.'
        );
    }


    if (
        $submittedPhotos
        && $photoToken === ''
    ) {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }


    $visitedAt =
        community_clean_text(
            $input['visited_at']
            ?? null,
            30
        );

    $notes =
        community_clean_text(
            $input['contributor_notes']
            ?? null
        );


    $updateId = 0;

    try {
        $db->beginTransaction();


        $stmt =
            $db->prepare(
                'INSERT INTO place_update_submissions
                    (
                        place_id,
                        user_id,
                        update_type,
                        status,
                        role_at_submission,
                        visited_at,
                        proposed_changes,
                        original_values,
                        photos,
                        contributor_notes
                    )
                 VALUES
                    (
                        :place_id,
                        :user_id,
                        :update_type,
                        :status,
                        :role_at_submission,
                        :visited_at,
                        :proposed_changes,
                        :original_values,
                        :photos,
                        :contributor_notes
                    )'
            );


        $stmt->execute([
            ':place_id' =>
                $placeId,

            ':user_id' =>
                $userId,

            ':update_type' =>
                'update',

            ':status' =>
                'pending',

            ':role_at_submission' =>
                community_role_at_submission(
                    $userId
                ),

            ':visited_at' =>
                $visitedAt !== null
                    ? $visitedAt
                        . (
                            strlen(
                                $visitedAt
                            ) === 10
                                ? ' 00:00:00'
                                : ''
                        )
                    : null,

            ':proposed_changes' =>
                json_encode(
                    $proposed,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),

            ':original_values' =>
                json_encode(
                    $original,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),

            ':photos' =>
                '[]',

            ':contributor_notes' =>
                $notes,
        ]);


        $updateId =
            (int) $db->lastInsertId();


        if ($photoToken !== '') {
            $committedPhotos =
                llama_photo_commit_stage(
                    'update-place',
                    $userId,
                    $photoToken,
                    $submittedPhotos,
                    '/uploads/place-updates/'
                    . $updateId
                );

            $photoUpdate =
                $db->prepare(
                    'UPDATE place_update_submissions
                     SET photos = ?
                     WHERE id = ?
                       AND user_id = ?'
                );

            $photoUpdate->execute([
                json_encode(
                    $committedPhotos,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
                $updateId,
                $userId,
            ]);
        }


        $db->commit();

    } catch (Throwable $exception) {
        if (
            $db->inTransaction()
        ) {
            $db->rollBack();
        }

        if ($updateId > 0) {
            llama_photo_remove_tree(
                dirname(__DIR__)
                . '/uploads/place-updates/'
                . $updateId
            );
        }

        throw $exception;
    }


    $row =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if ($row) {
        llama_place_update_append_history(
            $db,
            $updateId,
            [
                'type' =>
                    'submitted',

                'by' =>
                    'contributor',

                'proposed' =>
                    llama_place_update_decode_json(
                        $row['proposed_changes']
                        ?? '{}'
                    ),

                'original' =>
                    llama_place_update_decode_json(
                        $row['original_values']
                        ?? '{}'
                    ),

                'photo_count' =>
                    count(
                        llama_place_update_decode_json(
                            $row['photos']
                            ?? '[]'
                        )
                    ),

                'visited_at' =>
                    $row['visited_at']
                    ?? null,

                'contributor_notes' =>
                    $row['contributor_notes']
                    ?? null,
            ]
        );
    }


    return $updateId;
}


/*
 * =========================================================
 * RESUBMIT AFTER MODERATOR REQUEST
 * =========================================================
 */


function llama_place_update_resubmit(
    int $userId,
    array $place,
    int $updateId,
    array $input
): int {
    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    if (
        $userId < 1
        || $placeId < 1
        || $updateId < 1
    ) {
        throw new InvalidArgumentException(
            'Invalid Place update.'
        );
    }


    $db = db();

    $before =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if (
        !$before
        || (int) (
            $before['user_id']
            ?? 0
        ) !== $userId
        || (int) (
            $before['place_id']
            ?? 0
        ) !== $placeId
        || (string) (
            $before['status']
            ?? ''
        ) !== 'needs-changes'
    ) {
        throw new RuntimeException(
            'This Place update is no longer available for resubmission.'
        );
    }


    $current =
        llama_place_update_current_values(
            $db,
            $placeId
        );

    $changes =
        llama_place_update_build_changes(
            $input,
            $current
        );

    $proposed =
        $changes['proposed'];

    $original =
        $changes['original'];


    $existingPhotos =
        llama_place_update_decode_json(
            $before['photos']
            ?? '[]'
        );


    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );


    if (
        !$proposed
        && !$existingPhotos
        && !$submittedPhotos
    ) {
        throw new InvalidArgumentException(
            'Nothing changed. Edit at least one Place value or include a current photo before resubmitting.'
        );
    }


    if (
        $submittedPhotos
        && $photoToken === ''
    ) {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }


    $visitedAt =
        community_clean_text(
            $input['visited_at']
            ?? null,
            30
        );

    $notes =
        community_clean_text(
            $input['contributor_notes']
            ?? null
        );


    $beforeProposed =
        llama_place_update_decode_json(
            $before['proposed_changes']
            ?? '{}'
        );

    $reviewRequest =
        trim(
            (string) (
                $before['review_notes']
                ?? ''
            )
        );

    $newCommittedPhotos = [];


    try {
        $db->beginTransaction();


        if ($photoToken !== '') {
            $newCommittedPhotos =
                llama_photo_commit_stage(
                    'update-place',
                    $userId,
                    $photoToken,
                    $submittedPhotos,
                    '/uploads/place-updates/'
                    . $updateId
                );
        }


        $photos =
            array_values(
                array_merge(
                    $existingPhotos,
                    $newCommittedPhotos
                )
            );


        $stmt =
            $db->prepare(
                'UPDATE place_update_submissions
                 SET
                    status = "pending",
                    role_at_submission = ?,
                    visited_at = ?,
                    proposed_changes = ?,
                    original_values = ?,
                    photos = ?,
                    contributor_notes = ?,
                    submitted_at = CURRENT_TIMESTAMP,
                    reviewed_at = NULL,
                    reviewed_by = NULL,
                    review_notes = NULL
                 WHERE id = ?
                   AND user_id = ?
                   AND place_id = ?
                   AND status = "needs-changes"'
            );


        $stmt->execute([
            community_role_at_submission(
                $userId
            ),

            $visitedAt !== null
                ? $visitedAt
                    . (
                        strlen(
                            $visitedAt
                        ) === 10
                            ? ' 00:00:00'
                            : ''
                    )
                : null,

            json_encode(
                $proposed,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),

            json_encode(
                $original,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),

            json_encode(
                $photos,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),

            $notes,
            $updateId,
            $userId,
            $placeId,
        ]);


        if (
            $stmt->rowCount()
            !== 1
        ) {
            throw new RuntimeException(
                'The Place update changed before it could be resubmitted.'
            );
        }


        $db->commit();

    } catch (Throwable $exception) {
        if (
            $db->inTransaction()
        ) {
            $db->rollBack();
        }

        foreach (
            $newCommittedPhotos
            as $photo
        ) {
            $src =
                llama_place_report_photo_path(
                    $photo
                );

            if ($src === '') {
                continue;
            }

            $absolute =
                dirname(__DIR__)
                . $src;

            if (
                is_file(
                    $absolute
                )
            ) {
                @unlink(
                    $absolute
                );
            }
        }

        throw $exception;
    }


    $after =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );

    if ($after) {
        $afterProposed =
            llama_place_update_decode_json(
                $after['proposed_changes']
                ?? '{}'
            );

        $afterPhotos =
            llama_place_update_decode_json(
                $after['photos']
                ?? '[]'
            );

        llama_place_update_append_history(
            $db,
            $updateId,
            [
                'type' =>
                    'resubmitted',

                'by' =>
                    'contributor',

                'requested_changes' =>
                    $reviewRequest !== ''
                        ? $reviewRequest
                        : null,

                'proposal_changes' =>
                    llama_place_update_changed_proposals(
                        $beforeProposed,
                        $afterProposed
                    ),

                'proposed' =>
                    $afterProposed,

                'photo_count_before' =>
                    count(
                        $existingPhotos
                    ),

                'photo_count_after' =>
                    count(
                        $afterPhotos
                    ),

                'visited_at' =>
                    $after['visited_at']
                    ?? null,

                'contributor_notes' =>
                    $after['contributor_notes']
                    ?? null,
            ]
        );
    }


    return $updateId;
}


/*
 * =========================================================
 * APPLY APPROVED VALUE
 * =========================================================
 */


function llama_place_update_apply_field(
    PDO $db,
    int $placeId,
    string $path,
    mixed $value
): void {
    $definitions =
        llama_place_update_definitions();

    if (
        !isset(
            $definitions[$path]
        )
    ) {
        throw new RuntimeException(
            'Unsupported Place update field: '
            . $path
        );
    }

    $definition =
        $definitions[$path];

    $table =
        (string) $definition['table'];

    $column =
        (string) $definition['column'];

    if (
        !llama_place_update_valid_identifier(
            $table
        )
        || !llama_place_update_valid_identifier(
            $column
        )
    ) {
        throw new RuntimeException(
            'Invalid Place update storage definition.'
        );
    }


    $storedValue =
        is_bool($value)
            ? (
                $value
                    ? 1
                    : 0
            )
            : $value;


    if ($table === 'places') {
        $stmt =
            $db->prepare(
                "UPDATE places
                 SET `$column` = ?
                 WHERE id = ?"
            );

        $stmt->execute([
            $storedValue,
            $placeId,
        ]);


        if ($path === 'latitude') {
            $db->prepare(
                'UPDATE places
                 SET public_latitude = ?
                 WHERE id = ?'
            )->execute([
                $storedValue !== null
                    ? round(
                        (float) $storedValue,
                        1
                    )
                    : null,

                $placeId,
            ]);
        }


        if ($path === 'longitude') {
            $db->prepare(
                'UPDATE places
                 SET public_longitude = ?
                 WHERE id = ?'
            )->execute([
                $storedValue !== null
                    ? round(
                        (float) $storedValue,
                        1
                    )
                    : null,

                $placeId,
            ]);
        }


        return;
    }


    if (
        $table === 'place_sensory'
    ) {
        $period =
            (string) (
                $definition['period']
                ?? ''
            );

        $exists =
            $db->prepare(
                'SELECT id
                 FROM place_sensory
                 WHERE place_id = ?
                   AND period = ?
                 LIMIT 1'
            );

        $exists->execute([
            $placeId,
            $period,
        ]);


        if (
            $exists->fetchColumn()
        ) {
            $stmt =
                $db->prepare(
                    "UPDATE place_sensory
                     SET `$column` = ?
                     WHERE place_id = ?
                       AND period = ?"
                );

            $stmt->execute([
                $storedValue,
                $placeId,
                $period,
            ]);
        } else {
            $stmt =
                $db->prepare(
                    "INSERT INTO place_sensory
                        (
                            place_id,
                            period,
                            `$column`
                        )
                     VALUES (?, ?, ?)"
                );

            $stmt->execute([
                $placeId,
                $period,
                $storedValue,
            ]);
        }


        return;
    }


    $exists =
        $db->prepare(
            "SELECT place_id
             FROM `$table`
             WHERE place_id = ?
             LIMIT 1"
        );

    $exists->execute([
        $placeId,
    ]);


    if (
        $exists->fetchColumn()
    ) {
        $stmt =
            $db->prepare(
                "UPDATE `$table`
                 SET `$column` = ?
                 WHERE place_id = ?"
            );

        $stmt->execute([
            $storedValue,
            $placeId,
        ]);
    } else {
        $stmt =
            $db->prepare(
                "INSERT INTO `$table`
                    (
                        place_id,
                        `$column`
                    )
                 VALUES (?, ?)"
            );

        $stmt->execute([
            $placeId,
            $storedValue,
        ]);
    }
}


/*
 * =========================================================
 * MODERATION APPROVAL
 * =========================================================
 */


function llama_place_update_approve(
    PDO $db,
    int $updateId,
    int $reviewedBy,
    string $reviewNotes
): int {
    if (
        !$db->inTransaction()
    ) {
        throw new RuntimeException(
            'Place update approval requires an active database transaction.'
        );
    }


    $update =
        moderation_update(
            $db,
            $updateId,
            true
        );

    if (!$update) {
        throw new RuntimeException(
            'The Place update could not be found.'
        );
    }


    if (
        !in_array(
            (string) (
                $update['status']
                ?? ''
            ),
            [
                'pending',
                'needs-changes',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'This Place update is no longer awaiting review.'
        );
    }


    $placeId =
        (int) $update['place_id'];

    $proposed =
        is_array(
            $update['proposed']
            ?? null
        )
            ? $update['proposed']
            : [];

    $original =
        is_array(
            $update['original']
            ?? null
        )
            ? $update['original']
            : [];


    foreach (
        $proposed
        as $path => $value
    ) {
        if (
            !array_key_exists(
                $path,
                $original
            )
        ) {
            throw new RuntimeException(
                'This update is missing its original value for '
                . $path
                . '.'
            );
        }


        $current =
            llama_place_update_current_value(
                $db,
                $placeId,
                (string) $path,
                true
            );


        if (
            !llama_place_update_value_equal(
                $current,
                $original[$path]
            )
        ) {
            throw new RuntimeException(
                'This Place changed after the contribution was submitted. Review the current value of "'
                . str_replace(
                    [
                        '.',
                        '_',
                    ],
                    ' ',
                    (string) $path
                )
                . '" before approving.'
            );
        }
    }


    $photos =
        is_array(
            $update['photo_list']
            ?? null
        )
            ? $update['photo_list']
            : [];


    if (
        !$proposed
        && !$photos
    ) {
        throw new RuntimeException(
            'This update does not contain any changes.'
        );
    }


    foreach (
        $proposed
        as $path => $value
    ) {
        llama_place_update_apply_field(
            $db,
            $placeId,
            (string) $path,
            $value
        );
    }


    moderation_attach_place_photos(
        $db,
        $placeId,
        (int) $update['user_id'],
        $photos,
        '/uploads/place-updates/'
        . $updateId
        . '/'
    );


    $points =
        llama_points_policy_required(
            $db,
            'approved_place_update'
        );


    $contributionId =
        moderation_insert_contribution(
            $db,
            $placeId,
            (int) $update['user_id'],
            null,
            (string) (
                $update['update_type']
                ?? 'update'
            ),
            trim(
                (string) (
                    $update['role_at_submission']
                    ?? 'user'
                )
            ),
            !empty(
                $update['visited_at']
            )
                ? (string) $update['visited_at']
                : null,
            $reviewedBy,
            $points,
            array_keys(
                $proposed
            ),
            $reviewNotes !== ''
                ? $reviewNotes
                : null
        );


    moderation_award_badge(
        $db,
        (int) $update['user_id'],
        'first-contribution'
    );

    moderation_award_badge(
        $db,
        (int) $update['user_id'],
        'helpful-editor'
    );


    $stmt =
        $db->prepare(
            'UPDATE place_update_submissions
             SET
                status = ?,
                reviewed_by = ?,
                review_notes = ?,
                reviewed_at = CURRENT_TIMESTAMP,
                contribution_id = ?,
                points_awarded = ?
             WHERE id = ?'
        );

    $stmt->execute([
        'approved',
        $reviewedBy,
        $reviewNotes !== ''
            ? $reviewNotes
            : null,
        $contributionId,
        max(
            0,
            $points
        ),
        $updateId,
    ]);


    moderation_remove_tree(
        dirname(__DIR__)
        . '/uploads/place-updates/'
        . $updateId
    );


    return $contributionId;
}


/*
 * =========================================================
 * HISTORY FROM MODERATION
 * =========================================================
 */


function llama_place_update_record_review(
    PDO $db,
    int $updateId,
    int $moderatorId,
    string $type,
    string $notes,
    array $item
): void {
    llama_place_update_append_history(
        $db,
        $updateId,
        [
            'type' =>
                $type,

            'by' =>
                'moderator',

            'moderator_id' =>
                $moderatorId,

            'review_notes' =>
                $notes,

            'proposed' =>
                is_array(
                    $item['proposed']
                    ?? null
                )
                    ? $item['proposed']
                    : [],

            'photo_count' =>
                count(
                    is_array(
                        $item['photo_list']
                        ?? null
                    )
                        ? $item['photo_list']
                        : []
                ),
        ]
    );
}


/*
 * =========================================================
 * DISPLAY HELPERS
 * =========================================================
 */


function llama_place_update_display_value(
    string $path,
    mixed $value,
    bool $explicitUnknown = false
): string {
    if ($explicitUnknown) {
        return 'Unknown';
    }

    if (
        $value === null
        || $value === ''
    ) {
        return 'Not provided';
    }


    $field =
        llama_place_update_field_by_storage(
            $path
        );

    if (!$field) {
        if (is_bool($value)) {
            return $value
                ? 'Yes'
                : 'No';
        }

        return (string) $value;
    }


    $type =
        (string) (
            $field['type']
            ?? ''
        );


    if (
        in_array(
            $type,
            [
                'tri',
                'checkbox',
            ],
            true
        )
    ) {
        return (bool) $value
            ? 'Yes'
            : 'No';
    }


    if ($type === 'rating') {
        return (int) $value
            . '/5';
    }


    if ($type === 'select') {
        $options =
            (array) (
                $field['options']
                ?? []
            );

        if (
            array_key_exists(
                (string) $value,
                $options
            )
        ) {
            return (string) $options[
                (string) $value
            ];
        }
    }


    if (
        ($field['format'] ?? '')
        === 'currency'
    ) {
        return '$'
            . number_format(
                (float) $value,
                2
            );
    }


    return (string) $value;
}


function llama_place_update_schema_icon(
    string $path
): string {
    $field =
        llama_place_update_field_by_storage(
            $path
        );

    if (!$field) {
        return 'fa-pen-to-square';
    }


    if (
        function_exists(
            'llama_place_report_field_icon'
        )
    ) {
        return
            llama_place_report_field_icon(
                (string) $field['key'],
                $field
            );
    }


    return match (
        (string) (
            $field['section']
            ?? ''
        )
    ) {
        'location' =>
            'fa-location-dot',

        'site_vehicle' =>
            'fa-car-side',

        'road_access' =>
            'fa-road',

        'amenities' =>
            'fa-circle-info',

        'connectivity' =>
            'fa-signal',

        'sensory' =>
            'fa-brain',

        'environment_accessibility' =>
            'fa-person-walking',

        'rules' =>
            'fa-cloud-sun',

        'experience' =>
            'fa-compass',

        default =>
            'fa-pen-to-square',
    };
}

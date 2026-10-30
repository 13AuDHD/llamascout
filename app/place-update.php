<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/points.php';
require_once __DIR__ . '/access.php';


/*
 * =========================================================
 * PLACE UPDATE SYSTEM
 *
 * Suggest an Update uses the shared Place Report schema.
 * Field values and explicit Unknown answer state are both
 * treated as part of the proposed change.
 * =========================================================
 */


/*
 * =========================================================
 * SCHEMA
 * =========================================================
 */



function llama_place_update_definitions(): array
{
    return llama_place_report_published_definitions();
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
                        'edit',
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
 * DATABASE VALUE READERS
 * =========================================================
 */


function llama_place_update_current_values(PDO $db, int $placeId, bool $lock = false): array
{
    return llama_place_report_current_values($db, $placeId, $lock);
}


function llama_place_update_current_value(PDO $db, int $placeId, string $path, bool $lock = false): mixed
{
    return llama_place_report_current_value($db, $placeId, $path, $lock);
}


/*
 * =========================================================
 * FORM VALUE BUILDER
 * =========================================================
 */


function llama_place_update_shared_form_values(array $currentValues, array $currentUnknownFields = [],
    array $proposedValues = [], array $proposedUnknownFields = []): array
{
    $values = [];
    foreach (llama_place_update_definitions() as $storage => $definition) {
        $field = $definition['shared'];
        $proposed = array_key_exists($storage, $proposedValues);
        if (!$proposed && !array_key_exists($storage, $currentValues)) { continue; }
        $values[$field['key']] = llama_place_report_answer_form_value($field,
            $proposed ? $proposedValues[$storage] : $currentValues[$storage],
            in_array($field['key'], $proposed ? $proposedUnknownFields : $currentUnknownFields, true));
    }
    return $values;
}


/*
 * =========================================================
 * CHANGE DETECTION
 * =========================================================
 */


function llama_place_update_value_equal(mixed $a, mixed $b, ?array $field = null): bool
{
    return llama_place_report_values_equal($a, $b, $field);
}


function llama_place_update_build_changes(
    array $input,
    array $current,
    array $currentUnknownFields = []
): array {
    $partialReport =
        !empty(
            $input['_report_partial']
        );

    $input = llama_place_report_expand_presence($input);
    $proposed = [];
    $original = [];

    $newUnknownFields = [];
    $originalUnknownFields = [];


    $currentUnknownLookup =
        array_fill_keys(
            array_map(
                'strval',
                $currentUnknownFields
            ),
            true
        );


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


        if (
            $partialReport
            && empty(
                $input['_report_touched'][$key]
            )
        ) {
            continue;
        }

        if (!array_key_exists($key, $input) || !empty($field['derived'])
            || !in_array('place', (array) $field['report_scopes'], true)) {
            continue;
        }
        $raw = $input[$key];

        $submissionAnswer =
            null;

        if ($partialReport) {
            $submissionAnswer =
                llama_place_report_submission_answer(
                    $field,
                    $raw
                );

            /*
             * Unanswered means this report has no observation for the
             * question. It must never erase or clear an existing current
             * answer. Explicit No, Unknown and an intentional empty list are
             * still recorded answers and continue below.
             */
            if (
                !$submissionAnswer['recorded']
            ) {
                continue;
            }
        }

        if ($type === 'multiselect' && $raw === [] && ($current[$path] ?? null) === null
            && !isset($currentUnknownLookup[$key]) && empty($input['_report_touched'][$key])) { continue; }
        // Preserve unchanged historical options without accepting new invalid values.
        if (!$partialReport
            && $raw !== llama_place_report_unknown_token() && $raw !== llama_place_report_unanswered_token()
            && !isset($currentUnknownLookup[$key])
            && llama_place_report_values_equal($raw, $current[$path] ?? null, $field)) {
            continue;
        }

        $fieldUnknown = [];


        if ($submissionAnswer !== null) {
            $newValue =
                $submissionAnswer['value'];

            if (
                $submissionAnswer['state']
                === 'unknown'
            ) {
                $fieldUnknown[$key] =
                    true;
            }
        } else {
            $newValue =
                llama_place_report_parse_field(
                    $field,
                    $raw,
                    $fieldUnknown
                );
        }


        $oldValue =
            $current[$path]
            ?? null;


        $newIsUnknown =
            isset(
                $fieldUnknown[$key]
            );


        $oldIsUnknown =
            isset(
                $currentUnknownLookup[$key]
            );


        $valueChanged =
            !llama_place_update_value_equal(
                $oldValue,
                $newValue,
                $field
            );


        /*
         * Answer state can change even when both values are NULL.
         *
         * Examples:
         * blank -> Unknown
         * Unknown -> blank
         */
        $answerStateChanged =
            !empty(
                $field['allow_unknown']
            )
            && (
                $oldIsUnknown
                !== $newIsUnknown
            );


        if (
            !$partialReport
            && !$valueChanged
            && !$answerStateChanged
        ) {
            continue;
        }


        $proposed[$path] =
            $newValue;

        $original[$path] =
            $oldValue;


        if ($newIsUnknown) {
            $newUnknownFields[$key] =
                true;
        }


        if ($oldIsUnknown) {
            $originalUnknownFields[$key] =
                true;
        }
    }


    return [
        'proposed' =>
            $proposed,

        'original' =>
            $original,

        'unknown_fields' =>
            array_values(
                array_keys(
                    $newUnknownFields
                )
            ),

        'original_unknown_fields' =>
            array_values(
                array_keys(
                    $originalUnknownFields
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


    $history[] =
        $event;


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


    foreach (
        $paths
        as $path
    ) {
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


        $field =
            llama_place_report_field_by_storage(
                (string) $path
            );


        if (
            $hadOld === $hasNew
            && llama_place_update_value_equal(
                $old,
                $new,
                $field
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


function llama_place_update_latest_contributor_event(
    array $row
): ?array {
    $history =
        llama_place_update_history(
            $row
        );


    for (
        $index = count($history) - 1;
        $index >= 0;
        $index--
    ) {
        $event =
            $history[$index];


        if (!is_array($event)) {
            continue;
        }


        if (
            in_array(
                (string) (
                    $event['type']
                    ?? ''
                ),
                [
                    'submitted',
                    'resubmitted',
                ],
                true
            )
        ) {
            return $event;
        }
    }


    return null;
}


function llama_place_update_latest_unknown_fields(
    array $row
): array {
    $event =
        llama_place_update_latest_contributor_event(
            $row
        );


    if (!$event) {
        return [];
    }


    $fields =
        is_array(
            $event['unknown_fields']
            ?? null
        )
            ? $event['unknown_fields']
            : [];


    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $fields
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        )
    );
}


function llama_place_update_latest_original_unknown_fields(
    array $row
): array {
    $event =
        llama_place_update_latest_contributor_event(
            $row
        );


    if (!$event) {
        return [];
    }


    $fields =
        is_array(
            $event['original_unknown_fields']
            ?? null
        )
            ? $event['original_unknown_fields']
            : [];


    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $fields
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        )
    );
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
        !user_has_place_complete_access(
            $placeId,
            $userId
        )
    ) {
        throw new RuntimeException(
            'Complete Access is required to update this Place.'
        );
    }

    if (
        !llama_contributor_can(
            db(),
            $userId,
            'submit_update'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit Place updates.'
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


    $currentUnknownFields =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );


    $changes =
        llama_place_update_build_changes(
            $input,
            $current,
            $currentUnknownFields
        );


    $proposed =
        $changes['proposed'];

    $original =
        $changes['original'];

    $unknownFields =
        $changes['unknown_fields'];

    $originalUnknownFields =
        $changes[
            'original_unknown_fields'
        ];


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
            'Nothing was reported. Answer at least one Place question or add a current photo before submitting.'
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
        llama_place_report_metadata($input, 'visited_at');


    $notes =
        llama_place_report_metadata($input, 'contributor_notes');


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


        llama_place_update_append_history(
            $db,
            $updateId,
            [
                'type' =>
                    'submitted',

                'by' =>
                    'contributor',

                'proposed' =>
                    $proposed,

                'original' =>
                    $original,

                'unknown_fields' =>
                    $unknownFields,

                'original_unknown_fields' =>
                    $originalUnknownFields,

                'photo_count' =>
                    count(
                        $submittedPhotos
                    ),

                'visited_at' =>
                    $visitedAt,

                'contributor_notes' =>
                    $notes,
            ]
        );


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

    if (
        !user_has_place_complete_access(
            $placeId,
            $userId
        )
    ) {
        throw new RuntimeException(
            'Complete Access is required to update this Place.'
        );
    }

    if (
        !llama_contributor_can(
            db(),
            $userId,
            'submit_update'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit Place updates.'
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


    $currentUnknownFields =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );


    $changes =
        llama_place_update_build_changes(
            $input,
            $current,
            $currentUnknownFields
        );


    $proposed =
        $changes['proposed'];

    $original =
        $changes['original'];

    $unknownFields =
        $changes['unknown_fields'];

    $originalUnknownFields =
        $changes[
            'original_unknown_fields'
        ];


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
            'Nothing was reported. Answer at least one Place question or include a current photo before resubmitting.'
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
        llama_place_report_metadata($input, 'visited_at');


    $notes =
        llama_place_report_metadata($input, 'contributor_notes');


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
                        $proposed
                    ),

                'proposed' =>
                    $proposed,

                'original' =>
                    $original,

                'unknown_fields' =>
                    $unknownFields,

                'original_unknown_fields' =>
                    $originalUnknownFields,

                'photo_count_before' =>
                    count(
                        $existingPhotos
                    ),

                'photo_count_after' =>
                    count(
                        $photos
                    ),

                'visited_at' =>
                    $visitedAt,

                'contributor_notes' =>
                    $notes,
            ]
        );


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


    return $updateId;
}


/*
 * =========================================================
 * APPLY APPROVED VALUE
 * =========================================================
 */


/*
 * =========================================================
 * DELETE UNPUBLISHED UPDATE
 * =========================================================
 */


function llama_place_update_delete_unpublished(
    PDO $db,
    int $updateId
): array {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Deleting a Place update requires an active transaction.'
        );
    }


    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_update_submissions
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );


    $stmt->execute([
        $updateId,
    ]);


    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$row) {
        throw new RuntimeException(
            'The Place update could not be found.'
        );
    }


    if (
        (string) (
            $row['status']
            ?? ''
        ) === 'approved'
    ) {
        throw new RuntimeException(
            'An approved Place update cannot be deleted here.'
        );
    }


    $delete =
        $db->prepare(
            'DELETE FROM place_update_submissions
             WHERE id = ?
               AND status <> "approved"'
        );


    $delete->execute([
        $updateId,
    ]);


    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'The Place update changed before it could be deleted.'
        );
    }


    return $row;
}


function llama_place_update_remove_files(
    int $updateId
): void {
    $path =
        dirname(__DIR__)
        . '/uploads/place-updates/'
        . $updateId;


    if (
        function_exists(
            'moderation_remove_tree'
        )
    ) {
        moderation_remove_tree(
            $path
        );

        return;
    }


    if (!is_dir($path)) {
        return;
    }


    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );


    foreach ($iterator as $item) {

        if ($item->isDir()) {
            @rmdir(
                $item->getPathname()
            );

            continue;
        }


        @unlink(
            $item->getPathname()
        );
    }


    @rmdir(
        $path
    );
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
    string $reviewNotes,
    array &$copiedPhotoPaths = []
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


    $placeLock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
    $placeLock->execute([$placeId]);
    if (!$placeLock->fetchColumn()) { throw new RuntimeException('Place not found.'); }

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


    if (array_key_exists('__scoped_report__', $proposed)) {
        require_once __DIR__ . '/place-report/scoped-update-review.php';
        return llama_scoped_update_approve(
            $db,
            $update,
            $updateId,
            $reviewedBy,
            $reviewNotes,
            $copiedPhotoPaths
        );
    }

    $historyRow =
        llama_place_update_fetch_row(
            $db,
            $updateId
        );


    $explicitUnknownFields =
        $historyRow
            ? llama_place_update_latest_unknown_fields(
                $historyRow
            )
            : [];


    $originalUnknownFields =
        $historyRow
            ? llama_place_update_latest_original_unknown_fields(
                $historyRow
            )
            : [];


    $newUnknownLookup =
        array_fill_keys(
            $explicitUnknownFields,
            true
        );


    $originalUnknownLookup =
        array_fill_keys(
            $originalUnknownFields,
            true
        );


    /*
     * Current published answer state at approval time.
     */
    $currentPublishedUnknown =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );


    $currentPublishedUnknownLookup =
        array_fill_keys(
            $currentPublishedUnknown,
            true
        );


    $definitions =
        llama_place_update_definitions();


    /*
     * Validate both value and answer state against the
     * snapshot that existed when the contributor submitted.
     */
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


        $definition =
            $definitions[$path]
            ?? null;


        if (!$definition) {
            throw new RuntimeException(
                'Unsupported Place update field: '
                . $path
            );
        }


        $fieldKey =
            (string) (
                $definition['key']
                ?? ''
            );


        $currentValue =
            llama_place_update_current_value(
                $db,
                $placeId,
                (string) $path,
                true
            );


        if (
            !llama_place_update_value_equal(
                $currentValue,
                $original[$path],
                $definition['shared']
                ?? null
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


        $currentIsUnknown =
            $fieldKey !== ''
            && isset(
                $currentPublishedUnknownLookup[
                    $fieldKey
                ]
            );


        $originalWasUnknown =
            $fieldKey !== ''
            && isset(
                $originalUnknownLookup[
                    $fieldKey
                ]
            );


        if (
            $currentIsUnknown
            !== $originalWasUnknown
        ) {
            throw new RuntimeException(
                'The answer state for "'
                . (
                    $definition['label']
                    ?? $path
                )
                . '" changed after this contribution was submitted. Review the latest Place data before approving.'
            );
        }
    }


    foreach ($proposed as $path => $value) {
        $field = $definitions[$path]['shared'];
        if (!in_array('place', (array) $field['report_scopes'], true) || !empty($field['derived'])) {
            throw new RuntimeException('This pending answer requires a Site target or is read-only. Return it for correction.');
        }
        $answer = llama_place_report_parse_answer($field,
            isset($newUnknownLookup[$field['key']]) ? llama_place_report_unknown_token() : $value);
        if (in_array($path, ['name','type'], true) && $answer['state'] !== 'answered') {
            throw new InvalidArgumentException('Place name and type cannot be cleared.');
        }
        $proposed[$path] = $answer['value'];
    }
    $finalCoordinates = llama_place_report_current_values($db, $placeId, true);
    foreach (['latitude','longitude'] as $coordinate) {
        if (array_key_exists($coordinate, $proposed)) { $finalCoordinates[$coordinate] = $proposed[$coordinate]; }
    }
    if (($finalCoordinates['latitude'] === null) !== ($finalCoordinates['longitude'] === null)) {
        throw new InvalidArgumentException('Latitude and longitude must both be present or both be blank.');
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


    $copyJournalStart =
        count(
            $copiedPhotoPaths
        );


    moderation_attach_place_photos_transactional(
        $db,
        $placeId,
        (int) $update['user_id'],
        $photos,
        '/uploads/place-updates/'
        . $updateId
        . '/',
        $copiedPhotoPaths
    );


    $reportPhotoPaths =
        array_slice(
            $copiedPhotoPaths,
            $copyJournalStart
        );


    $reportAnswers = [];


    foreach ($proposed as $path => $value) {
        $field =
            $definitions[$path]['shared'];

        $key =
            (string) $field['key'];

        $reportAnswers[$key] =
            isset($newUnknownLookup[$key])
                ? llama_place_report_unknown_token()
                : $value;
    }


    llama_scout_report_record_approved_update(
        $db,
        $updateId,
        $update,
        [
            'scope' =>
                'place',

            'source' =>
                'place',

            'id' =>
                $placeId,

            'label' =>
                (string) (
                    $update['place_name']
                    ?? ''
                ),
        ],
        $reportAnswers,
        $reviewedBy,
        $reviewNotes,
        $photos,
        $reportPhotoPaths
    );


    foreach (
        $proposed
        as $path => $value
    ) {
        llama_place_report_apply_published_field(
            $db,
            $placeId,
            (string) $path,
            $value
        );
    }


    llama_place_report_apply_published_answer_state(
        $db,
        $placeId,
        $proposed,
        array_keys(
            $newUnknownLookup
        )
    );


    // Confirm each approved Place observation reached the current
    // published projection before the moderation transaction commits.
    $publishedAnswers = llama_place_report_current_place_answers($db, $placeId);
    foreach ($reportAnswers as $key => $submittedValue) {
        $field = llama_place_report_fields()[$key] ?? null;
        $publishedAnswer = $publishedAnswers[$key] ?? null;
        if (!is_array($field) || !is_array($publishedAnswer) || empty($publishedAnswer['recorded'])) {
            throw new RuntimeException(
                'An approved Place update answer was not published. Approval was cancelled.'
            );
        }
        $expected = llama_place_report_submission_answer($field, $submittedValue);
        if (
            ($publishedAnswer['state'] ?? '') !== $expected['state']
            || (
                $expected['state'] === 'answered'
                && !llama_place_report_values_equal(
                    $publishedAnswer['value'] ?? null,
                    $expected['value'],
                    $field
                )
            )
        ) {
            throw new RuntimeException(
                'An approved Place update answer did not match the published value. Approval was cancelled.'
            );
        }
    }

    $finalCurrentValues =
        llama_place_update_current_values(
            $db,
            $placeId,
            true
        );


    $finalInput = llama_place_update_shared_form_values($finalCurrentValues,
        llama_place_report_published_answer_state($db, $placeId));

    $pointEstimate =
        llama_points_estimate_place_update(
            $db,
            $finalInput,
            $proposed,
            count($photos)
        );


    $points =
        (int) (
            $pointEstimate['estimated_points']
            ?? 0
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


    /*
     * Do not remove the contributor's update photo folder here.
     *
     * The caller still has moderation history and Admin audit work
     * to complete before the outer transaction is committed.
     * Source files are removed only after that commit succeeds.
     */


    return $contributionId;
}


/*
 * =========================================================
 * MODERATION HISTORY
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


function llama_place_update_display_value(string $path, mixed $value, bool $explicitUnknown = false): string
{
    require_once __DIR__ . '/place-report/review.php';
    return llama_place_report_review_value($path, $value, $explicitUnknown);
}


function llama_place_update_schema_icon(
    string $path
): string {
    $field =
        llama_place_report_field_by_storage(
            $path
        );


    if (!$field) {
        return 'edit';
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
            'current-location',

        'site_vehicle' =>
            'camper',

        'road_access' =>
            'road',

        'amenities' =>
            'picnic-table',

        'connectivity' =>
            'antenna-bars-5',

        'sensory' =>
            'at-brain',

        'environment_accessibility' =>
            'accessible',

        'rules' =>
            'calendar-event',

        'experience' =>
            'compass',

        default =>
            'edit',
    };
}


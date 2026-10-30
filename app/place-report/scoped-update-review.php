<?php

declare(strict_types=1);

require_once __DIR__ . '/report-targets.php';
require_once __DIR__ . '/scoped-answers.php';
require_once __DIR__ . '/target-model.php';
require_once dirname(__DIR__) . '/photo-staging.php';
require_once dirname(__DIR__) . '/points.php';

/**
 * Parse and validate one Area/Site correction payload against the current
 * published scoped answers.
 *
 * @return array{
 *     target:array,
 *     payload:array,
 *     original:array,
 *     visited:string
 * }
 */
function llama_scoped_update_build_payload(
    PDO $db,
    int $placeId,
    array $input
): array {
    $scope =
        trim(
            (string) (
                $input['report_target_scope']
                ?? ''
            )
        );

    $source =
        trim(
            (string) (
                $input['report_target_source']
                ?? ''
            )
        );

    $id =
        (int) (
            $input['report_target_id']
            ?? 0
        );

    $target =
        llama_report_verified_target(
            $db,
            $placeId,
            $scope,
            $source,
            $id
        );

    if ($scope === 'place') {
        throw new InvalidArgumentException(
            'Use the normal Place update form.'
        );
    }

    /*
     * A Scout can report several answers about one target in a single review.
     * Keep compatibility with the original single-answer editor while old
     * pending submissions still exist.
     */
    $submitted = [];

    if (
        !empty(
            $input['scoped_answers_json']
        )
    ) {
        $submitted =
            json_decode(
                (string) $input['scoped_answers_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($submitted)
            || !array_is_list($submitted)
        ) {
            throw new InvalidArgumentException(
                'Invalid Area or Site answers.'
            );
        }
    } elseif (
        isset(
            $input['scoped_field_key'],
            $input['scoped_field_value']
        )
    ) {
        $submitted = [[
            'key' =>
                $input['scoped_field_key'],

            'value' =>
                $input['scoped_field_value'],
        ]];
    }

    if (
        !$submitted
    ) {
        throw new InvalidArgumentException(
            'Answer at least one question in this report.'
        );
    }

    $currentModel =
        llama_place_report_current_answer_model(
            $db,
            $placeId,
            $target
        );

    $currentAnswers =
        (array) (
            $currentModel['answers']
            ?? []
        );

    /*
     * Direct current answers and effective current answers are reference
     * snapshots for this new report. They are not draft answers.
     */
    $before =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'scoped_override'
        );

    $effectiveBefore =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'effective'
        );

    $answers = [];
    $originalAnswers = [];
    $seen = [];

    foreach ($submitted as $answer) {
        if (!is_array($answer)) {
            throw new InvalidArgumentException(
                'Invalid Area or Site question.'
            );
        }

        $key =
            trim(
                (string) (
                    $answer['key']
                    ?? ''
                )
            );

        if (
            $key === ''
            || isset($seen[$key])
        ) {
            throw new InvalidArgumentException(
                $key === ''
                    ? 'Invalid Area or Site question.'
                    : 'Each question can only be answered once.'
            );
        }

        $seen[$key] =
            true;

        $field =
            llama_scoped_report_field(
                $key,
                $scope
            );

        $raw =
            $answer['value']
            ?? null;

        $answerValue =
            llama_place_report_submission_answer(
                $field,
                $raw
            );

        /*
         * Unanswered means this report contains no observation for the
         * question. Match the Entire Place path by omitting it rather than
         * converting it to No or treating it as a submitted correction.
         */
        if (!$answerValue['recorded']) {
            continue;
        }

        $value =
            $answerValue['scoped_value'];

        $recorded =
            array_key_exists(
                $key,
                $before
            );

        $answers[$key] =
            $value;

        $originalAnswers[$key] = [
            'recorded' =>
                $recorded,

            'value' =>
                $recorded
                    ? $before[$key]
                    : null,
            'effective_recorded' => array_key_exists($key, $effectiveBefore),
            'effective_value' => $effectiveBefore[$key] ?? null,
        ];
    }

    if (!$answers) {
        throw new InvalidArgumentException(
            'Answer at least one Area or Site question before submitting.'
        );
    }

    $visited = (string) (llama_place_report_metadata($input, 'visited_at') ?? '');

    return [
        'target' =>
            $target,

        'payload' => [
            'scope' =>
                $scope,

            'source' =>
                $source,

            'id' =>
                $id,

            'answers' =>
                $answers,

            'target_label' =>
                (string) (
                    $target['label']
                    ?? ''
                ),
        ],

        'original' => [
            'answers' =>
                $originalAnswers,
        ],

        'visited' =>
            $visited,
    ];
}

/**
 * A scoped correction is submitted as a normal moderated update and never
 * changes published Place data immediately.
 */
function llama_scoped_update_submit(
    int $userId,
    array $place,
    array $input
): int {
    $db =
        db();

    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    if (
        $userId < 1
        || $placeId < 1
        || !user_has_place_complete_access(
            $placeId,
            $userId
        )
        || !llama_contributor_can(
            $db,
            $userId,
            'submit_update'
        )
    ) {
        throw new RuntimeException(
            'You do not have permission to update this Place.'
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

    $built =
        llama_scoped_update_build_payload(
            $db,
            $placeId,
            $input
        );

    $payload =
        $built['payload'];

    $original =
        $built['original'];

    $visited =
        $built['visited'];

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
        count($submittedPhotos)
        > 5
    ) {
        throw new InvalidArgumentException(
            'Add no more than 5 evidence photos to an Area or Site update.'
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

    $updateId = 0;

    $db->beginTransaction();

    try {
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
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

        $stmt->execute([
            $placeId,
            $userId,
            'scoped-update',
            'pending',
            community_role_at_submission(
                $userId
            ),
            $visited !== ''
                ? $visited
                    . (
                        strlen($visited) === 10
                            ? ' 00:00:00'
                            : ''
                    )
                : null,
            json_encode(
                [
                    '__scoped_report__' =>
                        $payload,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
            ),
            json_encode(
                [
                    '__scoped_report__' =>
                        $original,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
            ),
            '[]',
            (string) (llama_place_report_metadata($input, 'contributor_notes') ?? ''),
        ]);

        $updateId =
            (int) $db->lastInsertId();

        $committedPhotos = [];

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

                'proposed' => [
                    '__scoped_report__' =>
                        $payload,
                ],

                'original' => [
                    '__scoped_report__' =>
                        $original,
                ],

                'unknown_fields' =>
                    [],

                'original_unknown_fields' =>
                    [],

                'photo_count' =>
                    count(
                        $committedPhotos
                    ),

                'visited_at' =>
                    $visited,

                'contributor_notes' =>
                    (string) (
                        $input['contributor_notes']
                        ?? ''
                    ),
            ]
        );

        $db->commit();

        return $updateId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($updateId > 0) {
            llama_photo_remove_tree(
                dirname(__DIR__, 2)
                . '/uploads/place-updates/'
                . $updateId
            );
        }

        throw $exception;
    }
}

/**
 * Resubmit an Area/Site correction after a moderator requested changes.
 *
 * The reporting target is locked to the original scoped submission. Original
 * answer state is refreshed from the current published target so approval
 * conflict detection compares against the state that existed at resubmission,
 * matching the normal Place-update revision workflow.
 */
function llama_scoped_update_resubmit(
    int $userId,
    array $place,
    int $updateId,
    array $input
): int {
    $db =
        db();

    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    if (
        $userId < 1
        || $placeId < 1
        || $updateId < 1
        || !user_has_place_complete_access(
            $placeId,
            $userId
        )
        || !llama_contributor_can(
            $db,
            $userId,
            'submit_update'
        )
    ) {
        throw new RuntimeException(
            'You do not have permission to revise this update.'
        );
    }

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
        || (string) (
            $before['update_type']
            ?? ''
        ) !== 'scoped-update'
    ) {
        throw new RuntimeException(
            'This Area or Site update is no longer available for resubmission.'
        );
    }

    $previousProposed =
        llama_place_update_decode_json(
            $before['proposed_changes']
            ?? '{}'
        );

    $previousPayload =
        $previousProposed[
            '__scoped_report__'
        ]
        ?? null;

    if (!is_array($previousPayload)) {
        throw new RuntimeException(
            'This Area or Site update has invalid revision data.'
        );
    }

    $previousScope =
        (string) (
            $previousPayload['scope']
            ?? ''
        );

    $previousSource =
        (string) (
            $previousPayload['source']
            ?? ''
        );

    $previousTargetId =
        (int) (
            $previousPayload['id']
            ?? 0
        );

    if (
        trim(
            (string) (
                $input['report_target_scope']
                ?? ''
            )
        ) !== $previousScope
        || trim(
            (string) (
                $input['report_target_source']
                ?? ''
            )
        ) !== $previousSource
        || (int) (
            $input['report_target_id']
            ?? 0
        ) !== $previousTargetId
    ) {
        throw new InvalidArgumentException(
            'A revision must keep the same Area or Site reporting target.'
        );
    }

    $built =
        llama_scoped_update_build_payload(
            $db,
            $placeId,
            $input
        );

    $payload =
        $built['payload'];

    $original =
        $built['original'];

    $visited =
        $built['visited'];

    $notes =
        (string) (llama_place_report_metadata($input, 'contributor_notes') ?? '');

    $reviewRequest =
        trim(
            (string) (
                $before['review_notes']
                ?? ''
            )
        );

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
        count($existingPhotos)
        + count($submittedPhotos)
        > 5
    ) {
        throw new InvalidArgumentException(
            'An Area or Site update can have no more than 5 evidence photos.'
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

    $newCommittedPhotos = [];

    $db->beginTransaction();

    try {
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
                   AND status = "needs-changes"
                   AND update_type = "scoped-update"'
            );

        $stmt->execute([
            community_role_at_submission(
                $userId
            ),
            $visited !== ''
                ? $visited
                    . (
                        strlen($visited) === 10
                            ? ' 00:00:00'
                            : ''
                    )
                : null,
            json_encode(
                [
                    '__scoped_report__' =>
                        $payload,
                ],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
            json_encode(
                [
                    '__scoped_report__' =>
                        $original,
                ],
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

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The Area or Site update changed before it could be resubmitted.'
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

                'proposed' => [
                    '__scoped_report__' =>
                        $payload,
                ],

                'original' => [
                    '__scoped_report__' =>
                        $original,
                ],

                'unknown_fields' =>
                    [],

                'original_unknown_fields' =>
                    [],

                'photo_count_before' =>
                    count(
                        $existingPhotos
                    ),

                'photo_count_after' =>
                    count(
                        $photos
                    ),

                'visited_at' =>
                    $visited,

                'contributor_notes' =>
                    $notes,
            ]
        );

        $db->commit();

        return $updateId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
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
                dirname(__DIR__, 2)
                . $src;

            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        throw $exception;
    }
}

/**
 * Calculate scoped completion and update points against the target's effective
 * Place -> Area -> Site answer state.
 *
 * Inherited answers participate in applicability/completion, but only the
 * direct answers in this contribution count as changed items for points.
 */
function llama_scoped_update_point_estimate(
    PDO $db,
    int $placeId,
    string $placeSlug,
    array $payload
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Invalid Place ID.'
        );
    }

    $scope =
        (string) (
            $payload['scope']
            ?? ''
        );

    $source =
        (string) (
            $payload['source']
            ?? ''
        );

    $targetId =
        (int) (
            $payload['id']
            ?? 0
        );

    $target =
        llama_report_verified_target(
            $db,
            $placeId,
            $scope,
            $source,
            $targetId
        );

    $answers =
        (array) (
            $payload['answers']
            ?? []
        );

    if (
        !$answers
        && isset(
            $payload['field_key']
        )
    ) {
        $answers = [
            (string) $payload['field_key'] =>
                $payload['value']
                ?? null,
        ];
    }

    if (!$answers) {
        throw new RuntimeException(
            'Invalid scoped update answers.'
        );
    }

    $resolved = llama_place_report_target_model($db, $placeId, $target);
    $effectiveInput = [];
    foreach ($resolved['values'] as $key => $value) {
        $field = llama_place_report_fields()[$key] ?? null;
        if ($field) { $effectiveInput[$key] = llama_place_report_answer_form_value($field, $value); }
    }

    foreach (
        $answers
        as $fieldKey => $value
    ) {
        $fieldKey =
            (string) $fieldKey;

        llama_scoped_report_field(
            $fieldKey,
            $scope
        );

        $effectiveInput[$fieldKey] = llama_place_report_answer_form_value(
            llama_place_report_fields()[$fieldKey], $value);
    }

    return
        llama_points_estimate_scoped_update(
            $db,
            $effectiveInput,
            $scope,
            array_keys(
                $answers
            )
        );
}


/** Called inside the moderator's existing approval transaction. */
function llama_scoped_update_approve(
    PDO $db,
    array $update,
    int $updateId,
    int $reviewedBy,
    string $notes,
    array &$copiedPhotoPaths
): int {
    $payload =
        $update['proposed'][
            '__scoped_report__'
        ]
        ?? null;

    $before =
        $update['original'][
            '__scoped_report__'
        ]
        ?? null;

    if (
        !is_array($payload)
        || !is_array($before)
    ) {
        throw new RuntimeException(
            'Invalid scoped update payload.'
        );
    }

    $placeId =
        (int) $update['place_id'];

    $scope =
        (string) (
            $payload['scope']
            ?? ''
        );

    $source =
        (string) (
            $payload['source']
            ?? ''
        );

    $targetId =
        (int) (
            $payload['id']
            ?? 0
        );

    llama_report_verified_target(
        $db,
        $placeId,
        $scope,
        $source,
        $targetId
    );

    $answers =
        (array) (
            $payload['answers']
            ?? []
        );

    $oldAnswers =
        (array) (
            $before['answers']
            ?? []
        );

    if (
        !$answers
        && isset(
            $payload['field_key']
        )
    ) {
        /*
         * Pending submissions from the original single-answer editor.
         */
        $answers = [
            (string) $payload['field_key'] =>
                $payload['value']
                ?? null,
        ];

        $oldAnswers = [
            (string) $payload['field_key'] =>
                $before,
        ];
    }

    if (
        !$answers
    ) {
        throw new RuntimeException(
            'Invalid scoped update answers.'
        );
    }

    if (!$db->inTransaction()) { throw new RuntimeException('Scoped approval requires an active transaction.'); }
    llama_scoped_report_assert_owner($db, $placeId, $scope, $targetId, $source);
    $target = llama_report_verified_target($db, $placeId, $scope, $source, $targetId);

    $currentModel =
        llama_place_report_current_answer_model(
            $db,
            $placeId,
            $target
        );

    $currentAnswers =
        (array) (
            $currentModel['answers']
            ?? []
        );

    $effectiveCurrent =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'effective'
        );

    $current =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'scoped_override'
        );

    /*
     * Validate every original reference value before writing any approved
     * answer. Submission itself never edits the current target.
     */
    foreach ($answers as $key => $value) {
        $field = llama_scoped_report_field((string) $key, $scope);
        $answers[$key] = llama_place_report_scoped_value($field, $value);

        $prior =
            $oldAnswers[$key]
            ?? null;

        if (!is_array($prior)) {
            throw new RuntimeException(
                'Missing original Area or Site answer state.'
            );
        }

        if (array_key_exists('effective_recorded', $prior)
            && ((bool) $prior['effective_recorded'] !== array_key_exists($key, $effectiveCurrent)
                || !llama_place_report_values_equal($prior['effective_value'] ?? null,
                    $effectiveCurrent[$key] ?? null, $field))) {
            throw new RuntimeException('The inherited or imported answer changed since submission. Review the latest information.');
        }
        $recorded =
            array_key_exists(
                $key,
                $current
            );

        if (
            $recorded
            !== (bool) (
                $prior['recorded']
                ?? false
            )
            || (
                $recorded
                && !llama_place_report_values_equal($current[$key], $prior['value'] ?? null,
                    llama_place_report_fields()[$key] ?? null)
            )
        ) {
            throw new RuntimeException(
                'An Area or Site answer changed since submission. Review the latest information before approving.'
            );
        }
    }

    $pointEstimate =
        llama_scoped_update_point_estimate(
            $db,
            $placeId,
            (string) (
                $update['place_slug']
                ?? ''
            ),
            $payload
        );

    $points =
        (int) (
            $pointEstimate[
                'estimated_points'
            ]
            ?? 0
        );

    $photos =
        is_array(
            $update['photo_list']
            ?? null
        )
            ? $update['photo_list']
            : [];

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

    llama_scout_report_record_approved_update(
        $db,
        $updateId,
        $update,
        [
            'scope' =>
                $scope,

            'source' =>
                $source,

            'id' =>
                $targetId,

            'label' =>
                (string) (
                    $payload['target_label']
                    ?? $target['label']
                    ?? ''
                ),
        ],
        $answers,
        $reviewedBy,
        $notes,
        $photos,
        $reportPhotoPaths
    );

    foreach ($answers as $key => $value) {
        llama_scoped_report_save(
            $db,
            $placeId,
            $scope,
            $targetId,
            $source,
            (string) $key,
            $value
        );
    }

    /*
     * Both the immutable report and published scoped answers are part of
     * the moderator's transaction. Confirm that every approved answer is
     * actually present before recording the approved contribution.
     */
    $publishedAnswers = llama_scoped_report_load(
        $db,
        $placeId,
        $scope,
        $targetId,
        $source
    );
    foreach ($answers as $key => $value) {
        if (
            !array_key_exists($key, $publishedAnswers)
            || llama_scout_report_json($publishedAnswers[$key])
                !== llama_scout_report_json($value)
        ) {
            throw new RuntimeException(
                'An approved Scout Report answer was not published. Approval was cancelled.'
            );
        }
    }

    $contributionId =
        moderation_insert_contribution(
            $db,
            $placeId,
            (int) $update['user_id'],
            null,
            'scoped-update',
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
            array_map(
                static fn ($key) =>
                    $scope
                    . ':'
                    . $source
                    . ':'
                    . $targetId
                    . ':'
                    . $key,
                array_keys(
                    $answers
                )
            ),
            $notes !== ''
                ? $notes
                : null
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
        $notes !== ''
            ? $notes
            : null,
        $contributionId,
        max(
            0,
            $points
        ),
        $updateId,
    ]);

    return $contributionId;
}

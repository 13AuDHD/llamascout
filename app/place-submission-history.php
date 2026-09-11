<?php

declare(strict_types=1);

/*
 * =========================================================
 * LLAMA SCOUT
 * NEW PLACE SUBMISSION HISTORY
 *
 * Tracks moderation requests and contributor resubmissions.
 * No schema changes happen at runtime.
 * =========================================================
 */

function llama_place_submission_history_decode(
    mixed $value
): array {
    if (is_array($value)) {
        return $value;
    }

    if (
        !is_string($value)
        || trim($value) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $value,
            true
        );

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_place_submission_history(
    PDO $db,
    int $submissionId
): array {
    if ($submissionId < 1) {
        return [];
    }

    $stmt =
        $db->prepare(
            'SELECT revision_history
             FROM place_submissions
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $submissionId,
    ]);

    return llama_place_submission_history_decode(
        $stmt->fetchColumn()
    );
}

function llama_place_submission_history_append(
    PDO $db,
    int $submissionId,
    array $event
): void {
    if ($submissionId < 1) {
        throw new InvalidArgumentException(
            'Invalid Place submission.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT revision_history
             FROM place_submissions
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

    $stmt->execute([
        $submissionId,
    ]);

    $raw =
        $stmt->fetchColumn();

    if ($raw === false) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    $history =
        llama_place_submission_history_decode(
            $raw
        );

    $event['at'] =
        $event['at']
        ?? gmdate('Y-m-d H:i:s');

    $history[] =
        $event;

    $update =
        $db->prepare(
            'UPDATE place_submissions
             SET revision_history = ?
             WHERE id = ?'
        );

    $update->execute([
        json_encode(
            $history,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $submissionId,
    ]);
}

function llama_place_submission_value_equal(
    mixed $left,
    mixed $right
): bool {
    if (
        is_bool($left)
        || is_bool($right)
    ) {
        return (bool) $left
            === (bool) $right;
    }

    if (
        $left === null
        || $right === null
    ) {
        return $left === $right;
    }

    if (
        is_float($left)
        || is_float($right)
        || is_int($left)
        || is_int($right)
    ) {
        return (string) $left
            === (string) $right;
    }

    return (string) $left
        === (string) $right;
}

function llama_place_submission_field_snapshot(
    array $data,
    string $fieldKey
): array {
    $fields =
        llama_place_report_fields();

    $field =
        $fields[$fieldKey]
        ?? null;

    if (!$field) {
        return [
            'state' => 'unanswered',
            'value' => null,
        ];
    }

    $state =
        llama_place_report_answer_state(
            $data,
            $fieldKey
        );

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    return [
        'state' => $state,
        'value' => $value,
    ];
}

function llama_place_submission_diff(
    array $before,
    array $after
): array {
    $changes = [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $beforeSnapshot =
            llama_place_submission_field_snapshot(
                $before,
                $fieldKey
            );

        $afterSnapshot =
            llama_place_submission_field_snapshot(
                $after,
                $fieldKey
            );

        if (
            $beforeSnapshot['state']
                === $afterSnapshot['state']
            && llama_place_submission_value_equal(
                $beforeSnapshot['value'],
                $afterSnapshot['value']
            )
        ) {
            continue;
        }

        $changes[] = [
            'field' => $fieldKey,
            'storage' =>
                (string) $field['storage'],
            'before_state' =>
                $beforeSnapshot['state'],
            'before_value' =>
                $beforeSnapshot['value'],
            'after_state' =>
                $afterSnapshot['state'],
            'after_value' =>
                $afterSnapshot['value'],
        ];
    }

    return $changes;
}

function llama_place_submission_photo_paths(
    array $data
): array {
    $photos =
        is_array(
            $data['photos']
            ?? null
        )
            ? $data['photos']
            : [];

    $paths = [];

    foreach ($photos as $photo) {
        $path =
            llama_place_report_photo_path(
                $photo
            );

        if ($path !== '') {
            $paths[] = $path;
        }
    }

    return array_values(
        array_unique(
            $paths
        )
    );
}

function llama_place_submission_photo_diff(
    array $before,
    array $after
): array {
    $beforePaths =
        llama_place_submission_photo_paths(
            $before
        );

    $afterPaths =
        llama_place_submission_photo_paths(
            $after
        );

    return [
        'before_count' =>
            count($beforePaths),
        'after_count' =>
            count($afterPaths),
        'added' =>
            array_values(
                array_diff(
                    $afterPaths,
                    $beforePaths
                )
            ),
        'removed' =>
            array_values(
                array_diff(
                    $beforePaths,
                    $afterPaths
                )
            ),
    ];
}

function llama_place_submission_record_initial(
    PDO $db,
    int $submissionId,
    array $data
): void {
    llama_place_submission_history_append(
        $db,
        $submissionId,
        [
            'type' => 'submitted',
            'by' => 'contributor',
            'answered' =>
                count(
                    array_filter(
                        array_keys(
                            llama_place_report_fields()
                        ),
                        static fn (
                            string $fieldKey
                        ): bool =>
                            llama_place_report_answer_state(
                                $data,
                                $fieldKey
                            )
                            !== 'unanswered'
                    )
                ),
            'photo_count' =>
                count(
                    llama_place_submission_photo_paths(
                        $data
                    )
                ),
        ]
    );
}

function llama_place_submission_record_review(
    PDO $db,
    int $submissionId,
    int $moderatorId,
    string $type,
    string $notes
): void {
    $allowed = [
        'changes-requested',
        'rejected',
        'approved',
    ];

    if (
        !in_array(
            $type,
            $allowed,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid Place submission history event.'
        );
    }

    llama_place_submission_history_append(
        $db,
        $submissionId,
        [
            'type' => $type,
            'by' => 'moderator',
            'moderator_id' =>
                $moderatorId,
            'review_notes' =>
                $notes !== ''
                    ? $notes
                    : null,
        ]
    );
}

function llama_place_submission_record_resubmission(
    PDO $db,
    int $submissionId,
    array $before,
    array $after,
    string $requestedChanges = ''
): void {
    llama_place_submission_history_append(
        $db,
        $submissionId,
        [
            'type' => 'resubmitted',
            'by' => 'contributor',
            'requested_changes' =>
                trim($requestedChanges) !== ''
                    ? trim($requestedChanges)
                    : null,
            'changes' =>
                llama_place_submission_diff(
                    $before,
                    $after
                ),
            'photos' =>
                llama_place_submission_photo_diff(
                    $before,
                    $after
                ),
        ]
    );
}

function llama_place_submission_display_value(
    string $fieldKey,
    string $state,
    mixed $value
): string {
    if ($state === 'unanswered') {
        return 'Not provided';
    }

    if ($state === 'unknown') {
        return 'Unknown';
    }

    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return $value === null
            ? 'Not provided'
            : (string) $value;
    }

    $type =
        (string) (
            $field['type']
            ?? ''
        );

    if ($type === 'tri') {
        return $value
            ? 'Yes'
            : 'No';
    }

    if ($type === 'rating') {
        return (int) $value
            . '/5';
    }

    if ($type === 'checkbox') {
        return $value
            ? 'Yes'
            : 'No';
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

    if (is_bool($value)) {
        return $value
            ? 'Yes'
            : 'No';
    }

    if ($value === null || $value === '') {
        return 'Not provided';
    }

    return (string) $value;
}

function llama_place_submission_delete_unpublished(
    PDO $db,
    int $submissionId
): array {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Deleting a Place submission requires an active database transaction.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_submissions
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

    $stmt->execute([
        $submissionId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    if (
        !empty($row['place_id'])
        || (string) ($row['status'] ?? '')
            === 'approved'
    ) {
        throw new RuntimeException(
            'An approved Place submission cannot be deleted here.'
        );
    }

    $delete =
        $db->prepare(
            'DELETE FROM place_submissions
             WHERE id = ?
               AND place_id IS NULL
               AND status <> ?'
        );

    $delete->execute([
        $submissionId,
        'approved',
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'The Place submission changed before it could be deleted.'
        );
    }

    return $row;
}

function llama_place_submission_remove_files(
    int $submissionId
): void {
    if ($submissionId < 1) {
        return;
    }

    $path =
        dirname(__DIR__)
        . '/uploads/place-submissions/'
        . $submissionId;

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

    if (
        function_exists(
            'llama_place_report_delete_tree'
        )
    ) {
        llama_place_report_delete_tree(
            $path
        );
    }
}

<?php

declare(strict_types=1);

function llama_place_submission_history_decode(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function llama_place_submission_history(PDO $db, int $submissionId): array
{
    $stmt = $db->prepare(
        'SELECT revision_history
         FROM place_submissions
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$submissionId]);

    return llama_place_submission_history_decode(
        $stmt->fetchColumn()
    );
}

function llama_place_submission_history_append(
    PDO $db,
    int $submissionId,
    array $event
): void {
    $stmt = $db->prepare(
        'SELECT revision_history
         FROM place_submissions
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([$submissionId]);
    $raw = $stmt->fetchColumn();

    if ($raw === false) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    $history = llama_place_submission_history_decode($raw);

    $event['at'] = $event['at']
        ?? gmdate('Y-m-d H:i:s');

    $history[] = $event;

    $update = $db->prepare(
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

function llama_place_submission_snapshot(array $data): array
{
    return [
        'data' => $data,
        'photos' => llama_place_submission_photo_paths($data),
    ];
}

function llama_place_submission_photo_paths(array $data): array
{
    $photos = is_array($data['photos'] ?? null)
        ? $data['photos']
        : [];

    $paths = [];

    foreach ($photos as $photo) {
        $path = llama_place_report_photo_path($photo);

        if ($path !== '') {
            $paths[] = $path;
        }
    }

    return array_values(array_unique($paths));
}

function llama_place_submission_value_equal(mixed $a, mixed $b): bool
{
    if (is_bool($a) || is_bool($b)) {
        return (bool) $a === (bool) $b;
    }

    if ($a === null || $b === null) {
        return $a === $b;
    }

    return (string) $a === (string) $b;
}

function llama_place_submission_diff(
    array $before,
    array $after
): array {
    $changes = [];

    foreach (llama_place_report_fields() as $fieldKey => $field) {
        $beforeState = llama_place_report_answer_state(
            $before,
            $fieldKey
        );

        $afterState = llama_place_report_answer_state(
            $after,
            $fieldKey
        );

        $beforeValue = llama_place_report_get_path(
            $before,
            (string) $field['storage']
        );

        $afterValue = llama_place_report_get_path(
            $after,
            (string) $field['storage']
        );

        if (
            $beforeState === $afterState
            && llama_place_submission_value_equal(
                $beforeValue,
                $afterValue
            )
        ) {
            continue;
        }

        $changes[] = [
            'field' => $fieldKey,
            'before_state' => $beforeState,
            'before_value' => $beforeValue,
            'after_state' => $afterState,
            'after_value' => $afterValue,
        ];
    }

    return $changes;
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

    $field = llama_place_report_fields()[$fieldKey] ?? null;

    if (!$field) {
        return $value === null
            ? 'Not provided'
            : (string) $value;
    }

    $type = (string) ($field['type'] ?? '');

    if ($type === 'tri') {
        return $value ? 'Yes' : 'No';
    }

    if ($type === 'rating') {
        return (int) $value . '/5';
    }

    if ($type === 'checkbox') {
        return $value ? 'Yes' : 'No';
    }

    if ($type === 'select') {
        $options = (array) ($field['options'] ?? []);

        if (array_key_exists((string) $value, $options)) {
            return (string) $options[(string) $value];
        }
    }

    if (($field['format'] ?? '') === 'currency') {
        return '$' . number_format((float) $value, 2);
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    return ($value === null || $value === '')
        ? 'Not provided'
        : (string) $value;
}

function llama_place_submission_record_change_request(
    PDO $db,
    int $submissionId,
    int $moderatorId,
    string $notes,
    array $data
): void {
    llama_place_submission_history_append(
        $db,
        $submissionId,
        [
            'type' => 'changes-requested',
            'by' => 'moderator',
            'moderator_id' => $moderatorId,
            'review_notes' => $notes,
            'snapshot' => llama_place_submission_snapshot($data),
        ]
    );
}

function llama_place_submission_record_terminal_review(
    PDO $db,
    int $submissionId,
    int $moderatorId,
    string $type,
    string $notes
): void {
    llama_place_submission_history_append(
        $db,
        $submissionId,
        [
            'type' => $type,
            'by' => 'moderator',
            'moderator_id' => $moderatorId,
            'review_notes' => $notes !== ''
                ? $notes
                : null,
        ]
    );
}

function llama_place_submission_capture_resubmission_if_needed(
    PDO $db,
    int $submissionId,
    array $item
): void {
    if ((string) ($item['status'] ?? '') !== 'pending') {
        return;
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT revision_history, submission_data
             FROM place_submissions
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([$submissionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $db->rollBack();
            return;
        }

        $history = llama_place_submission_history_decode(
            $row['revision_history'] ?? null
        );

        $last = $history
            ? $history[array_key_last($history)]
            : null;

        if (
            !is_array($last)
            || (string) ($last['type'] ?? '') !== 'changes-requested'
            || !is_array($last['snapshot']['data'] ?? null)
        ) {
            $db->rollBack();
            return;
        }

        $before = $last['snapshot']['data'];
        $beforePhotos = is_array($last['snapshot']['photos'] ?? null)
            ? $last['snapshot']['photos']
            : llama_place_submission_photo_paths($before);

        $after = json_decode(
            (string) ($row['submission_data'] ?? '{}'),
            true
        );

        if (!is_array($after)) {
            $after = [];
        }

        $afterPhotos = llama_place_submission_photo_paths($after);

        llama_place_submission_history_append(
            $db,
            $submissionId,
            [
                'type' => 'resubmitted',
                'by' => 'contributor',
                'requested_changes' => $last['review_notes'] ?? null,
                'changes' => llama_place_submission_diff(
                    $before,
                    $after
                ),
                'photos' => [
                    'before_count' => count($beforePhotos),
                    'after_count' => count($afterPhotos),
                    'added' => array_values(
                        array_diff($afterPhotos, $beforePhotos)
                    ),
                    'removed' => array_values(
                        array_diff($beforePhotos, $afterPhotos)
                    ),
                ],
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function llama_place_submission_delete_unpublished(
    PDO $db,
    int $submissionId
): array {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Deleting a Place submission requires an active transaction.'
        );
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM place_submissions
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([$submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    if (
        !empty($row['place_id'])
        || (string) ($row['status'] ?? '') === 'approved'
    ) {
        throw new RuntimeException(
            'An approved Place submission cannot be deleted here.'
        );
    }

    $delete = $db->prepare(
        'DELETE FROM place_submissions
         WHERE id = ?
           AND place_id IS NULL
           AND status <> "approved"'
    );

    $delete->execute([$submissionId]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'The Place submission changed before it could be deleted.'
        );
    }

    return $row;
}

function llama_place_submission_remove_files(int $submissionId): void
{
    $path = dirname(__DIR__)
        . '/uploads/place-submissions/'
        . $submissionId;

    if (function_exists('moderation_remove_tree')) {
        moderation_remove_tree($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . '/' . $item;

        if (is_dir($target)) {
            continue;
        }

        @unlink($target);
    }

    @rmdir($path);
}

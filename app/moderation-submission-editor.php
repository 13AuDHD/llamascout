<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';

/*
 * Moderator editing uses the exact same Place Report parser as
 * contributor entry. This file owns only moderation-specific
 * persistence and audit-diff support.
 */

function moderation_submission_editor_flatten(
    array $data,
    string $prefix = ''
): array {
    $flat = [];

    foreach ($data as $key => $value) {
        if (
            $key === 'photos'
            || $key === '_answer_state'
        ) {
            continue;
        }

        $path =
            $prefix === ''
                ? (string) $key
                : $prefix . '.' . $key;

        if (is_array($value)) {
            $flat +=
                moderation_submission_editor_flatten(
                    $value,
                    $path
                );
        } else {
            $flat[$path] = $value;
        }
    }

    return $flat;
}

function moderation_save_submission_edits(
    PDO $db,
    int $submissionId,
    int $adminId,
    array $input,
    array $removePhotoPaths,
    string $photoToken,
    array $submittedPhotos
): array {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Moderator submission editing requires an active database transaction.'
        );
    }

    $submission =
        moderation_submission(
            $db,
            $submissionId,
            true
        );

    if (!$submission) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    if (
        !in_array(
            (string) $submission['status'],
            [
                'pending',
                'needs-changes',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Only Pending or Needs Changes submissions can be edited before approval.'
        );
    }

    $existingData =
        is_array(
            $submission['data']
            ?? null
        )
            ? $submission['data']
            : [];

    $before =
        moderation_submission_editor_flatten(
            $existingData
        );

    $beforeUnknown =
        llama_place_report_unknown_fields(
            $existingData
        );

    $data =
        llama_place_report_build_data(
            $input,
            $existingData
        );

    $existingPhotos =
        is_array(
            $existingData['photos']
            ?? null
        )
            ? $existingData['photos']
            : [];

    $removeLookup = [];

    foreach ($removePhotoPaths as $path) {
        $path =
            trim(
                (string) $path
            );

        if (
            $path !== ''
            && str_starts_with(
                $path,
                '/uploads/place-submissions/'
                . $submissionId
                . '/'
            )
        ) {
            $removeLookup[$path] =
                true;
        }
    }

    $keptPhotos = [];

    foreach ($existingPhotos as $photo) {
        $path =
            llama_place_report_photo_path(
                $photo
            );

        if (
            $path !== ''
            && isset(
                $removeLookup[$path]
            )
        ) {
            continue;
        }

        if (is_array($photo)) {
            $keptPhotos[] =
                $photo;
        }
    }

    $addedPhotos = [];

    if (
        $photoToken !== ''
        && $submittedPhotos
    ) {
        $addedPhotos =
            llama_place_report_normalize_committed_photos(
                llama_photo_commit_stage(
                    'add-place',
                    $adminId,
                    $photoToken,
                    $submittedPhotos,
                    '/uploads/place-submissions/'
                    . $submissionId
                )
            );
    }

    $data['photos'] =
        array_values(
            array_merge(
                $keptPhotos,
                $addedPhotos
            )
        );

    $stmt =
        $db->prepare(
            'UPDATE place_submissions
             SET
                place_name = ?,
                submission_data = ?
             WHERE id = ?
               AND status IN ("pending","needs-changes")'
        );

    $stmt->execute([
        (string) $data['name'],
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $submissionId,
    ]);

    $after =
        moderation_submission_editor_flatten(
            $data
        );

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

        if ($old === $new) {
            continue;
        }

        $changes[] = [
            'field' =>
                $path,
            'before' =>
                $old,
            'after' =>
                $new,
        ];
    }

    $afterUnknown =
        llama_place_report_unknown_fields(
            $data
        );

    if ($beforeUnknown !== $afterUnknown) {
        $changes[] = [
            'field' =>
                '_answer_state',
            'before' =>
                $beforeUnknown,
            'after' =>
                $afterUnknown,
        ];
    }

    return [
        'field_changes' =>
            $changes,
        'removed_photos' =>
            array_keys(
                $removeLookup
            ),
        'added_photo_count' =>
            count(
                $addedPhotos
            ),
    ];
}

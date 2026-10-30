<?php

declare(strict_types=1);

/** Canonicalize nested data for reproducible JSON snapshots and hashes. */
function llama_scout_report_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(
            'llama_scout_report_canonicalize',
            $value
        );
    }

    ksort($value, SORT_STRING);

    foreach ($value as $key => $item) {
        $value[$key] =
            llama_scout_report_canonicalize(
                $item
            );
    }

    return $value;
}

function llama_scout_report_json(mixed $value): string
{
    return json_encode(
        llama_scout_report_canonicalize($value),
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
    );
}

/** Store the exact canonical question registry used to validate a report. */
function llama_scout_report_schema_version(PDO $db): string
{
    $definitionJson =
        llama_scout_report_json([
            'fields' =>
                llama_place_report_fields(),
        ]);

    $schemaVersion =
        hash(
            'sha256',
            $definitionJson
        );

    $insert =
        $db->prepare(
            'INSERT IGNORE INTO scout_report_schemas
                (schema_version, definition_json, created_at)
             VALUES (?, ?, CURRENT_TIMESTAMP)'
        );

    $insert->execute([
        $schemaVersion,
        $definitionJson,
    ]);

    $check =
        $db->prepare(
            'SELECT definition_json
             FROM scout_report_schemas
             WHERE schema_version = ?
             LIMIT 1'
        );

    $check->execute([
        $schemaVersion
    ]);

    $stored =
        $check->fetchColumn();

    if (
        !is_string($stored)
        || !hash_equals(
            hash('sha256', $stored),
            $schemaVersion
        )
    ) {
        throw new RuntimeException(
            'The immutable Scout Report schema snapshot could not be verified.'
        );
    }

    return $schemaVersion;
}

/**
 * Validate and normalize only the answers intentionally present in one report.
 * Unanswered is never accepted into the immutable answer ledger.
 *
 * @return array<string,array{state:string,value:mixed}>
 */
function llama_scout_report_normalize_answers(
    string $scope,
    array $answers
): array {
    if (!in_array($scope, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException(
            'Invalid immutable Scout Report target scope.'
        );
    }

    $normalized = [];
    $fields =
        llama_place_report_fields();

    foreach ($answers as $fieldKey => $raw) {
        $fieldKey =
            trim((string) $fieldKey);

        $field =
            $fields[$fieldKey]
            ?? null;

        if (
            $fieldKey === ''
            || !is_array($field)
            || !in_array(
                $scope,
                (array) ($field['report_scopes'] ?? []),
                true
            )
            || !empty($field['derived'])
        ) {
            throw new InvalidArgumentException(
                'An immutable Scout Report contains an invalid question.'
            );
        }

        $answer =
            llama_place_report_submission_answer(
                $field,
                $raw
            );

        if (!$answer['recorded']) {
            throw new InvalidArgumentException(
                'Unanswered questions cannot be written to immutable Scout Report history.'
            );
        }

        $normalized[$fieldKey] = [
            'state' =>
                (string) $answer['state'],

            'value' =>
                $answer['value'],
        ];
    }

    ksort($normalized, SORT_STRING);

    return $normalized;
}

/**
 * Snapshot ordered report evidence after it has been copied into permanent
 * Place storage. The historical row points at the existing file instead of
 * creating a second report-only copy.
 *
 * @return array<int,array<string,mixed>>
 */
function llama_scout_report_photo_snapshots(
    array $submittedPhotos,
    array $permanentPaths
): array {
    if (count($submittedPhotos) !== count($permanentPaths)) {
        throw new RuntimeException(
            'Immutable Scout Report photo evidence is incomplete.'
        );
    }

    $root =
        dirname(__DIR__, 2);

    $snapshots = [];

    foreach ($permanentPaths as $index => $path) {
        $path =
            '/'
            . ltrim(
                trim((string) $path),
                '/'
            );

        if (
            $path === '/'
            || str_contains($path, '..')
            || !str_starts_with(
                $path,
                '/uploads/places/'
            )
        ) {
            throw new RuntimeException(
                'Immutable Scout Report photo evidence has an invalid permanent path.'
            );
        }

        $absolute =
            $root
            . $path;

        if (!is_file($absolute)) {
            throw new RuntimeException(
                'Immutable Scout Report photo evidence is missing from permanent storage.'
            );
        }

        $fileHash =
            hash_file(
                'sha256',
                $absolute
            );

        if (!is_string($fileHash)) {
            throw new RuntimeException(
                'Immutable Scout Report photo evidence could not be hashed.'
            );
        }

        $snapshots[] = [
            'photo_path' =>
                $path,

            'photo_path_sha256' =>
                hash('sha256', $path),

            'sort_order' =>
                (int) $index,

            'snapshot' => [
                'file_sha256' =>
                    $fileHash,

                'permanent_path' =>
                    $path,

                'submitted' =>
                    is_array($submittedPhotos[$index] ?? null)
                        ? $submittedPhotos[$index]
                        : [],
            ],
        ];
    }

    return $snapshots;
}

/**
 * Insert one approved Update This Place report and its evidence atomically.
 * The existing current Place/Area/Site tables remain compatibility projections
 * written later in the same moderation transaction.
 */
function llama_scout_report_record_approved_update(
    PDO $db,
    int $updateId,
    array $update,
    array $target,
    array $answers,
    int $reviewedBy,
    string $reviewNotes,
    array $submittedPhotos,
    array $permanentPhotoPaths
): int {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Immutable Scout Report writes require an active moderation transaction.'
        );
    }

    $placeId =
        (int) ($update['place_id'] ?? 0);

    $contributorId =
        (int) ($update['user_id'] ?? 0);

    $scope =
        trim((string) ($target['scope'] ?? ''));

    $source =
        trim((string) ($target['source'] ?? ''));

    $targetId =
        (int) ($target['id'] ?? 0);

    $targetLabel =
        trim((string) ($target['label'] ?? ''));

    $roleAtSubmission =
        trim(
            (string) (
                $update['role_at_submission']
                ?? 'user'
            )
        ) ?: 'user';

    if (
        $updateId < 1
        || $placeId < 1
        || $contributorId < 1
        || !in_array($scope, ['place', 'area', 'site'], true)
        || $source === ''
        || $targetId < 1
        || $reviewedBy < 1
    ) {
        throw new InvalidArgumentException(
            'Invalid approved Scout Report identity.'
        );
    }

    $submittedAt =
        trim((string) ($update['submitted_at'] ?? ''));

    if ($submittedAt === '') {
        throw new RuntimeException(
            'The approved Scout Report is missing its submission time.'
        );
    }

    $visitDate =
        trim((string) ($update['visited_at'] ?? ''));

    $visitDate =
        $visitDate !== ''
            ? substr($visitDate, 0, 10)
            : null;

    $normalizedAnswers =
        llama_scout_report_normalize_answers(
            $scope,
            $answers
        );

    $photoSnapshots =
        llama_scout_report_photo_snapshots(
            $submittedPhotos,
            $permanentPhotoPaths
        );

    if (
        !$normalizedAnswers
        && !$photoSnapshots
    ) {
        throw new InvalidArgumentException(
            'An immutable Scout Report requires an answer or photo.'
        );
    }

    $schemaVersion =
        llama_scout_report_schema_version(
            $db
        );

    $logicalReportKey =
        'place-update:'
        . $updateId;

    $submissionBundleKey =
        $logicalReportKey;

    $clientSubmissionKey =
        'web:'
        . $logicalReportKey;

    $content = [
        'answers' =>
            $normalizedAnswers,

        'contributor_id' =>
            $contributorId,

        'photos' =>
            $photoSnapshots,

        'place_id' =>
            $placeId,

        'role_at_submission' =>
            $roleAtSubmission,

        'schema_version' =>
            $schemaVersion,

        'submitted_at' =>
            $submittedAt,

        'target' => [
            'id' =>
                $targetId,

            'label' =>
                $targetLabel,

            'scope' =>
                $scope,

            'source' =>
                $source,
        ],

        'update_id' =>
            $updateId,

        'visit_date' =>
            $visitDate,
    ];

    $contentHash =
        hash(
            'sha256',
            llama_scout_report_json($content)
        );

    $existing =
        $db->prepare(
            'SELECT id, content_sha256
             FROM scout_reports
             WHERE contributor_id = ?
               AND client_submission_key = ?
             LIMIT 1
             FOR UPDATE'
        );

    $existing->execute([
        $contributorId,
        $clientSubmissionKey,
    ]);

    $existingReport =
        $existing->fetch(PDO::FETCH_ASSOC);

    if (is_array($existingReport)) {
        if (
            !hash_equals(
                (string) ($existingReport['content_sha256'] ?? ''),
                $contentHash
            )
        ) {
            throw new RuntimeException(
                'The approved Scout Report submission key already belongs to different content.'
            );
        }

        return (int) $existingReport['id'];
    }

    $insertReport =
        $db->prepare(
            'INSERT INTO scout_reports
                (
                    logical_report_key,
                    revision_number,
                    prior_revision_id,
                    amends_report_id,
                    submission_bundle_key,
                    place_id,
                    target_scope,
                    target_source,
                    target_id,
                    target_label,
                    contributor_id,
                    role_at_submission,
                    visit_date,
                    submitted_at,
                    schema_version,
                    source_channel,
                    client_submission_key,
                    content_sha256
                )
             VALUES
                (?, 1, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

    $insertReport->execute([
        $logicalReportKey,
        $submissionBundleKey,
        $placeId,
        $scope,
        $source,
        $targetId,
        $targetLabel !== ''
            ? $targetLabel
            : null,
        $contributorId,
        $roleAtSubmission,
        $visitDate,
        $submittedAt,
        $schemaVersion,
        'web',
        $clientSubmissionKey,
        $contentHash,
    ]);

    $reportId =
        (int) $db->lastInsertId();

    if ($reportId < 1) {
        throw new RuntimeException(
            'The immutable Scout Report could not be created.'
        );
    }

    $insertAnswer =
        $db->prepare(
            'INSERT INTO scout_report_answers
                (report_id, question_key, answer_state, value_json, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

    foreach ($normalizedAnswers as $fieldKey => $answer) {
        $insertAnswer->execute([
            $reportId,
            $fieldKey,
            $answer['state'],
            llama_scout_report_json($answer['value']),
        ]);
    }

    $insertPhoto =
        $db->prepare(
            'INSERT INTO scout_report_photos
                (
                    report_id,
                    photo_path,
                    photo_path_sha256,
                    sort_order,
                    photo_snapshot_json,
                    created_at
                )
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

    foreach ($photoSnapshots as $photo) {
        $insertPhoto->execute([
            $reportId,
            $photo['photo_path'],
            $photo['photo_path_sha256'],
            $photo['sort_order'],
            llama_scout_report_json($photo['snapshot']),
        ]);
    }

    $insertEvent =
        $db->prepare(
            'INSERT INTO scout_report_moderation_events
                (
                    report_id,
                    event_type,
                    actor_user_id,
                    reason,
                    metadata_json,
                    created_at
                )
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

    $insertEvent->execute([
        $reportId,
        'approved',
        $reviewedBy,
        $reviewNotes !== ''
            ? $reviewNotes
            : null,
        llama_scout_report_json([
            'place_update_submission_id' =>
                $updateId,
        ]),
    ]);

    return $reportId;
}

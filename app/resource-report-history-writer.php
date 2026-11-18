<?php

declare(strict_types=1);

require_once __DIR__ . '/resource-report-history-candidate.php';

/**
 * This writer is intentionally NOT called by Resource moderation yet.
 * Deployment requires both explicit enablement and the approved sidecar
 * schema. A PR merge or approved question list never enables writes.
 */
function llama_resource_ledger_writer_enabled(): bool
{
    return getenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED') === '1';
}

/**
 * Record one approved Resource or Resource-backed Facility submission.
 *
 * Caller must already hold the normal Resource moderation transaction and
 * must have saved the Resource, its selected Services and Experience answers.
 * All target ownership and answer values are re-read from those saved rows.
 *
 * This does not update resource_scout_current_answers or Resource pages.
 * It never changes the historical Place/Site/Area Scout Report ledger.
 *
 * @param list<array<string,mixed>> $approvedPhotos Trusted, reviewed source photos
 * @param list<string> $permanentPaths Paths actually copied by the publisher
 */
function llama_resource_ledger_record_approved(
    PDO $db,
    int $resourceId,
    int $submissionId,
    int $reviewedBy,
    string $reviewNotes = '',
    array $approvedPhotos = [],
    array $permanentPaths = []
): int {
    if (!llama_resource_ledger_writer_enabled()) {
        throw new RuntimeException(
            'Resource history writes are disabled pending reviewed deployment.'
        );
    }
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Resource history writes require the active moderation transaction.'
        );
    }
    if ($resourceId < 1 || $submissionId < 1 || $reviewedBy < 1) {
        throw new InvalidArgumentException('Invalid Resource moderation identity.');
    }

    // Querying the sidecar ensures the migration was installed. Fail closed
    // on missing tables, bad permissions or an unexpected schema.
    $db->query('SELECT id FROM resource_scout_reports LIMIT 0');

    $submissionStmt = $db->prepare(
        'SELECT id, submitted_by, target_resource_id, created_at, status
         FROM resource_submissions WHERE id = ? FOR UPDATE'
    );
    $submissionStmt->execute([$submissionId]);
    $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($submission)) {
        throw new InvalidArgumentException('Resource submission not found.');
    }
    if (!in_array((string) $submission['status'], ['pending', 'approved'], true)) {
        throw new InvalidArgumentException('Only approved Resource submissions can be recorded.');
    }

    $contributorId = (int) $submission['submitted_by'];
    $targetId = (int) ($submission['target_resource_id'] ?? 0);
    $submittedAt = (string) $submission['created_at'];
    if ($contributorId < 1 || $submittedAt === '') {
        throw new RuntimeException('Verified Resource submission provenance is missing.');
    }
    if ($targetId > 0 && $targetId !== $resourceId) {
        throw new InvalidArgumentException(
            'The selected Resource does not match the submitted update target.'
        );
    }

    // The Resource lock also serializes revision numbering for this target.
    $resourceStmt = $db->prepare('SELECT * FROM resources WHERE id = ? FOR UPDATE');
    $resourceStmt->execute([$resourceId]);
    $saved = $resourceStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($saved) || (int) ($saved['is_active'] ?? 0) !== 1) {
        throw new InvalidArgumentException('The reviewed Resource does not exist or is inactive.');
    }
    if ($targetId === 0 && (int) ($saved['created_by'] ?? 0) !== $contributorId) {
        throw new InvalidArgumentException(
            'A new Resource report must belong to its submitting contributor.'
        );
    }

    $serviceStmt = $db->prepare(
        'SELECT service_type, status, fee_status, fee_description,
                access_notes, notes, sort_order
         FROM resource_services
         WHERE resource_id = ? AND is_active = 1
         ORDER BY sort_order, service_type'
    );
    $serviceStmt->execute([$resourceId]);
    $serviceFacts = $serviceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $raw = json_decode((string) ($saved['experience_json'] ?? 'null'), true, 512, JSON_THROW_ON_ERROR);
    if ($raw !== null && !is_array($raw)) {
        throw new InvalidArgumentException('The saved Resource Experience is invalid.');
    }
    $parentId = (int) ($saved['parent_place_id'] ?? 0);
    if ($parentId > 0) {
        $parentStmt = $db->prepare('SELECT id FROM places WHERE id = ? LIMIT 1');
        $parentStmt->execute([$parentId]);
        if (!$parentStmt->fetchColumn()) {
            throw new InvalidArgumentException(
                'The linked Facility parent Place does not exist.'
            );
        }
    }

    $resource = $saved;
    $resource['experience'] = is_array($raw) ? $raw : [];
    $resource['services'] = $serviceFacts;

    // A single submission ID is the idempotency key. If this is a retry,
    // preserve the original review timestamp for a stable content digest.
    $existingStmt = $db->prepare(
        'SELECT id, resource_id, contributor_id, reviewed_at, content_sha256
         FROM resource_scout_reports WHERE submission_id = ? FOR UPDATE'
    );
    $existingStmt->execute([$submissionId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && (
        (int) $existing['resource_id'] !== $resourceId
        || (int) $existing['contributor_id'] !== $contributorId
    )) {
        throw new RuntimeException('A submission is already bound to another Resource report.');
    }
    $reviewedAt = $existing
        ? (string) $existing['reviewed_at']
        : (string) $db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();

    $candidate = llama_resource_history_candidate($resource, [
        'submission_id' => $submissionId,
        'contributor_id' => $contributorId,
        'reviewed_by' => $reviewedBy,
        'submitted_at' => $submittedAt,
    ]);
    if ($candidate['unmapped'] !== []) {
        throw new InvalidArgumentException(
            'Resource Experience contains unrecognized or inactive Service answers.'
        );
    }
    if ($candidate['target']['id'] !== $resourceId
        || $candidate['target']['source'] !== 'resource_record') {
        throw new RuntimeException('Resource report target identity mismatch.');
    }

    $schema = llama_resource_history_candidate_schema();
    if ($schema['definition']['approval_status']
        !== 'existing_resource_questions_approved_writer_not_enabled') {
        throw new RuntimeException('Resource-only question ownership is not approved.');
    }
    foreach ($schema['definition']['service_question_candidates'] as $question) {
        if (($question['review_status'] ?? '') !== 'approved_existing_resource_facility') {
            throw new RuntimeException('An Experience question is not approved.');
        }
    }
    if ($schema['schema_version'] !== $candidate['schema_version']) {
        throw new RuntimeException('Resource question schema changed during approval.');
    }

    // These paths must come from the existing verified moderation photo
    // publisher, not from a contributor-supplied target path.
    if (count($approvedPhotos) > 10) {
        throw new InvalidArgumentException('Too many Resource report photos.');
    }
    if (count($approvedPhotos) !== count($permanentPaths)) {
        throw new RuntimeException('Resource report photo evidence is incomplete.');
    }
    $snapshots = [];
    $seenPaths = [];
    foreach ($approvedPhotos as $index => $photo) {
        if (!is_array($photo)) {
            throw new InvalidArgumentException('Invalid reviewed Resource photo.');
        }
        $source = (string) ($photo['path'] ?? '');
        $sourcePrefix = '/uploads/resource-submissions/' . $submissionId . '/';
        if (!str_starts_with($source, $sourcePrefix)
            || !preg_match('/^[A-Za-z0-9._-]+$/D', substr($source, strlen($sourcePrefix)))) {
            throw new InvalidArgumentException('Photo evidence is not owned by this submission.');
        }
        $path = (string) $permanentPaths[$index];
        $prefix = '/uploads/resources/' . $resourceId . '/';
        if (!str_starts_with($path, $prefix)
            || !preg_match('/^[A-Za-z0-9._-]+$/D', substr($path, strlen($prefix)))
            || isset($seenPaths[$path])) {
            throw new InvalidArgumentException('Invalid or repeated Resource photo evidence path.');
        }
        $seenPaths[$path] = true;
        $absolute = dirname(__DIR__) . $path;
        if (!is_file($absolute)) {
            throw new RuntimeException('Approved Resource photo is missing from permanent storage.');
        }
        $fileHash = hash_file('sha256', $absolute);
        if (!is_string($fileHash)) {
            throw new RuntimeException('Could not hash approved Resource photo evidence.');
        }
        $snapshots[] = [
            'path' => $path,
            'path_sha256' => hash('sha256', $path),
            'sort_order' => (int) $index,
            'snapshot' => [
                'permanent_path' => $path,
                'file_sha256' => $fileHash,
                'submitted' => [
                    'path' => $source,
                    'alt' => (string) ($photo['alt'] ?? ''),
                ],
            ],
        ];
    }

    $recordFields = [
        'id', 'parent_place_id', 'resource_type', 'name', 'status',
        'latitude', 'longitude', 'notes', 'street_address', 'city',
        'county', 'state_region', 'postal_code', 'country_code', 'phone',
        'website_url', 'hours_text', 'hours_mode', 'fixed_open_hour',
        'fixed_close_hour',
    ];
    $recordSnapshot = array_intersect_key(
        $saved, array_fill_keys($recordFields, true)
    );
    $eventMetadata = [
        'resource_record' => $recordSnapshot,
        'service_facts' => $serviceFacts,
        'preview_contract' => $candidate['contract'],
        'source_channel' => 'resource_moderation',
    ];
    $digest = hash('sha256', llama_scout_report_json([
        'target' => $candidate['target'],
        'submission_id' => $submissionId,
        'contributor_id' => $contributorId,
        'reviewed_by' => $reviewedBy,
        'submitted_at' => $submittedAt,
        'reviewed_at' => $reviewedAt,
        'schema_version' => $schema['schema_version'],
        'answers' => $candidate['answers'],
        'approved_photos' => $snapshots,
        'review_notes' => $reviewNotes,
        'resource_snapshot' => $recordSnapshot,
        'service_facts' => $serviceFacts,
    ]));

    if ($existing) {
        if (!hash_equals((string) $existing['content_sha256'], $digest)) {
            throw new RuntimeException(
                'The Resource submission key already belongs to different approved content.'
            );
        }
        return (int) $existing['id'];
    }
    if ($submission['status'] !== 'pending') {
        throw new InvalidArgumentException('An approved submission has no matching immutable report.');
    }

    $insertSchema = $db->prepare(
        'INSERT IGNORE INTO resource_scout_report_schemas
            (schema_version, definition_json) VALUES (?, ?)'
    );
    $insertSchema->execute([$schema['schema_version'], $schema['definition_json']]);
    $verifySchema = $db->prepare(
        'SELECT definition_json FROM resource_scout_report_schemas WHERE schema_version = ?'
    );
    $verifySchema->execute([$schema['schema_version']]);
    $storedSchema = (string) $verifySchema->fetchColumn();
    if (!hash_equals($schema['schema_version'], hash('sha256', $storedSchema))) {
        throw new RuntimeException('Resource historical schema does not match its version.');
    }

    $logicalKey = 'resource_record:' . $resourceId;
    $priorStmt = $db->prepare(
        'SELECT id, revision_number FROM resource_scout_reports
         WHERE logical_report_key = ? ORDER BY revision_number DESC LIMIT 1 FOR UPDATE'
    );
    $priorStmt->execute([$logicalKey]);
    $prior = $priorStmt->fetch(PDO::FETCH_ASSOC);
    $revision = $prior ? (int) $prior['revision_number'] + 1 : 1;
    $parentId = $candidate['target']['parent_place_id'];

    $insert = $db->prepare(
        'INSERT INTO resource_scout_reports
           (resource_id, target_kind, parent_place_id, submission_id,
            logical_report_key, revision_number, prior_report_id, contributor_id,
            role_at_submission, submitted_at, reviewed_by, reviewed_at, visit_date,
            schema_version, client_submission_key, content_sha256)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insert->execute([
        $resourceId, $candidate['target']['kind'], $parentId, $submissionId,
        $logicalKey, $revision, $prior ? (int) $prior['id'] : null,
        $contributorId, 'not_recorded', $submittedAt, $reviewedBy, $reviewedAt, null,
        $schema['schema_version'], 'resource-review:' . $submissionId, $digest,
    ]);
    $reportId = (int) $db->lastInsertId();
    if ($reportId < 1) {
        throw new RuntimeException('Resource immutable report was not created.');
    }

    $insertAnswer = $db->prepare(
        'INSERT INTO resource_scout_report_answers
          (report_id, service_type, group_key, question_key,
           canonical_question_key, answer_state, value_json, legacy_value_json)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($candidate['answers'] as $answer) {
        $insertAnswer->execute([
            $reportId,
            $answer['service_type'],
            $answer['group_key'],
            $answer['question_key'],
            $answer['canonical_question_key'],
            $answer['state'],
            llama_scout_report_json($answer['value']),
            llama_scout_report_json($answer['legacy_value']),
        ]);
    }

    $insertEvent = $db->prepare(
        'INSERT INTO resource_scout_report_events
           (report_id, event_type, actor_user_id, reason, metadata_json)
         VALUES (?,?,?,?,?)'
    );
    $insertEvent->execute([
        $reportId, 'approved', $reviewedBy, $reviewNotes,
        llama_scout_report_json($eventMetadata),
    ]);

    $insertPhoto = $db->prepare(
        'INSERT INTO resource_scout_report_photos
           (report_id, photo_path, photo_path_sha256, sort_order, photo_snapshot_json)
         VALUES (?,?,?,?,?)'
    );
    foreach ($snapshots as $photo) {
        $insertPhoto->execute([
            $reportId, $photo['path'], $photo['path_sha256'],
            $photo['sort_order'], llama_scout_report_json($photo['snapshot']),
        ]);
    }
    return $reportId;
}

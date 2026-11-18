<?php

declare(strict_types=1);

/**
 * Versioned current-answer projection for approved Resources and linked
 * Facilities. The immutable report rows, never untrusted contributor input,
 * are the only source for these updates.
 *
 * This is NOT the public read cutover: resources.experience_json remains the
 * authoritative public/contributor read source until a separate review.
 *
 * Caller must hold the Resource's moderation transaction and record lock.
 * Every INSERT/UPDATE occurs inside that same transaction as the historical
 * report. The schema already exists from the PR #192 sidecar migration.
 */
function llama_resource_ledger_sync_current_answers(
    PDO $db,
    int $resourceId,
    int $reportId
): void {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Resource current-answer sync requires a moderation transaction.'
        );
    }
    if ($resourceId < 1 || $reportId < 1) {
        throw new InvalidArgumentException('A verified Resource report is required.');
    }

    // Verify the persisted report, and never derive a Resource/Facility target
    // from a client-selected tab, parent Place form or legacy mapped Area.
    $reportStmt = $db->prepare(
        'SELECT id, resource_id, target_kind, parent_place_id
         FROM resource_scout_reports WHERE id = ? FOR UPDATE'
    );
    $reportStmt->execute([$reportId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($report) || (int) $report['resource_id'] !== $resourceId) {
        throw new InvalidArgumentException(
            'Current-answer report does not belong to the selected Resource.'
        );
    }

    $kind = (string) $report['target_kind'];
    $parentId = (int) ($report['parent_place_id'] ?? 0);
    if (!in_array($kind, ['resource', 'facility'], true)
        || ($kind === 'resource' && $parentId !== 0)
        || ($kind === 'facility' && $parentId < 1)) {
        throw new RuntimeException('Invalid Resource/Facility report target.');
    }

    $resourceStmt = $db->prepare(
        'SELECT parent_place_id FROM resources WHERE id = ? FOR UPDATE'
    );
    $resourceStmt->execute([$resourceId]);
    $savedParent = $resourceStmt->fetchColumn();
    if ($savedParent === false || (int) $savedParent !== $parentId) {
        throw new RuntimeException(
            'Saved Resource/Facility identity changed during answer publication.'
        );
    }

    // The report already passed canonical Service/group/question validation
    // in the approved writer. Fetch its persisted answer snapshots so the
    // mutable current table cannot diverge from immutable history.
    $answerStmt = $db->prepare(
        'SELECT service_type, group_key, question_key, answer_state, value_json
         FROM resource_scout_report_answers
         WHERE report_id = ?
         ORDER BY service_type, group_key, question_key'
    );
    $answerStmt->execute([$reportId]);
    $reportAnswers = $answerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $incoming = [];
    foreach ($reportAnswers as $answer) {
        $service = (string) $answer['service_type'];
        $group = (string) $answer['group_key'];
        $key = (string) $answer['question_key'];
        $state = (string) $answer['answer_state'];
        if ($service === '' || $group === '' || $key === ''
            || !in_array($state, ['answered', 'unknown'], true)) {
            throw new RuntimeException('Invalid immutable Resource answer snapshot.');
        }
        $path = $service . '/' . $group . '/' . $key;
        if (isset($incoming[$path])) {
            throw new LogicException('Duplicate immutable Resource answer path.');
        }

        $value = json_decode((string) $answer['value_json'], true, 512, JSON_THROW_ON_ERROR);
        if ($state === 'unknown' && $value !== null) {
            throw new RuntimeException('Unknown must have a null immutable value.');
        }
        $incoming[$path] = [
            'service' => $service,
            'group' => $group,
            'question' => $key,
            'state' => $state,
            'value' => $value,
        ];
    }

    // Lock just THIS Resource's current rows. Never read, update or clear
    // another Resource, linked Facility, parent Place, or mapped Area.
    $currentStmt = $db->prepare(
        'SELECT c.id, c.service_type, c.group_key, c.question_key,
                c.answer_state, c.value_json, c.target_kind, c.row_version,
                previous.resource_id AS evidence_resource_id
         FROM resource_scout_current_answers c
         JOIN resource_scout_reports previous
           ON previous.id = c.source_report_id
         WHERE c.resource_id = ?
         FOR UPDATE'
    );
    $currentStmt->execute([$resourceId]);
    $currentRows = $currentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $insert = $db->prepare(
        'INSERT INTO resource_scout_current_answers
           (resource_id, target_kind, service_type, group_key, question_key,
            answer_state, value_json, source_report_id, row_version)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $update = $db->prepare(
        'UPDATE resource_scout_current_answers
         SET target_kind = ?, answer_state = ?, value_json = ?,
             source_report_id = ?, row_version = row_version + 1
         WHERE id = ? AND resource_id = ? AND row_version = ?'
    );

    $existing = [];
    foreach ($currentRows as $row) {
        if ((int) $row['evidence_resource_id'] !== $resourceId) {
            throw new RuntimeException(
                'Existing Resource answer cites history from another target.'
            );
        }
        $path = $row['service_type'] . '/' . $row['group_key']
            . '/' . $row['question_key'];
        if (isset($existing[$path])) {
            throw new RuntimeException('Duplicate current Resource answer path.');
        }
        $existing[$path] = $row;
    }

    foreach ($incoming as $path => $answer) {
        $json = json_encode(
            $answer['value'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (!isset($existing[$path])) {
            $insert->execute([
                $resourceId, $kind, $answer['service'], $answer['group'],
                $answer['question'], $answer['state'], $json, $reportId,
            ]);
            continue;
        }

        $row = $existing[$path];
        unset($existing[$path]);
        $previousValue = json_decode(
            (string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR
        );
        // Unchanged answers retain their original report provenance and row
        // version. A new approval is not a new observation of every field.
        if ($row['target_kind'] === $kind
            && $row['answer_state'] === $answer['state']
            && $previousValue === $answer['value']) {
            continue;
        }

        $update->execute([
            $kind, $answer['state'], $json, $reportId,
            (int) $row['id'], $resourceId, (int) $row['row_version'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Resource current answer changed concurrently.');
        }
    }

    // The saved Resource snapshot no longer includes these previously
    // answered keys. Keep explicit Unanswered tombstones instead of erasing
    // the old identity or confusing cleared with Unknown/No/zero.
    foreach ($existing as $row) {
        if ($row['target_kind'] === $kind
            && $row['answer_state'] === 'unanswered') {
            continue;
        }
        $update->execute([
            $kind, 'unanswered', 'null', $reportId,
            (int) $row['id'], $resourceId, (int) $row['row_version'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Resource clear operation changed concurrently.');
        }
    }
}

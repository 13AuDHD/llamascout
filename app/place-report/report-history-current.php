<?php

declare(strict_types=1);

/**
 * Retrieve the newest approved observation for each question on one target.
 *
 * Reads only the append-only V3 report ledger, without legacy backfill.
 * Approval records are inserted when moderation succeeds, so report ID
 * provides a deterministic approval order. Report visit dates are retained
 * as attribution, not used to overwrite newer approvals.
 *
 * Does not apply visibility rules. Callers must authorize the target and
 * filter question-level visibility before returning data to a visitor.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_scout_report_latest_approved_answers(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $targetId
): array {
    if ($placeId < 1 || $targetId < 1
        || !in_array($scope, ['place', 'area', 'site'], true)
        || $source === '') {
        throw new InvalidArgumentException('Invalid Scout Report target.');
    }

    // Verify the exact reporting target before reading current approved
    // observations. The caller still controls membership and field visibility.
    require_once __DIR__ . '/report-targets.php';
    llama_report_verified_target($db, $placeId, $scope, $source, $targetId);

    $stmt = $db->prepare(
        'SELECT ranked.question_key,
                ranked.answer_state,
                ranked.value_json,
                ranked.report_id,
                ranked.contributor_id,
                ranked.visit_date,
                ranked.submitted_at,
                ranked.source_channel
         FROM (
             SELECT a.question_key, a.answer_state, a.value_json,
                    r.id AS report_id, r.contributor_id, r.visit_date,
                    r.submitted_at, r.source_channel,
                    ROW_NUMBER() OVER (
                        PARTITION BY a.question_key
                        ORDER BY r.id DESC, a.id DESC
                    ) AS answer_rank
             FROM scout_reports r
             JOIN scout_report_answers a ON a.report_id = r.id
             WHERE r.place_id = ?
               AND r.target_scope = ?
               AND r.target_source = ?
               AND r.target_id = ?
         ) AS ranked
         WHERE ranked.answer_rank = 1
         ORDER BY ranked.question_key'
    );
    $stmt->execute([$placeId, $scope, $source, $targetId]);

    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) $row['question_key'];
        $state = (string) $row['answer_state'];
        if (!in_array($state, ['answered', 'unknown'], true)) {
            throw new UnexpectedValueException('Invalid stored report answer state.');
        }
        $answers[$key] = [
            'state' => $state,
            'value' => $state === 'answered'
                ? json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR)
                : null,
            'report_id' => (int) $row['report_id'],
            'contributor_id' => (int) $row['contributor_id'],
            'visit_date' => $row['visit_date'],
            'submitted_at' => $row['submitted_at'],
            'source_channel' => $row['source_channel'],
        ];
    }

    return $answers;
}

/**
 * Annotate existing published answers with matching immutable report evidence.
 * Do not replace published values here: imported baseline precedence and
 * scoped inheritance remain owned by the current-answer model.
 *
 * This function accepts a verified reporting target and must only be used
 * behind normal target authorization and question visibility checks.
 */
function llama_scout_report_attach_matching_history(
    array $answers,
    array $observations
): array {
    foreach ($observations as $key => $observation) {
        if (!isset($answers[$key]) || !is_array($answers[$key])) {
            continue;
        }
        $current = $answers[$key];
        if (empty($current['recorded'])
            || !empty($current['imported'])
            || !empty($current['inherited'])
            || !empty($current['baseline'])
            || ($current['state'] ?? '') !== ($observation['state'] ?? '')) {
            continue;
        }
        if (($current['state'] ?? '') === 'answered') {
            $field = llama_place_report_fields()[(string) $key] ?? null;
            if (!is_array($field)
                || !llama_place_report_values_equal(
                    $current['value'] ?? null,
                    $observation['value'] ?? null,
                    $field
                )) {
                continue;
            }
        }
        $answers[$key]['approved_report'] = [
            'id' => $observation['report_id'],
            'contributor_id' => $observation['contributor_id'],
            'visit_date' => $observation['visit_date'],
            'submitted_at' => $observation['submitted_at'],
            'source_channel' => $observation['source_channel'],
        ];
    }
    return $answers;
}

/**
 * Compose the current published projection with immutable report attribution.
 * Existing current-answer precedence is unchanged; no historical data is
 * backfilled and no database writes are performed.
 *
 * Caller is responsible for access checks before exposing model data.
 */
function llama_place_report_current_answer_model_with_history(
    PDO $db,
    int $placeId,
    array $target
): array {
    $model = llama_place_report_current_answer_model($db, $placeId, $target);
    $verified = $model['target'];
    $observations = llama_scout_report_latest_approved_answers(
        $db,
        $placeId,
        (string) $verified['scope'],
        (string) $verified['source'],
        (int) $verified['id']
    );
    $model['answers'] = llama_scout_report_attach_matching_history(
        $model['answers'],
        $observations
    );
    return $model;
}


/**
 * One attribution format for Place, Area and Site answer cards.
 * Never infer a contributor or observation date from the current value.
 *
 * @return array<string,array<string,mixed>>
 */
function llama_report_answer_sources(PDO $db, array $answers): array
{
    $ids = [];
    foreach ($answers as $answer) {
        $id = (int) ($answer['approved_report']['contributor_id'] ?? 0);
        if ($id > 0) { $ids[$id] = $id; }
    }
    $names = [];
    if ($ids) {
        $stmt = $db->prepare(
            'SELECT id, username FROM users WHERE id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $stmt->execute(array_values($ids));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $names[(int) $row['id']] = (string) $row['username'];
        }
    }
    $sources = [];
    foreach ($answers as $key => $answer) {
        if (empty($answer['recorded']) ||
            !in_array((string) ($answer['state'] ?? ''), ['answered', 'unknown'], true)) {
            continue;
        }
        $report = $answer['approved_report'] ?? null;
        if (is_array($report)) {
            $id = (int) ($report['contributor_id'] ?? 0);
            $sources[$key] = [
                'kind' => 'member',
                'label' => trim((string) ($names[$id] ?? '')) ?: 'Former member',
                'visitDate' => $report['visit_date'] ?? null,
                'submittedAt' => $report['submitted_at'] ?? null,
            ];
            continue;
        }
        if (!empty($answer['imported'])) {
            $origin = (array) ($answer['origin'] ?? []);
            $provider = trim((string) ($origin['provider'] ?? ''));
            if ($provider !== '') {
                $sources[$key] = [
                    'kind' => 'source',
                    'label' => $provider,
                    'sourceUpdatedAt' => $origin['source_updated_at'] ?? null,
                ];
            }
        }
    }
    return $sources;
}

/**
 * Read approved Site report history without changing current published values.
 * The caller must verify the target and authorize Site-level access first.
 *
 * @return array<int,array<string,mixed>>
 */
function llama_scout_report_target_history(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $targetId,
    int $limit = 50
): array {
    if ($placeId < 1 || $targetId < 1
        || !in_array($scope, ['place', 'area', 'site'], true)
        || trim($source) === '') {
        throw new InvalidArgumentException('Invalid Scout Report history target.');
    }
    // Defense in depth: verify that this exact Site/Area/Place target
    // belongs to the requested Place before reading its report ledger.
    // Authentication and paid/earned visibility remain the caller's job.
    require_once __DIR__ . '/report-targets.php';
    llama_report_verified_target($db, $placeId, $scope, $source, $targetId);

    $limit = max(1, min($limit, 100));
    $query = $db->prepare(
        'SELECT r.id, r.revision_number, r.contributor_id,
                u.username, r.visit_date, r.submitted_at,
                r.source_channel, r.target_label
         FROM scout_reports r
         LEFT JOIN users u ON u.id = r.contributor_id
         WHERE r.place_id = ?
           AND r.target_scope = ?
           AND r.target_source = ?
           AND r.target_id = ?
         ORDER BY r.id DESC
         LIMIT ' . $limit
    );
    $query->execute([$placeId, $scope, $source, $targetId]);
    $reports = [];
    $ids = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $id = (int) $row['id'];
        $ids[] = $id;
        $reports[$id] = [
            'reportId' => $id,
            'revision' => (int) $row['revision_number'],
            'username' => trim((string) ($row['username'] ?? '')) ?: 'Former member',
            'visitDate' => $row['visit_date'],
            'submittedAt' => $row['submitted_at'],
            'sourceChannel' => $row['source_channel'],
            'targetLabel' => $row['target_label'],
            'answers' => [],
        ];
    }
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $answerQuery = $db->prepare(
        'SELECT report_id, question_key, answer_state, value_json
         FROM scout_report_answers
         WHERE report_id IN (' . $marks . ')
         ORDER BY report_id DESC, question_key ASC'
    );
    $answerQuery->execute($ids);
    while ($answer = $answerQuery->fetch(PDO::FETCH_ASSOC)) {
        $id = (int) $answer['report_id'];
        if (!isset($reports[$id])) {
            continue;
        }
        $state = (string) $answer['answer_state'];
        if (!in_array($state, ['answered', 'unknown'], true)) {
            continue;
        }
        $reports[$id]['answers'][(string) $answer['question_key']] = [
            'state' => $state,
            'value' => $state === 'answered'
                ? json_decode((string) $answer['value_json'], true, 512, JSON_THROW_ON_ERROR)
                : null,
        ];
    }
    return array_values($reports);
}

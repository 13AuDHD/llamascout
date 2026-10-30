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

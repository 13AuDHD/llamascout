<?php

declare(strict_types=1);

/**
 * Shared V3 target-keyed current answer storage.
 *
 * This service is deliberately independent of legacy Place SQL columns and
 * Site overrides. It will become the common persistence adapter as those
 * readers/writers are migrated, without rewriting immutable report history.
 *
 * Callers must enforce user authorization; this service verifies target
 * ownership and canonical question scope on every read/write.
 */
function llama_report_current_store_target(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $targetId
): void {
    if ($placeId < 1 || $targetId < 1
        || !in_array($scope, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException('Invalid reporting target.');
    }
    require_once __DIR__ . '/report-targets.php';
    llama_report_verified_target($db, $placeId, $scope, $source, $targetId);
}

/**
 * Migration rollout check. Existing V2 storage remains authoritative until
 * M3-001 is installed and the current-answer readers are cut over.
 * Never use this check to suppress failures after the table exists.
 */
function llama_report_current_store_available(PDO $db): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute(['place_report_current_answers']);
    return $available = ((int) $statement->fetchColumn() === 1);
}

/**
 * One canonical answer writer for Place, Area and Site.
 *
 * Unanswered removes the current observation, Unknown is explicitly retained,
 * and an answered false/zero/empty list remains an answered observation.
 * Does not change the immutable approved report ledger.
 */
function llama_report_current_store_save(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $targetId,
    string $key,
    mixed $raw
): void {
    if (!$db->inTransaction()) {
        throw new RuntimeException('Current-answer publication requires a transaction.');
    }
    llama_report_current_store_target($db, $placeId, $scope, $source, $targetId);
    $field = llama_place_report_fields()[$key] ?? null;
    if (!is_array($field) || !empty($field['derived'])
        || !in_array($scope, (array) ($field['report_scopes'] ?? []), true)) {
        throw new InvalidArgumentException('Question is not writable for this reporting target.');
    }
    $answer = llama_place_report_submission_answer($field, $raw);

    if ($answer['state'] === 'unanswered') {
        $stmt = $db->prepare(
            'DELETE FROM place_report_current_answers
             WHERE place_id = ? AND target_scope = ? AND target_source = ?
               AND target_id = ? AND question_key = ?'
        );
        $stmt->execute([$placeId, $scope, $source, $targetId, $key]);
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_report_current_answers
            (place_id, target_scope, target_source, target_id,
             question_key, answer_state, value_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            answer_state = VALUES(answer_state),
            value_json = VALUES(value_json),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        $placeId, $scope, $source, $targetId, $key,
        $answer['state'],
        json_encode($answer['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
}

/**
 * @return array<string,array{state:string,value:mixed}>
 */
function llama_report_current_store_load(
    PDO $db,
    int $placeId,
    string $scope,
    string $source,
    int $targetId
): array {
    llama_report_current_store_target($db, $placeId, $scope, $source, $targetId);
    $stmt = $db->prepare(
        'SELECT question_key, answer_state, value_json
         FROM place_report_current_answers
         WHERE place_id = ? AND target_scope = ?
           AND target_source = ? AND target_id = ?'
    );
    $stmt->execute([$placeId, $scope, $source, $targetId]);
    $fields = llama_place_report_fields();
    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) $row['question_key'];
        $field = $fields[$key] ?? null;
        if (!is_array($field)
            || !in_array($scope, (array) ($field['report_scopes'] ?? []), true)) {
            continue;
        }
        $state = (string) $row['answer_state'];
        if (!in_array($state, ['answered', 'unknown'], true)) {
            throw new UnexpectedValueException('Invalid persisted current-answer state.');
        }
        $answers[$key] = [
            'state' => $state,
            'value' => $state === 'unknown' ? null
                : json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }
    return $answers;
}

/**
 * Bulk current Area/Site observations for one Place. Read once for all Sites.
 * Target ownership is enforced when the caller resolves verified targets;
 * this storage-only helper must never be exposed as a public endpoint.
 *
 * @return array<string,array<string,array<int,array<string,array{state:string,value:mixed}>>>>
 */
function llama_report_current_store_scoped_for_place(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        throw new InvalidArgumentException('Invalid Place ID.');
    }
    if (!llama_report_current_store_available($db)) {
        return [];
    }
    $query = $db->prepare(
        "SELECT target_scope, target_source, target_id, question_key,
                answer_state, value_json
         FROM place_report_current_answers
         WHERE place_id = ? AND target_scope IN ('area', 'site')"
    );
    $query->execute([$placeId]);
    $fields = llama_place_report_fields();
    $result = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $scope = (string) $row['target_scope'];
        $key = (string) $row['question_key'];
        $field = $fields[$key] ?? null;
        if (!is_array($field) || !empty($field['derived'])
            || !in_array($scope, (array) ($field['report_scopes'] ?? []), true)) {
            continue;
        }
        $state = (string) $row['answer_state'];
        if (!in_array($state, ['answered', 'unknown'], true)) {
            throw new UnexpectedValueException('Invalid current-answer state.');
        }
        $result[$scope][(string) $row['target_source']][(int) $row['target_id']][$key] = [
            'state' => $state,
            'value' => $state === 'unknown' ? null
                : json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }
    return $result;
}

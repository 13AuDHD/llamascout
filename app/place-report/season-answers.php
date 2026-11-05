<?php

declare(strict_types=1);

require_once __DIR__ . '/season-definitions.php';
require_once __DIR__ . '/season-answer-contract.php';
require_once __DIR__ . '/report-targets.php';

/**
 * Seasonal values are CURRENT APPROVED overrides, not duplicate Scout Reports.
 * The canonical question catalog validates every field and answer type.
 * A missing row means "inherit the normal answer".
 */

function llama_season_verified_place(PDO $db, int $placeId, int $seasonId, bool $lock = false): array
{
    if ($placeId < 1 || $seasonId < 1) {
        throw new InvalidArgumentException('Select a valid Place and saved season.');
    }
    $stmt = $db->prepare(
        'SELECT id, place_id, name, source_url, is_active FROM place_seasons
         WHERE id = ? AND place_id = ? AND is_active = 1'
        . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$seasonId, $placeId]);
    $season = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($season)) {
        throw new InvalidArgumentException('The selected active season was not found for this Place.');
    }
    return $season;
}

function llama_season_source_url(mixed $value): ?string
{
    $url = trim((string) ($value ?? ''));
    if ($url === '') {
        return null;
    }
    if (strlen($url) > 2048
        || !filter_var($url, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
        throw new InvalidArgumentException('Enter a valid HTTP(S) source URL.');
    }
    return $url;
}

/** Read one season/target's overrides without modifying normal answers. */
function llama_season_answers_for_target(
    PDO $db, int $placeId, int $seasonId, array $target
): array {
    llama_season_verified_place($db, $placeId, $seasonId);
    $verified = llama_report_verified_target(
        $db, $placeId, (string) ($target['scope'] ?? ''),
        (string) ($target['source'] ?? ''), (int) ($target['id'] ?? 0)
    );
    $stmt = $db->prepare(
        'SELECT question_key, answer_state, value_json, source_url, approved_by, approved_at
         FROM place_season_answers
         WHERE place_id = ? AND season_id = ? AND target_scope = ?
           AND target_source = ? AND target_id = ?'
    );
    $stmt->execute([
        $placeId, $seasonId, $verified['scope'], $verified['source'], $verified['id']
    ]);
    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) $row['question_key'];
        try {
            llama_season_question_for_scope($key, (string) $verified['scope']);
        } catch (InvalidArgumentException) {
            continue;
        }
        $answers[$key] = [
            'state' => (string) $row['answer_state'],
            'value' => (string) $row['answer_state'] === 'unknown'
                ? null
                : json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR),
            'source_url' => $row['source_url'],
            'approved_by' => (int) $row['approved_by'],
            'approved_at' => $row['approved_at'],
        ];
    }
    return $answers;
}

function llama_season_site_status_for_target(
    PDO $db, int $placeId, int $seasonId, array $target
): ?array {
    if ((string) ($target['scope'] ?? '') !== 'site') {
        return null;
    }
    llama_season_verified_place($db, $placeId, $seasonId);
    $verified = llama_report_verified_target(
        $db, $placeId, 'site', (string) ($target['source'] ?? ''),
        (int) ($target['id'] ?? 0)
    );
    $stmt = $db->prepare(
        'SELECT operating_status, source_url, approved_by, approved_at
         FROM place_season_site_status
         WHERE place_id = ? AND season_id = ?
           AND target_source = ? AND target_id = ?
         LIMIT 1'
    );
    $stmt->execute([
        $placeId, $seasonId, $verified['source'], $verified['id']
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * Save one season/target's small set of allowed question overrides.
 * All inputs are parsed using the canonical field contract. Unsupported keys,
 * wrong scope, invalid types, and unverified Site IDs are rejected.
 *
 * The caller MUST enforce Admin/Owner authorization and CSRF.
 * Use one transaction for answers, Site operating state, and audit.
 */
function llama_season_save_target_answers(
    PDO $db,
    int $placeId,
    int $seasonId,
    array $target,
    int $actorId,
    array $modes,
    array $values,
    ?string $rawSiteStatus,
    mixed $sourceInput = null
): array {
    if ($placeId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('A valid Place and administrator are required.');
    }

    $owns = !$db->inTransaction();
    if ($owns) {
        $db->beginTransaction();
    }
    try {
        // Serialize with edits to Place season definitions.
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }
        $season = llama_season_verified_place($db, $placeId, $seasonId, true);
        $verified = llama_report_verified_target(
            $db, $placeId,
            (string) ($target['scope'] ?? ''),
            (string) ($target['source'] ?? ''),
            (int) ($target['id'] ?? 0)
        );
        $scope = (string) $verified['scope'];
        $fields = llama_season_questions_for_scope($scope);
        foreach (array_keys($modes) as $key) {
            if (!is_string($key) || !isset($fields[$key])) {
                throw new InvalidArgumentException('An unsupported seasonal question was submitted.');
            }
        }
        $source = llama_season_source_url($sourceInput)
            ?? llama_season_source_url($season['source_url'] ?? null);

        $previousStmt = $db->prepare(
            'SELECT question_key, answer_state, value_json, source_url
             FROM place_season_answers
             WHERE place_id = ? AND season_id = ? AND target_scope = ?
               AND target_source = ? AND target_id = ? FOR UPDATE'
        );
        $params = [
            $placeId, $seasonId, $scope, (string) $verified['source'], (int) $verified['id'],
        ];
        $previousStmt->execute($params);
        $previous = [];
        foreach ($previousStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $old) {
            $previous[(string) $old['question_key']] = $old;
        }

        $upsert = $db->prepare(
            'INSERT INTO place_season_answers
              (place_id, season_id, target_scope, target_source, target_id,
               question_key, answer_state, value_json, source_url, approved_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               answer_state = VALUES(answer_state),
               value_json = VALUES(value_json),
               source_url = VALUES(source_url),
               approved_by = VALUES(approved_by),
               approved_at = CURRENT_TIMESTAMP'
        );
        $delete = $db->prepare(
            'DELETE FROM place_season_answers
             WHERE place_id = ? AND season_id = ? AND target_scope = ?
               AND target_source = ? AND target_id = ? AND question_key = ?'
        );
        $changed = [];
        foreach ($fields as $key => $field) {
            if (!array_key_exists($key, $modes)) {
                // Do not erase an unsubmitted question.
                continue;
            }
            $mode = (string) $modes[$key];
            if (!in_array($mode, ['inherit', 'answer', 'unknown'], true)) {
                throw new InvalidArgumentException('Invalid seasonal answer mode for ' . $key);
            }
            if ($mode === 'inherit') {
                if (isset($previous[$key])) {
                    $delete->execute([...$params, $key]);
                    $changed[$key] = ['before' => $previous[$key], 'after' => null];
                }
                continue;
            }
            $raw = $mode === 'unknown'
                ? llama_place_report_unknown_token()
                : ($values[$key] ?? null);
            $answer = llama_place_report_submission_answer($field, $raw);
            if (!$answer['recorded']) {
                throw new InvalidArgumentException(
                    'Choose an answer for ' . rtrim((string) ($field['label'] ?? $key), ' *')
                    . ', or select Use normal answer.'
                );
            }
            $encoded = json_encode(
                $answer['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );
            $old = $previous[$key] ?? null;
            if ($old !== null && (string) $old['answer_state'] === $answer['state']
                && (string) $old['value_json'] === $encoded
                && (string) ($old['source_url'] ?? '') === (string) ($source ?? '')) {
                continue;
            }
            $upsert->execute([
                ...$params, $key, $answer['state'], $encoded, $source, $actorId,
            ]);
            $changed[$key] = [
                'before' => $old,
                'after' => [
                    'answer_state' => $answer['state'],
                    'value_json' => $encoded,
                    'source_url' => $source,
                ],
            ];
        }

        $statusChange = null;
        if ($scope === 'site' && $rawSiteStatus !== null) {
            if (!in_array($rawSiteStatus, ['inherit', 'open', 'closed'], true)) {
                throw new InvalidArgumentException('Invalid seasonal Site operating status.');
            }
            $read = $db->prepare(
                'SELECT operating_status FROM place_season_site_status
                 WHERE place_id = ? AND season_id = ?
                   AND target_source = ? AND target_id = ? FOR UPDATE'
            );
            $siteParams = [
                $placeId, $seasonId, (string) $verified['source'], (int) $verified['id']
            ];
            $read->execute($siteParams);
            $beforeStatus = $read->fetchColumn();
            $oldStatus = $beforeStatus === false ? null : (string) $beforeStatus;
            $newStatus = $rawSiteStatus === 'inherit' ? null : $rawSiteStatus;
            if ($newStatus !== $oldStatus) {
                if ($newStatus === null) {
                    $remove = $db->prepare(
                        'DELETE FROM place_season_site_status
                         WHERE place_id = ? AND season_id = ?
                           AND target_source = ? AND target_id = ?'
                    );
                    $remove->execute($siteParams);
                } else {
                    $save = $db->prepare(
                        'INSERT INTO place_season_site_status
                            (place_id, season_id, target_source, target_id,
                             operating_status, source_url, approved_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            operating_status = VALUES(operating_status),
                            source_url = VALUES(source_url),
                            approved_by = VALUES(approved_by),
                            approved_at = CURRENT_TIMESTAMP'
                    );
                    $save->execute([...$siteParams, $newStatus, $source, $actorId]);
                }
                $statusChange = ['before' => $oldStatus, 'after' => $newStatus];
            }
        }

        if ($changed || $statusChange) {
            require_once dirname(__DIR__) . '/admin-users.php';
            admin_users_audit(
                $db, $actorId, null, 'place.season_updated',
                'Updated approved seasonal Place/Area/Site answers.',
                [
                    'place_id' => $placeId,
                    'season_id' => $seasonId,
                    'target_scope' => $scope,
                    'target_source' => $verified['source'],
                    'target_id' => $verified['id'],
                    'answers' => $changed,
                    'site_status' => $statusChange,
                ]
            );
        }
        if ($owns) {
            $db->commit();
        }
        return [
            'updated_questions' => count($changed),
            'site_status_updated' => $statusChange !== null,
        ];
    } catch (Throwable $error) {
        if ($owns && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

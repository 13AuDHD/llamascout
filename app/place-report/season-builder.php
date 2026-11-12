<?php

declare(strict_types=1);

require_once __DIR__ . '/season-answers.php';

/**
 * Compact Admin seasonal change builder.
 *
 * Every target is checked against the existing Place/Area/Site registry.
 * Multi-select applies to Sites ONLY; Place/Area remain single targets.
 * No canonical question, immutable Scout Report, or verified closure is rewritten.
 */
const LLAMA_SEASON_SITE_CLOSED_KEY = 'seasonal_site_closed';

function llama_season_builder_targets(array $available, mixed $selected, int $placeId): array
{
    $map = [];
    foreach ($available as $target) {
        if (!is_array($target)) {
            continue;
        }
        $token = (string) ($target['scope'] ?? '') . ':'
            . (string) ($target['source'] ?? '') . ':'
            . (int) ($target['id'] ?? 0);
        $map[$token] = $target;
    }
    if ($selected === null || $selected === []) {
        $selected = ['place:place:' . $placeId];
    }
    if (!is_array($selected) || count($selected) > 500) {
        throw new InvalidArgumentException('Select no more than 500 reporting targets.');
    }
    $targets = [];
    foreach ($selected as $token) {
        if (!is_string($token) || !isset($map[$token])) {
            throw new InvalidArgumentException('A selected Site or Area does not belong to this Place.');
        }
        $targets[$token] = $map[$token];
    }
    if (!$targets) {
        throw new InvalidArgumentException('Select a Place, Area, or Site.');
    }
    if (count($targets) > 1) {
        foreach ($targets as $target) {
            if ((string) $target['scope'] !== 'site') {
                throw new InvalidArgumentException(
                    'Only Sites may be selected together. Edit a Place or Area separately.'
                );
            }
        }
    }
    return $targets;
}

function llama_season_builder_questions(string $scope): array
{
    if (!in_array($scope, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException('Invalid seasonal report target.');
    }
    $groups = llama_season_question_groups($scope);

    // Site reservation method and normal seasonal Site open/closed status
    // belong exclusively to Admin > Campsite Pricing. Place-wide closure
    // remains editable through its Place-season questions.
    foreach ($groups as $name => $fields) {
        unset($fields['site_reservation_method'], $fields['seasonal_booking_method']);
        if ($fields) {
            $groups[$name] = $fields;
        } else {
            unset($groups[$name]);
        }
    }
    $questions = [];
    foreach ($groups as $heading => $fields) {
        foreach ($fields as $key => $field) {
            $type = (string) ($field['type'] ?? 'text');
            $options = [];
            if ($key === LLAMA_SEASON_SITE_CLOSED_KEY) {
                $options = ['1' => 'Yes, normally closed', '0' => 'No, normally open'];
            } elseif (in_array($type, ['tri', 'checkbox'], true)) {
                $options = ['1' => 'Yes', '0' => 'No'];
            } elseif ($type === 'permission') {
                $options = [
                    '1' => 'Yes', '0' => 'No',
                    '2' => 'Permit required', '3' => 'Conditional',
                ];
            } elseif ($type === 'select') {
                $options = (array) ($field['options'] ?? [])
                    + (array) ($field['legacy_values'] ?? []);
            }
            if (!empty($field['allow_unknown'])) {
                $options['__UNKNOWN__'] = 'Unknown';
            }
            $questions[(string) $key] = [
                'key' => (string) $key,
                'label' => rtrim((string) ($field['label'] ?? $key), ' *'),
                'group' => $heading,
                'type' => $type,
                'options' => $options,
            ];
        }
    }
    return $questions;
}

/**
 * Read current seasonal overrides once, and expose mixed values without
 * inventing a common answer or deleting one target's distinct saved value.
 */
function llama_season_builder_existing(
    PDO $db,
    int $placeId,
    int $seasonId,
    array $targets,
    array $questions
): array {
    $byTarget = [];
    foreach ($targets as $token => $_) {
        $byTarget[$token] = [];
    }
    $rows = $db->prepare(
        'SELECT target_scope, target_source, target_id,
                question_key, answer_state, value_json
         FROM place_season_answers
         WHERE place_id = ? AND season_id = ?'
    );
    $rows->execute([$placeId, $seasonId]);
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $token = (string) $row['target_scope'] . ':'
            . (string) $row['target_source'] . ':' . (int) $row['target_id'];
        $key = (string) $row['question_key'];
        if (!isset($byTarget[$token]) || !isset($questions[$key])) {
            continue;
        }
        $byTarget[$token][$key] = (string) $row['answer_state'] === 'unknown'
            ? '__UNKNOWN__'
            : json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR);
    }

    if (isset($questions[LLAMA_SEASON_SITE_CLOSED_KEY])) {
        $statusRows = $db->prepare(
            'SELECT target_source, target_id, operating_status
             FROM place_season_site_status
             WHERE place_id = ? AND season_id = ?'
        );
        $statusRows->execute([$placeId, $seasonId]);
        while ($row = $statusRows->fetch(PDO::FETCH_ASSOC)) {
            $token = 'site:' . $row['target_source'] . ':' . (int) $row['target_id'];
            if (isset($byTarget[$token])) {
                $byTarget[$token][LLAMA_SEASON_SITE_CLOSED_KEY] =
                    (string) $row['operating_status'] === 'closed' ? '1' : '0';
            }
        }
    }

    $rowsByKey = [];
    $count = count($byTarget);
    foreach ($questions as $key => $question) {
        $values = [];
        $present = 0;
        foreach ($byTarget as $saved) {
            if (array_key_exists($key, $saved)) {
                $values[] = $saved[$key];
                $present++;
            }
        }
        if ($present === 0) {
            continue;
        }
        $first = $values[0];
        $mixed = $present !== $count;
        foreach ($values as $value) {
            if ($value !== $first) {
                $mixed = true;
                break;
            }
        }
        $rowsByKey[$key] = [
            'key' => $key,
            'value' => $mixed ? null : $first,
            'mixed' => $mixed,
        ];
    }
    return array_values($rowsByKey);
}

/**
 * Save ONLY explicitly changed or removed keys to selected targets.
 * One transaction, one canonical target verification, and an audit record.
 *
 * A Site's seasonal closure here is a normal operating pattern, never a
 * verified administrative closure or an assertion of current availability.
 */
function llama_season_builder_save(
    PDO $db,
    int $placeId,
    int $seasonId,
    int $actorId,
    mixed $rawTargets,
    mixed $rawChanges,
    mixed $rawRemovals,
    mixed $sourceInput = null
): array {
    if ($placeId < 1 || $seasonId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('A valid Place, season, and Admin are required.');
    }
    if (!is_array($rawChanges) || count($rawChanges) > 100
        || !is_array($rawRemovals) || count($rawRemovals) > 100) {
        throw new InvalidArgumentException('Invalid seasonal change list.');
    }
    $ownTransaction = !$db->inTransaction();
    if ($ownTransaction) {
        $db->beginTransaction();
    }
    try {
        $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
        $lock->execute([$placeId]);
        if (!$lock->fetchColumn()) {
            throw new InvalidArgumentException('Place not found.');
        }
        $season = llama_season_verified_place($db, $placeId, $seasonId, true);
        $targets = llama_season_builder_targets(
            llama_report_targets($db, $placeId), $rawTargets, $placeId
        );
        $scope = (string) reset($targets)['scope'];
        $catalog = llama_season_builder_questions($scope);
        $source = llama_season_source_url($sourceInput)
            ?? llama_season_source_url($season['source_url'] ?? null);
        $changes = [];
        foreach ($rawChanges as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid seasonal change row.');
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || !isset($catalog[$key]) || isset($changes[$key])) {
                throw new InvalidArgumentException('Duplicate or unsupported seasonal question.');
            }
            $raw = $row['value'] ?? null;
            if (!is_string($raw) && !is_int($raw)) {
                throw new InvalidArgumentException('Choose a valid seasonal answer.');
            }
            if ($key === LLAMA_SEASON_SITE_CLOSED_KEY) {
                if (!in_array((string) $raw, ['0', '1'], true)) {
                    throw new InvalidArgumentException('Choose Yes or No for normal seasonal closure.');
                }
                $changes[$key] = [
                    'state' => 'answered',
                    'value' => (string) $raw === '1' ? 'closed' : 'open',
                ];
                continue;
            }
            $field = llama_season_question_for_scope($key, $scope);
            $answer = llama_place_report_submission_answer(
                $field,
                (string) $raw === '__UNKNOWN__'
                    ? llama_place_report_unknown_token()
                    : $raw
            );
            if (empty($answer['recorded'])) {
                throw new InvalidArgumentException('Choose an answer for ' . $catalog[$key]['label']);
            }
            $changes[$key] = [
                'state' => $answer['state'],
                'value' => json_encode(
                    $answer['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ),
            ];
        }
        $removals = [];
        foreach ($rawRemovals as $raw) {
            if (!is_string($raw) || !isset($catalog[$raw]) || isset($changes[$raw])) {
                throw new InvalidArgumentException('Invalid or conflicting seasonal removal.');
            }
            $removals[$raw] = true;
        }
        if (!$changes && !$removals) {
            throw new InvalidArgumentException('Choose at least one seasonal change.');
        }

        $read = $db->prepare(
            'SELECT target_scope, target_source, target_id, question_key,
                    answer_state, value_json
             FROM place_season_answers
             WHERE place_id = ? AND season_id = ? FOR UPDATE'
        );
        $read->execute([$placeId, $seasonId]);
        $existing = [];
        foreach ($read->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $token = (string) $row['target_scope'] . ':'
                . (string) $row['target_source'] . ':' . (int) $row['target_id'];
            $existing[$token][(string) $row['question_key']] = $row;
        }

        $statusRead = $db->prepare(
            'SELECT target_source, target_id, operating_status
             FROM place_season_site_status
             WHERE place_id = ? AND season_id = ? FOR UPDATE'
        );
        $statusRead->execute([$placeId, $seasonId]);
        $statuses = [];
        foreach ($statusRead->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $statuses['site:' . $row['target_source'] . ':' . (int) $row['target_id']] =
                (string) $row['operating_status'];
        }

        $save = $db->prepare(
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
        $saveStatus = $db->prepare(
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
        $deleteStatus = $db->prepare(
            'DELETE FROM place_season_site_status
             WHERE place_id = ? AND season_id = ? AND target_source = ? AND target_id = ?'
        );

        $audit = [];
        foreach ($targets as $token => $target) {
            $scope = (string) $target['scope'];
            $kind = (string) $target['source'];
            $id = (int) $target['id'];
            foreach ($changes as $key => $value) {
                if ($key === LLAMA_SEASON_SITE_CLOSED_KEY) {
                    $old = $statuses[$token] ?? null;
                    $new = $value['value'];
                    if ($old !== $new) {
                        $saveStatus->execute([$placeId, $seasonId, $kind, $id, $new, $source, $actorId]);
                        $audit[$token][$key] = ['before' => $old, 'after' => $new];
                    }
                    continue;
                }
                $old = $existing[$token][$key] ?? null;
                if ($old !== null && (string) $old['answer_state'] === $value['state']
                    && (string) $old['value_json'] === $value['value']) {
                    continue;
                }
                $save->execute([
                    $placeId, $seasonId, $scope, $kind, $id,
                    $key, $value['state'], $value['value'], $source, $actorId,
                ]);
                $audit[$token][$key] = [
                    'before' => $old === null ? null : [
                        'state' => $old['answer_state'], 'value_json' => $old['value_json'],
                    ],
                    'after' => $value,
                ];
            }
            foreach (array_keys($removals) as $key) {
                if ($key === LLAMA_SEASON_SITE_CLOSED_KEY) {
                    if (isset($statuses[$token])) {
                        $deleteStatus->execute([$placeId, $seasonId, $kind, $id]);
                        $audit[$token][$key] = ['before' => $statuses[$token], 'after' => null];
                    }
                    continue;
                }
                $old = $existing[$token][$key] ?? null;
                if ($old !== null) {
                    $delete->execute([$placeId, $seasonId, $scope, $kind, $id, $key]);
                    $audit[$token][$key] = [
                        'before' => [
                            'state' => $old['answer_state'], 'value_json' => $old['value_json'],
                        ],
                        'after' => null,
                    ];
                }
            }
        }
        if ($audit) {
            require_once dirname(__DIR__) . '/admin-users.php';
            admin_users_audit(
                $db, $actorId, null, 'place.season_builder_saved',
                'Updated selected seasonal Scout Report questions.',
                ['place_id' => $placeId, 'season_id' => $seasonId, 'changes' => $audit]
            );
        }
        if ($ownTransaction) {
            $db->commit();
        }
        return ['targets' => count($targets), 'changed' => count($audit)];
    } catch (Throwable $error) {
        if ($ownTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/report-targets.php';
require_once __DIR__ . '/scoped-answers.php';

/** A scoped correction is submitted as a normal moderated update, never published immediately. */
function llama_scoped_update_submit(int $userId, array $place, array $input): int
{
    $db = db();
    $placeId = (int) ($place['id'] ?? 0);
    if ($userId < 1 || $placeId < 1 || !user_has_place_complete_access($placeId, $userId)
        || !llama_contributor_can($db, $userId, 'submit_update')) {
        throw new RuntimeException('You do not have permission to update this Place.');
    }
    if (community_open_update_for_user($userId, $placeId)) {
        throw new RuntimeException('You already have an open update for this Place.');
    }
    $scope = (string) ($input['report_target_scope'] ?? '');
    $source = (string) ($input['report_target_source'] ?? '');
    $id = (int) ($input['report_target_id'] ?? 0);
    $target = llama_report_verified_target($db, $placeId, $scope, $source, $id);
    if ($scope === 'place') {
        throw new InvalidArgumentException('Use the normal Place update form.');
    }
    // A Scout can report several answers about one target in a single review.
    // Accept legacy single-answer submissions while existing pending reviews remain.
    $submitted = [];
    if (!empty($input['scoped_answers_json'])) {
        $submitted = json_decode((string) $input['scoped_answers_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($submitted) || !array_is_list($submitted)) {
            throw new InvalidArgumentException('Invalid Area or Site answers.');
        }
    } elseif (isset($input['scoped_field_key'], $input['scoped_field_value'])) {
        $submitted = [['key'=>$input['scoped_field_key'], 'value'=>$input['scoped_field_value']]];
    }
    if (!$submitted || count($submitted) > 50) {
        throw new InvalidArgumentException('Add between 1 and 50 answers to this report.');
    }
    $before = llama_scoped_report_load($db, $placeId, $scope, $id, $source);
    $answers = [];
    $originalAnswers = [];
    foreach ($submitted as $answer) {
        if (!is_array($answer)) {
            throw new InvalidArgumentException('Invalid Area or Site question.');
        }
        $key = trim((string) ($answer['key'] ?? ''));
        if (isset($answers[$key])) {
            throw new InvalidArgumentException('Each question can only be answered once.');
        }
        $field = llama_scoped_report_field($key, $scope);
        $raw = $answer['value'] ?? null;
        if (!is_scalar($raw) || trim((string) $raw) === '') {
            throw new InvalidArgumentException('Choose an answer for every question.');
        }
        $unknown = llama_place_report_unknown_token();
        if ((string) $raw === $unknown) {
            if (empty($field['allow_unknown'])) {
                throw new InvalidArgumentException('Unknown is not supported for this question.');
            }
            $value = $unknown;
        } elseif ($field['type'] === 'select') {
            if (!array_key_exists((string) $raw, (array) ($field['options'] ?? []))) {
                throw new InvalidArgumentException('Choose one of the allowed answers.');
            }
            $value = (string) $raw;
        } elseif ($field['type'] === 'tri') {
            if (!in_array((string) $raw, ['0','1'], true)) {
                throw new InvalidArgumentException('Choose Yes or No.');
            }
            $value = (string) $raw;
        } elseif ($field['type'] === 'permission') {
            if (!in_array((string) $raw, ['0','1','2','3'], true)) {
                throw new InvalidArgumentException('Choose a valid permission answer.');
            }
            $value = (string) $raw;
        } elseif ($field['type'] === 'rating') {
            if (!in_array((string) $raw, ['1','2','3','4','5'], true)) {
                throw new InvalidArgumentException('Choose a rating from 1 to 5.');
            }
            $value = (string) $raw;
        } elseif (in_array($field['type'], ['textarea','text','url','date','number'], true)) {
            $parsedUnknown = [];
            $value = llama_place_report_parse_field($field, $raw, $parsedUnknown);
            if ($value === null) {
                throw new InvalidArgumentException('Enter a valid answer for ' . $field['label'] . '.');
            }
        } else {
            throw new InvalidArgumentException('This question is not supported for Area or Site corrections.');
        }
        $recorded = array_key_exists($key, $before);
        if ($recorded && $before[$key] === $value) {
            throw new InvalidArgumentException('The answer for ' . $field['label'] . ' is already recorded.');
        }
        $answers[$key] = $value;
        $originalAnswers[$key] = ['recorded'=>$recorded, 'value'=>$recorded ? $before[$key] : null];
    }
    $payload = ['scope'=>$scope, 'source'=>$source, 'id'=>$id, 'answers'=>$answers,
        'target_label'=>(string) $target['label']];
    $original = ['answers'=>$originalAnswers];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO place_update_submissions
            (place_id,user_id,update_type,status,role_at_submission,visited_at,proposed_changes,original_values,photos,contributor_notes)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $visited = trim((string) ($input['visited_at'] ?? ''));
        $stmt->execute([$placeId,$userId,'scoped-update','pending',community_role_at_submission($userId),
            $visited !== '' ? $visited . (strlen($visited)===10?' 00:00:00':'') : null,
            json_encode(['__scoped_report__'=>$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            json_encode(['__scoped_report__'=>$original],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            '[]',trim((string)($input['contributor_notes']??''))]);
        $updateId = (int) $db->lastInsertId();
        llama_place_update_append_history($db,$updateId,['type'=>'submitted','by'=>'contributor',
            'proposed'=>['__scoped_report__'=>$payload],'original'=>['__scoped_report__'=>$original],
            'unknown_fields'=>[],'original_unknown_fields'=>[], 'photo_count'=>0,
            'visited_at'=>$visited,'contributor_notes'=>(string)($input['contributor_notes']??'')]);
        $db->commit();
        return $updateId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/** Called inside the moderator's existing approval transaction. */
function llama_scoped_update_approve(PDO $db, array $update, int $updateId, int $reviewedBy, string $notes): int
{
    $payload = $update['proposed']['__scoped_report__'] ?? null;
    $before = $update['original']['__scoped_report__'] ?? null;
    if (!is_array($payload) || !is_array($before)) {
        throw new RuntimeException('Invalid scoped update payload.');
    }
    $placeId = (int) $update['place_id'];
    $scope = (string) ($payload['scope'] ?? '');
    $source = (string) ($payload['source'] ?? '');
    $targetId = (int) ($payload['id'] ?? 0);
    llama_report_verified_target($db,$placeId,$scope,$source,$targetId);
    $answers = (array) ($payload['answers'] ?? []);
    $oldAnswers = (array) ($before['answers'] ?? []);
    if (!$answers && isset($payload['field_key'])) {
        // Pending submissions from the original single-answer editor.
        $answers = [(string)$payload['field_key'] => $payload['value'] ?? null];
        $oldAnswers = [(string)$payload['field_key'] => $before];
    }
    if (!$answers || count($answers)>50) {
        throw new RuntimeException('Invalid scoped update answers.');
    }
    $current = llama_scoped_report_load($db,$placeId,$scope,$targetId,$source);
    // Validate ALL original values before writing ANY answer.
    foreach ($answers as $key => $value) {
        llama_scoped_report_field((string)$key,$scope);
        $prior = $oldAnswers[$key] ?? null;
        if (!is_array($prior)) {
            throw new RuntimeException('Missing original Area or Site answer state.');
        }
        $recorded = array_key_exists($key,$current);
        if ($recorded !== (bool) ($prior['recorded'] ?? false)
            || ($recorded && $current[$key] !== ($prior['value'] ?? null))) {
            throw new RuntimeException('An Area or Site answer changed since submission. Review the latest information before approving.');
        }
    }
    foreach ($answers as $key => $value) {
        llama_scoped_report_save($db,$placeId,$scope,$targetId,$source,(string)$key,$value);
    }
    // Scoped point accounting is deliberately not guessed from Place-level scoring.
    // Award zero rather than duplicate or incorrectly compute Scout points.
    $points = 0;
    $contributionId = moderation_insert_contribution($db,$placeId,(int)$update['user_id'],null,
        'scoped-update',trim((string)($update['role_at_submission']??'user')),
        !empty($update['visited_at'])?(string)$update['visited_at']:null,
        $reviewedBy,$points,array_map(static fn ($key) => $scope.':'.$source.':'.$targetId.':'.$key, array_keys($answers)),$notes!==''?$notes:null);
    $stmt = $db->prepare('UPDATE place_update_submissions
        SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = CURRENT_TIMESTAMP,
            contribution_id = ?, points_awarded = ? WHERE id = ?');
    $stmt->execute(['approved',$reviewedBy,$notes!==''?$notes:null,$contributionId,0,$updateId]);
    return $contributionId;
}

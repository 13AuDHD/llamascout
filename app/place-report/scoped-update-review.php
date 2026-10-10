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
    $key = trim((string) ($input['scoped_field_key'] ?? ''));
    $field = llama_scoped_report_field($key, $scope);
    $raw = $input['scoped_field_value'] ?? null;
    if (!is_scalar($raw) || trim((string) $raw) === '') {
        throw new InvalidArgumentException('Choose an answer for the selected question.');
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
        if (!in_array((string) $raw, ['0','1','2'], true)) {
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
            throw new InvalidArgumentException('Enter a valid value for this question.');
        }
    } else {
        throw new InvalidArgumentException('This question requires the full form and is not yet supported for scoped corrections.');
    }
    $before = llama_scoped_report_load($db, $placeId, $scope, $id, $source);
    $previous = array_key_exists($key, $before) ? $before[$key] : null;
    if (array_key_exists($key, $before) && $previous === $value) {
        throw new InvalidArgumentException('That answer is already recorded.');
    }
    $payload = ['scope'=>$scope, 'source'=>$source, 'id'=>$id, 'field_key'=>$key, 'value'=>$value,
        'target_label'=>(string) $target['label']];
    $original = ['recorded'=>array_key_exists($key, $before), 'value'=>$previous];
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
    $key = (string) ($payload['field_key'] ?? '');
    llama_report_verified_target($db,$placeId,$scope,$source,$targetId);
    llama_scoped_report_field($key,$scope);
    $current = llama_scoped_report_load($db,$placeId,$scope,$targetId,$source);
    $recorded = array_key_exists($key,$current);
    if ($recorded !== (bool) ($before['recorded'] ?? false)
        || ($recorded && $current[$key] !== ($before['value'] ?? null))) {
        throw new RuntimeException('This Area or Site answer changed since submission. Review the latest information before approving.');
    }
    llama_scoped_report_save($db,$placeId,$scope,$targetId,$source,$key,$payload['value']);
    // Scoped point accounting is deliberately not guessed from Place-level scoring.
    // Award zero rather than duplicate or incorrectly compute Scout points.
    $points = 0;
    $contributionId = moderation_insert_contribution($db,$placeId,(int)$update['user_id'],null,
        'scoped-update',trim((string)($update['role_at_submission']??'user')),
        !empty($update['visited_at'])?(string)$update['visited_at']:null,
        $reviewedBy,$points,[$scope.':'.$source.':'.$targetId.':'.$key],$notes!==''?$notes:null);
    $stmt = $db->prepare('UPDATE place_update_submissions
        SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = CURRENT_TIMESTAMP,
            contribution_id = ?, points_awarded = ? WHERE id = ?');
    $stmt->execute(['approved',$reviewedBy,$notes!==''?$notes:null,$contributionId,0,$updateId]);
    return $contributionId;
}

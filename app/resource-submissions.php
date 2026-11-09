<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-resources.php';

function llama_resource_submissions_ready(PDO $db): bool
{
    try {
        $db->query('SELECT id FROM resource_submissions LIMIT 1');
        return true;
    } catch (PDOException) {
        return false;
    }
}

function llama_resource_review_notes_ready(PDO $db): bool
{
    try {
        return $db->query('SELECT review_notes FROM resource_submissions LIMIT 0') !== false;
    } catch (PDOException) {
        return false;
    }
}

function llama_resource_submission_queue(PDO $db, ?bool $updates = null, int $limit = 0): array
{
    if (!llama_resource_submissions_ready($db)) {
        return [];
    }

    $sql = "SELECT rs.id, rs.name AS place_name, rs.status, rs.target_resource_id,
                   rs.created_at AS submitted_at, u.display_name, u.username,
                   COALESCE(NULLIF(u.display_name, ''), u.username, 'Former Llama Scout Member') AS contributor_name,
                   1 AS is_resource
            FROM resource_submissions rs
            LEFT JOIN users u ON u.id = rs.submitted_by
            WHERE rs.status IN ('pending', 'needs-changes')";
    if ($updates !== null) {
        $sql .= $updates ? ' AND rs.target_resource_id IS NOT NULL' : ' AND rs.target_resource_id IS NULL';
    }
    $sql .= ' ORDER BY rs.created_at ASC, rs.id ASC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function llama_resource_merge_queue(array $places, array $resources): array
{
    $items = array_merge($places, $resources);
    usort($items, static fn (array $a, array $b): int => strcmp($a['submitted_at'], $b['submitted_at']));
    return $items;
}

function llama_resource_submission_input(array $input): array
{
    $fields = array_flip([
        'name', 'resource_type', 'marker_service_type', 'status', 'coordinates',
        'hours_mode', 'fixed_open_hour', 'fixed_close_hour', 'weekly_hours',
        'street_address', 'city', 'county', 'state_region', 'postal_code', 'country_code',
        'phone', 'website_url', 'services', 'notes',
    ]);
    $data = array_intersect_key($input, $fields);
    foreach ($data as $key => $value) {
        if (in_array($key, ['weekly_hours', 'services'], true)) {
            if (!is_array($value)) {
                throw new InvalidArgumentException('Check the hours and services.');
            }
            foreach ($value as $row) {
                if (!is_array($row) || count(array_filter($row, 'is_scalar')) !== count($row)) {
                    throw new InvalidArgumentException('Check the hours and services.');
                }
            }
        } elseif (!is_scalar($value)) {
            throw new InvalidArgumentException('Check the Resource fields.');
        }
    }
    return $data;
}

function llama_resource_active_record(PDO $db, int $resourceId): array
{
    return llama_resources($db, $resourceId)[0] ?? [];
}

function llama_resource_snapshot(array $resource): string
{
    unset($resource['images'], $resource['featured_image']);
    if (($resource['county'] ?? '') === '') {
        unset($resource['county']);
    }
    return hash('sha256', json_encode($resource, JSON_THROW_ON_ERROR));
}

function llama_resource_submit(
    PDO $db,
    int $userId,
    array $input,
    int $resourceId = 0,
    string $baseSnapshot = '',
    int $revisionId = 0
): void
{
    if ($userId < 1 || !llama_resource_submissions_ready($db)) {
        throw new RuntimeException('Resource submissions are currently unavailable.');
    }

    $data = llama_resource_submission_input($input);
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($payload) > 32000) {
        throw new InvalidArgumentException('Shorten the Resource notes and try again.');
    }

    if ($resourceId > 0) {
        $current = llama_resource_active_record($db, $resourceId);
        if (!$current || !hash_equals(llama_resource_snapshot($current), $baseSnapshot)) {
            throw new InvalidArgumentException('The Resource changed. Reopen its current record and try again.');
        }
    }

    $resource = llama_resource_prepare_input($db, $resourceId, $data);
    $identity = $resourceId > 0 ? 'resource:' . $resourceId : 'new:' . $resource['slug'];
    $key = hash('sha256', $userId . ':' . $identity);
    if ($revisionId > 0) {
        if (!llama_resource_review_notes_ready($db)) {
            throw new RuntimeException('Resource revisions are currently unavailable.');
        }
        $target = $resourceId > 0 ? 'target_resource_id = ?' : 'target_resource_id IS NULL';
        $statement = $db->prepare(
            "UPDATE resource_submissions
             SET name = ?, form_data = ?, pending_key = ?, status = 'pending', reviewed_by = NULL, reviewed_at = NULL,
                 review_notes = NULL
             WHERE id = ? AND submitted_by = ? AND status = 'needs-changes'
               AND COALESCE(base_snapshot, '') = ? AND {$target}"
        );
        $values = [$resource['name'], $payload, $key, $revisionId, $userId, $baseSnapshot];
        if ($resourceId > 0) {
            $values[] = $resourceId;
        }
    } else {
        $statement = $db->prepare(
            'INSERT INTO resource_submissions (submitted_by, name, target_resource_id, base_snapshot, pending_key, form_data)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $values = [$userId, $resource['name'], $resourceId ?: null, $resourceId ? $baseSnapshot : null, $key, $payload];
    }
    try {
        $statement->execute($values);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException('You already submitted this Resource for review.', 0, $exception);
        }
        throw $exception;
    }
    if ($revisionId > 0 && $statement->rowCount() !== 1) {
        throw new InvalidArgumentException('This Resource submission is no longer waiting for your changes.');
    }
}

function llama_resource_review(
    PDO $db,
    int $actorId,
    int $submissionId,
    ?array $approvedInput,
    string $notes = '',
    bool $requestChanges = false,
    ?string $expectedForm = null
): int {
    $notes = mb_substr(trim($notes), 0, 4000);
    $notesReady = llama_resource_review_notes_ready($db);
    if ($requestChanges && (!$notesReady || $notes === '' || $approvedInput !== null)) {
        throw new InvalidArgumentException('Add review notes before requesting changes.');
    }
    $db->beginTransaction();
    try {
        $statement = $db->prepare(
            "SELECT submitted_by, target_resource_id, base_snapshot, form_data FROM resource_submissions WHERE id = ? AND status = 'pending' FOR UPDATE"
        );
        $statement->execute([$submissionId]);
        $submission = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            throw new InvalidArgumentException('This Resource submission has already been reviewed or does not exist.');
        }

        if ($expectedForm !== null && !hash_equals(hash('sha256', $submission['form_data']), $expectedForm)) {
            throw new InvalidArgumentException('The contributor revised this submission. Reload it before reviewing.');
        }

        $targetId = (int) $submission['target_resource_id'];
        $resourceId = null;
        if ($approvedInput !== null) {
            if ($targetId > 0) {
                $lock = $db->prepare('SELECT id FROM resources WHERE id = ? FOR UPDATE');
                $lock->execute([$targetId]);
                $current = llama_resource_active_record($db, $targetId);
                if (!$current || !hash_equals((string) $submission['base_snapshot'], llama_resource_snapshot($current))) {
                    throw new InvalidArgumentException('The Resource changed since submission. Review the current record before making any edits.');
                }
            }
            $resourceId = admin_resource_save($db, $actorId, $targetId, $approvedInput);
            if ($targetId === 0) {
                $author = $db->prepare('UPDATE resources SET created_by = ? WHERE id = ?');
                $author->execute([(int) $submission['submitted_by'], $resourceId]);
            }
        }
        $status = $requestChanges ? 'needs-changes' : ($resourceId === null ? 'rejected' : 'approved');
        $notesSql = $notesReady ? ', review_notes = ?' : '';
        $pendingSql = $requestChanges ? '' : ', pending_key = NULL';
        $review = $db->prepare(
            "UPDATE resource_submissions
             SET status = ?, resource_id = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP{$pendingSql}{$notesSql}
             WHERE id = ?"
        );
        $values = [$status, $resourceId, $actorId];
        if ($notesReady) {
            $values[] = $notes;
        }
        $values[] = $submissionId;
        $review->execute($values);
        $db->commit();
        return $resourceId ?? 0;
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}

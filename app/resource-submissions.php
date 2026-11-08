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

function llama_resource_submission_input(array $input): array
{
    $fields = array_flip([
        'name', 'resource_type', 'marker_service_type', 'status', 'coordinates',
        'hours_mode', 'fixed_open_hour', 'fixed_close_hour', 'weekly_hours',
        'street_address', 'city', 'state_region', 'postal_code', 'country_code',
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
    return hash('sha256', json_encode($resource, JSON_THROW_ON_ERROR));
}

function llama_resource_submit(
    PDO $db,
    int $userId,
    array $input,
    int $resourceId = 0,
    string $baseSnapshot = ''
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
    $statement = $db->prepare(
        'INSERT INTO resource_submissions (submitted_by, name, target_resource_id, base_snapshot, pending_key, form_data)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    try {
        $statement->execute([
            $userId, $resource['name'], $resourceId ?: null, $resourceId ? $baseSnapshot : null, $key, $payload,
        ]);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException('You already submitted this Resource for review.', 0, $exception);
        }
        throw $exception;
    }
}

function llama_resource_review(PDO $db, int $actorId, int $submissionId, ?array $approvedInput): int
{
    $db->beginTransaction();
    try {
        $statement = $db->prepare(
            "SELECT submitted_by, target_resource_id, base_snapshot FROM resource_submissions WHERE id = ? AND status = 'pending' FOR UPDATE"
        );
        $statement->execute([$submissionId]);
        $submission = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            throw new InvalidArgumentException('This Resource submission has already been reviewed or does not exist.');
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
        $review = $db->prepare(
            'UPDATE resource_submissions
             SET status = ?, resource_id = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, pending_key = NULL
             WHERE id = ?'
        );
        $review->execute([$resourceId === null ? 'rejected' : 'approved', $resourceId, $actorId, $submissionId]);
        $db->commit();
        return $resourceId ?? 0;
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}

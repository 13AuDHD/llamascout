<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');

function admin_todo_json(array $payload, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function admin_todo_request_data(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);

        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

try {
    $adminUser = moderation_require_admin();
    $db = db();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        admin_todo_json(
            ['ok' => false, 'error' => 'POST required.'],
            405
        );
    }

    $data = admin_todo_request_data();

    if (!moderation_verify_csrf((string) ($data['csrf_token'] ?? ''))) {
        admin_todo_json(
            [
                'ok' => false,
                'error' => 'Your session token expired. Reload and try again.',
            ],
            403
        );
    }

    $action = trim((string) ($data['action'] ?? ''));
    $actorUserId = (int) ($adminUser['id'] ?? 0);

    if ($action === 'add') {
        $task = trim((string) ($data['task'] ?? ''));

        if ($task === '') {
            throw new InvalidArgumentException('Enter a task first.');
        }

        if (mb_strlen($task) > 500) {
            throw new InvalidArgumentException('Tasks can be up to 500 characters.');
        }

        $stmt = $db->prepare(
            "INSERT INTO admin_todos (
                task,
                is_completed,
                created_by,
                created_at,
                updated_at
             ) VALUES (?, 0, ?, NOW(), NOW())"
        );

        $stmt->execute([
            $task,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $id = (int) $db->lastInsertId();

        admin_todo_json([
            'ok' => true,
            'action' => 'add',
            'task' => [
                'id' => $id,
                'task' => $task,
                'is_completed' => false,
                'date_label' => 'Added ' . date('M j'),
            ],
        ]);
    }

    $id = (int) ($data['id'] ?? 0);

    if ($id < 1) {
        throw new InvalidArgumentException('Task not found.');
    }

    if ($action === 'toggle') {
        $stmt = $db->prepare(
            "SELECT is_completed
             FROM admin_todos
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);

        $current = $stmt->fetchColumn();

        if ($current === false) {
            throw new RuntimeException('Task not found.');
        }

        $completed = (int) $current === 1 ? 0 : 1;

        $stmt = $db->prepare(
            "UPDATE admin_todos
             SET
                is_completed = ?,
                completed_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END,
                updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$completed, $completed, $id]);

        admin_todo_json([
            'ok' => true,
            'action' => 'toggle',
            'id' => $id,
            'is_completed' => $completed === 1,
            'date_label' => ($completed === 1 ? 'Completed ' : 'Added ') . date('M j'),
        ]);
    }

    if ($action === 'edit') {
        $task = trim((string) ($data['task'] ?? ''));

        if ($task === '') {
            throw new InvalidArgumentException('Task cannot be blank.');
        }

        if (mb_strlen($task) > 500) {
            throw new InvalidArgumentException('Tasks can be up to 500 characters.');
        }

        $stmt = $db->prepare(
            "UPDATE admin_todos
             SET task = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$task, $id]);

        if ($stmt->rowCount() < 1) {
            $exists = $db->prepare(
                "SELECT 1 FROM admin_todos WHERE id = ? LIMIT 1"
            );
            $exists->execute([$id]);

            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Task not found.');
            }
        }

        admin_todo_json([
            'ok' => true,
            'action' => 'edit',
            'id' => $id,
            'task' => $task,
        ]);
    }

    if ($action === 'delete') {
        $stmt = $db->prepare(
            "DELETE FROM admin_todos WHERE id = ?"
        );
        $stmt->execute([$id]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Task not found.');
        }

        admin_todo_json([
            'ok' => true,
            'action' => 'delete',
            'id' => $id,
        ]);
    }

    throw new InvalidArgumentException('Unknown To-Do action.');

} catch (InvalidArgumentException $exception) {
    admin_todo_json(
        ['ok' => false, 'error' => $exception->getMessage()],
        422
    );
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'admin.todo_action'
    );

    admin_todo_json(
        [
            'ok' => false,
            'error' => llama_error_message_with_reference(
                'The Site To-Do list could not be updated.',
                $reference
            ),
        ],
        500
    );
}

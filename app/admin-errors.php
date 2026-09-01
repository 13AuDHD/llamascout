<?php

declare(strict_types=1);

function admin_errors_search(PDO $db, array $input, int $perPage = 50): array
{
    llama_error_ensure_table($db);

    $q = trim((string) ($input['q'] ?? ''));
    $severity = strtolower(trim((string) ($input['severity'] ?? '')));
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $userId = max(0, (int) ($input['user_id'] ?? 0));
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(10, min(100, $perPage));

    if (!in_array($severity, ['', 'error', 'fatal'], true)) {
        $severity = '';
    }

    if (!in_array($status, ['', 'open', 'resolved'], true)) {
        $status = '';
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(reference_code LIKE ? OR action LIKE ? OR request_path LIKE ? OR message LIKE ? OR exception_class LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($severity !== '') {
        $where[] = 'severity = ?';
        $params[] = $severity;
    }

    if ($status !== '') {
        $where[] = 'resolution_status = ?';
        $params[] = $status;
    }

    if ($userId > 0) {
        $where[] = 'user_id = ?';
        $params[] = $userId;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare('SELECT COUNT(*) FROM application_errors' . $whereSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT ae.*, u.username, u.display_name
            FROM application_errors ae
            LEFT JOIN users u ON u.id = ae.user_id'
        . $whereSql .
        ' ORDER BY ae.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return [
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'filters' => [
            'q' => $q,
            'severity' => $severity,
            'status' => $status,
            'user_id' => $userId,
        ],
    ];
}

function admin_errors_context(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_errors_recent_count(PDO $db, int $hours = 24): int
{
    llama_error_ensure_table($db);
    $hours = max(1, min(168, $hours));

    $stmt = $db->query(
        'SELECT COUNT(*) FROM application_errors
         WHERE created_at >= (UTC_TIMESTAMP() - INTERVAL ' . $hours . ' HOUR)'
    );

    return (int) $stmt->fetchColumn();
}

function admin_errors_set_resolution(
    PDO $db,
    int $errorId,
    int $actorUserId,
    string $status
): void {
    llama_error_ensure_table($db);

    if ($errorId <= 0 || $actorUserId <= 0) {
        throw new InvalidArgumentException('Invalid error or administrator.');
    }

    if (!in_array($status, ['open', 'resolved'], true)) {
        throw new InvalidArgumentException('Invalid error resolution status.');
    }

    $stmt = $db->prepare(
        'SELECT id, reference_code, resolution_status
         FROM application_errors
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$errorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Error record not found.');
    }

    if ($status === 'resolved') {
        $update = $db->prepare(
            'UPDATE application_errors
             SET resolution_status = "resolved",
                 resolved_at = UTC_TIMESTAMP(),
                 resolved_by = ?
             WHERE id = ?'
        );
        $update->execute([$actorUserId, $errorId]);
    } else {
        $update = $db->prepare(
            'UPDATE application_errors
             SET resolution_status = "open",
                 resolved_at = NULL,
                 resolved_by = NULL
             WHERE id = ?'
        );
        $update->execute([$errorId]);
    }

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            $status === 'resolved' ? 'error_resolved' : 'error_reopened',
            ($status === 'resolved' ? 'Resolved ' : 'Reopened ') .
                (string) $row['reference_code'] . '.',
            [
                'error_id' => $errorId,
                'reference_code' => (string) $row['reference_code'],
            ]
        );
    }
}

function admin_errors_create_test(PDO $db, int $actorUserId): string
{
    if ($actorUserId <= 0) {
        throw new InvalidArgumentException('Invalid administrator.');
    }

    $exception = new RuntimeException('Admin diagnostic test error. No user action failed.');
    $reference = llama_log_exception(
        $exception,
        'admin.errors.self_test',
        [
            'diagnostic_test' => true,
            'actor_user_id' => $actorUserId,
        ],
        'error'
    );

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'error_log_self_test',
            'Generated diagnostic error-log self-test ' . $reference . '.',
            [
                'reference_code' => $reference,
            ]
        );
    }

    return $reference;
}

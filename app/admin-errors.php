<?php

declare(strict_types=1);

function admin_errors_search(PDO $db, array $input, int $perPage = 50): array
{
    llama_error_ensure_table($db);

    $q = trim((string) ($input['q'] ?? ''));
    $severity = strtolower(trim((string) ($input['severity'] ?? '')));
    $userId = max(0, (int) ($input['user_id'] ?? 0));
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = max(10, min(100, $perPage));

    if (!in_array($severity, ['', 'error', 'fatal'], true)) {
        $severity = '';
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

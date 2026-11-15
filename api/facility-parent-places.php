<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId < 1 || !llama_contributor_can(db(), $userId, 'submit_place')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'results' => [], 'error' => 'Sign in to search Places.']);
    exit;
}

$query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
if (mb_strlen($query) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

try {
    $db = db();
    $visible = llama_place_visibility_sql('p', $userId);
    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.city, p.state, p.type
         FROM places p
         WHERE p.status IN ("active", "featured")
           AND ' . $visible . '
           AND (p.name LIKE ? OR CAST(p.id AS CHAR) = ?)
         ORDER BY CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END,
                  p.name ASC, p.id ASC
         LIMIT 20'
    );
    $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    $stmt->execute(['%' . $prefix . '%', $query, $prefix . '%']);
    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'location' => implode(', ', array_filter([
                (string) ($row['city'] ?? ''),
                (string) ($row['state'] ?? ''),
            ])),
        ];
    }
    echo json_encode(['ok' => true, 'results' => $results],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'api.facility_parent_places');
    http_response_code(500);
    echo json_encode(['ok' => false, 'results' => [],
        'error' => 'Place search is temporarily unavailable.']);
}

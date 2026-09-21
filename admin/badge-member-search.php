<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

moderation_require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$db = db();

$badgeId = (int) ($_GET['badge_id'] ?? 0);
$rawQuery = trim((string) ($_GET['q'] ?? ''));

if ($badgeId < 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Badge ID is required.',
        'results' => [],
    ]);
    exit;
}

$query = mb_substr($rawQuery, 0, 120);
$matchQuery = str_starts_with($query, '@')
    ? substr($query, 1)
    : $query;
$matchQuery = trim($matchQuery);

if ($matchQuery === '') {
    echo json_encode([
        'ok' => true,
        'results' => [],
    ]);
    exit;
}

if (!ctype_digit($matchQuery) && mb_strlen($matchQuery) < 2) {
    echo json_encode([
        'ok' => true,
        'results' => [],
        'minimum_not_met' => true,
    ]);
    exit;
}

$badgeStmt = $db->prepare(
    'SELECT *
     FROM badge_definitions
     WHERE id = ?
     LIMIT 1'
);
$badgeStmt->execute([$badgeId]);
$badge = $badgeStmt->fetch(PDO::FETCH_ASSOC);

if (!$badge) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Badge not found.',
        'results' => [],
    ]);
    exit;
}

$contains = '%' . $matchQuery . '%';
$prefix = $matchQuery . '%';

$stmt = $db->prepare(
    'SELECT
        u.id,
        u.display_name,
        u.username,
        u.email,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM user_badges ub
                WHERE ub.user_id = u.id
                  AND ub.badge_id = ?
                  AND ub.review_status = "earned"
            ) THEN 1
            ELSE 0
        END AS already_has_badge
     FROM users u
     WHERE u.anonymized_at IS NULL
       AND u.status <> "disabled"
       AND (
            CAST(u.id AS CHAR) = ?
            OR u.username LIKE ?
            OR u.email LIKE ?
            OR u.display_name LIKE ?
       )
     ORDER BY
        CASE
            WHEN CAST(u.id AS CHAR) = ? THEN 0
            WHEN u.username = ? THEN 1
            WHEN u.email = ? THEN 2
            WHEN u.username LIKE ? THEN 3
            WHEN u.email LIKE ? THEN 4
            WHEN u.display_name LIKE ? THEN 5
            ELSE 6
        END,
        COALESCE(
            NULLIF(u.display_name, ""),
            NULLIF(u.username, ""),
            u.email
        ) ASC,
        u.id ASC
     LIMIT 12'
);

$stmt->execute([
    $badgeId,
    $matchQuery,
    $contains,
    $contains,
    $contains,
    $matchQuery,
    $matchQuery,
    $matchQuery,
    $prefix,
    $prefix,
    $prefix,
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$results = [];

foreach ($rows as $row) {
    $eligible = llama_badge_user_eligible_for_manual_award(
        $db,
        (int) $row['id'],
        $badge
    );

    $results[] = [
        'id' => (int) $row['id'],
        'display_name' => trim((string) ($row['display_name'] ?? '')),
        'username' => trim((string) ($row['username'] ?? '')),
        'email' => trim((string) ($row['email'] ?? '')),
        'already_has_badge' => (int) ($row['already_has_badge'] ?? 0) === 1,
        'eligible' => $eligible,
        'eligibility_label' => llama_badge_scope_label(
            $badge['eligibility_scope'] ?? LLAMA_BADGE_SCOPE_ALL_MEMBERS
        ),
    ];
}

echo json_encode(
    [
        'ok' => true,
        'results' => $results,
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/compare-places.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');

$user = current_user();
$userId = is_array($user)
    ? (int) ($user['id'] ?? 0)
    : 0;

if ($userId < 1) {
    http_response_code(401);

    echo json_encode([
        'ok' => false,
        'error' => 'Sign in to search Places.',
        'results' => [],
    ]);

    exit;
}

if (!user_has_member_access($userId)) {
    http_response_code(403);

    echo json_encode([
        'ok' => false,
        'error' => 'Complete Access is required to use comparison search.',
        'results' => [],
    ]);

    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));

if ($query === '') {
    echo json_encode([
        'ok' => true,
        'query' => '',
        'results' => [],
    ]);

    exit;
}

if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 100);
} else {
    $query = substr($query, 0, 100);
}

try {
    $contains = '%' . $query . '%';
    $prefix = $query . '%';

    /*
     * This is intentionally server-backed rather than loading every Place
     * into the page. The UI can keep the same live-search behavior at 1,500
     * or 15,000 Places without building thousands of hidden DOM nodes first.
     */
    $stmt = db()->prepare(
        'SELECT
            p.id,
            p.slug,
            p.name,
            p.type,
            p.city,
            p.county,
            p.state,
            p.elevation_feet
         FROM places p
         WHERE p.status IN ("active", "featured")
           AND (
                p.name LIKE ?
                OR p.slug LIKE ?
                OR p.type LIKE ?
                OR p.city LIKE ?
                OR p.county LIKE ?
                OR p.state LIKE ?
                OR p.road LIKE ?
                OR p.land_manager LIKE ?
                OR CAST(p.id AS CHAR) LIKE ?
           )
         ORDER BY
            CASE
                WHEN p.name LIKE ? THEN 0
                WHEN p.city LIKE ? THEN 1
                ELSE 2
            END,
            p.name ASC
         LIMIT 24'
    );

    $stmt->execute([
        $contains,
        $contains,
        $contains,
        $contains,
        $contains,
        $contains,
        $contains,
        $contains,
        $contains,
        $prefix,
        $prefix,
    ]);

    $results = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $location = implode(
            ', ',
            array_filter([
                trim((string) ($row['city'] ?? '')),
                trim((string) ($row['state'] ?? '')),
            ])
        );

        $meta = trim(
            implode(
                ' · ',
                array_filter([
                    llama_compare_label($row['type'] ?? ''),
                    $location,
                    is_numeric($row['elevation_feet'] ?? null)
                        ? number_format((float) $row['elevation_feet']) . ' ft'
                        : '',
                ])
            )
        );

        $results[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? 'Unnamed Place'),
            'type' => (string) ($row['type'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'meta' => $meta,
        ];
    }

    echo json_encode(
        [
            'ok' => true,
            'query' => $query,
            'results' => $results,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'api.compare_place_search'
    );

    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'error' => llama_error_message_with_reference(
                'Place search is temporarily unavailable.',
                $reference
            ),
            'results' => [],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

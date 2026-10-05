<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: private, no-store, max-age=0'
);

$adminUser =
    moderation_require_admin();

$query =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

if ($query === '') {
    echo json_encode(
        [
            'ok' => true,
            'query' => '',
            'results' => [],
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (function_exists('mb_substr')) {
    $query =
        mb_substr(
            $query,
            0,
            100
        );
} else {
    $query =
        substr(
            $query,
            0,
            100
        );
}

try {
    $contains =
        '%'
        . $query
        . '%';

    $prefix =
        $query
        . '%';

    $stmt =
        db()->prepare(
            'SELECT
                p.id,
                p.name,
                p.slug,
                p.type,
                p.status,
                p.city,
                p.state
             FROM places p
             WHERE p.status NOT IN (
                "removed",
                "archived"
             )
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
                    WHEN p.name LIKE ?
                    THEN 0
                    WHEN p.city LIKE ?
                    THEN 1
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

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $location =
            implode(
                ', ',
                array_filter(
                    [
                        trim(
                            (string) (
                                $row['city']
                                ?? ''
                            )
                        ),
                        trim(
                            (string) (
                                $row['state']
                                ?? ''
                            )
                        ),
                    ]
                )
            );

        $meta =
            implode(
                ' · ',
                array_filter(
                    [
                        trim(
                            (string) (
                                $row['type']
                                ?? ''
                            )
                        ),
                        $location,
                        ucfirst(
                            trim(
                                (string) (
                                    $row['status']
                                    ?? ''
                                )
                            )
                        ),
                        '#'
                        . (int) $row['id'],
                    ]
                )
            );

        $results[] = [
            'id' =>
                (int) $row['id'],
            'name' =>
                (string) (
                    $row['name']
                    ?? 'Unnamed Place'
                ),
            'slug' =>
                (string) (
                    $row['slug']
                    ?? ''
                ),
            'meta' =>
                $meta,
        ];
    }

    echo json_encode(
        [
            'ok' => true,
            'query' => $query,
            'results' => $results,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.partner_place_search'
        );

    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'error' =>
                llama_error_message_with_reference(
                    'Place search is temporarily unavailable.',
                    $reference
                ),
            'results' => [],
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
}

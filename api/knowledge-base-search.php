<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/knowledge-base.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$query = trim((string) ($_GET['q'] ?? ''));

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode(
        [
            'ok' => true,
            'query' => $query,
            'results' => [],
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    $rows =
        llama_kb_public_search(
            db(),
            $query,
            12
        );

    $results = [];

    foreach ($rows as $row) {
        $results[] = [
            'title' =>
                (string) ($row['title'] ?? ''),
            'slug' =>
                (string) ($row['slug'] ?? ''),
            'summary' =>
                (string) ($row['summary'] ?? ''),
            'category_name' =>
                (string) ($row['category_name'] ?? ''),
            'category_slug' =>
                (string) ($row['category_slug'] ?? ''),
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
    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'query' => $query,
            'results' => [],
            'message' =>
                'Knowledge Base search is temporarily unavailable.',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
}

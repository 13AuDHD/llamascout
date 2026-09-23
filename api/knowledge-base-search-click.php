<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/knowledge-base.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode(
        [
            'ok' => false,
            'message' => 'POST required.',
        ]
    );
    exit;
}

$searchId =
    max(
        0,
        (int) ($_POST['search_id'] ?? 0)
    );

$articleId =
    max(
        0,
        (int) ($_POST['article_id'] ?? 0)
    );

try {
    $recorded =
        llama_kb_public_mark_search_click(
            db(),
            $searchId,
            $articleId
        );

    echo json_encode(
        [
            'ok' => true,
            'recorded' => $recorded,
        ],
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    /*
     * Click analytics must never interfere with navigation.
     */
    echo json_encode(
        [
            'ok' => true,
            'recorded' => false,
        ],
        JSON_UNESCAPED_SLASHES
    );
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/operating-closures.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

function closure_reports_response(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    !== 'GET'
) {
    closure_reports_response(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
        ],
        405
    );
}

moderation_require_admin();

try {
    $items =
        llama_closure_reported_review_list(
            db()
        );

    closure_reports_response([
        'ok' => true,
        'count' => count($items),
        'items' => $items,
    ]);
} catch (PDOException $exception) {
    closure_reports_response(
        [
            'ok' => false,
            'error' =>
                'Reported operating statuses could not be loaded. '
                . 'Make sure the Place operating fields have been installed.',
        ],
        500
    );
}

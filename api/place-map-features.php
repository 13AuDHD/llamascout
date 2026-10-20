<?php

declare(strict_types=1);

/**
 * Public map feature reader and administrator-only legacy map writer.
 *
 * Scouts propose polygons via /api/place-map-proposals.php, where they
 * wait for moderation. The existing map feature implementation is kept
 * unchanged in app/place-map-features-endpoint.php, a web-denied folder.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $account = current_user();
    $userId = (int) ($account['id'] ?? 0);

    if (
        $userId < 1
        || (!user_is_admin($userId) && !user_is_owner($userId))
    ) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Published map changes require admin review. Submit a mapping proposal instead.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$implementation = dirname(__DIR__) . '/app/place-map-features-endpoint.php';

if (!is_file($implementation)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'The map service is not fully installed. Contact the administrator.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require $implementation;

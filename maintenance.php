<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$db = db();

$state =
    llama_maintenance_state($db);

$preview =
    ((string) ($_GET['preview'] ?? '')) === '1';

if ($preview) {
    $user = current_user();

    if (!$user) {
        header(
            'Location: https://account.llamascout.com/login.php',
            true,
            302
        );
        exit;
    }

    $userId =
        (int) ($user['id'] ?? 0);

    if (
        !user_has_role('owner', $userId)
        && !user_has_role('admin', $userId)
    ) {
        http_response_code(403);
        exit('Not authorized.');
    }

    llama_render_maintenance(
        $db,
        true
    );
}

if (!$state['enabled']) {
    header(
        'Location: /',
        true,
        302
    );
    exit;
}

llama_render_maintenance($db);

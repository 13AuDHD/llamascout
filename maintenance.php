<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$db = db();

$state =
    llama_maintenance_state(
        $db
    );

$preview =
    ((string) ($_GET['preview'] ?? '')) === '1';


/* =========================================================
   OWNER / ADMIN PREVIEW
   ========================================================= */

if ($preview) {
    $user =
        current_user();

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
        !user_has_role(
            'owner',
            $userId
        )
        &&
        !user_has_role(
            'admin',
            $userId
        )
    ) {
        http_response_code(403);

        exit(
            'Not authorized.'
        );
    }

    /*
     * Preview is a terminal response.
     *
     * Do not continue into the normal maintenance-state branch
     * after rendering. Without this exit, a preview can render
     * twice when maintenance is enabled or attempt a redirect
     * after output when maintenance is disabled.
     */
    llama_render_maintenance(
        $db,
        true
    );

    exit;
}


/* =========================================================
   NORMAL MAINTENANCE PAGE
   ========================================================= */

if (!$state['enabled']) {
    header(
        'Location: /',
        true,
        302
    );

    exit;
}

llama_render_maintenance(
    $db
);

exit;

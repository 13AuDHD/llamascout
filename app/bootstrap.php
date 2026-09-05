<?php

declare(strict_types=1);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/error-logging.php';

llama_error_register_handlers();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/maintenance-mode.php';
require_once __DIR__ . '/places.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/weather.php';
require_once __DIR__ . '/saved-places.php';
require_once __DIR__ . '/photo-upload.php';
require_once __DIR__ . '/photo-staging.php';
require_once __DIR__ . '/community-profiles.php';
require_once __DIR__ . '/contributor-attribution.php';
require_once __DIR__ . '/profile-images.php';
require_once __DIR__ . '/shop-images.php';
require_once __DIR__ . '/place-reports.php';
require_once __DIR__ . '/community-contributions.php';
require_once __DIR__ . '/moderation.php';

start_llama_session();

/*
 * Opportunistic promotion-email maintenance.
 *
 * Only authenticated activity can advance the mail queue.
 * The worker has its own database throttle and connection lock,
 * and sends only a tiny batch per maintenance pass.
 *
 * Failure must never interfere with the member's request.
 */
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/promotion-campaigns.php';

        llama_run_promotion_email_maintenance(
            db(),
            2
        );

        require_once __DIR__ . '/promotion-codes.php';

        llama_sync_membership_promotion_codes(
            db()
        );
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout promotion email maintenance error: '
            . $exception->getMessage()
        );
    }
}

llama_enforce_maintenance(
    db()
);

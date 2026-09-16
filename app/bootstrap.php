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
require_once __DIR__ . '/icons.php';

llama_error_register_handlers();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/session-invalidation.php';
require_once __DIR__ . '/support-code.php';
require_once __DIR__ . '/passkeys.php';
require_once __DIR__ . '/passkey-library.php';
require_once __DIR__ . '/timezone.php';
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
require_once __DIR__ . '/shop-order-mail-maintenance.php';
require_once __DIR__ . '/promotion-code-maintenance.php';
require_once __DIR__ . '/membership-email-maintenance.php';
require_once __DIR__ . '/email-verification-guard.php';
require_once __DIR__ . '/external-lookup-protection.php';

start_llama_session();

llama_enforce_session_invalidation(db());
llama_enforce_verified_email_session(db());

/*
 * LS-020 / LS-021:
 * Expensive server-side lookup proxies are authenticated and throttled
 * before they can contact third-party services.
 */
llama_protect_external_lookup_request();

$runMaintenance = static function (
    string $worker,
    callable $callback
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'maintenance.opportunistic.' . $worker,
            [
                'worker' =>
                    $worker,
            ]
        );
    }
};

$runMaintenance(
    'promotion_email',
    static function (): void {
        require_once __DIR__ . '/promotion-campaigns.php';
        llama_run_promotion_email_maintenance(db(), 2);
    }
);

$runMaintenance(
    'newsletter',
    static function (): void {
        require_once __DIR__ . '/newsletters.php';
        llama_run_newsletter_maintenance(db(), 2);
    }
);

$runMaintenance(
    'support_email',
    static function (): void {
        require_once __DIR__ . '/support.php';
        llama_run_support_email_maintenance(db(), 10);
    }
);

$runMaintenance(
    'membership_email',
    static function (): void {
        llama_run_membership_email_maintenance(db(), 10);
    }
);

$runMaintenance(
    'promotion_code',
    static function (): void {
        llama_run_promotion_code_maintenance(db(), 300);
    }
);

$runMaintenance(
    'shipment_email',
    static function (): void {
        shop_run_shipment_email_maintenance(db(), 5);
    }
);

$runMaintenance(
    'shop_cleanup',
    static function (): void {
        require_once __DIR__ . '/shop-maintenance.php';
        shop_run_checkout_cleanup_maintenance(db(), 50, 300);
    }
);

$runMaintenance(
    'scout_renewal',
    static function (): void {
        require_once __DIR__ . '/scout-maintenance.php';
        llama_run_scout_renewal_maintenance(db());
    }
);

llama_enforce_maintenance(db());

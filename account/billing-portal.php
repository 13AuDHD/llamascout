<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

/*
 * Legacy endpoint kept for old bookmarks and links.
 * Billing now starts from Llama Scout's native dashboard;
 * Stripe-hosted flows are launched only for focused actions.
 */
header('Location: /billing.php', true, 303);
exit;

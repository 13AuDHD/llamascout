<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

start_llama_session();

/*
 * Legacy root login endpoint.
 *
 * All authentication is centralized on account.llamascout.com so
 * Turnstile, MFA, Remember Me, account-status checks, and future
 * authentication policy cannot be bypassed through an older form.
 */

$returnUrl = llama_safe_return_url(
    $_POST['return']
    ?? $_GET['return']
    ?? null
);

$destination = 'https://account.llamascout.com/login.php';

if ($returnUrl !== null) {
    $destination .= '?return=' . rawurlencode($returnUrl);
}

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

header(
    'Location: ' . $destination,
    true,
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ? 303
        : 302
);

exit;

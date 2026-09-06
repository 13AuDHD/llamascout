<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

/*
 * Use the authoritative logout helper so both the session cookie
 * and persistent Remember Me authentication are cleared.
 */

logout_user();

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

header(
    'Location: https://account.llamascout.com/login.php',
    true,
    303
);

exit;

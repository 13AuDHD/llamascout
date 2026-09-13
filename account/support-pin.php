<?php

declare(strict_types=1);

/*
 * Support PIN management moved into Account Information.
 * Keep this route so existing bookmarks and old links do not dead-end.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

header(
    'Location: /account-information.php#support-pin',
    true,
    302
);

exit;

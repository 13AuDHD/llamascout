<?php

declare(strict_types=1);

/*
 * Stable maintenance include.
 *
 * Existing callers can keep requiring this file. The large
 * implementation has been split into focused partials.
 */

require_once
    __DIR__
    . '/shop-order-mail.php';

require_once
    __DIR__
    . '/shop-order-mail/fulfillment.php';

require_once
    __DIR__
    . '/shop-order-mail/refund.php';

require_once
    __DIR__
    . '/shop-order-mail/maintenance.php';

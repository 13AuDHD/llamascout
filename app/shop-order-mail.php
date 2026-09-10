<?php

declare(strict_types=1);

/*
 * Public Shop order-mail API.
 *
 * Keep this file as the stable include path used by checkout
 * and Stripe/webhook code. Implementation lives in small
 * focused partials under app/shop-order-mail/.
 */

require_once __DIR__ . '/mail.php';

require_once
    __DIR__
    . '/shop-order-mail/common.php';

require_once
    __DIR__
    . '/shop-order-mail/order-confirmation.php';

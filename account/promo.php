<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';

start_llama_session();

$db = db();

$code = strtoupper(
    trim(
        (string) (
            $_GET['code']
            ?? ''
        )
    )
);

$plan = strtolower(
    trim(
        (string) (
            $_GET['plan']
            ?? ''
        )
    )
);

if (!in_array($plan, ['monthly', 'annual'], true)) {
    $plan = '';
}

$promotionCode = llama_membership_promotion_code_by_code(
    $db,
    $code,
    $plan !== '' ? $plan : null
);

if ($promotionCode) {
    $_SESSION['pending_membership_promo_code'] =
        (string) $promotionCode['code'];
} else {
    unset(
        $_SESSION['pending_membership_promo_code']
    );
}

$destination = '/membership.php';

if ($plan !== '') {
    $destination .= '?plan=' . rawurlencode($plan);
}

header(
    'Location: ' . $destination,
    true,
    303
);

exit;

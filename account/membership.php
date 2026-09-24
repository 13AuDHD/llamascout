<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';

start_llama_session();

$db = db();


/* =========================================================
   PLAN SELECTION
   ========================================================= */

$plan = strtolower(
    trim(
        (string) (
            $_GET['plan']
            ?? $_SESSION['pending_membership_plan']
            ?? ''
        )
    )
);

if (!in_array($plan, ['monthly', 'annual'], true)) {
    $plan = '';
}

if ($plan !== '') {
    $_SESSION['pending_membership_plan'] = $plan;
}


/* =========================================================
   MEMBERSHIP OFFERS
   ========================================================= */

$offers = llama_membership_offers($db);

$monthlyOffer = $offers['monthly'] ?? null;
$annualOffer = $offers['annual'] ?? null;


/* =========================================================
   PROMOTION CODE
   ========================================================= */

$incomingPromotionCode = strtoupper(
    trim(
        (string) (
            $_GET['promo']
            ?? $_GET['code']
            ?? ''
        )
    )
);

$pendingPromotionCode = $incomingPromotionCode !== ''
    ? $incomingPromotionCode
    : strtoupper(
        trim(
            (string) (
                $_SESSION['pending_membership_promo_code']
                ?? ''
            )
        )
    );

$promotionCodesByInterval = [
    'monthly' => null,
    'annual' => null,
];

$promotionCodeExists = false;
$promotionBlockedByAutomaticSale = false;
$promotionError = '';

if ($pendingPromotionCode !== '') {
    foreach (
        [
            'monthly' => $monthlyOffer,
            'annual' => $annualOffer,
        ]
        as $interval => $offer
    ) {
        if (!$offer) {
            continue;
        }

        $promotionCode = llama_membership_promotion_code_by_code(
            $db,
            $pendingPromotionCode,
            $interval
        );

        if (!$promotionCode) {
            continue;
        }

        $promotionCodeExists = true;

        /*
         * Automatic site sales and customer-entered Promotion Codes
         * do not stack on the same membership plan.
         */
        if (!empty($offer['on_sale'])) {
            $promotionBlockedByAutomaticSale = true;
            continue;
        }

        $promotionCodesByInterval[$interval] = $promotionCode;
    }

    $hasApplicablePromotion =
        $promotionCodesByInterval['monthly'] !== null
        || $promotionCodesByInterval['annual'] !== null;

    if ($hasApplicablePromotion) {
        $_SESSION['pending_membership_promo_code'] = $pendingPromotionCode;

    } elseif (!$promotionCodeExists) {
        unset($_SESSION['pending_membership_promo_code']);
        $pendingPromotionCode = '';

        if ($incomingPromotionCode !== '') {
            $promotionError =
                'That promotion code is not currently active or does not apply to an available membership plan.';
        }
    }
}


/* =========================================================
   ACCOUNT STATE
   ========================================================= */

$user = current_user();
$isLoggedIn = is_logged_in();

$isVerified =
    $user
    && !empty($user['email_verified_at']);

if ($isLoggedIn && !$isVerified) {
    header('Location: /verify-email.php', true, 303);
    exit;
}

$account = null;

if ($isLoggedIn) {
    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            membership_status,
            membership_interval,
            membership_ends_at,
            stripe_customer_id,
            stripe_subscription_id
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([(int) $user['id']]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$membershipStatus = strtolower(
    trim(
        (string) (
            $account['membership_status']
            ?? 'none'
        )
    )
);

$membershipInterval = strtolower(
    trim(
        (string) (
            $account['membership_interval']
            ?? ''
        )
    )
);

$hasPaidMembership =
    $account
    && in_array(
        $membershipStatus,
        [
            'active',
            'trialing',
            'past_due',
        ],
        true
    )
    && trim(
        (string) (
            $account['stripe_subscription_id']
            ?? ''
        )
    ) !== '';


/* =========================================================
   CHECKOUT SESSION TOKEN
   ========================================================= */

if (empty($_SESSION['membership_checkout_csrf'])) {
    $_SESSION['membership_checkout_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['membership_checkout_csrf'];


/* =========================================================
   DISPLAY HELPERS
   ========================================================= */

function signup_membership_e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function signup_membership_money(
    int $cents,
    string $currency = 'usd'
): string {
    return llama_membership_format_money(
        max(0, $cents),
        $currency
    );
}


function signup_membership_regular_price(?array $offer): string
{
    if (!$offer) {
        return '';
    }

    return signup_membership_money(
        (int) ($offer['base_price_cents'] ?? 0),
        (string) ($offer['plan']['currency'] ?? 'usd')
    );
}


function signup_membership_promotion_price_cents(
    array $offer,
    ?array $promotionCode
): int {
    if (!$promotionCode) {
        return (int) (
            $offer['effective_price_cents']
            ?? $offer['base_price_cents']
            ?? 0
        );
    }

    return llama_membership_promotion_code_price_cents(
        max(0, (int) ($offer['base_price_cents'] ?? 0)),
        $promotionCode
    );
}


function signup_membership_price(
    array $offer,
    ?array $promotionCode = null
): string {
    return signup_membership_money(
        signup_membership_promotion_price_cents(
            $offer,
            $promotionCode
        ),
        (string) ($offer['plan']['currency'] ?? 'usd')
    );
}


function signup_membership_promotion_duration_months(
    array $promotionCode
): int {
    $duration = strtolower(
        trim(
            (string) (
                $promotionCode['discount_duration']
                ?? 'once'
            )
        )
    );

    $months = (int) ($promotionCode['duration_months'] ?? 0);

    if (
        $duration !== 'months'
        || !in_array(
            $months,
            llama_membership_promotion_code_month_options(),
            true
        )
    ) {
        return 0;
    }

    return $months;
}


function signup_membership_promotion_terms(
    array $offer,
    array $promotionCode,
    string $interval
): string {
    $currency = (string) ($offer['plan']['currency'] ?? 'usd');

    $regularPriceCents = max(
        0,
        (int) ($offer['base_price_cents'] ?? 0)
    );

    $promotionalPriceCents =
        signup_membership_promotion_price_cents(
            $offer,
            $promotionCode
        );

    $regularPrice = signup_membership_money(
        $regularPriceCents,
        $currency
    );

    $promotionalPrice = signup_membership_money(
        $promotionalPriceCents,
        $currency
    );

    $durationMonths =
        signup_membership_promotion_duration_months(
            $promotionCode
        );

    if (
        $interval === 'monthly'
        && $durationMonths > 0
    ) {
        if ($durationMonths === 1) {
            return
                $promotionalPrice
                . '/month for your first month. '
                . 'Then '
                . $regularPrice
                . '/month.';
        }

        return
            $promotionalPrice
            . '/month for your first '
            . number_format($durationMonths)
            . ' months. '
            . 'Then '
            . $regularPrice
            . '/month.';
    }

    if ($interval === 'monthly') {
        return
            'Your first month is '
            . $promotionalPrice
            . '. Then '
            . $regularPrice
            . '/month.';
    }

    return
        'Your first year is '
        . $promotionalPrice
        . '. Then '
        . $regularPrice
        . '/year.';
}


function signup_membership_automatic_sale_terms(
    array $offer,
    string $interval
): string {
    $currency = (string) ($offer['plan']['currency'] ?? 'usd');

    $regularPriceCents = max(
        0,
        (int) ($offer['base_price_cents'] ?? 0)
    );

    $promotionalPriceCents = max(
        0,
        (int) (
            $offer['effective_price_cents']
            ?? $regularPriceCents
        )
    );

    $regularPrice = signup_membership_money(
        $regularPriceCents,
        $currency
    );

    $promotionalPrice = signup_membership_money(
        $promotionalPriceCents,
        $currency
    );

    if ($interval === 'monthly') {
        /*
         * Existing legacy Monthly campaigns used 12 months.
         * New campaigns explicitly store 1, 2, 3, 6, 9, or 12.
         */
        $durationMonths = (int) ($offer['duration_count'] ?? 12);

        if (!in_array($durationMonths, [1, 2, 3, 6, 9, 12], true)) {
            $durationMonths = 12;
        }

        if ($durationMonths === 1) {
            return
                $promotionalPrice
                . '/month for your first month. '
                . 'Then '
                . $regularPrice
                . '/month.';
        }

        return
            $promotionalPrice
            . '/month for your first '
            . number_format($durationMonths)
            . ' months. '
            . 'Then '
            . $regularPrice
            . '/month.';
    }

    return
        'Your first year is '
        . $promotionalPrice
        . '. Then '
        . $regularPrice
        . '/year.';
}


/* =========================================================
   PAGE STATE
   ========================================================= */

$hasPromotionPreview =
    $promotionCodesByInterval['monthly'] !== null
    || $promotionCodesByInterval['annual'] !== null;

$displayPromotionCode = '';

foreach ($promotionCodesByInterval as $promotionCode) {
    if (!$promotionCode) {
        continue;
    }

    $displayPromotionCode = strtoupper(
        trim(
            (string) (
                $promotionCode['code']
                ?? ''
            )
        )
    );

    if ($displayPromotionCode !== '') {
        break;
    }
}

$pageTitle = $hasPromotionPreview
    ? 'Special Membership Offer | Llama Scout'
    : 'Membership | Llama Scout';

$pageRobots = 'noindex,nofollow';

$pageDescription =
    'Choose a Llama Scout membership plan and complete secure checkout without leaving Llama Scout.';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/membership.css"
>

<section class="signup-membership-page">

<header class="signup-membership-header">

<a
    class="signup-membership-back"
    href="https://llamascout.com/membership"
>
    <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
    Membership details
</a>

<?php if ($hasPromotionPreview): ?>

<p class="eyebrow">Special Membership Offer</p>

<h1>Your Llama Scout offer is ready.</h1>

<p>
    Promotional pricing is shown below for every membership
    plan eligible for your offer.
</p>

<?php else: ?>

<p class="eyebrow">Llama Scout Membership</p>

<h1>Choose how you want to join.</h1>

<p>
    Monthly and annual memberships unlock the same complete
    Llama Scout Place reports. Only the billing interval changes.
</p>

<?php endif; ?>

</header>


<?php if ($hasPromotionPreview): ?>

<div class="signup-membership-notice is-success">
    <i aria-hidden="true"><?= llama_icon('ticket') ?></i>

    <div>
        <strong>
            <?= signup_membership_e($displayPromotionCode) ?> applied
        </strong>

        <span>
            Your promotional price is already attached to this
            membership session and will carry into secure checkout.
        </span>
    </div>
</div>

<?php elseif ($promotionBlockedByAutomaticSale): ?>

<div class="signup-membership-notice">
    <i aria-hidden="true"><?= llama_icon('info-circle') ?></i>

    <div>
        <strong>A site promotion is already active.</strong>

        <span>
            Promotion codes cannot be combined with the automatic
            membership sale currently being offered.
        </span>
    </div>
</div>

<?php elseif ($promotionError !== ''): ?>

<div class="signup-membership-notice">
    <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>

    <span><?= signup_membership_e($promotionError) ?></span>
</div>

<?php endif; ?>


<?php if (isset($_GET['verified'])): ?>

<div class="signup-membership-notice is-success">
    <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>

    Email verified. Your account is ready. Continue with the
    membership you selected.
</div>

<?php endif; ?>


<?php if (
    isset($_GET['checkout'])
    && $_GET['checkout'] === 'success'
): ?>

<div class="signup-membership-notice is-success">
    <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>

    Payment completed. Stripe is confirming your membership and
    your account will update automatically.
</div>

<?php elseif (
    isset($_GET['checkout'])
    && $_GET['checkout'] === 'canceled'
): ?>

<div class="signup-membership-notice">
    Checkout was canceled. No payment or membership change was made.
</div>

<?php endif; ?>


<?php if ($hasPaidMembership): ?>

<section class="signup-membership-current">

<div>
    <span>Current membership</span>

    <strong>
        <?= signup_membership_e(
            $membershipInterval === 'annual'
                ? 'Annual'
                : (
                    $membershipInterval === 'monthly'
                        ? 'Monthly'
                        : ucfirst($membershipStatus)
                )
        ) ?>
    </strong>
</div>

<a
    class="signup-membership-button"
    href="/billing.php"
>
    Manage membership & billing
</a>

</section>

<?php else: ?>

<div class="signup-membership-grid">

<?php foreach (
    [
        'monthly' => $monthlyOffer,
        'annual' => $annualOffer,
    ]
    as $interval => $offer
): ?>

<?php
if (!$offer) {
    continue;
}

$isSelected = $plan === $interval;

$automaticPromotion = $offer['promotion'] ?? null;
$automaticSale = !empty($offer['on_sale']);

$promotionCode =
    $promotionCodesByInterval[$interval]
    ?? null;

$hasPromotionCode = $promotionCode !== null;

$showDiscountedPrice =
    $automaticSale
    || $hasPromotionCode;

$currency = (string) ($offer['plan']['currency'] ?? 'usd');

$displayPrice = signup_membership_price(
    $offer,
    $hasPromotionCode
        ? $promotionCode
        : null
);

$regularPrice = signup_membership_regular_price($offer);

$promotionTerms = $hasPromotionCode
    ? signup_membership_promotion_terms(
        $offer,
        $promotionCode,
        $interval
    )
    : '';

$automaticSaleTerms = $automaticSale
    ? signup_membership_automatic_sale_terms(
        $offer,
        $interval
    )
    : '';
?>

<article
    class="signup-membership-plan <?= $isSelected ? 'is-selected' : '' ?>"
>

<?php if ($isSelected): ?>

<span class="signup-membership-selected">
    You selected this plan
</span>

<?php endif; ?>

<h2><?= $interval === 'annual' ? 'Annual' : 'Monthly' ?></h2>

<div class="signup-membership-price">

<?php if ($showDiscountedPrice): ?>

<del><?= signup_membership_e($regularPrice) ?></del>

<?php endif; ?>

<strong><?= signup_membership_e($displayPrice) ?></strong>

<span>
    / <?= $interval === 'annual' ? 'year' : 'month' ?>
</span>

</div>


<?php if ($automaticSale): ?>

<div class="signup-membership-notice is-success">
    <div>
        <strong>
            <?= signup_membership_e(
                (string) (
                    $automaticPromotion['public_label']
                    ?? $automaticPromotion['promotion_name']
                    ?? 'Limited-time promotion'
                )
            ) ?>
        </strong>

        <span>
            <?= signup_membership_e($automaticSaleTerms) ?>
        </span>
    </div>
</div>

<?php elseif ($hasPromotionCode): ?>

<div class="signup-membership-notice is-success">
    <i aria-hidden="true"><?= llama_icon('ticket') ?></i>

    <div>
        <strong>
            <?= signup_membership_e(
                (string) ($promotionCode['code'] ?? '')
            ) ?> applied
        </strong>

        <span>
            <?= signup_membership_e($promotionTerms) ?>

            <?php if (!empty($promotionCode['first_time_customers_only'])): ?>
                First-time customers only.
            <?php endif; ?>
        </span>
    </div>
</div>

<?php endif; ?>

<ul>
    <li>Exact Place locations and coordinates</li>
    <li>Complete sensory details</li>
    <li>Road and vehicle-access information</li>
    <li>Connectivity and Starlink information</li>
    <li>Complete warnings, rules, and planning data</li>
</ul>


<?php if (!$isLoggedIn): ?>

<?php
$registrationUrl =
    '/register.php?plan='
    . rawurlencode($interval);

if ($displayPromotionCode !== '') {
    $registrationUrl .=
        '&promo='
        . rawurlencode($displayPromotionCode);
}

$returnUrl =
    'https://account.llamascout.com/membership.php?plan='
    . rawurlencode($interval);

if ($displayPromotionCode !== '') {
    $returnUrl .=
        '&promo='
        . rawurlencode($displayPromotionCode);
}
?>

<a
    class="signup-membership-button"
    href="<?= signup_membership_e($registrationUrl) ?>"
>
    Create account to continue
</a>

<a
    class="signup-membership-signin"
    href="/login.php?return=<?= rawurlencode($returnUrl) ?>"
>
    Already have an account? Sign in
</a>

<?php else: ?>

<form
    method="post"
    action="/checkout.php"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= signup_membership_e($csrfToken) ?>"
    >

    <input
        type="hidden"
        name="interval"
        value="<?= signup_membership_e($interval) ?>"
    >

    <button
        class="signup-membership-button"
        type="submit"
    >
        Continue to secure checkout
    </button>
</form>

<?php endif; ?>

</article>

<?php endforeach; ?>

</div>

<div class="signup-membership-security">
    <i aria-hidden="true"><?= llama_icon('lock') ?></i>

    <div>
        <strong>Secure checkout on Llama Scout</strong>

        <span>
            Stripe securely handles the payment fields inside Llama Scout.
            Llama Scout does not receive or store your financial data.
        </span>
    </div>
</div>

<?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/memberships.php';

start_llama_session();

$db = db();

$plan =
    strtolower(
        trim(
            (string) (
                $_GET['plan']
                ?? $_SESSION['pending_membership_plan']
                ?? ''
            )
        )
    );

if (
    !in_array(
        $plan,
        ['monthly', 'annual'],
        true
    )
) {
    $plan = '';
}

if ($plan !== '') {
    $_SESSION['pending_membership_plan'] =
        $plan;
}

$offers =
    llama_membership_offers(
        $db
    );

$monthlyOffer =
    $offers['monthly']
    ?? null;

$annualOffer =
    $offers['annual']
    ?? null;

$user =
    current_user();

$isLoggedIn =
    is_logged_in();

$isVerified =
    $user
    && !empty(
        $user['email_verified_at']
    );

if (
    $isLoggedIn
    && !$isVerified
) {
    header(
        'Location: /verify-email.php',
        true,
        303
    );
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

    $stmt->execute([
        (int) $user['id'],
    ]);

    $account =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: null;
}

$membershipStatus =
    strtolower(
        trim(
            (string) (
                $account['membership_status']
                ?? 'none'
            )
        )
    );

$membershipInterval =
    strtolower(
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

if (
    empty(
        $_SESSION[
            'membership_checkout_csrf'
        ]
    )
) {
    $_SESSION[
        'membership_checkout_csrf'
    ] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    (string)
    $_SESSION[
        'membership_checkout_csrf'
    ];

function signup_membership_e(
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function signup_membership_price(
    ?array $offer
): string {
    if (!$offer) {
        return 'Unavailable';
    }

    return llama_membership_format_money(
        (int)
        $offer[
            'effective_price_cents'
        ],
        (string)
        $offer[
            'plan'
        ][
            'currency'
        ]
    );
}

function signup_membership_regular_price(
    ?array $offer
): string {
    if (!$offer) {
        return '';
    }

    return llama_membership_format_money(
        (int)
        $offer[
            'base_price_cents'
        ],
        (string)
        $offer[
            'plan'
        ][
            'currency'
        ]
    );
}

$pageTitle =
    'Membership | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageDescription =
    'Choose a Llama Scout membership plan and continue to secure Stripe checkout.';

require dirname(__DIR__) .
    '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account-membership-v2.css"
>

<section class="signup-membership-page">

<header class="signup-membership-header">

<a
    class="signup-membership-back"
    href="https://llamascout.com/membership"
>
    <i
        class="fa-solid fa-arrow-left"
        aria-hidden="true"
    ></i>
    Membership details
</a>

<p class="eyebrow">
    Llama Scout Membership
</p>

<h1>
    Choose how you want to join.
</h1>

<p>
    Monthly and annual memberships unlock the same complete
    Llama Scout Place reports. Only the billing interval changes.
</p>

</header>


<?php if (
    isset($_GET['verified'])
): ?>

<div class="signup-membership-notice is-success">
    <i
        class="fa-solid fa-circle-check"
        aria-hidden="true"
    ></i>

    Email verified. Your account is ready. Continue with the
    membership you selected.
</div>

<?php endif; ?>


<?php if (
    isset($_GET['checkout'])
    && $_GET['checkout'] === 'success'
): ?>

<div class="signup-membership-notice is-success">
    <i
        class="fa-solid fa-circle-check"
        aria-hidden="true"
    ></i>

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

<?php if (!$offer) continue; ?>

<?php
$isSelected =
    $plan === $interval;

$onSale =
    !empty(
        $offer['on_sale']
    );
?>

<article
    class="signup-membership-plan <?= $isSelected
        ? 'is-selected'
        : '' ?>"
>

<?php if ($isSelected): ?>

<span class="signup-membership-selected">
    You selected this plan
</span>

<?php endif; ?>

<h2>
    <?= $interval === 'annual'
        ? 'Annual'
        : 'Monthly' ?>
</h2>

<div class="signup-membership-price">

<?php if ($onSale): ?>
    <del>
        <?= signup_membership_e(
            signup_membership_regular_price(
                $offer
            )
        ) ?>
    </del>
<?php endif; ?>

<strong>
    <?= signup_membership_e(
        signup_membership_price(
            $offer
        )
    ) ?>
</strong>

<span>
    /
    <?= $interval === 'annual'
        ? 'year'
        : 'month' ?>
</span>

</div>

<ul>
    <li>Exact Place locations and coordinates</li>
    <li>Complete sensory details</li>
    <li>Road and vehicle-access information</li>
    <li>Connectivity and Starlink information</li>
    <li>Complete warnings, rules, and planning data</li>
</ul>


<?php if (!$isLoggedIn): ?>

<a
    class="signup-membership-button"
    href="/register.php?plan=<?= signup_membership_e(
        $interval
    ) ?>"
>
    Create account to continue
</a>

<a
    class="signup-membership-signin"
    href="/login.php?return=<?= rawurlencode(
        'https://account.llamascout.com/membership.php?plan=' .
        $interval
    ) ?>"
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
    value="<?= signup_membership_e(
        $csrfToken
    ) ?>"
>

<input
    type="hidden"
    name="interval"
    value="<?= signup_membership_e(
        $interval
    ) ?>"
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

<i
    class="fa-solid fa-lock"
    aria-hidden="true"
></i>

<div>
    <strong>
        Secure checkout by Stripe
    </strong>

    <span>
        Llama Scout does not receive or store your complete card
        number. Stripe handles payment information and recurring
        billing.
    </span>
</div>

</div>

<?php endif; ?>

</section>

<?php
require dirname(__DIR__) .
    '/partials/footer.php';
?>

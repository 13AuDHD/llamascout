<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/memberships.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

$config = llama_config();

$siteUrl = rtrim(
    (string) (
        $config['app']['url']
        ?? 'https://llamascout.com'
    ),
    '/'
);

$stmt = $db->prepare(
    'SELECT
        id,
        username,
        display_name,
        email,
        membership_status,
        membership_interval,
        membership_started_at,
        membership_ends_at,
        stripe_customer_id,
        stripe_subscription_id
     FROM users
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$userId]);

$account =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    http_response_code(404);
    exit('Account not found.');
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

$customerId =
    trim(
        (string) (
            $account['stripe_customer_id']
            ?? ''
        )
    );

$subscriptionId =
    trim(
        (string) (
            $account['stripe_subscription_id']
            ?? ''
        )
    );

$hasStripeCustomer =
    $customerId !== '';

$hasStripeSubscription =
    $subscriptionId !== '';

$isPaidMembership =
    in_array(
        $membershipStatus,
        [
            'active',
            'trialing',
            'past_due',
        ],
        true
    )
    && $hasStripeSubscription;

$membershipLabel = match ($membershipStatus) {
    'active' =>
        $membershipInterval === 'annual'
            ? 'Annual membership'
            : (
                $membershipInterval === 'monthly'
                    ? 'Monthly membership'
                    : 'Active membership'
            ),
    'trialing' => 'Trial membership',
    'past_due' => 'Payment issue',
    'complimentary' => 'Complimentary access',
    'canceled' => 'Canceled membership',
    default => 'Free account',
};

$plans = [];

try {
    $plans =
        llama_membership_plans(
            $db,
            true
        );
} catch (Throwable $exception) {
    error_log(
        'Llama Scout billing plan lookup error for user #' .
        $userId .
        ': ' .
        $exception->getMessage()
    );
}

function billing_e(
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function billing_money(
    int $cents,
    string $currency
): string {
    $currency =
        strtolower(
            trim($currency)
        );

    if ($currency === 'usd') {
        return '$' .
            number_format(
                $cents / 100,
                2
            );
    }

    return number_format(
        $cents / 100,
        2
    ) .
    ' ' .
    strtoupper($currency);
}

function billing_date(
    ?string $value
): string {
    if (!$value) {
        return '';
    }

    try {
        return (
            new DateTimeImmutable(
                $value
            )
        )->format(
            'M j, Y'
        );
    } catch (Throwable) {
        return '';
    }
}

$pageTitle =
    'Membership & Billing | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageDescription = '';

require dirname(__DIR__) .
    '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= billing_e(
        $siteUrl .
        '/css/account-billing.css'
    ) ?>"
>

<section class="billing-page">

<header class="billing-page-header">

<a
    class="billing-back-link"
    href="/index.php"
>
    <i
        class="fa-solid fa-arrow-left"
        aria-hidden="true"
    ></i>
    Your account
</a>

<p class="eyebrow">
    Membership & billing
</p>

<h1>
    Manage your Llama Scout membership.
</h1>

<p>
    Update payment information, review invoices,
    change plans, cancel a membership, or start
    a new one.
</p>

</header>


<section class="billing-status-card">

<div class="billing-status-icon">
    <i
        class="fa-solid fa-credit-card"
        aria-hidden="true"
    ></i>
</div>

<div class="billing-status-main">

<span>Current access</span>

<strong>
    <?= billing_e(
        $membershipLabel
    ) ?>
</strong>

<?php if (
    !empty(
        $account[
            'membership_ends_at'
        ]
    )
): ?>

<small>
    Current period ends
    <?= billing_e(
        billing_date(
            (string) $account[
                'membership_ends_at'
            ]
        )
    ) ?>
</small>

<?php endif; ?>

</div>

<?php if ($isPaidMembership): ?>

<span class="billing-status-pill is-active">
    <?= $membershipStatus === 'past_due'
        ? 'Action needed'
        : 'Active' ?>
</span>

<?php elseif (
    $membershipStatus ===
    'complimentary'
): ?>

<span class="billing-status-pill">
    Complimentary
</span>

<?php else: ?>

<span class="billing-status-pill">
    Free
</span>

<?php endif; ?>

</section>


<div class="billing-action-grid">

<?php if ($hasStripeCustomer): ?>

<article class="billing-action-card">

<div class="billing-action-icon">
    <i
        class="fa-solid fa-wallet"
        aria-hidden="true"
    ></i>
</div>

<h2>
    Stripe billing portal
</h2>

<p>
    Update your card or billing information,
    review and download invoices, manage your
    subscription, switch available plans, or
    cancel.
</p>

<a
    class="billing-primary-button"
    href="/billing-portal.php"
>
    Open secure billing portal
    <i
        class="fa-solid fa-arrow-up-right-from-square"
        aria-hidden="true"
    ></i>
</a>

<small>
    Opens Stripe's secure customer portal.
</small>

</article>

<?php endif; ?>


<article class="billing-action-card">

<div class="billing-action-icon">
    <i
        class="fa-solid fa-arrows-rotate"
        aria-hidden="true"
    ></i>
</div>

<h2>
    Membership plans
</h2>

<p>
    Compare the current monthly and annual plans.
    If you do not have an active paid membership,
    you can start one here.
</p>

<a
    class="billing-secondary-button"
    href="<?= billing_e(
        $siteUrl .
        '/membership'
    ) ?>"
>
    View membership plans
</a>

</article>

</div>


<?php if ($plans): ?>

<section class="billing-plans-section">

<header>
    <p class="eyebrow">
        Current options
    </p>

    <h2>
        Membership plans
    </h2>
</header>


<div class="billing-plan-grid">

<?php foreach ($plans as $plan): ?>

<?php
$interval =
    strtolower(
        trim(
            (string) (
                $plan[
                    'interval_slug'
                ]
                ?? ''
            )
        )
    );

$currentPlan =
    $isPaidMembership
    && $membershipInterval ===
        $interval;
?>

<article
    class="billing-plan-card <?= $currentPlan
        ? 'is-current'
        : '' ?>"
>

<?php if ($currentPlan): ?>

<span class="billing-current-plan">
    Current plan
</span>

<?php endif; ?>

<h3>
    <?= billing_e(
        $plan['name']
        ?? ucfirst($interval)
    ) ?>
</h3>

<div class="billing-plan-price">

<strong>
    <?= billing_e(
        billing_money(
            (int) (
                $plan[
                    'base_price_cents'
                ]
                ?? 0
            ),
            (string) (
                $plan['currency']
                ?? 'usd'
            )
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

<?php if (
    !empty(
        $plan['description']
    )
): ?>

<p>
    <?= billing_e(
        $plan['description']
    ) ?>
</p>

<?php endif; ?>


<?php if ($currentPlan): ?>

<span class="billing-plan-selected">
    Your current membership
</span>

<?php elseif ($isPaidMembership): ?>

<a
    href="/billing-portal.php"
    class="billing-plan-action"
>
    Switch in Stripe
</a>

<?php else: ?>

<a
    href="<?= billing_e(
        $siteUrl .
        '/membership'
    ) ?>"
    class="billing-plan-action"
>
    Start membership
</a>

<?php endif; ?>

</article>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>


<section class="billing-explainer">

<i
    class="fa-solid fa-shield-halved"
    aria-hidden="true"
></i>

<div>

<strong>
    Payment information stays with Stripe.
</strong>

<p>
    Llama Scout stores your Stripe customer and
    subscription references, but your full card
    number is handled by Stripe rather than being
    stored by Llama Scout.
</p>

</div>

</section>

</section>

<?php
require dirname(__DIR__) .
    '/partials/footer.php';
?>

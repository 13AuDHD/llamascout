<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();
start_llama_session();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();
$config = llama_config();

$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');

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
        stripe_subscription_id,
        stripe_cancel_at_period_end
     FROM users
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    http_response_code(404);
    exit('Account not found.');
}

if (empty($_SESSION['billing_csrf'])) {
    $_SESSION['billing_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['billing_csrf'];

$membershipStatus = strtolower(trim((string) ($account['membership_status'] ?? 'none')));
$membershipInterval = strtolower(trim((string) ($account['membership_interval'] ?? '')));
$customerId = trim((string) ($account['stripe_customer_id'] ?? ''));
$subscriptionId = trim((string) ($account['stripe_subscription_id'] ?? ''));
$cancelAtPeriodEnd = !empty($account['stripe_cancel_at_period_end']);

$hasStripeCustomer = $customerId !== '';
$hasStripeSubscription = $subscriptionId !== '';
$isPaidMembership = in_array($membershipStatus, ['active', 'trialing', 'past_due'], true)
    && $hasStripeSubscription;

$membershipLabel = match ($membershipStatus) {
    'active' => $membershipInterval === 'annual'
        ? 'Annual membership'
        : ($membershipInterval === 'monthly' ? 'Monthly membership' : 'Active membership'),
    'trialing' => 'Trial membership',
    'past_due' => 'Payment issue',
    'complimentary' => 'Complimentary access',
    'canceled' => 'Canceled membership',
    default => 'Free account',
};

$plans = [];
$billingSnapshot = null;
$billingSnapshotError = '';
$billingError = trim((string) ($_SESSION['billing_error'] ?? ''));
unset($_SESSION['billing_error']);

try {
    $plans = llama_membership_plans($db, true);
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'billing_plan_lookup',
        ['user_id' => $userId]
    );
}

if ($hasStripeCustomer) {
    try {
        $billingSnapshot = llama_stripe_billing_snapshot(
            $customerId,
            $hasStripeSubscription ? $subscriptionId : null,
            12
        );

        if (!empty($billingSnapshot['subscription'])) {
            $cancelAtPeriodEnd =
                !empty($billingSnapshot['subscription']['cancel_at_period_end']);
        }
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'billing_snapshot',
            ['user_id' => $userId]
        );

        $billingSnapshotError = llama_error_message_with_reference(
            'Live billing details are temporarily unavailable. Your membership access has not been changed.',
            $reference
        );
    }
}

function billing_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function billing_money(int $cents, string $currency): string
{
    $currency = strtolower(trim($currency));
    if ($currency === 'usd') {
        return '$' . number_format($cents / 100, 2);
    }
    return number_format($cents / 100, 2) . ' ' . strtoupper($currency);
}

function billing_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return '';
    }
}

function billing_timestamp_date(?int $value): string
{
    if (!$value) {
        return '';
    }
    return gmdate('M j, Y', $value);
}

function billing_brand(string $brand): string
{
    return match (strtolower($brand)) {
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'discover' => 'Discover',
        default => $brand !== '' ? ucfirst($brand) : 'Card',
    };
}

$notices = [
    'payment-updated' => 'Payment method updated.',
    'plan-updated' => 'Membership plan updated. Stripe is syncing the change to your account.',
    'cancellation-updated' => 'Cancellation preference updated. Your access will follow the billing period shown below.',
    'reactivated' => 'Automatic renewal has been restored for this membership.',
];
$noticeKey = trim((string) ($_GET['billing'] ?? ''));
$notice = $notices[$noticeKey] ?? '';

$periodEnd = (string) ($account['membership_ends_at'] ?? '');
if (!empty($billingSnapshot['subscription']['period_end'])) {
    $periodEnd = (string) $billingSnapshot['subscription']['period_end'];
}

$pageTitle = 'Membership & Billing | Llama Scout';
$pageRobots = 'noindex,nofollow';
$pageDescription = '';

require dirname(__DIR__) . '/partials/header.php';
?>

<link rel="stylesheet" href="<?= billing_e($siteUrl . '/css/account/pages/billing.css') ?>">

<section class="billing-page">

<header class="billing-page-header">
    <a class="billing-back-link" href="/index.php">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Your account
    </a>

    <p class="eyebrow">Membership & billing</p>
    <h1>Your membership, without leaving Llama Scout.</h1>
    <p>
        Review your plan, renewal, payment method and billing history here.
        Stripe still securely handles payment credentials and sensitive billing actions.
    </p>
</header>

<?php if ($notice !== ''): ?>
<div class="billing-notice is-success">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <?= billing_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($billingError !== ''): ?>
<div class="billing-notice is-error">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <?= billing_e($billingError) ?>
</div>
<?php endif; ?>

<?php if ($billingSnapshotError !== ''): ?>
<div class="billing-notice is-warning">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <?= billing_e($billingSnapshotError) ?>
</div>
<?php endif; ?>

<section class="billing-status-card">
    <div class="billing-status-icon">
        <i class="fa-solid fa-id-card" aria-hidden="true"></i>
    </div>

    <div class="billing-status-main">
        <span>Current access</span>
        <strong><?= billing_e($membershipLabel) ?></strong>

        <?php if ($periodEnd !== ''): ?>
        <small>
            <?= $cancelAtPeriodEnd ? 'Access through' : 'Current period ends' ?>
            <?= billing_e(billing_date($periodEnd)) ?>
        </small>
        <?php endif; ?>
    </div>

    <span class="billing-status-pill <?= $membershipStatus === 'active' && !$cancelAtPeriodEnd ? 'is-active' : '' ?>">
        <?php
        echo billing_e(match (true) {
            $membershipStatus === 'past_due' => 'Action needed',
            $cancelAtPeriodEnd => 'Ends this period',
            $membershipStatus === 'active' => 'Active',
            $membershipStatus === 'complimentary' => 'Complimentary',
            default => ucfirst($membershipStatus === 'none' ? 'free' : $membershipStatus),
        });
        ?>
    </span>
</section>

<div class="billing-overview-grid">

<article class="billing-detail-card">
    <div class="billing-detail-heading">
        <div class="billing-action-icon">
            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
        </div>
        <div>
            <span>Membership</span>
            <h2><?= billing_e($membershipLabel) ?></h2>
        </div>
    </div>

    <?php if ($periodEnd !== ''): ?>
    <dl class="billing-detail-list">
        <div>
            <dt><?= $cancelAtPeriodEnd ? 'Ends' : 'Renews / period ends' ?></dt>
            <dd><?= billing_e(billing_date($periodEnd)) ?></dd>
        </div>
        <?php if (!empty($account['membership_started_at'])): ?>
        <div>
            <dt>Member since</dt>
            <dd><?= billing_e(billing_date((string) $account['membership_started_at'])) ?></dd>
        </div>
        <?php endif; ?>
    </dl>
    <?php endif; ?>

    <?php if ($isPaidMembership && $cancelAtPeriodEnd): ?>
    <form method="post" action="/billing-action.php" class="billing-inline-action">
        <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
        <input type="hidden" name="action" value="reactivate_subscription">
        <button type="submit" class="billing-primary-button">
            Keep my membership
        </button>
    </form>
    <?php endif; ?>
</article>

<article class="billing-detail-card">
    <div class="billing-detail-heading">
        <div class="billing-action-icon">
            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
        </div>
        <div>
            <span>Payment method</span>
            <h2>
                <?php if (!empty($billingSnapshot['payment_method'])): ?>
                    <?= billing_e(billing_brand((string) $billingSnapshot['payment_method']['brand'])) ?>
                    •••• <?= billing_e((string) $billingSnapshot['payment_method']['last4']) ?>
                <?php elseif ($hasStripeCustomer): ?>
                    Securely stored with Stripe
                <?php else: ?>
                    No payment method
                <?php endif; ?>
            </h2>
        </div>
    </div>

    <?php if (!empty($billingSnapshot['payment_method'])): ?>
    <p class="billing-card-expiry">
        Expires
        <?= billing_e(sprintf(
            '%02d/%d',
            (int) $billingSnapshot['payment_method']['exp_month'],
            (int) $billingSnapshot['payment_method']['exp_year']
        )) ?>
    </p>
    <?php endif; ?>

    <?php if ($hasStripeCustomer): ?>
    <form method="post" action="/payment-method.php" class="billing-inline-action">
        <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
        <button type="submit" class="billing-secondary-button">
            Update payment method
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
        </button>
    </form>
    <?php endif; ?>
</article>

</div>

<?php if ($plans): ?>
<section class="billing-plans-section">
    <header>
        <p class="eyebrow">Membership options</p>
        <h2>Choose the billing interval that works for you.</h2>
        <p class="billing-section-copy">
            Monthly and annual memberships unlock the same member features.
        </p>
    </header>

    <div class="billing-plan-grid">
    <?php foreach ($plans as $plan): ?>
        <?php
        $interval = strtolower(trim((string) ($plan['interval_slug'] ?? '')));
        $currentPlan = $isPaidMembership && $membershipInterval === $interval;
        ?>
        <article class="billing-plan-card <?= $currentPlan ? 'is-current' : '' ?>">
            <?php if ($currentPlan): ?>
            <span class="billing-current-plan">Current plan</span>
            <?php endif; ?>

            <h3><?= billing_e($plan['name'] ?? ucfirst($interval)) ?></h3>

            <div class="billing-plan-price">
                <strong><?= billing_e(billing_money(
                    (int) ($plan['base_price_cents'] ?? 0),
                    (string) ($plan['currency'] ?? 'usd')
                )) ?></strong>
                <span>/ <?= $interval === 'annual' ? 'year' : 'month' ?></span>
            </div>

            <?php if (!empty($plan['description'])): ?>
            <p><?= billing_e($plan['description']) ?></p>
            <?php endif; ?>

            <?php if ($currentPlan): ?>
                <span class="billing-plan-selected">Your current membership</span>
            <?php elseif ($isPaidMembership && !$cancelAtPeriodEnd): ?>
                <form method="post" action="/billing-action.php" class="billing-plan-form">
                    <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
                    <input type="hidden" name="action" value="change_plan">
                    <input type="hidden" name="interval" value="<?= billing_e($interval) ?>">
                    <button type="submit" class="billing-plan-action">
                        Switch to <?= billing_e($interval === 'annual' ? 'annual' : 'monthly') ?>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </button>
                </form>
            <?php elseif (!$isPaidMembership): ?>
                <a href="/membership.php?plan=<?= billing_e($interval) ?>" class="billing-plan-action">
                    Start membership
                </a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($isPaidMembership): ?>
<section class="billing-management-section">
    <header>
        <p class="eyebrow">Subscription controls</p>
        <h2>Manage renewal.</h2>
    </header>

    <div class="billing-management-card">
        <?php if ($cancelAtPeriodEnd): ?>
            <div>
                <strong>Your membership is scheduled to end.</strong>
                <p>
                    You keep member access through <?= billing_e(billing_date($periodEnd)) ?>.
                    You can restore automatic renewal before then.
                </p>
            </div>
            <form method="post" action="/billing-action.php">
                <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
                <input type="hidden" name="action" value="reactivate_subscription">
                <button type="submit" class="billing-primary-button">Keep membership</button>
            </form>
        <?php else: ?>
            <div>
                <strong>Automatic renewal is on.</strong>
                <p>
                    Canceling does not remove access immediately. Stripe will show the exact end date before you confirm.
                </p>
            </div>
            <form method="post" action="/billing-action.php">
                <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
                <input type="hidden" name="action" value="cancel_subscription">
                <button type="submit" class="billing-danger-button">
                    Cancel membership
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="billing-history-section">
    <header class="billing-section-header">
        <div>
            <p class="eyebrow">Billing history</p>
            <h2>Invoices & receipts</h2>
        </div>
    </header>

    <?php $invoices = $billingSnapshot['invoices'] ?? []; ?>

    <?php if ($invoices): ?>
    <div class="billing-history-list">
        <?php foreach ($invoices as $invoice): ?>
        <article class="billing-history-row">
            <div class="billing-history-date">
                <strong><?= billing_e(billing_timestamp_date($invoice['created'] ?? null)) ?></strong>
                <span><?= billing_e((string) ($invoice['number'] ?: $invoice['id'])) ?></span>
            </div>

            <div class="billing-history-amount">
                <strong><?= billing_e(billing_money(
                    (int) (($invoice['amount_paid'] ?? 0) ?: ($invoice['amount_due'] ?? 0)),
                    (string) ($invoice['currency'] ?? 'usd')
                )) ?></strong>
                <span><?= billing_e(ucfirst((string) ($invoice['status'] ?? ''))) ?></span>
            </div>

            <div class="billing-history-actions">
                <?php if (!empty($invoice['hosted_invoice_url'])): ?>
                <a href="<?= billing_e($invoice['hosted_invoice_url']) ?>" target="_blank" rel="noopener">
                    View
                </a>
                <?php endif; ?>
                <?php if (!empty($invoice['invoice_pdf'])): ?>
                <a href="<?= billing_e($invoice['invoice_pdf']) ?>" target="_blank" rel="noopener">
                    PDF
                </a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="billing-empty-state">
        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
        <div>
            <strong>No Stripe invoices to show yet.</strong>
            <p>Your paid membership invoices will appear here after Stripe creates them.</p>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php if ($hasStripeCustomer): ?>
<section class="billing-advanced-section">
    <div>
        <strong>Need something not shown here?</strong>
        <p>Tax IDs and other advanced Stripe-managed billing options are available in the secure Stripe portal.</p>
    </div>
    <form method="post" action="/billing-action.php">
        <input type="hidden" name="csrf_token" value="<?= billing_e($csrfToken) ?>">
        <input type="hidden" name="action" value="manage_billing">
        <button type="submit" class="billing-text-button">
            Advanced billing options
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
        </button>
    </form>
</section>
<?php endif; ?>

<section class="billing-explainer">
    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
    <div>
        <strong>Payment information stays with Stripe.</strong>
        <p>
            Llama Scout stores Stripe customer and subscription references so your account knows what access you have.
            Full card numbers and payment credentials are handled by Stripe, not stored by Llama Scout.
        </p>
    </div>
</section>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

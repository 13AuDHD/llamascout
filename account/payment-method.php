<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once dirname(__DIR__) . '/app/memberships.php';

require_login();
start_llama_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /billing.php', true, 303);
    exit;
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

$expectedToken = $_SESSION['billing_csrf'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($expectedToken)
    || $expectedToken === ''
    || !is_string($submittedToken)
    || !hash_equals($expectedToken, $submittedToken)
) {
    http_response_code(403);
    exit('Your billing session could not be verified. Return to Billing and try again.');
}

$stmt = $db->prepare(
    'SELECT
        id,
        stripe_customer_id,
        stripe_subscription_id,
        membership_interval
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

$customerId = trim((string) ($account['stripe_customer_id'] ?? ''));
$subscriptionId = trim((string) ($account['stripe_subscription_id'] ?? ''));

$clientSecret = '';
$publishableKey = '';
$pageError = '';

try {
    if ($customerId === '') {
        throw new RuntimeException('No Stripe customer is attached to this account.');
    }

    $publishableKey = llama_stripe_publishable_key();

    $setupIntent = llama_stripe_client()
        ->setupIntents
        ->create([
            'customer' => $customerId,
            'usage' => 'off_session',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'llama_user_id' => (string) $userId,
                'stripe_subscription_id' => $subscriptionId,
                'purpose' => 'membership_payment_method_update',
            ],
        ]);

    $clientSecret = trim((string) ($setupIntent->client_secret ?? ''));

    if ($clientSecret === '') {
        throw new RuntimeException('Stripe did not return a SetupIntent client secret.');
    }
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'stripe_payment_method_setup_intent',
        ['user_id' => $userId]
    );

    $pageError = llama_error_message_with_reference(
        'Secure payment-method update could not be started.',
        $reference
    );
}

function payment_method_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Update Payment Method | Llama Scout';
$pageRobots = 'noindex,nofollow';
$pageDescription = '';

require dirname(__DIR__) . '/partials/header.php';
?>
<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/billing.css"
>
<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/checkout.css"
>
<?php if ($clientSecret !== ''): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<section class="billing-page checkout-page">
<header class="billing-page-header checkout-page-header">
    <a class="billing-back-link" href="/billing.php">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Membership & billing
    </a>
    <p class="eyebrow">Secure payment method</p>
    <h1>Update your payment method.</h1>
    <p>
        The payment form stays inside Llama Scout. Stripe securely receives the card details and Llama Scout never stores the full number.
    </p>
</header>

<?php if ($pageError !== ''): ?>
<div class="billing-error-card checkout-error-card">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <h2>Payment method could not open.</h2>
    <p><?= payment_method_e($pageError) ?></p>
    <a class="billing-primary-button" href="/billing.php">Return to billing</a>
</div>
<?php else: ?>
<div class="checkout-shell payment-method-shell">
    <aside class="checkout-summary-card">
        <p class="eyebrow">Payment security</p>
        <h2>Your card details stay with Stripe.</h2>
        <ul class="checkout-trust-list">
            <li><i class="fa-solid fa-lock" aria-hidden="true"></i> Encrypted Stripe payment fields</li>
            <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Llama Scout never stores the full card number</li>
            <li><i class="fa-solid fa-receipt" aria-hidden="true"></i> Used for future membership invoices</li>
        </ul>
        <div class="checkout-secure-note">
            <i class="fa-brands fa-stripe" aria-hidden="true"></i>
            <span>Secure payment processing by Stripe</span>
        </div>
    </aside>

    <main class="checkout-form-card">
        <form id="payment-method-form" novalidate>
            <div
                id="payment-element"
                class="checkout-embed"
                data-publishable-key="<?= payment_method_e($publishableKey) ?>"
                data-client-secret="<?= payment_method_e($clientSecret) ?>"
            >
                <div class="checkout-loading" id="payment-method-loading">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Loading secure payment formâ¦
                </div>
            </div>

            <div id="payment-method-message" class="checkout-load-error" role="alert" hidden></div>

            <button id="payment-method-submit" class="billing-primary-button payment-method-submit" type="submit" disabled>
                <span class="payment-method-submit-label">Save payment method</span>
                <span class="payment-method-submit-working" hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Savingâ¦
                </span>
            </button>
        </form>
    </main>
</div>
<script src="https://llamascout.com/js/payment-method.js" defer></script>
<?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$setupIntentId = trim((string) ($_GET['setup_intent'] ?? ''));
$db = db();

$status = 'error';
$message = 'The payment method could not be confirmed.';

try {
    if ($setupIntentId === '') {
        throw new InvalidArgumentException('Stripe SetupIntent ID is missing.');
    }

    $stmt = $db->prepare(
        'SELECT stripe_customer_id, stripe_subscription_id
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new RuntimeException('Signed-in account could not be loaded.');
    }

    $expectedCustomerId = trim((string) ($account['stripe_customer_id'] ?? ''));
    $subscriptionId = trim((string) ($account['stripe_subscription_id'] ?? ''));

    if ($expectedCustomerId === '') {
        throw new RuntimeException('No Stripe customer is attached to this account.');
    }

    $stripe = llama_stripe_client();
    $setupIntent = $stripe->setupIntents->retrieve($setupIntentId, []);

    $intentUserId = (int) ($setupIntent->metadata->llama_user_id ?? 0);
    $intentCustomerId = trim((string) ($setupIntent->customer ?? ''));
    $intentStatus = strtolower(trim((string) ($setupIntent->status ?? '')));

    if ($intentUserId !== $userId || $intentCustomerId !== $expectedCustomerId) {
        throw new RuntimeException('Payment-method setup does not belong to the signed-in account.');
    }

    if ($intentStatus !== 'succeeded') {
        throw new RuntimeException('Payment-method setup is not complete.');
    }

    $paymentMethodId = trim((string) ($setupIntent->payment_method ?? ''));

    if ($paymentMethodId === '') {
        throw new RuntimeException('Stripe SetupIntent did not contain a payment method.');
    }

    $stripe->customers->update($expectedCustomerId, [
        'invoice_settings' => [
            'default_payment_method' => $paymentMethodId,
        ],
    ]);

    if ($subscriptionId !== '') {
        $subscription = $stripe->subscriptions->update($subscriptionId, [
            'default_payment_method' => $paymentMethodId,
        ]);

        llama_sync_stripe_subscription($db, $subscription, $userId);
    }

    $status = 'success';
    $message = 'Your payment method has been updated for future membership invoices.';
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'stripe_payment_method_return',
        [
            'user_id' => $userId,
            'setup_intent' => $setupIntentId,
        ]
    );

    $message = llama_error_message_with_reference(
        'We could not confirm the payment-method update.',
        $reference
    );
}

function payment_return_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Payment Method | Llama Scout';
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
<section class="billing-page checkout-return-page">
    <div class="checkout-return-card is-<?= payment_return_e($status) ?>">
        <div class="checkout-return-icon">
            <i class="fa-solid <?= $status === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
        </div>
        <p class="eyebrow">Payment method</p>
        <h1><?= $status === 'success' ? 'Updated.' : 'Update problem' ?></h1>
        <p><?= payment_return_e($message) ?></p>
        <div class="checkout-return-actions">
            <a class="billing-primary-button" href="/billing.php">Return to billing</a>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

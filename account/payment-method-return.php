<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$db = db();

$status = 'error';
$message = 'The payment method could not be confirmed.';

try {
    if ($sessionId === '') {
        throw new InvalidArgumentException('Stripe Checkout Session ID is missing.');
    }

    $session = llama_stripe_client()
        ->checkout
        ->sessions
        ->retrieve($sessionId, []);

    $sessionUserId = (int) (
        $session->client_reference_id
        ?? $session->metadata->llama_user_id
        ?? 0
    );

    if ($sessionUserId !== $userId) {
        throw new RuntimeException('Payment-method session does not belong to the signed-in account.');
    }

    if (strtolower((string) ($session->status ?? '')) !== 'complete') {
        throw new RuntimeException('Payment-method setup is not complete.');
    }

    $setupIntentId = trim((string) ($session->setup_intent ?? ''));
    $customerId = trim((string) ($session->customer ?? ''));

    if ($setupIntentId === '' || $customerId === '') {
        throw new RuntimeException('Completed setup session is missing Stripe billing references.');
    }

    $setupIntent = llama_stripe_client()
        ->setupIntents
        ->retrieve($setupIntentId, []);

    $paymentMethodId = trim((string) ($setupIntent->payment_method ?? ''));

    if ($paymentMethodId === '') {
        throw new RuntimeException('Stripe SetupIntent did not contain a payment method.');
    }

    $stripe = llama_stripe_client();

    $stripe->customers->update($customerId, [
        'invoice_settings' => [
            'default_payment_method' => $paymentMethodId,
        ],
    ]);

    $subscriptionId = trim((string) ($session->metadata->stripe_subscription_id ?? ''));

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
            'session_id' => $sessionId,
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
<link rel="stylesheet" href="https://llamascout.com/css/account-billing.css">
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

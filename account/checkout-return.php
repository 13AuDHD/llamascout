<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$db = db();

$status = 'processing';
$message = 'Stripe is confirming your membership. This usually takes only a moment.';
$reference = '';

if ($sessionId === '') {
    http_response_code(400);
    $status = 'error';
    $message = 'The checkout return did not include a Stripe session.';
} else {
    try {
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
            throw new RuntimeException(
                'Checkout Session does not belong to the signed-in Llama Scout account.'
            );
        }

        $sessionStatus = strtolower(trim((string) ($session->status ?? '')));
        $paymentStatus = strtolower(trim((string) ($session->payment_status ?? '')));
        $subscriptionId = trim((string) ($session->subscription ?? ''));

        if ($sessionStatus === 'complete' && $subscriptionId !== '') {
            $subscription = llama_stripe_client()
                ->subscriptions
                ->retrieve($subscriptionId, []);

            llama_sync_stripe_subscription(
                $db,
                $subscription,
                $userId
            );

            if (in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
                $status = 'success';
                $message = 'Your Llama Scout membership is active.';
            } else {
                $status = 'processing';
                $message = 'Checkout is complete and Stripe is still confirming the payment.';
            }
        } elseif ($sessionStatus === 'expired') {
            $status = 'expired';
            $message = 'This checkout session expired before the membership was completed.';
        }
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'stripe_checkout_return',
            [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]
        );

        http_response_code(500);
        $status = 'error';
        $message = llama_error_message_with_reference(
            'We could not confirm the checkout status yet.',
            $reference
        );
    }
}

function checkout_return_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Membership Checkout | Llama Scout';
$pageRobots = 'noindex,nofollow';
$pageDescription = '';

require dirname(__DIR__) . '/partials/header.php';
?>
<link rel="stylesheet" href="https://llamascout.com/css/account-billing.css">

<section class="billing-page checkout-return-page">
    <div class="checkout-return-card is-<?= checkout_return_e($status) ?>">
        <div class="checkout-return-icon">
            <?php if ($status === 'success'): ?>
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <?php elseif ($status === 'processing'): ?>
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <?php else: ?>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <?php endif; ?>
        </div>

        <p class="eyebrow">Membership checkout</p>
        <h1><?= $status === 'success' ? 'You’re in.' : 'Checkout update' ?></h1>
        <p><?= checkout_return_e($message) ?></p>

        <div class="checkout-return-actions">
            <a class="billing-primary-button" href="/billing.php">
                Membership & billing
            </a>
            <a class="billing-secondary-button" href="https://llamascout.com/">
                Return to Llama Scout
            </a>
        </div>
    </div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

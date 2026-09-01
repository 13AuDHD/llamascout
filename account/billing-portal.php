<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

$stmt = $db->prepare(
    'SELECT
        id,
        stripe_customer_id
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

$customerId =
    trim(
        (string) (
            $account[
                'stripe_customer_id'
            ]
            ?? ''
        )
    );

if ($customerId === '') {
    header(
        'Location: /billing.php',
        true,
        303
    );

    exit;
}

try {
    $portalSession =
        llama_stripe_client()
            ->billingPortal
            ->sessions
            ->create([
                'customer' =>
                    $customerId,

                'return_url' =>
                    'https://account.llamascout.com/billing.php',
            ]);

    $portalUrl =
        trim(
            (string) (
                $portalSession->url
                ?? ''
            )
        );

    if ($portalUrl === '') {
        throw new RuntimeException(
            'Stripe did not return a billing portal URL.'
        );
    }

    header(
        'Location: ' .
        $portalUrl,
        true,
        303
    );

    exit;
} catch (Throwable $exception) {
    error_log(
        'Llama Scout Stripe portal error for user #' .
        $userId .
        ': ' .
        $exception->getMessage()
    );

    http_response_code(500);
}

$pageTitle =
    'Billing Portal Error | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageDescription = '';

require dirname(__DIR__) .
    '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account-billing.css"
>

<section class="billing-page">

<div class="billing-error-card">

<i
    class="fa-solid fa-triangle-exclamation"
    aria-hidden="true"
></i>

<h1>
    The billing portal could not open.
</h1>

<p>
    No billing or membership changes were made.
    Please try again in a moment.
</p>

<a
    class="billing-primary-button"
    href="/billing.php"
>
    Return to billing
</a>

</div>

</section>

<?php
require dirname(__DIR__) .
    '/partials/footer.php';
?>

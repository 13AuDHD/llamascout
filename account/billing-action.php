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

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$allowedActions = [
    'change_plan',
    'cancel_subscription',
    'manage_billing',
    'reactivate_subscription',
];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    exit('That billing action is not available.');
}

$stmt = $db->prepare(
    'SELECT
        id,
        stripe_customer_id,
        stripe_subscription_id,
        membership_status,
        membership_interval,
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

$customerId = trim((string) ($account['stripe_customer_id'] ?? ''));
$subscriptionId = trim((string) ($account['stripe_subscription_id'] ?? ''));
$returnUrl = 'https://account.llamascout.com/billing.php';

try {
    if ($action === 'reactivate_subscription') {
        if ($subscriptionId === '') {
            throw new RuntimeException('No Stripe subscription is available to reactivate.');
        }

        $subscription = llama_stripe_client()
            ->subscriptions
            ->update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);

        llama_sync_stripe_subscription($db, $subscription, $userId);

        header('Location: /billing.php?billing=reactivated', true, 303);
        exit;
    }

    if ($customerId === '') {
        throw new RuntimeException('No Stripe customer is attached to this account.');
    }

    $flowData = null;

    if ($action === 'cancel_subscription') {
        if ($subscriptionId === '') {
            throw new RuntimeException('No Stripe subscription is available to cancel.');
        }

        $flowData = [
            'type' => 'subscription_cancel',
            'subscription_cancel' => [
                'subscription' => $subscriptionId,
            ],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'return_url' => $returnUrl . '?billing=cancellation-updated',
                ],
            ],
        ];
    } elseif ($action === 'change_plan') {
        if ($subscriptionId === '') {
            throw new RuntimeException('No Stripe subscription is available to change.');
        }

        $interval = strtolower(trim((string) ($_POST['interval'] ?? '')));

        if (!in_array($interval, [
            LLAMA_MEMBERSHIP_INTERVAL_MONTHLY,
            LLAMA_MEMBERSHIP_INTERVAL_ANNUAL,
        ], true)) {
            throw new InvalidArgumentException('That membership plan is not valid.');
        }

        $offer = llama_membership_plan_offer($db, $interval);
        $targetPlan = $offer['plan'] ?? null;
        $targetPriceId = trim((string) ($targetPlan['stripe_price_id'] ?? ''));

        if (!$targetPlan || $targetPriceId === '') {
            throw new RuntimeException('The selected membership plan is not available for billing changes.');
        }

        $subscription = llama_stripe_client()
            ->subscriptions
            ->retrieve($subscriptionId, []);

        $itemId = trim((string) ($subscription->items->data[0]->id ?? ''));

        if ($itemId === '') {
            throw new RuntimeException('The Stripe subscription does not contain an updatable membership item.');
        }

        $flowData = [
            'type' => 'subscription_update_confirm',
            'subscription_update_confirm' => [
                'subscription' => $subscriptionId,
                'items' => [[
                    'id' => $itemId,
                    'price' => $targetPriceId,
                    'quantity' => 1,
                ]],
            ],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'return_url' => $returnUrl . '?billing=plan-updated',
                ],
            ],
        ];
    }

    $portalUrl = llama_stripe_portal_flow_url(
        $customerId,
        $returnUrl,
        $flowData
    );

    header('Location: ' . $portalUrl, true, 303);
    exit;
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'stripe_billing_action',
        [
            'user_id' => $userId,
            'action' => $action,
            'interval' => (string) ($_POST['interval'] ?? ''),
        ]
    );

    $_SESSION['billing_error'] = llama_error_message_with_reference(
        'The billing action could not be started. No membership change was made by Llama Scout.',
        $reference
    );

    header('Location: /billing.php', true, 303);
    exit;
}

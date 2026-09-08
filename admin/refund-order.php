<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/shop-return-refunds.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$orderId = (int) (
    $_GET['id']
    ?? $_POST['order_id']
    ?? 0
);

if ($orderId < 1) {
    header('Location: /orders.php');
    exit;
}

$order = admin_shop_order(
    $db,
    $orderId
);

if (!$order) {
    header('Location: /orders.php');
    exit;
}

if (!function_exists('shop_reconcile_full_refund_from_stripe')) {
    function shop_reconcile_full_refund_from_stripe(
        PDO $db,
        int $orderId,
        int $actorUserId
    ): array {
        if ($orderId < 1) {
            throw new InvalidArgumentException(
                'A valid Shop order is required.'
            );
        }

        if (!shop_refund_table_exists($db)) {
            throw new RuntimeException(
                'Shop refund database migration is missing.'
            );
        }

        if (!shop_refund_restock_table_exists($db)) {
            throw new RuntimeException(
                'Shop inventory restock database migration is missing.'
            );
        }

        $orderStmt = $db->prepare(
            'SELECT *
             FROM shop_orders
             WHERE id = ?
             LIMIT 1'
        );
        $orderStmt->execute([$orderId]);

        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new InvalidArgumentException(
                'Shop order not found.'
            );
        }

        $paymentIntentId = trim(
            (string) ($order['stripe_payment_intent_id'] ?? '')
        );

        if ($paymentIntentId === '') {
            throw new RuntimeException(
                'This order does not have a Stripe PaymentIntent.'
            );
        }

        $orderTotal = max(
            0,
            (int) ($order['total_cents'] ?? 0)
        );

        if ($orderTotal < 1) {
            throw new RuntimeException(
                'This order does not have a valid total.'
            );
        }

        $refundList = llama_stripe_client()
            ->refunds
            ->all([
                'payment_intent' => $paymentIntentId,
                'limit' => 100,
            ]);

        $stripeRefunds = [];

        foreach ($refundList->data ?? [] as $stripeRefund) {
            $refundId = trim(
                (string) ($stripeRefund->id ?? '')
            );

            if ($refundId !== '') {
                $stripeRefunds[] = $stripeRefund;
            }
        }

        if (!$stripeRefunds) {
            throw new InvalidArgumentException(
                'Stripe does not show any refund for this order PaymentIntent.'
            );
        }

        $successfulRefunds = array_values(
            array_filter(
                $stripeRefunds,
                static function ($refund): bool {
                    return strtolower(
                        trim((string) ($refund->status ?? ''))
                    ) === 'succeeded';
                }
            )
        );

        if (!$successfulRefunds) {
            throw new InvalidArgumentException(
                'Stripe does not show a succeeded refund for this order.'
            );
        }

        if (count($successfulRefunds) !== 1) {
            throw new InvalidArgumentException(
                'Stripe shows multiple succeeded refunds for this order. Automatic reconciliation is not safe.'
            );
        }

        $stripeRefund = $successfulRefunds[0];

        $refundId = trim(
            (string) ($stripeRefund->id ?? '')
        );

        $refundAmount = max(
            0,
            (int) ($stripeRefund->amount ?? 0)
        );

        if ($refundAmount !== $orderTotal) {
            throw new InvalidArgumentException(
                'Stripe shows a succeeded refund, but it is not a full-order refund. Automatic reconciliation is not safe.'
            );
        }

        $refundPaymentIntent = trim(
            (string) ($stripeRefund->payment_intent ?? '')
        );

        if (
            $refundPaymentIntent !== ''
            && $refundPaymentIntent !== $paymentIntentId
        ) {
            throw new RuntimeException(
                'Stripe refund does not belong to this order PaymentIntent.'
            );
        }

        $existing = shop_refund_for_order(
            $db,
            $orderId
        );

        if ($existing) {
            $existingRefundId = trim(
                (string) ($existing['stripe_refund_id'] ?? '')
            );

            if (
                $existingRefundId !== ''
                && $existingRefundId !== $refundId
            ) {
                throw new InvalidArgumentException(
                    'This order already contains a different local refund record. Automatic reconciliation is not safe.'
                );
            }
        }

        $currency = strtolower(
            trim(
                (string) (
                    $stripeRefund->currency
                    ?? $order['currency']
                    ?? 'usd'
                )
            )
        );

        $reason = trim(
            (string) ($stripeRefund->reason ?? '')
        );

        if (
            !in_array(
                $reason,
                [
                    'requested_by_customer',
                    'duplicate',
                    'fraudulent',
                ],
                true
            )
        ) {
            $reason = 'requested_by_customer';
        }

        $stmt = $db->prepare(
            'INSERT INTO shop_refunds
             (
                order_id,
                stripe_refund_id,
                stripe_payment_intent_id,
                amount_cents,
                currency,
                reason,
                status,
                failure_reason,
                requested_by,
                requested_at,
                updated_at
             )
             VALUES
             (
                ?, ?, ?, ?, ?, ?, "succeeded", NULL, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                stripe_refund_id = VALUES(stripe_refund_id),
                stripe_payment_intent_id = VALUES(stripe_payment_intent_id),
                amount_cents = VALUES(amount_cents),
                currency = VALUES(currency),
                reason = VALUES(reason),
                status = "succeeded",
                failure_reason = NULL,
                requested_by = VALUES(requested_by),
                updated_at = UTC_TIMESTAMP()'
        );

        $stmt->execute([
            $orderId,
            $refundId,
            $paymentIntentId,
            $refundAmount,
            $currency,
            $reason,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        shop_refund_apply_order_status(
            $db,
            $orderId,
            'succeeded',
            $refundId
        );

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                !empty($order['user_id'])
                    ? (int) $order['user_id']
                    : null,
                'shop.order_refund_reconciled',
                'Reconciled existing Stripe full refund for order '
                    . (string) ($order['order_number'] ?? $orderId)
                    . '.',
                [
                    'order_id' => $orderId,
                    'stripe_refund_id' => $refundId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'amount_cents' => $refundAmount,
                ]
            );
        }

        return [
            'refund_id' => $refundId,
            'status' => 'succeeded',
            'amount_cents' => $refundAmount,
            'currency' => $currency,
        ];
    }
}

$error = '';
$notice = '';

$refund = shop_refund_for_order(
    $db,
    $orderId
);

$refundBlocker = shop_return_aware_refund_blocker(
    $db,
    $orderId
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $refundAction = strtolower(
                trim(
                    (string) ($_POST['refund_action'] ?? 'issue')
                )
            );

            if ($refundBlocker !== null) {
                throw new InvalidArgumentException(
                    $refundBlocker
                );
            }

            if ($refundAction === 'reconcile') {
                $result = shop_reconcile_full_refund_from_stripe(
                    $db,
                    $orderId,
                    (int) ($adminUser['id'] ?? 0)
                );

                $notice =
                    'Existing succeeded full Stripe refund was verified and reconciled with Llama Scout.';
            } elseif ($refundAction === 'issue') {
                $result = shop_issue_return_aware_full_refund(
                    $db,
                    $orderId,
                    (int) ($adminUser['id'] ?? 0),
                    (string) (
                        $_POST['refund_reason']
                        ?? 'requested_by_customer'
                    )
                );

                $notice =
                    $result['status'] === 'succeeded'
                        ? 'Stripe refund completed and committed tracked inventory was returned to stock.'
                        : 'Stripe accepted the refund. Current status: '
                            . ucfirst((string) $result['status'])
                            . '. Inventory will return to stock when Stripe confirms the refund succeeded.';
            } else {
                throw new InvalidArgumentException(
                    'Choose a valid refund action.'
                );
            }

            $order = admin_shop_order(
                $db,
                $orderId
            );

            $refund = shop_refund_for_order(
                $db,
                $orderId
            );

            $refundBlocker = shop_return_aware_refund_blocker(
                $db,
                $orderId
            );
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.shop_refund',
                ['order_id' => $orderId],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The refund could not be processed.',
                    $reference
                );
        }
    }
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Refund ' .
    (string) $order['order_number'];

$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'orders';

$adminPageActions =
    '<a class="admin-button" href="/order.php?id='
    . (int) $orderId
    . '">Back to order</a>'
    . '<a class="admin-button" href="/return-order.php?id='
    . (int) $orderId
    . '">Receive return</a>';

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Stripe Refund</p>
        <h2><?= moderation_e(
            (string) $order['order_number']
        ) ?></h2>
    </div>

    <strong>
        <?= moderation_e(
            admin_shop_money(
                (int) $order['total_cents'],
                (string) $order['currency']
            )
        ) ?>
    </strong>
</header>

<?php if ($refund): ?>

<div class="admin-user-action-box">
    <p>
        <strong>Refund status:</strong>
        <?= moderation_e(
            ucfirst((string) $refund['status'])
        ) ?>
    </p>

    <p>
        Stripe refund:
        <strong><?= moderation_e(
            (string) $refund['stripe_refund_id']
        ) ?></strong>
    </p>

    <p>
        Amount:
        <strong><?= moderation_e(
            admin_shop_money(
                (int) $refund['amount_cents'],
                (string) $refund['currency']
            )
        ) ?></strong>
    </p>

    <?php if (!empty($refund['failure_reason'])): ?>
    <p>
        Failure:
        <?= moderation_e(
            (string) $refund['failure_reason']
        ) ?>
    </p>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php
$refundStatus = $refund
    ? strtolower(trim((string) ($refund['status'] ?? '')))
    : '';

$localRefundComplete =
    $refundStatus === 'succeeded'
    && (string) $order['payment_status'] === 'refunded'
    && (string) $order['order_status'] === 'refunded';

$canIssueRefund =
    (string) $order['payment_status'] === 'paid'
    && $refundBlocker === null
    && (
        !$refund
        || in_array(
            $refundStatus,
            ['failed', 'canceled', 'cancelled'],
            true
        )
    );

$canAttemptReconciliation =
    !$localRefundComplete
    && $refundBlocker === null
    && trim(
        (string) ($order['stripe_payment_intent_id'] ?? '')
    ) !== '';
?>

<?php if ($canIssueRefund): ?>

<div class="admin-user-action-box">
    <p>
        This sends a <strong>full refund</strong> through Stripe
        to the original payment method.
    </p>

    <p>
        When Stripe confirms the refund succeeded, any committed
        tracked inventory from this order is automatically returned
        to sellable stock. The inventory restock ledger prevents the
        same quantity from being restored more than once.
    </p>
</div>

<form method="post" class="admin-user-action-box">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(
            moderation_csrf_token()
        ) ?>"
    >

    <input
        type="hidden"
        name="order_id"
        value="<?= (int) $orderId ?>"
    >

    <input
        type="hidden"
        name="refund_action"
        value="issue"
    >

    <label>
        <span>Refund reason</span>

        <select name="refund_reason">
            <option value="requested_by_customer">
                Requested by customer
            </option>
            <option value="duplicate">
                Duplicate payment
            </option>
            <option value="fraudulent">
                Fraudulent payment
            </option>
        </select>
    </label>

    <button
        class="admin-button"
        type="submit"
    >
        Issue full Stripe refund
    </button>
</form>

<?php endif; ?>

<?php if ($canAttemptReconciliation): ?>

<div class="admin-user-action-box">
    <strong>
        Already refunded directly in Stripe?
    </strong>

    <p>
        Use this only when the refund was performed from the
        Stripe Dashboard instead of Llama Scout.
    </p>

    <p>
        Llama Scout checks the order's PaymentIntent directly with
        Stripe. Reconciliation succeeds only when Stripe shows exactly
        one succeeded refund equal to the full order total.
    </p>

    <p>
        Partial refunds, multiple succeeded refunds, mismatched
        PaymentIntents, and ambiguous refund history are rejected and
        left for manual review.
    </p>

    <form method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(
                moderation_csrf_token()
            ) ?>"
        >

        <input
            type="hidden"
            name="order_id"
            value="<?= (int) $orderId ?>"
        >

        <input
            type="hidden"
            name="refund_action"
            value="reconcile"
        >

        <button
            class="admin-button"
            type="submit"
        >
            <i
                class="fa-solid fa-arrows-rotate"
                aria-hidden="true"
            ></i>
            Check Stripe and reconcile refund
        </button>
    </form>
</div>

<?php endif; ?>

<?php if (
    (string) $order['payment_status'] === 'paid'
    && $refundBlocker !== null
): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>
    <h3>Refund paused</h3>
    <p>
        <?= moderation_e($refundBlocker) ?>
    </p>
    <p>
        If the merchandise has already shipped or was delivered,
        record it as physically returned before issuing or reconciling
        the refund. If it has not shipped, cancel or resolve
        fulfillment first.
    </p>
    <p>
        <a
            class="admin-button"
            href="/return-order.php?id=<?= (int) $orderId ?>"
        >
            Receive returned merchandise
        </a>
    </p>
</div>

<?php elseif ($localRefundComplete): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-circle-check"
        aria-hidden="true"
    ></i>
    <h3>Refunded</h3>
    <p>
        Stripe has completed the full refund for this order and any
        committed tracked inventory has been returned to stock.
    </p>
</div>

<?php elseif (
    !$canIssueRefund
    && !$canAttemptReconciliation
    && $refundBlocker === null
): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-circle-info"
        aria-hidden="true"
    ></i>
    <h3>Refund unavailable</h3>
    <p>
        This order is not currently in a state where a new full refund
        or safe Stripe reconciliation can be performed.
    </p>
</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>

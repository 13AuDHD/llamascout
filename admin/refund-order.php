<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/shop-refunds.php';
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

$error = '';
$notice = '';
$refund = shop_refund_for_order(
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
            $result = shop_issue_full_refund(
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
                    ? 'Stripe refund completed.'
                    : 'Stripe accepted the refund. Current status: '
                        . ucfirst(
                            (string) $result['status']
                        )
                        . '.';

            $order = admin_shop_order(
                $db,
                $orderId
            );

            $refund = shop_refund_for_order(
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
    . '">Back to order</a>';

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
            ucfirst(
                (string) $refund['status']
            )
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


<?php if (
    (string) $order['payment_status'] === 'paid'
    && (
        !$refund
        || in_array(
            (string) $refund['status'],
            ['failed','canceled','cancelled'],
            true
        )
    )
): ?>

<div class="admin-user-action-box">
    <p>
        This sends a <strong>full refund</strong> through Stripe
        to the original payment method.
    </p>

    <p>
        Inventory is not automatically restocked. A refund does
        not prove that physical merchandise has been returned.
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

<?php elseif (
    (string) $order['payment_status'] === 'refunded'
): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-circle-check"
        aria-hidden="true"
    ></i>
    <h3>Refunded</h3>
    <p>
        Stripe has completed the refund for this order.
    </p>
</div>

<?php else: ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-circle-info"
        aria-hidden="true"
    ></i>
    <h3>Refund unavailable</h3>
    <p>
        This order is not currently in a refundable paid state.
    </p>
</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>

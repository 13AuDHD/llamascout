<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/admin-fulfillment.php';
require_once dirname(__DIR__) . '/app/printful-cancellation.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$notice = '';
$error = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim(
                (string) (
                    $_POST[
                        'printful_order_action'
                    ]
                    ?? ''
                )
            );

            if ($action === 'cancel') {
                $result =
                    llama_printful_cancel_fulfillment(
                        $db,
                        (int) (
                            $_POST[
                                'fulfillment_id'
                            ]
                            ?? 0
                        ),
                        $actorUserId
                    );

                $notice =
                    'Printful order #'
                    . $result[
                        'provider_order_id'
                    ]
                    . ' cancelled.';

                if (
                    !empty(
                        $result[
                            'refund_required'
                        ]
                    )
                ) {
                    $notice .=
                        ' The customer is still paid and must now be refunded through Stripe.';
                }
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.printful_order_cancel',
                    [],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Printful order could not be cancelled.',
                        $reference
                    );
        }
    }
}

$fulfillments =
    llama_printful_active_fulfillments(
        $db,
        200
    );

$rows = [];

foreach ($fulfillments as $fulfillment) {
    $providerOrderId = trim(
        (string) (
            $fulfillment[
                'provider_order_id'
            ]
            ?? ''
        )
    );

    $remoteOrder = [];
    $remoteError = '';

    if ($providerOrderId !== '') {
        try {
            $remoteOrder =
                llama_printful_get_order(
                    $providerOrderId
                );
        } catch (Throwable $exception) {
            $remoteError =
                $exception->getMessage();
        }
    }

    $remoteStatus = strtolower(
        trim(
            (string) (
                $remoteOrder['status']
                ?? ''
            )
        )
    );

    $fulfillment[
        '_remote_status'
    ] = $remoteStatus;

    $fulfillment[
        '_remote_error'
    ] = $remoteError;

    $fulfillment[
        '_can_cancel'
    ] =
        $remoteError === ''
        && llama_printful_cancellable_status(
            $remoteStatus
        );

    $rows[] = $fulfillment;
}

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],
    'updates' =>
        $stats['updates'],
    'reports' =>
        $stats['reports'],
    'orders' =>
        $stats['orders'],
    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Printful Orders';

$adminPageEyebrow =
    'Commerce';

$adminActiveNav =
    'printful-orders';

require __DIR__
    . '/_header.php';
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
        <p>Provider Fulfillment</p>
        <h2>Printful Orders</h2>
    </div>

    <a
        class="admin-button"
        href="/printful.php"
    >
        Variant Mapping
    </a>
</header>

<div class="admin-user-notice admin-printful-order-warning">
    <strong>
        Provider cancellation and customer refund are separate.
    </strong>

    <p>
        Cancelling here stops an eligible Printful order and
        returns Printful's charge to Llama Scout. It does not
        refund the customer. Paid customer orders are moved to
        Problem until you complete the Stripe refund.
    </p>
</div>

<?php if (!$rows): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-shirt"
        aria-hidden="true"
    ></i>

    <h3>No Printful orders yet.</h3>

    <p>
        Printful fulfillments will appear here after a Shop order
        has been sent to Printful.
    </p>
</div>

<?php else: ?>

<div class="admin-commerce-orders-table-wrap">

<table class="admin-commerce-orders-table">

<thead>
<tr>
    <th>Llama Scout Order</th>
    <th>Printful Order</th>
    <th>Remote Status</th>
    <th>Local Status</th>
    <th>Payment</th>
    <th>
        <span class="sr-only">
            Actions
        </span>
    </th>
</tr>
</thead>

<tbody>

<?php foreach ($rows as $row): ?>

<?php
$remoteStatus =
    (string) (
        $row['_remote_status']
        ?? ''
    );

$remoteError =
    (string) (
        $row['_remote_error']
        ?? ''
    );

$canCancel =
    !empty(
        $row['_can_cancel']
    );
?>

<tr>

<td data-label="Llama Scout Order">
    <a
        class="admin-commerce-order-number"
        href="/order.php?id=<?= (int) $row['order_id'] ?>"
    >
        <?= moderation_e(
            (string) $row[
                'order_number'
            ]
        ) ?>
    </a>
</td>

<td data-label="Printful Order">
    #<?= moderation_e(
        (string) $row[
            'provider_order_id'
        ]
    ) ?>
</td>

<td data-label="Remote Status">
    <?php if ($remoteError !== ''): ?>
        <span class="admin-status-pill">
            Unavailable
        </span>

        <small class="admin-printful-remote-error">
            <?= moderation_e(
                $remoteError
            ) ?>
        </small>
    <?php else: ?>
        <span class="admin-status-pill">
            <?= moderation_e(
                ucfirst(
                    $remoteStatus !== ''
                        ? $remoteStatus
                        : 'Unknown'
                )
            ) ?>
        </span>
    <?php endif; ?>
</td>

<td data-label="Local Status">
    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) (
                    $row['status']
                    ?? ''
                )
            )
        ) ?>
    </span>
</td>

<td data-label="Payment">
    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) (
                    $row[
                        'payment_status'
                    ]
                    ?? ''
                )
            )
        ) ?>
    </span>
</td>

<td>

<div class="admin-user-form-actions admin-printful-order-actions">

    <a
        class="admin-button"
        href="/order.php?id=<?= (int) $row['order_id'] ?>"
    >
        Manage Order
    </a>

    <?php if (
        (string) (
            $row['payment_status']
            ?? ''
        ) === 'paid'
    ): ?>
        <a
            class="admin-button"
            href="/refund-order.php?id=<?= (int) $row['order_id'] ?>"
        >
            Refund Customer
        </a>
    <?php endif; ?>

    <?php if ($canCancel): ?>

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
                name="fulfillment_id"
                value="<?= (int) $row['id'] ?>"
            >

            <input
                type="hidden"
                name="printful_order_action"
                value="cancel"
            >

            <button
                class="admin-button"
                type="submit"
                onclick="return confirm('Cancel this order at Printful? The customer will NOT be refunded by this action.');"
            >
                Cancel at Printful
            </button>

        </form>

    <?php endif; ?>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<?php
require __DIR__
    . '/_footer.php';
?>

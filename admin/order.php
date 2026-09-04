<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/admin-fulfillment.php';
require_once dirname(__DIR__) . '/app/admin-fulfillment-safe.php';
require_once dirname(__DIR__) . '/app/shipping.php';
require_once dirname(__DIR__) . '/app/printful-orders.php';
require_once dirname(__DIR__) . '/app/printful-sync.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);

$orderId = (int) (
    $_GET['id']
    ?? $_POST['order_id']
    ?? 0
);

if ($orderId < 1) {
    header('Location: /orders.php');
    exit;
}

$notice = '';
$error = '';

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
            $action = (string) (
                $_POST['shop_admin_action'] ?? ''
            );

            if ($action === 'order-status') {
                admin_shop_save_order_status(
                    $db,
                    $actorUserId,
                    $orderId,
                    (string) ($_POST['order_status'] ?? '')
                );

                $notice = 'Order status updated.';
            } elseif ($action === 'create-fulfillment') {
                admin_safe_create_fulfillment(
                    $db,
                    $actorUserId,
                    $orderId,
                    $_POST
                );

                admin_fulfillment_sync_order_status(
                    $db,
                    $orderId
                );

                $notice = 'Fulfillment created.';
            } elseif ($action === 'update-fulfillment') {
                $fulfillmentId =
                    (int) ($_POST['fulfillment_id'] ?? 0);

                admin_fulfillment_validate_status_update(
                    $db,
                    $orderId,
                    $fulfillmentId,
                    $_POST
                );

                admin_shop_update_fulfillment(
                    $db,
                    $actorUserId,
                    $fulfillmentId,
                    $_POST
                );

                admin_fulfillment_sync_order_status(
                    $db,
                    $orderId
                );

                $notice = 'Fulfillment updated.';
            } elseif ($action === 'save-package') {
                admin_fulfillment_save_package(
                    $db,
                    $actorUserId,
                    $orderId,
                    (int) ($_POST['fulfillment_id'] ?? 0),
                    $_POST
                );

                $notice = 'Package details saved.';
            } elseif ($action === 'quote-shipping-rates') {
                $rateCount = admin_fulfillment_quote_rates(
                    $db,
                    $actorUserId,
                    $orderId,
                    (int) ($_POST['fulfillment_id'] ?? 0)
                );

                $notice =
                    number_format($rateCount) .
                    ' shipping rate' .
                    ($rateCount === 1 ? '' : 's') .
                    ' loaded.';
            } elseif ($action === 'buy-shipping-label') {
                $label = admin_fulfillment_buy_label(
                    $db,
                    $actorUserId,
                    $orderId,
                    (int) ($_POST['fulfillment_id'] ?? 0),
                    (int) ($_POST['rate_id'] ?? 0)
                );

                $notice =
                    'Shipping label purchased. Tracking: ' .
                    (string) $label['tracking_code'];
            } elseif ($action === 'refresh-printful') {
                $sync = llama_printful_sync_fulfillment(
                    $db,
                    (int) ($_POST['fulfillment_id'] ?? 0),
                    $actorUserId
                );

                $notice =
                    'Printful fulfillment refreshed. Current status: ' .
                    ucfirst(
                        (string) $sync['local_status']
                    ) .
                    '.';
            } elseif ($action === 'create-printful-order') {
                $printfulOrder = llama_printful_create_fulfillment_order(
                    $db,
                    $actorUserId,
                    (int) ($_POST['fulfillment_id'] ?? 0)
                );

                $printfulStatus = strtolower(
                    trim((string) ($printfulOrder['status'] ?? 'draft'))
                );

                $notice = llama_printful_auto_confirm()
                    ? 'Printful order submitted for fulfillment.'
                    : 'Printful draft order created. No Printful charge was authorized.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.order_update',
                ['order_id' => $orderId],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The order could not be updated.',
                    $reference
                );
        }
    }
}

$order = admin_shop_order(
    $db,
    $orderId
);

if (!$order) {
    header('Location: /orders.php');
    exit;
}

$items = admin_shop_order_items(
    $db,
    $orderId
);

$fulfillments = admin_shop_fulfillments(
    $db,
    $orderId
);

$shippingAddress = [];

if (!empty($order['shipping_address_json'])) {
    $shippingAddress =
        json_decode(
            (string) $order['shipping_address_json'],
            true
        ) ?: [];
}

$fulfillmentProviders = admin_shop_fulfillment_providers();
$trackingCarriers = admin_shop_tracking_carriers();
$packageTypes = admin_fulfillment_package_types();

$defaultFulfillmentProvider = 'llama_scout';
$shippingItemProviders = [];

foreach ($items as $item) {
    if ((int) ($item['requires_shipping'] ?? 0) !== 1) {
        continue;
    }

    $itemProvider = admin_shop_normalize_provider(
        (string) ($item['fulfillment_provider'] ?? '')
    );

    if ($itemProvider === '') {
        $itemProvider = 'llama_scout';
    }

    $shippingItemProviders[$itemProvider] = true;
}

if (count($shippingItemProviders) === 1) {
    $defaultFulfillmentProvider = (string) array_key_first($shippingItemProviders);
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    (string) $order['order_number'];

$adminPageEyebrow = 'Order Administration';
$adminActiveNav = 'orders';

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


<section class="admin-commerce-order-summary">

<div>
    <p>Order</p>
    <h2><?= moderation_e((string) $order['order_number']) ?></h2>

    <span>
        <?= moderation_e((string) $order['created_at']) ?>
        |
        <?= moderation_e(
            (string) (
                $order['shipping_name']
                ?: $order['display_name']
                ?: $order['customer_email']
                ?: 'Guest'
            )
        ) ?>
    </span>
</div>

<strong>
    <?= moderation_e(
        admin_shop_money(
            (int) $order['total_cents'],
            (string) $order['currency']
        )
    ) ?>
</strong>

</section>


<div class="admin-user-detail-grid">

<div class="admin-user-detail-main">


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Order</p>
        <h2>Items</h2>
    </div>
</header>

<div class="admin-commerce-order-items">

<?php foreach ($items as $item): ?>

<article>

<div class="admin-commerce-order-item-image">
    <?php if (!empty($item['image_url'])): ?>
        <img
            src="<?= str_starts_with(
                (string) $item['image_url'],
                'http'
            )
                ? moderation_e((string) $item['image_url'])
                : 'https://llamascout.com' .
                    moderation_e((string) $item['image_url']) ?>"
            alt=""
        >
    <?php else: ?>
        <i class="fa-solid fa-box" aria-hidden="true"></i>
    <?php endif; ?>
</div>

<div>
    <strong>
        <?= moderation_e((string) $item['product_name']) ?>
    </strong>

    <span>
        <?= moderation_e((string) $item['variant_name']) ?>
    </span>

    <small>
        <?= moderation_e((string) $item['sku']) ?>
        | Qty <?= (int) $item['quantity'] ?>
        | <?= moderation_e(
            admin_shop_fulfillment_provider_label(
                (int) ($item['requires_shipping'] ?? 0) === 1
                    && trim((string) ($item['fulfillment_provider'] ?? '')) === ''
                        ? 'llama_scout'
                        : (string) ($item['fulfillment_provider'] ?? '')
            )
        ) ?>
    </small>
</div>

<strong>
    <?= moderation_e(
        admin_shop_money(
            (int) $item['line_total_cents'],
            (string) $item['currency']
        )
    ) ?>
</strong>

</article>

<?php endforeach; ?>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Shipping</p>
        <h2>Fulfillment</h2>
    </div>
</header>

<?php if (!$fulfillments): ?>

<form
    class="admin-commerce-fulfillment-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
<input type="hidden" name="shop_admin_action" value="create-fulfillment">

<label>
    <span>Fulfillment provider</span>
    <select name="fulfillment_provider">
        <?php foreach ($fulfillmentProviders as $providerKey => $providerLabel): ?>
            <?php if ($providerKey === '') { continue; } ?>
            <option
                value="<?= moderation_e($providerKey) ?>"
                <?= $defaultFulfillmentProvider === $providerKey ? 'selected' : '' ?>
            >
                <?= moderation_e($providerLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Status</span>
    <select name="status">
        <?php foreach (
            [
                'pending',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'problem',
                'cancelled',
            ] as $status
        ): ?>
            <option value="<?= moderation_e($status) ?>">
                <?= moderation_e(ucfirst($status)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Provider order ID</span>
    <input
        type="text"
        name="provider_order_id"
    >
</label>

<label>
    <span>Tracking provider</span>
    <select name="tracking_carrier">
        <?php foreach ($trackingCarriers as $carrierKey => $carrierLabel): ?>
            <option value="<?= moderation_e($carrierKey) ?>">
                <?= moderation_e($carrierLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Tracking number</span>
    <input
        type="text"
        name="tracking_number"
        autocomplete="off"
    >
</label>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Create fulfillment
    </button>
</div>

</form>

<?php else: ?>

<div class="admin-commerce-fulfillments">

<?php foreach ($fulfillments as $fulfillment): ?>

<form
    class="admin-commerce-fulfillment-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
<input type="hidden" name="fulfillment_id" value="<?= (int) $fulfillment['id'] ?>">
<input type="hidden" name="shop_admin_action" value="update-fulfillment">

<div class="admin-commerce-fulfillment-heading">
    <strong>
        Fulfillment #<?= (int) $fulfillment['id'] ?>
    </strong>

    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) $fulfillment['status']
            )
        ) ?>
    </span>
</div>

<label>
    <span>Fulfillment provider</span>
    <select name="fulfillment_provider">
        <?php
        $currentProvider = admin_shop_normalize_provider(
            (string) ($fulfillment['fulfillment_provider'] ?? '')
        );
        if ($currentProvider === '') {
            $currentProvider = 'llama_scout';
        }
        ?>
        <?php foreach ($fulfillmentProviders as $providerKey => $providerLabel): ?>
            <?php if ($providerKey === '') { continue; } ?>
            <option
                value="<?= moderation_e($providerKey) ?>"
                <?= $currentProvider === $providerKey ? 'selected' : '' ?>
            >
                <?= moderation_e($providerLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Status</span>
    <select name="status">
        <?php foreach (
            [
                'pending',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'problem',
                'cancelled',
            ] as $status
        ): ?>
            <option
                value="<?= moderation_e($status) ?>"
                <?= $fulfillment['status'] === $status ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($status)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Provider order ID</span>
    <input
        type="text"
        name="provider_order_id"
        value="<?= moderation_e(
            (string) ($fulfillment['provider_order_id'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Tracking provider</span>
    <select name="tracking_carrier">
        <?php
        $currentCarrier = admin_shop_normalize_tracking_carrier(
            (string) ($fulfillment['tracking_carrier'] ?? '')
        );
        ?>
        <?php foreach ($trackingCarriers as $carrierKey => $carrierLabel): ?>
            <option
                value="<?= moderation_e($carrierKey) ?>"
                <?= $currentCarrier === $carrierKey ? 'selected' : '' ?>
            >
                <?= moderation_e($carrierLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Tracking number</span>
    <input
        type="text"
        name="tracking_number"
        value="<?= moderation_e(
            (string) ($fulfillment['tracking_number'] ?? '')
        ) ?>"
        autocomplete="off"
    >
</label>

<?php if (!empty($fulfillment['tracking_url'])): ?>
<div class="admin-commerce-tracking-link">
    <span>Tracking link</span>
    <a
        href="<?= moderation_e((string) $fulfillment['tracking_url']) ?>"
        target="_blank"
        rel="noopener"
    >
        Open <?= moderation_e(
            admin_shop_tracking_carrier_label(
                (string) ($fulfillment['tracking_carrier'] ?? '')
            )
        ) ?> tracking
        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
    </a>
</div>
<?php endif; ?>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Update fulfillment
    </button>
</div>

</form>

<?php
$providerKey = admin_shop_normalize_provider(
    (string) ($fulfillment['fulfillment_provider'] ?? '')
);
?>

<?php if ($providerKey === 'printful'): ?>
<?php
$printfulRemoteOrder = [];
$printfulRemoteError = '';
$printfulProviderOrderId = trim(
    (string) ($fulfillment['provider_order_id'] ?? '')
);

if ($printfulProviderOrderId !== '') {
    try {
        $printfulRemoteOrder = llama_printful_get_order(
            $printfulProviderOrderId
        );
    } catch (Throwable $exception) {
        $printfulRemoteError = $exception->getMessage();
    }
}
?>

<div class="admin-commerce-provider-box">

<div>
    <i class="fa-solid fa-shirt" aria-hidden="true"></i>

    <div>
        <strong>Printful Fulfillment</strong>

        <?php if ($printfulProviderOrderId === ''): ?>
        <span>
            This fulfillment has not been sent to Printful yet.
        </span>
        <?php elseif ($printfulRemoteError !== ''): ?>
        <span>
            Printful order #<?= moderation_e($printfulProviderOrderId) ?>
            could not be refreshed.
        </span>
        <?php else: ?>
        <span>
            Printful order #<?= moderation_e($printfulProviderOrderId) ?>
            | <?= moderation_e(
                ucfirst(
                    (string) ($printfulRemoteOrder['status'] ?? 'Unknown')
                )
            ) ?>
        </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($printfulProviderOrderId === ''): ?>
<form method="post">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >
    <input
        type="hidden"
        name="order_id"
        value="<?= (int) $orderId ?>"
    >
    <input
        type="hidden"
        name="fulfillment_id"
        value="<?= (int) $fulfillment['id'] ?>"
    >
    <input
        type="hidden"
        name="shop_admin_action"
        value="create-printful-order"
    >

    <button
        class="admin-button"
        type="submit"
    >
        <?= llama_printful_auto_confirm()
            ? 'Send to Printful'
            : 'Create Printful draft' ?>
    </button>
</form>
<?php else: ?>

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
        name="fulfillment_id"
        value="<?= (int) $fulfillment['id'] ?>"
    >

    <input
        type="hidden"
        name="shop_admin_action"
        value="refresh-printful"
    >

    <button
        class="admin-button"
        type="submit"
    >
        <i
            class="fa-solid fa-arrows-rotate"
            aria-hidden="true"
        ></i>

        Refresh from Printful
    </button>
</form>

<?php if (!empty($printfulRemoteOrder['dashboard_url'])): ?>
<a
    class="admin-button"
    href="<?= moderation_e(
        (string) $printfulRemoteOrder['dashboard_url']
    ) ?>"
    target="_blank"
    rel="noopener"
>
    Open in Printful
</a>
<?php endif; ?>

<?php endif; ?>

</div>

<?php if ($printfulRemoteError !== ''): ?>
<p class="admin-commerce-provider-error">
    <?= moderation_e($printfulRemoteError) ?>
</p>
<?php endif; ?>

<?php endif; ?>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<?php if ($fulfillments): ?>
<?php foreach ($fulfillments as $fulfillment): ?>
<?php
$currentProvider = admin_shop_normalize_provider(
    (string) ($fulfillment['fulfillment_provider'] ?? '')
);

if ($currentProvider === '') {
    $currentProvider = 'llama_scout';
}

$package = $currentProvider === 'llama_scout'
    ? admin_fulfillment_package(
        $db,
        (int) $fulfillment['id']
    )
    : null;
?>

<?php if ($currentProvider === 'llama_scout'): ?>
<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Llama Scout Fulfillment</p>
        <h2>Package &amp; Label</h2>
    </div>

    <span>
        Fulfillment #<?= (int) $fulfillment['id'] ?>
    </span>
</header>

<form
    class="admin-commerce-package-form"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>
<input
    type="hidden"
    name="order_id"
    value="<?= (int) $orderId ?>"
>
<input
    type="hidden"
    name="fulfillment_id"
    value="<?= (int) $fulfillment['id'] ?>"
>
<input
    type="hidden"
    name="shop_admin_action"
    value="save-package"
>

<div class="admin-commerce-package-grid">

<label>
    <span>Package type</span>
    <select name="package_type" required>
        <?php foreach ($packageTypes as $typeKey => $typeLabel): ?>
            <option
                value="<?= moderation_e($typeKey) ?>"
                <?= ($package['package_type'] ?? 'poly_mailer') === $typeKey
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($typeLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Weight</span>
    <div class="admin-commerce-measure-input">
        <input
            type="number"
            name="weight_oz"
            min="0.01"
            max="9999"
            step="0.01"
            inputmode="decimal"
            required
            value="<?= moderation_e(
                (string) ($package['weight_oz'] ?? '')
            ) ?>"
        >
        <span>oz</span>
    </div>
</label>

<label>
    <span>Length</span>
    <div class="admin-commerce-measure-input">
        <input
            type="number"
            name="length_in"
            min="0.01"
            max="9999"
            step="0.01"
            inputmode="decimal"
            value="<?= moderation_e(
                (string) ($package['length_in'] ?? '')
            ) ?>"
        >
        <span>in</span>
    </div>
</label>

<label>
    <span>Width</span>
    <div class="admin-commerce-measure-input">
        <input
            type="number"
            name="width_in"
            min="0.01"
            max="9999"
            step="0.01"
            inputmode="decimal"
            value="<?= moderation_e(
                (string) ($package['width_in'] ?? '')
            ) ?>"
        >
        <span>in</span>
    </div>
</label>

<label>
    <span>Height</span>
    <div class="admin-commerce-measure-input">
        <input
            type="number"
            name="height_in"
            min="0.01"
            max="9999"
            step="0.01"
            inputmode="decimal"
            value="<?= moderation_e(
                (string) ($package['height_in'] ?? '')
            ) ?>"
        >
        <span>in</span>
    </div>
</label>

</div>

<label class="admin-commerce-package-notes">
    <span>Internal packing notes</span>
    <textarea
        name="package_notes"
        rows="3"
        maxlength="2000"
        placeholder="Example: Include sticker pack. Fold bandanna flat."
    ><?= moderation_e(
        (string) ($package['internal_notes'] ?? '')
    ) ?></textarea>
</label>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save package
    </button>
</div>

</form>

<?php
$shippingConfigured =
    llama_shipping_easypost_configured();

$shippingRates =
    admin_fulfillment_rate_rows(
        $db,
        (int) $fulfillment['id']
    );

$shippingLabel =
    admin_fulfillment_label(
        $db,
        (int) $fulfillment['id']
    );
?>

<div class="admin-commerce-label-box">

<?php if ($shippingLabel): ?>

<div class="admin-commerce-label-result">
    <div>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>

        <div>
            <strong>Shipping label ready</strong>
            <span>
                <?= moderation_e(
                    (string) $shippingLabel['carrier']
                ) ?>
                <?= moderation_e(
                    (string) $shippingLabel['service']
                ) ?>
                | <?= moderation_e(
                    admin_shop_money(
                        (int) $shippingLabel['postage_cents'],
                        strtolower(
                            (string) $shippingLabel['currency']
                        )
                    )
                ) ?>
            </span>
        </div>
    </div>

    <div class="admin-commerce-label-actions">
        <a
            class="admin-button"
            href="<?= moderation_e(
                (string) $shippingLabel['label_url']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Open label
        </a>

        <?php if (!empty($shippingLabel['tracking_url'])): ?>
        <a
            class="admin-button"
            href="<?= moderation_e(
                (string) $shippingLabel['tracking_url']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Track package
        </a>
        <?php endif; ?>
    </div>
</div>

<?php elseif (!$shippingConfigured): ?>

<div>
    <i class="fa-solid fa-plug" aria-hidden="true"></i>

    <div>
        <strong>Connect EasyPost</strong>
        <span>
            Add the EasyPost API key and Llama Scout Fulfillment origin address to the private shipping configuration.
        </span>
    </div>
</div>

<button
    class="admin-button"
    type="button"
    disabled
>
    Shipping not configured
</button>

<?php else: ?>

<div class="admin-commerce-label-heading">
    <div>
        <i class="fa-solid fa-tag" aria-hidden="true"></i>

        <div>
            <strong>Create shipping label</strong>
            <span>
                Get live carrier rates, choose one, then purchase the label.
            </span>
        </div>
    </div>

    <form method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="order_id"
            value="<?= (int) $orderId ?>"
        >
        <input
            type="hidden"
            name="fulfillment_id"
            value="<?= (int) $fulfillment['id'] ?>"
        >
        <input
            type="hidden"
            name="shop_admin_action"
            value="quote-shipping-rates"
        >

        <button
            class="admin-button"
            type="submit"
        >
            <?= $shippingRates
                ? 'Refresh rates'
                : 'Get shipping rates' ?>
        </button>
    </form>
</div>

<?php if ($shippingRates): ?>
<div class="admin-commerce-rate-list">

<?php foreach ($shippingRates as $rate): ?>
<form
    class="admin-commerce-rate-row"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>
<input
    type="hidden"
    name="order_id"
    value="<?= (int) $orderId ?>"
>
<input
    type="hidden"
    name="fulfillment_id"
    value="<?= (int) $fulfillment['id'] ?>"
>
<input
    type="hidden"
    name="rate_id"
    value="<?= (int) $rate['id'] ?>"
>
<input
    type="hidden"
    name="shop_admin_action"
    value="buy-shipping-label"
>

<div>
    <strong>
        <?= moderation_e(
            (string) $rate['carrier']
        ) ?>
        <?= moderation_e(
            (string) $rate['service']
        ) ?>
    </strong>

    <span>
        <?php if (!empty($rate['delivery_days'])): ?>
            Estimated <?= (int) $rate['delivery_days'] ?>
            day<?= (int) $rate['delivery_days'] === 1 ? '' : 's' ?>
        <?php elseif (!empty($rate['delivery_date'])): ?>
            Estimated <?= moderation_e(
                (string) $rate['delivery_date']
            ) ?>
        <?php else: ?>
            Delivery estimate unavailable
        <?php endif; ?>
    </span>
</div>

<strong>
    <?= moderation_e(
        admin_shop_money(
            (int) $rate['rate_cents'],
            strtolower(
                (string) $rate['currency']
            )
        )
    ) ?>
</strong>

<button
    class="admin-button"
    type="submit"
>
    Buy label
</button>

</form>
<?php endforeach; ?>

</div>
<?php endif; ?>

<?php endif; ?>

</div>

</section>
<?php endif; ?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>History</p>
        <h2>Fulfillment Timeline</h2>
    </div>
</header>

<dl class="admin-commerce-fulfillment-timeline">

<div>
    <dt>Created</dt>
    <dd>
        <?= moderation_e(
            admin_fulfillment_format_timestamp(
                $fulfillment['created_at'] ?? ''
            )
        ) ?>
    </dd>
</div>

<div>
    <dt>Submitted / processing</dt>
    <dd>
        <?= moderation_e(
            admin_fulfillment_format_timestamp(
                $fulfillment['submitted_at'] ?? ''
            )
        ) ?>
    </dd>
</div>

<div>
    <dt>Shipped</dt>
    <dd>
        <?= moderation_e(
            admin_fulfillment_format_timestamp(
                $fulfillment['shipped_at'] ?? ''
            )
        ) ?>
    </dd>
</div>

<div>
    <dt>Delivered</dt>
    <dd>
        <?= moderation_e(
            admin_fulfillment_format_timestamp(
                $fulfillment['delivered_at'] ?? ''
            )
        ) ?>
    </dd>
</div>

</dl>

</section>

<?php endforeach; ?>
<?php endif; ?>


</div>


<aside class="admin-user-detail-side">

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Status</p>
        <h2>Order State</h2>
    </div>
</header>

<div class="admin-commerce-order-status-note">
    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
    <p>
        Normal order status follows fulfillment automatically.
        Use this control only for an exception such as cancellation,
        refund, or a problem that needs manual intervention.
    </p>
</div>

<form
    class="admin-user-action-box"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
<input type="hidden" name="shop_admin_action" value="order-status">

<label>
    <span>Order status</span>
    <select name="order_status">
        <?php foreach (
            [
                'pending',
                'paid',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'cancelled',
                'refunded',
                'problem',
            ] as $status
        ): ?>
            <option
                value="<?= moderation_e($status) ?>"
                <?= $order['order_status'] === $status ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($status)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<button class="admin-button" type="submit">
    Save order status
</button>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Payment</p>
        <h2>Totals</h2>
    </div>
</header>

<dl class="admin-user-definition-list">

<div>
    <dt>Subtotal</dt>
    <dd><?= moderation_e(admin_shop_money((int) $order['subtotal_cents'])) ?></dd>
</div>

<div>
    <dt>Discount</dt>
    <dd>-<?= moderation_e(admin_shop_money((int) $order['discount_cents'])) ?></dd>
</div>

<div>
    <dt>Shipping</dt>
    <dd><?= moderation_e(admin_shop_money((int) $order['shipping_cents'])) ?></dd>
</div>

<div>
    <dt>Tax</dt>
    <dd><?= moderation_e(admin_shop_money((int) $order['tax_cents'])) ?></dd>
</div>

<div>
    <dt>Total</dt>
    <dd><?= moderation_e(admin_shop_money((int) $order['total_cents'])) ?></dd>
</div>

<div>
    <dt>Payment status</dt>
    <dd><?= moderation_e((string) $order['payment_status']) ?></dd>
</div>

</dl>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Customer</p>
        <h2>Shipping Details</h2>
    </div>
</header>

<dl class="admin-user-definition-list">

<div>
    <dt>Name</dt>
    <dd><?= moderation_e((string) ($order['shipping_name'] ?: $order['display_name'] ?: 'Not supplied')) ?></dd>
</div>

<div>
    <dt>Email</dt>
    <dd><?= moderation_e((string) ($order['customer_email'] ?: 'Not supplied')) ?></dd>
</div>

<div>
    <dt>Phone</dt>
    <dd><?= moderation_e((string) ($order['shipping_phone'] ?: 'Not supplied')) ?></dd>
</div>

<div>
    <dt>Ship to</dt>
    <dd>
        <?php if ($shippingAddress): ?>
            <?php if (!empty($shippingAddress['line1'])): ?>
                <?= moderation_e((string) $shippingAddress['line1']) ?><br>
            <?php endif; ?>
            <?php if (!empty($shippingAddress['line2'])): ?>
                <?= moderation_e((string) $shippingAddress['line2']) ?><br>
            <?php endif; ?>
            <?php
            $locality = array_filter([
                trim((string) ($shippingAddress['city'] ?? '')),
                trim((string) ($shippingAddress['state'] ?? '')),
                trim((string) ($shippingAddress['postal_code'] ?? '')),
            ], static fn(string $part): bool => $part !== '');
            ?>
            <?php if ($locality): ?>
                <?= moderation_e(implode(' ', $locality)) ?><br>
            <?php endif; ?>
            <?php if (!empty($shippingAddress['country'])): ?>
                <?= moderation_e((string) $shippingAddress['country']) ?>
            <?php endif; ?>
        <?php else: ?>
            Not supplied
        <?php endif; ?>
    </dd>
</div>

</dl>

</section>


<?php if ((int) ($order['shipping_needs_review'] ?? 0) === 1): ?>
<section class="admin-panel admin-danger-panel">
<header class="admin-panel-header">
    <div>
        <p>Shipping Review</p>
        <h2>Needs Attention</h2>
    </div>
</header>

<div class="admin-user-action-box">
    <p>
        <?= moderation_e(
            (string) (
                $order['shipping_review_reason']
                ?: 'Shipping quote requires manual review.'
            )
        ) ?>
    </p>
</div>
</section>
<?php endif; ?>

</aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>

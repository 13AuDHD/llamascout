<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once dirname(__DIR__) . '/app/timezone.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';
$mountainTz = 'America/Denver';

function membership_admin_local_to_utc(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        throw new InvalidArgumentException('Start and end times are required.');
    }

    $local = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        new DateTimeZone('America/Denver')
    );

    if (!$local) {
        throw new InvalidArgumentException('A valid Mountain Time date and time is required.');
    }

    return $local
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

function membership_admin_utc_to_input(?string $value): string
{
    if (!$value) {
        return '';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('America/Denver'))
            ->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function membership_admin_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

function membership_admin_discount_text(array $row): string
{
    $type = (string) ($row['discount_type'] ?? '');
    $value = (int) ($row['discount_value'] ?? 0);

    if ($type === LLAMA_PROMOTION_DISCOUNT_PERCENT) {
        return $value . '% off';
    }

    return membership_admin_money($value) . ' off';
}

function membership_admin_require_schema(PDO $db): void
{
    $required = [
        'membership_plans',
        'membership_plan_prices',
        'membership_promotions',
        'membership_promotion_plans',
        'membership_checkout_settings',
    ];

    foreach ($required as $table) {
        $stmt = $db->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?
             LIMIT 1'
        );
        $stmt->execute([$table]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Membership promotion database migration has not been installed yet. Missing table: ' . $table
            );
        }
    }
}

try {
    membership_admin_require_schema($db);
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'admin.membership_schema'
    );

    $error = llama_error_message_with_reference(
        'Membership pricing cannot be managed until the database migration is installed.',
        $reference
    );
}

if (
    $error === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['membership_action'] ?? ''));

            if ($action === 'update-plan-price') {
                $planId = (int) ($_POST['plan_id'] ?? 0);
                $amountRaw = trim((string) ($_POST['amount'] ?? ''));
                $reason = trim((string) ($_POST['change_reason'] ?? ''));

                if ($planId < 1 || !is_numeric($amountRaw)) {
                    throw new InvalidArgumentException('Choose a valid plan and price.');
                }

                $amountCents = (int) round(((float) $amountRaw) * 100);

                if ($amountCents < 1) {
                    throw new InvalidArgumentException('Membership price must be greater than zero.');
                }

                $stmt = $db->prepare(
                    'SELECT
                        p.*,
                        cp.amount_cents AS current_amount_cents,
                        cp.currency AS current_currency,
                        cp.stripe_price_id AS current_stripe_price_id
                     FROM membership_plans p
                     LEFT JOIN membership_plan_prices cp
                       ON cp.plan_id = p.id
                      AND cp.is_current = 1
                     WHERE p.id = ?
                     LIMIT 1'
                );
                $stmt->execute([$planId]);
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$plan) {
                    throw new InvalidArgumentException('Membership plan not found.');
                }

                $productId = trim((string) ($plan['stripe_product_id'] ?? ''));

                if ($productId === '') {
                    throw new RuntimeException(
                        'This membership plan does not have a Stripe Product ID. Connect the Stripe product before changing its price.'
                    );
                }

                $intervalSlug = (string) $plan['interval_slug'];
                $stripeInterval = $intervalSlug === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL
                    ? 'year'
                    : 'month';

                $currency = strtolower(trim((string) ($plan['current_currency'] ?? $plan['currency'] ?? 'usd')))
                    ?: 'usd';

                $stripe = llama_stripe_client();

                $stripePrice = $stripe->prices->create([
                    'unit_amount' => $amountCents,
                    'currency' => $currency,
                    'recurring' => [
                        'interval' => $stripeInterval,
                    ],
                    'product' => $productId,
                    'metadata' => [
                        'llama_membership_plan_id' => (string) $planId,
                        'llama_membership_interval' => $intervalSlug,
                    ],
                ]);

                $stripePriceId = trim((string) ($stripePrice->id ?? ''));

                if ($stripePriceId === '') {
                    throw new RuntimeException('Stripe did not return a new Price ID.');
                }

                llama_insert_membership_price_version(
                    $db,
                    $planId,
                    $amountCents,
                    $currency,
                    $stripePriceId,
                    $actorUserId,
                    $reason !== '' ? $reason : 'Admin price change'
                );

                $notice = ucfirst($intervalSlug)
                    . ' membership is now '
                    . membership_admin_money($amountCents)
                    . ' for new signups.';
            }

            if ($action === 'create-promotion') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $publicLabel = trim((string) ($_POST['public_label'] ?? ''));
                $publicDescription = trim((string) ($_POST['public_description'] ?? ''));
                $startsAt = membership_admin_local_to_utc((string) ($_POST['starts_at'] ?? ''));
                $endsAt = membership_admin_local_to_utc((string) ($_POST['ends_at'] ?? ''));
                $discountType = trim((string) ($_POST['discount_type'] ?? 'percent'));

                if ($name === '') {
                    throw new InvalidArgumentException('Promotion name is required.');
                }

                if (strtotime($endsAt) <= strtotime($startsAt)) {
                    throw new InvalidArgumentException('Promotion end must be after its start.');
                }

                if (!in_array(
                    $discountType,
                    [LLAMA_PROMOTION_DISCOUNT_PERCENT, LLAMA_PROMOTION_DISCOUNT_AMOUNT],
                    true
                )) {
                    throw new InvalidArgumentException('Choose a valid discount type.');
                }

                $selectedPlanIds = array_values(array_unique(array_map(
                    'intval',
                    (array) ($_POST['plan_ids'] ?? [])
                )));

                $selectedPlanIds = array_values(array_filter(
                    $selectedPlanIds,
                    static fn(int $id): bool => $id > 0
                ));

                if (!$selectedPlanIds) {
                    throw new InvalidArgumentException('Choose at least one membership plan.');
                }

                $discountRaw = trim((string) ($_POST['discount_value'] ?? ''));

                if (!is_numeric($discountRaw)) {
                    throw new InvalidArgumentException('Enter a valid discount.');
                }

                $discountValue = $discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT
                    ? (int) round((float) $discountRaw)
                    : (int) round(((float) $discountRaw) * 100);

                if ($discountValue < 1) {
                    throw new InvalidArgumentException('Discount must be greater than zero.');
                }

                if (
                    $discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT
                    && $discountValue > 100
                ) {
                    throw new InvalidArgumentException('Percentage discount cannot exceed 100%.');
                }

                foreach ($selectedPlanIds as $planId) {
                    $conflicts = llama_membership_promotion_conflicts(
                        $db,
                        $planId,
                        $startsAt,
                        $endsAt
                    );

                    if ($conflicts) {
                        throw new InvalidArgumentException(
                            'This promotion overlaps another enabled promotion for one of the selected plans.'
                        );
                    }
                }

                $db->beginTransaction();

                $insertPromotion = $db->prepare(
                    'INSERT INTO membership_promotions
                     (
                        name,
                        public_label,
                        public_description,
                        starts_at,
                        ends_at,
                        is_enabled,
                        created_by
                     )
                     VALUES (?, ?, ?, ?, ?, 1, ?)'
                );

                $insertPromotion->execute([
                    $name,
                    $publicLabel !== '' ? $publicLabel : null,
                    $publicDescription !== '' ? $publicDescription : null,
                    $startsAt,
                    $endsAt,
                    $actorUserId > 0 ? $actorUserId : null,
                ]);

                $promotionId = (int) $db->lastInsertId();

                $planStmt = $db->prepare(
                    'SELECT
                        p.id,
                        p.interval_slug,
                        p.currency,
                        p.stripe_product_id,
                        cp.id AS current_price_id,
                        cp.amount_cents,
                        cp.currency AS current_currency
                     FROM membership_plans p
                     INNER JOIN membership_plan_prices cp
                        ON cp.plan_id = p.id
                       AND cp.is_current = 1
                     WHERE p.id = ?
                       AND p.is_active = 1
                     LIMIT 1'
                );

                $insertRule = $db->prepare(
                    'INSERT INTO membership_promotion_plans
                     (
                        promotion_id,
                        plan_id,
                        plan_price_id,
                        discount_type,
                        discount_value,
                        stripe_coupon_id,
                        discount_duration,
                        duration_count,
                        allow_manual_promotion_codes
                     )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)'
                );

                $stripe = llama_stripe_client();

                foreach ($selectedPlanIds as $planId) {
                    $planStmt->execute([$planId]);
                    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$plan) {
                        throw new RuntimeException('One selected membership plan is unavailable.');
                    }

                    $couponData = [
                        'name' => $name . ' - ' . ucfirst((string) $plan['interval_slug']),
                        'metadata' => [
                            'llama_membership_promotion_id' => (string) $promotionId,
                            'llama_membership_plan_id' => (string) $planId,
                            'llama_membership_interval' => (string) $plan['interval_slug'],
                            'llama_discount_policy' => 'first_year_only',
                        ],
                    ];

                    if ($discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT) {
                        $couponData['percent_off'] = $discountValue;
                    } else {
                        $couponData['amount_off'] = $discountValue;
                        $couponData['currency'] = strtolower(
                            trim((string) ($plan['current_currency'] ?? $plan['currency'] ?? 'usd'))
                        ) ?: 'usd';
                    }

                    $productId = trim((string) ($plan['stripe_product_id'] ?? ''));

                    if ($productId !== '') {
                        $couponData['applies_to'] = [
                            'products' => [$productId],
                        ];
                    }

                    /*
                     * Llama Scout automatic signup promotions are introductory.
                     * Monthly subscriptions receive the discounted invoices for
                     * 12 months. Annual subscriptions receive the discount on the
                     * first annual invoice only. After that Stripe automatically
                     * returns the subscription to the regular recurring price.
                     */
                    if ((string) $plan['interval_slug'] === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY) {
                        $couponData['duration'] = 'repeating';
                        $couponData['duration_in_months'] = 12;
                        $duration = LLAMA_PROMOTION_DURATION_REPEATING;
                        $durationCount = 12;
                    } else {
                        $couponData['duration'] = 'once';
                        $duration = LLAMA_PROMOTION_DURATION_ONCE;
                        $durationCount = 1;
                    }

                    $coupon = $stripe->coupons->create($couponData);
                    $couponId = trim((string) ($coupon->id ?? ''));

                    if ($couponId === '') {
                        throw new RuntimeException('Stripe did not return a Coupon ID.');
                    }

                    $insertRule->execute([
                        $promotionId,
                        $planId,
                        (int) $plan['current_price_id'],
                        $discountType,
                        $discountValue,
                        $couponId,
                        $duration,
                        $durationCount,
                    ]);
                }

                $db->commit();

                $notice = 'Promotion created. Times are stored in UTC and shown here in Mountain Time.';
            }

            if ($action === 'toggle-promotion') {
                $promotionId = (int) ($_POST['promotion_id'] ?? 0);
                $enabled = (int) ($_POST['enabled'] ?? 0) === 1 ? 1 : 0;

                if ($promotionId < 1) {
                    throw new InvalidArgumentException('Promotion not found.');
                }

                $stmt = $db->prepare(
                    'UPDATE membership_promotions
                     SET is_enabled = ?
                     WHERE id = ?'
                );
                $stmt->execute([$enabled, $promotionId]);

                $notice = $enabled
                    ? 'Promotion enabled.'
                    : 'Promotion disabled.';
            }

            if ($action === 'save-code-setting') {
                $enabled = isset($_POST['manual_codes_enabled']) ? 1 : 0;

                $stmt = $db->prepare(
                    'INSERT INTO membership_checkout_settings
                     (id, manual_promotion_codes_enabled, updated_by)
                     VALUES (1, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        manual_promotion_codes_enabled = VALUES(manual_promotion_codes_enabled),
                        updated_by = VALUES(updated_by),
                        updated_at = CURRENT_TIMESTAMP'
                );
                $stmt->execute([
                    $enabled,
                    $actorUserId > 0 ? $actorUserId : null,
                ]);

                $notice = $enabled
                    ? 'Manual Stripe promotion codes are enabled when no automatic sale is active.'
                    : 'Manual Stripe promotion codes are disabled.';
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $reference = llama_log_caught_exception(
                $exception,
                'admin.membership_pricing',
                [],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'Membership pricing could not be updated.',
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

$adminPageTitle = 'Membership Pricing';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'memberships';

$plans = [];
$promotions = [];
$manualCodesEnabled = false;

if ($error === '') {
    $plans = llama_membership_plans($db, false);

    $promotions = $db->query(
        'SELECT
            mp.*,
            GROUP_CONCAT(
                CONCAT(
                    p.name,
                    ":",
                    mpp.discount_type,
                    ":",
                    mpp.discount_value,
                    ":",
                    mpp.discount_duration,
                    ":",
                    COALESCE(mpp.duration_count, 0)
                )
                ORDER BY p.sort_order, p.id
                SEPARATOR "|"
            ) AS plan_rules
         FROM membership_promotions mp
         LEFT JOIN membership_promotion_plans mpp
           ON mpp.promotion_id = mp.id
         LEFT JOIN membership_plans p
           ON p.id = mpp.plan_id
         GROUP BY mp.id
         ORDER BY mp.starts_at DESC, mp.id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $setting = $db->query(
        'SELECT manual_promotion_codes_enabled
         FROM membership_checkout_settings
         WHERE id = 1
         LIMIT 1'
    )->fetchColumn();

    $manualCodesEnabled = (bool) $setting;
}

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin-memberships.css') ?>"
>

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

<?php if ($error === ''): ?>

<section class="admin-membership-grid">

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Regular pricing</p>
                <h2>Membership Plans</h2>
            </div>
            <span>New signups only</span>
        </header>

        <div class="admin-membership-plan-list">
            <?php foreach ($plans as $plan): ?>
                <form class="admin-membership-plan-card" method="post">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="membership_action"
                        value="update-plan-price"
                    >
                    <input
                        type="hidden"
                        name="plan_id"
                        value="<?= (int) $plan['id'] ?>"
                    >

                    <div class="admin-membership-plan-heading">
                        <div>
                            <span><?= moderation_e(ucfirst((string) $plan['interval_slug'])) ?></span>
                            <strong>
                                <?= moderation_e(
                                    membership_admin_money((int) $plan['base_price_cents'])
                                ) ?>
                            </strong>
                        </div>
                        <small>
                            / <?= $plan['interval_slug'] === 'annual' ? 'year' : 'month' ?>
                        </small>
                    </div>

                    <label>
                        New regular price
                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            value="<?= moderation_e(
                                number_format((int) $plan['base_price_cents'] / 100, 2, '.', '')
                            ) ?>"
                            required
                        >
                    </label>

                    <label>
                        Change reason
                        <input
                            type="text"
                            name="change_reason"
                            maxlength="255"
                            placeholder="Example: 2027 pricing adjustment"
                        >
                    </label>

                    <p>
                        A new immutable Stripe price is created for future signups.
                        Existing subscriptions keep their current recurring price.
                    </p>

                    <button class="admin-button" type="submit">
                        Save regular price
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>


    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Holiday and signup sales</p>
                <h2>Create Automatic Promotion</h2>
            </div>
            <span>Mountain Time</span>
        </header>

        <form class="admin-membership-promotion-form" method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >
            <input
                type="hidden"
                name="membership_action"
                value="create-promotion"
            >

            <div class="admin-membership-form-grid">
                <label>
                    Internal name
                    <input
                        type="text"
                        name="name"
                        maxlength="150"
                        placeholder="Black Friday 2026"
                        required
                    >
                </label>

                <label>
                    Customer label
                    <input
                        type="text"
                        name="public_label"
                        maxlength="150"
                        placeholder="Black Friday"
                    >
                </label>

                <label>
                    Starts, Mountain Time
                    <input
                        type="datetime-local"
                        name="starts_at"
                        required
                    >
                </label>

                <label>
                    Ends, Mountain Time
                    <input
                        type="datetime-local"
                        name="ends_at"
                        required
                    >
                </label>

                <label>
                    Discount type
                    <select name="discount_type">
                        <option value="percent">Percent off</option>
                        <option value="amount">Dollar amount off</option>
                    </select>
                </label>

                <label>
                    Discount value
                    <input
                        type="number"
                        name="discount_value"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="25"
                        required
                    >
                </label>
            </div>

            <fieldset class="admin-membership-plan-choice">
                <legend>Applies to</legend>

                <?php foreach ($plans as $plan): ?>
                    <?php if (empty($plan['is_active'])) continue; ?>
                    <label>
                        <input
                            type="checkbox"
                            name="plan_ids[]"
                            value="<?= (int) $plan['id'] ?>"
                        >
                        <?= moderation_e(ucfirst((string) $plan['interval_slug'])) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label>
                Customer description
                <textarea
                    name="public_description"
                    rows="3"
                    placeholder="Introductory membership pricing for your first year."
                ></textarea>
            </label>

            <div class="admin-membership-policy-note">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                <div>
                    <strong>First year only</strong>
                    <span>
                        Monthly signups receive 12 discounted monthly invoices.
                        Annual signups receive the discount on the first annual invoice.
                        After that, Stripe returns them to the regular recurring price.
                    </span>
                </div>
            </div>

            <button class="admin-button" type="submit">
                Create promotion
            </button>
        </form>
    </section>

</section>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Separate from automatic sales</p>
            <h2>Manual Promotion Codes</h2>
        </div>
        <span><?= $manualCodesEnabled ? 'Enabled' : 'Disabled' ?></span>
    </header>

    <form class="admin-membership-code-setting" method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="membership_action"
            value="save-code-setting"
        >

        <label>
            <input
                type="checkbox"
                name="manual_codes_enabled"
                value="1"
                <?= $manualCodesEnabled ? 'checked' : '' ?>
            >
            Allow Stripe promotion codes at membership checkout when no automatic promotional price is active.
        </label>

        <p>
            This is intentionally separate from Black Friday-style automatic pricing.
            Use Stripe Promotion Codes for one-off or special customer codes.
        </p>

        <button class="admin-button" type="submit">
            Save code setting
        </button>
    </form>
</section>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Scheduled sales</p>
            <h2>Promotions</h2>
        </div>
        <span><?= number_format(count($promotions)) ?> total</span>
    </header>

    <?php if (!$promotions): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-tags" aria-hidden="true"></i>
            <h3>No promotions yet.</h3>
            <p>Create the first automatic signup promotion above.</p>
        </div>
    <?php else: ?>
        <div class="admin-membership-promotion-list">
            <?php foreach ($promotions as $promotion): ?>
                <?php
                $status = llama_membership_promotion_status($promotion);
                $rules = array_filter(explode('|', (string) ($promotion['plan_rules'] ?? '')));
                ?>
                <article class="admin-membership-promotion-card">
                    <div class="admin-membership-promotion-top">
                        <div>
                            <span class="admin-status-pill is-<?= moderation_e($status) ?>">
                                <?= moderation_e(ucfirst($status)) ?>
                            </span>
                            <h3><?= moderation_e((string) $promotion['name']) ?></h3>
                            <?php if (!empty($promotion['public_label'])): ?>
                                <p><?= moderation_e((string) $promotion['public_label']) ?></p>
                            <?php endif; ?>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= moderation_e(moderation_csrf_token()) ?>"
                            >
                            <input
                                type="hidden"
                                name="membership_action"
                                value="toggle-promotion"
                            >
                            <input
                                type="hidden"
                                name="promotion_id"
                                value="<?= (int) $promotion['id'] ?>"
                            >
                            <input
                                type="hidden"
                                name="enabled"
                                value="<?= !empty($promotion['is_enabled']) ? '0' : '1' ?>"
                            >

                            <button class="admin-button" type="submit">
                                <?= !empty($promotion['is_enabled']) ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </div>

                    <dl class="admin-membership-promotion-meta">
                        <div>
                            <dt>Starts</dt>
                            <dd>
                                <?= moderation_e(
                                    llama_format_datetime(
                                        (string) $promotion['starts_at'],
                                        $mountainTz,
                                        'M j, Y g:i A T'
                                    )
                                ) ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Ends</dt>
                            <dd>
                                <?= moderation_e(
                                    llama_format_datetime(
                                        (string) $promotion['ends_at'],
                                        $mountainTz,
                                        'M j, Y g:i A T'
                                    )
                                ) ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="admin-membership-rule-list">
                        <?php foreach ($rules as $rule): ?>
                            <?php
                            [$planName, $discountType, $discountValue, $duration, $durationCount] =
                                array_pad(explode(':', $rule), 5, '');
                            $displayValue = $discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT
                                ? ((int) $discountValue) . '% off'
                                : membership_admin_money((int) $discountValue) . ' off';
                            ?>
                            <span>
                                <strong><?= moderation_e($planName) ?></strong>
                                <?= moderation_e($displayValue) ?>
                                Â· first year
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($promotion['public_description'])): ?>
                        <p class="admin-membership-promotion-description">
                            <?= moderation_e((string) $promotion['public_description']) ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

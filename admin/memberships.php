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

function membership_admin_local_to_utc(string $value, bool $allowBlank = false): ?string
{
    $value = trim($value);

    if ($value === '') {
        if ($allowBlank) {
            return null;
        }

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

function membership_admin_require_schema(PDO $db): void
{
    $required = [
        'membership_plans',
        'membership_plan_prices',
        'membership_promotions',
        'membership_promotion_plans',
        'membership_checkout_settings',
        'membership_promotion_deliveries',
        'membership_promotion_events',
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
                'Membership campaign database upgrade is incomplete. Missing table: ' . $table
            );
        }
    }

    $requiredColumns = [
        'campaign_type',
        'auto_apply',
        'show_site_banner',
        'show_countdown',
        'banner_text',
        'landing_url',
        'email_enabled',
        'email_send_at',
        'email_subject',
        'email_body_text',
        'reminder_enabled',
        'reminder_send_at',
        'reminder_subject',
        'reminder_body_text',
    ];

    foreach ($requiredColumns as $column) {
        $stmt = $db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = "membership_promotions"
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([$column]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Membership campaign database upgrade is incomplete. Missing column: ' . $column
            );
        }
    }
}

function membership_admin_selected_plan_ids(array $source): array
{
    $ids = array_values(array_unique(array_map('intval', $source)));

    return array_values(array_filter(
        $ids,
        static fn(int $id): bool => $id > 0
    ));
}

function membership_admin_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function membership_admin_status_label(array $promotion): string
{
    if (empty($promotion['is_enabled'])) {
        return 'disabled';
    }

    $now = time();
    $start = strtotime((string) ($promotion['starts_at'] ?? '')) ?: 0;
    $end = strtotime((string) ($promotion['ends_at'] ?? '')) ?: 0;

    if ($now < $start) {
        return 'scheduled';
    }

    if ($now > $end) {
        return 'ended';
    }

    return 'active';
}

function membership_admin_campaign_month(array $promotion): string
{
    try {
        return (new DateTimeImmutable(
            (string) $promotion['starts_at'],
            new DateTimeZone('UTC')
        ))
            ->setTimezone(new DateTimeZone('America/Denver'))
            ->format('F Y');
    } catch (Throwable) {
        return 'Other';
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
        'Membership pricing and promotions cannot be managed until the database upgrade is installed.',
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
                $bannerText = trim((string) ($_POST['banner_text'] ?? ''));
                $landingUrl = trim((string) ($_POST['landing_url'] ?? ''));
                $startsAt = membership_admin_local_to_utc((string) ($_POST['starts_at'] ?? ''));
                $endsAt = membership_admin_local_to_utc((string) ($_POST['ends_at'] ?? ''));
                $discountType = trim((string) ($_POST['discount_type'] ?? 'percent'));

                if ($name === '') {
                    throw new InvalidArgumentException('Promotion name is required.');
                }

                if (!$startsAt || !$endsAt || strtotime($endsAt) <= strtotime($startsAt)) {
                    throw new InvalidArgumentException('Promotion end must be after its start.');
                }

                if (!in_array(
                    $discountType,
                    [LLAMA_PROMOTION_DISCOUNT_PERCENT, LLAMA_PROMOTION_DISCOUNT_AMOUNT],
                    true
                )) {
                    throw new InvalidArgumentException('Choose a valid discount type.');
                }

                $selectedPlanIds = membership_admin_selected_plan_ids(
                    (array) ($_POST['plan_ids'] ?? [])
                );

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
                    if (llama_membership_promotion_conflicts(
                        $db,
                        $planId,
                        $startsAt,
                        $endsAt
                    )) {
                        throw new InvalidArgumentException(
                            'This promotion overlaps another enabled automatic promotion for one of the selected plans.'
                        );
                    }
                }

                $showBanner = membership_admin_checkbox('show_site_banner');
                $showCountdown = membership_admin_checkbox('show_countdown');
                $emailEnabled = membership_admin_checkbox('email_enabled');
                $reminderEnabled = membership_admin_checkbox('reminder_enabled');

                $emailSendAt = membership_admin_local_to_utc(
                    (string) ($_POST['email_send_at'] ?? ''),
                    true
                );
                $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
                $emailPreheader = trim((string) ($_POST['email_preheader'] ?? ''));
                $emailBody = trim((string) ($_POST['email_body_text'] ?? ''));

                $reminderSendAt = membership_admin_local_to_utc(
                    (string) ($_POST['reminder_send_at'] ?? ''),
                    true
                );
                $reminderSubject = trim((string) ($_POST['reminder_subject'] ?? ''));
                $reminderBody = trim((string) ($_POST['reminder_body_text'] ?? ''));

                if ($emailEnabled && (!$emailSendAt || $emailSubject === '' || $emailBody === '')) {
                    throw new InvalidArgumentException(
                        'Campaign email requires a send time, subject, and message.'
                    );
                }

                if ($reminderEnabled && (!$reminderSendAt || $reminderSubject === '' || $reminderBody === '')) {
                    throw new InvalidArgumentException(
                        'Final reminder requires a send time, subject, and message.'
                    );
                }

                $db->beginTransaction();

                $insertPromotion = $db->prepare(
                    'INSERT INTO membership_promotions
                     (
                        name,
                        public_label,
                        public_description,
                        campaign_type,
                        auto_apply,
                        show_site_banner,
                        show_countdown,
                        banner_text,
                        landing_url,
                        starts_at,
                        ends_at,
                        is_enabled,
                        email_enabled,
                        email_audience,
                        email_send_at,
                        email_subject,
                        email_preheader,
                        email_body_text,
                        reminder_enabled,
                        reminder_send_at,
                        reminder_subject,
                        reminder_body_text,
                        created_by
                     )
                     VALUES
                     (?, ?, ?, "automatic", 1, ?, ?, ?, ?, ?, ?, 1, ?, "free_members",
                      ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $insertPromotion->execute([
                    $name,
                    $publicLabel !== '' ? $publicLabel : null,
                    $publicDescription !== '' ? $publicDescription : null,
                    $showBanner,
                    $showCountdown,
                    $bannerText !== '' ? $bannerText : null,
                    $landingUrl !== '' ? $landingUrl : null,
                    $startsAt,
                    $endsAt,
                    $emailEnabled,
                    $emailSendAt,
                    $emailSubject !== '' ? $emailSubject : null,
                    $emailPreheader !== '' ? $emailPreheader : null,
                    $emailBody !== '' ? $emailBody : null,
                    $reminderEnabled,
                    $reminderSendAt,
                    $reminderSubject !== '' ? $reminderSubject : null,
                    $reminderBody !== '' ? $reminderBody : null,
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

                $notice = 'Promotion created and connected to Stripe. Campaign times are shown in Mountain Time.';
            }

            if ($action === 'update-campaign') {
                $promotionId = (int) ($_POST['promotion_id'] ?? 0);

                if ($promotionId < 1) {
                    throw new InvalidArgumentException('Promotion not found.');
                }

                $existingStmt = $db->prepare(
                    'SELECT *
                     FROM membership_promotions
                     WHERE id = ?
                     LIMIT 1'
                );
                $existingStmt->execute([$promotionId]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new InvalidArgumentException('Promotion not found.');
                }

                $name = trim((string) ($_POST['name'] ?? ''));
                $publicLabel = trim((string) ($_POST['public_label'] ?? ''));
                $publicDescription = trim((string) ($_POST['public_description'] ?? ''));
                $bannerText = trim((string) ($_POST['banner_text'] ?? ''));
                $landingUrl = trim((string) ($_POST['landing_url'] ?? ''));
                $startsAt = membership_admin_local_to_utc((string) ($_POST['starts_at'] ?? ''));
                $endsAt = membership_admin_local_to_utc((string) ($_POST['ends_at'] ?? ''));

                if ($name === '') {
                    throw new InvalidArgumentException('Promotion name is required.');
                }

                if (!$startsAt || !$endsAt || strtotime($endsAt) <= strtotime($startsAt)) {
                    throw new InvalidArgumentException('Promotion end must be after its start.');
                }

                $showBanner = membership_admin_checkbox('show_site_banner');
                $showCountdown = membership_admin_checkbox('show_countdown');
                $emailEnabled = membership_admin_checkbox('email_enabled');
                $reminderEnabled = membership_admin_checkbox('reminder_enabled');

                $emailSendAt = membership_admin_local_to_utc(
                    (string) ($_POST['email_send_at'] ?? ''),
                    true
                );
                $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
                $emailPreheader = trim((string) ($_POST['email_preheader'] ?? ''));
                $emailBody = trim((string) ($_POST['email_body_text'] ?? ''));

                $reminderSendAt = membership_admin_local_to_utc(
                    (string) ($_POST['reminder_send_at'] ?? ''),
                    true
                );
                $reminderSubject = trim((string) ($_POST['reminder_subject'] ?? ''));
                $reminderBody = trim((string) ($_POST['reminder_body_text'] ?? ''));

                if ($emailEnabled && (!$emailSendAt || $emailSubject === '' || $emailBody === '')) {
                    throw new InvalidArgumentException(
                        'Campaign email requires a send time, subject, and message.'
                    );
                }

                if ($reminderEnabled && (!$reminderSendAt || $reminderSubject === '' || $reminderBody === '')) {
                    throw new InvalidArgumentException(
                        'Final reminder requires a send time, subject, and message.'
                    );
                }

                $stmt = $db->prepare(
                    'UPDATE membership_promotions
                     SET
                        name = ?,
                        public_label = ?,
                        public_description = ?,
                        show_site_banner = ?,
                        show_countdown = ?,
                        banner_text = ?,
                        landing_url = ?,
                        starts_at = ?,
                        ends_at = ?,
                        email_enabled = ?,
                        email_send_at = ?,
                        email_subject = ?,
                        email_preheader = ?,
                        email_body_text = ?,
                        reminder_enabled = ?,
                        reminder_send_at = ?,
                        reminder_subject = ?,
                        reminder_body_text = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $name,
                    $publicLabel !== '' ? $publicLabel : null,
                    $publicDescription !== '' ? $publicDescription : null,
                    $showBanner,
                    $showCountdown,
                    $bannerText !== '' ? $bannerText : null,
                    $landingUrl !== '' ? $landingUrl : null,
                    $startsAt,
                    $endsAt,
                    $emailEnabled,
                    $emailSendAt,
                    $emailSubject !== '' ? $emailSubject : null,
                    $emailPreheader !== '' ? $emailPreheader : null,
                    $emailBody !== '' ? $emailBody : null,
                    $reminderEnabled,
                    $reminderSendAt,
                    $reminderSubject !== '' ? $reminderSubject : null,
                    $reminderBody !== '' ? $reminderBody : null,
                    $promotionId,
                ]);

                $notice = 'Campaign details updated. Stripe discount rules were not changed.';
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
                    'Membership pricing or promotion could not be updated.',
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

$adminPageTitle = 'Pricing & Promotions';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'memberships';

$plans = [];
$promotions = [];
$manualCodesEnabled = false;
$editingPromotion = null;
$promotionStats = [];

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
         ORDER BY mp.starts_at ASC, mp.id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $setting = $db->query(
        'SELECT manual_promotion_codes_enabled
         FROM membership_checkout_settings
         WHERE id = 1
         LIMIT 1'
    )->fetchColumn();

    $manualCodesEnabled = (bool) $setting;

    $statsStmt = $db->query(
        'SELECT
            promotion_id,
            SUM(status = "sent") AS sent_count,
            SUM(status = "failed") AS failed_count
         FROM membership_promotion_deliveries
         GROUP BY promotion_id'
    );

    if ($statsStmt) {
        foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $promotionStats[(int) $row['promotion_id']] = $row;
        }
    }

    $editId = (int) ($_GET['edit'] ?? 0);

    if ($editId > 0) {
        foreach ($promotions as $candidate) {
            if ((int) $candidate['id'] === $editId) {
                $editingPromotion = $candidate;
                break;
            }
        }
    }
}

$calendarGroups = [];

foreach ($promotions as $promotion) {
    $calendarGroups[membership_admin_campaign_month($promotion)][] = $promotion;
}

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin-memberships.css') ?>"
>

<style>
.admin-campaign-summary-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:22px;
}
.admin-campaign-summary-card {
    padding:18px;
    border:1px solid var(--admin-border, rgba(127,127,127,.25));
    border-radius:14px;
    background:var(--admin-panel, rgba(127,127,127,.06));
}
.admin-campaign-summary-card span {
    display:block;
    font-size:.78rem;
    opacity:.72;
    text-transform:uppercase;
    letter-spacing:.06em;
}
.admin-campaign-summary-card strong {
    display:block;
    margin-top:7px;
    font-size:1.45rem;
}
.admin-campaign-section {
    margin-top:22px;
}
.admin-campaign-toggle-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.admin-campaign-toggle {
    display:flex !important;
    gap:10px;
    align-items:flex-start;
    padding:12px;
    border:1px solid var(--admin-border, rgba(127,127,127,.25));
    border-radius:12px;
}
.admin-campaign-toggle input {
    width:auto !important;
    margin-top:3px;
}
.admin-campaign-toggle strong,
.admin-campaign-toggle span {
    display:block;
}
.admin-campaign-toggle span {
    margin-top:3px;
    font-size:.85rem;
    opacity:.72;
}
.admin-campaign-calendar-month {
    margin-top:20px;
}
.admin-campaign-calendar-month > h3 {
    margin:0 0 10px;
}
.admin-campaign-card-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.admin-campaign-card-actions a {
    text-decoration:none;
}
.admin-campaign-badges {
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-top:10px;
}
.admin-campaign-badge {
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:5px 8px;
    border-radius:999px;
    background:rgba(127,127,127,.12);
    font-size:.78rem;
}
.admin-campaign-edit-banner {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:14px;
    padding:12px 14px;
    border-radius:12px;
    background:rgba(127,127,127,.10);
}
.admin-campaign-edit-banner a {
    white-space:nowrap;
}
.admin-campaign-form-section {
    margin-top:18px;
    padding-top:18px;
    border-top:1px solid var(--admin-border, rgba(127,127,127,.25));
}
.admin-campaign-form-section h3 {
    margin:0 0 5px;
}
.admin-campaign-form-section > p {
    margin:0 0 14px;
    opacity:.75;
}
.admin-campaign-code-placeholder {
    display:flex;
    gap:12px;
    align-items:flex-start;
}
@media (max-width:900px) {
    .admin-campaign-summary-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media (max-width:640px) {
    .admin-campaign-summary-grid,
    .admin-campaign-toggle-grid {
        grid-template-columns:1fr;
    }
}
</style>

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

<?php
$activeCount = 0;
$scheduledCount = 0;
$enabledEmailCount = 0;

foreach ($promotions as $promotion) {
    $status = membership_admin_status_label($promotion);
    if ($status === 'active') $activeCount++;
    if ($status === 'scheduled') $scheduledCount++;
    if (!empty($promotion['email_enabled'])) $enabledEmailCount++;
}
?>

<div class="admin-campaign-summary-grid">
    <div class="admin-campaign-summary-card">
        <span>Active sale</span>
        <strong><?= number_format($activeCount) ?></strong>
    </div>
    <div class="admin-campaign-summary-card">
        <span>Scheduled</span>
        <strong><?= number_format($scheduledCount) ?></strong>
    </div>
    <div class="admin-campaign-summary-card">
        <span>Campaigns</span>
        <strong><?= number_format(count($promotions)) ?></strong>
    </div>
    <div class="admin-campaign-summary-card">
        <span>Email campaigns</span>
        <strong><?= number_format($enabledEmailCount) ?></strong>
    </div>
</div>

<section class="admin-membership-grid">

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Stripe-connected catalog</p>
                <h2>Regular Pricing</h2>
            </div>
            <span>New signups only</span>
        </header>

        <div class="admin-membership-plan-list">
            <?php foreach ($plans as $plan): ?>
                <form class="admin-membership-plan-card" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="membership_action" value="update-plan-price">
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">

                    <div class="admin-membership-plan-heading">
                        <div>
                            <span><?= moderation_e(ucfirst((string) $plan['interval_slug'])) ?></span>
                            <strong><?= moderation_e(
                                membership_admin_money((int) $plan['base_price_cents'])
                            ) ?></strong>
                        </div>
                        <small>/ <?= $plan['interval_slug'] === 'annual' ? 'year' : 'month' ?></small>
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

                    <div class="admin-campaign-badges">
                        <span class="admin-campaign-badge">
                            <i class="fa-brands fa-stripe" aria-hidden="true"></i>
                            <?= !empty($plan['stripe_product_id']) ? 'Product connected' : 'Product missing' ?>
                        </span>
                        <span class="admin-campaign-badge">
                            <i class="fa-solid fa-tag" aria-hidden="true"></i>
                            <?= !empty($plan['stripe_price_id']) ? 'Price connected' : 'Price missing' ?>
                        </span>
                    </div>

                    <p>
                        Saving creates a new immutable Stripe Price for future signups.
                        Existing subscriptions keep the recurring price they already have.
                    </p>

                    <button class="admin-button" type="submit">
                        Create new regular price
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p><?= $editingPromotion ? 'Campaign editor' : 'Calendar event' ?></p>
                <h2><?= $editingPromotion ? 'Edit Promotion' : 'Schedule Promotion' ?></h2>
            </div>
            <span>Mountain Time</span>
        </header>

        <?php if ($editingPromotion): ?>
            <div class="admin-campaign-edit-banner">
                <span>
                    Editing <strong><?= moderation_e((string) $editingPromotion['name']) ?></strong>.
                    Discount amount and Stripe coupon rules stay locked.
                </span>
                <a class="admin-button" href="/memberships.php">New promotion</a>
            </div>
        <?php endif; ?>

        <form class="admin-membership-promotion-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input
                type="hidden"
                name="membership_action"
                value="<?= $editingPromotion ? 'update-campaign' : 'create-promotion' ?>"
            >
            <?php if ($editingPromotion): ?>
                <input type="hidden" name="promotion_id" value="<?= (int) $editingPromotion['id'] ?>">
            <?php endif; ?>

            <div class="admin-membership-form-grid">
                <label>
                    Internal name
                    <input
                        type="text"
                        name="name"
                        maxlength="150"
                        placeholder="Black Friday 2026"
                        value="<?= moderation_e((string) ($editingPromotion['name'] ?? '')) ?>"
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
                        value="<?= moderation_e((string) ($editingPromotion['public_label'] ?? '')) ?>"
                    >
                </label>

                <label>
                    Starts, Mountain Time
                    <input
                        type="datetime-local"
                        name="starts_at"
                        value="<?= moderation_e(
                            membership_admin_utc_to_input($editingPromotion['starts_at'] ?? null)
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    Ends, Mountain Time
                    <input
                        type="datetime-local"
                        name="ends_at"
                        value="<?= moderation_e(
                            membership_admin_utc_to_input($editingPromotion['ends_at'] ?? null)
                        ) ?>"
                        required
                    >
                </label>

                <?php if (!$editingPromotion): ?>
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
                <?php endif; ?>
            </div>

            <?php if (!$editingPromotion): ?>
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
            <?php endif; ?>

            <label>
                Customer description
                <textarea
                    name="public_description"
                    rows="3"
                    placeholder="Save 25% when you join during our Black Friday promotion."
                ><?= moderation_e((string) ($editingPromotion['public_description'] ?? '')) ?></textarea>
            </label>

            <div class="admin-campaign-form-section">
                <h3>Website promotion</h3>
                <p>Choose what Llama Scout should show while this promotion is active.</p>

                <div class="admin-campaign-toggle-grid">
                    <label class="admin-campaign-toggle">
                        <input
                            type="checkbox"
                            name="show_site_banner"
                            value="1"
                            <?= !isset($editingPromotion['show_site_banner']) || !empty($editingPromotion['show_site_banner'])
                                ? 'checked'
                                : '' ?>
                        >
                        <span>
                            <strong>Show site banner</strong>
                            <span>Display the promotion across eligible Llama Scout pages.</span>
                        </span>
                    </label>

                    <label class="admin-campaign-toggle">
                        <input
                            type="checkbox"
                            name="show_countdown"
                            value="1"
                            <?= !empty($editingPromotion['show_countdown']) ? 'checked' : '' ?>
                        >
                        <span>
                            <strong>Show countdown</strong>
                            <span>Show time remaining until the scheduled promotion ends.</span>
                        </span>
                    </label>
                </div>

                <div class="admin-membership-form-grid">
                    <label>
                        Banner text
                        <input
                            type="text"
                            name="banner_text"
                            maxlength="255"
                            placeholder="Black Friday: Save 25% on Llama Scout membership"
                            value="<?= moderation_e((string) ($editingPromotion['banner_text'] ?? '')) ?>"
                        >
                    </label>

                    <label>
                        Banner link
                        <input
                            type="url"
                            name="landing_url"
                            maxlength="500"
                            placeholder="https://llamascout.com/membership"
                            value="<?= moderation_e((string) ($editingPromotion['landing_url'] ?? '')) ?>"
                        >
                    </label>
                </div>
            </div>

            <div class="admin-campaign-form-section">
                <h3>Campaign email</h3>
                <p>
                    Schedule an announcement for free registered members. Saving the campaign
                    stores the schedule; the campaign worker will send it when due.
                </p>

                <label class="admin-campaign-toggle">
                    <input
                        type="checkbox"
                        name="email_enabled"
                        value="1"
                        <?= !empty($editingPromotion['email_enabled']) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Send campaign announcement</strong>
                        <span>Audience: free registered Llama Scout members.</span>
                    </span>
                </label>

                <div class="admin-membership-form-grid">
                    <label>
                        Send at, Mountain Time
                        <input
                            type="datetime-local"
                            name="email_send_at"
                            value="<?= moderation_e(
                                membership_admin_utc_to_input($editingPromotion['email_send_at'] ?? null)
                            ) ?>"
                        >
                    </label>

                    <label>
                        Subject
                        <input
                            type="text"
                            name="email_subject"
                            maxlength="255"
                            placeholder="Black Friday at Llama Scout"
                            value="<?= moderation_e((string) ($editingPromotion['email_subject'] ?? '')) ?>"
                        >
                    </label>

                    <label>
                        Preheader
                        <input
                            type="text"
                            name="email_preheader"
                            maxlength="255"
                            placeholder="Save 25% when you upgrade this weekend."
                            value="<?= moderation_e((string) ($editingPromotion['email_preheader'] ?? '')) ?>"
                        >
                    </label>
                </div>

                <label>
                    Email message
                    <textarea
                        name="email_body_text"
                        rows="5"
                        placeholder="Our Black Friday membership sale is live..."
                    ><?= moderation_e((string) ($editingPromotion['email_body_text'] ?? '')) ?></textarea>
                </label>
            </div>

            <div class="admin-campaign-form-section">
                <h3>Final reminder</h3>
                <p>Optional second message before the promotion ends.</p>

                <label class="admin-campaign-toggle">
                    <input
                        type="checkbox"
                        name="reminder_enabled"
                        value="1"
                        <?= !empty($editingPromotion['reminder_enabled']) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Send final reminder</strong>
                        <span>Useful for a final-hours or last-day message.</span>
                    </span>
                </label>

                <div class="admin-membership-form-grid">
                    <label>
                        Reminder at, Mountain Time
                        <input
                            type="datetime-local"
                            name="reminder_send_at"
                            value="<?= moderation_e(
                                membership_admin_utc_to_input($editingPromotion['reminder_send_at'] ?? null)
                            ) ?>"
                        >
                    </label>

                    <label>
                        Reminder subject
                        <input
                            type="text"
                            name="reminder_subject"
                            maxlength="255"
                            placeholder="Last chance: Black Friday ends tonight"
                            value="<?= moderation_e((string) ($editingPromotion['reminder_subject'] ?? '')) ?>"
                        >
                    </label>
                </div>

                <label>
                    Reminder message
                    <textarea
                        name="reminder_body_text"
                        rows="4"
                        placeholder="The Llama Scout sale ends tonight..."
                    ><?= moderation_e((string) ($editingPromotion['reminder_body_text'] ?? '')) ?></textarea>
                </label>
            </div>

            <?php if (!$editingPromotion): ?>
                <div class="admin-membership-policy-note">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    <div>
                        <strong>Automatic introductory promotion</strong>
                        <span>
                            Monthly signups receive the discounted rate for 12 monthly invoices.
                            Annual signups receive the discount on their first annual invoice.
                            Stripe then returns the subscription to its normal recurring price.
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <button class="admin-button" type="submit">
                <?= $editingPromotion ? 'Save campaign details' : 'Create promotion in Stripe' ?>
            </button>
        </form>
    </section>

</section>

<section class="admin-panel admin-campaign-section">
    <header class="admin-panel-header">
        <div>
            <p>Promotion calendar</p>
            <h2>Scheduled Campaigns</h2>
        </div>
        <span><?= number_format(count($promotions)) ?> total</span>
    </header>

    <?php if (!$promotions): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
            <h3>No promotions scheduled.</h3>
            <p>Create your first campaign above.</p>
        </div>
    <?php else: ?>
        <?php foreach ($calendarGroups as $month => $monthPromotions): ?>
            <div class="admin-campaign-calendar-month">
                <h3><?= moderation_e($month) ?></h3>

                <div class="admin-membership-promotion-list">
                    <?php foreach ($monthPromotions as $promotion): ?>
                        <?php
                        $status = membership_admin_status_label($promotion);
                        $rules = array_filter(explode('|', (string) ($promotion['plan_rules'] ?? '')));
                        $delivery = $promotionStats[(int) $promotion['id']] ?? [];
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

                                <div class="admin-campaign-card-actions">
                                    <a
                                        class="admin-button"
                                        href="/memberships.php?edit=<?= (int) $promotion['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form method="post">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= moderation_e(moderation_csrf_token()) ?>"
                                        >
                                        <input type="hidden" name="membership_action" value="toggle-promotion">
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
                            </div>

                            <dl class="admin-membership-promotion-meta">
                                <div>
                                    <dt>Starts</dt>
                                    <dd><?= moderation_e(
                                        llama_format_datetime(
                                            (string) $promotion['starts_at'],
                                            $mountainTz,
                                            'M j, Y g:i A T'
                                        )
                                    ) ?></dd>
                                </div>
                                <div>
                                    <dt>Ends</dt>
                                    <dd><?= moderation_e(
                                        llama_format_datetime(
                                            (string) $promotion['ends_at'],
                                            $mountainTz,
                                            'M j, Y g:i A T'
                                        )
                                    ) ?></dd>
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

                            <div class="admin-campaign-badges">
                                <?php if (!empty($promotion['show_site_banner'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                                        Site banner
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($promotion['show_countdown'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                                        Countdown
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($promotion['email_enabled'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                        Email scheduled
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($promotion['reminder_enabled'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                                        Reminder scheduled
                                    </span>
                                <?php endif; ?>

                                <?php if ((int) ($delivery['sent_count'] ?? 0) > 0): ?>
                                    <span class="admin-campaign-badge">
                                        <?= number_format((int) $delivery['sent_count']) ?> sent
                                    </span>
                                <?php endif; ?>

                                <?php if ((int) ($delivery['failed_count'] ?? 0) > 0): ?>
                                    <span class="admin-campaign-badge">
                                        <?= number_format((int) $delivery['failed_count']) ?> failed
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($promotion['banner_text'])): ?>
                                <p class="admin-membership-promotion-description">
                                    <strong>Banner:</strong>
                                    <?= moderation_e((string) $promotion['banner_text']) ?>
                                </p>
                            <?php elseif (!empty($promotion['public_description'])): ?>
                                <p class="admin-membership-promotion-description">
                                    <?= moderation_e((string) $promotion['public_description']) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="admin-panel admin-campaign-section">
    <header class="admin-panel-header">
        <div>
            <p>Customer-entered codes</p>
            <h2>Promotion Codes</h2>
        </div>
        <span><?= $manualCodesEnabled ? 'Checkout enabled' : 'Checkout disabled' ?></span>
    </header>

    <div class="admin-campaign-code-placeholder">
        <i class="fa-solid fa-ticket" aria-hidden="true"></i>
        <div>
            <strong>Promotion-code checkout support is already present.</strong>
            <p>
                The switch below controls whether Stripe's promotion-code field appears
                when no automatic sale is active. Code creation and expiration management
                will be connected to this admin page next.
            </p>
        </div>
    </div>

    <form class="admin-membership-code-setting" method="post">
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
        <input type="hidden" name="membership_action" value="save-code-setting">

        <label>
            <input
                type="checkbox"
                name="manual_codes_enabled"
                value="1"
                <?= $manualCodesEnabled ? 'checked' : '' ?>
            >
            Allow Stripe promotion codes at membership checkout when no automatic promotion is active.
        </label>

        <button class="admin-button" type="submit">
            Save checkout setting
        </button>
    </form>
</section>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

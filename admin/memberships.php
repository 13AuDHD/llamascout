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

$viewerTimezone = llama_viewer_timezone();
$timezoneLabels = llama_timezones();
$viewerTimezoneLabel =
    (string) (
        $timezoneLabels[$viewerTimezone]
        ?? $viewerTimezone
    );

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
        new DateTimeZone(llama_viewer_timezone())
    );

    if (!$local) {
        throw new InvalidArgumentException('A valid date and time is required.');
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
            ->setTimezone(new DateTimeZone(llama_viewer_timezone()))
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

function membership_admin_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function membership_admin_sale_rules_from_post(
    PDO $db,
    array $input
): array {
    $source =
        is_array($input['sale_rules'] ?? null)
            ? $input['sale_rules']
            : [];

    $rules = [];

    $planStmt = $db->prepare(
        'SELECT
            p.id,
            p.name,
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

    foreach ($source as $planIdRaw => $ruleInput) {
        $planId = (int) $planIdRaw;

        if (
            $planId < 1
            || !is_array($ruleInput)
            || empty($ruleInput['enabled'])
        ) {
            continue;
        }

        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            throw new InvalidArgumentException(
                'One selected membership plan is unavailable.'
            );
        }

        $interval = (string) ($plan['interval_slug'] ?? '');

        if (!in_array(
            $interval,
            [
                LLAMA_MEMBERSHIP_INTERVAL_MONTHLY,
                LLAMA_MEMBERSHIP_INTERVAL_ANNUAL,
            ],
            true
        )) {
            throw new InvalidArgumentException(
                'Only monthly and annual membership plans can be added to a sale.'
            );
        }

        $discountType =
            trim(
                (string) (
                    $ruleInput['discount_type']
                    ?? LLAMA_PROMOTION_DISCOUNT_PERCENT
                )
            );

        if (!in_array(
            $discountType,
            [
                LLAMA_PROMOTION_DISCOUNT_PERCENT,
                LLAMA_PROMOTION_DISCOUNT_AMOUNT,
            ],
            true
        )) {
            throw new InvalidArgumentException(
                'Choose a valid discount type for ' . ucfirst($interval) . '.'
            );
        }

        $discountRaw =
            trim(
                (string) (
                    $ruleInput['discount_value']
                    ?? ''
                )
            );

        if (!is_numeric($discountRaw)) {
            throw new InvalidArgumentException(
                'Enter a valid ' . $interval . ' discount.'
            );
        }

        $discountValue =
            $discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT
                ? (int) round((float) $discountRaw)
                : (int) round(((float) $discountRaw) * 100);

        if ($discountValue < 1) {
            throw new InvalidArgumentException(
                ucfirst($interval) . ' discount must be greater than zero.'
            );
        }

        if (
            $discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT
            && $discountValue > 100
        ) {
            throw new InvalidArgumentException(
                ucfirst($interval) . ' percentage discount cannot exceed 100%.'
            );
        }

        $rules[$planId] = [
            'plan' => $plan,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
        ];
    }

    if (!$rules) {
        throw new InvalidArgumentException(
            'Put at least one membership plan on sale.'
        );
    }

    $hasAnnual = false;

    foreach ($rules as $rule) {
        if (
            (string) ($rule['plan']['interval_slug'] ?? '')
            === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL
        ) {
            $hasAnnual = true;
            break;
        }
    }

    if (!$hasAnnual) {
        throw new InvalidArgumentException(
            'Every promotion needs an Annual sale. Monthly can be added separately.'
        );
    }

    return $rules;
}

function membership_admin_create_sale_coupon(
    object $stripe,
    int $promotionId,
    string $promotionName,
    array $rule
): array {
    $plan = $rule['plan'];
    $planId = (int) $plan['id'];
    $interval = (string) $plan['interval_slug'];
    $discountType = (string) $rule['discount_type'];
    $discountValue = (int) $rule['discount_value'];

    $couponData = [
        'name' =>
            $promotionName
            . ' - '
            . ucfirst($interval),
        'metadata' => [
            'llama_membership_promotion_id' =>
                (string) $promotionId,
            'llama_membership_plan_id' =>
                (string) $planId,
            'llama_membership_interval' =>
                $interval,
            'llama_discount_policy' =>
                'first_year_only',
        ],
    ];

    if ($discountType === LLAMA_PROMOTION_DISCOUNT_PERCENT) {
        $couponData['percent_off'] = $discountValue;
    } else {
        $couponData['amount_off'] = $discountValue;
        $couponData['currency'] =
            strtolower(
                trim(
                    (string) (
                        $plan['current_currency']
                        ?? $plan['currency']
                        ?? 'usd'
                    )
                )
            ) ?: 'usd';
    }

    $productId =
        trim(
            (string) (
                $plan['stripe_product_id']
                ?? ''
            )
        );

    if ($productId !== '') {
        $couponData['applies_to'] = [
            'products' => [$productId],
        ];
    }

    if ($interval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY) {
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
        throw new RuntimeException(
            'Stripe did not return a Coupon ID for ' . ucfirst($interval) . '.'
        );
    }

    return [
        'coupon_id' => $couponId,
        'duration' => $duration,
        'duration_count' => $durationCount,
    ];
}

function membership_admin_discount_label(array $rule): string
{
    if (
        (string) ($rule['discount_type'] ?? '')
        === LLAMA_PROMOTION_DISCOUNT_PERCENT
    ) {
        return ((int) ($rule['discount_value'] ?? 0)) . '% off';
    }

    return membership_admin_money(
        (int) ($rule['discount_value'] ?? 0)
    ) . ' off';
}

function membership_admin_rule_sale_price(array $rule): int
{
    return llama_membership_discounted_price_cents(
        (int) ($rule['base_price_cents'] ?? 0),
        (string) ($rule['discount_type'] ?? ''),
        (int) ($rule['discount_value'] ?? 0)
    );
}


function membership_admin_status_label(array $promotion): string
{
    if (empty($promotion['is_enabled'])) {
        return 'disabled';
    }

    try {
        $utc = new DateTimeZone('UTC');

        $start = (
            new DateTimeImmutable(
                (string) ($promotion['starts_at'] ?? ''),
                $utc
            )
        )->getTimestamp();

        $end = (
            new DateTimeImmutable(
                (string) ($promotion['ends_at'] ?? ''),
                $utc
            )
        )->getTimestamp();
    } catch (Throwable) {
        return 'disabled';
    }

    $now = time();

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
            ->setTimezone(new DateTimeZone(llama_viewer_timezone()))
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

                if ($name === '') {
                    throw new InvalidArgumentException('Promotion name is required.');
                }

                if (!$startsAt || !$endsAt || strtotime($endsAt) <= strtotime($startsAt)) {
                    throw new InvalidArgumentException('Promotion end must be after its start.');
                }

                $saleRules =
                    membership_admin_sale_rules_from_post(
                        $db,
                        $_POST
                    );

                foreach ($saleRules as $planId => $rule) {
                    if (llama_membership_promotion_conflicts(
                        $db,
                        (int) $planId,
                        $startsAt,
                        $endsAt
                    )) {
                        throw new InvalidArgumentException(
                            ucfirst((string) $rule['plan']['interval_slug'])
                            . ' overlaps another enabled automatic promotion.'
                        );
                    }
                }

                $showBanner = membership_admin_checkbox('show_site_banner');
                $showCountdown = membership_admin_checkbox('show_countdown');

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
                        reminder_enabled,
                        created_by
                     )
                     VALUES
                     (?, ?, ?, "automatic", 1, ?, ?, ?, ?, ?, ?, 1, 0, "free_members", 0, ?)'
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
                    $actorUserId > 0 ? $actorUserId : null,
                ]);

                $promotionId = (int) $db->lastInsertId();
                $stripe = llama_stripe_client();

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

                foreach ($saleRules as $planId => $rule) {
                    $coupon = membership_admin_create_sale_coupon(
                        $stripe,
                        $promotionId,
                        $name,
                        $rule
                    );

                    $insertRule->execute([
                        $promotionId,
                        (int) $planId,
                        (int) $rule['plan']['current_price_id'],
                        (string) $rule['discount_type'],
                        (int) $rule['discount_value'],
                        (string) $coupon['coupon_id'],
                        (string) $coupon['duration'],
                        (int) $coupon['duration_count'],
                    ]);
                }

                $db->commit();

                $notice =
                    'Promotion created and connected to Stripe. Configure its messages under Communications > Email Campaigns.';
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

                $saleRules =
                    membership_admin_sale_rules_from_post(
                        $db,
                        $_POST
                    );

                foreach ($saleRules as $planId => $rule) {
                    if (llama_membership_promotion_conflicts(
                        $db,
                        (int) $planId,
                        $startsAt,
                        $endsAt,
                        $promotionId
                    )) {
                        throw new InvalidArgumentException(
                            ucfirst((string) $rule['plan']['interval_slug'])
                            . ' overlaps another enabled automatic promotion.'
                        );
                    }
                }

                $existingRulesStmt = $db->prepare(
                    'SELECT *
                     FROM membership_promotion_plans
                     WHERE promotion_id = ?'
                );
                $existingRulesStmt->execute([$promotionId]);

                $existingRules = [];

                foreach ($existingRulesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existingRules[(int) $row['plan_id']] = $row;
                }

                $showBanner = membership_admin_checkbox('show_site_banner');
                $showCountdown = membership_admin_checkbox('show_countdown');
                $stripe = llama_stripe_client();

                $db->beginTransaction();

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
                        ends_at = ?
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
                    $promotionId,
                ]);

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

                $updateRule = $db->prepare(
                    'UPDATE membership_promotion_plans
                     SET
                        plan_price_id = ?,
                        discount_type = ?,
                        discount_value = ?,
                        stripe_coupon_id = ?,
                        discount_duration = ?,
                        duration_count = ?,
                        allow_manual_promotion_codes = 0
                     WHERE promotion_id = ?
                       AND plan_id = ?'
                );

                foreach ($saleRules as $planId => $rule) {
                    $planId = (int) $planId;
                    $oldRule = $existingRules[$planId] ?? null;

                    $ruleChanged =
                        !$oldRule
                        || (int) ($oldRule['plan_price_id'] ?? 0)
                            !== (int) $rule['plan']['current_price_id']
                        || (string) ($oldRule['discount_type'] ?? '')
                            !== (string) $rule['discount_type']
                        || (int) ($oldRule['discount_value'] ?? 0)
                            !== (int) $rule['discount_value'];

                    if (!$ruleChanged) {
                        continue;
                    }

                    $coupon = membership_admin_create_sale_coupon(
                        $stripe,
                        $promotionId,
                        $name,
                        $rule
                    );

                    if ($oldRule) {
                        $updateRule->execute([
                            (int) $rule['plan']['current_price_id'],
                            (string) $rule['discount_type'],
                            (int) $rule['discount_value'],
                            (string) $coupon['coupon_id'],
                            (string) $coupon['duration'],
                            (int) $coupon['duration_count'],
                            $promotionId,
                            $planId,
                        ]);
                    } else {
                        $insertRule->execute([
                            $promotionId,
                            $planId,
                            (int) $rule['plan']['current_price_id'],
                            (string) $rule['discount_type'],
                            (int) $rule['discount_value'],
                            (string) $coupon['coupon_id'],
                            (string) $coupon['duration'],
                            (int) $coupon['duration_count'],
                        ]);
                    }
                }

                $selectedPlanIds = array_map(
                    'intval',
                    array_keys($saleRules)
                );

                $placeholders =
                    implode(
                        ',',
                        array_fill(
                            0,
                            count($selectedPlanIds),
                            '?'
                        )
                    );

                $deleteStmt = $db->prepare(
                    'DELETE FROM membership_promotion_plans
                     WHERE promotion_id = ?
                       AND plan_id NOT IN (' . $placeholders . ')'
                );

                $deleteStmt->execute(
                    array_merge(
                        [$promotionId],
                        $selectedPlanIds
                    )
                );

                $db->commit();

                $notice =
                    'Promotion updated. Any changed discounts received new Stripe coupons for new checkouts.';
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
$plansById = [];
$promotions = [];
$promotionRulesByPromotionId = [];
$editingPromotionRulesByPlanId = [];
$manualCodesEnabled = false;
$editingPromotion = null;
$promotionEventStats = [];

if ($error === '') {
    $plans = llama_membership_plans($db, false);

    foreach ($plans as $plan) {
        $plansById[(int) $plan['id']] = $plan;
    }

    $promotions = $db->query(
        'SELECT *
         FROM membership_promotions
         ORDER BY starts_at ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $rulesStmt = $db->query(
        'SELECT
            mpp.*,
            p.name AS plan_name,
            p.interval_slug,
            COALESCE(
                pp.amount_cents,
                cp.amount_cents,
                p.base_price_cents
            ) AS base_price_cents,
            COALESCE(
                pp.currency,
                cp.currency,
                p.currency,
                "usd"
            ) AS price_currency
         FROM membership_promotion_plans mpp
         INNER JOIN membership_plans p
            ON p.id = mpp.plan_id
         LEFT JOIN membership_plan_prices pp
            ON pp.id = mpp.plan_price_id
         LEFT JOIN membership_plan_prices cp
            ON cp.plan_id = p.id
           AND cp.is_current = 1
         ORDER BY
            mpp.promotion_id ASC,
            p.sort_order ASC,
            p.id ASC'
    );

    if ($rulesStmt) {
        foreach ($rulesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $promotionRulesByPromotionId[
                (int) $row['promotion_id']
            ][] = $row;
        }
    }

    $setting = $db->query(
        'SELECT manual_promotion_codes_enabled
         FROM membership_checkout_settings
         WHERE id = 1
         LIMIT 1'
    )->fetchColumn();

    $manualCodesEnabled = (bool) $setting;

    $eventStatsStmt = $db->query(
        'SELECT
            promotion_id,
            SUM(event_type = "checkout_started") AS checkout_started,
            SUM(event_type = "membership_purchased") AS membership_purchased,
            COALESCE(
                SUM(
                    CASE
                        WHEN event_type = "membership_purchased"
                        THEN amount_cents
                        ELSE 0
                    END
                ),
                0
            ) AS revenue_cents
         FROM membership_promotion_events
         GROUP BY promotion_id'
    );

    if ($eventStatsStmt) {
        foreach ($eventStatsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $promotionEventStats[(int) $row['promotion_id']] = $row;
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

        foreach (
            $promotionRulesByPromotionId[$editId]
            ?? []
            as $rule
        ) {
            $editingPromotionRulesByPlanId[
                (int) $rule['plan_id']
            ] = $rule;
        }
    }
}

$calendarGroups = [];

foreach ($promotions as $promotion) {
    $calendarGroups[membership_admin_campaign_month($promotion)][] = $promotion;
}

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

<?php if ($error === ''): ?>

<?php
$activeCount = 0;
$scheduledCount = 0;
$totalCampaignPurchases = 0;
$totalCampaignRevenueCents = 0;

foreach ($promotions as $promotion) {
    $status = membership_admin_status_label($promotion);
    if ($status === 'active') $activeCount++;
    if ($status === 'scheduled') $scheduledCount++;

    $eventSummary = $promotionEventStats[(int) $promotion['id']] ?? [];
    $totalCampaignPurchases += (int) ($eventSummary['membership_purchased'] ?? 0);
    $totalCampaignRevenueCents += (int) ($eventSummary['revenue_cents'] ?? 0);
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
        <span>Memberships sold</span>
        <strong><?= number_format($totalCampaignPurchases) ?></strong>
    </div>
    <div class="admin-campaign-summary-card">
        <span>Campaign revenue</span>
        <strong><?= moderation_e(membership_admin_money($totalCampaignRevenueCents)) ?></strong>
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
                            <i aria-hidden="true"><?= llama_icon('brand-stripe') ?></i>
                            <?= !empty($plan['stripe_product_id']) ? 'Product connected' : 'Product missing' ?>
                        </span>
                        <span class="admin-campaign-badge">
                            <i aria-hidden="true"><?= llama_icon('tag') ?></i>
                            <?= !empty($plan['stripe_price_id']) ? 'Price connected' : 'Price missing' ?>
                        </span>
                    </div>


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
            <span><?= moderation_e($viewerTimezoneLabel) ?></span>
        </header>

        <?php if ($editingPromotion): ?>
            <div class="admin-campaign-edit-banner">
                <span>
                    Editing <strong><?= moderation_e((string) $editingPromotion['name']) ?></strong>.
                    Monthly and annual discounts can be changed independently. A changed rule creates a new Stripe coupon for new checkouts.
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
                    Starts, <?= moderation_e($viewerTimezoneLabel) ?>
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
                    Ends, <?= moderation_e($viewerTimezoneLabel) ?>
                    <input
                        type="datetime-local"
                        name="ends_at"
                        value="<?= moderation_e(
                            membership_admin_utc_to_input($editingPromotion['ends_at'] ?? null)
                        ) ?>"
                        required
                    >
                </label>

            </div>

            <div class="admin-campaign-form-section admin-sale-builder">
                <div class="admin-sale-builder-heading">
                    <div>
                        <h3>Plan discounts</h3>
                        <p>
                            Monthly and annual are separate Stripe offers under the same campaign dates.
                            Enable either or both and set each discount independently.
                        </p>
                    </div>
                </div>

                <div class="admin-sale-plan-grid">
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        if (empty($plan['is_active'])) {
                            continue;
                        }

                        $planId = (int) $plan['id'];
                        $interval = (string) $plan['interval_slug'];

                        if (!in_array(
                            $interval,
                            [
                                LLAMA_MEMBERSHIP_INTERVAL_MONTHLY,
                                LLAMA_MEMBERSHIP_INTERVAL_ANNUAL,
                            ],
                            true
                        )) {
                            continue;
                        }

                        $existingRule =
                            $editingPromotionRulesByPlanId[$planId]
                            ?? null;

                        $enabledByDefault =
                            $editingPromotion
                                ? (bool) $existingRule
                                : $interval === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL;

                        $discountTypeValue =
                            $existingRule
                                ? (string) $existingRule['discount_type']
                                : (
                                    $interval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY
                                        ? LLAMA_PROMOTION_DISCOUNT_AMOUNT
                                        : LLAMA_PROMOTION_DISCOUNT_PERCENT
                                );

                        $discountValueRaw =
                            $existingRule
                                ? (
                                    $discountTypeValue === LLAMA_PROMOTION_DISCOUNT_AMOUNT
                                        ? number_format(
                                            (int) $existingRule['discount_value'] / 100,
                                            2,
                                            '.',
                                            ''
                                        )
                                        : (string) (int) $existingRule['discount_value']
                                )
                                : (
                                    $interval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY
                                        ? '1.00'
                                        : '25'
                                );
                        ?>

                        <section
                            class="admin-sale-plan-card"
                            data-sale-plan="<?= moderation_e($interval) ?>"
                            data-base-cents="<?= (int) $plan['base_price_cents'] ?>"
                        >
                            <label class="admin-sale-plan-enable">
                                <?php if ($interval === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL): ?>
                                    <input
                                        type="hidden"
                                        name="sale_rules[<?= $planId ?>][enabled]"
                                        value="1"
                                    >
                                    <input
                                        type="checkbox"
                                        checked
                                        disabled
                                        data-sale-enabled
                                    >
                                <?php else: ?>
                                    <input
                                        type="checkbox"
                                        name="sale_rules[<?= $planId ?>][enabled]"
                                        value="1"
                                        <?= $enabledByDefault ? 'checked' : '' ?>
                                        data-sale-enabled
                                    >
                                <?php endif; ?>
                                <span>
                                    <strong>
                                        <?= moderation_e(ucfirst($interval)) ?> sale
                                        <?= $interval === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL ? ' · Required' : ' · Optional' ?>
                                    </strong>
                                    <small>
                                        Regular <?= moderation_e(
                                            membership_admin_money(
                                                (int) $plan['base_price_cents']
                                            )
                                        ) ?>
                                        / <?= $interval === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL ? 'year' : 'month' ?>
                                    </small>
                                </span>
                            </label>

                            <div class="admin-sale-plan-fields">
                                <label>
                                    Discount type
                                    <select
                                        name="sale_rules[<?= $planId ?>][discount_type]"
                                        data-sale-type
                                    >
                                        <option
                                            value="percent"
                                            <?= $discountTypeValue === LLAMA_PROMOTION_DISCOUNT_PERCENT ? 'selected' : '' ?>
                                        >Percent off</option>
                                        <option
                                            value="amount"
                                            <?= $discountTypeValue === LLAMA_PROMOTION_DISCOUNT_AMOUNT ? 'selected' : '' ?>
                                        >Dollar amount off</option>
                                    </select>
                                </label>

                                <label>
                                    Discount value
                                    <input
                                        type="number"
                                        name="sale_rules[<?= $planId ?>][discount_value]"
                                        min="0.01"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="<?= moderation_e($discountValueRaw) ?>"
                                        data-sale-value
                                    >
                                </label>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <?php
                $initialSalePreview = [];

                foreach ($plans as $previewPlan) {
                    if (empty($previewPlan['is_active'])) {
                        continue;
                    }

                    $previewInterval =
                        (string) ($previewPlan['interval_slug'] ?? '');

                    if (!in_array(
                        $previewInterval,
                        [
                            LLAMA_MEMBERSHIP_INTERVAL_MONTHLY,
                            LLAMA_MEMBERSHIP_INTERVAL_ANNUAL,
                        ],
                        true
                    )) {
                        continue;
                    }

                    $previewPlanId = (int) $previewPlan['id'];
                    $previewRule =
                        $editingPromotionRulesByPlanId[$previewPlanId]
                        ?? null;

                    $previewEnabled =
                        $previewInterval === LLAMA_MEMBERSHIP_INTERVAL_ANNUAL
                        || (
                            $editingPromotion
                                ? (bool) $previewRule
                                : false
                        );

                    $previewType =
                        $previewRule
                            ? (string) $previewRule['discount_type']
                            : (
                                $previewInterval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY
                                    ? LLAMA_PROMOTION_DISCOUNT_AMOUNT
                                    : LLAMA_PROMOTION_DISCOUNT_PERCENT
                            );

                    $previewValue =
                        $previewRule
                            ? (int) $previewRule['discount_value']
                            : (
                                $previewInterval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY
                                    ? 100
                                    : 25
                            );

                    $previewBase =
                        (int) $previewPlan['base_price_cents'];

                    $previewSale = $previewEnabled
                        ? llama_membership_discounted_price_cents(
                            $previewBase,
                            $previewType,
                            $previewValue
                        )
                        : $previewBase;

                    $initialSalePreview[$previewInterval] = [
                        'enabled' => $previewEnabled,
                        'base' => $previewBase,
                        'sale' => $previewSale,
                    ];
                }
                ?>

                <section class="admin-sale-calculator" aria-live="polite">
                    <header>
                        <div>
                            <span>Price check</span>
                            <h4>Sale price comparison</h4>
                        </div>
                        <small>Monthly × 12 · Annual ÷ 12</small>
                    </header>

                    <div class="admin-sale-calculator-grid">
                        <?php
                        $monthlyPreview = $initialSalePreview['monthly'] ?? [
                            'enabled' => false,
                            'base' => 0,
                            'sale' => 0,
                        ];
                        $annualPreview = $initialSalePreview['annual'] ?? [
                            'enabled' => false,
                            'base' => 0,
                            'sale' => 0,
                        ];
                        ?>

                        <div
                            class="admin-sale-calculator-row<?= $monthlyPreview['enabled'] ? '' : ' is-disabled' ?>"
                            data-sale-summary="monthly"
                        >
                            <strong>Monthly</strong>
                            <span data-sale-regular>
                                <?= moderation_e(membership_admin_money((int) $monthlyPreview['base'])) ?> / month regular
                            </span>
                            <span data-sale-price>
                                <?= $monthlyPreview['enabled']
                                    ? moderation_e(membership_admin_money((int) $monthlyPreview['sale'])) . ' / month sale'
                                    : 'Not included in sale' ?>
                            </span>
                            <span data-sale-equivalent>
                                <?= moderation_e(
                                    membership_admin_money(
                                        (int) (
                                            ($monthlyPreview['enabled']
                                                ? $monthlyPreview['sale']
                                                : $monthlyPreview['base']) * 12
                                        )
                                    )
                                ) ?> / year<?= $monthlyPreview['enabled'] ? '' : ' at regular price' ?>
                            </span>
                        </div>

                        <div
                            class="admin-sale-calculator-row<?= $annualPreview['enabled'] ? '' : ' is-disabled' ?>"
                            data-sale-summary="annual"
                        >
                            <strong>Annual</strong>
                            <span data-sale-regular>
                                <?= moderation_e(membership_admin_money((int) $annualPreview['base'])) ?> / year regular
                            </span>
                            <span data-sale-price>
                                <?= $annualPreview['enabled']
                                    ? moderation_e(membership_admin_money((int) $annualPreview['sale'])) . ' / year sale'
                                    : 'Not included in sale' ?>
                            </span>
                            <span data-sale-equivalent>
                                <?= moderation_e(
                                    membership_admin_money(
                                        (int) round(
                                            (
                                                $annualPreview['enabled']
                                                    ? $annualPreview['sale']
                                                    : $annualPreview['base']
                                            ) / 12
                                        )
                                    )
                                ) ?> / month<?= $annualPreview['enabled'] ? '' : ' at regular price' ?>
                            </span>
                        </div>
                    </div>
                </section>
            </div>

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

            <button class="admin-button" type="submit">
                <?= $editingPromotion ? 'Save promotion' : 'Create sale in Stripe' ?>
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
            <i aria-hidden="true"><?= llama_icon('calendar') ?></i>
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
                        $rules =
                            $promotionRulesByPromotionId[(int) $promotion['id']]
                            ?? [];
                        $events = $promotionEventStats[(int) $promotion['id']] ?? [];
                        $checkoutStarts = (int) ($events['checkout_started'] ?? 0);
                        $membershipsPurchased = (int) ($events['membership_purchased'] ?? 0);
                        $campaignRevenueCents = (int) ($events['revenue_cents'] ?? 0);
                        $conversionRate = $checkoutStarts > 0
                            ? ($membershipsPurchased / $checkoutStarts) * 100
                            : 0.0;
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

                                    <a
                                        class="admin-button"
                                        href="/email-campaigns.php?id=<?= (int) $promotion['id'] ?>"
                                    >
                                        Campaign emails
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
                                        llama_format_viewer_datetime(
                                            (string) $promotion['starts_at'],
                                            'M j, Y g:i A T'
                                        )
                                    ) ?></dd>
                                </div>
                                <div>
                                    <dt>Ends</dt>
                                    <dd><?= moderation_e(
                                        llama_format_viewer_datetime(
                                            (string) $promotion['ends_at'],
                                            'M j, Y g:i A T'
                                        )
                                    ) ?></dd>
                                </div>
                            </dl>

                            <div class="admin-membership-rule-list">
                                <?php foreach ($rules as $rule): ?>
                                    <?php
                                    $interval =
                                        (string) ($rule['interval_slug'] ?? '');
                                    $basePrice =
                                        (int) ($rule['base_price_cents'] ?? 0);
                                    $salePrice =
                                        membership_admin_rule_sale_price($rule);

                                    $equivalent =
                                        $interval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY
                                            ? membership_admin_money($salePrice * 12) . ' / year'
                                            : membership_admin_money((int) round($salePrice / 12)) . ' / month';
                                    ?>
                                    <div class="admin-membership-rule-card">
                                        <span>
                                            <?= moderation_e(ucfirst($interval)) ?>
                                            · <?= moderation_e(membership_admin_discount_label($rule)) ?>
                                        </span>
                                        <strong>
                                            <?= moderation_e(membership_admin_money($salePrice)) ?>
                                            / <?= $interval === LLAMA_MEMBERSHIP_INTERVAL_MONTHLY ? 'month' : 'year' ?>
                                        </strong>
                                        <small><?= moderation_e($equivalent) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-campaign-results">
                                <div class="admin-campaign-result is-revenue">
                                    <span>Revenue</span>
                                    <strong><?= moderation_e(membership_admin_money($campaignRevenueCents)) ?></strong>
                                </div>
                                <div class="admin-campaign-result">
                                    <span>Memberships</span>
                                    <strong><?= number_format($membershipsPurchased) ?></strong>
                                </div>
                                <div class="admin-campaign-result">
                                    <span>Checkout starts</span>
                                    <strong><?= number_format($checkoutStarts) ?></strong>
                                </div>
                                <div class="admin-campaign-result">
                                    <span>Conversion</span>
                                    <strong><?= moderation_e(number_format($conversionRate, 1)) ?>%</strong>
                                </div>
                            </div>

                            <div class="admin-campaign-badges">
                                <?php if (!empty($promotion['show_site_banner'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i aria-hidden="true"><?= llama_icon('speakerphone') ?></i>
                                        Site banner
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($promotion['show_countdown'])): ?>
                                    <span class="admin-campaign-badge">
                                        <i aria-hidden="true"><?= llama_icon('hourglass') ?></i>
                                        Countdown
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
        <a class="admin-button" href="/promotion-codes.php">
            Manage codes
        </a>
    </header>


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

<script src="https://llamascout.com/js/admin/membership-promotions.js"></script>

<?php require __DIR__ . '/_footer.php'; ?>

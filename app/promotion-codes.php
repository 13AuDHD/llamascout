<?php

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/memberships.php';


function llama_promotion_code_should_be_active(array $row): bool
{
    if (empty($row['is_enabled'])) {
        return false;
    }

    $now = time();
    $start = strtotime((string) ($row['starts_at'] ?? '')) ?: 0;
    $end = strtotime((string) ($row['ends_at'] ?? '')) ?: 0;

    return $start > 0
        && $end > 0
        && $now >= $start
        && $now < $end;
}


function llama_sync_membership_promotion_codes(PDO $db): array
{
    $summary = [
        'checked' => 0,
        'changed' => 0,
    ];

    try {
        $table = $db->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'membership_promotion_codes'"
        );

        if (!$table || (int) $table->fetchColumn() < 1) {
            return $summary;
        }

        $rows = $db->query(
            'SELECT *
             FROM membership_promotion_codes
             WHERE stripe_promotion_code_id IS NOT NULL
               AND stripe_promotion_code_id <> ""
             ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return $summary;
        }

        $stripe = llama_stripe_client();
        $update = $db->prepare(
            'UPDATE membership_promotion_codes
             SET stripe_active = ?
             WHERE id = ?'
        );

        foreach ($rows as $row) {
            $summary['checked']++;

            $desired = llama_promotion_code_should_be_active($row);
            $current = !empty($row['stripe_active']);

            if ($desired === $current) {
                continue;
            }

            $stripe->promotionCodes->update(
                (string) $row['stripe_promotion_code_id'],
                [
                    'active' => $desired,
                ]
            );

            $update->execute([
                $desired ? 1 : 0,
                (int) $row['id'],
            ]);

            $summary['changed']++;
        }

        return $summary;
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout promotion code sync error: '
            . $exception->getMessage()
        );

        return $summary;
    }
}


function llama_create_membership_promotion_code(
    PDO $db,
    array $input,
    ?int $createdBy = null
): int {
    $name = trim((string) ($input['internal_name'] ?? ''));
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $discountType = trim((string) ($input['discount_type'] ?? 'percent'));
    $discountValue = (int) ($input['discount_value'] ?? 0);
    $planScope = trim((string) ($input['plan_scope'] ?? 'all'));
    $startsAt = trim((string) ($input['starts_at'] ?? ''));
    $endsAt = trim((string) ($input['ends_at'] ?? ''));
    $firstTimeOnly = !empty($input['first_time_customers_only']);
    $maxRedemptions = isset($input['max_redemptions'])
        && (int) $input['max_redemptions'] > 0
            ? (int) $input['max_redemptions']
            : null;

    if ($name === '') {
        throw new InvalidArgumentException('Internal name is required.');
    }

    if (!preg_match('/^[A-Z0-9-]{2,100}$/', $code)) {
        throw new InvalidArgumentException(
            'Promotion code may contain letters, numbers, and dashes.'
        );
    }

    if (!in_array($discountType, ['percent', 'amount'], true)) {
        throw new InvalidArgumentException('Choose a valid discount type.');
    }

    if ($discountValue < 1) {
        throw new InvalidArgumentException('Discount must be greater than zero.');
    }

    if ($discountType === 'percent' && $discountValue > 100) {
        throw new InvalidArgumentException('Percentage discount cannot exceed 100%.');
    }

    if (!in_array($planScope, ['all', 'monthly', 'annual'], true)) {
        throw new InvalidArgumentException('Choose a valid membership plan.');
    }

    if (
        strtotime($startsAt) === false
        || strtotime($endsAt) === false
        || strtotime($endsAt) <= strtotime($startsAt)
    ) {
        throw new InvalidArgumentException('Promotion code dates are invalid.');
    }

    $duplicate = $db->prepare(
        'SELECT id
         FROM membership_promotion_codes
         WHERE UPPER(code) = ?
         LIMIT 1'
    );
    $duplicate->execute([$code]);

    if ($duplicate->fetchColumn()) {
        throw new InvalidArgumentException('That promotion code already exists.');
    }

    $plans = llama_membership_plans($db, true);
    $productIds = [];
    $currencies = [];

    foreach ($plans as $plan) {
        $interval = (string) ($plan['interval_slug'] ?? '');

        if ($planScope !== 'all' && $interval !== $planScope) {
            continue;
        }

        $productId = trim((string) ($plan['stripe_product_id'] ?? ''));

        if ($productId === '') {
            throw new RuntimeException(
                'The selected membership plan is not connected to a Stripe Product.'
            );
        }

        $productIds[] = $productId;
        $currencies[] = strtolower((string) ($plan['currency'] ?? 'usd'));
    }

    $productIds = array_values(array_unique($productIds));
    $currencies = array_values(array_unique($currencies));

    if (!$productIds) {
        throw new RuntimeException('No active Stripe membership product is available.');
    }

    if ($discountType === 'amount' && count($currencies) !== 1) {
        throw new RuntimeException(
            'Dollar-off codes require the selected plans to use one currency.'
        );
    }

    $stripe = llama_stripe_client();

    $couponData = [
        'name' => $name,
        'duration' => 'once',
        'applies_to' => [
            'products' => $productIds,
        ],
        'metadata' => [
            'llama_promotion_code' => $code,
            'llama_plan_scope' => $planScope,
        ],
    ];

    if ($discountType === 'percent') {
        $couponData['percent_off'] = $discountValue;
    } else {
        $couponData['amount_off'] = $discountValue;
        $couponData['currency'] = $currencies[0] ?? 'usd';
    }

    $coupon = $stripe->coupons->create($couponData);
    $couponId = trim((string) ($coupon->id ?? ''));

    if ($couponId === '') {
        throw new RuntimeException('Stripe did not return a Coupon ID.');
    }

    $shouldBeActive = time() >= strtotime($startsAt)
        && time() < strtotime($endsAt);

    $promotionData = [
        'promotion' => [
            'type' => 'coupon',
            'coupon' => $couponId,
        ],
        'active' => $shouldBeActive,
        'code' => $code,
        'expires_at' => strtotime($endsAt),
        'metadata' => [
            'llama_internal_name' => $name,
            'llama_plan_scope' => $planScope,
        ],
        'restrictions' => [
            'first_time_transaction' => $firstTimeOnly,
        ],
    ];

    if ($maxRedemptions !== null) {
        $promotionData['max_redemptions'] = $maxRedemptions;
    }

    $promotionCode = $stripe->promotionCodes->create($promotionData);
    $promotionCodeId = trim((string) ($promotionCode->id ?? ''));

    if ($promotionCodeId === '') {
        throw new RuntimeException('Stripe did not return a Promotion Code ID.');
    }

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_codes
         (
            internal_name,
            code,
            discount_type,
            discount_value,
            plan_scope,
            starts_at,
            ends_at,
            first_time_customers_only,
            max_redemptions,
            stripe_coupon_id,
            stripe_promotion_code_id,
            stripe_active,
            is_enabled,
            created_by
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );

    $stmt->execute([
        $name,
        $code,
        $discountType,
        $discountValue,
        $planScope,
        $startsAt,
        $endsAt,
        $firstTimeOnly ? 1 : 0,
        $maxRedemptions,
        $couponId,
        $promotionCodeId,
        $shouldBeActive ? 1 : 0,
        $createdBy && $createdBy > 0 ? $createdBy : null,
    ]);

    return (int) $db->lastInsertId();
}


function llama_set_membership_promotion_code_enabled(
    PDO $db,
    int $id,
    bool $enabled
): void {
    $stmt = $db->prepare(
        'SELECT *
         FROM membership_promotion_codes
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new InvalidArgumentException('Promotion code not found.');
    }

    $desiredStripeActive = $enabled
        && time() >= (strtotime((string) $row['starts_at']) ?: PHP_INT_MAX)
        && time() < (strtotime((string) $row['ends_at']) ?: 0);

    $stripeId = trim((string) ($row['stripe_promotion_code_id'] ?? ''));

    if ($stripeId !== '') {
        llama_stripe_client()
            ->promotionCodes
            ->update(
                $stripeId,
                ['active' => $desiredStripeActive]
            );
    }

    $update = $db->prepare(
        'UPDATE membership_promotion_codes
         SET
            is_enabled = ?,
            stripe_active = ?
         WHERE id = ?'
    );

    $update->execute([
        $enabled ? 1 : 0,
        $desiredStripeActive ? 1 : 0,
        $id,
    ]);
}


/* =========================================================
   PROMOTION CODE REDEMPTION TRACKING
   ========================================================= */

function llama_record_membership_promotion_code_redemption(
    PDO $db,
    string $stripePromotionCodeId,
    int $userId,
    string $membershipInterval,
    string $checkoutSessionId,
    string $subscriptionId,
    ?int $amountCents
): void {
    $stripePromotionCodeId = trim($stripePromotionCodeId);
    $checkoutSessionId = trim($checkoutSessionId);

    if (
        $stripePromotionCodeId === ''
        || $userId < 1
        || $checkoutSessionId === ''
    ) {
        return;
    }

    $lookup = $db->prepare(
        'SELECT id
         FROM membership_promotion_codes
         WHERE stripe_promotion_code_id = ?
         LIMIT 1'
    );
    $lookup->execute([$stripePromotionCodeId]);

    $promotionCodeId = (int) $lookup->fetchColumn();

    if ($promotionCodeId < 1) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_code_events
         (
            promotion_code_id,
            user_id,
            membership_interval,
            stripe_checkout_session_id,
            stripe_subscription_id,
            amount_cents
         )
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            stripe_subscription_id = VALUES(stripe_subscription_id),
            amount_cents = VALUES(amount_cents)'
    );

    $stmt->execute([
        $promotionCodeId,
        $userId,
        $membershipInterval !== '' ? $membershipInterval : null,
        $checkoutSessionId,
        $subscriptionId !== '' ? $subscriptionId : null,
        $amountCents !== null ? max(0, $amountCents) : null,
    ]);
}


function llama_membership_promotion_code_stats(PDO $db): array
{
    $stats = [];

    $table = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'membership_promotion_code_events'"
    );

    if (!$table || (int) $table->fetchColumn() < 1) {
        return $stats;
    }

    $rows = $db->query(
        'SELECT
            promotion_code_id,
            COUNT(*) AS redemptions,
            COALESCE(SUM(amount_cents), 0) AS revenue_cents
         FROM membership_promotion_code_events
         GROUP BY promotion_code_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $stats[(int) $row['promotion_code_id']] = [
            'redemptions' => (int) ($row['redemptions'] ?? 0),
            'revenue_cents' => (int) ($row['revenue_cents'] ?? 0),
        ];
    }

    return $stats;
}

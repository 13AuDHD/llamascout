<?php

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/memberships.php';


/* =========================================================
   PROMOTION CODE DURATION
   ========================================================= */

function llama_membership_promotion_code_month_options(): array
{
    return [1, 2, 3, 6, 9, 12];
}


function llama_membership_promotion_code_duration_label(
    array $promotionCode
): string {
    $duration = strtolower(
        trim((string) ($promotionCode['discount_duration'] ?? 'once'))
    );

    $months = (int) ($promotionCode['duration_months'] ?? 0);

    if (
        $duration === 'months'
        && in_array(
            $months,
            llama_membership_promotion_code_month_options(),
            true
        )
    ) {
        return $months === 1
            ? '1 month'
            : $months . ' months';
    }

    return 'First payment';
}


function llama_membership_promotion_code_is_multi_month(
    array $promotionCode
): bool {
    $duration = strtolower(
        trim((string) ($promotionCode['discount_duration'] ?? 'once'))
    );

    $months = (int) ($promotionCode['duration_months'] ?? 0);

    return
        $duration === 'months'
        && $months > 1
        && in_array(
            $months,
            llama_membership_promotion_code_month_options(),
            true
        );
}


/* =========================================================
   PROMOTION CODE PRICE
   ========================================================= */

function llama_membership_promotion_code_price_cents(
    int $basePriceCents,
    array $promotionCode
): int {
    $basePriceCents = max(0, $basePriceCents);

    $discountType = strtolower(
        trim((string) ($promotionCode['discount_type'] ?? ''))
    );

    $discountValue = max(
        0,
        (int) ($promotionCode['discount_value'] ?? 0)
    );

    if (
        in_array(
            $discountType,
            ['percent', 'amount'],
            true
        )
    ) {
        return llama_membership_discounted_price_cents(
            $basePriceCents,
            $discountType,
            $discountValue
        );
    }

    return $basePriceCents;
}


/* =========================================================
   UTC TIME
   ========================================================= */

function llama_promotion_code_utc_timestamp(
    ?string $value
): ?int {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )->getTimestamp();

    } catch (Throwable) {
        return null;
    }
}


/* =========================================================
   SHOULD CODE BE ACTIVE
   ========================================================= */

function llama_promotion_code_should_be_active(
    array $row
): bool {
    if (empty($row['is_enabled'])) {
        return false;
    }

    $now = time();

    $start = llama_promotion_code_utc_timestamp(
        (string) ($row['starts_at'] ?? '')
    );

    $end = llama_promotion_code_utc_timestamp(
        (string) ($row['ends_at'] ?? '')
    );

    return
        $start !== null
        && $end !== null
        && $now >= $start
        && $now < $end;
}


/* =========================================================
   SYNC CODE ACTIVE STATUS WITH STRIPE
   ========================================================= */

function llama_sync_membership_promotion_codes(
    PDO $db
): array {
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

        if (
            !$table
            || (int) $table->fetchColumn() < 1
        ) {
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

            $desired =
                llama_promotion_code_should_be_active($row);

            $current =
                !empty($row['stripe_active']);

            if ($desired === $current) {
                continue;
            }

            $stripe
                ->promotionCodes
                ->update(
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


/* =========================================================
   VALIDATE AND NORMALIZE INPUT
   ========================================================= */

function llama_prepare_membership_promotion_code(
    PDO $db,
    array $input,
    ?int $excludeId = null
): array {
    if (
        !llama_membership_column_exists(
            $db,
            'membership_promotion_codes',
            'discount_duration'
        )
        || !llama_membership_column_exists(
            $db,
            'membership_promotion_codes',
            'duration_months'
        )
    ) {
        throw new RuntimeException(
            'Promotion code duration storage is not initialized.'
        );
    }

    $name = trim(
        (string) ($input['internal_name'] ?? '')
    );

    $code = strtoupper(
        trim((string) ($input['code'] ?? ''))
    );

    $discountType = strtolower(
        trim((string) ($input['discount_type'] ?? 'percent'))
    );

    $discountValue =
        (int) ($input['discount_value'] ?? 0);

    $planScope = strtolower(
        trim((string) ($input['plan_scope'] ?? 'all'))
    );

    $discountDuration = strtolower(
        trim((string) ($input['discount_duration'] ?? 'once'))
    );

    $durationMonths =
        isset($input['duration_months'])
            && $input['duration_months'] !== null
            ? (int) $input['duration_months']
            : null;

    $startsAt = trim(
        (string) ($input['starts_at'] ?? '')
    );

    $endsAt = trim(
        (string) ($input['ends_at'] ?? '')
    );

    $firstTimeOnly =
        !empty($input['first_time_customers_only']);

    $maxRedemptions =
        isset($input['max_redemptions'])
        && (int) $input['max_redemptions'] > 0
            ? (int) $input['max_redemptions']
            : null;


    if ($name === '') {
        throw new InvalidArgumentException(
            'Internal name is required.'
        );
    }

    if (
        !preg_match(
            '/^[A-Z0-9-]{2,100}$/',
            $code
        )
    ) {
        throw new InvalidArgumentException(
            'Promotion code may contain letters, numbers, and dashes.'
        );
    }

    if (
        !in_array(
            $discountType,
            ['percent', 'amount', 'promotional_price'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid discount type.'
        );
    }

    if ($discountValue < 1) {
        throw new InvalidArgumentException(
            'Discount must be greater than zero.'
        );
    }

    if (
        $discountType === 'percent'
        && $discountValue > 100
    ) {
        throw new InvalidArgumentException(
            'Percentage discount cannot exceed 100%.'
        );
    }

    if (
        !in_array(
            $planScope,
            ['all', 'monthly', 'annual'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid membership plan.'
        );
    }


    if (
        !in_array(
            $discountDuration,
            ['once', 'months'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid promotion duration.'
        );
    }

    if ($discountDuration === 'months') {
        if ($planScope !== 'monthly') {
            throw new InvalidArgumentException(
                'Multi-month discounts are available only for the Monthly membership.'
            );
        }

        if (
            $durationMonths === null
            || !in_array(
                $durationMonths,
                llama_membership_promotion_code_month_options(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Choose 1, 2, 3, 6, 9, or 12 months.'
            );
        }

    } else {
        $durationMonths = null;
    }

    if (
        $discountType === 'promotional_price'
        && $planScope !== 'monthly'
    ) {
        throw new InvalidArgumentException(
            'Promotional monthly pricing is available only for the Monthly membership.'
        );
    }


    $startsTimestamp =
        llama_promotion_code_utc_timestamp($startsAt);

    $endsTimestamp =
        llama_promotion_code_utc_timestamp($endsAt);

    if (
        $startsTimestamp === null
        || $endsTimestamp === null
        || $endsTimestamp <= $startsTimestamp
    ) {
        throw new InvalidArgumentException(
            'Promotion code dates are invalid.'
        );
    }


    $duplicateSql =
        'SELECT id
         FROM membership_promotion_codes
         WHERE UPPER(code) = ?';

    $duplicateParams = [$code];

    if (
        $excludeId !== null
        && $excludeId > 0
    ) {
        $duplicateSql .=
            ' AND id <> ?';

        $duplicateParams[] =
            $excludeId;
    }

    $duplicateSql .=
        ' LIMIT 1';

    $duplicate = $db->prepare(
        $duplicateSql
    );

    $duplicate->execute(
        $duplicateParams
    );

    if ($duplicate->fetchColumn()) {
        throw new InvalidArgumentException(
            'That promotion code already exists.'
        );
    }


    llama_stripe_backfill_membership_product_ids(
        $db
    );

    $plans =
        llama_membership_plans(
            $db,
            true
        );

    $selectedPlans = [];
    $productIds = [];
    $currencies = [];

    foreach ($plans as $plan) {
        $interval = strtolower(
            trim((string) ($plan['interval_slug'] ?? ''))
        );

        if (
            $planScope !== 'all'
            && $interval !== $planScope
        ) {
            continue;
        }

        $productId = trim(
            (string) ($plan['stripe_product_id'] ?? '')
        );

        if ($productId === '') {
            throw new RuntimeException(
                'The selected membership plan is not connected to a Stripe Product.'
            );
        }

        $selectedPlans[] = $plan;
        $productIds[] = $productId;

        $currencies[] = strtolower(
            (string) ($plan['currency'] ?? 'usd')
        );
    }

    $productIds =
        array_values(
            array_unique($productIds)
        );

    $currencies =
        array_values(
            array_unique($currencies)
        );

    if (!$productIds) {
        throw new RuntimeException(
            'No active Stripe membership product is available.'
        );
    }

    if (
        in_array(
            $discountType,
            ['amount', 'promotional_price'],
            true
        )
        && count($currencies) !== 1
    ) {
        throw new RuntimeException(
            'Dollar-based codes require the selected plans to use one currency.'
        );
    }


    /*
     * Admin can enter a target Monthly price. Stripe stores the
     * equivalent amount-off coupon, which is also what the local
     * record stores.
     */
    if ($discountType === 'promotional_price') {
        $monthlyPlan = null;

        foreach ($selectedPlans as $selectedPlan) {
            if (
                strtolower(
                    (string) (
                        $selectedPlan['interval_slug']
                        ?? ''
                    )
                ) === 'monthly'
            ) {
                $monthlyPlan = $selectedPlan;
                break;
            }
        }

        if (!$monthlyPlan) {
            throw new RuntimeException(
                'The Monthly membership plan could not be found.'
            );
        }

        $regularMonthlyPrice =
            (int) ($monthlyPlan['base_price_cents'] ?? 0);

        $promotionalMonthlyPrice =
            $discountValue;

        if ($regularMonthlyPrice < 1) {
            throw new RuntimeException(
                'The Monthly membership price is not configured.'
            );
        }

        if (
            $promotionalMonthlyPrice >=
            $regularMonthlyPrice
        ) {
            throw new InvalidArgumentException(
                'Promotional price must be lower than the regular Monthly price.'
            );
        }

        $discountType = 'amount';

        $discountValue =
            $regularMonthlyPrice
            - $promotionalMonthlyPrice;
    }


    return [
        'internal_name' => $name,
        'code' => $code,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_duration' => $discountDuration,
        'duration_months' => $durationMonths,
        'plan_scope' => $planScope,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'starts_timestamp' => $startsTimestamp,
        'ends_timestamp' => $endsTimestamp,
        'first_time_customers_only' => $firstTimeOnly,
        'max_redemptions' => $maxRedemptions,
        'product_ids' => $productIds,
        'currencies' => $currencies,
    ];
}


/* =========================================================
   CREATE STRIPE OBJECTS
   ========================================================= */

function llama_create_membership_promotion_code_stripe_objects(
    array $config,
    bool $enabled
): array {
    $stripe =
        llama_stripe_client();

    $couponData = [
        'name' =>
            mb_substr(
                (string) $config['internal_name'],
                0,
                40
            ),

        'applies_to' => [
            'products' =>
                $config['product_ids'],
        ],

        'metadata' => [
            'llama_promotion_code' =>
                (string) $config['code'],

            'llama_plan_scope' =>
                (string) $config['plan_scope'],

            'llama_discount_duration' =>
                (string) $config['discount_duration'],

            'llama_duration_months' =>
                $config['duration_months'] !== null
                    ? (string) $config['duration_months']
                    : '',
        ],
    ];


    if (
        $config['discount_duration'] === 'months'
        && $config['duration_months'] !== null
        && (int) $config['duration_months'] > 1
    ) {
        $couponData['duration'] =
            'repeating';

        $couponData['duration_in_months'] =
            (int) $config['duration_months'];

    } else {
        $couponData['duration'] =
            'once';
    }


    if (
        $config['discount_type'] === 'percent'
    ) {
        $couponData['percent_off'] =
            (int) $config['discount_value'];

    } else {
        $couponData['amount_off'] =
            (int) $config['discount_value'];

        $couponData['currency'] =
            $config['currencies'][0]
            ?? 'usd';
    }


    $coupon =
        $stripe
            ->coupons
            ->create($couponData);

    $couponId =
        trim((string) ($coupon->id ?? ''));

    if ($couponId === '') {
        throw new RuntimeException(
            'Stripe did not return a Coupon ID.'
        );
    }


    $now = time();

    $shouldBeActive =
        $enabled
        && $now >= (int) $config['starts_timestamp']
        && $now < (int) $config['ends_timestamp'];

    $promotionData = [
        'promotion' => [
            'type' => 'coupon',
            'coupon' => $couponId,
        ],

        'active' =>
            $shouldBeActive,

        'code' =>
            (string) $config['code'],

        'expires_at' =>
            (int) $config['ends_timestamp'],

        'metadata' => [
            'llama_internal_name' =>
                (string) $config['internal_name'],

            'llama_plan_scope' =>
                (string) $config['plan_scope'],

            'llama_discount_duration' =>
                (string) $config['discount_duration'],

            'llama_duration_months' =>
                $config['duration_months'] !== null
                    ? (string) $config['duration_months']
                    : '',
        ],

        'restrictions' => [
            'first_time_transaction' =>
                !empty(
                    $config['first_time_customers_only']
                ),
        ],
    ];

    if (
        $config['max_redemptions'] !== null
    ) {
        $promotionData['max_redemptions'] =
            (int) $config['max_redemptions'];
    }


    $promotionCode =
        $stripe
            ->promotionCodes
            ->create($promotionData);

    $promotionCodeId =
        trim(
            (string) (
                $promotionCode->id
                ?? ''
            )
        );

    if ($promotionCodeId === '') {
        throw new RuntimeException(
            'Stripe did not return a Promotion Code ID.'
        );
    }


    return [
        'coupon_id' =>
            $couponId,

        'promotion_code_id' =>
            $promotionCodeId,

        'stripe_active' =>
            $shouldBeActive,
    ];
}


/* =========================================================
   CREATE PROMOTION CODE
   ========================================================= */

function llama_create_membership_promotion_code(
    PDO $db,
    array $input,
    ?int $createdBy = null
): int {
    $config =
        llama_prepare_membership_promotion_code(
            $db,
            $input
        );

    $stripeObjects =
        llama_create_membership_promotion_code_stripe_objects(
            $config,
            true
        );

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_codes
         (
            internal_name,
            code,
            discount_type,
            discount_value,
            discount_duration,
            duration_months,
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
         VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?
         )'
    );

    $stmt->execute([
        $config['internal_name'],
        $config['code'],
        $config['discount_type'],
        $config['discount_value'],
        $config['discount_duration'],
        $config['duration_months'],
        $config['plan_scope'],
        $config['starts_at'],
        $config['ends_at'],
        $config['first_time_customers_only'] ? 1 : 0,
        $config['max_redemptions'],
        $stripeObjects['coupon_id'],
        $stripeObjects['promotion_code_id'],
        $stripeObjects['stripe_active'] ? 1 : 0,
        $createdBy && $createdBy > 0
            ? $createdBy
            : null,
    ]);

    return (int) $db->lastInsertId();
}


/* =========================================================
   EDIT PROMOTION CODE
   ========================================================= */

function llama_update_membership_promotion_code(
    PDO $db,
    int $id,
    array $input
): void {
    if ($id < 1) {
        throw new InvalidArgumentException(
            'Promotion code not found.'
        );
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM membership_promotion_codes
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$id]);

    $existing =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        throw new InvalidArgumentException(
            'Promotion code not found.'
        );
    }


    $config =
        llama_prepare_membership_promotion_code(
            $db,
            $input,
            $id
        );

    $enabled =
        !empty($existing['is_enabled']);

    $oldPromotionCodeId =
        trim(
            (string) (
                $existing['stripe_promotion_code_id']
                ?? ''
            )
        );

    $oldWasActive =
        !empty($existing['stripe_active']);

    $stripe =
        llama_stripe_client();


    /*
     * Stripe intentionally makes most coupon and promotion-code
     * fields immutable. Editing therefore creates replacement
     * Stripe objects while keeping the same local record and stats.
     *
     * The old Promotion Code is disabled first so the same
     * customer-facing code can be reused.
     */
    if ($oldPromotionCodeId !== '') {
        $stripe
            ->promotionCodes
            ->update(
                $oldPromotionCodeId,
                [
                    'active' => false,
                ]
            );
    }


    $newObjects = null;

    try {
        $newObjects =
            llama_create_membership_promotion_code_stripe_objects(
                $config,
                $enabled
            );

    } catch (Throwable $exception) {
        if (
            $oldPromotionCodeId !== ''
            && $oldWasActive
        ) {
            try {
                $stripe
                    ->promotionCodes
                    ->update(
                        $oldPromotionCodeId,
                        [
                            'active' => true,
                        ]
                    );
            } catch (Throwable $restoreException) {
                error_log(
                    'Llama Scout could not reactivate the original promotion code after an edit failure: '
                    . $restoreException->getMessage()
                );
            }
        }

        throw $exception;
    }


    try {
        $update = $db->prepare(
            'UPDATE membership_promotion_codes
             SET
                internal_name = ?,
                code = ?,
                discount_type = ?,
                discount_value = ?,
                discount_duration = ?,
                duration_months = ?,
                plan_scope = ?,
                starts_at = ?,
                ends_at = ?,
                first_time_customers_only = ?,
                max_redemptions = ?,
                stripe_coupon_id = ?,
                stripe_promotion_code_id = ?,
                stripe_active = ?
             WHERE id = ?'
        );

        $update->execute([
            $config['internal_name'],
            $config['code'],
            $config['discount_type'],
            $config['discount_value'],
            $config['discount_duration'],
            $config['duration_months'],
            $config['plan_scope'],
            $config['starts_at'],
            $config['ends_at'],
            $config['first_time_customers_only'] ? 1 : 0,
            $config['max_redemptions'],
            $newObjects['coupon_id'],
            $newObjects['promotion_code_id'],
            $newObjects['stripe_active'] ? 1 : 0,
            $id,
        ]);

    } catch (Throwable $exception) {
        try {
            $stripe
                ->promotionCodes
                ->update(
                    (string) $newObjects['promotion_code_id'],
                    [
                        'active' => false,
                    ]
                );
        } catch (Throwable) {
        }

        if (
            $oldPromotionCodeId !== ''
            && $oldWasActive
        ) {
            try {
                $stripe
                    ->promotionCodes
                    ->update(
                        $oldPromotionCodeId,
                        [
                            'active' => true,
                        ]
                    );
            } catch (Throwable $restoreException) {
                error_log(
                    'Llama Scout could not reactivate the original promotion code after a database edit failure: '
                    . $restoreException->getMessage()
                );
            }
        }

        throw $exception;
    }
}


/* =========================================================
   ENABLE / DISABLE PROMOTION CODE
   ========================================================= */

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

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new InvalidArgumentException(
            'Promotion code not found.'
        );
    }

    $startsTimestamp =
        llama_promotion_code_utc_timestamp(
            (string) ($row['starts_at'] ?? '')
        );

    $endsTimestamp =
        llama_promotion_code_utc_timestamp(
            (string) ($row['ends_at'] ?? '')
        );

    $now = time();

    $desiredStripeActive =
        $enabled
        && $startsTimestamp !== null
        && $endsTimestamp !== null
        && $now >= $startsTimestamp
        && $now < $endsTimestamp;

    $stripeId = trim(
        (string) (
            $row['stripe_promotion_code_id']
            ?? ''
        )
    );

    if ($stripeId !== '') {
        llama_stripe_client()
            ->promotionCodes
            ->update(
                $stripeId,
                [
                    'active' =>
                        $desiredStripeActive,
                ]
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
    $stripePromotionCodeId =
        trim($stripePromotionCodeId);

    $checkoutSessionId =
        trim($checkoutSessionId);

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

    $lookup->execute([
        $stripePromotionCodeId,
    ]);

    $promotionCodeId =
        (int) $lookup->fetchColumn();

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
            stripe_subscription_id =
                VALUES(stripe_subscription_id),
            amount_cents =
                VALUES(amount_cents)'
    );

    $stmt->execute([
        $promotionCodeId,
        $userId,
        $membershipInterval !== ''
            ? $membershipInterval
            : null,
        $checkoutSessionId,
        $subscriptionId !== ''
            ? $subscriptionId
            : null,
        $amountCents !== null
            ? max(0, $amountCents)
            : null,
    ]);
}


/* =========================================================
   PROMOTION CODE STATS
   ========================================================= */

function llama_membership_promotion_code_stats(
    PDO $db
): array {
    $stats = [];

    $table = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'membership_promotion_code_events'"
    );

    if (
        !$table
        || (int) $table->fetchColumn() < 1
    ) {
        return $stats;
    }

    $rows = $db->query(
        'SELECT
            promotion_code_id,
            COUNT(*) AS redemptions,
            COALESCE(
                SUM(amount_cents),
                0
            ) AS revenue_cents
         FROM membership_promotion_code_events
         GROUP BY promotion_code_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $stats[(int) $row['promotion_code_id']] = [
            'redemptions' =>
                (int) ($row['redemptions'] ?? 0),
            'revenue_cents' =>
                (int) ($row['revenue_cents'] ?? 0),
        ];
    }

    return $stats;
}


/* =========================================================
   ACTIVE CUSTOMER PROMOTION CODE
   ========================================================= */

function llama_membership_promotion_code_by_code(
    PDO $db,
    string $code,
    ?string $membershipInterval = null
): ?array {
    $code = strtoupper(
        trim($code)
    );

    if ($code === '') {
        return null;
    }

    $membershipInterval = strtolower(
        trim((string) $membershipInterval)
    );

    if (
        $membershipInterval !== ''
        && !in_array(
            $membershipInterval,
            ['monthly', 'annual'],
            true
        )
    ) {
        return null;
    }

    $sql =
        'SELECT *
         FROM membership_promotion_codes
         WHERE UPPER(code) = ?
           AND is_enabled = 1
           AND stripe_active = 1
           AND starts_at <= UTC_TIMESTAMP()
           AND ends_at > UTC_TIMESTAMP()
           AND stripe_promotion_code_id IS NOT NULL
           AND stripe_promotion_code_id <> ""';

    $params = [$code];

    if ($membershipInterval !== '') {
        $sql .=
            ' AND (
                plan_scope = "all"
                OR plan_scope = ?
              )';

        $params[] =
            $membershipInterval;
    }

    $sql .=
        ' LIMIT 1';

    $stmt =
        $db->prepare($sql);

    $stmt->execute($params);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

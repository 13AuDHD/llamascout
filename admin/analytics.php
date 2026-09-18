<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/registration-source.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();

/*
 * Analytics contains account-growth and financial information.
 * Admin and Moderator access is intentionally not enough.
 */
require_role('owner');

$db = db();


/* =========================================================
   HELPERS
   ========================================================= */

function admin_analytics_table_exists(
    PDO $db,
    string $table
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}


function admin_analytics_column_exists(
    PDO $db,
    string $table,
    string $column
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        $table,
        $column,
    ]);

    return (bool) $stmt->fetchColumn();
}


function admin_analytics_money(
    int $cents
): string {
    $negative = $cents < 0;
    $cents = abs($cents);

    return ($negative ? '-$' : '$')
        . number_format(
            $cents / 100,
            2
        );
}


function admin_analytics_percent(
    float $value
): string {
    return number_format(
        $value,
        1
    ) . '%';
}


function admin_analytics_count_since(
    PDO $db,
    DateTimeImmutable $startUtc
): int {
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM users
         WHERE status <> "deleted"
           AND created_at >= ?'
    );

    $stmt->execute([
        $startUtc->format('Y-m-d H:i:s'),
    ]);

    return (int) $stmt->fetchColumn();
}


function admin_analytics_range(
    string $key,
    DateTimeImmutable $nowUtc,
    DateTimeImmutable $quarterStartUtc,
    DateTimeImmutable $yearStartUtc
): array {
    return match ($key) {
        '24h' => [
            'key' => '24h',
            'label' => '24H',
            'start' => $nowUtc->modify('-24 hours'),
            'bucket' => 'hour',
        ],
        '7d' => [
            'key' => '7d',
            'label' => '7D',
            'start' => $nowUtc->modify('-7 days'),
            'bucket' => 'day',
        ],
        'quarter' => [
            'key' => 'quarter',
            'label' => 'QTD',
            'start' => $quarterStartUtc,
            'bucket' => 'day',
        ],
        'year' => [
            'key' => 'year',
            'label' => 'YTD',
            'start' => $yearStartUtc,
            'bucket' => 'month',
        ],
        default => [
            'key' => '30d',
            'label' => '30D',
            'start' => $nowUtc->modify('-30 days'),
            'bucket' => 'day',
        ],
    };
}


function admin_analytics_bucket_key(
    int $timestamp,
    string $bucket
): string {
    return match ($bucket) {
        'hour' => gmdate('Y-m-d H', $timestamp),
        'month' => gmdate('Y-m', $timestamp),
        default => gmdate('Y-m-d', $timestamp),
    };
}


function admin_analytics_empty_buckets(
    DateTimeImmutable $startUtc,
    DateTimeImmutable $endUtc,
    string $bucket
): array {
    $values = [];

    if ($bucket === 'hour') {
        $cursor = $startUtc->setTime(
            (int) $startUtc->format('H'),
            0,
            0
        );

        while ($cursor <= $endUtc) {
            $values[
                $cursor->format('Y-m-d H')
            ] = 0;

            $cursor =
                $cursor->modify('+1 hour');
        }

        return $values;
    }

    if ($bucket === 'month') {
        $cursor = $startUtc
            ->modify('first day of this month')
            ->setTime(0, 0, 0);

        while ($cursor <= $endUtc) {
            $values[
                $cursor->format('Y-m')
            ] = 0;

            $cursor =
                $cursor->modify(
                    'first day of next month'
                );
        }

        return $values;
    }

    $cursor = $startUtc->setTime(
        0,
        0,
        0
    );

    while ($cursor <= $endUtc) {
        $values[
            $cursor->format('Y-m-d')
        ] = 0;

        $cursor =
            $cursor->modify('+1 day');
    }

    return $values;
}


function admin_analytics_bar_chart(
    array $values,
    string $ariaLabel
): string {
    $values = array_values(
        array_map(
            static fn ($value): int =>
                max(0, (int) $value),
            $values
        )
    );

    if (!$values) {
        $values = [0];
    }

    $maxValue =
        max(1, max($values));

    $count =
        max(1, count($values));

    $canvasWidth = 1000.0;
    $canvasHeight = 120.0;
    $gap =
        $count > 60
            ? 2.0
            : (
                $count > 30
                    ? 4.0
                    : 8.0
            );

    $barWidth =
        max(
            1.5,
            (
                $canvasWidth
                - ($gap * ($count - 1))
            ) / $count
        );

    $parts = [
        '<svg class="admin-analytics-bars" '
        . 'viewBox="0 0 1000 120" '
        . 'preserveAspectRatio="none" '
        . 'role="img" aria-label="'
        . htmlspecialchars(
            $ariaLabel,
            ENT_QUOTES,
            'UTF-8'
        )
        . '">',
    ];

    foreach ($values as $index => $value) {
        $height =
            $value > 0
                ? max(
                    3.0,
                    (
                        $value
                        / $maxValue
                    ) * $canvasHeight
                )
                : 1.0;

        $x =
            $index
            * ($barWidth + $gap);

        $y =
            $canvasHeight
            - $height;

        $parts[] =
            '<rect x="'
            . number_format(
                $x,
                2,
                '.',
                ''
            )
            . '" y="'
            . number_format(
                $y,
                2,
                '.',
                ''
            )
            . '" width="'
            . number_format(
                $barWidth,
                2,
                '.',
                ''
            )
            . '" height="'
            . number_format(
                $height,
                2,
                '.',
                ''
            )
            . '" rx="2"></rect>';
    }

    $parts[] = '</svg>';

    return implode('', $parts);
}


function admin_analytics_invoice_is_membership(
    object $invoice,
    array $subscriptionIds,
    array $priceIds
): bool {
    $subscription =
        $invoice->subscription
        ?? $invoice
            ->parent
            ->subscription_details
            ->subscription
        ?? null;

    $subscriptionId = '';

    if (is_string($subscription)) {
        $subscriptionId =
            trim($subscription);
    } elseif (is_object($subscription)) {
        $subscriptionId =
            trim(
                (string) (
                    $subscription->id
                    ?? ''
                )
            );
    }

    if (
        $subscriptionId !== ''
        && isset(
            $subscriptionIds[
                $subscriptionId
            ]
        )
    ) {
        return true;
    }

    foreach (
        (array) (
            $invoice->lines->data
            ?? []
        )
        as $line
    ) {
        if (!is_object($line)) {
            continue;
        }

        $linePriceId = '';

        $price =
            $line->price
            ?? null;

        if (is_string($price)) {
            $linePriceId =
                trim($price);
        } elseif (is_object($price)) {
            $linePriceId =
                trim(
                    (string) (
                        $price->id
                        ?? ''
                    )
                );
        }

        if ($linePriceId === '') {
            $pricingPrice =
                $line
                    ->pricing
                    ->price_details
                    ->price
                ?? null;

            if (is_string($pricingPrice)) {
                $linePriceId =
                    trim($pricingPrice);
            } elseif (
                is_object($pricingPrice)
            ) {
                $linePriceId =
                    trim(
                        (string) (
                            $pricingPrice->id
                            ?? ''
                        )
                    );
            }
        }

        if (
            $linePriceId !== ''
            && isset(
                $priceIds[
                    $linePriceId
                ]
            )
        ) {
            return true;
        }
    }

    return false;
}


function admin_analytics_source_label(
    string $key
): string {
    if ($key === 'unknown') {
        return 'Not answered';
    }

    if (
        function_exists(
            'llama_registration_source_label'
        )
    ) {
        return
            llama_registration_source_label(
                $key
            );
    }

    return ucwords(
        str_replace(
            [
                '_',
                '-',
            ],
            ' ',
            $key
        )
    );
}


/* =========================================================
   RANGE
   ========================================================= */

$utc =
    new DateTimeZone('UTC');

$viewerTimezone =
    new DateTimeZone(
        llama_viewer_timezone()
    );

$nowUtc =
    new DateTimeImmutable(
        'now',
        $utc
    );

$nowLocal =
    $nowUtc->setTimezone(
        $viewerTimezone
    );

$quarterMonth =
    (
        intdiv(
            ((int) $nowLocal->format('n')) - 1,
            3
        ) * 3
    ) + 1;

$quarterStartLocal =
    $nowLocal
        ->setDate(
            (int) $nowLocal->format('Y'),
            $quarterMonth,
            1
        )
        ->setTime(
            0,
            0,
            0
        );

$quarterStartUtc =
    $quarterStartLocal
        ->setTimezone($utc);

$yearStartLocal =
    $nowLocal
        ->setDate(
            (int) $nowLocal->format('Y'),
            1,
            1
        )
        ->setTime(
            0,
            0,
            0
        );

$yearStartUtc =
    $yearStartLocal
        ->setTimezone($utc);

$rangeKey =
    strtolower(
        trim(
            (string) (
                $_GET['range']
                ?? '30d'
            )
        )
    );

$allowedRanges = [
    '24h',
    '7d',
    '30d',
    'quarter',
    'year',
];

if (
    !in_array(
        $rangeKey,
        $allowedRanges,
        true
    )
) {
    $rangeKey = '30d';
}

$range =
    admin_analytics_range(
        $rangeKey,
        $nowUtc,
        $quarterStartUtc,
        $yearStartUtc
    );

$rangeStartUtc =
    $range['start'];

$rangeStartSql =
    $rangeStartUtc->format(
        'Y-m-d H:i:s'
    );

$rangeEndSql =
    $nowUtc->format(
        'Y-m-d H:i:s'
    );

$bucketType =
    (string) $range['bucket'];


/* =========================================================
   NAVIGATION COUNTS
   ========================================================= */

$stats =
    admin_dashboard_stats(
        $db
    );

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
    'Analytics';

$adminPageEyebrow =
    'Owner';

$adminActiveNav =
    'analytics';


/* =========================================================
   ACCOUNT GROWTH
   ========================================================= */

$accountGrowth = [
    '24h' => 0,
    '7d' => 0,
    '30d' => 0,
    'quarter' => 0,
];

$totalAccounts = 0;
$activePaid = 0;
$monthlyPaid = 0;
$annualPaid = 0;
$complimentary = 0;
$selectedNewAccounts = 0;
$selectedPaidStarts = 0;
$paidShare = 0.0;
$estimatedMrrCents = 0;

$analyticsWarnings = [];

try {
    $accountGrowth['24h'] =
        admin_analytics_count_since(
            $db,
            $nowUtc->modify(
                '-24 hours'
            )
        );

    $accountGrowth['7d'] =
        admin_analytics_count_since(
            $db,
            $nowUtc->modify(
                '-7 days'
            )
        );

    $accountGrowth['30d'] =
        admin_analytics_count_since(
            $db,
            $nowUtc->modify(
                '-30 days'
            )
        );

    $accountGrowth['quarter'] =
        admin_analytics_count_since(
            $db,
            $quarterStartUtc
        );

    $accountSummary =
        $db->query(
            'SELECT
                COUNT(*) AS total_accounts,
                SUM(
                    membership_status IN (
                        "active",
                        "trialing"
                    )
                ) AS active_paid,
                SUM(
                    membership_status IN (
                        "active",
                        "trialing"
                    )
                    AND membership_interval = "monthly"
                ) AS monthly_paid,
                SUM(
                    membership_status IN (
                        "active",
                        "trialing"
                    )
                    AND membership_interval = "annual"
                ) AS annual_paid,
                SUM(
                    membership_status = "complimentary"
                ) AS complimentary
             FROM users
             WHERE status <> "deleted"'
        )
        ->fetch(
            PDO::FETCH_ASSOC
        )
        ?: [];

    $totalAccounts =
        (int) (
            $accountSummary[
                'total_accounts'
            ]
            ?? 0
        );

    $activePaid =
        (int) (
            $accountSummary[
                'active_paid'
            ]
            ?? 0
        );

    $monthlyPaid =
        (int) (
            $accountSummary[
                'monthly_paid'
            ]
            ?? 0
        );

    $annualPaid =
        (int) (
            $accountSummary[
                'annual_paid'
            ]
            ?? 0
        );

    $complimentary =
        (int) (
            $accountSummary[
                'complimentary'
            ]
            ?? 0
        );

    $paidShare =
        $totalAccounts > 0
            ? (
                $activePaid
                / $totalAccounts
            ) * 100
            : 0.0;

    $selectedAccountStmt =
        $db->prepare(
            'SELECT COUNT(*)
             FROM users
             WHERE status <> "deleted"
               AND created_at >= ?
               AND created_at < ?'
        );

    $selectedAccountStmt->execute([
        $rangeStartSql,
        $rangeEndSql,
    ]);

    $selectedNewAccounts =
        (int)
        $selectedAccountStmt
            ->fetchColumn();

    if (
        admin_analytics_column_exists(
            $db,
            'users',
            'membership_started_at'
        )
    ) {
        $paidStartStmt =
            $db->prepare(
                'SELECT COUNT(*)
                 FROM users
                 WHERE status <> "deleted"
                   AND membership_started_at >= ?
                   AND membership_started_at < ?'
            );

        $paidStartStmt->execute([
            $rangeStartSql,
            $rangeEndSql,
        ]);

        $selectedPaidStarts =
            (int)
            $paidStartStmt
                ->fetchColumn();
    }

    if (
        admin_analytics_table_exists(
            $db,
            'membership_plans'
        )
    ) {
        $planRows =
            $db->query(
                'SELECT
                    interval_slug,
                    base_price_cents
                 FROM membership_plans
                 WHERE is_active = 1'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $monthlyPrice = 0;
        $annualPrice = 0;

        foreach ($planRows as $plan) {
            $interval =
                strtolower(
                    trim(
                        (string) (
                            $plan[
                                'interval_slug'
                            ]
                            ?? ''
                        )
                    )
                );

            if ($interval === 'monthly') {
                $monthlyPrice =
                    max(
                        0,
                        (int) (
                            $plan[
                                'base_price_cents'
                            ]
                            ?? 0
                        )
                    );
            }

            if ($interval === 'annual') {
                $annualPrice =
                    max(
                        0,
                        (int) (
                            $plan[
                                'base_price_cents'
                            ]
                            ?? 0
                        )
                    );
            }
        }

        $estimatedMrrCents =
            (
                $monthlyPaid
                * $monthlyPrice
            )
            + (int) round(
                (
                    $annualPaid
                    * $annualPrice
                ) / 12
            );
    }
} catch (Throwable $exception) {
    $analyticsWarnings[] =
        'Account metrics are partially unavailable.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_accounts'
    );
}


/* =========================================================
   ACCOUNT TREND
   ========================================================= */

$accountBuckets =
    admin_analytics_empty_buckets(
        $rangeStartUtc,
        $nowUtc,
        $bucketType
    );

try {
    $bucketExpression =
        match ($bucketType) {
            'hour' =>
                'DATE_FORMAT(created_at, "%Y-%m-%d %H")',
            'month' =>
                'DATE_FORMAT(created_at, "%Y-%m")',
            default =>
                'DATE_FORMAT(created_at, "%Y-%m-%d")',
        };

    $stmt = $db->prepare(
        'SELECT
            ' . $bucketExpression . ' AS bucket_key,
            COUNT(*) AS account_count
         FROM users
         WHERE status <> "deleted"
           AND created_at >= ?
           AND created_at < ?
         GROUP BY bucket_key
         ORDER BY bucket_key ASC'
    );

    $stmt->execute([
        $rangeStartSql,
        $rangeEndSql,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $key =
            (string) (
                $row[
                    'bucket_key'
                ]
                ?? ''
            );

        if (
            $key !== ''
            && array_key_exists(
                $key,
                $accountBuckets
            )
        ) {
            $accountBuckets[$key] =
                max(
                    0,
                    (int) (
                        $row[
                            'account_count'
                        ]
                        ?? 0
                    )
                );
        }
    }
} catch (Throwable $exception) {
    $analyticsWarnings[] =
        'Account trend data is unavailable.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_account_trend'
    );
}


/* =========================================================
   ACQUISITION SOURCES
   ========================================================= */

$acquisitionRows = [];
$acquisitionTotal = 0;

try {
    $stmt = $db->prepare(
        'SELECT
            COALESCE(
                NULLIF(
                    TRIM(registration_source),
                    ""
                ),
                "unknown"
            ) AS source_key,
            COUNT(*) AS source_count
         FROM users
         WHERE status <> "deleted"
           AND created_at >= ?
           AND created_at < ?
         GROUP BY source_key
         ORDER BY source_count DESC, source_key ASC'
    );

    $stmt->execute([
        $rangeStartSql,
        $rangeEndSql,
    ]);

    $acquisitionRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    foreach ($acquisitionRows as $row) {
        $acquisitionTotal +=
            max(
                0,
                (int) (
                    $row[
                        'source_count'
                    ]
                    ?? 0
                )
            );
    }
} catch (Throwable $exception) {
    $analyticsWarnings[] =
        'Acquisition-source data is unavailable.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_sources'
    );
}


/* =========================================================
   SHOP REVENUE
   ========================================================= */

$shopGrossCents = 0;
$shopRefundCents = 0;
$shopRevenueCents = 0;
$shopOrderCount = 0;
$shopUnits = 0;
$shopAverageOrderCents = 0;

$revenueBuckets =
    admin_analytics_empty_buckets(
        $rangeStartUtc,
        $nowUtc,
        $bucketType
    );

$shopAvailable =
    admin_analytics_table_exists(
        $db,
        'shop_orders'
    );

if ($shopAvailable) {
    try {
        $shopStmt = $db->prepare(
            'SELECT
                id,
                total_cents,
                COALESCE(
                    paid_at,
                    created_at
                ) AS paid_at_effective
             FROM shop_orders
             WHERE payment_status IN (
                    "paid",
                    "refunded",
                    "partially_refunded"
             )
               AND COALESCE(
                    paid_at,
                    created_at
               ) >= ?
               AND COALESCE(
                    paid_at,
                    created_at
               ) < ?
             ORDER BY paid_at_effective ASC'
        );

        $shopStmt->execute([
            $rangeStartSql,
            $rangeEndSql,
        ]);

        $shopOrders =
            $shopStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        foreach ($shopOrders as $order) {
            $amount =
                max(
                    0,
                    (int) (
                        $order[
                            'total_cents'
                        ]
                        ?? 0
                    )
                );

            $shopGrossCents +=
                $amount;

            $shopOrderCount++;

            $paidAt =
                trim(
                    (string) (
                        $order[
                            'paid_at_effective'
                        ]
                        ?? ''
                    )
                );

            $timestamp =
                $paidAt !== ''
                    ? strtotime(
                        $paidAt . ' UTC'
                    )
                    : false;

            if ($timestamp !== false) {
                $bucketKey =
                    admin_analytics_bucket_key(
                        $timestamp,
                        $bucketType
                    );

                if (
                    array_key_exists(
                        $bucketKey,
                        $revenueBuckets
                    )
                ) {
                    $revenueBuckets[
                        $bucketKey
                    ] += $amount;
                }
            }
        }

        if (
            admin_analytics_table_exists(
                $db,
                'shop_refunds'
            )
        ) {
            $refundStmt =
                $db->prepare(
                    'SELECT
                        amount_cents,
                        requested_at
                     FROM shop_refunds
                     WHERE status = "succeeded"
                       AND requested_at >= ?
                       AND requested_at < ?
                     ORDER BY requested_at ASC'
                );

            $refundStmt->execute([
                $rangeStartSql,
                $rangeEndSql,
            ]);

            foreach (
                $refundStmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
                as $refund
            ) {
                $amount =
                    max(
                        0,
                        (int) (
                            $refund[
                                'amount_cents'
                            ]
                            ?? 0
                        )
                    );

                $shopRefundCents +=
                    $amount;

                $requestedAt =
                    trim(
                        (string) (
                            $refund[
                                'requested_at'
                            ]
                            ?? ''
                        )
                    );

                $timestamp =
                    $requestedAt !== ''
                        ? strtotime(
                            $requestedAt
                            . ' UTC'
                        )
                        : false;

                if ($timestamp !== false) {
                    $bucketKey =
                        admin_analytics_bucket_key(
                            $timestamp,
                            $bucketType
                        );

                    if (
                        array_key_exists(
                            $bucketKey,
                            $revenueBuckets
                        )
                    ) {
                        $revenueBuckets[
                            $bucketKey
                        ] -= $amount;
                    }
                }
            }
        }

        $shopRevenueCents =
            max(
                0,
                $shopGrossCents
                - $shopRefundCents
            );

        $shopAverageOrderCents =
            $shopOrderCount > 0
                ? (int) round(
                    $shopGrossCents
                    / $shopOrderCount
                )
                : 0;

        if (
            admin_analytics_table_exists(
                $db,
                'shop_order_items'
            )
        ) {
            $unitsStmt =
                $db->prepare(
                    'SELECT
                        COALESCE(
                            SUM(oi.quantity),
                            0
                        )
                     FROM shop_order_items oi
                     INNER JOIN shop_orders o
                        ON o.id = oi.order_id
                     WHERE o.payment_status IN (
                            "paid",
                            "refunded",
                            "partially_refunded"
                     )
                       AND COALESCE(
                            o.paid_at,
                            o.created_at
                       ) >= ?
                       AND COALESCE(
                            o.paid_at,
                            o.created_at
                       ) < ?'
                );

            $unitsStmt->execute([
                $rangeStartSql,
                $rangeEndSql,
            ]);

            $shopUnits =
                max(
                    0,
                    (int)
                    $unitsStmt
                        ->fetchColumn()
                );
        }
    } catch (Throwable $exception) {
        $shopAvailable = false;

        $analyticsWarnings[] =
            'Shop revenue is partially unavailable.';

        llama_log_caught_exception(
            $exception,
            'admin.analytics_shop_revenue'
        );
    }
}


/* =========================================================
   MEMBERSHIP REVENUE FROM STRIPE PAID INVOICES
   ========================================================= */

$membershipRevenueCents = 0;
$membershipPaymentCount = 0;
$membershipRevenueAvailable = true;

try {
    $knownSubscriptionIds = [];
    $knownPriceIds = [];

    if (
        admin_analytics_column_exists(
            $db,
            'users',
            'stripe_subscription_id'
        )
    ) {
        $subscriptionRows =
            $db->query(
                'SELECT stripe_subscription_id
                 FROM users
                 WHERE stripe_subscription_id IS NOT NULL
                   AND stripe_subscription_id <> ""'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: [];

        foreach ($subscriptionRows as $id) {
            $id =
                trim(
                    (string) $id
                );

            if ($id !== '') {
                $knownSubscriptionIds[$id] =
                    true;
            }
        }
    }

    if (
        admin_analytics_table_exists(
            $db,
            'membership_plan_prices'
        )
    ) {
        $priceRows =
            $db->query(
                'SELECT stripe_price_id
                 FROM membership_plan_prices
                 WHERE stripe_price_id IS NOT NULL
                   AND stripe_price_id <> ""'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: [];

        foreach ($priceRows as $id) {
            $id =
                trim(
                    (string) $id
                );

            if ($id !== '') {
                $knownPriceIds[$id] =
                    true;
            }
        }
    }

    if (
        admin_analytics_table_exists(
            $db,
            'membership_plans'
        )
    ) {
        $priceRows =
            $db->query(
                'SELECT stripe_price_id
                 FROM membership_plans
                 WHERE stripe_price_id IS NOT NULL
                   AND stripe_price_id <> ""'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: [];

        foreach ($priceRows as $id) {
            $id =
                trim(
                    (string) $id
                );

            if ($id !== '') {
                $knownPriceIds[$id] =
                    true;
            }
        }
    }

    if (
        $knownSubscriptionIds
        || $knownPriceIds
    ) {
        $invoiceCollection =
            llama_stripe_client()
                ->invoices
                ->all([
                    'status' => 'paid',
                    'created' => [
                        'gte' =>
                            $rangeStartUtc
                                ->getTimestamp(),
                        'lt' =>
                            $nowUtc
                                ->getTimestamp(),
                    ],
                    'limit' => 100,
                ]);

        $invoiceIterable =
            method_exists(
                $invoiceCollection,
                'autoPagingIterator'
            )
                ? $invoiceCollection
                    ->autoPagingIterator()
                : (
                    $invoiceCollection->data
                    ?? []
                );

        $scanned = 0;

        foreach (
            $invoiceIterable
            as $invoice
        ) {
            if (!is_object($invoice)) {
                continue;
            }

            $scanned++;

            /*
             * Defensive ceiling. It is far above current expected
             * volume but prevents an accidental unbounded admin request.
             */
            if ($scanned > 5000) {
                $analyticsWarnings[] =
                    'Membership revenue hit the 5,000-invoice display limit.';

                break;
            }

            if (
                !admin_analytics_invoice_is_membership(
                    $invoice,
                    $knownSubscriptionIds,
                    $knownPriceIds
                )
            ) {
                continue;
            }

            $amountPaid =
                max(
                    0,
                    (int) (
                        $invoice->amount_paid
                        ?? 0
                    )
                );

            if ($amountPaid < 1) {
                continue;
            }

            $membershipRevenueCents +=
                $amountPaid;

            $membershipPaymentCount++;

            $paidAt =
                $invoice
                    ->status_transitions
                    ->paid_at
                ?? $invoice->created
                ?? null;

            if (
                is_numeric($paidAt)
            ) {
                $bucketKey =
                    admin_analytics_bucket_key(
                        (int) $paidAt,
                        $bucketType
                    );

                if (
                    array_key_exists(
                        $bucketKey,
                        $revenueBuckets
                    )
                ) {
                    $revenueBuckets[
                        $bucketKey
                    ] += $amountPaid;
                }
            }
        }
    }
} catch (Throwable $exception) {
    $membershipRevenueAvailable =
        false;

    $analyticsWarnings[] =
        'Live membership revenue is unavailable from Stripe.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_membership_revenue'
    );
}

$siteRevenueAvailable =
    $membershipRevenueAvailable
    && $shopAvailable;

$siteRevenueCents =
    $membershipRevenueCents
    + $shopRevenueCents;


/* =========================================================
   PROMOTION CODES
   ========================================================= */

$promotionCodes = [];

try {
    if (
        admin_analytics_table_exists(
            $db,
            'membership_promotion_codes'
        )
    ) {
        $codeStats =
            llama_membership_promotion_code_stats(
                $db
            );

        $codeRows =
            $db->query(
                'SELECT *
                 FROM membership_promotion_codes
                 ORDER BY starts_at DESC, id DESC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        foreach ($codeRows as $code) {
            $id =
                (int) (
                    $code['id']
                    ?? 0
                );

            $results =
                $codeStats[$id]
                ?? [];

            $code['redemptions'] =
                (int) (
                    $results[
                        'redemptions'
                    ]
                    ?? 0
                );

            $code['revenue_cents'] =
                (int) (
                    $results[
                        'revenue_cents'
                    ]
                    ?? 0
                );

            $promotionCodes[] =
                $code;
        }

        usort(
            $promotionCodes,
            static function (
                array $a,
                array $b
            ): int {
                $usageCompare =
                    (
                        (int) (
                            $b[
                                'redemptions'
                            ]
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int) (
                            $a[
                                'redemptions'
                            ]
                            ?? 0
                        )
                    );

                if ($usageCompare !== 0) {
                    return $usageCompare;
                }

                return
                    (
                        (int) (
                            $b['id']
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int) (
                            $a['id']
                            ?? 0
                        )
                    );
            }
        );
    }
} catch (Throwable $exception) {
    $analyticsWarnings[] =
        'Promotion-code analytics are unavailable.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_promotion_codes'
    );
}


/* =========================================================
   CAMPAIGNS
   ========================================================= */

$campaignRows = [];

try {
    if (
        admin_analytics_table_exists(
            $db,
            'membership_promotions'
        )
        && admin_analytics_table_exists(
            $db,
            'membership_promotion_events'
        )
    ) {
        $campaignStats = [];

        $eventRows =
            $db->query(
                'SELECT
                    promotion_id,
                    SUM(
                        event_type = "checkout_started"
                    ) AS checkout_started,
                    SUM(
                        event_type = "membership_purchased"
                    ) AS membership_purchased,
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
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        foreach ($eventRows as $row) {
            $campaignStats[
                (int) (
                    $row[
                        'promotion_id'
                    ]
                    ?? 0
                )
            ] = $row;
        }

        $promotions =
            $db->query(
                'SELECT *
                 FROM membership_promotions
                 ORDER BY starts_at DESC, id DESC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        foreach ($promotions as $promotion) {
            $id =
                (int) (
                    $promotion['id']
                    ?? 0
                );

            $event =
                $campaignStats[$id]
                ?? [];

            $checkouts =
                (int) (
                    $event[
                        'checkout_started'
                    ]
                    ?? 0
                );

            $purchases =
                (int) (
                    $event[
                        'membership_purchased'
                    ]
                    ?? 0
                );

            $promotion[
                'checkout_started'
            ] = $checkouts;

            $promotion[
                'membership_purchased'
            ] = $purchases;

            $promotion[
                'revenue_cents'
            ] =
                (int) (
                    $event[
                        'revenue_cents'
                    ]
                    ?? 0
                );

            $promotion[
                'conversion_rate'
            ] =
                $checkouts > 0
                    ? (
                        $purchases
                        / $checkouts
                    ) * 100
                    : 0.0;

            $campaignRows[] =
                $promotion;
        }

        usort(
            $campaignRows,
            static function (
                array $a,
                array $b
            ): int {
                $revenueCompare =
                    (
                        (int) (
                            $b[
                                'revenue_cents'
                            ]
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int) (
                            $a[
                                'revenue_cents'
                            ]
                            ?? 0
                        )
                    );

                if ($revenueCompare !== 0) {
                    return $revenueCompare;
                }

                return
                    (
                        (int) (
                            $b['id']
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int) (
                            $a['id']
                            ?? 0
                        )
                    );
            }
        );
    }
} catch (Throwable $exception) {
    $analyticsWarnings[] =
        'Campaign analytics are unavailable.';

    llama_log_caught_exception(
        $exception,
        'admin.analytics_campaigns'
    );
}


/* =========================================================
   PAGE
   ========================================================= */

$rangeStartLabel =
    $rangeStartUtc
        ->setTimezone(
            $viewerTimezone
        )
        ->format(
            $bucketType === 'hour'
                ? 'M j, g a'
                : (
                    $bucketType === 'month'
                        ? 'M Y'
                        : 'M j'
                )
        );

$rangeEndLabel =
    $nowLocal->format(
        $bucketType === 'hour'
            ? 'M j, g a'
            : (
                $bucketType === 'month'
                    ? 'M Y'
                    : 'M j'
            )
    );

require __DIR__ . '/_header.php';
?>

<?php if ($analyticsWarnings): ?>
<div class="admin-analytics-warning">
    <?= moderation_e(
        implode(
            ' ',
            array_values(
                array_unique(
                    $analyticsWarnings
                )
            )
        )
    ) ?>
</div>
<?php endif; ?>


<nav
    class="admin-analytics-range"
    aria-label="Analytics date range"
>
<?php
$rangeOptions = [
    '24h' => '24H',
    '7d' => '7D',
    '30d' => '30D',
    'quarter' => 'QTD',
    'year' => 'YTD',
];
?>

<?php foreach (
    $rangeOptions
    as $key => $label
): ?>
<a
    class="<?= $rangeKey === $key
        ? 'is-active'
        : '' ?>"
    href="/analytics.php?range=<?= moderation_e(
        $key
    ) ?>"
>
    <?= moderation_e($label) ?>
</a>
<?php endforeach; ?>
</nav>


<section
    class="admin-panel admin-analytics-section"
>
<header class="admin-panel-header">
    <div>
        <p>Acquisition</p>
        <h2>New Accounts</h2>
    </div>
</header>

<div class="admin-analytics-metrics is-four">
    <div>
        <span>24H</span>
        <strong><?= number_format(
            $accountGrowth['24h']
        ) ?></strong>
    </div>

    <div>
        <span>7D</span>
        <strong><?= number_format(
            $accountGrowth['7d']
        ) ?></strong>
    </div>

    <div>
        <span>30D</span>
        <strong><?= number_format(
            $accountGrowth['30d']
        ) ?></strong>
    </div>

    <div>
        <span>QTD</span>
        <strong><?= number_format(
            $accountGrowth['quarter']
        ) ?></strong>
    </div>
</div>
</section>


<section class="admin-analytics-chart-grid">

<article class="admin-panel admin-analytics-chart-card">
<header class="admin-panel-header">
    <div>
        <p><?= moderation_e(
            $range['label']
        ) ?></p>
        <h2>Account Growth</h2>
    </div>

    <strong>
        <?= number_format(
            $selectedNewAccounts
        ) ?>
    </strong>
</header>

<div class="admin-analytics-chart">
    <?= admin_analytics_bar_chart(
        $accountBuckets,
        'New accounts over the selected analytics period'
    ) ?>

    <div class="admin-analytics-chart-foot">
        <span>
            <?= moderation_e(
                $rangeStartLabel
            ) ?>
        </span>

        <span>
            <?= moderation_e(
                $rangeEndLabel
            ) ?>
        </span>
    </div>
</div>
</article>


<article class="admin-panel admin-analytics-chart-card">
<header class="admin-panel-header">
    <div>
        <p><?= moderation_e(
            $range['label']
        ) ?></p>
        <h2>Revenue</h2>
    </div>

    <strong>
        <?= $siteRevenueAvailable
            ? moderation_e(
                admin_analytics_money(
                    $siteRevenueCents
                )
            )
            : 'â' ?>
    </strong>
</header>

<div class="admin-analytics-chart">
    <?= admin_analytics_bar_chart(
        array_map(
            static fn ($value): int =>
                max(
                    0,
                    (int) $value
                ),
            $revenueBuckets
        ),
        'Tracked revenue over the selected analytics period'
    ) ?>

    <div class="admin-analytics-chart-foot">
        <span>
            <?= moderation_e(
                $rangeStartLabel
            ) ?>
        </span>

        <span>
            <?= moderation_e(
                $rangeEndLabel
            ) ?>
        </span>
    </div>
</div>
</article>

</section>


<section
    class="admin-panel admin-analytics-section"
>
<header class="admin-panel-header">
    <div>
        <p><?= moderation_e(
            $range['label']
        ) ?></p>
        <h2>Revenue & Commerce</h2>
    </div>
</header>

<div class="admin-analytics-metrics is-six">
    <div>
        <span>Site Revenue</span>
        <strong>
            <?= $siteRevenueAvailable
                ? moderation_e(
                    admin_analytics_money(
                        $siteRevenueCents
                    )
                )
                : 'â' ?>
        </strong>
    </div>

    <div>
        <span>Memberships</span>
        <strong>
            <?= $membershipRevenueAvailable
                ? moderation_e(
                    admin_analytics_money(
                        $membershipRevenueCents
                    )
                )
                : 'â' ?>
        </strong>
    </div>

    <div>
        <span>Shop</span>
        <strong>
            <?= $shopAvailable
                ? moderation_e(
                    admin_analytics_money(
                        $shopRevenueCents
                    )
                )
                : 'â' ?>
        </strong>
    </div>

    <div>
        <span>Shop Orders</span>
        <strong>
            <?= $shopAvailable
                ? number_format(
                    $shopOrderCount
                )
                : 'â' ?>
        </strong>
    </div>

    <div>
        <span>Units</span>
        <strong>
            <?= $shopAvailable
                ? number_format(
                    $shopUnits
                )
                : 'â' ?>
        </strong>
    </div>

    <div>
        <span>Avg Order</span>
        <strong>
            <?= $shopAvailable
                ? moderation_e(
                    admin_analytics_money(
                        $shopAverageOrderCents
                    )
                )
                : 'â' ?>
        </strong>
    </div>
</div>

<div class="admin-analytics-submetrics">
    <span>
        Membership payments
        <strong><?= number_format(
            $membershipPaymentCount
        ) ?></strong>
    </span>

    <span>
        Shop refunds
        <strong><?= moderation_e(
            admin_analytics_money(
                $shopRefundCents
            )
        ) ?></strong>
    </span>
</div>
</section>


<section
    class="admin-panel admin-analytics-section"
>
<header class="admin-panel-header">
    <div>
        <p>Membership</p>
        <h2>Current Base</h2>
    </div>
</header>

<div class="admin-analytics-metrics is-six">
    <div>
        <span>Accounts</span>
        <strong>
            <?= number_format(
                $totalAccounts
            ) ?>
        </strong>
    </div>

    <div>
        <span>Paid</span>
        <strong>
            <?= number_format(
                $activePaid
            ) ?>
        </strong>
    </div>

    <div>
        <span>Monthly</span>
        <strong>
            <?= number_format(
                $monthlyPaid
            ) ?>
        </strong>
    </div>

    <div>
        <span>Annual</span>
        <strong>
            <?= number_format(
                $annualPaid
            ) ?>
        </strong>
    </div>

    <div>
        <span>Paid Share</span>
        <strong>
            <?= moderation_e(
                admin_analytics_percent(
                    $paidShare
                )
            ) ?>
        </strong>
    </div>

    <div>
        <span>Est. MRR</span>
        <strong>
            <?= moderation_e(
                admin_analytics_money(
                    $estimatedMrrCents
                )
            ) ?>
        </strong>
    </div>
</div>

<div class="admin-analytics-submetrics">
    <span>
        Complimentary
        <strong><?= number_format(
            $complimentary
        ) ?></strong>
    </span>

    <span>
        Paid starts <?= moderation_e(
            $range['label']
        ) ?>
        <strong><?= number_format(
            $selectedPaidStarts
        ) ?></strong>
    </span>
</div>
</section>


<section class="admin-analytics-detail-grid">

<article class="admin-panel admin-analytics-section">
<header class="admin-panel-header">
    <div>
        <p><?= moderation_e(
            $range['label']
        ) ?></p>
        <h2>Acquisition Sources</h2>
    </div>
</header>

<?php if (!$acquisitionRows): ?>

<div class="admin-analytics-empty">
    No account-source data in this period.
</div>

<?php else: ?>

<div class="admin-analytics-source-list">
<?php foreach (
    $acquisitionRows
    as $row
): ?>
<?php
$sourceKey =
    (string) (
        $row['source_key']
        ?? 'unknown'
    );

$sourceCount =
    max(
        0,
        (int) (
            $row['source_count']
            ?? 0
        )
    );

$sourcePercent =
    $acquisitionTotal > 0
        ? (
            $sourceCount
            / $acquisitionTotal
        ) * 100
        : 0.0;
?>

<div class="admin-analytics-source-row">
    <span>
        <?= moderation_e(
            admin_analytics_source_label(
                $sourceKey
            )
        ) ?>
    </span>

    <progress
        max="100"
        value="<?= moderation_e(
            number_format(
                $sourcePercent,
                2,
                '.',
                ''
            )
        ) ?>"
    ></progress>

    <strong>
        <?= number_format(
            $sourceCount
        ) ?>
    </strong>
</div>

<?php endforeach; ?>
</div>

<?php endif; ?>
</article>


<article class="admin-panel admin-analytics-section">
<header class="admin-panel-header">
    <div>
        <p>Lifetime</p>
        <h2>Promotion Codes</h2>
    </div>

    <a
        class="admin-analytics-mini-link"
        href="/promotion-codes.php"
    >
        Manage
    </a>
</header>

<?php if (!$promotionCodes): ?>

<div class="admin-analytics-empty">
    No promotion codes yet.
</div>

<?php else: ?>

<div class="admin-analytics-table-scroll">
<table class="admin-analytics-table">
<thead>
<tr>
    <th>Code</th>
    <th>Uses</th>
    <th>Limit</th>
    <th>Revenue</th>
</tr>
</thead>
<tbody>

<?php foreach (
    $promotionCodes
    as $code
): ?>
<tr>
    <td>
        <strong>
            <?= moderation_e(
                (string) (
                    $code['code']
                    ?? ''
                )
            ) ?>
        </strong>

        <small>
            <?php
            $discountType =
                (string) (
                    $code[
                        'discount_type'
                    ]
                    ?? 'percent'
                );

            $discountValue =
                (int) (
                    $code[
                        'discount_value'
                    ]
                    ?? 0
                );

            echo moderation_e(
                $discountType === 'amount'
                    ? admin_analytics_money(
                        $discountValue
                    ) . ' off'
                    : number_format(
                        $discountValue
                    ) . '% off'
            );
            ?>
        </small>
    </td>

    <td>
        <?= number_format(
            (int) (
                $code['redemptions']
                ?? 0
            )
        ) ?>
    </td>

    <td>
        <?php
        $limit =
            (int) (
                $code[
                    'max_redemptions'
                ]
                ?? 0
            );
        ?>

        <?= $limit > 0
            ? number_format($limit)
            : 'â' ?>
    </td>

    <td>
        <?= moderation_e(
            admin_analytics_money(
                (int) (
                    $code[
                        'revenue_cents'
                    ]
                    ?? 0
                )
            )
        ) ?>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>
</article>

</section>


<section
    class="admin-panel admin-analytics-section"
>
<header class="admin-panel-header">
    <div>
        <p>Lifetime</p>
        <h2>Promotion Campaigns</h2>
    </div>

    <a
        class="admin-analytics-mini-link"
        href="/memberships.php"
    >
        Manage
    </a>
</header>

<?php if (!$campaignRows): ?>

<div class="admin-analytics-empty">
    No campaign results yet.
</div>

<?php else: ?>

<div class="admin-analytics-table-scroll is-campaigns">
<table class="admin-analytics-table">
<thead>
<tr>
    <th>Campaign</th>
    <th>Checkouts</th>
    <th>Sales</th>
    <th>Conv.</th>
    <th>Revenue</th>
</tr>
</thead>
<tbody>

<?php foreach (
    $campaignRows
    as $campaign
): ?>
<tr>
    <td>
        <strong>
            <?= moderation_e(
                (string) (
                    $campaign['name']
                    ?? $campaign[
                        'public_label'
                    ]
                    ?? 'Promotion'
                )
            ) ?>
        </strong>

        <?php if (
            !empty(
                $campaign[
                    'public_label'
                ]
            )
            && (
                (string) (
                    $campaign[
                        'public_label'
                    ]
                )
                !==
                (string) (
                    $campaign['name']
                    ?? ''
                )
            )
        ): ?>
        <small>
            <?= moderation_e(
                (string) $campaign[
                    'public_label'
                ]
            ) ?>
        </small>
        <?php endif; ?>
    </td>

    <td>
        <?= number_format(
            (int) (
                $campaign[
                    'checkout_started'
                ]
                ?? 0
            )
        ) ?>
    </td>

    <td>
        <?= number_format(
            (int) (
                $campaign[
                    'membership_purchased'
                ]
                ?? 0
            )
        ) ?>
    </td>

    <td>
        <?= moderation_e(
            admin_analytics_percent(
                (float) (
                    $campaign[
                        'conversion_rate'
                    ]
                    ?? 0
                )
            )
        ) ?>
    </td>

    <td>
        <?= moderation_e(
            admin_analytics_money(
                (int) (
                    $campaign[
                        'revenue_cents'
                    ]
                    ?? 0
                )
            )
        ) ?>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>
</section>


<?php require __DIR__ . '/_footer.php'; ?>

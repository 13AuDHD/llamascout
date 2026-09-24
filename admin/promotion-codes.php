<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';
require_once dirname(__DIR__) . '/app/timezone.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

$viewerTimezone =
    llama_viewer_timezone();

$timezoneLabels =
    llama_timezones();

$viewerTimezoneLabel =
    (string) (
        $timezoneLabels[$viewerTimezone]
        ?? $viewerTimezone
    );


/* =========================================================
   HELPERS
   ========================================================= */

function promotion_code_local_to_utc(
    string $value,
    string $timezone
): string {
    $value = trim($value);

    $local =
        DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $value,
            new DateTimeZone($timezone)
        );

    if (!$local) {
        throw new InvalidArgumentException(
            'A valid date and time is required.'
        );
    }

    return $local
        ->setTimezone(
            new DateTimeZone('UTC')
        )
        ->format('Y-m-d H:i:s');
}


function promotion_code_utc_to_local_input(
    ?string $value,
    string $timezone
): string {
    $value =
        trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )
            ->setTimezone(
                new DateTimeZone($timezone)
            )
            ->format('Y-m-d\TH:i');

    } catch (Throwable) {
        return '';
    }
}


function promotion_code_money(
    int $cents
): string {
    return
        '$'
        . number_format(
            $cents / 100,
            2
        );
}


function promotion_code_scope_label(
    string $scope
): string {
    return match (
        strtolower(trim($scope))
    ) {
        'monthly' => 'Monthly',
        'annual' => 'Annual',
        'all' => 'Monthly + Annual',
        default => ucfirst($scope),
    };
}


function promotion_code_discount_summary(
    array $code,
    int $monthlyBasePriceCents
): string {
    $discountType = strtolower(
        trim(
            (string) (
                $code['discount_type']
                ?? ''
            )
        )
    );

    $discountValue = max(
        0,
        (int) (
            $code['discount_value']
            ?? 0
        )
    );

    $planScope = strtolower(
        trim(
            (string) (
                $code['plan_scope']
                ?? ''
            )
        )
    );

    if ($discountType === 'percent') {
        return
            number_format($discountValue)
            . '% off';
    }

    if ($discountType === 'amount') {
        if (
            $planScope === 'monthly'
            && $monthlyBasePriceCents > 0
            && $discountValue < $monthlyBasePriceCents
        ) {
            $promotionalPrice =
                $monthlyBasePriceCents
                - $discountValue;

            return
                promotion_code_money(
                    $promotionalPrice
                )
                . '/month'
                . ' ('
                . promotion_code_money(
                    $discountValue
                )
                . ' off)';
        }

        return
            promotion_code_money($discountValue)
            . ' off';
    }

    return 'Promotion';
}


function promotion_code_duration_summary(
    array $code
): string {
    $scope = strtolower(
        trim(
            (string) (
                $code['plan_scope']
                ?? ''
            )
        )
    );

    $duration = strtolower(
        trim(
            (string) (
                $code['discount_duration']
                ?? 'once'
            )
        )
    );

    $months =
        (int) (
            $code['duration_months']
            ?? 0
        );

    if (
        $scope === 'monthly'
        && $duration === 'months'
        && in_array(
            $months,
            llama_membership_promotion_code_month_options(),
            true
        )
    ) {
        return
            $months === 1
                ? '1 month'
                : $months . ' months';
    }

    if ($scope === 'annual') {
        return '1 annual billing period';
    }

    if ($scope === 'all') {
        return '1 billing period per plan';
    }

    return '1 billing period';
}


function promotion_code_form_input(
    array $source
): array {
    $planScope =
        strtolower(
            trim(
                (string) (
                    $source['plan_scope']
                    ?? 'all'
                )
            )
        );

    $discountType =
        strtolower(
            trim(
                (string) (
                    $source['discount_type']
                    ?? 'percent'
                )
            )
        );

    $discountValue =
        (int) (
            $source['discount_value']
            ?? 0
        );

    $durationMonths =
        (int) (
            $source['duration_months']
            ?? 1
        );

    if (
        !in_array(
            $durationMonths,
            llama_membership_promotion_code_month_options(),
            true
        )
    ) {
        $durationMonths = 1;
    }

    return [
        'internal_name' =>
            (string) (
                $source['internal_name']
                ?? ''
            ),

        'code' =>
            (string) (
                $source['code']
                ?? ''
            ),

        'plan_scope' =>
            in_array(
                $planScope,
                ['all', 'monthly', 'annual'],
                true
            )
                ? $planScope
                : 'all',

        'discount_type' =>
            in_array(
                $discountType,
                ['percent', 'amount'],
                true
            )
                ? $discountType
                : 'percent',

        'discount_value' =>
            $discountType === 'amount'
                ? number_format(
                    $discountValue / 100,
                    2,
                    '.',
                    ''
                )
                : (
                    $discountValue > 0
                        ? (string) $discountValue
                        : ''
                ),

        'duration_months' =>
            $durationMonths,

        'max_redemptions' =>
            !empty(
                $source['max_redemptions']
            )
                ? (string) (
                    (int) $source['max_redemptions']
                )
                : '',

        'starts_at' =>
            (string) (
                $source['starts_at']
                ?? ''
            ),

        'ends_at' =>
            (string) (
                $source['ends_at']
                ?? ''
            ),

        'first_time_customers_only' =>
            !empty(
                $source['first_time_customers_only']
            ),
    ];
}


/* =========================================================
   PAGE DATA
   ========================================================= */

$monthlyBasePriceCents = 0;
$schemaReady = false;

try {
    $exists =
        $db->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'membership_promotion_codes'"
        );

    if (
        !$exists
        || (int) $exists->fetchColumn() < 1
    ) {
        throw new RuntimeException(
            'Promotion code database table is missing.'
        );
    }

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

    $schemaReady = true;

    $monthlyPlan =
        llama_membership_plan_by_interval(
            $db,
            'monthly',
            true
        );

    if ($monthlyPlan) {
        $monthlyBasePriceCents =
            (int) (
                $monthlyPlan['base_price_cents']
                ?? 0
            );
    }


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
            throw new InvalidArgumentException(
                'Your session token expired. Reload and try again.'
            );
        }

        $action = trim(
            (string) (
                $_POST['promotion_code_action']
                ?? ''
            )
        );


        if (
            in_array(
                $action,
                ['create', 'update'],
                true
            )
            && (
                (string) (
                    $_POST['confirm_save']
                    ?? ''
                )
                !== '1'
            )
        ) {
            throw new InvalidArgumentException(
                'Use the save button to create or update a promotion code.'
            );
        }


        if (
            $action === 'create'
            || $action === 'update'
        ) {
            $discountType = strtolower(
                trim(
                    (string) (
                        $_POST['discount_type']
                        ?? 'percent'
                    )
                )
            );

            $discountRaw = trim(
                (string) (
                    $_POST['discount_value']
                    ?? ''
                )
            );

            $planScope = strtolower(
                trim(
                    (string) (
                        $_POST['plan_scope']
                        ?? 'all'
                    )
                )
            );

            if (!is_numeric($discountRaw)) {
                throw new InvalidArgumentException(
                    'Enter a valid discount.'
                );
            }

            if (
                !in_array(
                    $discountType,
                    [
                        'percent',
                        'amount',
                        'promotional_price',
                    ],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Choose a valid discount type.'
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

            $discountValue =
                $discountType === 'percent'
                    ? (int) round(
                        (float) $discountRaw
                    )
                    : (int) round(
                        ((float) $discountRaw)
                        * 100
                    );

            if ($discountValue < 1) {
                throw new InvalidArgumentException(
                    'Discount must be greater than zero.'
                );
            }


            $discountDuration = 'once';
            $durationMonths = null;

            if ($planScope === 'monthly') {
                $promotionDuration = strtolower(
                    trim(
                        (string) (
                            $_POST['promotion_duration']
                            ?? 'months_1'
                        )
                    )
                );

                if (
                    !preg_match(
                        '/^months_(1|2|3|6|9|12)$/',
                        $promotionDuration,
                        $durationMatch
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Choose 1, 2, 3, 6, 9, or 12 months.'
                    );
                }

                $discountDuration = 'months';
                $durationMonths =
                    (int) $durationMatch[1];
            }

            if (
                $discountType === 'promotional_price'
                && $planScope !== 'monthly'
            ) {
                throw new InvalidArgumentException(
                    'Promotional monthly pricing is available only for the Monthly membership.'
                );
            }


            $input = [
                'internal_name' =>
                    $_POST['internal_name']
                    ?? '',

                'code' =>
                    $_POST['code']
                    ?? '',

                'discount_type' =>
                    $discountType,

                'discount_value' =>
                    $discountValue,

                'plan_scope' =>
                    $planScope,

                'discount_duration' =>
                    $discountDuration,

                'duration_months' =>
                    $durationMonths,

                'starts_at' =>
                    promotion_code_local_to_utc(
                        (string) (
                            $_POST['starts_at']
                            ?? ''
                        ),
                        $viewerTimezone
                    ),

                'ends_at' =>
                    promotion_code_local_to_utc(
                        (string) (
                            $_POST['ends_at']
                            ?? ''
                        ),
                        $viewerTimezone
                    ),

                'first_time_customers_only' =>
                    isset(
                        $_POST[
                            'first_time_customers_only'
                        ]
                    ),

                'max_redemptions' =>
                    $_POST['max_redemptions']
                    ?? null,
            ];


            if ($action === 'update') {
                $id =
                    (int) (
                        $_POST['promotion_code_id']
                        ?? 0
                    );

                llama_update_membership_promotion_code(
                    $db,
                    $id,
                    $input
                );

                $notice =
                    'Promotion code updated in Stripe and Llama Scout.';

            } else {
                llama_create_membership_promotion_code(
                    $db,
                    $input,
                    $actorUserId
                );

                $notice =
                    'Promotion code created in Stripe.';
            }
        }


        if ($action === 'toggle') {
            $id =
                (int) (
                    $_POST['promotion_code_id']
                    ?? 0
                );

            $enabled =
                (int) (
                    $_POST['enabled']
                    ?? 0
                )
                === 1;

            llama_set_membership_promotion_code_enabled(
                $db,
                $id,
                $enabled
            );

            $notice =
                $enabled
                    ? 'Promotion code enabled.'
                    : 'Promotion code disabled.';
        }
    }


    llama_sync_membership_promotion_codes(
        $db
    );

} catch (Throwable $exception) {
    $reference =
        llama_log_caught_exception(
            $exception,
            'admin.promotion_codes',
            [],
            [
                InvalidArgumentException::class,
            ]
        );

    $error =
        $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference(
                'Promotion code could not be updated.',
                $reference
            );
}


/* =========================================================
   ADMIN NAV
   ========================================================= */

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
    'Promotion Codes';

$adminPageEyebrow =
    'Commerce';

$adminActiveNav =
    'promotion-codes';


/* =========================================================
   PROMOTION CODE LIST AND EDITOR
   ========================================================= */

$codes = [];
$codeStats = [];
$editingCode = null;

if ($schemaReady) {
    try {
        $codes =
            $db->query(
                'SELECT *
                 FROM membership_promotion_codes
                 ORDER BY starts_at DESC, id DESC'
            )->fetchAll(PDO::FETCH_ASSOC);

        $codeStats =
            llama_membership_promotion_code_stats(
                $db
            );

        $editId =
            (int) (
                $_GET['edit']
                ?? (
                    $_POST['promotion_code_action'] === 'update'
                        ? (
                            $_POST['promotion_code_id']
                            ?? 0
                        )
                        : 0
                )
            );

        if ($editId > 0) {
            foreach ($codes as $candidate) {
                if (
                    (int) $candidate['id']
                    === $editId
                ) {
                    $editingCode = $candidate;
                    break;
                }
            }
        }

    } catch (Throwable $listException) {
        if ($error === '') {
            $reference =
                llama_log_caught_exception(
                    $listException,
                    'admin.promotion_codes.list'
                );

            $error =
                llama_error_message_with_reference(
                    'Promotion codes could not be loaded.',
                    $reference
                );
        }
    }
}


$formSource =
    $editingCode
        ? promotion_code_form_input(
            $editingCode
        )
        : [
            'internal_name' => '',
            'code' => '',
            'plan_scope' => 'all',
            'discount_type' => 'percent',
            'discount_value' => '',
            'duration_months' => 1,
            'max_redemptions' => '',
            'starts_at' => '',
            'ends_at' => '',
            'first_time_customers_only' => false,
        ];

if ($editingCode) {
    $formSource['starts_at'] =
        promotion_code_utc_to_local_input(
            $editingCode['starts_at']
                ?? null,
            $viewerTimezone
        );

    $formSource['ends_at'] =
        promotion_code_utc_to_local_input(
            $editingCode['ends_at']
                ?? null,
            $viewerTimezone
        );
}


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


<?php if ($schemaReady): ?>


<section class="admin-panel">


<header class="admin-panel-header">

    <div>
        <p>
            <?= $editingCode
                ? 'Edit customer discount'
                : 'Customer discounts'
            ?>
        </p>

        <h2>
            <?= $editingCode
                ? 'Edit Promotion Code'
                : 'Create Promotion Code'
            ?>
        </h2>
    </div>


    <div class="admin-campaign-card-actions">

        <?php if ($editingCode): ?>

        <a
            class="admin-button"
            href="/promotion-codes.php"
        >
            Cancel edit
        </a>

        <?php endif; ?>


        <a
            class="admin-button"
            href="/memberships.php"
        >
            Pricing & Promotions
        </a>

    </div>

</header>


<?php if ($editingCode): ?>

<div class="admin-user-notice">

    Saving changes replaces the underlying Stripe Promotion Code
    and Coupon when needed. Existing customer subscriptions keep
    discounts already attached to them. Redemption and revenue
    history stay attached to this Llama Scout record.

</div>

<?php endif; ?>


<form
    class="promo-code-form"
    method="post"
    id="promotion-code-form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>


<input
    type="hidden"
    name="promotion_code_action"
    value="<?= $editingCode
        ? 'update'
        : 'create'
    ?>"
>


<input
    type="hidden"
    name="promotion_code_id"
    value="<?= $editingCode
        ? (int) $editingCode['id']
        : 0
    ?>"
>


<input
    type="hidden"
    name="confirm_save"
    id="promotion-confirm-save"
    value="0"
>


<div class="promo-code-grid">


<label>

    Internal name

    <input
        type="text"
        name="internal_name"
        maxlength="150"
        placeholder="Summer monthly special"
        value="<?= moderation_e(
            $formSource['internal_name']
        ) ?>"
        required
    >

</label>


<label>

    Customer code

    <input
        type="text"
        name="code"
        maxlength="100"
        placeholder="SUMMER499"
        autocapitalize="characters"
        value="<?= moderation_e(
            $formSource['code']
        ) ?>"
        required
    >

</label>


<label>

    Membership plan

    <select
        name="plan_scope"
        id="promotion-plan-scope"
    >

        <option
            value="all"
            <?= $formSource['plan_scope'] === 'all'
                ? 'selected'
                : ''
            ?>
        >
            Monthly + Annual
        </option>

        <option
            value="monthly"
            <?= $formSource['plan_scope'] === 'monthly'
                ? 'selected'
                : ''
            ?>
        >
            Monthly only
        </option>

        <option
            value="annual"
            <?= $formSource['plan_scope'] === 'annual'
                ? 'selected'
                : ''
            ?>
        >
            Annual only
        </option>

    </select>

</label>


<label>

    Discount type

    <select
        name="discount_type"
        id="promotion-discount-type"
    >

        <option
            value="percent"
            <?= $formSource['discount_type'] === 'percent'
                ? 'selected'
                : ''
            ?>
        >
            Percent off
        </option>

        <option
            value="amount"
            <?= $formSource['discount_type'] === 'amount'
                ? 'selected'
                : ''
            ?>
        >
            Dollar amount off
        </option>

        <option value="promotional_price">
            Promotional monthly price
        </option>

    </select>

</label>


<label>

    <span id="promotion-discount-value-label">
        Discount value
    </span>

    <input
        type="number"
        name="discount_value"
        id="promotion-discount-value"
        min="0.01"
        step="0.01"
        inputmode="decimal"
        value="<?= moderation_e(
            $formSource['discount_value']
        ) ?>"
        required
    >

    <small
        class="admin-table-muted"
        id="promotion-discount-value-help"
    >
        Enter the discount.
    </small>

</label>


<label>

    Discount duration

    <select
        name="promotion_duration"
        id="promotion-duration"
        <?= $formSource['plan_scope'] === 'monthly'
            ? ''
            : 'disabled'
        ?>
    >

        <?php foreach (
            llama_membership_promotion_code_month_options()
            as $months
        ): ?>

        <option
            value="months_<?= (int) $months ?>"
            <?= (int) $formSource['duration_months'] ===
                $months
                    ? 'selected'
                    : ''
            ?>
        >
            <?= (int) $months ?>
            <?= $months === 1
                ? 'month'
                : 'months'
            ?>
        </option>

        <?php endforeach; ?>

    </select>

    <small
        class="admin-table-muted"
        id="promotion-duration-help"
    >
        Monthly duration options become available when Monthly only is selected.
    </small>

</label>


<label>

    Maximum redemptions

    <input
        type="number"
        name="max_redemptions"
        min="1"
        step="1"
        inputmode="numeric"
        placeholder="Unlimited"
        value="<?= moderation_e(
            $formSource['max_redemptions']
        ) ?>"
    >

</label>


<label>

    Starts,
    <?= moderation_e(
        $viewerTimezoneLabel
    ) ?>

    <input
        type="datetime-local"
        name="starts_at"
        value="<?= moderation_e(
            $formSource['starts_at']
        ) ?>"
        required
    >

</label>


<label>

    Ends,
    <?= moderation_e(
        $viewerTimezoneLabel
    ) ?>

    <input
        type="datetime-local"
        name="ends_at"
        value="<?= moderation_e(
            $formSource['ends_at']
        ) ?>"
        required
    >

</label>


</div>


<p class="admin-table-muted">

    Times entered here use your profile timezone:
    <?= moderation_e($viewerTimezone) ?>.

</p>


<label class="admin-toggle">

    <input
        type="checkbox"
        name="first_time_customers_only"
        value="1"
        <?= !empty(
            $formSource[
                'first_time_customers_only'
            ]
        )
            ? 'checked'
            : ''
        ?>
    >

    <span
        class="admin-toggle-track"
        aria-hidden="true"
    >
        <span class="admin-toggle-knob"></span>
    </span>

    <span class="admin-toggle-copy">
        <strong>
            First-time customers only
        </strong>
    </span>

</label>


<div class="admin-campaign-card-actions">

    <button
        class="admin-button"
        type="button"
        id="promotion-save-button"
    >
        <?= $editingCode
            ? 'Save changes'
            : 'Create code in Stripe'
        ?>
    </button>

    <?php if ($editingCode): ?>

    <a
        class="admin-button"
        href="/promotion-codes.php"
    >
        Cancel
    </a>

    <?php endif; ?>

</div>


</form>


</section>


<section class="admin-panel">


<header class="admin-panel-header">

    <div>
        <p>Stripe promotion codes</p>
        <h2>Codes</h2>
    </div>

    <span>
        <?= number_format(count($codes)) ?>
        total
    </span>

</header>


<?php if (!$codes): ?>


<div class="admin-empty-state">

    <i aria-hidden="true">
        <?= llama_icon('ticket') ?>
    </i>

    <h3>
        No promotion codes.
    </h3>

</div>


<?php else: ?>


<div class="promo-code-list">


<?php foreach ($codes as $code): ?>


<?php

$now = time();

$startsDate =
    new DateTimeImmutable(
        (string) $code['starts_at'],
        new DateTimeZone('UTC')
    );

$endsDate =
    new DateTimeImmutable(
        (string) $code['ends_at'],
        new DateTimeZone('UTC')
    );

$starts =
    $startsDate->getTimestamp();

$ends =
    $endsDate->getTimestamp();

if (empty($code['is_enabled'])) {
    $status = 'Disabled';

} elseif ($now < $starts) {
    $status = 'Scheduled';

} elseif ($now >= $ends) {
    $status = 'Ended';

} else {
    $status = 'Active';
}

$discount =
    promotion_code_discount_summary(
        $code,
        $monthlyBasePriceCents
    );

$duration =
    promotion_code_duration_summary(
        $code
    );

$scopeLabel =
    promotion_code_scope_label(
        (string) (
            $code['plan_scope']
            ?? ''
        )
    );

$results =
    $codeStats[
        (int) $code['id']
    ]
    ?? [];

$redemptions =
    (int) (
        $results['redemptions']
        ?? 0
    );

$revenueCents =
    (int) (
        $results['revenue_cents']
        ?? 0
    );

?>


<article class="promo-code-card">


<div class="promo-code-card-top">


<div>

    <span class="admin-status-pill">
        <?= moderation_e($status) ?>
    </span>

    <h3>
        <?= moderation_e(
            (string) $code['code']
        ) ?>
    </h3>

    <p>
        <?= moderation_e(
            (string) $code['internal_name']
        ) ?>
    </p>

</div>


<div class="admin-campaign-card-actions">

    <a
        class="admin-button"
        href="/promotion-codes.php?edit=<?= (int) $code['id'] ?>"
    >
        Edit
    </a>


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
            name="promotion_code_action"
            value="toggle"
        >

        <input
            type="hidden"
            name="promotion_code_id"
            value="<?= (int) $code['id'] ?>"
        >

        <input
            type="hidden"
            name="enabled"
            value="<?= !empty(
                $code['is_enabled']
            )
                ? '0'
                : '1'
            ?>"
        >

        <button
            class="admin-toggle-action"
            type="submit"
            aria-pressed="<?= !empty(
                $code['is_enabled']
            )
                ? 'true'
                : 'false'
            ?>"
            title="<?= !empty(
                $code['is_enabled']
            )
                ? 'Disable code'
                : 'Enable code'
            ?>"
        >

            <span
                class="admin-toggle-track"
                aria-hidden="true"
            >
                <span class="admin-toggle-knob"></span>
            </span>

            <span class="admin-toggle-action-label">
                <?= !empty(
                    $code['is_enabled']
                )
                    ? 'Enabled'
                    : 'Disabled'
                ?>
            </span>

        </button>

    </form>

</div>


</div>


<div class="promo-code-share">


<label
    for="promotion-share-link-<?= (int) $code['id'] ?>"
>
    Share link
</label>


<div class="promo-code-share-control">

<input
    id="promotion-share-link-<?= (int) $code['id'] ?>"
    type="text"
    readonly
    value="<?= moderation_e(
        'https://account.llamascout.com/promo.php?code='
        . rawurlencode(
            (string) $code['code']
        )
    ) ?>"
>


<button
    class="promo-code-copy-button"
    type="button"
    data-copy-target="promotion-share-link-<?= (int) $code['id'] ?>"
    aria-label="Copy share link"
    title="Copy share link"
>

    <span
        class="promo-code-copy-default"
        aria-hidden="true"
    >
        <?= llama_icon('copy') ?>
    </span>

    <span
        class="promo-code-copy-success"
        aria-hidden="true"
    >
        <?= llama_icon('check') ?>
    </span>

</button>


</div>


</div>


<div class="promo-code-results">


<div class="promo-code-result">

    <span>
        Redemptions
    </span>

    <strong>
        <?= number_format(
            $redemptions
        ) ?>
    </strong>

</div>


<div class="promo-code-result">

    <span>
        Revenue
    </span>

    <strong>
        <?= moderation_e(
            promotion_code_money(
                $revenueCents
            )
        ) ?>
    </strong>

</div>


</div>


<div class="promo-code-meta">

<span>
    <?= moderation_e($discount) ?>
</span>

<span>
    <?= moderation_e($scopeLabel) ?>
</span>

<span>
    <?= moderation_e($duration) ?>
</span>


<?php if (
    !empty(
        $code['first_time_customers_only']
    )
): ?>

<span>
    First-time only
</span>

<?php endif; ?>


<?php if (
    !empty(
        $code['max_redemptions']
    )
): ?>

<span>
    <?= number_format(
        (int) $code['max_redemptions']
    ) ?>
    max
</span>

<?php endif; ?>


<span>

    <?= moderation_e(
        llama_format_viewer_datetime(
            (string) $code['starts_at']
        )
    ) ?>

    â

    <?= moderation_e(
        llama_format_viewer_datetime(
            (string) $code['ends_at']
        )
    ) ?>

</span>


</div>


</article>


<?php endforeach; ?>


</div>


<?php endif; ?>


</section>


<?php endif; ?>


<script>
(function () {
    'use strict';

    const form =
        document.getElementById(
            'promotion-code-form'
        );

    if (!form) {
        return;
    }

    const planSelect =
        document.getElementById(
            'promotion-plan-scope'
        );

    const discountType =
        document.getElementById(
            'promotion-discount-type'
        );

    const discountValue =
        document.getElementById(
            'promotion-discount-value'
        );

    const discountValueLabel =
        document.getElementById(
            'promotion-discount-value-label'
        );

    const discountValueHelp =
        document.getElementById(
            'promotion-discount-value-help'
        );

    const durationSelect =
        document.getElementById(
            'promotion-duration'
        );

    const durationHelp =
        document.getElementById(
            'promotion-duration-help'
        );

    const saveButton =
        document.getElementById(
            'promotion-save-button'
        );

    const confirmSave =
        document.getElementById(
            'promotion-confirm-save'
        );


    function syncPromotionForm() {
        if (
            !planSelect
            || !discountType
            || !discountValue
            || !durationSelect
        ) {
            return;
        }

        const monthlyOnly =
            planSelect.value === 'monthly';

        const annualOnly =
            planSelect.value === 'annual';

        if (monthlyOnly) {
            durationSelect.disabled = false;

            if (
                ![
                    'months_1',
                    'months_2',
                    'months_3',
                    'months_6',
                    'months_9',
                    'months_12'
                ].includes(
                    durationSelect.value
                )
            ) {
                durationSelect.value =
                    'months_1';
            }

            if (durationHelp) {
                durationHelp.textContent =
                    'Choose how many monthly billing cycles receive the promotional price.';
            }

        } else {
            durationSelect.value =
                'months_1';

            durationSelect.disabled =
                true;

            if (durationHelp) {
                durationHelp.textContent =
                    annualOnly
                        ? 'Annual promotions apply to the first annual billing period.'
                        : 'This promotion applies to one billing period for each membership plan.';
            }
        }


        if (
            discountType.value === 'promotional_price'
            && !monthlyOnly
        ) {
            discountType.value = 'percent';
        }


        if (
            discountType.value === 'promotional_price'
        ) {
            if (discountValueLabel) {
                discountValueLabel.textContent =
                    'Promotional monthly price';
            }

            discountValue.min = '0.01';
            discountValue.step = '0.01';
            discountValue.placeholder = '4.99';

            if (discountValueHelp) {
                discountValueHelp.textContent =
                    'Enter the price the customer pays each month, not the amount off.';
            }

            return;
        }


        if (discountType.value === 'amount') {
            if (discountValueLabel) {
                discountValueLabel.textContent =
                    'Dollar amount off';
            }

            discountValue.min = '0.01';
            discountValue.step = '0.01';
            discountValue.placeholder = '2.00';

            if (discountValueHelp) {
                discountValueHelp.textContent =
                    'Enter the dollar amount deducted from the normal membership price.';
            }

            return;
        }


        if (discountValueLabel) {
            discountValueLabel.textContent =
                'Percent off';
        }

        discountValue.min = '1';
        discountValue.step = '1';
        discountValue.placeholder = '25';

        if (discountValueHelp) {
            discountValueHelp.textContent =
                'Enter the percentage to discount.';
        }
    }


    /*
     * Return or Enter must never create or update a code.
     * The user must deliberately tap the save button.
     */
    form.addEventListener(
        'keydown',
        function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const target = event.target;

            if (
                target
                && target.matches(
                    'input, select, textarea'
                )
            ) {
                event.preventDefault();
            }
        }
    );


    form.addEventListener(
        'submit',
        function (event) {
            if (
                !confirmSave
                || confirmSave.value !== '1'
            ) {
                event.preventDefault();
            }
        }
    );


    if (saveButton) {
        saveButton.addEventListener(
            'click',
            function () {
                if (confirmSave) {
                    confirmSave.value = '1';
                }

                if (
                    typeof form.requestSubmit === 'function'
                ) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        );
    }


    if (discountType) {
        discountType.addEventListener(
            'change',
            function () {
                if (
                    discountType.value === 'promotional_price'
                    && planSelect
                ) {
                    planSelect.value = 'monthly';
                }

                syncPromotionForm();
            }
        );
    }


    if (planSelect) {
        planSelect.addEventListener(
            'change',
            syncPromotionForm
        );
    }


    syncPromotionForm();
})();


document.addEventListener(
    'click',
    async function (event) {
        const button =
            event.target.closest(
                '.promo-code-copy-button'
            );

        if (!button) {
            return;
        }

        const targetId =
            button.dataset.copyTarget;

        const input =
            document.getElementById(
                targetId
            );

        if (!input) {
            return;
        }

        let copied = false;

        try {
            if (
                navigator.clipboard
                && window.isSecureContext
            ) {
                await navigator.clipboard.writeText(
                    input.value
                );

                copied = true;
            }

        } catch (error) {
            copied = false;
        }


        if (!copied) {
            input.focus();
            input.select();

            input.setSelectionRange(
                0,
                input.value.length
            );

            try {
                copied =
                    document.execCommand('copy');

            } catch (error) {
                copied = false;
            }
        }


        if (!copied) {
            return;
        }

        button.classList.add('is-copied');

        button.setAttribute(
            'aria-label',
            'Copied'
        );

        button.setAttribute(
            'title',
            'Copied'
        );

        window.setTimeout(
            function () {
                button.classList.remove(
                    'is-copied'
                );

                button.setAttribute(
                    'aria-label',
                    'Copy share link'
                );

                button.setAttribute(
                    'title',
                    'Copy share link'
                );

            },
            1600
        );
    }
);
</script>


<?php

require __DIR__
    . '/_footer.php';

?>

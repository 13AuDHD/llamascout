<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';
require_once dirname(__DIR__) . '/app/timezone.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

function promotion_code_local_to_utc(string $value): string
{
    $value = trim($value);

    $local = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        new DateTimeZone('America/Denver')
    );

    if (!$local) {
        throw new InvalidArgumentException(
            'A valid Mountain Time date and time is required.'
        );
    }

    return $local
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

function promotion_code_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

try {
    $exists = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'membership_promotion_codes'"
    );

    if (!$exists || (int) $exists->fetchColumn() < 1) {
        throw new RuntimeException(
            'Promotion code database table is missing.'
        );
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException(
                'Your session token expired. Reload and try again.'
            );
        }

        $action = trim((string) ($_POST['promotion_code_action'] ?? ''));

        if ($action === 'create') {
            $discountType = trim((string) ($_POST['discount_type'] ?? 'percent'));
            $discountRaw = trim((string) ($_POST['discount_value'] ?? ''));

            if (!is_numeric($discountRaw)) {
                throw new InvalidArgumentException('Enter a valid discount.');
            }

            $discountValue = $discountType === 'amount'
                ? (int) round(((float) $discountRaw) * 100)
                : (int) round((float) $discountRaw);

            llama_create_membership_promotion_code(
                $db,
                [
                    'internal_name' => $_POST['internal_name'] ?? '',
                    'code' => $_POST['code'] ?? '',
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'plan_scope' => $_POST['plan_scope'] ?? 'all',
                    'starts_at' => promotion_code_local_to_utc(
                        (string) ($_POST['starts_at'] ?? '')
                    ),
                    'ends_at' => promotion_code_local_to_utc(
                        (string) ($_POST['ends_at'] ?? '')
                    ),
                    'first_time_customers_only' =>
                        isset($_POST['first_time_customers_only']),
                    'max_redemptions' => $_POST['max_redemptions'] ?? null,
                ],
                $actorUserId
            );

            $notice = 'Promotion code created in Stripe.';
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['promotion_code_id'] ?? 0);
            $enabled = (int) ($_POST['enabled'] ?? 0) === 1;

            llama_set_membership_promotion_code_enabled(
                $db,
                $id,
                $enabled
            );

            $notice = $enabled
                ? 'Promotion code enabled.'
                : 'Promotion code disabled.';
        }
    }

    llama_sync_membership_promotion_codes($db);
} catch (Throwable $exception) {
    $reference = llama_log_caught_exception(
        $exception,
        'admin.promotion_codes',
        [],
        [InvalidArgumentException::class]
    );

    $error = $reference === null
        ? $exception->getMessage()
        : llama_error_message_with_reference(
            'Promotion code could not be updated.',
            $reference
        );
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Promotion Codes';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'promotion-codes';

$codes = [];
$codeStats = [];

if ($error === '') {
    $codes = $db->query(
        'SELECT *
         FROM membership_promotion_codes
         ORDER BY starts_at DESC, id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $codeStats = llama_membership_promotion_code_stats($db);
}

require __DIR__ . '/_header.php';
?>

<style>
.promo-code-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.promo-code-form {
    display:grid;
    gap:16px;
    padding:20px;
}
.promo-code-form input,
.promo-code-form select {
    width:100%;
    margin-top:7px;
}
.promo-code-list {
    display:grid;
    gap:14px;
    padding:20px;
}
.promo-code-card {
    display:grid;
    gap:12px;
    padding:16px;
    border:1px solid var(--admin-border,rgba(127,127,127,.25));
    border-radius:14px;
}
.promo-code-card-top {
    display:flex;
    justify-content:space-between;
    gap:14px;
    align-items:flex-start;
}
.promo-code-card h3,
.promo-code-card p {
    margin:0;
}
.promo-code-meta {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}
.promo-code-meta span {
    padding:6px 9px;
    border-radius:999px;
    background:rgba(127,127,127,.10);
    font-size:.8rem;
}
.promo-code-results {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}
.promo-code-result {
    padding:11px 12px;
    border:1px solid var(--admin-border,rgba(127,127,127,.25));
    border-radius:12px;
    background:rgba(127,127,127,.055);
}
.promo-code-result span {
    display:block;
    margin-bottom:4px;
    font-size:.72rem;
    opacity:.68;
    text-transform:uppercase;
    letter-spacing:.045em;
}
.promo-code-result strong {
    display:block;
    font-size:1rem;
}
.promo-code-share {
    display:grid;
    gap:7px;
}
.promo-code-share label {
    font-size:.78rem;
    font-weight:700;
    opacity:.75;
}
.promo-code-share input {
    width:100%;
}
@media (max-width:760px) {
    .promo-code-grid {
        grid-template-columns:1fr;
    }
    .promo-code-card-top {
        display:grid;
    }
    .promo-code-results {
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

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Customer-entered discounts</p>
            <h2>Create Promotion Code</h2>
        </div>
        <a class="admin-button" href="/memberships.php">
            Pricing & Promotions
        </a>
    </header>

    <form class="promo-code-form" method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="promotion_code_action"
            value="create"
        >

        <div class="promo-code-grid">
            <label>
                Internal name
                <input
                    type="text"
                    name="internal_name"
                    maxlength="150"
                    placeholder="Podcast partner offer"
                    required
                >
            </label>

            <label>
                Customer code
                <input
                    type="text"
                    name="code"
                    maxlength="100"
                    placeholder="LLAMA25"
                    autocapitalize="characters"
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

            <label>
                Membership plan
                <select name="plan_scope">
                    <option value="all">Monthly + Annual</option>
                    <option value="monthly">Monthly only</option>
                    <option value="annual">Annual only</option>
                </select>
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
        </div>

        <label>
            <input
                type="checkbox"
                name="first_time_customers_only"
                value="1"
            >
            First-time customers only
        </label>

        <button class="admin-button" type="submit">
            Create code in Stripe
        </button>
    </form>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Stripe promotion codes</p>
            <h2>Codes</h2>
        </div>
        <span><?= number_format(count($codes)) ?> total</span>
    </header>

    <?php if (!$codes): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-ticket" aria-hidden="true"></i>
            <h3>No promotion codes.</h3>
        </div>
    <?php else: ?>
        <div class="promo-code-list">
            <?php foreach ($codes as $code): ?>
                <?php
                $now = time();
                $starts = strtotime((string) $code['starts_at']) ?: 0;
                $ends = strtotime((string) $code['ends_at']) ?: 0;

                if (empty($code['is_enabled'])) {
                    $status = 'Disabled';
                } elseif ($now < $starts) {
                    $status = 'Scheduled';
                } elseif ($now >= $ends) {
                    $status = 'Ended';
                } else {
                    $status = 'Active';
                }

                $discount = (string) $code['discount_type'] === 'percent'
                    ? ((int) $code['discount_value']) . '% off'
                    : promotion_code_money((int) $code['discount_value']) . ' off';

                $results = $codeStats[(int) $code['id']] ?? [];
                $redemptions = (int) ($results['redemptions'] ?? 0);
                $revenueCents = (int) ($results['revenue_cents'] ?? 0);
                ?>
                <article class="promo-code-card">
                    <div class="promo-code-card-top">
                        <div>
                            <span class="admin-status-pill">
                                <?= moderation_e($status) ?>
                            </span>
                            <h3><?= moderation_e((string) $code['code']) ?></h3>
                            <p><?= moderation_e((string) $code['internal_name']) ?></p>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= moderation_e(moderation_csrf_token()) ?>"
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
                                value="<?= !empty($code['is_enabled']) ? '0' : '1' ?>"
                            >

                            <button class="admin-button" type="submit">
                                <?= !empty($code['is_enabled']) ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </div>

                    <div class="promo-code-share">
                        <label
                            for="promotion-share-link-<?= (int) $code['id'] ?>"
                        >
                            Share link
                        </label>
                        <input
                            id="promotion-share-link-<?= (int) $code['id'] ?>"
                            type="text"
                            readonly
                            value="<?= moderation_e(
                                'https://account.llamascout.com/promo.php?code='
                                . rawurlencode((string) $code['code'])
                            ) ?>"
                        >
                    </div>

                    <div class="promo-code-results">
                        <div class="promo-code-result">
                            <span>Redemptions</span>
                            <strong><?= number_format($redemptions) ?></strong>
                        </div>
                        <div class="promo-code-result">
                            <span>Revenue</span>
                            <strong><?= moderation_e(promotion_code_money($revenueCents)) ?></strong>
                        </div>
                    </div>

                    <div class="promo-code-meta">
                        <span><?= moderation_e($discount) ?></span>
                        <span><?= moderation_e(ucfirst((string) $code['plan_scope'])) ?></span>
                        <?php if (!empty($code['first_time_customers_only'])): ?>
                            <span>First-time only</span>
                        <?php endif; ?>
                        <?php if (!empty($code['max_redemptions'])): ?>
                            <span><?= number_format((int) $code['max_redemptions']) ?> max</span>
                        <?php endif; ?>
                        <span>
                            <?= moderation_e(
                                llama_format_datetime(
                                    (string) $code['starts_at'],
                                    'America/Denver',
                                    'M j, Y g:i A T'
                                )
                            ) ?>
                            â
                            <?= moderation_e(
                                llama_format_datetime(
                                    (string) $code['ends_at'],
                                    'America/Denver',
                                    'M j, Y g:i A T'
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

<?php require __DIR__ . '/_footer.php'; ?>

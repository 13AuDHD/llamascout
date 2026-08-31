<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-scouts.php';
require_once dirname(__DIR__) . '/app/scout-policy.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            admin_scout_update_policy(
                $db,
                $actorUserId,
                (array) ($_POST['policy'] ?? [])
            );
            $notice = 'Scout policy saved.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$policy = admin_scout_policy_rows($db);

$sections = [
    'Scout Period + Renewal' => [
        'annual_new_places_required',
        'scout_period_months',
        'reactivation_new_places_required',
        'reactivation_window_days',
    ],
    'Master Scout Qualification' => [
        'master_scout_qualification_enabled',
        'master_scout_lifetime_new_places_required',
        'master_scout_updates_required',
        'master_scout_updated_places_required',
        'master_scout_corrections_required',
        'master_scout_points_required',
        'master_scout_requires_current_period',
    ],
    'Contribution Point Caps' => [
        'new_place_max_points',
        'place_update_max_points',
        'place_correction_points',
    ],
    'System' => [
        'maintenance_interval_seconds',
    ],
];

$policyByKey = [];
foreach ($policy as $row) {
    $policyByKey[(string) $row['policy_key']] = $row;
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Policies';
$adminPageEyebrow = 'Configuration';
$adminActiveNav = 'policies';

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


<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">

    <?php foreach ($sections as $sectionTitle => $keys): ?>

        <section class="admin-panel admin-policy-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Policy</p>
                    <h2><?= moderation_e($sectionTitle) ?></h2>
                </div>
            </header>

            <div class="admin-policy-grid">

                <?php foreach ($keys as $key): ?>
                    <?php if (!isset($policyByKey[$key])) continue; ?>
                    <?php $row = $policyByKey[$key]; ?>

                    <label class="admin-policy-row">
                        <span>
                            <strong>
                                <?= moderation_e(
                                    ucwords(
                                        str_replace('_', ' ', $key)
                                    )
                                ) ?>
                            </strong>
                            <small>
                                <?= moderation_e(
                                    (string) ($row['description'] ?? '')
                                ) ?>
                            </small>
                        </span>

                        <?php if ((string) $row['value_type'] === 'bool'): ?>
                            <select name="policy[<?= moderation_e($key) ?>]">
                                <option
                                    value="1"
                                    <?= (string) $row['policy_value'] === '1' ? 'selected' : '' ?>
                                >
                                    Enabled
                                </option>
                                <option
                                    value="0"
                                    <?= (string) $row['policy_value'] === '0' ? 'selected' : '' ?>
                                >
                                    Disabled
                                </option>
                            </select>
                        <?php else: ?>
                            <input
                                type="<?= in_array((string) $row['value_type'], ['int','float'], true) ? 'number' : 'text' ?>"
                                name="policy[<?= moderation_e($key) ?>]"
                                value="<?= moderation_e((string) $row['policy_value']) ?>"
                                <?= (string) $row['value_type'] === 'int' ? 'min="0" step="1"' : '' ?>
                            >
                        <?php endif; ?>
                    </label>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endforeach; ?>

    <div class="admin-policy-savebar">
        <p>
            Changes affect future qualification and renewal calculations.
            Previously recorded Scout activity and points are not rewritten.
        </p>

        <button class="admin-button" type="submit">
            Save Scout policy
        </button>
    </div>

</form>

<?php require __DIR__ . '/_footer.php'; ?>

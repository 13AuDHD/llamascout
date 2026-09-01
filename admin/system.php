<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-system.php';
require_once dirname(__DIR__) . '/app/admin-testing.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);
$actorIsOwner = admin_users_current_is_owner(
    $db,
    $actorUserId
);

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
            $action =
                trim(
                    (string) (
                        $_POST['action']
                        ?? 'maintenance'
                    )
                );

            if ($action === 'cleanup_staging') {
                $cleanup =
                    admin_system_cleanup_staging(
                        $db,
                        $actorUserId
                    );

                $notice =
                    'Photo staging cleanup removed ' .
                    number_format(
                        (int) $cleanup['deleted_files']
                    ) .
                    ' abandoned files (' .
                    admin_system_format_bytes(
                        (int) $cleanup['deleted_bytes']
                    ) .
                    ').';
            } elseif ($action === 'reset_test_account') {
                $targetUserId = (int) ($_POST['test_user_id'] ?? 0);
                $result = admin_testing_reset(
                    $db,
                    $actorUserId,
                    $targetUserId,
                    isset($_POST['wipe_stripe']),
                    isset($_POST['wipe_scout']),
                    isset($_POST['wipe_saved_places'])
                );

                $notice = 'Test account reset completed immediately.';
            } else {
                admin_system_set_maintenance(
                    $db,
                    $actorUserId,
                    $_POST
                );

                $notice =
                    ((string) ($_POST['enabled'] ?? '0')) === '1'
                        ? 'Maintenance mode enabled.'
                        : 'Maintenance mode disabled.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_exception(
                $exception,
                'admin.system.' . ($action ?? 'unknown')
            );

            $error =
                'The action failed. Error reference: ' .
                $reference;
        }
    }
}

$state = llama_maintenance_state($db);

$startedByName = '';

if ((int) $state['started_by'] > 0) {
    $starter = admin_users_get(
        $db,
        (int) $state['started_by']
    );

    if ($starter) {
        $startedByName =
            (string) (
                $starter['display_name']
                ?: $starter['username']
                ?: 'User #' . $state['started_by']
            );
    }
}

$lastScoutMaintenance =
    admin_system_last_scout_maintenance($db);

$health =
    admin_system_health(
        $db
    );

$maintenanceHistory =
    admin_system_maintenance_history(
        $db,
        8
    );

$testAccounts = admin_testing_accounts($db);
$stripeTestMode = admin_testing_is_stripe_test_mode();

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'System';
$adminPageEyebrow = 'Configuration';
$adminActiveNav = 'system';

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


<section class="admin-system-status <?= $state['enabled'] ? 'is-maintenance' : 'is-live' ?>">

    <div>
        <span class="admin-system-status-dot" aria-hidden="true"></span>

        <div>
            <p>Site Status</p>
            <h2>
                <?= $state['enabled']
                    ? 'Maintenance Mode'
                    : 'Live' ?>
            </h2>
        </div>
    </div>

    <span>
        <?= $state['enabled']
            ? 'Public access is currently restricted according to the selected scopes.'
            : 'The public site is operating normally.' ?>
    </span>

</section>



<section class="admin-panel admin-system-health-panel">

    <header class="admin-panel-header">
        <div>
            <p>Quick glance</p>
            <h2>System Health</h2>
        </div>

        <div class="admin-health-summary" aria-label="System health summary">
            <span class="is-good">
                <i aria-hidden="true"></i>
                <?= number_format((int) $health['summary']['good']) ?>
                Healthy
            </span>

            <span class="is-attention">
                <i aria-hidden="true"></i>
                <?= number_format((int) $health['summary']['attention']) ?>
                Need attention
            </span>

            <span class="is-down">
                <i aria-hidden="true"></i>
                <?= number_format((int) $health['summary']['down']) ?>
                Problem
            </span>
        </div>
    </header>

    <div class="admin-health-grid">

        <?php foreach ($health['cards'] as $card): ?>

            <article
                class="admin-health-card is-<?= moderation_e(
                    (string) $card['status']
                ) ?>"
            >
                <span
                    class="admin-health-light"
                    aria-hidden="true"
                ></span>

                <div class="admin-health-card-icon">
                    <i
                        class="fa-solid <?= moderation_e(
                            (string) $card['icon']
                        ) ?>"
                        aria-hidden="true"
                    ></i>
                </div>

                <div class="admin-health-card-copy">
                    <span>
                        <?= moderation_e(
                            (string) $card['label']
                        ) ?>
                    </span>

                    <strong>
                        <?= moderation_e(
                            (string) $card['value']
                        ) ?>
                    </strong>

                    <small>
                        <?= moderation_e(
                            (string) $card['detail']
                        ) ?>
                    </small>
                </div>

                <span class="admin-health-status-text">
                    <?= $card['status'] === 'good'
                        ? 'Healthy'
                        : (
                            $card['status'] === 'attention'
                                ? 'Needs attention'
                                : 'Problem'
                        ) ?>
                </span>
            </article>

        <?php endforeach; ?>

    </div>

</section>


<section class="admin-panel admin-system-maintenance-panel">

    <header class="admin-panel-header">
        <div>
            <p>Operations</p>
            <h2>Maintenance Mode</h2>
        </div>

        <?php if (!$actorIsOwner): ?>
            <span>Owner access required to change</span>
        <?php endif; ?>
    </header>

    <form class="admin-system-maintenance-form" method="post">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="maintenance"
        >

        <div class="admin-system-maintenance-toggle">
            <div>
                <strong>
                    <?= $state['enabled']
                        ? 'Maintenance mode is ON'
                        : 'Maintenance mode is OFF' ?>
                </strong>

                <span>
                    Admin access always remains available.
                    Owners and administrators can also bypass maintenance
                    on other Llama Scout surfaces while signed in.
                </span>
            </div>

            <select
                name="enabled"
                <?= !$actorIsOwner ? 'disabled' : '' ?>
            >
                <option
                    value="0"
                    <?= !$state['enabled'] ? 'selected' : '' ?>
                >
                    Site Live
                </option>

                <option
                    value="1"
                    <?= $state['enabled'] ? 'selected' : '' ?>
                >
                    Maintenance Mode
                </option>
            </select>
        </div>


        <label>
            <span>Maintenance message</span>
            <textarea
                name="message"
                rows="4"
                maxlength="500"
                <?= !$actorIsOwner ? 'disabled' : '' ?>
            ><?= moderation_e((string) $state['message']) ?></textarea>
        </label>


        <label>
            <span>Expected return</span>
            <input
                type="datetime-local"
                name="return_at"
                value="<?= moderation_e(
                    preg_replace(
                        '/:\d{2}(?:[+-]\d{2}:\d{2})?$/',
                        '',
                        (string) $state['return_at']
                    )
                ) ?>"
                <?= !$actorIsOwner ? 'disabled' : '' ?>
            >
            <small>
                Optional. Leave blank if there is no reliable return time.
            </small>
        </label>


        <fieldset>
            <legend>Which surfaces should maintenance affect?</legend>

            <label class="admin-system-check">
                <input
                    type="checkbox"
                    name="public_enabled"
                    value="1"
                    <?= $state['public_enabled'] ? 'checked' : '' ?>
                    <?= !$actorIsOwner ? 'disabled' : '' ?>
                >
                <span>
                    <strong>Public site</strong>
                    <small>
                        llamascout.com, Places, map, Guides, profiles, membership.
                    </small>
                </span>
            </label>

            <label class="admin-system-check">
                <input
                    type="checkbox"
                    name="account_enabled"
                    value="1"
                    <?= $state['account_enabled'] ? 'checked' : '' ?>
                    <?= !$actorIsOwner ? 'disabled' : '' ?>
                >
                <span>
                    <strong>Account site</strong>
                    <small>
                        account.llamascout.com.
                    </small>
                </span>
            </label>

            <label class="admin-system-check">
                <input
                    type="checkbox"
                    name="api_enabled"
                    value="1"
                    <?= $state['api_enabled'] ? 'checked' : '' ?>
                    <?= !$actorIsOwner ? 'disabled' : '' ?>
                >
                <span>
                    <strong>API</strong>
                    <small>
                        api.llamascout.com. Keep this off unless API
                        traffic must also stop.
                    </small>
                </span>
            </label>
        </fieldset>


        <?php if ($state['enabled']): ?>
            <div class="admin-system-maintenance-meta">
                <span>
                    <strong>Started</strong>
                    <?= moderation_e(
                        (string) (
                            $state['started_at']
                            ?: 'Unknown'
                        )
                    ) ?>
                </span>

                <span>
                    <strong>Started by</strong>
                    <?= moderation_e(
                        $startedByName !== ''
                            ? $startedByName
                            : 'Unknown'
                    ) ?>
                </span>
            </div>
        <?php endif; ?>


        <div class="admin-user-form-actions">
            <a
                class="admin-button is-muted"
                href="https://llamascout.com/maintenance.php?preview=1"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                Preview maintenance page
            </a>

            <?php if ($actorIsOwner): ?>
                <button class="admin-button" type="submit">
                    Save system status
                </button>
            <?php endif; ?>
        </div>

    </form>

</section>



<section class="admin-panel admin-system-testing-panel">

    <header class="admin-panel-header">
        <div>
            <p>Owner tools</p>
            <h2>Testing</h2>
        </div>

        <?php if (!$actorIsOwner): ?>
            <span>Owner access required</span>
        <?php endif; ?>
    </header>

    <div class="admin-system-testing-body">
        <div class="admin-system-testing-copy">
            <h3>Reset a reusable test account</h3>
            <p>
                Pick a non-Admin account and wipe only the test state you want
                to repeat. Published Places and contribution provenance are not
                deleted by this tool. Account anonymization remains under Users
                for the final account-deletion test.
            </p>
        </div>

        <div class="admin-system-testing-mode <?= $stripeTestMode ? 'is-test' : 'is-live' ?>">
            <i class="fa-solid <?= $stripeTestMode ? 'fa-flask' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
            <span>
                <strong>Stripe: <?= $stripeTestMode ? 'TEST mode' : 'LIVE / unavailable' ?></strong>
                <?= $stripeTestMode
                    ? 'Stripe test subscriptions/customers can be canceled and deleted by the reset tool.'
                    : 'Stripe wipe is blocked. The tool will never silently destroy live Stripe billing.' ?>
            </span>
        </div>

        <?php if ($actorIsOwner): ?>
        <form
            method="post"
            class="admin-system-testing-form"
            onsubmit="return confirm('Reset the selected test account now? The checked state will be removed immediately.');"
        >
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_test_account">

            <label class="admin-system-testing-user">
                <span>Test username</span>
                <select name="test_user_id" required>
                    <option value="">Choose a test account...</option>
                    <?php foreach ($testAccounts as $testAccount): ?>
                        <option value="<?= (int) $testAccount['id'] ?>">
                            <?= moderation_e(
                                (string) ($testAccount['username'] ?: $testAccount['display_name'] ?: $testAccount['email'])
                            ) ?>
                            · User #<?= (int) $testAccount['id'] ?>
                            <?php if (!empty($testAccount['scout_status'])): ?>
                                · Scout: <?= moderation_e((string) $testAccount['scout_status']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="admin-system-testing-options">
                <label class="admin-system-check <?= !$stripeTestMode ? 'is-disabled' : '' ?>">
                    <input
                        type="checkbox"
                        name="wipe_stripe"
                        value="1"
                        <?= !$stripeTestMode ? 'disabled' : '' ?>
                    >
                    <span>
                        <strong>Wipe / disconnect Stripe payments</strong>
                        <small>
                            Cancel the linked Stripe test subscription, delete the
                            Stripe test customer, clear local billing IDs, grants,
                            and membership state.
                        </small>
                    </span>
                </label>

                <label class="admin-system-check">
                    <input type="checkbox" name="wipe_scout" value="1">
                    <span>
                        <strong>Wipe Scout status earned</strong>
                        <small>
                            Remove Scout and Master Scout authority, onboarding,
                            training, reactivation, rank-history test state, and
                            Scout complimentary membership. Published contribution
                            history remains intact.
                        </small>
                    </span>
                </label>

                <label class="admin-system-check">
                    <input type="checkbox" name="wipe_saved_places" value="1">
                    <span>
                        <strong>Wipe Saved Places</strong>
                        <small>
                            Remove the selected account's saved/favorite Place state.
                        </small>
                    </span>
                </label>
            </div>

            <div class="admin-system-testing-warning">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                <span>
                    <strong>Immediate action</strong>
                    Only the checked items are reset. There is no undo button.
                </span>
            </div>

            <div class="admin-user-form-actions">
                <button class="admin-button is-danger" type="submit">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Reset selected test state
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

</section>

<div class="admin-system-operations-grid">

    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>Automation</p>
                <h2>Scheduled Maintenance</h2>
            </div>
        </header>

        <div class="admin-system-operation-body">
            <dl class="admin-user-definition-list">
                <div>
                    <dt>Scout renewal maintenance</dt>
                    <dd>
                        <?= moderation_e(
                            $lastScoutMaintenance
                            ?: 'No run recorded'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Photo staging cleanup</dt>
                    <dd>
                        Runs opportunistically during photo upload activity.
                    </dd>
                </div>
            </dl>
        </div>

    </section>


    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>Storage</p>
                <h2>Photo Staging Cleanup</h2>
            </div>
        </header>

        <div class="admin-system-operation-body">

            <p>
                Only temporary staged files older than 24 hours
                are eligible. Published Place, profile, report,
                and product photos are never touched by this cleanup.
            </p>

            <dl class="admin-user-definition-list">
                <div>
                    <dt>Staged files</dt>
                    <dd>
                        <?= number_format(
                            (int) $health['staging']['files']
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Eligible for cleanup</dt>
                    <dd>
                        <?= number_format(
                            (int) $health['staging']['stale_files']
                        ) ?>
                        files,
                        <?= moderation_e(
                            admin_system_format_bytes(
                                (int) $health['staging']['stale_bytes']
                            )
                        ) ?>
                    </dd>
                </div>
            </dl>

            <?php if ($actorIsOwner): ?>

                <form
                    method="post"
                    class="admin-system-cleanup-form"
                    onsubmit="return confirm('Remove abandoned staged photo files older than 24 hours?');"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="cleanup_staging"
                    >

                    <button
                        class="admin-button is-muted"
                        type="submit"
                    >
                        <i
                            class="fa-solid fa-broom"
                            aria-hidden="true"
                        ></i>
                        Run safe cleanup
                    </button>
                </form>

            <?php endif; ?>

        </div>

    </section>


    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>History</p>
                <h2>Maintenance History</h2>
            </div>

            <a
                class="admin-panel-header-link"
                href="/audit.php?category=system"
            >
                Full audit
            </a>
        </header>

        <?php if (!$maintenanceHistory): ?>

            <div class="admin-empty-state">
                <p>No maintenance changes have been recorded yet.</p>
            </div>

        <?php else: ?>

            <div class="admin-maintenance-history">

                <?php foreach ($maintenanceHistory as $historyRow): ?>

                    <div>
                        <span
                            class="admin-health-light <?= str_ends_with(
                                (string) $historyRow['action'],
                                'disabled'
                            )
                                ? 'is-good'
                                : 'is-attention' ?>"
                            aria-hidden="true"
                        ></span>

                        <div>
                            <strong>
                                <?= moderation_e(
                                    (string) $historyRow['summary']
                                ) ?>
                            </strong>

                            <span>
                                <?= moderation_e(
                                    (string) $historyRow['actor_name']
                                ) ?>
                                ·
                                <?= moderation_e(
                                    (string) $historyRow['created_at']
                                ) ?>
                            </span>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <section class="admin-panel">

        <header class="admin-panel-header">
            <div>
                <p>Accountability</p>
                <h2>Administrative Audit</h2>
            </div>
        </header>

        <div class="admin-system-operation-body">
            <p>
                Search and filter administrative changes, inspect
                stored metadata, and trace who changed what.
            </p>

            <a class="admin-button" href="/audit.php">
                View audit console
            </a>
        </div>

    </section>

</div>

<?php require __DIR__ . '/_footer.php'; ?>

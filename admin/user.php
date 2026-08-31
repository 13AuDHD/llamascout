<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);
$actorIsOwner = admin_users_current_is_owner(
    $db,
    $actorUserId
);

$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);

if ($userId < 1) {
    header('Location: /users.php');
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (!moderation_verify_csrf($csrf)) {
        $error = 'Your session token expired. Reload the page and try again.';
    } else {
        $action = trim(
            (string) ($_POST['admin_user_action'] ?? '')
        );

        try {
            if ($action === 'save-account') {
                admin_users_save_account(
                    $db,
                    $actorUserId,
                    $userId,
                    $_POST
                );

                $notice = 'Account details updated.';
            } elseif ($action === 'save-roles') {
                admin_users_set_roles(
                    $db,
                    $actorUserId,
                    $userId,
                    (array) ($_POST['roles'] ?? [])
                );

                $notice = 'Roles updated.';
            } elseif ($action === 'force-logout') {
                $revoked = admin_users_force_logout(
                    $db,
                    $actorUserId,
                    $userId
                );

                $notice =
                    'Account signed out everywhere. ' .
                    number_format($revoked) .
                    ' session or remember-me record' .
                    ($revoked === 1 ? '' : 's') .
                    ' revoked.';
            } elseif ($action === 'anonymize') {
                $confirmation = trim(
                    (string) ($_POST['confirmation'] ?? '')
                );

                if ($confirmation !== 'ANONYMIZE') {
                    throw new RuntimeException(
                        'Type ANONYMIZE exactly to confirm account deletion.'
                    );
                }

                admin_users_anonymize(
                    $db,
                    $actorUserId,
                    $userId,
                    (string) ($_POST['reason'] ?? '')
                );

                $notice =
                    'Account anonymized. Contribution history was preserved.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$user = admin_users_get(
    $db,
    $userId
);

if (!$user) {
    http_response_code(404);

    $adminPageTitle = 'User Not Found';
    $adminPageEyebrow = 'People';
    $adminActiveNav = 'users';

    $stats = admin_dashboard_stats($db);

    $adminNavCounts = [
        'new_places' => $stats['new_places'],
        'updates' => $stats['updates'],
        'reports' => $stats['reports'],
        'orders' => $stats['orders'],
    ];

    require __DIR__ . '/_header.php';
    ?>
    <section class="admin-panel">
        <div class="admin-empty-state">
            <h2>Account not found.</h2>
            <p><a href="/users.php">Return to Users</a></p>
        </div>
    </section>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$targetRoles = admin_users_roles(
    $db,
    $userId
);

$targetIsOwner = in_array(
    'owner',
    $targetRoles,
    true
);

$userStats = admin_users_stats(
    $db,
    $userId
);

$contributions = admin_users_recent_contributions(
    $db,
    $userId
);

$auditHistory = admin_users_audit_history(
    $db,
    $userId
);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    (string) (
        $user['display_name']
        ?: $user['username']
        ?: 'User #' . $userId
    );

$adminPageEyebrow = 'User Administration';
$adminActiveNav = 'users';

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


<section class="admin-user-summary">

    <div class="admin-user-summary-identity">
        <span class="admin-user-summary-avatar">
            <img
                src="<?= moderation_e(
                    admin_user_avatar_src(
                        (string) ($user['profile_image_src'] ?? ''),
                        $siteUrl
                    )
                ) ?>"
                alt=""
            >
        </span>

        <div>
            <div class="admin-user-summary-heading">
                <h2>
                    <?= moderation_e(
                        $user['display_name']
                        ?: $user['username']
                        ?: 'Unnamed account'
                    ) ?>
                </h2>

                <?php if (!empty($user['anonymized_at'])): ?>
                    <span class="admin-status-pill">Anonymized</span>
                <?php else: ?>
                    <span class="admin-status-pill">
                        <?= moderation_e(ucfirst((string) $user['status'])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <p>
                User #<?= (int) $user['id'] ?>

                <?php if (
                    empty($user['anonymized_at']) &&
                    !empty($user['username'])
                ): ?>
                    · @<?= moderation_e($user['username']) ?>
                <?php endif; ?>
            </p>

            <?php if (empty($user['anonymized_at'])): ?>
                <p><?= moderation_e($user['email']) ?></p>
            <?php else: ?>
                <p>
                    Personal account data removed
                    <?= moderation_e((string) $user['anonymized_at']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (
        empty($user['anonymized_at']) &&
        !empty($user['username'])
    ): ?>
        <a
            class="admin-button"
            href="https://llamascout.com/<?= rawurlencode((string) $user['username']) ?>"
            target="_blank"
            rel="noopener"
        >
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            Public profile
        </a>
    <?php endif; ?>

</section>


<section class="admin-user-stat-grid">

    <div>
        <span>Contribution Points</span>
        <strong><?= number_format((int) $userStats['points']) ?></strong>
    </div>

    <div>
        <span>Contributions</span>
        <strong><?= number_format((int) $userStats['contributions']) ?></strong>
    </div>

    <div>
        <span>Places Added</span>
        <strong><?= number_format((int) $userStats['places_added']) ?></strong>
    </div>

    <div>
        <span>Updates</span>
        <strong><?= number_format((int) $userStats['updates']) ?></strong>
    </div>

    <div>
        <span>Badges</span>
        <strong><?= number_format((int) $userStats['badges']) ?></strong>
    </div>

    <div>
        <span>Reports</span>
        <strong><?= number_format((int) $userStats['reports']) ?></strong>
    </div>

</section>


<div class="admin-user-detail-grid">

    <div class="admin-user-detail-main">

        <?php if (empty($user['anonymized_at'])): ?>

            <section class="admin-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Account</p>
                        <h2>Identity + Status</h2>
                    </div>
                </header>

                <form class="admin-user-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <input type="hidden" name="admin_user_action" value="save-account">

                    <div class="admin-user-form-grid">

                        <label>
                            <span>Display name</span>
                            <input
                                type="text"
                                name="display_name"
                                maxlength="100"
                                value="<?= moderation_e((string) ($user['display_name'] ?? '')) ?>"
                            >
                        </label>

                        <label>
                            <span>Username</span>
                            <input
                                type="text"
                                name="username"
                                maxlength="16"
                                value="<?= moderation_e((string) ($user['username'] ?? '')) ?>"
                            >
                        </label>

                        <label class="is-wide">
                            <span>Email</span>
                            <input
                                type="email"
                                name="email"
                                value="<?= moderation_e((string) $user['email']) ?>"
                                required
                            >
                        </label>

                        <label>
                            <span>Timezone</span>
                            <input
                                type="text"
                                name="timezone"
                                value="<?= moderation_e((string) $user['timezone']) ?>"
                            >
                        </label>

                        <label>
                            <span>Account status</span>
                            <select name="status">
                                <?php foreach (['active','pending','suspended','disabled'] as $option): ?>
                                    <option
                                        value="<?= moderation_e($option) ?>"
                                        <?= (string) $user['status'] === $option ? 'selected' : '' ?>
                                    >
                                        <?= moderation_e(ucfirst($option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                    </div>

                    <div class="admin-user-form-actions">
                        <button class="admin-button" type="submit">
                            Save account
                        </button>
                    </div>
                </form>

            </section>


            <section class="admin-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Permissions</p>
                        <h2>Roles</h2>
                    </div>

                    <?php if (!$actorIsOwner): ?>
                        <span>Owner access required to edit</span>
                    <?php endif; ?>
                </header>

                <form class="admin-user-role-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <input type="hidden" name="admin_user_action" value="save-roles">

                    <?php foreach (
                        [
                            'member' => ['Member', 'Normal member account access.'],
                            'scout' => ['Llama Scout', 'Scout workflows and Scout identity.'],
                            'admin' => ['Administrator', 'Routine site administration and moderation.'],
                            'owner' => ['Owner', 'Highest trust level and destructive account operations.'],
                        ]
                        as $slug => [$name, $description]
                    ): ?>
                        <label class="admin-user-role-option">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="<?= moderation_e($slug) ?>"
                                <?= in_array($slug, $targetRoles, true) ? 'checked' : '' ?>
                                <?= !$actorIsOwner ? 'disabled' : '' ?>
                            >

                            <span>
                                <strong><?= moderation_e($name) ?></strong>
                                <small><?= moderation_e($description) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <?php if ($actorIsOwner): ?>
                        <div class="admin-user-form-actions">
                            <button class="admin-button" type="submit">
                                Save roles
                            </button>
                        </div>
                    <?php endif; ?>
                </form>

            </section>


            <section class="admin-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Membership</p>
                        <h2>Current Access</h2>
                    </div>
                </header>

                <dl class="admin-user-definition-list">
                    <div>
                        <dt>Status</dt>
                        <dd><?= moderation_e((string) $user['membership_status']) ?></dd>
                    </div>
                    <div>
                        <dt>Billing interval</dt>
                        <dd><?= moderation_e((string) ($user['membership_interval'] ?: 'None')) ?></dd>
                    </div>
                    <div>
                        <dt>Started</dt>
                        <dd><?= moderation_e((string) ($user['membership_started_at'] ?: 'Not applicable')) ?></dd>
                    </div>
                    <div>
                        <dt>Ends / renews</dt>
                        <dd><?= moderation_e((string) ($user['membership_ends_at'] ?: 'Not applicable')) ?></dd>
                    </div>
                    <div>
                        <dt>Stripe customer</dt>
                        <dd><?= moderation_e((string) ($user['stripe_customer_id'] ?: 'None')) ?></dd>
                    </div>
                </dl>

            </section>

        <?php endif; ?>


        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>History</p>
                    <h2>Recent Contributions</h2>
                </div>
            </header>

            <?php if (!$contributions): ?>

                <div class="admin-empty-state">
                    <p>No contribution history yet.</p>
                </div>

            <?php else: ?>

                <div class="admin-user-history-list">

                    <?php foreach ($contributions as $contribution): ?>
                        <a
                            href="https://llamascout.com/place.php?id=<?= (int) $contribution['place_id'] ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <span>
                                <strong><?= moderation_e((string) $contribution['place_name']) ?></strong>
                                <small>
                                    <?= moderation_e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string) $contribution['contribution_type']
                                            )
                                        )
                                    ) ?>
                                </small>
                            </span>

                            <span>
                                +<?= number_format((int) $contribution['points_awarded']) ?> pts
                            </span>
                        </a>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </div>


    <aside class="admin-user-detail-side">

        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Account Facts</p>
                    <h2>Overview</h2>
                </div>
            </header>

            <dl class="admin-user-definition-list">
                <div>
                    <dt>Created</dt>
                    <dd><?= moderation_e((string) $user['created_at']) ?></dd>
                </div>
                <div>
                    <dt>Email verified</dt>
                    <dd><?= moderation_e((string) ($user['email_verified_at'] ?: 'No')) ?></dd>
                </div>
                <div>
                    <dt>Last login</dt>
                    <dd><?= moderation_e((string) ($user['last_login_at'] ?: 'Never')) ?></dd>
                </div>
                <div>
                    <dt>Active sessions</dt>
                    <dd><?= number_format((int) $userStats['sessions']) ?></dd>
                </div>
                <div>
                    <dt>Saved Places</dt>
                    <dd><?= number_format((int) $userStats['saved_places']) ?></dd>
                </div>
            </dl>

        </section>


        <?php if (
            empty($user['anonymized_at']) &&
            $userId !== $actorUserId
        ): ?>

            <section class="admin-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Security</p>
                        <h2>Sessions</h2>
                    </div>
                </header>

                <form class="admin-user-action-box" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <input type="hidden" name="admin_user_action" value="force-logout">

                    <p>
                        Immediately revoke browser sessions and remember-me tokens.
                    </p>

                    <button class="admin-button" type="submit">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        Sign out everywhere
                    </button>
                </form>

            </section>

        <?php endif; ?>


        <?php if (
            $actorIsOwner &&
            empty($user['anonymized_at']) &&
            $userId !== $actorUserId &&
            !$targetIsOwner
        ): ?>

            <section class="admin-panel admin-danger-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Destructive Action</p>
                        <h2>Anonymize Account</h2>
                    </div>
                </header>

                <form class="admin-user-action-box" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <input type="hidden" name="admin_user_action" value="anonymize">

                    <p>
                        Removes personal profile and authentication data while
                        keeping the member's approved contribution and Place history.
                    </p>

                    <label>
                        <span>Reason</span>
                        <textarea
                            name="reason"
                            rows="3"
                            required
                            placeholder="Account deletion request, privacy request, etc."
                        ></textarea>
                    </label>

                    <label>
                        <span>Type ANONYMIZE to confirm</span>
                        <input
                            type="text"
                            name="confirmation"
                            autocomplete="off"
                            required
                        >
                    </label>

                    <button class="admin-danger-button" type="submit">
                        Anonymize account
                    </button>
                </form>

            </section>

        <?php endif; ?>


        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Audit Trail</p>
                    <h2>Admin Activity</h2>
                </div>
            </header>

            <?php if (!$auditHistory): ?>

                <div class="admin-empty-state">
                    <p>No administrative changes recorded yet.</p>
                </div>

            <?php else: ?>

                <div class="admin-user-audit-list">
                    <?php foreach ($auditHistory as $entry): ?>
                        <div>
                            <strong><?= moderation_e((string) $entry['summary']) ?></strong>
                            <span>
                                <?= moderation_e((string) $entry['actor_name']) ?>
                                · <?= moderation_e((string) $entry['created_at']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>

    </aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>

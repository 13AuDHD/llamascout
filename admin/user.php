<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/memberships.php';
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
                llama_invalidate_user_authentication(
                    $db,
                    $userId
                );

                admin_users_audit(
                    $db,
                    $actorUserId,
                    $userId,
                    'user.sessions_revoked',
                    'Invalidated all authenticated sessions and remember-me tokens.'
                );

                $notice =
                    'Account signed out everywhere. Existing authenticated browsers '
                    . 'will be rejected on their next request.';
            } elseif ($action === 'verify-support-pin') {
                $supportPhone =
                    llama_support_phone_number(
                        $db,
                        $userId
                    );

                if ($supportPhone === null) {
                    throw new RuntimeException(
                        'This account does not currently have a phone number on file.'
                    );
                }

                if (
                    !llama_support_pin_verify(
                        $db,
                        $userId,
                        (string) ($_POST['support_pin'] ?? '')
                    )
                ) {
                    throw new InvalidArgumentException(
                        'That support PIN is not valid.'
                    );
                }

                admin_users_audit(
                    $db,
                    $actorUserId,
                    $userId,
                    'user.support_identity_verified',
                    'Verified the caller using the member-created support PIN.'
                );

                $notice =
                    'Support PIN verified. Caller identity matched the account.';
            } elseif ($action === 'reset-mfa-support') {
                if ($userId === $actorUserId) {
                    throw new RuntimeException(
                        'You cannot use the support reset on your own account.'
                    );
                }

                $targetIsPrivileged =
                    user_has_role(
                        'owner',
                        $userId
                    )
                    ||
                    user_has_role(
                        'admin',
                        $userId
                    );

                if (
                    $targetIsPrivileged
                    &&
                    !$actorIsOwner
                ) {
                    throw new RuntimeException(
                        'Only an Owner can reset MFA for an Administrator or Owner account.'
                    );
                }

                $supportPhone =
                    llama_support_phone_number(
                        $db,
                        $userId
                    );

                if ($supportPhone === null) {
                    throw new RuntimeException(
                        'This account does not currently have a phone number on file.'
                    );
                }

                if (
                    !llama_mfa_is_enabled(
                        $userId,
                        $db
                    )
                ) {
                    throw new RuntimeException(
                        'Multi-factor authentication is not currently enabled for this account.'
                    );
                }

                llama_support_pin_reset_mfa(
                    $db,
                    $userId,
                    (string) ($_POST['support_pin'] ?? '')
                );

                admin_users_audit(
                    $db,
                    $actorUserId,
                    $userId,
                    'user.mfa_support_reset',
                    'Reset MFA after successful phone support PIN verification.'
                );

                $notice =
                    'MFA was reset. The support PIN remains valid for future identity verification, '
                    . 'but its one-time MFA reset allowance has been used.';
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
            $reference = llama_log_caught_exception(
                $exception,
                'admin.user_action',
                ['target_user_id' => $userId, 'action' => $action],
                [InvalidArgumentException::class, RuntimeException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The user action could not be completed.',
                    $reference
                );
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

$targetIsAdmin = in_array(
    'admin',
    $targetRoles,
    true
);

$supportPhoneNumber =
    trim(
        (string) (
            $user['phone_number']
            ?? ''
        )
    );

$supportPinRecord =
    llama_support_pin_record(
        $db,
        $userId
    );

$supportPinIsSet =
    is_array($supportPinRecord)
    &&
    !empty(
        $supportPinRecord['pin_hash']
    );

$supportMfaEnabled =
    llama_mfa_is_enabled(
        $userId,
        $db
    );

$supportMfaResetAvailable =
    $supportPinIsSet
    &&
    empty(
        $supportPinRecord['mfa_reset_used_at']
    );

$supportResetPermitted =
    $userId !== $actorUserId
    &&
    (
        !($targetIsOwner || $targetIsAdmin)
        ||
        $actorIsOwner
    );


/*
 * Current membership/access source.
 *
 * Complimentary access lives in membership_grants and should not
 * be mistaken for a missing Stripe subscription.
 */
$complimentaryGrant =
    llama_active_complimentary_grant(
        $db,
        $userId
    );

$membershipStatus =
    strtolower(
        trim(
            (string) (
                $user['membership_status']
                ?? 'none'
            )
        )
    );

$hasPaidMembership =
    !$complimentaryGrant
    &&
    !in_array(
        $membershipStatus,
        [
            '',
            'none',
            'free',
        ],
        true
    );

$membershipDaysLeft =
    static function (
        ?string $endsAt
    ): string {
        $endsAt =
            trim(
                (string) $endsAt
            );

        if ($endsAt === '') {
            return '';
        }

        try {
            $viewerTimezone =
                new DateTimeZone(
                    llama_viewer_timezone()
                );

            /*
             * Remaining time is based on calendar days from today
             * through the end of the current access period.
             */
            $today =
                new DateTimeImmutable(
                    'today',
                    $viewerTimezone
                );

            $end =
                (
                    new DateTimeImmutable(
                        $endsAt,
                        new DateTimeZone('UTC')
                    )
                )
                ->setTimezone(
                    $viewerTimezone
                )
                ->setTime(
                    0,
                    0,
                    0
                );

            $days =
                (int) $today
                    ->diff(
                        $end
                    )
                    ->format('%r%a');

            if ($days < 0) {
                return 'Ended';
            }

            if ($days === 0) {
                return 'Ends today';
            }

            return
                number_format($days)
                . ' day'
                . ($days === 1 ? '' : 's')
                . ' remaining';
        } catch (Throwable) {
            return '';
        }
    };

$userStats = admin_users_stats(
    $db,
    $userId
);

/*
 * Presence is intentionally approximate.
 * A user is considered active when an authenticated request has
 * touched users.last_seen_at within the last five minutes.
 */
$lastSeenAt = trim(
    (string) (
        $user['last_seen_at']
        ?? ''
    )
);

$isRecentlyActive = false;
$lastSeenDisplay = 'Never';

if ($lastSeenAt !== '') {
    try {
        $lastSeenUtc = new DateTimeImmutable(
            $lastSeenAt,
            new DateTimeZone('UTC')
        );

        $isRecentlyActive =
            $lastSeenUtc->getTimestamp()
            >= (time() - 300);

        $lastSeenDisplay =
            llama_format_viewer_datetime(
                $lastSeenAt
            );
    } catch (Throwable) {
        $lastSeenDisplay = 'Unavailable';
    }
}

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
                    Â· @<?= moderation_e($user['username']) ?>
                <?php endif; ?>
            </p>

            <?php if (empty($user['anonymized_at'])): ?>
                <p><?= moderation_e($user['email']) ?></p>
            <?php else: ?>
                <p>
                    Personal account data removed
                    <?= moderation_e(
                        llama_format_viewer_datetime(
                            (string) $user['anonymized_at']
                        )
                    ) ?>
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

    <div class="admin-user-stat-card">
        <span>Points</span>
        <strong><?= number_format((int) $userStats['points']) ?></strong>
    </div>

    <div class="admin-user-stat-card">
        <span>Contributions</span>
        <strong><?= number_format((int) $userStats['contributions']) ?></strong>
    </div>

    <div class="admin-user-stat-card">
        <span>Places Added</span>
        <strong><?= number_format((int) $userStats['places_added']) ?></strong>
    </div>

    <div class="admin-user-stat-card">
        <span>Updates</span>
        <strong><?= number_format((int) $userStats['updates']) ?></strong>
    </div>

    <a
        class="admin-user-stat-card admin-user-stat-card-link"
        href="/badges.php?user_id=<?= (int) $userId ?>"
        aria-label="Manage badges for <?= moderation_e(
            $user['display_name']
            ?: $user['username']
            ?: 'this user'
        ) ?>"
    >
        <span>Badges</span>
        <strong><?= number_format((int) $userStats['badges']) ?></strong>
    </a>

    <div class="admin-user-stat-card">
        <span>Reports</span>
        <strong><?= number_format((int) $userStats['reports']) ?></strong>
    </div>

</section>


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


<?php if (empty($user['anonymized_at'])): ?>

    <div class="admin-user-half-grid">

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

                <?php if ($complimentaryGrant): ?>
                    <?php
                    $complimentaryEndsAt =
                        (string) (
                            $complimentaryGrant['ends_at']
                            ?? ''
                        );

                    $complimentaryRemaining =
                        $membershipDaysLeft(
                            $complimentaryEndsAt
                        );

                    $grantor =
                        trim(
                            (string) (
                                $complimentaryGrant['granted_by_display_name']
                                ?: $complimentaryGrant['granted_by_username']
                                ?: ''
                            )
                        );
                    ?>

                    <div>
                        <dt>Access type</dt>
                        <dd>
                            <span class="admin-user-access-pill is-complimentary">
                                Complimentary
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt>Status</dt>
                        <dd>Active</dd>
                    </div>

                    <div>
                        <dt>Started</dt>
                        <dd>
                            <?= moderation_e(
                                llama_format_viewer_datetime(
                                    (string) $complimentaryGrant['starts_at']
                                )
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Good through</dt>
                        <dd class="admin-user-access-date">
                            <span>
                                <?= moderation_e(
                                    llama_format_viewer_datetime(
                                        $complimentaryEndsAt
                                    )
                                ) ?>
                            </span>

                            <?php if ($complimentaryRemaining !== ''): ?>
                                <small class="admin-user-access-remaining">
                                    <?= moderation_e($complimentaryRemaining) ?>
                                </small>
                            <?php endif; ?>
                        </dd>
                    </div>

                    <?php if ($grantor !== ''): ?>
                        <div>
                            <dt>Granted by</dt>
                            <dd><?= moderation_e($grantor) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($complimentaryGrant['reason'])): ?>
                        <div>
                            <dt>Reason</dt>
                            <dd>
                                <?= moderation_e(
                                    (string) $complimentaryGrant['reason']
                                ) ?>
                            </dd>
                        </div>
                    <?php endif; ?>

                    <div>
                        <dt>Stripe customer</dt>
                        <dd>
                            <?= moderation_e(
                                (string) (
                                    $user['stripe_customer_id']
                                    ?: 'None'
                                )
                            ) ?>
                        </dd>
                    </div>

                <?php elseif ($hasPaidMembership): ?>
                    <?php
                    $paidEndsAt =
                        trim(
                            (string) (
                                $user['membership_ends_at']
                                ?? ''
                            )
                        );

                    $paidRemaining =
                        $membershipDaysLeft(
                            $paidEndsAt
                        );

                    $paidEndLabel =
                        in_array(
                            $membershipStatus,
                            [
                                'active',
                                'trialing',
                            ],
                            true
                        )
                            ? 'Renews'
                            : 'Ends';
                    ?>

                    <div>
                        <dt>Access type</dt>
                        <dd>
                            <span class="admin-user-access-pill is-paid">
                                Paid membership
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt>Status</dt>
                        <dd>
                            <?= moderation_e(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $membershipStatus
                                    )
                                )
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Billing interval</dt>
                        <dd>
                            <?= moderation_e(
                                (string) (
                                    $user['membership_interval']
                                    ?: 'None'
                                )
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Started</dt>
                        <dd>
                            <?= !empty($user['membership_started_at'])
                                ? moderation_e(
                                    llama_format_viewer_datetime(
                                        (string) $user['membership_started_at']
                                    )
                                )
                                : 'Not available' ?>
                        </dd>
                    </div>

                    <?php if ($paidEndsAt !== ''): ?>
                        <div>
                            <dt><?= moderation_e($paidEndLabel) ?></dt>
                            <dd class="admin-user-access-date">
                                <span>
                                    <?= moderation_e(
                                        llama_format_viewer_datetime(
                                            $paidEndsAt
                                        )
                                    ) ?>
                                </span>

                                <?php if ($paidRemaining !== ''): ?>
                                    <small class="admin-user-access-remaining">
                                        <?= moderation_e($paidRemaining) ?>
                                    </small>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>

                    <div>
                        <dt>Stripe customer</dt>
                        <dd>
                            <?= moderation_e(
                                (string) (
                                    $user['stripe_customer_id']
                                    ?: 'None'
                                )
                            ) ?>
                        </dd>
                    </div>

                <?php else: ?>

                    <div>
                        <dt>Access type</dt>
                        <dd>
                            <span class="admin-user-access-pill is-free">
                                Free
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt>Status</dt>
                        <dd>No paid access</dd>
                    </div>

                <?php endif; ?>

            </dl>

        </section>

    </div>

<?php endif; ?>


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
            <dd>
                <?= moderation_e(
                    llama_format_viewer_datetime(
                        (string) $user['created_at']
                    )
                ) ?>
            </dd>
        </div>
        <div>
            <dt>Email verified</dt>
            <dd>
                <?= !empty($user['email_verified_at'])
                    ? moderation_e(
                        llama_format_viewer_datetime(
                            (string) $user['email_verified_at']
                        )
                    )
                    : 'No' ?>
            </dd>
        </div>
        <div>
            <dt>Last login</dt>
            <dd>
                <?= !empty($user['last_login_at'])
                    ? moderation_e(
                        llama_format_viewer_datetime(
                            (string) $user['last_login_at']
                        )
                    )
                    : 'Never' ?>
            </dd>
        </div>
        <div>
            <dt>Saved Places</dt>
            <dd><?= number_format((int) $userStats['saved_places']) ?></dd>
        </div>
    </dl>

</section>


<?php if (empty($user['anonymized_at'])): ?>

    <div class="admin-user-half-grid">

        <?php if ($userId !== $actorUserId): ?>

            <section class="admin-panel">

                <header class="admin-panel-header">
                    <div>
                        <p>Security</p>
                        <h2>Sessions</h2>
                    </div>

                    <span
                        class="admin-user-session-status <?= $isRecentlyActive ? 'is-active' : 'is-inactive' ?>"
                    >
                        <i
                            class="fa-solid <?= $isRecentlyActive ? 'fa-circle-check' : 'fa-circle-xmark' ?>"
                            aria-hidden="true"
                        ></i>

                        <?= $isRecentlyActive
                            ? 'Active'
                            : 'Not active' ?>
                    </span>
                </header>

                <form class="admin-user-action-box" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <input type="hidden" name="admin_user_action" value="force-logout">

                    <div class="admin-user-session-count">
                        <span>Last seen</span>
                        <strong><?= moderation_e($lastSeenDisplay) ?></strong>
                    </div>

                    <p>
                        Immediately revoke browser sessions and remember-me tokens.
                    </p>

                    <button class="admin-button" type="submit">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        Sign out everywhere
                    </button>
                </form>

            </section>

        <?php else: ?>

            <section class="admin-panel">
                <header class="admin-panel-header">
                    <div>
                        <p>Security</p>
                        <h2>Sessions</h2>
                    </div>

                    <span
                        class="admin-user-session-status <?= $isRecentlyActive ? 'is-active' : 'is-inactive' ?>"
                    >
                        <i
                            class="fa-solid <?= $isRecentlyActive ? 'fa-circle-check' : 'fa-circle-xmark' ?>"
                            aria-hidden="true"
                        ></i>

                        <?= $isRecentlyActive
                            ? 'Active'
                            : 'Not active' ?>
                    </span>
                </header>

                <div class="admin-user-action-box">
                    <div class="admin-user-session-count">
                        <span>Last seen</span>
                        <strong><?= moderation_e($lastSeenDisplay) ?></strong>
                    </div>

                    <p>
                        Session controls are unavailable for your own Admin account.
                    </p>
                </div>
            </section>

        <?php endif; ?>


        <?php require __DIR__ . '/_user-email-verification-panel.php'; ?>

    </div>

<?php endif; ?>


<?php if (empty($user['anonymized_at'])): ?>

    <section class="admin-panel admin-user-support-panel">

        <header class="admin-panel-header">
            <div>
                <p>Account Recovery</p>
                <h2>Support Verification</h2>
            </div>

            <span
                class="admin-user-support-status <?= $supportPinIsSet ? 'is-ready' : 'is-unavailable' ?>"
            >
                <i
                    class="fa-solid <?= $supportPinIsSet ? 'fa-circle-check' : 'fa-circle-xmark' ?>"
                    aria-hidden="true"
                ></i>
                <?= $supportPinIsSet ? 'Support PIN set' : 'Not configured' ?>
            </span>
        </header>

        <div class="admin-user-support-body">

            <dl class="admin-user-definition-list">
                <div>
                    <dt>Phone</dt>
                    <dd>
                        <?= $supportPhoneNumber !== ''
                            ? moderation_e($supportPhoneNumber)
                            : 'Not provided' ?>
                    </dd>
                </div>

                <div>
                    <dt>MFA</dt>
                    <dd>
                        <?= $supportMfaEnabled
                            ? 'Enabled'
                            : 'Not enabled' ?>
                    </dd>
                </div>

                <div>
                    <dt>MFA reset</dt>
                    <dd>
                        <?php if (!$supportPinIsSet): ?>
                            Not configured
                        <?php elseif ($supportMfaResetAvailable): ?>
                            Available
                        <?php else: ?>
                            Used
                        <?php endif; ?>
                    </dd>
                </div>

                <div>
                    <dt>Last support verification</dt>
                    <dd>
                        <?= !empty($supportPinRecord['last_verified_at'])
                            ? moderation_e(
                                llama_format_viewer_datetime(
                                    (string) $supportPinRecord['last_verified_at']
                                )
                            )
                            : 'Never' ?>
                    </dd>
                </div>
            </dl>

            <?php if (!$supportPinIsSet): ?>

                <p class="admin-user-support-note">
                    This member has not created a Support PIN.
                </p>

            <?php elseif ($supportPhoneNumber === ''): ?>

                <p class="admin-user-support-note">
                    A Support PIN exists, but there is no phone number on the account.
                    Support verification and MFA reset are unavailable until the member
                    adds a phone number.
                </p>

            <?php else: ?>

                <p class="admin-user-support-note">
                    Call the phone number on the account and ask the member to read their
                    8-digit Support PIN. The PIN may verify support calls repeatedly.
                    Its MFA reset privilege can be used only once.
                </p>

                <form
                    class="admin-user-support-form"
                    method="post"
                    autocomplete="off"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $userId ?>"
                    >

                    <label>
                        <span>Support PIN</span>
                        <input
                            type="password"
                            name="support_pin"
                            inputmode="numeric"
                            autocomplete="off"
                            pattern="[0-9]{8}"
                            maxlength="8"
                            required
                        >
                    </label>

                    <div class="admin-user-support-actions">
                        <button
                            class="admin-button"
                            type="submit"
                            name="admin_user_action"
                            value="verify-support-pin"
                        >
                            Verify caller
                        </button>

                        <button
                            class="admin-danger-button"
                            type="submit"
                            name="admin_user_action"
                            value="reset-mfa-support"
                            <?= (
                                !$supportMfaEnabled
                                ||
                                !$supportMfaResetAvailable
                                ||
                                !$supportResetPermitted
                            ) ? 'disabled' : '' ?>
                        >
                            Reset MFA
                        </button>
                    </div>
                </form>

                <?php if (
                    ($targetIsOwner || $targetIsAdmin)
                    &&
                    !$actorIsOwner
                ): ?>
                    <p class="admin-user-support-note">
                        Owner access is required to reset MFA for privileged accounts.
                    </p>
                <?php elseif (!$supportMfaResetAvailable): ?>
                    <p class="admin-user-support-note">
                        This Support PIN has already used its one-time MFA reset.
                        The member must replace the PIN while fully authenticated to
                        create a new reset allowance.
                    </p>
                <?php endif; ?>

            <?php endif; ?>

        </div>

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
                        Â·
                        <?= moderation_e(
                            llama_format_viewer_datetime(
                                (string) $entry['created_at']
                            )
                        ) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</section>


<?php require __DIR__ . '/_footer.php'; ?>

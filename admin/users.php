<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$membership = trim((string) ($_GET['membership'] ?? ''));

/*
 * Paid can still use the existing database-level filter.
 *
 * Complimentary and Free need a little more context because a
 * complimentary membership can come from either users.membership_status
 * or an active membership_grants row.
 */
$listMembershipFilter =
    $membership === 'paid'
        ? 'paid'
        : '';

$users = admin_users_list(
    $db,
    $search,
    $status,
    $role,
    $listMembershipFilter
);


/*
 * =========================================================
 * MEMBERSHIP DISPLAY STATE
 * =========================================================
 *
 * Keep the Users table aligned with the same complimentary-grant
 * rules used by member access. This lets Basecamp distinguish:
 *
 *   Paid
 *   Complimentary
 *   Free
 *
 * rather than grouping complimentary access under Free.
 */

$activeComplimentaryGrantUserIds = [];

if ($users) {
    $userIds =
        array_values(
            array_filter(
                array_map(
                    static fn (array $user): int =>
                        (int) ($user['id'] ?? 0),
                    $users
                ),
                static fn (int $id): bool =>
                    $id > 0
            )
        );

    if ($userIds) {
        try {
            $placeholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($userIds),
                        '?'
                    )
                );

            $grantStmt =
                $db->prepare(
                    'SELECT DISTINCT user_id
                     FROM membership_grants
                     WHERE grant_type = "complimentary"
                       AND revoked_at IS NULL
                       AND starts_at <= UTC_TIMESTAMP()
                       AND ends_at >= UTC_TIMESTAMP()
                       AND user_id IN (' . $placeholders . ')'
                );

            $grantStmt->execute($userIds);

            foreach (
                $grantStmt->fetchAll(PDO::FETCH_COLUMN)
                ?: []
                as $grantedUserId
            ) {
                $activeComplimentaryGrantUserIds[
                    (int) $grantedUserId
                ] = true;
            }
        } catch (Throwable $exception) {
            /*
             * The Users page should remain usable if membership_grants
             * is temporarily unavailable. users.membership_status can
             * still identify direct complimentary memberships.
             */
            $activeComplimentaryGrantUserIds = [];
        }
    }
}

$membershipKind =
    static function (
        array $user
    ) use (
        $activeComplimentaryGrantUserIds
    ): string {
        $userId =
            (int) (
                $user['id']
                ?? 0
            );

        $membershipStatus =
            strtolower(
                trim(
                    (string) (
                        $user['membership_status']
                        ?? ''
                    )
                )
            );

        $membershipEndsAt =
            trim(
                (string) (
                    $user['membership_ends_at']
                    ?? ''
                )
            );

        $membershipStillCurrent =
            $membershipEndsAt === '';

        if (
            !$membershipStillCurrent
            && function_exists(
                'llama_access_utc_timestamp'
            )
        ) {
            $endsTimestamp =
                llama_access_utc_timestamp(
                    $membershipEndsAt
                );

            $membershipStillCurrent =
                $endsTimestamp !== null
                && $endsTimestamp >= time();
        } elseif (
            !$membershipStillCurrent
        ) {
            $endsTimestamp =
                strtotime(
                    $membershipEndsAt
                    . ' UTC'
                );

            $membershipStillCurrent =
                $endsTimestamp !== false
                && $endsTimestamp >= time();
        }

        if (
            in_array(
                $membershipStatus,
                [
                    'active',
                    'trialing',
                ],
                true
            )
            && $membershipStillCurrent
        ) {
            return 'paid';
        }

        if (
            (
                $membershipStatus
                === 'complimentary'
                && $membershipStillCurrent
            )
            || isset(
                $activeComplimentaryGrantUserIds[
                    $userId
                ]
            )
        ) {
            return 'complimentary';
        }

        return 'free';
    };


/*
 * Complimentary and truly Free are filtered after grant state has
 * been resolved. Search, role, and account-status filters were
 * already applied by admin_users_list().
 */
if (
    in_array(
        $membership,
        [
            'complimentary',
            'free',
        ],
        true
    )
) {
    $users =
        array_values(
            array_filter(
                $users,
                static function (
                    array $user
                ) use (
                    $membership,
                    $membershipKind
                ): bool {
                    return
                        $membershipKind($user)
                        === $membership;
                }
            )
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

$adminPageTitle = 'Users';
$adminPageEyebrow = 'People';
$adminActiveNav = 'users';

$adminUsesSplitCss = true;

$adminPageStyles = [
    'users.css',
];

$adminFeatureStyles = [];

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-user-filter-panel">

    <form
        class="admin-user-filters"
        method="get"
        action="/users.php"
    >
        <label class="admin-user-search">
            <span>Search accounts</span>

            <div>
                <i
                    class="fa-solid fa-magnifying-glass"
                    aria-hidden="true"
                ></i>

                <input
                    type="search"
                    name="q"
                    value="<?= moderation_e($search) ?>"
                    placeholder="Name, username, email, or user ID"
                >
            </div>
        </label>

        <label>
            <span>Status</span>

            <select name="status">
                <option value="">All statuses</option>

                <?php foreach (
                    [
                        'active',
                        'pending',
                        'suspended',
                        'disabled',
                    ]
                    as $option
                ): ?>
                    <option
                        value="<?= moderation_e($option) ?>"
                        <?= $status === $option
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e(
                            ucfirst($option)
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Role</span>

            <select name="role">
                <option value="">All roles</option>
                <option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Owner</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="scout" <?= $role === 'scout' ? 'selected' : '' ?>>Scout</option>
                <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option>
            </select>
        </label>

        <label>
            <span>Membership</span>

            <select name="membership">
                <option value="">All memberships</option>
                <option value="paid" <?= $membership === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="complimentary" <?= $membership === 'complimentary' ? 'selected' : '' ?>>Complimentary</option>
                <option value="free" <?= $membership === 'free' ? 'selected' : '' ?>>Free</option>
            </select>
        </label>

        <div class="admin-user-filter-actions">
            <button
                class="admin-button"
                type="submit"
            >
                Filter
            </button>

            <a
                class="admin-button is-muted"
                href="/users.php"
            >
                Clear
            </a>
        </div>
    </form>

</section>


<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>Accounts</p>
            <h2>
                <?= number_format(count($users)) ?>
                shown
            </h2>
        </div>

        <span>Up to 250 accounts</span>
    </header>

    <?php if (!$users): ?>

        <div class="admin-empty-state">
            <i
                class="fa-solid fa-user-slash"
                aria-hidden="true"
            ></i>

            <h3>No accounts found.</h3>

            <p>
                Try changing the filters or search term.
            </p>
        </div>

    <?php else: ?>

        <div class="admin-user-table-wrap">

            <table class="admin-user-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Membership</th>
                        <th>Contributions</th>
                        <th>Last login</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $user): ?>
                <?php
                $roles = array_values(
                    array_filter(
                        explode(
                            ',',
                            (string) (
                                $user['role_slugs']
                                ?? ''
                            )
                        )
                    )
                );

                $userMembershipKind =
                    $membershipKind(
                        $user
                    );
                ?>

                <tr>
                    <td>
                        <div class="admin-user-identity">

                            <span class="admin-user-table-avatar">
                                <img
                                    src="<?= moderation_e(
                                        admin_user_avatar_src(
                                            (string) (
                                                $user[
                                                    'profile_image_src'
                                                ]
                                                ?? ''
                                            ),
                                            $siteUrl
                                        )
                                    ) ?>"
                                    alt=""
                                    loading="lazy"
                                >
                            </span>

                            <div class="admin-user-identity-copy">

                                <strong>
                                    <a
                                        class="admin-user-name-link"
                                        href="/user.php?id=<?= (int) $user['id'] ?>"
                                    >
                                        <?= moderation_e(
                                            $user['display_name']
                                            ?: $user['username']
                                            ?: 'Unnamed account'
                                        ) ?>
                                    </a>
                                </strong>

                                <?php if (
                                    !empty(
                                        $user['anonymized_at']
                                    )
                                ): ?>

                                    <span>
                                        Former member
                                        | #<?= (int) $user['id'] ?>
                                    </span>

                                <?php else: ?>

                                    <?php if (
                                        !empty($user['username'])
                                    ): ?>
                                        <span class="admin-user-username">
                                            @<?= moderation_e(
                                                $user['username']
                                            ) ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="admin-user-email">
                                        <?= moderation_e(
                                            $user['email']
                                        ) ?>
                                    </span>

                                    <span class="admin-user-mobile-login">
                                        Last login:
                                        <?= !empty($user['last_login_at'])
                                            ? moderation_e(
                                                llama_format_viewer_datetime(
                                                    (string) $user['last_login_at']
                                                )
                                            )
                                            : 'Never' ?>
                                    </span>

                                    <span class="admin-user-mobile-contributions">
                                        <?= number_format(
                                            (int) $user[
                                                'contribution_count'
                                            ]
                                        ) ?>
                                        contribution<?= (int) $user[
                                            'contribution_count'
                                        ] === 1 ? '' : 's' ?>
                                    </span>

                                <?php endif; ?>

                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="admin-role-chips">

                            <?php if (!$roles): ?>
                                <span>None</span>
                            <?php else: ?>

                                <?php foreach (
                                    $roles
                                    as $roleSlug
                                ): ?>
                                    <span>
                                        <?= moderation_e(
                                            ucfirst($roleSlug)
                                        ) ?>
                                    </span>
                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>
                    </td>

                    <td>
                        <?php if (
                            !empty($user['anonymized_at'])
                        ): ?>
                            <span class="admin-status-pill">
                                Anonymized
                            </span>
                        <?php else: ?>
                            <span class="admin-status-pill">
                                <?= moderation_e(
                                    ucfirst(
                                        (string) $user[
                                            'status'
                                        ]
                                    )
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (
                            $userMembershipKind
                            === 'paid'
                        ): ?>
                            <strong class="admin-membership-label is-paid">
                                Paid

                                <?php if (
                                    !empty(
                                        $user[
                                            'membership_interval'
                                        ]
                                    )
                                ): ?>
                                    |
                                    <?= moderation_e(
                                        ucfirst(
                                            (string) $user[
                                                'membership_interval'
                                            ]
                                        )
                                    ) ?>
                                <?php endif; ?>
                            </strong>

                        <?php elseif (
                            $userMembershipKind
                            === 'complimentary'
                        ): ?>
                            <strong class="admin-membership-label is-complimentary">
                                Complimentary
                            </strong>

                        <?php else: ?>
                            <span class="admin-membership-label is-free">
                                Free
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= number_format(
                            (int) $user[
                                'contribution_count'
                            ]
                        ) ?>
                    </td>

                    <td>
                        <span class="admin-table-muted">
                            <?= !empty($user['last_login_at'])
                                ? moderation_e(
                                    llama_format_viewer_datetime(
                                        (string) $user['last_login_at']
                                    )
                                )
                                : 'Never' ?>
                        </span>
                    </td>
                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>

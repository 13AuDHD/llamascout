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

$users = admin_users_list(
    $db,
    $search,
    $status,
    $role,
    $membership
);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
];

$adminPageTitle = 'Users';
$adminPageEyebrow = 'People';
$adminActiveNav = 'users';

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
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
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
                <?php foreach (['active','pending','suspended','disabled'] as $option): ?>
                    <option
                        value="<?= moderation_e($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= moderation_e(ucfirst($option)) ?>
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
                <option value="free" <?= $membership === 'free' ? 'selected' : '' ?>>Free</option>
            </select>
        </label>

        <div class="admin-user-filter-actions">
            <button class="admin-button" type="submit">
                Filter
            </button>

            <a class="admin-button is-muted" href="/users.php">
                Clear
            </a>
        </div>
    </form>

</section>


<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>Accounts</p>
            <h2><?= number_format(count($users)) ?> shown</h2>
        </div>
        <span>Up to 250 accounts</span>
    </header>

    <?php if (!$users): ?>

        <div class="admin-empty-state">
            <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
            <h3>No accounts found.</h3>
            <p>Try changing the filters or search term.</p>
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
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $roles = array_values(
                            array_filter(
                                explode(
                                    ',',
                                    (string) ($user['role_slugs'] ?? '')
                                )
                            )
                        );
                        ?>
                        <tr>
                            <td>
                                <div class="admin-user-identity">
                                    <span class="admin-user-table-avatar" aria-hidden="true">
                                        <?= moderation_e(
                                            strtoupper(
                                                substr(
                                                    (string) (
                                                        $user['display_name']
                                                        ?: $user['username']
                                                        ?: 'U'
                                                    ),
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>
                                    </span>

                                    <div>
                                        <strong>
                                            <?= moderation_e(
                                                $user['display_name']
                                                ?: $user['username']
                                                ?: 'Unnamed account'
                                            ) ?>
                                        </strong>

                                        <?php if (!empty($user['anonymized_at'])): ?>
                                            <span>Former member · #<?= (int) $user['id'] ?></span>
                                        <?php else: ?>
                                            <span>
                                                <?php if (!empty($user['username'])): ?>
                                                    @<?= moderation_e($user['username']) ?> ·
                                                <?php endif; ?>
                                                <?= moderation_e($user['email']) ?>
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
                                        <?php foreach ($roles as $roleSlug): ?>
                                            <span><?= moderation_e(ucfirst($roleSlug)) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <?php if (!empty($user['anonymized_at'])): ?>
                                    <span class="admin-status-pill">Anonymized</span>
                                <?php else: ?>
                                    <span class="admin-status-pill">
                                        <?= moderation_e(ucfirst((string) $user['status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    in_array(
                                        (string) $user['membership_status'],
                                        ['active','trialing'],
                                        true
                                    )
                                ): ?>
                                    <strong>
                                        Paid
                                        <?php if (!empty($user['membership_interval'])): ?>
                                            · <?= moderation_e(ucfirst((string) $user['membership_interval'])) ?>
                                        <?php endif; ?>
                                    </strong>
                                <?php else: ?>
                                    <span class="admin-table-muted">Free</span>
                                <?php endif; ?>
                            </td>

                            <td><?= number_format((int) $user['contribution_count']) ?></td>

                            <td>
                                <span class="admin-table-muted">
                                    <?= moderation_e(
                                        $user['last_login_at']
                                        ?: 'Never'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="admin-button"
                                    href="/user.php?id=<?= (int) $user['id'] ?>"
                                >
                                    Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-scouts.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$inviteNotice = '';
$inviteError = '';

if (
    $_SERVER['REQUEST_METHOD']
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
        $inviteError =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['scout_admin_action']
                        ?? ''
                    )
                );

            if ($action === 'invite') {
                $result =
                    llama_scout_admin_invite(
                        $db,
                        $actorUserId,
                        (int) (
                            $_POST['candidate_user_id']
                            ?? 0
                        )
                    );

                $candidate =
                    $result['candidate'];

                $inviteNotice =
                    !empty(
                        $result['mail_sent']
                    )
                        ? 'Scout invitation sent to ' .
                            (
                                $candidate['display_name']
                                ?: $candidate['username']
                                ?: $candidate['email']
                            ) .
                            '.'
                        : 'The Scout invitation was created, but the email could not be sent.';
            }
        } catch (Throwable $exception) {
            $inviteError =
                $exception->getMessage();
        }
    }
}

$eligibleCandidates =
    llama_scout_admin_eligible_candidates(
        $db
    );

$allScouts = admin_scouts_list($db);
$scoutStats = admin_scout_operational_stats($allScouts);

$q = trim((string) ($_GET['q'] ?? ''));
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));

if (!in_array($filter, ['all','attention','onboarding','active','master','inactive'], true)) {
    $filter = 'all';
}

$scouts = array_values(array_filter(
    $allScouts,
    static function (array $scout) use ($q, $filter): bool {
        $roles = explode(',', (string) ($scout['role_slugs'] ?? ''));
        $status = (string) ($scout['status'] ?? '');
        $isMaster = in_array('master-scout', $roles, true) || in_array('master_scout', $roles, true);

        $matchesFilter = match ($filter) {
            'attention' => in_array($status, ['application_submitted','pending_approval','inactive'], true),
            'onboarding' => in_array($status, ['invited','application_started','application_submitted','training','pending_approval'], true),
            'active' => $status === 'active',
            'master' => $isMaster,
            'inactive' => in_array($status, ['inactive','declined','removed'], true),
            default => true,
        };

        if (!$matchesFilter) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            $scout['display_name'] ?? '',
            $scout['username'] ?? '',
            $scout['email'] ?? '',
            $status,
        ]));

        return str_contains($haystack, strtolower($q));
    }
));

$stats = admin_dashboard_stats($db);
$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Scouts';
$adminPageEyebrow = 'People';
$adminActiveNav = 'scouts';

require __DIR__ . '/_header.php';
?>

<?php if ($inviteNotice !== ''): ?>
    <div class="admin-notice is-success">
        <?= moderation_e($inviteNotice) ?>
    </div>
<?php endif; ?>

<?php if ($inviteError !== ''): ?>
    <div class="admin-notice is-error">
        <?= moderation_e($inviteError) ?>
    </div>
<?php endif; ?>


<section class="admin-panel admin-scout-invite-panel">

<header class="admin-panel-header">
    <div>
        <p>Recruitment</p>
        <h2>Invite a Llama Scout</h2>
    </div>

    <span>
        <?= number_format(
            count($eligibleCandidates)
        ) ?>
        eligible
    </span>
</header>

<?php if (!$eligibleCandidates): ?>
    <div class="admin-empty-state">
        <p>
            There are no verified member accounts currently eligible
            for a new Scout invitation.
        </p>
    </div>
<?php else: ?>

<form
    class="admin-scout-invite-form"
    method="post"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="scout_admin_action"
        value="invite"
    >

    <label>
        <span>Member</span>

        <select
            name="candidate_user_id"
            required
        >
            <option value="">
                Choose an eligible member
            </option>

            <?php foreach ($eligibleCandidates as $candidate): ?>
                <option
                    value="<?= (int) $candidate['id'] ?>"
                >
                    <?= moderation_e(
                        (string) (
                            $candidate['display_name']
                            ?: $candidate['username']
                            ?: $candidate['email']
                        )
                    ) ?>
                    <?php if (!empty($candidate['username'])): ?>
                        (@<?= moderation_e((string) $candidate['username']) ?>)
                    <?php endif; ?>
                    <?php if ((string) ($candidate['scout_status'] ?? '') === 'invited'): ?>
                        · resend invitation
                    <?php elseif ((string) ($candidate['scout_status'] ?? '') === 'declined'): ?>
                        · previously declined
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div>
        <strong>30-day invitation</strong>
        <span>
            The member receives an email and an onboarding card
            in their Llama Scout account.
        </span>
    </div>

    <button
        class="admin-button"
        type="submit"
    >
        <i
            class="fa-solid fa-paper-plane"
            aria-hidden="true"
        ></i>
        Send Scout invitation
    </button>
</form>

<?php endif; ?>

</section>


<section class="admin-scout-stat-grid">
    <div><span>Total profiles</span><strong><?= number_format($scoutStats['total']) ?></strong></div>
    <div><span>Active Scouts</span><strong><?= number_format($scoutStats['active']) ?></strong></div>
    <div><span>Master Scouts</span><strong><?= number_format($scoutStats['master']) ?></strong></div>
    <div><span>Onboarding</span><strong><?= number_format($scoutStats['onboarding']) ?></strong></div>
    <div class="<?= $scoutStats['attention'] > 0 ? 'has-attention' : '' ?>"><span>Needs attention</span><strong><?= number_format($scoutStats['attention']) ?></strong></div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Scout Program</p>
            <h2><?= number_format(count($scouts)) ?> shown</h2>
        </div>
        <a class="admin-button" href="/policies.php">Scout policies</a>
    </header>

    <form class="admin-scout-filter-form" method="get">
        <label>
            <span>Search</span>
            <input type="search" name="q" value="<?= moderation_e($q) ?>" placeholder="Name, username, email">
        </label>
        <label>
            <span>View</span>
            <select name="filter">
                <?php foreach ([
                    'all' => 'All Scouts',
                    'attention' => 'Needs attention',
                    'onboarding' => 'Onboarding',
                    'active' => 'Active',
                    'master' => 'Master Scouts',
                    'inactive' => 'Inactive / former',
                ] as $value => $label): ?>
                    <option value="<?= moderation_e($value) ?>" <?= $filter === $value ? 'selected' : '' ?>><?= moderation_e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="admin-button" type="submit">Apply</button>
        <?php if ($q !== '' || $filter !== 'all'): ?>
            <a class="admin-button is-muted" href="/scouts.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (!$scouts): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
            <h3>No Scout profiles match this view.</h3>
        </div>
    <?php else: ?>
        <div class="admin-scout-list">
            <?php foreach ($scouts as $scout): ?>
                <?php
                $roles = explode(',', (string) ($scout['role_slugs'] ?? ''));
                $isMaster = in_array('master-scout', $roles, true) || in_array('master_scout', $roles, true);
                $status = (string) $scout['status'];
                $attention = in_array($status, ['application_submitted','pending_approval','inactive'], true);
                $period = admin_scout_current_period($db, $scout);
                ?>
                <article class="admin-scout-list-row <?= $attention ? 'has-attention' : '' ?>">
                    <div class="admin-user-identity">
                        <span class="admin-user-table-avatar">
                            <img src="<?= moderation_e(admin_user_avatar_src((string) ($scout['profile_image_src'] ?? ''), $siteUrl)) ?>" alt="" loading="lazy">
                        </span>
                        <div>
                            <strong><a href="/scout.php?id=<?= (int) $scout['id'] ?>"><?= moderation_e($scout['display_name'] ?: $scout['username'] ?: 'Scout') ?></a></strong>
                            <span>@<?= moderation_e((string) $scout['username']) ?></span>
                            <span><?= moderation_e((string) $scout['email']) ?></span>
                        </div>
                    </div>

                    <div class="admin-scout-list-facts">
                        <span><small>Rank</small><strong><?= $isMaster ? 'Master Scout' : 'Llama Scout' ?></strong></span>
                        <span><small>Status</small><strong><?= moderation_e(ucwords(str_replace('_', ' ', $status))) ?></strong></span>
                        <span><small>Points</small><strong><?= number_format((int) $scout['scout_points']) ?></strong></span>
                        <span><small>New Places</small><strong><?= number_format((int) $scout['new_place_count']) ?></strong></span>
                        <span><small>Improvements</small><strong><?= number_format((int) $scout['improvement_count']) ?></strong></span>
                    </div>

                    <div class="admin-scout-period-mini <?= !empty($period['met']) ? 'is-good' : '' ?>">
                        <?php if ($status === 'active' && !empty($period['end'])): ?>
                            <strong><?= number_format((int) $period['completed']) ?> / <?= number_format((int) $period['required']) ?> new Places</strong>
                            <span>Active through <?= moderation_e((string) $period['end']) ?></span>
                        <?php else: ?>
                            <strong><?= moderation_e(ucwords(str_replace('_', ' ', $status))) ?></strong>
                            <span>Current Scout period not active</span>
                        <?php endif; ?>
                    </div>

                    <a class="admin-button" href="/scout.php?id=<?= (int) $scout['id'] ?>">Manage</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-scouts.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);
$actorIsOwner = admin_users_current_is_owner(
    $db,
    $actorUserId
);

$scoutProfileId = (int) ($_GET['id'] ?? $_POST['scout_profile_id'] ?? 0);

if ($scoutProfileId < 1) {
    header('Location: /scouts.php');
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = (string) ($_POST['scout_admin_action'] ?? '');

            if ($action === 'status') {
                admin_scout_set_status(
                    $db,
                    $actorUserId,
                    $scoutProfileId,
                    (string) ($_POST['status'] ?? ''),
                    (string) ($_POST['notes'] ?? '')
                );
                $notice = 'Scout status updated.';
            } elseif ($action === 'master') {
                admin_scout_set_master(
                    $db,
                    $actorUserId,
                    $scoutProfileId,
                    ((string) ($_POST['make_master'] ?? '0')) === '1',
                    (string) ($_POST['notes'] ?? '')
                );
                $notice = 'Scout rank updated.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$scout = admin_scout_get($db, $scoutProfileId);

if (!$scout) {
    header('Location: /scouts.php');
    exit;
}

$application = admin_scout_application($db, $scoutProfileId);
$training = admin_scout_training($db, $scoutProfileId);
$activity = admin_scout_activity($db, (int) $scout['user_id']);
$rankHistory = admin_scout_rank_history($db, (int) $scout['user_id']);
$currentPeriod = admin_scout_current_period($db, $scout);
$masterQualification = admin_scout_master_qualification($db, $scout);

$roles = explode(
    ',',
    (string) ($scout['role_slugs'] ?? '')
);

$isMaster = in_array(
    'master_scout',
    $roles,
    true
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
        $scout['display_name']
        ?: $scout['username']
        ?: 'Scout'
    );

$adminPageEyebrow = 'Scout Administration';
$adminActiveNav = 'scouts';

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
                        (string) ($scout['profile_image_src'] ?? ''),
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
                        $scout['display_name']
                        ?: $scout['username']
                    ) ?>
                </h2>

                <span class="admin-status-pill">
                    <?= $isMaster ? 'Master Scout' : 'Llama Scout' ?>
                </span>

                <span class="admin-status-pill">
                    <?= moderation_e(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string) $scout['status']
                            )
                        )
                    ) ?>
                </span>
            </div>

            <p>
                @<?= moderation_e((string) $scout['username']) ?>
                · Scout profile #<?= (int) $scout['id'] ?>
            </p>
        </div>
    </div>

    <a
        class="admin-button"
        href="/user.php?id=<?= (int) $scout['user_id'] ?>"
    >
        User account
    </a>
</section>

<section class="admin-scout-operation-strip">
    <div>
        <span>Current rank</span>
        <strong><?= $isMaster ? 'Master Scout' : 'Llama Scout' ?></strong>
    </div>
    <div class="<?= (string) $scout['status'] === 'active' ? 'is-good' : 'has-attention' ?>">
        <span>Program status</span>
        <strong><?= moderation_e(ucwords(str_replace('_', ' ', (string) $scout['status']))) ?></strong>
    </div>
    <div class="<?= !empty($currentPeriod['met']) ? 'is-good' : '' ?>">
        <span>Current period</span>
        <strong><?= number_format((int) $currentPeriod['completed']) ?> / <?= number_format((int) $currentPeriod['required']) ?> new Places</strong>
    </div>
    <div>
        <span>Active through</span>
        <strong><?= moderation_e((string) ($currentPeriod['end'] ?: 'Not active')) ?></strong>
    </div>
    <div class="<?= !empty($masterQualification['eligible']) ? 'is-good' : '' ?>">
        <span>Master qualification</span>
        <strong><?= $isMaster ? 'Earned' : (!empty($masterQualification['eligible']) ? 'Ready' : 'In progress') ?></strong>
    </div>
</section>


<div class="admin-user-detail-grid">

    <div class="admin-user-detail-main">

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Program</p>
                    <h2>Scout Status</h2>
                </div>
            </header>

            <form class="admin-user-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                <input type="hidden" name="scout_profile_id" value="<?= (int) $scoutProfileId ?>">
                <input type="hidden" name="scout_admin_action" value="status">

                <div class="admin-user-form-grid">
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <?php foreach (
                                [
                                    'invited',
                                    'application_started',
                                    'application_submitted',
                                    'training',
                                    'pending_approval',
                                    'active',
                                    'inactive',
                                    'declined',
                                    'removed',
                                ] as $status
                            ): ?>
                                <option
                                    value="<?= moderation_e($status) ?>"
                                    <?= (string) $scout['status'] === $status ? 'selected' : '' ?>
                                >
                                    <?= moderation_e(
                                        ucwords(
                                            str_replace('_', ' ', $status)
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Active through</span>
                        <input
                            type="text"
                            value="<?= moderation_e(
                                (string) (
                                    $scout['active_through']
                                    ?: 'Not active'
                                )
                            ) ?>"
                            disabled
                        >
                    </label>

                    <label class="is-wide">
                        <span>Administrative notes</span>
                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Reason for this status change."
                        ></textarea>
                    </label>
                </div>

                <div class="admin-user-form-actions">
                    <button class="admin-button" type="submit">
                        Save Scout status
                    </button>
                </div>
            </form>
        </section>


        <?php if ($application): ?>
            <section class="admin-panel">
                <header class="admin-panel-header">
                    <div>
                        <p>Application</p>
                        <h2>Scout Application</h2>
                    </div>
                    <span>
                        Submitted <?= moderation_e(
                            (string) ($application['submitted_at'] ?: 'Not yet')
                        ) ?>
                    </span>
                </header>

                <dl class="admin-user-definition-list">
                    <div>
                        <dt>Why Scout</dt>
                        <dd><?= moderation_e((string) ($application['why_scout'] ?: 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Travel experience</dt>
                        <dd><?= moderation_e((string) ($application['travel_experience'] ?: 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Field experience</dt>
                        <dd><?= moderation_e((string) ($application['field_experience'] ?: 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Accessibility experience</dt>
                        <dd><?= moderation_e((string) ($application['accessibility_experience'] ?: 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Sensory experience</dt>
                        <dd><?= moderation_e((string) ($application['sensory_experience'] ?: 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Review notes</dt>
                        <dd><?= moderation_e((string) ($application['review_notes'] ?: 'None')) ?></dd>
                    </div>
                </dl>
            </section>
        <?php endif; ?>


        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Qualification Period</p>
                    <h2>Current Scout Period</h2>
                </div>
                <?php if (!empty($currentPeriod['end'])): ?>
                    <span><?= moderation_e((string) $currentPeriod['end']) ?></span>
                <?php endif; ?>
            </header>

            <div class="admin-scout-period-panel">
                <div class="admin-scout-period-progress">
                    <div>
                        <strong><?= number_format((int) $currentPeriod['completed']) ?></strong>
                        <span>approved new Places</span>
                    </div>
                    <div>
                        <strong><?= number_format((int) $currentPeriod['required']) ?></strong>
                        <span>required this period</span>
                    </div>
                    <div>
                        <strong><?= number_format((int) $currentPeriod['remaining']) ?></strong>
                        <span>remaining</span>
                    </div>
                </div>

                <?php if ((string) $scout['status'] === 'active'): ?>
                    <div class="admin-scout-period-status <?= !empty($currentPeriod['met']) ? 'is-good' : 'has-attention' ?>">
                        <i class="fa-solid <?= !empty($currentPeriod['met']) ? 'fa-circle-check' : 'fa-hourglass-half' ?>" aria-hidden="true"></i>
                        <div>
                            <strong><?= !empty($currentPeriod['met']) ? 'Current period requirement complete' : 'Current period still in progress' ?></strong>
                            <span>
                                <?php if ($currentPeriod['days_remaining'] !== null): ?>
                                    <?= number_format(max(0, (int) $currentPeriod['days_remaining'])) ?> days remain in this Scout period.
                                <?php else: ?>
                                    The active-through date needs review.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="admin-scout-period-status has-attention">
                        <i class="fa-solid fa-circle-pause" aria-hidden="true"></i>
                        <div>
                            <strong>No active Scout period</strong>
                            <span>Activating this Scout establishes a new period using the current Scout policy.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>


        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Field Work</p>
                    <h2>Recent Scout Activity</h2>
                </div>
            </header>

            <?php if (!$activity): ?>
                <div class="admin-empty-state">
                    <p>No Scout activity recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="admin-user-history-list">
                    <?php foreach ($activity as $item): ?>
                        <div class="admin-scout-activity-row">
                            <span>
                                <strong>
                                    <?= moderation_e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string) $item['activity_type']
                                            )
                                        )
                                    ) ?>
                                </strong>
                                <small>
                                    <?= moderation_e(
                                        (string) (
                                            $item['place_name']
                                            ?: $item['occurred_at']
                                        )
                                    ) ?>
                                </small>
                            </span>
                            <span>
                                <?= (int) $item['points'] >= 0 ? '+' : '' ?>
                                <?= number_format((int) $item['points']) ?> pts
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>


    <aside class="admin-user-detail-side">

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Rank</p>
                    <h2>Master Scout Qualification</h2>
                </div>
                <span><?= $isMaster ? 'Master Scout' : (!empty($masterQualification['eligible']) ? 'Ready' : 'In progress') ?></span>
            </header>

            <div class="admin-scout-master-checklist">
                <?php foreach ($masterQualification['requirements'] as $requirement): ?>
                    <div class="<?= !empty($requirement['met']) ? 'is-met' : '' ?>">
                        <i class="fa-solid <?= !empty($requirement['met']) ? 'fa-circle-check' : 'fa-circle' ?>" aria-hidden="true"></i>
                        <span>
                            <strong><?= moderation_e((string) $requirement['label']) ?></strong>
                            <small>
                                <?= number_format((int) $requirement['current']) ?>
                                / <?= number_format((int) $requirement['required']) ?>
                            </small>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$isMaster && empty($masterQualification['enabled'])): ?>
                <div class="admin-scout-policy-warning">Master Scout qualification is disabled in Scout policy.</div>
            <?php elseif (!$isMaster && empty($masterQualification['policy_complete'])): ?>
                <div class="admin-scout-policy-warning">One or more Master Scout thresholds are not configured.</div>
            <?php endif; ?>

            <?php if ($actorIsOwner): ?>
                <form class="admin-user-action-box admin-scout-master-action" method="post">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="scout_profile_id" value="<?= (int) $scoutProfileId ?>">
                    <input type="hidden" name="scout_admin_action" value="master">
                    <input type="hidden" name="make_master" value="<?= $isMaster ? '0' : '1' ?>">

                    <label>
                        <span>Reason / notes</span>
                        <textarea name="notes" rows="3" required></textarea>
                    </label>

                    <button
                        class="admin-button"
                        type="submit"
                        <?= !$isMaster && empty($masterQualification['eligible']) ? 'disabled' : '' ?>
                    >
                        <?= $isMaster ? 'Remove Master Scout' : 'Promote to Master Scout' ?>
                    </button>

                    <?php if (!$isMaster && empty($masterQualification['eligible'])): ?>
                        <small>Promotion becomes available when every current qualification requirement is complete.</small>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>


        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Training</p>
                    <h2>Completion</h2>
                </div>
            </header>

            <dl class="admin-user-definition-list">
                <div>
                    <dt>Training started</dt>
                    <dd><?= moderation_e((string) ($scout['training_started_at'] ?: 'No')) ?></dd>
                </div>
                <div>
                    <dt>Training completed</dt>
                    <dd><?= moderation_e((string) ($scout['training_completed_at'] ?: 'No')) ?></dd>
                </div>
                <?php if ($training): ?>
                    <div>
                        <dt>Accuracy acknowledged</dt>
                        <dd><?= (int) $training['acknowledged_accuracy'] === 1 ? 'Yes' : 'No' ?></dd>
                    </div>
                    <div>
                        <dt>Safety acknowledged</dt>
                        <dd><?= (int) $training['acknowledged_safety'] === 1 ? 'Yes' : 'No' ?></dd>
                    </div>
                    <div>
                        <dt>Privacy acknowledged</dt>
                        <dd><?= (int) $training['acknowledged_privacy'] === 1 ? 'Yes' : 'No' ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>


        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Rank History</p>
                    <h2>Scout Timeline</h2>
                </div>
            </header>

            <?php if (!$rankHistory): ?>
                <div class="admin-empty-state">
                    <p>No rank changes recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="admin-user-audit-list">
                    <?php foreach ($rankHistory as $entry): ?>
                        <div>
                            <strong>
                                <?= moderation_e(
                                    (string) $entry['from_rank']
                                ) ?>
                                →
                                <?= moderation_e(
                                    (string) $entry['to_rank']
                                ) ?>
                            </strong>
                            <span>
                                <?= moderation_e((string) $entry['reason']) ?>
                                · <?= moderation_e((string) $entry['occurred_at']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>

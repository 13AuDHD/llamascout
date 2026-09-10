<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/admin-users.php';

require_once
    dirname(__DIR__)
    . '/app/admin-points.php';

require_once
    __DIR__
    . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$actorIsOwner =
    admin_users_current_is_owner(
        $db,
        $actorUserId
    );

$notice = '';
$error = '';

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
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                (string) (
                    $_POST['points_admin_action']
                    ?? ''
                );

            if (
                $action
                === 'save-policy'
            ) {
                admin_points_save_policy(
                    $db,
                    $actorUserId,
                    (array) (
                        $_POST['policy']
                        ?? []
                    )
                );

                $notice =
                    'Sitewide points policy updated.';
            } elseif (
                $action
                === 'manual-adjustment'
            ) {
                admin_points_manual_adjustment(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['user_id']
                        ?? 0
                    ),
                    (int) (
                        $_POST['points']
                        ?? 0
                    ),
                    (string) (
                        $_POST['reason']
                        ?? ''
                    )
                );

                $notice =
                    'Point adjustment recorded.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

try {
    $policyGroups =
        admin_points_policy_groups(
            $db
        );

    $newPlaceMax =
        llama_points_new_place_max_points(
            $db
        );
} catch (Throwable $exception) {
    $policyGroups = [];
    $newPlaceMax = 0;

    if ($error === '') {
        $error =
            $exception->getMessage();
    }
}

$ledger =
    admin_points_recent(
        $db,
        150
    );

$stats =
    admin_dashboard_stats(
        $db
    );

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
    'Points';

$adminPageEyebrow =
    'Configuration';

$adminActiveNav =
    'points';

require
    __DIR__
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


<section class="admin-panel admin-policy-panel">

    <header class="admin-panel-header">
        <div>
            <p>Sitewide Source of Truth</p>
            <h2>Points Policy</h2>
        </div>

        <span>
            Future awards only
        </span>
    </header>

    <div class="admin-points-source-note">
        <strong>
            All point values belong here.
        </strong>

        <span>
            Draft estimates, contribution awards, moderation,
            and other point-aware features must read from this
            policy. Historical ledger entries never change when
            these values are edited.
        </span>
    </div>


    <?php if ($policyGroups): ?>

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
                name="points_admin_action"
                value="save-policy"
            >


            <?php foreach (
                $policyGroups
                as $group => $rows
            ): ?>

                <section class="admin-points-policy-group">

                    <header>
                        <h3>
                            <?= moderation_e(
                                $group
                            ) ?>
                        </h3>

                        <?php if (
                            $group
                            === 'New Place Categories'
                        ): ?>
                            <span>
                                Current maximum:
                                <?= number_format(
                                    $newPlaceMax
                                ) ?>
                                points
                            </span>
                        <?php endif; ?>
                    </header>


                    <div class="admin-policy-grid">

                        <?php foreach (
                            $rows
                            as $row
                        ): ?>

                            <label class="admin-policy-row">
                                <span>
                                    <strong>
                                        <?= moderation_e(
                                            (string) $row['label']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= moderation_e(
                                            (string) $row['description']
                                        ) ?>
                                    </small>
                                </span>

                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="policy[<?= moderation_e(
                                        (string) $row['policy_key']
                                    ) ?>]"
                                    value="<?= (int) $row['points_value'] ?>"
                                    <?= !$actorIsOwner
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >
                            </label>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>


            <?php if ($actorIsOwner): ?>
                <div class="admin-points-save">
                    <button
                        class="admin-button"
                        type="submit"
                    >
                        Save points policy
                    </button>
                </div>
            <?php endif; ?>

        </form>

    <?php endif; ?>

</section>


<?php if ($actorIsOwner): ?>

<section class="admin-panel admin-points-adjustment-panel">

    <header class="admin-panel-header">
        <div>
            <p>Manual Ledger Entry</p>
            <h2>Adjust Member Points</h2>
        </div>
    </header>

    <form
        class="admin-points-adjustment"
        method="post"
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
            name="points_admin_action"
            value="manual-adjustment"
        >

        <label>
            <span>User ID</span>

            <input
                type="number"
                name="user_id"
                min="1"
                step="1"
                required
            >
        </label>

        <label>
            <span>Points</span>

            <input
                type="number"
                name="points"
                step="1"
                placeholder="+25 or -10"
                required
            >
        </label>

        <label class="is-wide">
            <span>Reason</span>

            <input
                type="text"
                name="reason"
                maxlength="500"
                placeholder="Why this manual adjustment is being made"
                required
            >
        </label>

        <div>
            <button
                class="admin-button"
                type="submit"
            >
                Record adjustment
            </button>
        </div>
    </form>

</section>

<?php endif; ?>


<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>Permanent History</p>
            <h2>Points Ledger</h2>
        </div>

        <span>Latest 150 entries</span>
    </header>

    <?php if (!$ledger): ?>

        <div class="admin-empty-state">
            <p>
                No points have been recorded yet.
            </p>
        </div>

    <?php else: ?>

        <div class="admin-points-ledger">

            <?php foreach (
                $ledger
                as $entry
            ): ?>

                <article class="admin-points-ledger-row">

                    <span class="admin-user-table-avatar">
                        <img
                            src="<?= moderation_e(
                                admin_user_avatar_src(
                                    (string) (
                                        $entry['profile_image_src']
                                        ?? ''
                                    ),
                                    $siteUrl
                                )
                            ) ?>"
                            alt=""
                            loading="lazy"
                        >
                    </span>

                    <div>
                        <strong>
                            <?= moderation_e(
                                (string) $entry['member_name']
                            ) ?>
                        </strong>

                        <span>
                            <?= moderation_e(
                                (string) $entry['reason']
                            ) ?>
                        </span>

                        <small>
                            <?= moderation_e(
                                (string) $entry['source_type']
                            ) ?>
                            ·
                            <?= moderation_e(
                                llama_format_viewer_datetime(
                                    (string) $entry['created_at']
                                )
                            ) ?>
                            · by
                            <?= moderation_e(
                                (string) $entry['awarded_by_name']
                            ) ?>
                        </small>
                    </div>

                    <strong class="<?= (int) $entry['points'] < 0
                        ? 'is-negative'
                        : 'is-positive'
                    ?>">
                        <?= (int) $entry['points'] > 0
                            ? '+'
                            : ''
                        ?>
                        <?= number_format(
                            (int) $entry['points']
                        ) ?>
                    </strong>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php
require
    __DIR__
    . '/_footer.php';

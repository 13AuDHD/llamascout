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

$policyRows = [];
$policyLookup = [];
$categoryDefinitions = [];
$otherPolicyRows = [];
$newPlaceMax = 0;
$placeUpdateMax = 0;

try {
    $policyRows =
        admin_points_policy_rows(
            $db
        );

    foreach ($policyRows as $row) {
        $policyLookup[
            (string) $row['policy_key']
        ] = $row;

        if (
            (string) (
                $row['group']
                ?? ''
            ) === 'Other Contributions'
        ) {
            $otherPolicyRows[] =
                $row;
        }
    }

    $categoryDefinitions =
        llama_place_report_category_definitions();

    $newPlaceMax =
        llama_points_new_place_max_points(
            $db
        );

    $placeUpdateMax =
        llama_points_place_update_max_points(
            $db
        );
} catch (Throwable $exception) {
    $policyRows = [];
    $policyLookup = [];
    $categoryDefinitions = [];
    $otherPolicyRows = [];
    $newPlaceMax = 0;
    $placeUpdateMax = 0;

    if ($error === '') {
        $error =
            $exception->getMessage();
    }
}

$manualAdjustmentUsers = [];
$manualAdjustmentSelected = null;
$manualAdjustmentSelectedId =
    (int) (
        $_POST['user_id']
        ?? 0
    );

if ($actorIsOwner) {
    try {
        $manualAdjustmentUsers =
            admin_points_manual_adjustment_users(
                $db
            );

        if ($manualAdjustmentSelectedId > 0) {
            foreach (
                $manualAdjustmentUsers
                as $member
            ) {
                if (
                    (int) (
                        $member['id']
                        ?? 0
                    ) === $manualAdjustmentSelectedId
                ) {
                    $manualAdjustmentSelected =
                        $member;
                    break;
                }
            }
        }
    } catch (Throwable $exception) {
        $manualAdjustmentUsers = [];
        $manualAdjustmentSelected = null;

        if ($error === '') {
            $error =
                $exception->getMessage();
        }
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
            New Places and Place Updates have separate weighted policies.
        </strong>

        <span>
            New Place points reward how much of the Place Report is supplied.
            Update points reward only the specific approved fields that changed.
            Historical ledger entries never change when these values are edited.
        </span>
    </div>


    <?php if ($categoryDefinitions): ?>

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


            <section class="admin-points-policy-group admin-points-category-policy">

                <header class="admin-points-category-header">
                    <div>
                        <h3>Place Contribution Categories</h3>
                        <p>
                            The same Place Report categories are weighted independently
                            for a new Place and for later updates.
                        </p>
                    </div>

                    <div class="admin-points-policy-maxima">
                        <span>
                            New Place max
                            <strong><?= number_format($newPlaceMax) ?></strong>
                        </span>

                        <span>
                            Update max
                            <strong><?= number_format($placeUpdateMax) ?></strong>
                        </span>
                    </div>
                </header>


                <div class="admin-points-category-table">

                    <div class="admin-points-category-columns" aria-hidden="true">
                        <span>Category</span>
                        <span>New Place</span>
                        <span>Update</span>
                    </div>


                    <?php foreach (
                        $categoryDefinitions
                        as $slug => $category
                    ): ?>

                        <?php
                        $newKey =
                            (string) (
                                $category['policy_key']
                                ?? ''
                            );

                        $updateKey =
                            'place_update_'
                            . (string) $slug;

                        $newRow =
                            $policyLookup[$newKey]
                            ?? null;

                        $updateRow =
                            $policyLookup[$updateKey]
                            ?? null;

                        if (!$newRow || !$updateRow) {
                            continue;
                        }
                        ?>

                        <div class="admin-points-category-row">

                            <span class="admin-points-category-copy">
                                <strong>
                                    <?= moderation_e(
                                        (string) (
                                            $category['label']
                                            ?? $slug
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    <?= moderation_e(
                                        (string) (
                                            $newRow['description']
                                            ?? ''
                                        )
                                    ) ?>
                                </small>
                            </span>


                            <label>
                                <span>New Place</span>

                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="policy[<?= moderation_e($newKey) ?>]"
                                    value="<?= (int) $newRow['points_value'] ?>"
                                    <?= !$actorIsOwner
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >
                            </label>


                            <label>
                                <span>Update</span>

                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="policy[<?= moderation_e($updateKey) ?>]"
                                    value="<?= (int) $updateRow['points_value'] ?>"
                                    <?= !$actorIsOwner
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >
                            </label>

                        </div>

                    <?php endforeach; ?>

                </div>

            </section>


            <?php if ($otherPolicyRows): ?>

                <section class="admin-points-policy-group">

                    <header>
                        <h3>Other Contributions</h3>
                    </header>

                    <div class="admin-policy-grid">

                        <?php foreach (
                            $otherPolicyRows
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

            <?php endif; ?>


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

<?php
$selectedMemberName = '';
$selectedMemberMeta = '';

if (is_array($manualAdjustmentSelected)) {
    $selectedDisplayName =
        trim(
            (string) (
                $manualAdjustmentSelected['display_name']
                ?? ''
            )
        );

    $selectedUsername =
        trim(
            (string) (
                $manualAdjustmentSelected['username']
                ?? ''
            )
        );

    $selectedEmail =
        trim(
            (string) (
                $manualAdjustmentSelected['email']
                ?? ''
            )
        );

    $selectedMemberName =
        $selectedDisplayName !== ''
            ? $selectedDisplayName
            : (
                $selectedUsername !== ''
                    ? '@' . $selectedUsername
                    : $selectedEmail
            );

    $selectedMemberMeta =
        trim(
            implode(
                ' · ',
                array_filter(
                    [
                        $selectedUsername !== ''
                            ? '@' . $selectedUsername
                            : null,
                        $selectedEmail !== ''
                            ? $selectedEmail
                            : null,
                        '#' . $manualAdjustmentSelectedId,
                    ]
                )
            )
        );
}
?>

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
        data-points-adjustment-form
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

        <label class="admin-points-member-field">
            <span>Member</span>

            <div
                class="admin-points-member-picker"
                data-member-picker
            >
                <input
                    type="search"
                    class="admin-points-member-search"
                    placeholder="Search name, username, or email"
                    value="<?= moderation_e($selectedMemberName) ?>"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="admin-points-member-results"
                    data-member-search
                >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= $manualAdjustmentSelectedId > 0
                        ? $manualAdjustmentSelectedId
                        : ''
                    ?>"
                    data-member-id
                >

                <div
                    id="admin-points-member-results"
                    class="admin-points-member-results"
                    role="listbox"
                    hidden
                    data-member-results
                >
                    <?php foreach (
                        $manualAdjustmentUsers
                        as $member
                    ): ?>
                        <?php
                        $memberName =
                            trim(
                                (string) (
                                    $member['display_name']
                                    ?? ''
                                )
                            );

                        $memberUsername =
                            trim(
                                (string) (
                                    $member['username']
                                    ?? ''
                                )
                            );

                        $memberEmail =
                            trim(
                                (string) (
                                    $member['email']
                                    ?? ''
                                )
                            );

                        $memberId =
                            (int) (
                                $member['id']
                                ?? 0
                            );

                        $memberStatus =
                            trim(
                                (string) (
                                    $member['status']
                                    ?? ''
                                )
                            );

                        $memberPrimary =
                            $memberName !== ''
                                ? $memberName
                                : (
                                    $memberUsername !== ''
                                        ? '@' . $memberUsername
                                        : $memberEmail
                                );

                        $memberSearchText =
                            strtolower(
                                implode(
                                    ' ',
                                    [
                                        $memberPrimary,
                                        $memberUsername,
                                        $memberEmail,
                                        (string) $memberId,
                                    ]
                                )
                            );
                        ?>

                        <button
                            type="button"
                            class="admin-points-member-option"
                            role="option"
                            data-member-option
                            data-member-id="<?= $memberId ?>"
                            data-member-label="<?= moderation_e($memberPrimary) ?>"
                            data-member-meta="<?= moderation_e(
                                trim(
                                    implode(
                                        ' · ',
                                        array_filter(
                                            [
                                                $memberUsername !== ''
                                                    ? '@' . $memberUsername
                                                    : null,
                                                $memberEmail !== ''
                                                    ? $memberEmail
                                                    : null,
                                                '#' . $memberId,
                                                $memberStatus !== ''
                                                    ? ucfirst($memberStatus)
                                                    : null,
                                            ]
                                        )
                                    )
                                )
                            ) ?>"
                            data-member-search-text="<?= moderation_e($memberSearchText) ?>"
                            hidden
                        >
                            <strong>
                                <?= moderation_e($memberPrimary) ?>
                            </strong>

                            <small>
                                <?php if ($memberUsername !== ''): ?>
                                    @<?= moderation_e($memberUsername) ?>
                                    ·
                                <?php endif; ?>

                                <?= moderation_e($memberEmail) ?>

                                · #<?= $memberId ?>
                            </small>
                        </button>

                    <?php endforeach; ?>
                </div>
            </div>
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

<script src="https://llamascout.com/js/admin/points.js"></script>

<?php
require
    __DIR__
    . '/_footer.php';
?>

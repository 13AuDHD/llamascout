<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-badges.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$actorIsOwner =
    admin_users_current_is_owner(
        $db,
        $actorUserId
    );

$notice =
    isset($_GET['credential_reviewed'])
        ? 'Credential review saved.'
        : '';
$error = '';

$selectedUserId =
    max(
        0,
        (int) (
            $_GET['user_id']
            ?? $_POST['user_id']
            ?? 0
        )
    );

$selectedUser =
    $selectedUserId > 0
        ? admin_users_get(
            $db,
            $selectedUserId
        )
        : null;

if (
    $selectedUserId > 0
    && !$selectedUser
) {
    $error =
        'Member account not found.';

    $selectedUserId = 0;
}

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
                trim(
                    (string) (
                        $_POST['badge_admin_action']
                        ?? ''
                    )
                );

            if ($action === 'create-badge') {
                $badgeId =
                    admin_badges_save_definition(
                        $db,
                        $actorUserId,
                        0,
                        $_POST
                    );

                $submittedPhotos =
                    llama_photo_decode_form_photos(
                        $_POST['photos_json']
                        ?? '[]'
                    );

                if ($submittedPhotos) {
                    admin_badges_replace_image_from_stage(
                        $db,
                        $actorUserId,
                        $badgeId,
                        (string) (
                            $_POST['photo_stage_token']
                            ?? ''
                        ),
                        $submittedPhotos
                    );
                }

                header(
                    'Location: /badge-admin.php?id=' .
                    $badgeId .
                    '&created=1'
                );

                exit;

            } elseif ($action === 'award-user-badge') {
                if ($selectedUserId < 1) {
                    throw new RuntimeException(
                        'Choose a member before awarding a badge.'
                    );
                }

                admin_badges_award(
                    $db,
                    $actorUserId,
                    $selectedUserId,
                    (int) (
                        $_POST['badge_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['note']
                        ?? ''
                    ),
                    (string) (
                        $_POST['evidence_url']
                        ?? ''
                    )
                );

                header(
                    'Location: /badges.php?user_id=' .
                    $selectedUserId .
                    '&awarded=1'
                );
                exit;

            } elseif ($action === 'review-credential') {
                $result = llama_badge_credential_review(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['submission_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['review_decision']
                        ?? ''
                    ),
                    (string) (
                        $_POST['review_note']
                        ?? ''
                    )
                );

                header(
                    'Location: /badges.php?credential_reviewed=1',
                    true,
                    303
                );
                exit;

            } elseif ($action === 'revoke-user-badge') {
                if ($selectedUserId < 1) {
                    throw new RuntimeException(
                        'Choose a member before removing a badge.'
                    );
                }

                $userBadgeId =
                    (int) (
                        $_POST['user_badge_id']
                        ?? 0
                    );

                $ownedBadge =
                    admin_badges_user_badge(
                        $db,
                        $selectedUserId,
                        $userBadgeId
                    );

                if (!$ownedBadge) {
                    throw new RuntimeException(
                        'That badge award does not belong to this member.'
                    );
                }

                admin_badges_revoke(
                    $db,
                    $actorUserId,
                    $userBadgeId,
                    (string) (
                        $_POST['reason']
                        ?? ''
                    )
                );

                header(
                    'Location: /badges.php?user_id=' .
                    $selectedUserId .
                    '&removed=1'
                );
                exit;
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$definitions =
    admin_badges_definitions(
        $db
    );

$pendingCredentialReviews =
    llama_badge_credential_pending_reviews(
        $db,
        null,
        100
    );

$badgeThresholdMetricLabels =
    llama_badge_threshold_metric_labels();

$badgeThresholdMetricDescriptions =
    llama_badge_threshold_metric_descriptions();

$selectedUserBadges =
    $selectedUserId > 0
        ? admin_badges_user_badges(
            $db,
            $selectedUserId
        )
        : [];

$selectedEarnedBadgeIds = [];

foreach (
    $selectedUserBadges
    as
    $userBadge
) {
    if (
        (string) $userBadge['review_status']
        === 'earned'
    ) {
        $selectedEarnedBadgeIds[] =
            (int) $userBadge['badge_id'];
    }
}

$availableUserBadges =
    array_values(
        array_filter(
            $definitions,
            static fn (array $badge): bool =>
                (int) $badge['is_active'] === 1
                && !in_array(
                    (int) $badge['id'],
                    $selectedEarnedBadgeIds,
                    true
                )
        )
    );

$badgeStats =
    admin_badges_stats(
        $db
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
    'Badges';

$adminPageEyebrow =
    'People';

$adminActiveNav =
    'badges';

$adminNeedsPhotoUploader =
    true;

require __DIR__ .
    '/_header.php';
?>

<?php if (isset($_GET['awarded'])): ?>
<div class="admin-notice is-success">
    Badge awarded.
</div>
<?php endif; ?>

<?php if (isset($_GET['removed'])): ?>
<div class="admin-notice is-success">
    Badge removed.
</div>
<?php endif; ?>

<?php if ($notice): ?>
<div class="admin-notice is-success">
    <?= moderation_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="admin-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>


<?php if ($selectedUser): ?>

<section class="admin-panel admin-member-badge-manager">

<header class="admin-panel-header">
    <div>
        <p>Member Badges</p>
        <h2>
            <?= moderation_e(
                (string) (
                    $selectedUser['display_name']
                    ?: $selectedUser['username']
                    ?: $selectedUser['email']
                    ?: 'Member'
                )
            ) ?>
        </h2>
    </div>

    <a
        class="admin-button is-muted"
        href="/user.php?id=<?= (int) $selectedUserId ?>"
    >
        Back to member
    </a>
</header>

<div class="admin-member-badge-summary">
    <div>
        <span>Earned</span>
        <strong>
            <?= number_format(
                count(
                    array_filter(
                        $selectedUserBadges,
                        static fn (array $row): bool =>
                            (string) $row['review_status'] === 'earned'
                    )
                )
            ) ?>
        </strong>
    </div>

    <div>
        <span>Pending / Review</span>
        <strong>
            <?= number_format(
                count(
                    array_filter(
                        $selectedUserBadges,
                        static fn (array $row): bool =>
                            (string) $row['review_status'] !== 'earned'
                    )
                )
            ) ?>
        </strong>
    </div>
</div>

<?php if (!$selectedUserBadges): ?>
    <div class="admin-empty-state">
        <i aria-hidden="true"><?= llama_icon('award') ?></i>
        <h3>No badges assigned yet.</h3>
    </div>
<?php else: ?>

<div class="admin-member-badge-list">

<?php foreach ($selectedUserBadges as $userBadge): ?>

<article class="admin-member-badge-row">
    <div class="admin-member-badge-art">
        <?php
        $badgeImage =
            llama_badge_image_url(
                (string) $userBadge['slug'],
                (string) ($userBadge['image_src'] ?? '')
            );
        ?>

        <?php if ($badgeImage !== ''): ?>
            <img
                src="<?= moderation_e(
                    llama_profile_image_url(
                        $badgeImage,
                        'https://llamascout.com'
                    )
                ) ?>"
                alt=""
            >
        <?php else: ?>
            <i aria-hidden="true">
                <?= llama_icon(
                    admin_badges_icon_name(
                        $userBadge['icon'] ?? null
                    )
                ) ?>
            </i>
        <?php endif; ?>
    </div>

    <div class="admin-member-badge-copy">
        <span>
            <?= moderation_e(
                ucwords(
                    str_replace(
                        '-',
                        ' ',
                        (string) $userBadge['category']
                    )
                )
            ) ?>
            ·
            <?= moderation_e(
                ucwords(
                    str_replace(
                        '-',
                        ' ',
                        (string) $userBadge['review_status']
                    )
                )
            ) ?>
        </span>

        <strong>
            <?= moderation_e((string) $userBadge['name']) ?>
        </strong>

        <small>
            Awarded
            <?= moderation_e(
                llama_format_viewer_datetime(
                    (string) $userBadge['awarded_at']
                )
            ) ?>
            ·
            <?= moderation_e((string) $userBadge['awarded_by_name']) ?>
        </small>

        <?php if (!empty($userBadge['note'])): ?>
            <p><?= moderation_e((string) $userBadge['note']) ?></p>
        <?php endif; ?>
    </div>

    <div class="admin-member-badge-actions">
        <a
            class="admin-button is-muted"
            href="/badge-admin.php?id=<?= (int) $userBadge['badge_id'] ?>"
        >
            Badge
        </a>

        <?php if ((string) $userBadge['review_status'] === 'earned'): ?>
            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(moderation_csrf_token()) ?>"
                >
                <input
                    type="hidden"
                    name="user_id"
                    value="<?= (int) $selectedUserId ?>"
                >
                <input
                    type="hidden"
                    name="badge_admin_action"
                    value="revoke-user-badge"
                >
                <input
                    type="hidden"
                    name="user_badge_id"
                    value="<?= (int) $userBadge['id'] ?>"
                >

                <label>
                    <span class="sr-only">Removal reason</span>
                    <input
                        type="text"
                        name="reason"
                        maxlength="500"
                        placeholder="Reason required"
                        required
                    >
                </label>

                <button
                    class="admin-button is-danger"
                    type="submit"
                    onclick="return confirm('Remove this badge from the member?');"
                >
                    Remove
                </button>
            </form>
        <?php endif; ?>
    </div>
</article>

<?php endforeach; ?>

</div>

<?php endif; ?>


<section class="admin-member-badge-award">
    <div>
        <p>Manual Award</p>
        <h3>Award another badge</h3>
    </div>

    <?php if (!$availableUserBadges): ?>
        <p class="admin-muted-copy">
            This member already has every active badge available for manual assignment.
        </p>
    <?php else: ?>

    <form method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="user_id"
            value="<?= (int) $selectedUserId ?>"
        >
        <input
            type="hidden"
            name="badge_admin_action"
            value="award-user-badge"
        >

        <label>
            <span>Badge</span>
            <select name="badge_id" required>
                <option value="">Choose badge</option>
                <?php foreach ($availableUserBadges as $badge): ?>
                    <option value="<?= (int) $badge['id'] ?>">
                        <?= moderation_e((string) $badge['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Admin note (optional)</span>
            <input
                type="text"
                name="note"
                maxlength="500"
            >
        </label>

        <label>
            <span>Evidence URL (optional)</span>
            <input
                type="url"
                name="evidence_url"
                maxlength="500"
                placeholder="https://"
            >
        </label>

        <button
            class="admin-button"
            type="submit"
        >
            Award badge
        </button>
    </form>

    <?php endif; ?>
</section>

</section>

<?php endif; ?>


<section class="admin-badge-stat-grid">

<div>
    <span>Active Badges</span>
    <strong>
        <?= number_format(
            (int) $badgeStats['active_badges']
        ) ?>
    </strong>
</div>

<div>
    <span>Inactive</span>
    <strong>
        <?= number_format(
            (int) $badgeStats['inactive_badges']
        ) ?>
    </strong>
</div>

<div>
    <span>Awards Earned</span>
    <strong>
        <?= number_format(
            (int) $badgeStats['earned_awards']
        ) ?>
    </strong>
</div>

<div class="<?= (int) $badgeStats['pending_review'] > 0
    ? 'has-attention'
    : '' ?>">
    <span>Needs Review</span>
    <strong>
        <?= number_format(
            (int) $badgeStats['pending_review']
        ) ?>
    </strong>
</div>

</section>


<?php if ($pendingCredentialReviews): ?>
<section class="admin-panel admin-credential-review-panel">

<header class="admin-panel-header">
    <div>
        <p>Credential Review</p>
        <h2>Waiting for Approval</h2>
    </div>

    <span><?= number_format(count($pendingCredentialReviews)) ?></span>
</header>

<div class="admin-credential-review-list">

<?php foreach ($pendingCredentialReviews as $submission): ?>
<article class="admin-credential-review-card">

    <div class="admin-credential-review-copy">
        <span>
            <?= moderation_e((string) $submission['badge_name']) ?>
            · Submission #<?= (int) $submission['id'] ?>
        </span>

        <strong>
            <a href="/user.php?id=<?= (int) $submission['user_id'] ?>">
                <?= moderation_e((string) $submission['member_name']) ?>
            </a>
        </strong>

        <small>
            Submitted
            <?= moderation_e(
                llama_format_viewer_datetime(
                    (string) $submission['submitted_at']
                )
            ) ?>
            · <?= moderation_e(
                llama_badge_credential_format_bytes(
                    (int) $submission['file_size']
                )
            ) ?>
        </small>

        <?php if (!empty($submission['credential_identifier'])): ?>
            <p>
                <strong>Credential:</strong>
                <?= moderation_e((string) $submission['credential_identifier']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($submission['issued_on']) || !empty($submission['expires_on'])): ?>
            <p>
                <?php if (!empty($submission['issued_on'])): ?>
                    Issued <?= moderation_e((string) $submission['issued_on']) ?>
                <?php endif; ?>
                <?php if (!empty($submission['expires_on'])): ?>
                    <?= !empty($submission['issued_on']) ? ' · ' : '' ?>
                    Expires <?= moderation_e((string) $submission['expires_on']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($submission['member_note'])): ?>
            <p><?= moderation_e((string) $submission['member_note']) ?></p>
        <?php endif; ?>

        <a
            class="admin-button is-muted admin-credential-file-link"
            href="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>"
        >
            Download private evidence
        </a>
    </div>

    <?php if (str_starts_with((string) $submission['mime_type'], 'image/')): ?>
        <a
            class="admin-credential-preview"
            href="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>"
            aria-label="Download credential evidence"
        >
            <img
                src="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>&amp;inline=1"
                alt="Private credential evidence preview"
                loading="lazy"
            >
        </a>
    <?php endif; ?>

    <div class="admin-credential-review-actions">
        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >
            <input type="hidden" name="badge_admin_action" value="review-credential">
            <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
            <input type="hidden" name="review_decision" value="approve">

            <label>
                <span>Approval note</span>
                <textarea
                    name="review_note"
                    rows="2"
                    maxlength="1000"
                    placeholder="Optional"
                ></textarea>
            </label>

            <button class="admin-button" type="submit">
                Approve
            </button>
        </form>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >
            <input type="hidden" name="badge_admin_action" value="review-credential">
            <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
            <input type="hidden" name="review_decision" value="decline">

            <label>
                <span>Decline reason</span>
                <textarea
                    name="review_note"
                    rows="2"
                    maxlength="1000"
                    required
                ></textarea>
            </label>

            <button class="admin-button is-danger" type="submit">
                Decline
            </button>
        </form>
    </div>

</article>
<?php endforeach; ?>

</div>
</section>
<?php endif; ?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Achievements</p>
        <h2>Badge Definitions</h2>
    </div>

    <span>
        <?= number_format(
            count($definitions)
        ) ?>
    </span>
</header>


<div class="admin-badge-definition-grid">

<?php foreach ($definitions as $badge): ?>

<a
    class="admin-badge-definition-card <?= (int) $badge['is_active'] === 1
        ? ''
        : 'is-inactive' ?>"
    href="/badge-admin.php?id=<?= (int) $badge['id'] ?>"
>

<div class="admin-badge-definition-icon">
    <?php if (!empty($badge['image_src'])): ?>
        <img
            src="<?= moderation_e(
                llama_profile_image_url(
                    llama_badge_image_url(
                        (string) $badge['slug'],
                        (string) ($badge['image_src'] ?? '')
                    ),
                    'https://llamascout.com'
                )
            ) ?>"
            alt=""
        >
    <?php else: ?>
        <i aria-hidden="true">
            <?= llama_icon(
                admin_badges_icon_name(
                    $badge['icon'] ?? null
                )
            ) ?>
        </i>
    <?php endif; ?>
</div>

<div class="admin-badge-definition-copy">
    <span>
        <?= moderation_e(
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    (string) $badge['category']
                )
            )
        ) ?>
        ·
        <?= moderation_e(
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    (string) $badge['award_type']
                )
            )
        ) ?>
    </span>

    <strong>
        <?= moderation_e(
            (string) $badge['name']
        ) ?>
    </strong>

    <p>
        <?= moderation_e(
            (string) (
                $badge['description']
                ?: 'No description.'
            )
        ) ?>
    </p>
</div>

<div class="admin-badge-definition-stats">
    <span>
        <?= number_format(
            (int) $badge['earned_count']
        ) ?>
        earned
    </span>

    <?php if ((int) ($badge['review_count'] ?? 0) > 0): ?>
        <span class="has-attention">
            <?= number_format((int) $badge['review_count']) ?>
            review<?= (int) $badge['review_count'] === 1 ? '' : 's' ?>
        </span>
    <?php endif; ?>

    <?php if ((int) $badge['threshold_value'] > 0): ?>
        <?php
        $thresholdMetric =
            (string) (
                $badge['threshold_metric']
                ?? ''
            );

        $thresholdLabel =
            $badgeThresholdMetricLabels[
                $thresholdMetric
            ]
            ?? 'Threshold';
        ?>
        <span>
            <?= moderation_e($thresholdLabel) ?>
            ·
            <?= number_format(
                (int) $badge['threshold_value']
            ) ?>
        </span>
    <?php endif; ?>

    <?php if ((int) $badge['is_active'] !== 1): ?>
        <span class="is-inactive">
            Inactive
        </span>
    <?php endif; ?>
</div>

</a>

<?php endforeach; ?>

</div>

</section>


<?php if ($actorIsOwner): ?>

<section class="admin-panel admin-badge-create-panel">

<header class="admin-panel-header">
    <div>
        <p>Definition</p>
        <h2>Create Badge</h2>
    </div>
</header>

<form
    method="post"
    class="admin-badge-definition-form"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="badge_admin_action"
        value="create-badge"
    >

    <div class="admin-badge-definition-form-grid">

        <label>
            <span>Name</span>
            <input
                type="text"
                name="name"
                maxlength="150"
                required
            >
        </label>

        <label>
            <span>Slug</span>
            <input
                type="text"
                name="slug"
                maxlength="100"
                placeholder="Auto-generated from name"
            >
        </label>

        <label>
            <span>Category</span>
            <select name="category">
                <option value="community">Community</option>
                <option value="scouting">Scouting</option>
                <option value="stewardship">Stewardship</option>
                <option value="training">Training</option>
                <option value="special">Special</option>
            </select>
        </label>

        <label>
            <span>Award type</span>
            <select
                name="award_type"
                data-badge-award-type
            >
                <option value="automatic">Automatic</option>
                <option value="manual">Manual</option>
                <option value="credential">Credential</option>
            </select>
        </label>

        <label data-badge-threshold-field>
            <span>Threshold type</span>
            <select
                name="threshold_metric"
                data-badge-threshold-metric
                required
            >
                <option value="" selected disabled>
                    Choose metric
                </option>
                <?php foreach (
                    $badgeThresholdMetricLabels
                    as
                    $metricValue => $metricLabel
                ): ?>
                    <option
                        value="<?= moderation_e(
                            $metricValue
                        ) ?>"
                        data-description="<?= moderation_e(
                            (string) (
                                $badgeThresholdMetricDescriptions[
                                    $metricValue
                                ]
                                ?? ''
                            )
                        ) ?>"
                    >
                        <?= moderation_e(
                            $metricLabel
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small data-badge-threshold-help>
                Choose the activity this automatic badge measures.
            </small>
        </label>

        <label data-badge-threshold-field>
            <span>Threshold</span>
            <input
                type="number"
                name="threshold_value"
                min="1"
                step="1"
                placeholder="Required"
                required
            >
            <small>
                Award the badge when the selected metric reaches this value.
            </small>
        </label>

        <label>
            <span>Sort order</span>
            <input
                type="number"
                name="sort_order"
                min="0"
                value="0"
            >
        </label>

        <label>
            <span>Local SVG icon</span>
            <input
                type="text"
                name="icon"
                maxlength="100"
                placeholder="award"
                list="badge-icon-options"
            >
            <small>
                Filename from /assets/icons, without .svg.
            </small>
        </label>

        <datalist id="badge-icon-options">
            <?php foreach (admin_badges_icon_options() as $iconOption): ?>
                <option value="<?= moderation_e($iconOption) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <label>
            <span>Source organization</span>
            <input
                type="text"
                name="source_organization"
                maxlength="150"
            >
        </label>

        <div class="is-wide admin-badge-image-uploader-field">
            <span>Badge image</span>

            <input
                type="hidden"
                name="photo_stage_token"
                value=""
            >

            <input
                type="hidden"
                name="photos_json"
                value="[]"
            >

            <div
                data-photo-uploader
                data-photo-context="badges"
                data-photo-max="1"
                data-photo-csrf="<?= moderation_e(llama_photo_csrf_token()) ?>"
                data-photo-endpoint="/photo-upload.php"
                data-photo-title="Upload badge image"
                data-photo-help="Upload one image. Transparent PNG files stay PNG. Other image types use the normal optimized JPEG pipeline. Metadata is stripped and the file is named from the badge slug."
            ></div>
        </div>

        <label class="is-wide">
            <span>Description</span>
            <textarea
                name="description"
                rows="3"
                maxlength="500"
            ></textarea>
        </label>

        <label class="admin-badge-active-field">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                checked
            >
            <span>Badge is active</span>
        </label>

    </div>

    <div class="admin-user-form-actions">
        <button
            class="admin-button"
            type="submit"
        >
            Create badge
        </button>
    </div>

</form>

</section>

<?php endif; ?>


<script src="https://llamascout.com/js/admin/badges.js"></script>

<?php
require __DIR__ .
    '/_footer.php';
?>

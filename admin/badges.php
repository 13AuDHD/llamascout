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
        <i
            class="fa-solid <?= moderation_e(
                (string) (
                    $badge['icon']
                    ?: 'fa-award'
                )
            ) ?>"
            aria-hidden="true"
        ></i>
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

    <?php if ((int) $badge['threshold_value'] > 0): ?>
        <span>
            threshold
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
            <select name="award_type">
                <option value="automatic">Automatic</option>
                <option value="manual">Manual</option>
                <option value="credential">Credential</option>
            </select>
        </label>

        <label>
            <span>Threshold</span>
            <input
                type="number"
                name="threshold_value"
                min="0"
                placeholder="Optional"
            >
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
            <span>Font Awesome icon</span>
            <input
                type="text"
                name="icon"
                maxlength="100"
                placeholder="fa-award"
            >
        </label>

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
                data-photo-help="Upload one image. It will be resized, stripped of location metadata, saved as the badge slug, and used automatically."
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


<?php
require __DIR__ .
    '/_footer.php';
?>

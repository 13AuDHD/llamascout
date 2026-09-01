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

$badgeId =
    (int) (
        $_GET['id']
        ?? $_POST['badge_id']
        ?? 0
    );

if ($badgeId < 1) {
    header(
        'Location: /badges.php'
    );

    exit;
}

$notice =
    isset($_GET['created'])
        ? 'Badge created.'
        : '';

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

            if ($action === 'save-definition') {
                admin_badges_save_definition(
                    $db,
                    $actorUserId,
                    $badgeId,
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

                $notice =
                    $submittedPhotos
                        ? 'Badge definition and image updated.'
                        : 'Badge definition updated.';
            } elseif ($action === 'award') {
                admin_badges_award(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['user_id']
                        ?? 0
                    ),
                    $badgeId,
                    (string) (
                        $_POST['note']
                        ?? ''
                    ),
                    (string) (
                        $_POST['evidence_url']
                        ?? ''
                    )
                );

                $notice =
                    'Badge awarded.';
            } elseif ($action === 'revoke') {
                admin_badges_revoke(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['user_badge_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['reason']
                        ?? ''
                    )
                );

                $notice =
                    'Badge removed.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$badge =
    admin_badges_definition(
        $db,
        $badgeId
    );

if (!$badge) {
    http_response_code(404);

    $adminPageTitle =
        'Badge Not Found';

    $adminPageEyebrow =
        'People';

    $adminActiveNav =
        'badges';

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

    require __DIR__ .
        '/_header.php';
    ?>
    <section class="admin-panel">
        <div class="admin-empty-state">
            <p>Badge definition not found.</p>
        </div>
    </section>
    <?php
    require __DIR__ .
        '/_footer.php';

    exit;
}

$recipients =
    admin_badges_recipients(
        $db,
        $badgeId
    );

$users =
    $db->query(
        'SELECT
            id,
            COALESCE(
                NULLIF(display_name, ""),
                NULLIF(username, ""),
                email
            ) AS name,
            username,
            email
         FROM users
         WHERE anonymized_at IS NULL
           AND status <> "disabled"
         ORDER BY
            name ASC,
            id ASC'
    )->fetchAll(PDO::FETCH_ASSOC)
    ?: [];

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
    (string) $badge['name'];

$adminPageEyebrow =
    'Badges';

$adminActiveNav =
    'badges';

$adminNeedsPhotoUploader =
    true;

require __DIR__ .
    '/_header.php';
?>

<div class="admin-page-back">
    <a href="/badges.php">
        <i
            class="fa-solid fa-arrow-left"
            aria-hidden="true"
        ></i>
        All badges
    </a>
</div>


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


<section class="admin-badge-detail-hero">

<div class="admin-badge-detail-art">
    <?php if (!empty($badge['image_src'])): ?>
        <img
            src="<?= moderation_e(
                (string) $badge['image_src']
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

<div>
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

    <h2>
        <?= moderation_e(
            (string) $badge['name']
        ) ?>
    </h2>

    <p>
        <?= moderation_e(
            (string) (
                $badge['description']
                ?: 'No description.'
            )
        ) ?>
    </p>
</div>

<div class="admin-badge-detail-counts">
    <div>
        <span>Earned</span>
        <strong>
            <?= number_format(
                (int) $badge['earned_count']
            ) ?>
        </strong>
    </div>

    <div>
        <span>Status</span>
        <strong>
            <?= (int) $badge['is_active'] === 1
                ? 'Active'
                : 'Inactive' ?>
        </strong>
    </div>
</div>

</section>


<div class="admin-badge-detail-grid">

<div class="admin-badge-detail-main">

<?php if ($actorIsOwner): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Definition</p>
        <h2>Badge Settings</h2>
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
        name="badge_id"
        value="<?= (int) $badgeId ?>"
    >

    <input
        type="hidden"
        name="badge_admin_action"
        value="save-definition"
    >

    <div class="admin-badge-definition-form-grid">

        <label>
            <span>Name</span>
            <input
                id="admin-badge-name"
                type="text"
                name="name"
                maxlength="150"
                value="<?= moderation_e(
                    (string) $badge['name']
                ) ?>"
                required
            >
        </label>

        <label>
            <span>Slug</span>
            <input
                id="admin-badge-slug"
                type="text"
                name="slug"
                maxlength="100"
                value="<?= moderation_e(
                    (string) $badge['slug']
                ) ?>"
                required
            >
            <small>
                Defaults from the badge name, but can be edited.
            </small>
        </label>

        <label>
            <span>Category</span>
            <select name="category">
                <?php foreach (
                    [
                        'community' => 'Community',
                        'scouting' => 'Scouting',
                        'stewardship' => 'Stewardship',
                        'training' => 'Training',
                        'special' => 'Special',
                    ]
                    as
                    $value => $label
                ): ?>
                    <option
                        value="<?= moderation_e($value) ?>"
                        <?= (string) $badge['category'] === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Award type</span>
            <select name="award_type">
                <?php foreach (
                    [
                        'automatic' => 'Automatic',
                        'manual' => 'Manual',
                        'credential' => 'Credential',
                    ]
                    as
                    $value => $label
                ): ?>
                    <option
                        value="<?= moderation_e($value) ?>"
                        <?= (string) $badge['award_type'] === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Threshold</span>
            <input
                type="number"
                name="threshold_value"
                min="0"
                value="<?= $badge['threshold_value'] !== null
                    ? (int) $badge['threshold_value']
                    : '' ?>"
            >
        </label>

        <label>
            <span>Sort order</span>
            <input
                type="number"
                name="sort_order"
                min="0"
                value="<?= (int) $badge['sort_order'] ?>"
            >
        </label>

        <label>
            <span>Font Awesome icon</span>
            <input
                type="text"
                name="icon"
                maxlength="100"
                value="<?= moderation_e(
                    (string) (
                        $badge['icon']
                        ?? ''
                    )
                ) ?>"
            >
        </label>

        <label>
            <span>Source organization</span>
            <input
                type="text"
                name="source_organization"
                maxlength="150"
                value="<?= moderation_e(
                    (string) (
                        $badge['source_organization']
                        ?? ''
                    )
                ) ?>"
            >
        </label>

        <div class="is-wide admin-badge-image-uploader-field">
            <span>Badge image</span>

            <?php if (!empty($badge['image_src'])): ?>
                <div class="admin-badge-current-image">
                    <img
                        src="<?= moderation_e((string) $badge['image_src']) ?>"
                        alt="Current <?= moderation_e((string) $badge['name']) ?> badge"
                    >

                    <div>
                        <strong>Current image</strong>
                        <small>
                            Upload another image below to replace it automatically.
                        </small>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-badge-current-image is-missing">
                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                    <div>
                        <strong>No badge image found</strong>
                        <small>
                            Upload one below. This will also repair badges that were manually uploaded but not linked correctly.
                        </small>
                    </div>
                </div>
            <?php endif; ?>

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
                data-photo-title="Replace badge image"
                data-photo-help="Upload one image. It becomes /uploads/badges/<?= moderation_e((string) $badge['slug']) ?>.jpg automatically."
            ></div>
        </div>

        <label class="is-wide">
            <span>Description</span>
            <textarea
                name="description"
                rows="3"
                maxlength="500"
            ><?= moderation_e(
                (string) (
                    $badge['description']
                    ?? ''
                )
            ) ?></textarea>
        </label>

        <label class="admin-badge-active-field">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                <?= (int) $badge['is_active'] === 1
                    ? 'checked'
                    : '' ?>
            >
            <span>Badge is active</span>
        </label>

    </div>

    <div class="admin-user-form-actions">
        <button
            class="admin-button"
            type="submit"
        >
            Save badge
        </button>
    </div>

</form>

</section>

<?php endif; ?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Recipients</p>
        <h2>Badge Awards</h2>
    </div>

    <span>
        <?= number_format(
            count($recipients)
        ) ?>
    </span>
</header>

<?php if (!$recipients): ?>

<div class="admin-empty-state">
    <p>No one has this badge yet.</p>
</div>

<?php else: ?>

<div class="admin-badge-recipient-list">

<?php foreach ($recipients as $recipient): ?>

<article>

<div class="admin-badge-recipient-copy">
    <strong>
        <a
            href="/user.php?id=<?= (int) $recipient['user_id'] ?>"
        >
            <?= moderation_e(
                (string) $recipient['member_name']
            ) ?>
        </a>
    </strong>

    <?php if (!empty($recipient['username'])): ?>
        <span>
            @<?= moderation_e(
                (string) $recipient['username']
            ) ?>
        </span>
    <?php endif; ?>

    <span>
        <?= moderation_e(
            (string) $recipient['review_status']
        ) ?>
        ·
        <?= moderation_e(
            (string) $recipient['awarded_at']
        ) ?>
        ·
        <?= moderation_e(
            (string) $recipient['awarded_by_name']
        ) ?>
    </span>

    <?php if (!empty($recipient['note'])): ?>
        <p>
            <?= moderation_e(
                (string) $recipient['note']
            ) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($recipient['evidence_url'])): ?>
        <a
            class="admin-inline-link"
            href="<?= moderation_e(
                (string) $recipient['evidence_url']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Evidence
        </a>
    <?php endif; ?>
</div>


<form
    method="post"
    class="admin-badge-revoke-form"
    onsubmit="return confirm('Remove this badge from the member?');"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="badge_id"
        value="<?= (int) $badgeId ?>"
    >

    <input
        type="hidden"
        name="user_badge_id"
        value="<?= (int) $recipient['id'] ?>"
    >

    <input
        type="hidden"
        name="badge_admin_action"
        value="revoke"
    >

    <input
        type="text"
        name="reason"
        maxlength="500"
        placeholder="Reason for removal"
        required
    >

    <button
        class="admin-button is-danger"
        type="submit"
    >
        Remove
    </button>
</form>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</div>


<aside class="admin-badge-detail-side">

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Manual Award</p>
        <h2>Award This Badge</h2>
    </div>
</header>

<form
    method="post"
    class="admin-badge-award-form"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="badge_id"
        value="<?= (int) $badgeId ?>"
    >

    <input
        type="hidden"
        name="badge_admin_action"
        value="award"
    >

    <label>
        <span>Member</span>
        <select
            name="user_id"
            required
        >
            <option value="">
                Choose member
            </option>

            <?php foreach ($users as $member): ?>
                <option
                    value="<?= (int) $member['id'] ?>"
                >
                    <?= moderation_e(
                        (string) $member['name']
                    ) ?>
                    <?php if (!empty($member['username'])): ?>
                        (@<?= moderation_e(
                            (string) $member['username']
                        ) ?>)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <span>Evidence URL</span>
        <input
            type="url"
            name="evidence_url"
            placeholder="Optional"
        >
    </label>

    <label>
        <span>Admin note</span>
        <textarea
            name="note"
            rows="3"
            maxlength="500"
            placeholder="Optional reason, credential, or context"
        ></textarea>
    </label>

    <button
        class="admin-button"
        type="submit"
        <?= (int) $badge['is_active'] !== 1
            ? 'disabled'
            : '' ?>
    >
        Award badge
    </button>

    <?php if ((int) $badge['is_active'] !== 1): ?>
        <small>
            Activate this badge before awarding it.
        </small>
    <?php endif; ?>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Public</p>
        <h2>Badge Page</h2>
    </div>
</header>

<div class="admin-badge-public-link">
    <a
        class="admin-button is-muted"
        href="https://llamascout.com/badges/<?= rawurlencode(
            (string) $badge['slug']
        ) ?>"
        target="_blank"
        rel="noopener"
    >
        View public badge
    </a>
</div>

</section>

</aside>

</div>


<script>
(() => {
    const name =
        document.getElementById(
            'admin-badge-name'
        );

    const slug =
        document.getElementById(
            'admin-badge-slug'
        );

    if (!name || !slug) {
        return;
    }

    let manuallyEdited =
        false;

    const slugify = (value) =>
        value
            .normalize('NFKD')
            .replace(
                /[\u0300-\u036f]/g,
                ''
            )
            .toLowerCase()
            .trim()
            .replace(
                /[^a-z0-9]+/g,
                '-'
            )
            .replace(
                /^-+|-+$/g,
                ''
            );

    slug.addEventListener(
        'input',
        () => {
            manuallyEdited =
                true;
        }
    );

    name.addEventListener(
        'input',
        () => {
            if (!manuallyEdited) {
                slug.value =
                    slugify(
                        name.value
                    );
            }
        }
    );
})();
</script>


<?php
require __DIR__ .
    '/_footer.php';
?>

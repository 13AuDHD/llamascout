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

$badgeThresholdMetricLabels =
    llama_badge_threshold_metric_labels();

$badgeThresholdMetricDescriptions =
    llama_badge_threshold_metric_descriptions();

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

$credentialSubmissions =
    (string) $badge['award_type'] === 'credential'
        ? llama_badge_credential_submissions_for_badge(
            $db,
            $badgeId,
            100
        )
        : [];

$pendingCredentialSubmissions =
    array_values(
        array_filter(
            $credentialSubmissions,
            static fn (array $submission): bool =>
                (string) ($submission['status'] ?? '') === 'pending'
        )
    );

$credentialEventsBySubmission = [];

foreach ($credentialSubmissions as $credentialSubmission) {
    $credentialSubmissionId =
        (int) ($credentialSubmission['id'] ?? 0);

    if ($credentialSubmissionId > 0) {
        $credentialEventsBySubmission[$credentialSubmissionId] =
            llama_badge_credential_events(
                $db,
                $credentialSubmissionId
            );
    }
}


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
        <i aria-hidden="true">
            <?= llama_icon('arrow-left') ?>
        </i>
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

    <?php if ((string) $badge['award_type'] === 'credential'): ?>
    <div>
        <span>Needs Review</span>
        <strong>
            <?= number_format(count($pendingCredentialSubmissions)) ?>
        </strong>
    </div>
    <?php endif; ?>

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
            <select
                name="award_type"
                data-badge-award-type
            >
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

        <label
            data-badge-threshold-field
            <?= (string) $badge['award_type'] === 'automatic'
                ? ''
                : 'hidden' ?>
        >
            <span>Threshold type</span>
            <select
                name="threshold_metric"
                data-badge-threshold-metric
                <?= (string) $badge['award_type'] === 'automatic'
                    ? 'required'
                    : '' ?>
            >
                <option
                    value=""
                    disabled
                    <?= empty($badge['threshold_metric'])
                        ? 'selected'
                        : '' ?>
                >
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
                        <?= (string) (
                            $badge['threshold_metric']
                            ?? ''
                        ) === $metricValue
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e(
                            $metricLabel
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small data-badge-threshold-help>
                <?php
                $currentThresholdMetric =
                    (string) (
                        $badge['threshold_metric']
                        ?? ''
                    );

                echo moderation_e(
                    (string) (
                        $badgeThresholdMetricDescriptions[
                            $currentThresholdMetric
                        ]
                        ?? 'Choose the activity this automatic badge measures.'
                    )
                );
                ?>
            </small>
        </label>

        <label
            data-badge-threshold-field
            <?= (string) $badge['award_type'] === 'automatic'
                ? ''
                : 'hidden' ?>
        >
            <span>Threshold</span>
            <input
                type="number"
                name="threshold_value"
                min="1"
                step="1"
                value="<?= $badge['threshold_value'] !== null
                    ? (int) $badge['threshold_value']
                    : '' ?>"
                <?= (string) $badge['award_type'] === 'automatic'
                    ? 'required'
                    : '' ?>
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
                value="<?= (int) $badge['sort_order'] ?>"
            >
        </label>

        <label>
            <span>Local SVG icon</span>
            <input
                type="text"
                name="icon"
                maxlength="100"
                value="<?= moderation_e(
                    admin_badges_icon_name(
                        $badge['icon'] ?? null
                    )
                ) ?>"
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
                        src="<?= moderation_e(
                        llama_profile_image_url(
                            llama_badge_image_url(
                                (string) $badge['slug'],
                                (string) ($badge['image_src'] ?? '')
                            ),
                            'https://llamascout.com'
                        )
                    ) ?>"
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
                    <i aria-hidden="true"><?= llama_icon('photo') ?></i>
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
                data-photo-help="Upload one image. Transparent PNG files stay PNG. Other image types are optimized as JPEG. The file is named from the badge slug automatically."
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


<?php if ((string) $badge['award_type'] === 'credential'): ?>

<section class="admin-panel admin-badge-credential-panel">

<header class="admin-panel-header">
    <div>
        <p>Credential Review</p>
        <h2>Submissions</h2>
    </div>
    <span><?= number_format(count($credentialSubmissions)) ?></span>
</header>

<?php if (!$credentialSubmissions): ?>
    <div class="admin-empty-state">
        <p>No credential submissions yet.</p>
    </div>
<?php else: ?>

<div class="admin-badge-credential-history">
<?php foreach ($credentialSubmissions as $submission): ?>
    <?php
    $submissionStatus = (string) $submission['status'];
    $submissionHasPreview =
        str_starts_with(
            (string) $submission['mime_type'],
            'image/'
        );
    ?>
    <article class="admin-badge-credential-record is-<?= moderation_e($submissionStatus) ?><?= $submissionHasPreview ? ' has-preview' : '' ?>">
        <div class="admin-badge-credential-record-main">
            <span>
                Submission #<?= (int) $submission['id'] ?>
                · <?= moderation_e(ucfirst($submissionStatus)) ?>
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

            <?php if (!empty($submission['reviewed_at'])): ?>
                <small>
                    Reviewed
                    <?= moderation_e(
                        llama_format_viewer_datetime(
                            (string) $submission['reviewed_at']
                        )
                    ) ?>
                    <?php if (!empty($submission['reviewer_name'])): ?>
                        · <?= moderation_e((string) $submission['reviewer_name']) ?>
                    <?php endif; ?>
                </small>
            <?php endif; ?>

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
                <p>
                    <strong>Member note:</strong>
                    <?= moderation_e((string) $submission['member_note']) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($submission['review_note'])): ?>
                <p>
                    <strong>Review note:</strong>
                    <?= moderation_e((string) $submission['review_note']) ?>
                </p>
            <?php endif; ?>

            <details class="admin-badge-credential-provenance">
                <summary>Provenance</summary>
                <dl>
                    <div>
                        <dt>Original file</dt>
                        <dd><?= moderation_e((string) $submission['original_filename']) ?></dd>
                    </div>
                    <div>
                        <dt>MIME type</dt>
                        <dd><?= moderation_e((string) $submission['mime_type']) ?></dd>
                    </div>
                    <div>
                        <dt>SHA-256</dt>
                        <dd><code><?= moderation_e((string) $submission['sha256']) ?></code></dd>
                    </div>
                    <?php if (!empty($submission['approved_user_badge_id'])): ?>
                    <div>
                        <dt>Award record</dt>
                        <dd>User badge #<?= (int) $submission['approved_user_badge_id'] ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>

                <?php
                $submissionEvents =
                    $credentialEventsBySubmission[
                        (int) $submission['id']
                    ]
                    ?? [];
                ?>

                <?php if ($submissionEvents): ?>
                    <ol class="admin-badge-credential-event-list">
                        <?php foreach ($submissionEvents as $event): ?>
                            <li>
                                <strong>
                                    <?= moderation_e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string) $event['event_type']
                                            )
                                        )
                                    ) ?>
                                </strong>
                                <span>
                                    <?= moderation_e(
                                        llama_format_viewer_datetime(
                                            (string) $event['created_at']
                                        )
                                    ) ?>
                                    · <?= moderation_e((string) $event['actor_name']) ?>
                                </span>
                                <?php if (!empty($event['event_note'])): ?>
                                    <p><?= moderation_e((string) $event['event_note']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </details>

            <a
                class="admin-button is-muted admin-credential-file-link"
                href="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>"
            >
                Download private evidence
            </a>
        </div>

        <?php if ($submissionHasPreview): ?>
            <a
                class="admin-badge-credential-preview"
                href="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>"
            >
                <img
                    src="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>&amp;inline=1"
                    alt="Private credential evidence preview"
                    loading="lazy"
                >
            </a>
        <?php endif; ?>

        <?php if ($submissionStatus === 'pending'): ?>
        <div class="admin-badge-credential-pending-action">
            <div>
                <strong>Pending review</strong>
                <span>
                    Credential decisions are handled in the main Badges review queue.
                </span>
            </div>
            <a
                class="admin-button"
                href="/badges.php"
            >
                Review in queue
            </a>
        </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>

<?php endif; ?>

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
            llama_format_viewer_datetime(
                (string) $recipient['awarded_at']
            )
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

<section class="admin-panel admin-badge-award-panel">

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

    <div
        class="admin-badge-member-picker"
        data-badge-member-picker
        data-search-endpoint="/badge-member-search.php"
        data-badge-id="<?= (int) $badgeId ?>"
    >
        <label for="badge-award-member-search">
            Member
        </label>

        <div class="admin-badge-member-search-wrap">
            <input
                id="badge-award-member-search"
                class="admin-badge-member-search"
                type="search"
                placeholder="Search name, @handle, email, or user ID"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="badge-award-member-results"
                aria-expanded="false"
                data-badge-member-search
                hidden
            >

            <input
                type="hidden"
                data-badge-member-id
            >

            <div
                id="badge-award-member-results"
                class="admin-badge-member-results"
                role="listbox"
                data-badge-member-results
                hidden
            ></div>
        </div>

        <input
            class="admin-badge-member-fallback"
            type="number"
            name="user_id"
            min="1"
            inputmode="numeric"
            placeholder="Member user ID"
            required
            data-badge-member-fallback
        >

        <small
            class="admin-badge-member-help"
            data-badge-member-help
            hidden
        >
            Search by name, handle, email, or user ID.
        </small>
    </div>

    <?php if ((string) $badge['award_type'] !== 'credential'): ?>
        <label>
            <span>Evidence URL</span>
            <input
                type="url"
                name="evidence_url"
                maxlength="500"
                placeholder="Optional external evidence link"
            >
        </label>
    <?php endif; ?>

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


<script src="https://llamascout.com/js/admin/badges.js"></script>

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

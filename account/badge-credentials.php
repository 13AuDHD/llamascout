<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/badge-credentials.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();

function badge_credentials_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$notice = '';
$error = '';

if (isset($_GET['submitted'])) {
    $notice = 'Credential submitted for review.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !llama_badge_credential_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['credential_action'] ?? ''));

            if ($action !== 'submit') {
                throw new RuntimeException('Credential request is invalid.');
            }

            $submissionId = llama_badge_credential_submit(
                $db,
                $userId,
                (int) ($_POST['badge_id'] ?? 0),
                isset($_FILES['credential_file'])
                    && is_array($_FILES['credential_file'])
                        ? $_FILES['credential_file']
                        : [],
                $_POST
            );

            header(
                'Location: /badge-credentials.php?submitted=' . $submissionId,
                true,
                303
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$storageReady = llama_badge_credentials_storage_ready($db);
$credentialBadges = llama_badge_credential_badges_for_user($db, $userId);
$history = $storageReady
    ? llama_badge_credential_history_for_user($db, $userId)
    : [];

$config = llama_config();
$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$pageTitle = 'Credential Badges | Llama Scout';
$pageDescription = '';
$pageRobots = 'noindex,nofollow';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= badge_credentials_e($siteUrl . '/css/account/pages/badge-credentials.css') ?>"
>

<section class="account-page badge-credentials-page">

<header class="account-page-header badge-credentials-header">
    <div>
        <a class="badge-credentials-back" href="/">
            <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
            Back to account
        </a>
        <p class="account-eyebrow">Recognition</p>
        <h1>Credential badges</h1>
        <p>
            Submit proof for training, stewardship, and other credential-based badges.
            Your certificate stays private and is only available to you and Llama Scout reviewers.
        </p>
    </div>
</header>

<?php if ($notice !== ''): ?>
    <div class="account-notice is-success">
        <?= badge_credentials_e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="account-notice is-error">
        <?= badge_credentials_e($error) ?>
    </div>
<?php endif; ?>

<?php if (!$storageReady): ?>
    <section class="account-section">
        <div class="account-empty-state">
            <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
            <h2>Credential submissions are temporarily unavailable</h2>
            <p>The credential review system has not finished being installed yet.</p>
        </div>
    </section>
<?php elseif (!$credentialBadges): ?>
    <section class="account-section">
        <div class="account-empty-state">
            <i aria-hidden="true"><?= llama_icon('certificate') ?></i>
            <h2>No credential badges are available yet</h2>
            <p>Credential-based badges will appear here when they are active.</p>
        </div>
    </section>
<?php else: ?>

<section class="account-section" aria-labelledby="credential-options-heading">
    <div class="account-section-heading">
        <div>
            <p class="account-eyebrow">Available credentials</p>
            <h2 id="credential-options-heading">Submit a credential</h2>
        </div>
        <span class="account-section-count"><?= count($credentialBadges) ?></span>
    </div>

    <div class="badge-credential-grid">

    <?php foreach ($credentialBadges as $badge): ?>
        <?php
        $earned = is_array($badge['earned_award'] ?? null);
        $latest = is_array($badge['latest_submission'] ?? null)
            ? $badge['latest_submission']
            : null;
        $latestStatus = strtolower(trim((string) ($latest['status'] ?? '')));
        $canSubmit = !$earned && $latestStatus !== 'pending';
        $badgeImage = llama_badge_image_url(
            (string) ($badge['slug'] ?? ''),
            (string) ($badge['image_src'] ?? '')
        );

        $badgeIcon = strtolower(
            trim(
                (string) (
                    $badge['icon']
                    ?? ''
                )
            )
        );

        if (
            $badgeIcon === ''
            || !preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $badgeIcon
            )
        ) {
            $badgeIcon = '';
        }

        $badgeIconMarkup =
            $badgeIcon !== ''
                ? llama_icon($badgeIcon)
                : '';

        if ($badgeIconMarkup === '') {
            $badgeIconMarkup =
                llama_icon('certificate');
        }
        ?>

        <article class="badge-credential-card">
            <div class="badge-credential-card-heading">
                <div class="badge-credential-art">
                    <?php if ($badgeImage !== ''): ?>
                        <img
                            src="<?= badge_credentials_e(
                                llama_profile_image_url($badgeImage, $siteUrl)
                            ) ?>"
                            alt=""
                            loading="lazy"
                        >
                    <?php else: ?>
                        <i aria-hidden="true"><?= $badgeIconMarkup ?></i>
                    <?php endif; ?>
                </div>

                <div>
                    <p>
                        <?= badge_credentials_e(
                            (string) ($badge['source_organization'] ?: 'Credential')
                        ) ?>
                    </p>
                    <h3><?= badge_credentials_e((string) $badge['name']) ?></h3>
                    <?php if (!empty($badge['description'])): ?>
                        <span><?= badge_credentials_e((string) $badge['description']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="badge-credential-status <?= $earned ? 'is-earned' : ($latestStatus !== '' ? 'is-' . badge_credentials_e($latestStatus) : '') ?>">
                <?php if ($earned): ?>
                    <strong>Earned</strong>
                    <span>This badge is already on your account.</span>
                <?php elseif ($latestStatus === 'pending'): ?>
                    <strong>In review</strong>
                    <span>
                        Submitted
                        <?= badge_credentials_e(
                            llama_format_viewer_datetime((string) $latest['submitted_at'])
                        ) ?>.
                    </span>
                <?php elseif ($latestStatus === 'declined'): ?>
                    <strong>Needs resubmission</strong>
                    <span>Your last submission was declined. You can submit new evidence below.</span>
                    <?php if (!empty($latest['review_note'])): ?>
                        <p><?= badge_credentials_e((string) $latest['review_note']) ?></p>
                    <?php endif; ?>
                <?php elseif ($latestStatus === 'approved'): ?>
                    <strong>Previously approved</strong>
                    <span>The prior credential was approved, but this badge is not currently on your account.</span>
                <?php else: ?>
                    <strong>Not submitted</strong>
                    <span>Upload a certificate or other credential proof for review.</span>
                <?php endif; ?>
            </div>

            <?php if ($canSubmit): ?>
                <form
                    method="post"
                    enctype="multipart/form-data"
                    class="badge-credential-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= badge_credentials_e(llama_badge_credential_csrf_token()) ?>"
                    >
                    <input type="hidden" name="credential_action" value="submit">
                    <input type="hidden" name="badge_id" value="<?= (int) $badge['id'] ?>">

                    <label class="is-wide">
                        <span>Certificate or proof</span>
                        <input
                            type="file"
                            name="credential_file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/*"
                            required
                        >
                        <small>PDF or image, up to 12 MB. This file is stored privately.</small>
                    </label>

                    <label>
                        <span>Credential or certificate number</span>
                        <input
                            type="text"
                            name="credential_identifier"
                            maxlength="150"
                            autocomplete="off"
                            placeholder="Optional"
                        >
                    </label>

                    <label>
                        <span>Issued date</span>
                        <input type="date" name="issued_on">
                    </label>

                    <label>
                        <span>Expiration date</span>
                        <input type="date" name="expires_on">
                    </label>

                    <label class="is-wide">
                        <span>Note for the reviewer</span>
                        <textarea
                            name="member_note"
                            rows="3"
                            maxlength="1000"
                            placeholder="Optional context about this credential"
                        ></textarea>
                    </label>

                    <button class="account-button is-primary" type="submit">
                        Submit for review
                    </button>
                </form>
            <?php endif; ?>

            <a
                class="badge-credential-public-link"
                href="<?= badge_credentials_e(
                    $siteUrl . '/badges/' . rawurlencode((string) $badge['slug'])
                ) ?>"
            >
                View badge details
                <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
            </a>
        </article>
    <?php endforeach; ?>

    </div>
</section>

<?php endif; ?>

<?php if ($history): ?>
<section class="account-section" aria-labelledby="credential-history-heading">
    <div class="account-section-heading">
        <div>
            <p class="account-eyebrow">Private history</p>
            <h2 id="credential-history-heading">Your submissions</h2>
        </div>
        <span class="account-section-count"><?= count($history) ?></span>
    </div>

    <div class="badge-credential-history">
        <?php foreach ($history as $submission): ?>
            <?php $status = strtolower((string) $submission['status']); ?>
            <article>
                <div>
                    <strong><?= badge_credentials_e((string) $submission['badge_name']) ?></strong>
                    <span>
                        Submission #<?= (int) $submission['id'] ?> ·
                        <?= badge_credentials_e(
                            llama_format_viewer_datetime((string) $submission['submitted_at'])
                        ) ?>
                    </span>
                    <small>
                        <?= badge_credentials_e((string) $submission['original_filename']) ?> ·
                        <?= badge_credentials_e(
                            llama_badge_credential_format_bytes((int) $submission['file_size'])
                        ) ?>
                    </small>
                    <?php if (!empty($submission['review_note'])): ?>
                        <p><?= badge_credentials_e((string) $submission['review_note']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="badge-credential-history-actions">
                    <span class="badge-credential-pill is-<?= badge_credentials_e($status) ?>">
                        <?= badge_credentials_e(ucfirst($status)) ?>
                    </span>
                    <a
                        class="account-button"
                        href="/badge-credential-file.php?id=<?= (int) $submission['id'] ?>"
                    >
                        Download file
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

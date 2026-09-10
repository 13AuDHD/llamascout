<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-drafts.php';

require_verified_email();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$error = '';
$notice = isset($_GET['saved']) ? 'Place saved for later.' : '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['delete_draft'])
) {
    if (!llama_place_draft_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload the page and try again.';
    } else {
        $draftId = (int) ($_POST['draft_id'] ?? 0);

        if (
            $draftId < 1
            || !llama_place_draft_delete($db, $userId, $draftId)
        ) {
            $error = 'That saved Place could not be found.';
        } else {
            header('Location: /saved-later.php?deleted=1', true, 303);
            exit;
        }
    }
}

if (isset($_GET['deleted'])) {
    $notice = 'Saved Place removed.';
}

$drafts = llama_place_drafts_for_user($db, $userId);
$config = llama_config();
$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$pageTitle = 'Saved for Later | Llama Scout';
$pageRobots = 'noindex,nofollow';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $siteUrl . '/css/account/pages/saved-later.css',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<section class="account-page saved-later-page">

    <header class="account-page-header saved-later-header">
        <div>
            <p class="account-eyebrow">Contributions</p>
            <h1>Saved for Later</h1>
            <p>
                Continue Place reports you started in the field without sending
                incomplete information to moderation.
            </p>
        </div>

        <a
            class="contribution-submit"
            href="<?= htmlspecialchars(
                $siteUrl . '/add-place.php',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Start a Place
        </a>
    </header>

    <?php if ($notice !== ''): ?>
        <div class="contribution-message is-success" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="contribution-message is-error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$drafts): ?>
        <div class="account-empty-state">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            <h2>Nothing saved for later</h2>
            <p>
                Start a Place report and use Save for Later whenever you want
                to finish it another time.
            </p>
        </div>
    <?php else: ?>
        <div class="saved-later-list">
            <?php foreach ($drafts as $draft): ?>
                <?php
                $draftId = (int) ($draft['id'] ?? 0);
                $draftData = is_array($draft['form_data'] ?? null)
                    ? $draft['form_data']
                    : [];
                $draftPhotos = is_array($draft['photos'] ?? null)
                    ? $draft['photos']
                    : [];
                $progress = llama_place_draft_progress($draftData, count($draftPhotos));
                $draftName = trim((string) ($draft['draft_name'] ?? 'Untitled Place'));
                if ($draftName === '') {
                    $draftName = 'Untitled Place';
                }
                ?>

                <article class="saved-later-card">
                    <div class="saved-later-card-main">
                        <div class="saved-later-title-row">
                            <div>
                                <p class="account-eyebrow">Saved Place</p>
                                <h2><?= htmlspecialchars($draftName, ENT_QUOTES, 'UTF-8') ?></h2>
                            </div>

                            <span class="saved-later-percent">
                                <?= (int) $progress['completion_percent'] ?>%
                            </span>
                        </div>

                        <div
                            class="saved-later-progress"
                            aria-label="<?= (int) $progress['completion_percent'] ?> percent of the Place form completed"
                        >
                            <span style="width: <?= (int) $progress['completion_percent'] ?>%;"></span>
                        </div>

                        <div class="saved-later-stats">
                            <div>
                                <span>Form completed</span>
                                <strong><?= (int) $progress['completion_percent'] ?>%</strong>
                            </div>

                            <div>
                                <span>Point estimate</span>
                                <strong><?= (int) $progress['estimated_points'] ?>/100</strong>
                            </div>

                            <div>
                                <span>Point categories started</span>
                                <strong><?= (int) $progress['categories_started'] ?>/10</strong>
                            </div>

                            <div>
                                <span>Photos</span>
                                <strong><?= count($draftPhotos) ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($progress['minimum_ready'])): ?>
                            <p class="saved-later-readiness is-ready">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                Minimum submission information is present.
                            </p>
                        <?php else: ?>
                            <p class="saved-later-readiness">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                Still needed for the minimum:
                                <?= htmlspecialchars(
                                    implode(', ', (array) ($progress['missing_minimum'] ?? [])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>.
                            </p>
                        <?php endif; ?>

                        <p class="saved-later-updated">
                            Last saved
                            <?= htmlspecialchars(
                                llama_format_viewer_date(
                                    (string) ($draft['updated_at'] ?? ''),
                                    'M j, Y g:i a T'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>

                    <div class="saved-later-card-actions">
                        <a
                            class="saved-later-continue"
                            href="<?= htmlspecialchars(
                                $siteUrl . '/add-place.php?draft=' . $draftId,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            Continue editing
                        </a>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    llama_place_draft_csrf_token(),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                            <input type="hidden" name="draft_id" value="<?= $draftId ?>">

                            <button
                                type="submit"
                                name="delete_draft"
                                value="1"
                                class="saved-later-delete"
                            >
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                Delete
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="saved-later-estimate-note">
            The point estimate uses the proposed 10-category model. Amenities and
            Connectivity earn their category value once any information is supplied.
            Other categories are weighted by how much has been answered. Final
            contribution scoring can be adjusted later without changing saved drafts.
        </p>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

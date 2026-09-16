<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$items = community_submissions_for_user($userId);

/*
 * Approved new-Place submissions retain the published place_id,
 * but the community contribution history helper does not currently
 * expose that Place slug. Resolve those approved submissions here
 * so contributors can go directly to the Place they created.
 */
$approvedNewSubmissionIds = [];

foreach ($items as $item) {
    if (
        (string) ($item['kind'] ?? '') === 'new-place'
        && (string) ($item['status'] ?? '') === 'approved'
        && empty($item['slug'])
        && (int) ($item['id'] ?? 0) > 0
    ) {
        $approvedNewSubmissionIds[] =
            (int) $item['id'];
    }
}

if ($approvedNewSubmissionIds) {
    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($approvedNewSubmissionIds),
                '?'
            )
        );

    $stmt =
        db()->prepare(
            'SELECT
                ps.id AS submission_id,
                p.slug
             FROM place_submissions ps
             INNER JOIN places p
                ON p.id = ps.place_id
             WHERE ps.user_id = ?
               AND ps.status = "approved"
               AND p.status IN ("active", "featured")
               AND ps.id IN (' . $placeholders . ')'
        );

    $stmt->execute(
        array_merge(
            [$userId],
            $approvedNewSubmissionIds
        )
    );

    $approvedPlaceSlugs = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        as
        $row
    ) {
        $submissionId =
            (int) (
                $row['submission_id']
                ?? 0
            );

        $slug =
            trim(
                (string) (
                    $row['slug']
                    ?? ''
                )
            );

        if (
            $submissionId > 0
            && $slug !== ''
        ) {
            $approvedPlaceSlugs[
                $submissionId
            ] =
                $slug;
        }
    }

    foreach ($items as &$item) {
        if (
            (string) ($item['kind'] ?? '') !== 'new-place'
            || (string) ($item['status'] ?? '') !== 'approved'
        ) {
            continue;
        }

        $submissionId =
            (int) (
                $item['id']
                ?? 0
            );

        if (
            isset(
                $approvedPlaceSlugs[
                    $submissionId
                ]
            )
        ) {
            $item['slug'] =
                $approvedPlaceSlugs[
                    $submissionId
                ];
        }
    }
    unset($item);
}

$submitted = (string) ($_GET['submitted'] ?? '');
$successMessage =
    trim(
        (string) (
            $_SESSION['contribution_success_message']
            ?? ''
        )
    );

$successSubmissionId =
    (int) (
        $_SESSION['contribution_success_submission_id']
        ?? 0
    );

unset(
    $_SESSION['contribution_success_message'],
    $_SESSION['contribution_success_submission_id']
);

$config = llama_config();

$siteUrl =
    rtrim(
        (string) (
            $config['app']['url']
            ?? 'https://llamascout.com'
        ),
        '/'
    );

$pageTitle = 'My Contributions | Llama Scout';
$pageRobots = 'noindex,nofollow';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $siteUrl . '/css/account/pages/contributions.css',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<section class="account-page contribution-history-page">

    <header class="account-page-header">

        <div>
            <p class="account-eyebrow">Community</p>
            <h1>My contributions</h1>
        </div>

        <a
            class="contribution-submit"
            href="<?= htmlspecialchars(
                $siteUrl . '/add-place.php',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <i aria-hidden="true"><?= llama_icon('plus') ?></i>
            Add a place
        </a>

    </header>

    <?php if ($successMessage !== '' || $submitted !== ''): ?>

        <div
            class="contribution-message is-success"
            role="status"
            aria-live="polite"
        >
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>

            <div>
                <strong>
                    <?= $successMessage !== ''
                        ? 'Changes resubmitted'
                        : 'Submission received' ?>
                </strong>

                <span>
                    <?= htmlspecialchars(
                        $successMessage !== ''
                            ? $successMessage
                            : (
                                in_array(
                                    $submitted,
                                    [
                                        'update-resubmitted',
                                        'new-resubmitted',
                                    ],
                                    true
                                )
                                    ? 'Your changes were resubmitted successfully and are back in review.'
                                    : 'Your contribution was submitted for review.'
                            ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </div>
        </div>

    <?php endif; ?>

    <?php if (!$items): ?>

        <div class="account-empty-state">
            <i aria-hidden="true"><?= llama_icon('route') ?></i>

            <h2>No contributions yet</h2>

            <p>
                Add a new place or suggest a correction
                when something has changed.
            </p>
        </div>

    <?php else: ?>

        <div class="contribution-history-list">

            <?php foreach ($items as $item): ?>

                <?php
                $status =
                    (string) (
                        $item['status']
                        ?? 'pending'
                    );

                $statusLabel =
                    ucwords(
                        str_replace(
                            '-',
                            ' ',
                            $status
                        )
                    );

                $slug =
                    trim(
                        (string) (
                            $item['slug']
                            ?? ''
                        )
                    );
                ?>

                <article class="contribution-history-card">

                    <div class="contribution-history-main">

                        <p class="account-eyebrow">
                            <?= htmlspecialchars(
                                (string) $item['label'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <h2>
                            <?= htmlspecialchars(
                                (string) $item['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h2>

                        <p class="contribution-history-date">
                            Submitted
                            <?= htmlspecialchars(
                                llama_format_viewer_date(
                                    (string) $item['submitted_at'],
                                    'M j, Y'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <?php if (!empty($item['review_notes'])): ?>

                            <p class="contribution-review-note">
                                <?= nl2br(
                                    htmlspecialchars(
                                        (string) $item['review_notes'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                    <div class="contribution-history-side">

                        <span
                            class="contribution-status status-<?= htmlspecialchars(
                                $status,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                $statusLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <?php if (
                            $status === 'approved'
                            && $slug !== ''
                        ): ?>

                            <a
                                class="contribution-view-place"
                                href="<?= htmlspecialchars(
                                    $siteUrl
                                    . '/place.php?slug='
                                    . rawurlencode($slug),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                View place
                            </a>

                        <?php elseif (
                            (string) ($item['kind'] ?? '') === 'update'
                            && $slug !== ''
                        ): ?>

                            <a
                                href="<?= htmlspecialchars(
                                    $siteUrl
                                    . '/place.php?slug='
                                    . rawurlencode($slug),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                View place
                            </a>

                        <?php endif; ?>

                        <?php if (
                            $status === 'needs-changes'
                            && (string) ($item['kind'] ?? '') === 'new-place'
                        ): ?>

                            <a
                                class="contribution-resubmit-link"
                                href="<?= htmlspecialchars(
                                    $siteUrl
                                    . '/add-place.php?submission='
                                    . (int) $item['id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <i aria-hidden="true"><?= llama_icon('edit') ?></i>
                                Edit &amp; resubmit
                            </a>

                        <?php endif; ?>

                        <?php if (
                            $status === 'needs-changes'
                            && (string) ($item['kind'] ?? '') === 'update'
                            && $slug !== ''
                        ): ?>

                            <a
                                class="contribution-resubmit-link"
                                href="<?= htmlspecialchars(
                                    $siteUrl
                                    . '/account/update-place.php?slug='
                                    . rawurlencode($slug),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <i aria-hidden="true"><?= llama_icon('edit') ?></i>
                                Edit &amp; resubmit
                            </a>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

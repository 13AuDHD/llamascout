<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/site-submissions.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$ready = llama_site_submissions_ready($db);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload before reviewing this Site.';
    } elseif (!$ready) {
        $error = 'Install the standalone Site submissions migration first.';
    } else {
        try {
            $siteId = llama_site_submission_moderate(
                $db,
                (int) ($_POST['submission_id'] ?? 0),
                (int) ($adminUser['id'] ?? 0),
                (string) ($_POST['site_decision'] ?? ''),
                (string) ($_POST['review_notes'] ?? '')
            );
            header('Location: /site-submissions.php?reviewed=1'
                . ($siteId ? '&site=' . $siteId : ''), true, 303);
            exit;
        } catch (Throwable $exception) {
            llama_log_caught_exception($exception, 'admin.site.review',
                ['actor_id' => (int) ($adminUser['id'] ?? 0)]);
            $error = $exception instanceof InvalidArgumentException
                || ($exception instanceof RuntimeException
                    && str_contains($exception->getMessage(), 'already exists'))
                ? $exception->getMessage()
                : 'This Site review could not be saved. No Site was published.';
        }
    }
}

$items = $ready ? llama_site_submission_queue($db, 100) : [];
$siteFields = llama_site_submission_fields();
$adminPageTitle = 'New Site Submissions';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';
$e = static fn (mixed $v): string => moderation_e($v);
require __DIR__ . '/_header.php';
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Site reviews</p>
        <h1>New Sites</h1>
        <p>Review and publish one Site under its existing parent Place.
           Approval never changes the parent Place Report or sibling Sites.</p>
    </div>
</section>

<?php if (!$ready): ?>
    <div class="admin-notice is-error" role="alert">
        Install <code>tools/migrations/20261118_standalone_site_submissions.sql</code>
        before reviewing new Site submissions.
    </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="admin-notice is-error" role="alert"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (isset($_GET['reviewed'])): ?>
    <div class="admin-notice" role="status">
        Site review saved. <?= isset($_GET['site']) ? 'Published Site #' . (int) $_GET['site'] : '' ?>
    </div>
<?php endif; ?>

<?php if (!$items): ?>
    <section class="admin-panel"><p>There are no new Sites awaiting review.</p></section>
<?php else: ?>
    <?php foreach ($items as $item): ?>
        <?php
        $data = json_decode((string) $item['submission_data'], true);
        $data = is_array($data) ? $data : [];
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $photos = llama_site_submission_photos($data, (int) $item['id']);
        ?>
        <article class="admin-panel" id="site-submission-<?= (int) $item['id'] ?>">
            <header class="admin-panel-header">
                <div>
                    <p>Site submission #<?= (int) $item['id'] ?>
                       · <?= $e($item['contributor_name'] ?? 'Member') ?></p>
                    <h2><?= $e($item['site_name']) ?></h2>
                    <p>Parent Place: <?= $e($item['place_name']) ?>
                       (#<?= (int) $item['place_id'] ?>)
                       · Visited <?= $e($data['visit_date'] ?? 'Unknown') ?></p>
                    <p><?= is_array($data['coordinates'] ?? null)
                        ? 'Site coordinates: ' . $e($data['coordinates']['latitude'] ?? '')
                            . ', ' . $e($data['coordinates']['longitude'] ?? '')
                        : 'No Site marker submitted; coordinates can be added later.' ?></p>
                </div>
            </header>
            <details>
                <summary>Review <?= count($answers) ?> Site answers</summary>
                <dl>
                    <?php foreach ($answers as $key => $value): ?>
                        <?php if (!isset($siteFields[$key])) continue; ?>
                        <dt><strong><?= $e($siteFields[$key]['label']) ?></strong></dt>
                        <dd><?= $e($value === llama_place_report_unknown_token()
                            ? '? Unknown'
                            : (is_array($value) ? implode(', ', array_map('strval', $value))
                                : (is_bool($value) ? ($value ? 'Yes' : 'No') : $value))) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </details>
            <?php if ($photos): ?>
                <details>
                    <summary>Review <?= count($photos) ?> submitted Site photos</summary>
                    <div class="admin-site-review-photos">
                        <?php foreach ($photos as $photo): ?>
                            <figure>
                                <img src="<?= $e(llama_photo_public_url((string) $photo['path'])) ?>"
                                     alt="<?= $e($photo['alt'] ?? 'Submitted Site photo') ?>"
                                     loading="lazy" class="admin-site-review-photo">
                                <figcaption><?= $e($photo['alt'] ?? '') ?></figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <form method="post" action="/site-submissions.php#site-submission-<?= (int) $item['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
                <input type="hidden" name="submission_id" value="<?= (int) $item['id'] ?>">
                <label>
                    Review notes (required when rejecting or requesting changes)
                    <textarea name="review_notes" rows="3" maxlength="4000"></textarea>
                </label>
                <div class="admin-actions">
                    <button class="admin-button" type="submit"
                            name="site_decision" value="approved">Approve &amp; publish Site</button>
                    <button class="admin-button is-muted" type="submit"
                            name="site_decision" value="needs-changes">Request Changes</button>
                    <button class="admin-button is-muted" type="submit"
                            name="site_decision" value="rejected">Reject Site</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>

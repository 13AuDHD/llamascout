<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/operating-closures.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'Your session expired. Reload the page and try again.';
    } else {
        try {
            llama_closure_save($db, $_POST, (int) ($adminUser['id'] ?? 0));
            header('Location: /closed-places.php?updated=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            $error = 'Unable to save the status. Check the closure database table.';
        }
    }
}

try {
    $closed = llama_closure_admin_list($db);
} catch (PDOException $exception) {
    $closed = [];
    $error = 'Unable to load verified closures. Check the closure database table.';
}

// Admin-only searchable Place picker: IDs never need to be memorized.
$placeChoices = [];
try {
    $placeChoices = $db->query(
        'SELECT id, name, state FROM places WHERE status NOT IN ("removed", "archived") ORDER BY name ASC, id ASC LIMIT 10000'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $exception) {
    $error = 'Unable to load the Place selector.';
}

$today = date('Y-m-d');
$overdueCount = 0;
$upcomingCount = 0;
foreach ($closed as $item) {
    $date = (string) ($item['review_date'] ?? '');
    if ($date !== '' && $date <= $today) {
        ++$overdueCount;
    } elseif ($date !== '') {
        ++$upcomingCount;
    }
}

$stats = admin_dashboard_stats($db);
$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];
$adminPageTitle = 'Closed Places';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'closed_places';
require __DIR__ . '/_header.php';
?>

<div class="closure-admin">
    <header class="closure-page-header">
        <div>
            <p class="eyebrow">Operating status &amp; verification</p>
            <h2>Closure management</h2>
            <p>Verify closures, schedule follow-up reviews, and manage reopening without removing Places from Llama Scout.</p>
        </div>
        <a class="admin-button is-muted" href="/places.php">All Places</a>
    </header>

    <?php if ($error !== ''): ?>
        <div class="closure-notice is-error" role="alert"><?= moderation_e($error) ?></div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="closure-notice is-success" role="status">Operating status saved and verified.</div>
    <?php endif; ?>

    <section class="closure-summary-grid" aria-label="Closure overview">
        <article class="closure-summary-card is-confirmed">
            <span class="closure-summary-label">Confirmed closures</span>
            <strong><?= count($closed) ?></strong>
            <small>Currently verified as closed</small>
        </article>
        <article class="closure-summary-card is-due">
            <span class="closure-summary-label">Due for review</span>
            <strong><?= $overdueCount ?></strong>
            <small>Review date reached or overdue</small>
        </article>
        <article class="closure-summary-card is-upcoming">
            <span class="closure-summary-label">Upcoming reviews</span>
            <strong><?= $upcomingCount ?></strong>
            <small>Scheduled for a future check</small>
        </article>
    </section>

    <section class="admin-panel closure-editor-panel" aria-labelledby="closure-editor-title">
        <header class="admin-panel-header">
            <div>
                <p>Administrator verification</p>
                <h2 id="closure-editor-title">Record or update an operating status</h2>
            </div>
        </header>

        <div class="admin-panel-body">
            <p class="closure-help">Select the Place first. Add a campsite ID only when the closure applies to one individual campsite.</p>

            <form method="post" action="/closed-places.php" class="closure-form admin-form" id="closure-form">
                <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">

                <div class="closure-form-grid">
                    <label class="closure-field closure-span-8">
                        <span>Place <abbr title="Required">*</abbr></span>
                        <select name="place_id" id="closure-place-id" required>
                            <option value="">Choose a Place:</option>
                            <?php foreach ($placeChoices as $place): ?>
                                <option value="<?= (int) $place['id'] ?>" <?= (string) ($_POST['place_id'] ?? '') === (string) $place['id'] ? 'selected' : '' ?>><?= moderation_e((string) $place['name'] . ' · ' . (string) ($place['state'] ?? '') . ' · #' . $place['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Campsite ID <small>optional</small></span>
                        <input type="number" name="campsite_id" min="1" inputmode="numeric" value="<?= moderation_e((string) ($_POST['campsite_id'] ?? '')) ?>" placeholder="Entire Place if blank">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Operating status <abbr title="Required">*</abbr></span>
                        <select name="status" id="closure-status" required>
                            <?php foreach (['temporary' => 'Temporarily closed', 'seasonal' => 'Seasonally closed', 'permanent' => 'Permanently closed', 'open' => 'Open â verified reopening'] as $key => $label): ?>
                                <option value="<?= moderation_e($key) ?>" <?= (string) ($_POST['status'] ?? 'temporary') === $key ? 'selected' : '' ?>><?= moderation_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Reason</span>
                        <select name="reason">
                            <?php foreach (['unknown','maintenance','construction','weather','fire','flooding','road_damage','administrative','other'] as $reason): ?>
                                <option value="<?= moderation_e($reason) ?>" <?= (string) ($_POST['reason'] ?? 'unknown') === $reason ? 'selected' : '' ?>><?= moderation_e(ucwords(str_replace('_', ' ', $reason))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Closure began</span>
                        <input type="date" name="closure_began" value="<?= moderation_e((string) ($_POST['closure_began'] ?? '')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Expected reopening <small>tentative</small></span>
                        <input type="date" name="expected_reopening" value="<?= moderation_e((string) ($_POST['expected_reopening'] ?? '')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Next review date</span>
                        <input type="date" name="review_date" value="<?= moderation_e((string) ($_POST['review_date'] ?? '')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Official source URL</span>
                        <input type="url" name="source_url" maxlength="2048" placeholder="https://" value="<?= moderation_e((string) ($_POST['source_url'] ?? '')) ?>">
                    </label>

                    <label class="closure-field closure-span-12">
                        <span>Verification notes</span>
                        <textarea name="notes" rows="3" maxlength="10000" placeholder="What was verified and where?"><?= moderation_e((string) ($_POST['notes'] ?? '')) ?></textarea>
                    </label>
                </div>

                <div class="closure-form-actions">
                    <div>
                        <button class="admin-button" type="submit">Save verified status</button>
                        <button class="admin-button is-muted" type="reset">Clear form</button>
                    </div>
                    <small>Review dates never reopen a Place automatically.</small>
                </div>
            </form>
        </div>
    </section>

    <section class="admin-panel closure-review-panel" aria-labelledby="closure-review-title">
        <header class="admin-panel-header closure-review-header">
            <div>
                <p>Verified closures</p>
                <h2 id="closure-review-title">Locations requiring review</h2>
            </div>
            <span class="closure-count"><?= count($closed) ?> <?= count($closed) === 1 ? 'location' : 'locations' ?></span>
        </header>

        <div class="admin-panel-body closure-review-body">
            <?php if (!$closed): ?>
                <div class="admin-empty-state closure-empty">
                    <h3>No confirmed closures</h3>
                    <p>Verified closures will appear here when a Place or campsite is marked closed.</p>
                </div>
            <?php else: ?>
                <div class="closure-review-list">
                    <?php foreach ($closed as $row): ?>
                        <?php
                        $site = $row['campsite_id'] === null ? '' : (trim((string) ($row['site_name'] ?? '')) ?: ('Site ' . ($row['site_code'] ?? $row['campsite_id'])));
                        $due = (string) ($row['review_date'] ?? '');
                        $isDue = $due !== '' && $due <= $today;
                        $isUpcoming = $due !== '' && $due > $today;
                        $statusLabel = ['temporary' => 'Temporarily closed', 'seasonal' => 'Seasonally closed', 'permanent' => 'Permanently closed'][(string) $row['status']] ?? 'Closed';
                        ?>
                        <article class="closure-review-card<?= $isDue ? ' is-due' : ($isUpcoming ? ' is-upcoming' : '') ?>">
                            <div class="closure-review-main">
                                <div class="closure-review-title-row">
                                    <div>
                                        <div class="closure-review-pills">
                                            <span class="closure-status-pill"><?= moderation_e($statusLabel) ?></span>
                                            <?php if ($isDue): ?>
                                                <span class="closure-review-pill is-due">Review due</span>
                                            <?php elseif ($isUpcoming): ?>
                                                <span class="closure-review-pill is-upcoming">Review scheduled</span>
                                            <?php else: ?>
                                                <span class="closure-review-pill">No review date</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3><?= moderation_e((string) $row['place_name']) ?><?= $site !== '' ? ' · ' . moderation_e($site) : '' ?></h3>
                                        <p><?= moderation_e((string) ($row['state'] ?? '')) ?> · <?= moderation_e(ucwords(str_replace('_', ' ', (string) $row['reason']))) ?></p>
                                    </div>

                                    <div class="closure-review-actions">
                                        <button type="button" class="admin-button is-muted" data-closure-edit
                                            data-place-id="<?= (int) $row['place_id'] ?>"
                                            data-campsite-id="<?= (int) ($row['campsite_id'] ?? 0) ?>"
                                            data-status="<?= moderation_e((string) $row['status']) ?>"
                                            data-reason="<?= moderation_e((string) $row['reason']) ?>"
                                            data-began="<?= moderation_e((string) ($row['closure_began'] ?? '')) ?>"
                                            data-reopening="<?= moderation_e((string) ($row['expected_reopening'] ?? '')) ?>"
                                            data-review="<?= moderation_e((string) ($row['review_date'] ?? '')) ?>"
                                            data-source="<?= moderation_e((string) ($row['source_url'] ?? '')) ?>"
                                            data-notes="<?= moderation_e((string) ($row['notes'] ?? '')) ?>">Edit / verify</button>
                                        <a class="admin-button is-muted" href="/place.php?id=<?= (int) $row['place_id'] ?>">View Place</a>
                                    </div>
                                </div>

                                <div class="closure-review-facts">
                                    <div>
                                        <span>Expected reopening</span>
                                        <strong><?= moderation_e((string) ($row['expected_reopening'] ?: 'Not known')) ?></strong>
                                    </div>
                                    <div>
                                        <span>Next review</span>
                                        <strong><?= moderation_e($due ?: 'Not scheduled') ?></strong>
                                    </div>
                                    <div>
                                        <span>Last verified</span>
                                        <strong><?= moderation_e((string) ($row['verified_at'] ?? 'Unknown')) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="/js/admin-closed-places.js" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>

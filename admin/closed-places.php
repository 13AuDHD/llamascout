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

// The editable record is loaded server-side. Edit works even if the
// optional admin JavaScript fails to load on the admin subdomain.
$editRecord = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    try {
        $statement = $db->prepare(
            'SELECT * FROM place_operating_closures WHERE id = ? LIMIT 1'
        );
        $statement->execute([$editId]);
        $editRecord = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($editRecord === null) {
            $error = 'The closure record no longer exists.';
        }
    } catch (PDOException $exception) {
        $error = 'Unable to load that closure into the editor.';
    }
}
$fieldValue = static function (string $key, string $default = '') use ($editRecord): string {
    return (string) ($_POST[$key] ?? $editRecord[$key] ?? $default);
};

$activeCount = 0;
$scheduledCount = 0;
$endedCount = 0;
$overdueCount = 0;
foreach ($closed as $item) {
    $today = llama_closure_local_today((string) ($item['state'] ?? ''));
    $phase = llama_closure_phase($item, $today);
    if ($phase === 'active') ++$activeCount;
    if ($phase === 'scheduled') ++$scheduledCount;
    if ($phase === 'ended') ++$endedCount;
    $reviewDate = (string) ($item['review_date'] ?? '');
    if ($reviewDate !== '' && $reviewDate <= $today) {
        ++$overdueCount;
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
            <span class="closure-summary-label">Currently closed</span>
            <strong><?= $activeCount ?></strong>
            <small>Active on today's local date</small>
        </article>
        <article class="closure-summary-card is-upcoming">
            <span class="closure-summary-label">Scheduled closures</span>
            <strong><?= $scheduledCount ?></strong>
            <small>Will activate on the start date</small>
        </article>
        <article class="closure-summary-card is-due">
            <span class="closure-summary-label">Due for review</span>
            <strong><?= $overdueCount ?></strong>
            <small>Review date reached or overdue</small>
        </article>
        <article class="closure-summary-card is-ended">
            <span class="closure-summary-label">Automatically reopened</span>
            <strong><?= $endedCount ?></strong>
            <small>Closure window has ended</small>
        </article>
    </section>

    <section class="admin-panel closure-editor-panel" aria-labelledby="closure-editor-title">
        <header class="admin-panel-header">
            <div>
                <p>Administrator verification</p>
                <h2 id="closure-editor-title"><?= $editRecord ? 'Edit saved closure' : 'Record or schedule a closure' ?></h2>
            </div>
        </header>

        <div class="admin-panel-body">
            <p class="closure-help">Select a Place, then set when its closure begins and ends. Leaving the Site ID blank applies it to the whole Place. The review date is a separate reminder and does not change opening status.</p>
            <?php if ($editRecord): ?>
                <p class="closure-notice is-success" role="status">
                    Editing saved record #<?= (int) $editRecord['id'] ?>. All saved dates and details are loaded.
                </p>
            <?php endif; ?>

            <form method="post" action="/closed-places.php" class="closure-form admin-form" id="closure-form">
                <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">

                <div class="closure-form-grid">
                    <label class="closure-field closure-span-8">
                        <span>Place <abbr title="Required">*</abbr></span>
                        <select name="place_id" id="closure-place-id" required>
                            <option value="">Choose a Place:</option>
                            <?php foreach ($placeChoices as $place): ?>
                                <option value="<?= (int) $place['id'] ?>" <?= $fieldValue('place_id') === (string) $place['id'] ? 'selected' : '' ?>><?= moderation_e((string) $place['name'] . ' · ' . (string) ($place['state'] ?? '') . ' · #' . $place['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Campsite ID <small>optional</small></span>
                        <input type="number" name="campsite_id" min="1" inputmode="numeric" value="<?= moderation_e($fieldValue('campsite_id')) ?>" placeholder="Entire Place if blank">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Operating status <abbr title="Required">*</abbr></span>
                        <select name="status" id="closure-status" required>
                            <?php foreach (['temporary' => 'Temporarily closed', 'seasonal' => 'Seasonally closed', 'permanent' => 'Permanently closed', 'open' => 'Open (manual override)'] as $key => $label): ?>
                                <option value="<?= moderation_e($key) ?>" <?= $fieldValue('status', 'temporary') === $key ? 'selected' : '' ?>><?= moderation_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Reason</span>
                        <select name="reason">
                            <?php foreach (['unknown','maintenance','construction','weather','fire','flooding','road_damage','administrative','other'] as $reason): ?>
                                <option value="<?= moderation_e($reason) ?>" <?= $fieldValue('reason', 'unknown') === $reason ? 'selected' : '' ?>><?= moderation_e(ucwords(str_replace('_', ' ', $reason))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Closure begins <small>automatically</small></span>
                        <input type="date" name="closure_began" value="<?= moderation_e($fieldValue('closure_began')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Closure ends / automatically reopens</span>
                        <input type="date" name="expected_reopening" value="<?= moderation_e($fieldValue('expected_reopening')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Review date <small>does not affect opening</small></span>
                        <input type="date" name="review_date" value="<?= moderation_e($fieldValue('review_date')) ?>">
                    </label>

                    <label class="closure-field closure-span-4">
                        <span>Official source URL</span>
                        <input type="url" name="source_url" maxlength="2048" placeholder="https://" value="<?= moderation_e($fieldValue('source_url')) ?>">
                    </label>

                    <label class="closure-field closure-span-12">
                        <span>Verification notes</span>
                        <textarea name="notes" rows="3" maxlength="10000" placeholder="What was verified and where?"><?= moderation_e($fieldValue('notes')) ?></textarea>
                    </label>
                </div>

                <div class="closure-form-actions">
                    <div>
                        <button class="admin-button" type="submit">Save closure schedule</button>
                        <a class="admin-button is-muted" href="/closed-places.php#closure-editor-title">New / clear form</a>
                    </div>
                    <small>Dates use the Place's local calendar. Closure begins at midnight on its start date, then ends at midnight on the reopening date. Review dates are reminders only.</small>
                </div>
            </form>
        </div>
    </section>

    <section class="admin-panel closure-review-panel" aria-labelledby="closure-review-title">
        <header class="admin-panel-header closure-review-header">
            <div>
                <p>Operating status calendar</p>
                <h2 id="closure-review-title">Active, scheduled and finished closures</h2>
            </div>
            <span class="closure-count"><?= count($closed) ?> <?= count($closed) === 1 ? 'location' : 'locations' ?></span>
        </header>

        <div class="admin-panel-body closure-review-body">
            <?php if (!$closed): ?>
                <div class="admin-empty-state closure-empty">
                    <h3>No saved closures</h3>
                    <p>Active and upcoming closure schedules will appear here.</p>
                </div>
            <?php else: ?>
                <div class="closure-review-list">
                    <?php foreach ($closed as $row): ?>
                        <?php
                        $site = $row['campsite_id'] === null ? '' : (trim((string) ($row['site_name'] ?? '')) ?: ('Site ' . ($row['site_code'] ?? $row['campsite_id'])));
                        $today = llama_closure_local_today((string) ($row['state'] ?? ''));
                        $due = (string) ($row['review_date'] ?? '');
                        $isDue = $due !== '' && $due <= $today;
                        $isUpcoming = $due !== '' && $due > $today;
                        $phase = llama_closure_phase($row, $today);
                        $phaseLabel = [
                            'active' => 'Currently closed',
                            'scheduled' => 'Scheduled to close',
                            'ended' => 'Automatically reopened',
                        ][$phase] ?? 'Open';
                        $statusLabel = ['temporary' => 'Temporary', 'seasonal' => 'Seasonal', 'permanent' => 'Permanent'][(string) $row['status']] ?? 'Closure';
                        ?>
                        <article class="closure-review-card is-<?= moderation_e($phase) ?><?= $isDue ? ' is-due' : '' ?>">
                            <div class="closure-review-main">
                                <div class="closure-review-title-row">
                                    <div>
                                        <div class="closure-review-pills">
                                            <span class="closure-status-pill"><?= moderation_e($phaseLabel) ?></span>
                                            <span class="closure-review-pill"><?= moderation_e($statusLabel) ?></span>
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
                                        <a class="admin-button is-muted"
                                            href="/closed-places.php?edit=<?= (int) $row['id'] ?>#closure-editor-title">Edit / verify</a>
                                        <a class="admin-button is-muted" href="/place.php?id=<?= (int) $row['place_id'] ?>">View Place</a>
                                    </div>
                                </div>

                                <div class="closure-review-facts">
                                    <div>
                                        <span>Closure begins</span>
                                        <strong><?= moderation_e((string) ($row['closure_began'] ?: 'Immediately')) ?></strong>
                                    </div>
                                    <div>
                                        <span>Automatically reopens</span>
                                        <strong><?= moderation_e((string) ($row['expected_reopening'] ?: 'Not known')) ?></strong>
                                    </div>
                                    <div>
                                        <span>Review on</span>
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

<script src="https://llamascout.com/js/admin-closed-places.js?v=<?= (int) filemtime(dirname(__DIR__) . '/js/admin-closed-places.js') ?>" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>

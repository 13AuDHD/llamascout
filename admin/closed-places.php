<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/operating-closures.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'Your session expired. Reload and try again.';
    } else {
        try {
            llama_closure_save($db, $_POST, (int) ($adminUser['id'] ?? 0));
            header('Location: /closed-places.php?updated=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            $error = 'Could not save the closure. Confirm that the closure-status SQL migration has been applied.';
        }
    }
}

try {
    $closed = llama_closure_admin_list($db);
} catch (PDOException $exception) {
    $closed = [];
    $error = 'Could not load closures. Apply sql/closure-status.sql first.';
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
$adminActiveNav = 'places';
require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <h1>Closed Places</h1>
    <p>Confirmed closures remain listed until an administrator verifies reopening. Review dates do not reopen locations automatically.</p>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= moderation_e($error) ?></p>
    <?php elseif (isset($_GET['updated'])): ?>
        <p role="status">Operating status saved.</p>
    <?php endif; ?>

    <details>
        <summary>Record or update a closure</summary>
        <form method="post" action="/closed-places.php">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <label>Place ID <input type="number" name="place_id" min="1" required></label>
            <label>Site ID (leave blank for entire Place) <input type="number" name="campsite_id" min="1"></label>
            <label>Operating status
                <select name="status" required>
                    <option value="temporary">Temporarily Closed</option>
                    <option value="seasonal">Seasonally Closed</option>
                    <option value="permanent">Permanently Closed</option>
                    <option value="open">Reopened (verified)</option>
                </select>
            </label>
            <label>Reason
                <select name="reason">
                    <?php foreach (['unknown','maintenance','construction','weather','fire','flooding','road_damage','administrative','other'] as $reason): ?>
                        <option value="<?= moderation_e($reason) ?>"><?= moderation_e(ucwords(str_replace('_', ' ', $reason))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Closure began <input type="date" name="closure_began"></label>
            <label>Expected reopening (tentative) <input type="date" name="expected_reopening"></label>
            <label>Next review date <input type="date" name="review_date"></label>
            <label>Official source URL <input type="url" name="source_url" maxlength="2048"></label>
            <label>Notes <textarea name="notes" rows="3" maxlength="10000"></textarea></label>
            <button class="admin-button" type="submit">Save operating status</button>
        </form>
    </details>
</section>

<section class="admin-panel">
    <h2>Locations requiring review</h2>
    <?php if (!$closed): ?>
        <p>No confirmed closures are currently listed.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Place / Site</th><th>Status</th><th>Reason</th><th>Expected reopening</th><th>Review due</th><th>Verified</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($closed as $row): ?>
                    <?php
                    $site = $row['campsite_id'] === null ? '' : (trim((string) ($row['site_name'] ?? '')) ?: ('Site ' . ($row['site_code'] ?? $row['campsite_id'])));
                    $due = (string) ($row['review_date'] ?? '');
                    $overdue = $due !== '' && $due <= date('Y-m-d');
                    ?>
                    <tr>
                        <td><?= moderation_e($row['place_name']) ?><?= $site !== '' ? ' / ' . moderation_e($site) : '' ?><br><?= moderation_e((string) ($row['state'] ?? '')) ?></td>
                        <td><?= moderation_e(ucfirst($row['status'])) ?> Closed</td>
                        <td><?= moderation_e(ucwords(str_replace('_', ' ', $row['reason']))) ?></td>
                        <td><?= moderation_e((string) ($row['expected_reopening'] ?? 'Not known')) ?></td>
                        <td><?= $overdue ? '<strong>Due: ' : '' ?><?= moderation_e($due !== '' ? $due : 'Not scheduled') ?><?= $overdue ? '</strong>' : '' ?></td>
                        <td><?= moderation_e((string) ($row['verified_at'] ?? '')) ?></td>
                        <td><a class="admin-button is-muted" href="/place.php?id=<?= (int) $row['place_id'] ?>">View Place</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-errors.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $adminAction = trim((string) ($_POST['admin_action'] ?? 'resolution'));

            if ($adminAction === 'self_test') {
                $reference = admin_errors_create_test($db, $actorUserId);
                header('Location: /errors.php?' . http_build_query([
                    'q' => $reference,
                    'tested' => '1',
                ]));
                exit;
            }

            if ($adminAction === 'retention') {
                $days = admin_errors_set_retention_days(
                    $db,
                    $actorUserId,
                    (int) ($_POST['retention_days'] ?? 0)
                );
                header('Location: /errors.php?retention_updated=' . $days);
                exit;
            }

            if ($adminAction === 'cleanup') {
                $deleted = admin_errors_cleanup_now($db, $actorUserId);
                header('Location: /errors.php?cleaned=' . $deleted);
                exit;
            }

            if ($adminAction !== 'resolution') {
                throw new InvalidArgumentException('Choose a valid error-log action.');
            }

            $errorId = (int) ($_POST['error_id'] ?? 0);
            $resolutionAction = trim((string) ($_POST['resolution_action'] ?? ''));
            $newStatus = $resolutionAction === 'resolve' ? 'resolved' : ($resolutionAction === 'reopen' ? 'open' : '');

            if ($newStatus === '') {
                throw new InvalidArgumentException('Choose a valid error action.');
            }

            admin_errors_set_resolution($db, $errorId, $actorUserId, $newStatus);

            $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
            $returnQuery = preg_replace('/(?:^|&)updated=[^&]*/', '', $returnQuery) ?? '';
            $returnQuery = trim($returnQuery, '&');
            $returnQuery .= ($returnQuery !== '' ? '&' : '') . 'updated=' . rawurlencode($newStatus);

            header('Location: /errors.php?' . $returnQuery);
            exit;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.errors.resolution',
                ['error_id' => (int) ($_POST['error_id'] ?? 0)]
            );
            $error = llama_error_message_with_reference(
                'The error record could not be updated.',
                $reference
            );
        }
    }
}

$updated = strtolower(trim((string) ($_GET['updated'] ?? '')));
$tested = (string) ($_GET['tested'] ?? '') === '1';
$retentionUpdated = (int) ($_GET['retention_updated'] ?? 0);
$cleaned = isset($_GET['cleaned']) ? max(0, (int) $_GET['cleaned']) : null;
if ($updated === 'resolved') {
    $notice = 'Error marked resolved.';
} elseif ($updated === 'open') {
    $notice = 'Error reopened.';
} elseif ($tested) {
    $notice = 'Diagnostic test recorded successfully. The matching error record is shown below.';
} elseif ($retentionUpdated > 0) {
    $notice = 'Resolved error history will be kept for ' . $retentionUpdated . ' days.';
} elseif ($cleaned !== null) {
    $notice = number_format($cleaned) . ' old resolved error record' . ($cleaned === 1 ? '' : 's') . ' removed.';
}

$retentionDays = admin_errors_retention_days($db);
$result = admin_errors_search($db, $_GET, 50);
$rows = $result['rows'];
$filters = $result['filters'];

$stats = admin_dashboard_stats($db);
$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Error Log';
$adminPageEyebrow = 'System';
$adminActiveNav = 'errors';

function error_log_query(array $changes = []): string
{
    $query = array_merge($_GET, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            unset($query[$key]);
        }
    }

    return http_build_query($query);
}

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-audit-console">
    <?php if ($notice !== ''): ?>
        <div class="admin-notice is-success"><p><?= moderation_e($notice) ?></p></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="admin-notice is-error"><p><?= moderation_e($error) ?></p></div>
    <?php endif; ?>

    <header class="admin-panel-header">
        <div>
            <p>Application diagnostics</p>
            <h2><?= number_format((int) $result['total']) ?> recorded issues</h2>
        </div>
        <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
    </header>

    <div class="admin-notice">
        <div>
            <strong>Error-log self-test</strong>
            <p>Creates a harmless test exception so you can verify reference IDs, database logging, the Admin viewer, and System Health without breaking a real workflow.</p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="self_test">
            <button class="admin-button is-muted" type="submit"><i class="fa-solid fa-vial" aria-hidden="true"></i> Run error-log test</button>
        </form>
    </div>

    <div class="admin-notice">
        <div>
            <strong>Resolved error history</strong>
            <p>Open issues are never removed automatically. Resolved issues are kept for the retention period below, then cleaned up automatically when new errors are recorded.</p>
        </div>
        <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="retention">
            <label>
                <span class="sr-only">Retention days</span>
                <input type="number" name="retention_days" min="30" max="3650" step="1" value="<?= (int) $retentionDays ?>" required>
            </label>
            <span>days</span>
            <button class="admin-button is-muted" type="submit">Save retention</button>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="cleanup">
            <button class="admin-button is-muted" type="submit"><i class="fa-solid fa-broom" aria-hidden="true"></i> Clean up now</button>
        </form>
    </div>

    <form class="admin-audit-filters" method="get">
        <label class="admin-audit-search">
            <span>Search</span>
            <input type="search" name="q" value="<?= moderation_e((string) $filters['q']) ?>" placeholder="Reference, action, page, exception, or message">
        </label>

        <label>
            <span>Severity</span>
            <select name="severity">
                <option value="">All severities</option>
                <option value="error" <?= $filters['severity'] === 'error' ? 'selected' : '' ?>>Error</option>
                <option value="fatal" <?= $filters['severity'] === 'fatal' ? 'selected' : '' ?>>Fatal</option>
            </select>
        </label>

        <label>
            <span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="resolved" <?= $filters['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </label>

        <label>
            <span>User ID</span>
            <input type="number" min="1" name="user_id" value="<?= (int) $filters['user_id'] > 0 ? (int) $filters['user_id'] : '' ?>" placeholder="Any">
        </label>

        <div class="admin-audit-filter-actions">
            <button class="admin-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter</button>
            <a class="admin-button is-muted" href="/errors.php">Clear</a>
        </div>
    </form>

    <?php if (!$rows): ?>
        <div class="admin-empty-state"><p>No application errors match these filters.</p></div>
    <?php else: ?>
        <div class="admin-audit-list">
            <?php foreach ($rows as $row): ?>
                <?php
                $context = admin_errors_context($row['context_json'] ?? null);
                $userName = trim((string) ($row['display_name'] ?? ''));
                if ($userName === '') {
                    $userName = trim((string) ($row['username'] ?? ''));
                }
                ?>
                <article class="admin-audit-row">
                    <span class="admin-audit-icon"><i class="fa-solid <?= $row['severity'] === 'fatal' ? 'fa-skull-crossbones' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i></span>
                    <div class="admin-audit-main">
                        <div class="admin-audit-title-row">
                            <strong><?= moderation_e((string) $row['reference_code']) ?></strong>
                            <span class="admin-audit-category"><?= moderation_e(strtoupper((string) $row['severity'])) ?></span>
                            <?php if ((int) ($row['occurrence_count'] ?? 1) > 1): ?>
                                <span class="admin-audit-category"><?= number_format((int) $row['occurrence_count']) ?> occurrences</span>
                            <?php endif; ?>
                        </div>

                        <span>
                            <?= moderation_e((string) ($row['exception_class'] ?: 'PHP error')) ?>
                            · Last seen <?= moderation_e((string) ($row['last_seen_at'] ?: $row['created_at'])) ?> UTC
                            <?php if ((int) ($row['occurrence_count'] ?? 1) > 1): ?>
                                · First seen <?= moderation_e((string) ($row['first_seen_at'] ?: $row['created_at'])) ?> UTC
                            <?php endif; ?>
                        </span>

                        <small>
                            <?= moderation_e((string) ($row['request_method'] ?: '')) ?>
                            <?= moderation_e((string) ($row['request_path'] ?: 'unknown path')) ?>
                            <?php if (!empty($row['action'])): ?> · <?= moderation_e((string) $row['action']) ?><?php endif; ?>
                            <?php if ((int) ($row['user_id'] ?? 0) > 0): ?>
                                · User #<?= (int) $row['user_id'] ?><?= $userName !== '' ? ' (' . moderation_e($userName) . ')' : '' ?>
                            <?php endif; ?>
                        </small>

                        <p class="admin-error-message"><?= moderation_e((string) $row['message']) ?></p>

                        <?php $isResolved = (string) ($row['resolution_status'] ?? 'open') === 'resolved'; ?>
                        <div class="admin-error-resolution">
                            <strong><?= $isResolved ? 'Resolved' : 'Open' ?></strong>
                            <?php if ($isResolved && !empty($row['resolved_at'])): ?>
                                <span>Resolved <?= moderation_e((string) $row['resolved_at']) ?> UTC<?php if ((int) ($row['resolved_by'] ?? 0) > 0): ?> by Admin #<?= (int) $row['resolved_by'] ?><?php endif; ?></span>
                            <?php endif; ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                                <input type="hidden" name="admin_action" value="resolution">
                                <input type="hidden" name="error_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="return_query" value="<?= moderation_e((string) ($_SERVER['QUERY_STRING'] ?? '')) ?>">
                                <input type="hidden" name="resolution_action" value="<?= $isResolved ? 'reopen' : 'resolve' ?>">
                                <button class="admin-button is-muted" type="submit"><?= $isResolved ? 'Reopen' : 'Mark resolved' ?></button>
                            </form>
                        </div>

                        <details class="admin-audit-details">
                            <summary>View technical details <span><?= $context ? count($context) : 0 ?></span></summary>
                            <dl class="admin-audit-metadata">
                                <div><dt>File</dt><dd><?= moderation_e((string) ($row['file_path'] ?: 'Unknown')) ?>:<?= (int) ($row['line_number'] ?? 0) ?></dd></div>
                                <?php if ((int) ($row['user_id'] ?? 0) > 0): ?><div><dt>User</dt><dd><a href="/user.php?id=<?= (int) $row['user_id'] ?>">Open user #<?= (int) $row['user_id'] ?></a></dd></div><?php endif; ?>
                                <?php foreach ($context as $key => $value): ?>
                                    <div><dt><?= moderation_e((string) $key) ?></dt><dd><?= moderation_e(is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                            <?php if (!empty($row['trace'])): ?><pre class="admin-error-trace"><?= moderation_e((string) $row['trace']) ?></pre><?php endif; ?>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ((int) $result['pages'] > 1): ?>
            <nav class="admin-audit-pagination" aria-label="Error log pages">
                <?php if ((int) $result['page'] > 1): ?><a class="admin-button is-muted" href="?<?= moderation_e(error_log_query(['page' => (int) $result['page'] - 1])) ?>">Previous</a><?php endif; ?>
                <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                <?php if ((int) $result['page'] < (int) $result['pages']): ?><a class="admin-button is-muted" href="?<?= moderation_e(error_log_query(['page' => (int) $result['page'] + 1])) ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/nps.php';

$adminUser = moderation_require_admin();
// One short source-only request per batch. The browser drives continuation.
$importAction = (string) ($_POST['action'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && in_array($importAction, ['import_start', 'import_next', 'import_pause', 'import_status'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            throw new RuntimeException('Your session expired. Reload this page and resume the import.');
        }
        // Do not hold the login session lock while an NPS request is in flight.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $sourceDb = nps_db();
        $result = match ($importAction) {
            'import_start' => llama_nps_import_start($sourceDb, ($_POST['refresh'] ?? '') === '1'),
            'import_next' => llama_nps_import_next($sourceDb),
            'import_pause' => llama_nps_import_pause($sourceDb),
            default => ['summary' => llama_nps_import_summary($sourceDb)],
        };
        if (isset($result['summary']) && !$result['summary']['pending']) {
            $result['counts'] = llama_nps_cache_summary($sourceDb);
        }
        echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        if (http_response_code() < 400) {
            http_response_code(500);
        }
        $message = $exception instanceof PDOException
            ? 'The NPS import database could not be prepared or read. Check the NPS connection, source schema, and permission to create nps_import_progress. Existing records and saved progress are retained.'
            : $exception->getMessage();
        if (function_exists('llama_log_caught_exception')) {
            $reference = llama_log_caught_exception($exception, 'admin.nps_source_import_control',
                ['action' => $importAction], [RuntimeException::class]);
            $message = llama_error_message_with_reference($message, $reference);
        }
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

$adminPageTitle = 'National Park Service';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';

$resources = llama_nps_resources();
$parks = llama_nps_parks();
$resource = strtolower(trim((string) (
    $_POST['resource'] ?? $_GET['resource'] ?? 'campgrounds'
)));
$parkCode = strtolower(trim((string) (
    $_POST['park_code'] ?? $_GET['park_code'] ?? 'yose'
)));
$limit = max(1, min(10, (int) (
    $_POST['limit'] ?? $_GET['limit'] ?? 5
)));

if (!isset($resources[$resource])) {
    $resource = 'campgrounds';
}

$dbError = '';
$error = '';
$notice = '';
$preview = null;
$npsDatabase = null;
$schemaReady = false;
$counts = array_fill_keys(array_keys($resources), 0);
$records = [];
$inventory = ['records' => 0, 'fields' => []];

try {
    $npsDatabase = nps_db();
    $npsDatabase->query('SELECT 1');
    try {
        $npsDatabase->query('SELECT record_id FROM nps_raw_records LIMIT 0');
        $schemaReady = true;
        $parks = llama_nps_available_parks($npsDatabase);
    } catch (Throwable) {
        $dbError = 'NPS database is connected. Run nps-schema.sql in this database before importing records.';
    }
} catch (Throwable) {
    $dbError = 'NPS database connection failed. Check the nps_database entry in private configuration.';
}

if (!isset($parks[$parkCode])) {
    $parkCode = 'yose';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        try {
            if ($action === 'test_api') {
                $test = llama_nps_request('parks', $parkCode, 1);
                $notice = 'NPS API connection succeeded for '
                    . $parks[$parkCode]
                    . '. NPS returned '
                    . number_format($test['total'])
                    . ' matching park record(s). No records were saved.';
            } elseif ($action === 'preview' || $action === 'import_sample') {
                if ($action === 'import_sample' && !$schemaReady) {
                    throw new RuntimeException('Create the NPS source table before importing.');
                }

                $preview = llama_nps_request($resource, $parkCode, $limit);
                if ($action === 'import_sample') {
                    if (!$npsDatabase instanceof PDO) {
                        throw new RuntimeException('NPS database is unavailable.');
                    }
                    $stored = llama_nps_store_sample($npsDatabase, $preview);
                    $notice = number_format($stored['stored']) . ' '
                        . $resources[$resource]
                        . ' record(s) saved to the NPS source database only. '
                        . 'No Llama Scout Places or Scout answers were changed.';
                } else {
                    $notice = 'Preview loaded from NPS. No records were saved.';
                }
            } else {
                $error = 'Unknown action.';
            }
        } catch (Throwable $exception) {
            // Keep failure messages understandable without disclosing credentials.
            $error = $exception->getMessage();
        }
    }
}

if ($schemaReady && $npsDatabase instanceof PDO) {
    try {
        $counts = llama_nps_cache_summary($npsDatabase);
        $records = llama_nps_sample_rows($npsDatabase, $resource, $parkCode);
        $inventory = llama_nps_field_inventory($npsDatabase, $resource, $parkCode);
    } catch (Throwable) {
        $dbError = 'The NPS table exists, but the cache could not be read. Confirm the installed schema.';
    }
}

$importSummary = ['resources' => [], 'complete' => false, 'pending' => false, 'errors' => false];
foreach ($resources as $type => $label) {
    $importSummary['resources'][] = ['resource' => $type, 'label' => $label,
        'status' => 'not_started', 'processed' => 0, 'total' => null, 'error' => ''];
}
if ($schemaReady && $npsDatabase instanceof PDO) {
    try {
        $importSummary = llama_nps_import_summary($npsDatabase);
    } catch (PDOException) {
        // The progress table is created by the first explicit Start action.
    }
}
$importStatuses = ['not_started' => 'Not started', 'pending' => 'Ready to resume',
    'paused' => 'Paused', 'error' => 'Needs retry', 'complete' => 'Complete'];

$viewQuery = http_build_query([
    'resource' => $resource,
    'park_code' => $parkCode,
    'limit' => $limit,
], '', '&', PHP_QUERY_RFC3986);

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <div class="admin-user-notice is-error" role="alert">
        <?= moderation_e($error) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($dbError !== ''): ?>
<section class="admin-panel">
    <div class="admin-user-notice is-error" role="status">
        <?= moderation_e($dbError) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($notice !== ''): ?>
<section class="admin-panel">
    <div class="admin-user-notice" role="status">
        <?= moderation_e($notice) ?>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Official NPS source data</p>
            <h2>National Park Service</h2>
        </div>
        <span class="admin-status-pill">
            <?= $schemaReady ? 'Source database ready' : 'Setup required' ?>
        </span>
    </header>

    <p>
        Import and inspect original NPS information in the separate NPS source
        database, including its identifiers, source dates, and full record contents.
    </p>

    <div class="admin-user-form-actions">
        <a class="admin-button is-secondary" href="/ridb.php">RIDB control center</a>
        <a class="admin-button is-secondary" href="/ridb-schema.php">RIDB schema explorer</a>
    </div>
</section>

<section class="admin-panel" data-nps-import data-endpoint="/nps.php"
    data-summary="<?= moderation_e(json_encode($importSummary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
    <header class="admin-panel-header">
        <div>
            <p>All NPS parks</p>
            <h2>National source import</h2>
        </div>
    </header>
    <p>
        Import all parks, campgrounds, alerts and closures, fees and passes,
        parking lots, and road events. Existing source records are refreshed
        using their saved identities.
    </p>
    <p>Keep this page open while importing. You can pause and resume from the last saved batch.</p>
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <div class="admin-user-form-actions">
        <button class="admin-button" type="button" data-nps-start <?= $schemaReady ? '' : 'disabled' ?>>
            <?= $importSummary['complete'] ? 'Refresh source records' : 'Start / resume national import' ?>
        </button>
        <button class="admin-button is-secondary" type="button" data-nps-pause disabled>Pause</button>
    </div>
    <p data-nps-import-message role="status" aria-live="polite">
        <?= $importSummary['complete'] ? 'The last national import completed for all six record types.' : 'Ready to import. Saved progress is shown below.' ?>
    </p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Record type</th><th>Records read</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($importSummary['resources'] as $item): ?>
                <tr data-nps-resource="<?= moderation_e($item['resource']) ?>">
                    <td><?= moderation_e($item['label']) ?></td>
                    <td data-nps-count><?= number_format($item['processed']) ?> / <?= $item['total'] === null ? 'not fetched yet' : number_format($item['total']) ?></td>
                    <td><progress data-nps-progress
                        aria-label="<?= moderation_e($item['label']) ?> import progress"
                        value="<?= $item['status'] === 'complete' ? max(1, $item['processed']) : $item['processed'] ?>"
                        max="<?= max(1, $item['total'] ?? $item['processed']) ?>"></progress></td>
                    <td><span data-nps-state><?= moderation_e($importStatuses[$item['status']] ?? $item['status']) ?></span>
                        <p data-nps-error><?= moderation_e($item['error']) ?></p></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <noscript><p>JavaScript is required to continue importing batches automatically.</p></noscript>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Official source mirror</p>
            <h2>Cached record types</h2>
        </div>
    </header>

    <div class="admin-integration-summary">
        <?php foreach ($counts as $type => $count): ?>
            <div>
                <strong data-nps-cache-resource="<?= moderation_e($type) ?>"><?= number_format($count) ?></strong>
                <span><?= moderation_e($resources[$type]) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Inspect a small sample</p>
            <h2>Connection and source records</h2>
        </div>
    </header>

    <form method="post" action="/nps.php?<?= moderation_e($viewQuery) ?>">
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">

        <div class="admin-user-form-grid">
            <label>
                <span>National Park</span>
                <select name="park_code">
                    <?php foreach ($parks as $code => $label): ?>
                        <option value="<?= moderation_e($code) ?>" <?= $parkCode === $code ? 'selected' : '' ?>>
                            <?= moderation_e($label) ?> (<?= moderation_e($code) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>NPS resource</span>
                <select name="resource">
                    <?php foreach ($resources as $key => $label): ?>
                        <option value="<?= moderation_e($key) ?>" <?= $resource === $key ? 'selected' : '' ?>>
                            <?= moderation_e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Sample size</span>
                <select name="limit">
                    <?php foreach ([1, 3, 5, 10] as $option): ?>
                        <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>>
                            <?= $option ?> record<?= $option === 1 ? '' : 's' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="admin-user-form-actions">
            <button class="admin-button is-secondary" type="submit" name="action" value="test_api">
                Test NPS connection
            </button>
            <button class="admin-button is-secondary" type="submit" name="action" value="preview">
                Preview NPS records
            </button>
            <button class="admin-button" type="submit" name="action" value="import_sample" <?= $schemaReady ? '' : 'disabled' ?>>
                Save sample to NPS database
            </button>
        </div>
    </form>
</section>

<?php if (is_array($preview)): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Live response</p>
            <h2><?= moderation_e($resources[$resource]) ?> — <?= moderation_e($parks[$parkCode]) ?></h2>
        </div>
        <span><?= number_format($preview['total']) ?> NPS record(s) matched</span>
    </header>

    <?php if (!$preview['records']): ?>
        <div class="admin-empty-state">
            <p>No records were returned for this park and resource. This can be a valid NPS result.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>NPS record</th><th>Source ID</th><th>Park code</th></tr></thead>
                <tbody>
                    <?php foreach ($preview['records'] as $entry): ?>
                        <tr>
                            <td><?= moderation_e((string) ($entry['name'] ?? $entry['title'] ?? $entry['fullName'] ?? 'Unnamed')) ?></td>
                            <td><?= moderation_e(llama_nps_record_id($entry, $resource)) ?></td>
                            <td><?= moderation_e((string) ($entry['parkCode'] ?? $parkCode)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Saved source inventory</p>
            <h2><?= moderation_e($resources[$resource]) ?> — <?= moderation_e($parks[$parkCode]) ?></h2>
        </div>
        <span><?= number_format(count($records)) ?> saved record(s) / <?= number_format(count($inventory['fields'])) ?> field paths</span>
    </header>

    <form method="get" action="/nps.php">
        <div class="admin-user-form-grid">
            <label>
                <span>National Park</span>
                <select name="park_code">
                    <?php foreach ($parks as $code => $label): ?>
                        <option value="<?= moderation_e($code) ?>" <?= $parkCode === $code ? 'selected' : '' ?>><?= moderation_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Resource</span>
                <select name="resource">
                    <?php foreach ($resources as $key => $label): ?>
                        <option value="<?= moderation_e($key) ?>" <?= $resource === $key ? 'selected' : '' ?>><?= moderation_e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-user-form-actions">
            <button class="admin-button is-secondary" type="submit">View cached inventory</button>
        </div>
    </form>

    <?php if (!$records): ?>
        <div class="admin-empty-state">
            <p>No NPS source records are cached for this park and resource yet.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>NPS source ID</th><th>Indexed</th><th>Cached</th></tr></thead>
                <tbody>
                <?php foreach ($records as $entry): ?>
                    <tr>
                        <td><?= moderation_e((string) $entry['record_name']) ?></td>
                        <td><?= moderation_e((string) $entry['record_id']) ?></td>
                        <td><?= moderation_e((string) $entry['source_updated_at']) ?></td>
                        <td><?= moderation_e((string) $entry['fetched_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Original NPS field path</th><th>Data type</th><th>Occurrences</th><th>Sample value</th></tr></thead>
                <tbody>
                <?php foreach ($inventory['fields'] as $path => $details): ?>
                    <tr>
                        <td><strong><?= moderation_e((string) $path) ?></strong></td>
                        <td><?= moderation_e(implode(', ', array_keys((array) ($details['type'] ?? [])))) ?></td>
                        <td><?= number_format((int) ($details['seen'] ?? $details['empty'] ?? 0)) ?></td>
                        <td><?= moderation_e(implode('; ', (array) ($details['examples'] ?? []))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script src="https://llamascout.com/js/admin-nps-import.js" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>


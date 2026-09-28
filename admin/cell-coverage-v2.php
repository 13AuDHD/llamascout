<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-v2.php';

$adminUser = moderation_require_admin();

$adminPageTitle = 'Cell Coverage V2';
$adminPageEyebrow = 'Map Data';
$adminActiveNav = 'cell-coverage';

$v2Ready = llama_fcc_v2_tables_ready();
$snapshot = null;

if ($v2Ready) {
    try {
        $snapshot = llama_fcc_v2_snapshot();
    } catch (Throwable $e) {
        $snapshot = null;
    }
}

function admin_cell_v2_status_class(string $state): string
{
    return match ($state) {
        'current' => 'is-current',
        'downloading' => 'is-downloading',
        'importing' => 'is-importing',
        'error' => 'is-error',
        'missing' => 'is-missing',
        'outdated' => 'is-outdated',
        default => 'is-working',
    };
}

function admin_cell_v2_status_label(string $state): string
{
    return match ($state) {
        'current' => 'Current',
        'downloading' => 'Downloading',
        'processing', 'process_queued' => 'Processing',
        'unpacking', 'unpack_queued' => 'Unpacking',
        'importing' => 'Importing',
        'cleanup', 'cleanup_queued' => 'Cleanup',
        'error' => 'Error',
        'missing' => 'Missing',
        'outdated' => 'Outdated',
        default => 'Preparing',
    };
}

require __DIR__ . '/_header.php';
?>
<link rel="stylesheet" href="<?= moderation_e($siteUrl . '/css/admin/pages/cell-coverage-v2.css') ?>">

<?php if (!$v2Ready): ?>
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>V2 Setup</p>
                <h2>Database migration required</h2>
            </div>
        </header>
        <div class="admin-panel-body">
            <p>
                Run <code>cell-coverage-v2-foundation.sql</code> against the
                dedicated cell coverage database, then reload this page.
            </p>
            <p>V1 is untouched and can continue operating normally.</p>
        </div>
    </section>
<?php else: ?>

<section
    id="cell-v2-root"
    class="cell-v2-root"
    data-endpoint="/cell-coverage-v2-api.php"
    data-csrf="<?= moderation_e(moderation_csrf_token()) ?>"
>
    <section class="cell-v2-summary">
        <article class="cell-v2-card">
            <span>FCC Latest</span>
            <strong id="cell-v2-fcc-latest">
                <?= moderation_e((string) ($snapshot['fcc_latest'] ?? 'Not checked')) ?>
            </strong>
        </article>

        <article class="cell-v2-card">
            <span>Coverage cells</span>
            <strong id="cell-v2-coverage-cells">
                <?= number_format((int) ($snapshot['coverage_cells'] ?? 0)) ?>
            </strong>
        </article>

        <article class="cell-v2-card">
            <span>Database size</span>
            <strong id="cell-v2-db-size">—</strong>
        </article>

        <article class="cell-v2-card">
            <span>Sync status</span>
            <strong id="cell-v2-sync-status">
                <?= moderation_e(ucfirst((string) ($snapshot['sync_status'] ?? 'idle'))) ?>
            </strong>
        </article>
    </section>

    <section class="admin-panel cell-v2-controls">
        <div class="cell-v2-control-row">
            <div class="cell-v2-buttons">
                <button type="button" class="admin-button is-primary" id="cell-v2-latest-button">
                    FCC Latest
                </button>
                <button type="button" class="admin-button" id="cell-v2-sync-button" disabled title="Worker engine comes in the next V2 package">
                    Sync Coverage
                </button>
                <button type="button" class="admin-button" id="cell-v2-errors-button" disabled title="Recovery engine comes in the next V2 package">
                    Check Errors
                </button>
            </div>

            <label class="cell-v2-banner-toggle">
                <input
                    type="checkbox"
                    id="cell-v2-banner-toggle"
                    <?= !empty($snapshot['banner_enabled']) ? 'checked' : '' ?>
                >
                <span>
                    <strong>Map update banner</strong>
                    <small>Stored now. Public map hook will be connected with the V2 activation package.</small>
                </span>
            </label>
        </div>

        <p class="cell-v2-message" id="cell-v2-message" aria-live="polite"></p>

        <div class="cell-v2-overall-progress">
            <div>
                <strong>Overall progress</strong>
                <span id="cell-v2-progress-text">V2 worker engine not started</span>
            </div>
            <progress id="cell-v2-progress" max="306" value="0"></progress>
        </div>

        <div class="cell-v2-factory-status">
            <div>
                <span>Workers</span>
                <strong id="cell-v2-workers">Download 0/1 · Process 0/1 · Unpack 0/3 · Import 0/1 · Cleanup 0/1</strong>
            </div>
            <div>
                <span>Queues</span>
                <strong id="cell-v2-queues">No active run</strong>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Coverage Matrix</p>
                <h2>Live Dataset Status</h2>
            </div>
            <span id="cell-v2-completeness"></span>
        </header>

        <div class="cell-coverage-table-wrap">
            <table class="cell-coverage-matrix cell-v2-matrix">
                <thead>
                    <tr>
                        <th rowspan="2">State</th>
                        <th colspan="2">AT&amp;T</th>
                        <th colspan="2">T-Mobile</th>
                        <th colspan="2">Verizon</th>
                    </tr>
                    <tr>
                        <th>4G</th>
                        <th>5G</th>
                        <th>4G</th>
                        <th>5G</th>
                        <th>4G</th>
                        <th>5G</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $slots = is_array($snapshot['slots'] ?? null) ? $snapshot['slots'] : [];
                $states = llama_fcc_sync_states();
                $slotMap = [];
                foreach ($slots as $slot) {
                    $slotMap[(string) $slot['slot_key']] = $slot;
                }
                ?>
                <?php foreach ($states as $fips => $stateName): ?>
                    <tr>
                        <th><?= moderation_e($stateName) ?></th>
                        <?php foreach (['att', 'tmobile', 'verizon'] as $provider): ?>
                            <?php foreach (['4g', '5g'] as $technology): ?>
                                <?php
                                $key = llama_fcc_v2_slot_key((string) $fips, $provider, $technology);
                                $slot = $slotMap[$key] ?? null;
                                $state = (string) ($slot['display_state'] ?? 'missing');
                                $title = trim((string) ($slot['last_error'] ?? $slot['last_diagnostic'] ?? ''));
                                ?>
                                <td>
                                    <span
                                        class="cell-v2-pill <?= moderation_e(admin_cell_v2_status_class($state)) ?>"
                                        data-cell-v2-slot="<?= moderation_e($key) ?>"
                                        title="<?= moderation_e($title) ?>"
                                    ><?= moderation_e(admin_cell_v2_status_label($state)) ?></span>
                                </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Factory Activity</p>
                <h2>Live Activity Log</h2>
            </div>
            <span>Newest first</span>
        </header>
        <div class="cell-v2-log" id="cell-v2-log" aria-live="polite"></div>
    </section>
</section>

<script>
window.LLAMA_CELL_V2_INITIAL = <?= json_encode(
    $snapshot,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;
</script>
<script src="/js/admin-cell-coverage-v2.js?v=20260928-1"></script>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

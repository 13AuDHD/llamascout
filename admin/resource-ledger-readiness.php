<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/resource-ledger-readiness.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$report = llama_resource_ledger_readiness(db());

$adminPageTitle = 'Resource report history readiness';
$adminPageEyebrow = 'Resources';
$adminActiveNav = 'resources';

require __DIR__ . '/_header.php';

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$statusLabels = [
    'schema_verified' => 'Schema verified, staging approval still required',
    'schema_incomplete' => 'Missing or changed database structure',
    'integrity_review_required' => 'Existing history/current answers require review',
    'inspection_failed' => 'Unable to inspect database safely',
    'unverified' => 'Not yet verified',
];
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Read-only database inspection</p>
            <h2>Resource and Facility report history</h2>
        </div>
        <a class="admin-button is-muted" href="/resources.php">Back to Resources</a>
    </header>

    <div class="admin-user-action-box">
        <p><strong><?= $e($statusLabels[$report['status']] ?? $report['status']) ?></strong></p>
        <p>This page checks the database your website is actually connected to.
           It does not install SQL, change existing answers, enable report history,
           or approve a production rollout.</p>
        <p>
            History writes:
            <strong><?= $report['flags']['history_writes_enabled'] ? 'ENABLED' : 'Disabled' ?></strong>.
            Current-answer reads:
            <strong><?= $report['flags']['current_reads_enabled'] ? 'ENABLED' : 'Disabled' ?></strong>.
        </p>
        <?php if ($report['flags']['history_writes_enabled'] || $report['flags']['current_reads_enabled']): ?>
            <p><strong>Attention:</strong> At least one release switch is enabled.
               Check staged approval results and database backup before proceeding.</p>
        <?php endif; ?>
        <p>Even a fully verified structure is not proof that an approved Resource
           or Facility was successfully published end to end.
           Keep both release switches off until staging checks pass.</p>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Installation</p>
            <h2>Six tables, indexes, constraints and immutable triggers</h2>
        </div>
    </header>
    <div class="admin-user-action-box">
        <ul>
            <?php foreach ($report['checks'] as $check): ?>
                <li>
                    <strong><?= $check['ok'] ? 'PASS' : 'REVIEW' ?>:
                    <?= $e($check['label']) ?></strong>
                    <span><?= $e($check['details']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if ($report['schema_ready']): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Read-only counts</p>
            <h2>Existing Resource report coverage</h2>
        </div>
    </header>
    <div class="admin-user-action-box">
        <dl>
            <?php foreach ($report['counts'] as $label => $count): ?>
                <div>
                    <dt><?= $e(str_replace('_', ' ', $label)) ?></dt>
                    <dd><?= number_format((int) $count) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <p>Older approved submissions without history are expected until a separately
           reviewed backfill. They are not an error and will not be modified here.</p>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Evidence integrity</p>
            <h2>Cross-target and identity checks</h2>
        </div>
    </header>
    <div class="admin-user-action-box">
        <dl>
            <?php foreach ($report['integrity'] as $label => $count): ?>
                <div>
                    <dt><?= $e(str_replace('_', ' ', $label)) ?></dt>
                    <dd><?= number_format((int) $count) ?> <?= $count === 0 ? '(PASS)' : '(REVIEW)' ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <p>These are structural checks only. A zero count does not replace testing
           Resource and linked-Facility Add, Update, moderation and public display.</p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

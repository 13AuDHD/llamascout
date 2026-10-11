<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-mirror.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Full Mirror';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

$error = '';
$notice = '';

$ridbDb = ridb_db();

$feeds =
    llama_ridb_mirror_feeds();

$childFeeds =
    llama_ridb_mirror_child_feeds();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            @set_time_limit(0);

            $action =
                trim(
                    (string) (
                        $_POST['action']
                        ?? ''
                    )
                );

            $feed =
                trim(
                    (string) (
                        $_POST['feed']
                        ?? ''
                    )
                );

            if ($action === 'sync_feed') {
                $result =
                    llama_ridb_mirror_sync_feed_batch(
                        $ridbDb,
                        $feed,
                        500
                    );

                $notice =
                    number_format(
                        (int) $result['seen']
                    )
                    . ' '
                    . (
                        $feeds[$feed]['label']
                        ?? $feed
                    )
                    . ' records read; '
                    . number_format(
                        (int) $result['stored']
                    )
                    . ' stored in the lossless mirror.';
            } elseif ($action === 'sync_child_feed') {
                $result =
                    llama_ridb_mirror_sync_child_batch(
                        $ridbDb,
                        $feed,
                        50
                    );

                $notice =
                    number_format(
                        (int) $result['parents_seen']
                    )
                    . ' parent records checked; '
                    . number_format(
                        (int) $result['stored']
                    )
                    . ' child records stored.';
            } elseif ($action === 'reset_feed') {
                llama_ridb_mirror_reset_feed(
                    $ridbDb,
                    $feed
                );

                $notice =
                    'The selected mirror feed was reset to the beginning.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$counts =
    llama_ridb_mirror_counts(
        $ridbDb
    );

$states = [];

foreach ($feeds as $feed => $definition) {
    $states[$feed] =
        llama_ridb_mirror_sync_state(
            $ridbDb,
            $feed,
            $definition['endpoint']
        );
}

$childStates = [];

foreach ($childFeeds as $feed => $definition) {
    $childStates[$feed] =
        llama_ridb_mirror_child_state(
            $ridbDb,
            $feed,
            $definition['path']
        );
}

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
<section class="admin-panel">
    <div
        class="admin-user-notice is-success"
        role="status"
    >
        <?= moderation_e($notice) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <div
        class="admin-user-notice is-error"
        role="alert"
    >
        <?= moderation_e($error) ?>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Lossless source cache</p>
        <h2>RIDB Full Mirror</h2>
    </div>
</header>

<p>
    The normal RIDB catalog remains the searchable working index.
    This mirror preserves complete source JSON for additional RIDB
    datasets so useful source fields are not discarded just because
    Llama Scout does not map them yet.
</p>

<div class="admin-user-form-actions">
    <a
        class="admin-button is-secondary"
        href="/ridb.php"
    >
        Back to RIDB catalog
    </a>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Global RIDB datasets</p>
        <h2>Mirror feeds</h2>
    </div>
</header>

<div class="admin-integration-table-wrap">
<table class="admin-integration-table">
<thead>
<tr>
    <th>Dataset</th>
    <th>Cached</th>
    <th>Offset</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($feeds as $feed => $definition): ?>
<?php
$state =
    $states[$feed]
    ?? [];

$complete =
    (int) (
        $state['complete']
        ?? 0
    ) === 1;
?>
<tr>
    <td data-label="Dataset">
        <strong>
            <?= moderation_e(
                $definition['label']
            ) ?>
        </strong>
        <div>
            <?= moderation_e(
                $definition['endpoint']
            ) ?>
        </div>
    </td>

    <td data-label="Cached">
        <?= number_format(
            (int) (
                $counts[$feed]
                ?? 0
            )
        ) ?>
    </td>

    <td data-label="Offset">
        <?= number_format(
            (int) (
                $state['next_offset']
                ?? 0
            )
        ) ?>
    </td>

    <td data-label="Status">
        <span class="admin-status-pill">
            <?= $complete
                ? 'Complete'
                : 'In progress' ?>
        </span>
    </td>

    <td data-label="Action">
        <div class="admin-user-form-actions">
            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(
                        moderation_csrf_token()
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="sync_feed"
                >
                <input
                    type="hidden"
                    name="feed"
                    value="<?= moderation_e(
                        $feed
                    ) ?>"
                >

                <button
                    class="admin-button"
                    type="submit"
                    <?= $complete
                        ? 'disabled'
                        : '' ?>
                >
                    <?= (int) (
                        $state['next_offset']
                        ?? 0
                    ) > 0
                        ? 'Continue'
                        : 'Start' ?>
                </button>
            </form>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(
                        moderation_csrf_token()
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="reset_feed"
                >
                <input
                    type="hidden"
                    name="feed"
                    value="<?= moderation_e(
                        $feed
                    ) ?>"
                >

                <button
                    class="admin-button is-secondary"
                    type="submit"
                >
                    Reset
                </button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Per-entity detail datasets</p>
        <h2>Child feeds</h2>
    </div>
</header>

<p>
    Run these after their parent datasets are mirrored. They preserve
    attributes and zones that RIDB only exposes from an individual
    campsite, permit entrance, or tour endpoint.
</p>

<div class="admin-integration-table-wrap">
<table class="admin-integration-table">
<thead>
<tr>
    <th>Dataset</th>
    <th>Cached</th>
    <th>Parents processed</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($childFeeds as $feed => $definition): ?>
<?php
$state =
    $childStates[$feed]
    ?? [];

$complete =
    (int) (
        $state['complete']
        ?? 0
    ) === 1;
?>
<tr>
    <td data-label="Dataset">
        <strong>
            <?= moderation_e(
                $definition['label']
            ) ?>
        </strong>
    </td>

    <td data-label="Cached">
        <?= number_format(
            (int) (
                $counts[$feed]
                ?? 0
            )
        ) ?>
    </td>

    <td data-label="Parents processed">
        <?= number_format(
            (int) (
                $state['next_offset']
                ?? 0
            )
        ) ?>
    </td>

    <td data-label="Status">
        <span class="admin-status-pill">
            <?= $complete
                ? 'Complete'
                : 'In progress' ?>
        </span>
    </td>

    <td data-label="Action">
        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(
                    moderation_csrf_token()
                ) ?>"
            >
            <input
                type="hidden"
                name="action"
                value="sync_child_feed"
            >
            <input
                type="hidden"
                name="feed"
                value="<?= moderation_e(
                    $feed
                ) ?>"
            >

            <button
                class="admin-button"
                type="submit"
                <?= $complete
                    ? 'disabled'
                    : '' ?>
            >
                <?= (int) (
                    $state['next_offset']
                    ?? 0
                ) > 0
                    ? 'Continue'
                    : 'Start' ?>
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

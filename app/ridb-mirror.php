<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-mirror-extended.php';

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
$autoFeed = '';

$ridbDb =
    ridb_db();

$feeds =
    llama_ridb_mirror_feeds();

$childFeeds =
    llama_ridb_mirror_recommended_child_feeds();

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
            /*
             * IMPORTANT:
             * Keep every web request deliberately short. The browser may
             * continue automatically, but the PHP request itself must never
             * loop until an entire national RIDB feed is complete.
             */
            @set_time_limit(45);

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
                    . ' records processed. Offset is now '
                    . number_format(
                        (int) $result['next_offset']
                    )
                    . '.';

                if (empty($result['complete'])) {
                    $autoFeed = $feed;
                }
            } elseif (
                $action
                === 'extract_campsite_details'
            ) {
                /*
                 * This extraction is local DB work only, but can still be
                 * large. Keep the existing full backfill action manual for
                 * now rather than chaining it through gateway requests.
                 */
                $result =
                    llama_ridb_mirror_extract_cached_campsite_details(
                        $ridbDb,
                        1000
                    );

                $notice =
                    'Campsite detail extraction finished. '
                    . number_format(
                        (int) $result['sites']
                    )
                    . ' campsite records checked, '
                    . number_format(
                        (int) $result['attributes']
                    )
                    . ' embedded attributes, '
                    . number_format(
                        (int) $result['equipment']
                    )
                    . ' equipment records, and '
                    . number_format(
                        (int) $result['media']
                    )
                    . ' campsite media records stored.';
            } elseif ($action === 'sync_child_feed') {
                $result =
                    llama_ridb_mirror_sync_child_batch(
                        $ridbDb,
                        $feed,
                        25
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

            /*
             * Never auto-submit again after a failed request.
             */
            $autoFeed = '';
        }
    }
}

$counts =
    llama_ridb_mirror_counts(
        $ridbDb
    );

$detailCounts =
    llama_ridb_mirror_campsite_detail_counts(
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
    RIDB records are cached in the dedicated reference database before
    selected information is promoted into Llama Scout.
</p>

<p>
    Large feeds now run as a chain of short 500-record requests. The browser
    continues automatically, but each server request ends before the next one
    begins so a national feed does not sit inside one gateway request.
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

$isRunning =
    $autoFeed === $feed;
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
                : (
                    $isRunning
                        ? 'Running'
                        : 'In progress'
                ) ?>
        </span>
    </td>

    <td data-label="Action">
        <div class="admin-user-form-actions">
            <form
                method="post"
                <?= $isRunning
                    ? 'data-ridb-auto-sync'
                    : '' ?>
            >
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
                    <?= $complete
                        ? 'Complete'
                        : (
                            $isRunning
                                ? 'Running...'
                                : 'Continue safely'
                        ) ?>
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
                    <?= $isRunning
                        ? 'disabled'
                        : '' ?>
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
        <p>Campsite records already downloaded</p>
        <h2>Embedded campsite details</h2>
    </div>
</header>

<div class="admin-integration-summary">
<div>
    <strong>
        <?= number_format(
            (int) (
                $detailCounts[
                    'campsite_attributes'
                ]
                ?? 0
            )
        ) ?>
    </strong>
    <span>Campsite attributes</span>
</div>

<div>
    <strong>
        <?= number_format(
            (int) (
                $detailCounts[
                    'campsite_permitted_equipment'
                ]
                ?? 0
            )
        ) ?>
    </strong>
    <span>Equipment records</span>
</div>

<div>
    <strong>
        <?= number_format(
            (int) (
                $detailCounts[
                    'campsite_media'
                ]
                ?? 0
            )
        ) ?>
    </strong>
    <span>Campsite media</span>
</div>
</div>

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
        value="extract_campsite_details"
    >

    <button
        class="admin-button"
        type="submit"
    >
        Extract cached campsite details
    </button>
</form>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Per-entity detail datasets</p>
        <h2>Child feeds</h2>
    </div>
</header>

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
                <?= $complete
                    ? 'Complete'
                    : 'Process 25 parents' ?>
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</section>

<?php if ($autoFeed !== ''): ?>
<form
    method="post"
    id="ridb-auto-sync-next"
    hidden
>
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
            $autoFeed
        ) ?>"
    >
</form>

<script>
window.setTimeout(function () {
    var form =
        document.getElementById(
            'ridb-auto-sync-next'
        );

    if (form) {
        form.submit();
    }
}, 750);
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

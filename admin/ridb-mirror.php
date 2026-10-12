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
                    llama_ridb_mirror_sync_feed_until_complete(
                        $ridbDb,
                        $feed
                    );

                $notice =
                    (
                        $feeds[$feed]['label']
                        ?? $feed
                    )
                    . ' synchronization finished. '
                    . number_format(
                        (int) $result['seen']
                    )
                    . ' additional records were read in '
                    . number_format(
                        (int) $result['passes']
                    )
                    . ' checkpoint pass'
                    . (
                        (int) $result['passes'] === 1
                            ? ''
                            : 'es'
                    )
                    . '.';
            } elseif ($action === 'sync_all_feeds') {
                $results =
                    llama_ridb_mirror_sync_all_until_complete(
                        $ridbDb
                    );

                $notice =
                    !$results
                        ? 'Every global RIDB mirror feed was already complete.'
                        : 'Every incomplete global RIDB mirror feed finished. '
                            . number_format(
                                count($results)
                            )
                            . ' dataset'
                            . (
                                count($results) === 1
                                    ? ''
                                    : 's'
                            )
                            . ' were completed.';
            } elseif (
                $action
                === 'extract_campsite_details'
            ) {
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
                    . ' permitted-equipment records, and '
                    . number_format(
                        (int) $result['media']
                    )
                    . ' campsite media records stored.';
            } elseif ($action === 'sync_child_feed') {
                $result =
                    llama_ridb_mirror_sync_child_until_complete(
                        $ridbDb,
                        $feed
                    );

                $notice =
                    (
                        $childFeeds[$feed]['label']
                        ?? $feed
                    )
                    . ' synchronization finished. '
                    . number_format(
                        (int) $result['parents_seen']
                    )
                    . ' parent records were checked.';
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

$incompleteGlobal =
    array_filter(
        $states,
        static fn (array $state): bool =>
            (int) (
                $state['complete']
                ?? 0
            ) !== 1
    );

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
    selected information is promoted into Llama Scout. Full source JSON
    remains available even when Llama Scout does not currently map a field.
</p>

<div class="admin-user-form-actions">
    <a
        class="admin-button is-secondary"
        href="/ridb.php"
    >
        Back to RIDB catalog
    </a>

    <?php if ($incompleteGlobal): ?>
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
            value="sync_all_feeds"
        >

        <button
            class="admin-button"
            type="submit"
        >
            Finish all incomplete feeds
        </button>
    </form>
    <?php endif; ?>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Global RIDB datasets</p>
        <h2>Mirror feeds</h2>
    </div>
</header>

<p>
    A single Finish button now continues through every remaining
    5,000-record checkpoint automatically. You no longer need to press
    Continue for each 500 records.
</p>

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
                    <?= $complete
                        ? 'Complete'
                        : 'Finish' ?>
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
        <p>Campsite records already downloaded</p>
        <h2>Embedded campsite details</h2>
    </div>
</header>

<p>
    RIDB campsite records can already contain attributes, permitted equipment,
    and individual campsite media. Extract these directly from the cached
    campsite JSON instead of making a separate API request for every campsite.
</p>

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
        <p>Only data that still needs per-entity requests</p>
        <h2>Child feeds</h2>
    </div>
</header>

<p>
    Campsite attributes are no longer listed here because they are extracted
    from the campsite records already downloaded. These remaining feeds are
    only for RIDB data that still requires a parent-specific endpoint.
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
                <?= $complete
                    ? 'Complete'
                    : 'Finish' ?>
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

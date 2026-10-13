<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-catalog.php';
require_once dirname(__DIR__) . '/app/ridb-mirror.php';
require_once dirname(__DIR__) . '/app/ridb-campsite-extract.php';
require_once dirname(__DIR__) . '/app/ridb-status.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'Recreation.gov / RIDB';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'ridb';

$notice = '';
$error = '';

$mirrorAutoRunning = false;
$mirrorAutoFeed = '';

$extractAutoRunning = false;

try {
    $ridbDb =
        ridb_db();

    $ridbDb->query(
        'SELECT 1'
    );
} catch (Throwable $exception) {
    $ridbDb = null;

    $error =
        'RIDB database connection failed: '
        . $exception->getMessage();
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
    && $ridbDb instanceof PDO
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
        $action =
            trim(
                (string) (
                    $_POST['action']
                    ?? ''
                )
            );

        try {
            @set_time_limit(45);

            /*
             * =========================================================
             * LOSSLESS GLOBAL MIRROR
             * =========================================================
             */
            if (
                in_array(
                    $action,
                    [
                        'mirror_auto_start',
                        'mirror_auto_tick',
                    ],
                    true
                )
            ) {
                $requestedFeed =
                    trim(
                        (string) (
                            $_POST['feed']
                            ?? ''
                        )
                    );

                $mirrorAutoFeed =
                    $requestedFeed !== ''
                        ? $requestedFeed
                        : (
                            llama_ridb_mirror_first_incomplete_feed(
                                $ridbDb
                            )
                            ?? ''
                        );

                if (
                    $mirrorAutoFeed !== ''
                    && isset(
                        llama_ridb_mirror_feeds()[
                            $mirrorAutoFeed
                        ]
                    )
                ) {
                    $feeds =
                        llama_ridb_mirror_feeds();

                    $result =
                        llama_ridb_mirror_sync_feed_batch(
                            $ridbDb,
                            $mirrorAutoFeed,
                            500
                        );

                    $notice =
                        number_format(
                            (int) (
                                $result['seen']
                                ?? 0
                            )
                        )
                        . ' '
                        . $feeds[
                            $mirrorAutoFeed
                        ]['label']
                        . ' records processed. Offset '
                        . number_format(
                            (int) (
                                $result['next_offset']
                                ?? 0
                            )
                        )
                        . '.';

                    if (
                        !empty(
                            $result['complete']
                        )
                    ) {
                        $nextFeed =
                            llama_ridb_mirror_first_incomplete_feed(
                                $ridbDb
                            );

                        if ($nextFeed !== null) {
                            $mirrorAutoFeed =
                                $nextFeed;

                            $mirrorAutoRunning =
                                true;

                            $notice .=
                                ' That feed is complete. The next feed starts after the 3-second pause.';
                        } else {
                            $mirrorAutoFeed = '';
                            $mirrorAutoRunning =
                                false;

                            $notice .=
                                ' Every global RIDB mirror feed is complete.';
                        }
                    } else {
                        $mirrorAutoRunning =
                            true;
                    }
                }

            } elseif (
                $action
                === 'mirror_single_batch'
            ) {
                $feed =
                    trim(
                        (string) (
                            $_POST['feed']
                            ?? ''
                        )
                    );

                $feeds =
                    llama_ridb_mirror_feeds();

                if (!isset($feeds[$feed])) {
                    throw new InvalidArgumentException(
                        'Unknown RIDB mirror feed.'
                    );
                }

                $result =
                    llama_ridb_mirror_sync_feed_batch(
                        $ridbDb,
                        $feed,
                        500
                    );

                $notice =
                    number_format(
                        (int) (
                            $result['seen']
                            ?? 0
                        )
                    )
                    . ' '
                    . $feeds[$feed]['label']
                    . ' records processed. Offset '
                    . number_format(
                        (int) (
                            $result['next_offset']
                            ?? 0
                        )
                    )
                    . '.';

            } elseif (
                $action
                === 'mirror_stop'
            ) {
                $notice =
                    'Automatic RIDB mirror synchronization stopped.';

            /*
             * =========================================================
             * CAMPSITE EMBEDDED DETAIL EXTRACTION
             * =========================================================
             */
            } elseif (
                in_array(
                    $action,
                    [
                        'extract_auto_start',
                        'extract_auto_tick',
                        'extract_run_once',
                    ],
                    true
                )
            ) {
                $result =
                    llama_ridb_campsite_extract_batch(
                        $ridbDb,
                        100
                    );

                $notice =
                    number_format(
                        (int) (
                            $result['seen']
                            ?? 0
                        )
                    )
                    . ' campsites processed, '
                    . number_format(
                        (int) (
                            $result['attributes']
                            ?? 0
                        )
                    )
                    . ' attributes, '
                    . number_format(
                        (int) (
                            $result['equipment']
                            ?? 0
                        )
                    )
                    . ' equipment records, and '
                    . number_format(
                        (int) (
                            $result['media']
                            ?? 0
                        )
                    )
                    . ' campsite media rows extracted.';

                $extractAutoRunning =
                    in_array(
                        $action,
                        [
                            'extract_auto_start',
                            'extract_auto_tick',
                        ],
                        true
                    )
                    && empty(
                        $result['complete']
                    );

                if (
                    !empty(
                        $result['complete']
                    )
                ) {
                    $notice .=
                        ' Campsite detail extraction is complete.';
                }

            } elseif (
                $action
                === 'extract_stop'
            ) {
                $notice =
                    'Automatic campsite detail extraction stopped.';

            /*
             * =========================================================
             * NORMALIZED FACILITY CATALOG
             * =========================================================
             */
            } elseif (
                $action
                === 'catalog_sync'
            ) {
                $result =
                    llama_ridb_catalog_sync_batch(
                        $ridbDb,
                        1000
                    );

                $notice =
                    number_format(
                        (int) (
                            $result['seen']
                            ?? 0
                        )
                    )
                    . ' facilities checked and '
                    . number_format(
                        (int) (
                            $result['stored']
                            ?? 0
                        )
                    )
                    . ' cached locally. '
                    . (
                        !empty(
                            $result['complete']
                        )
                            ? 'The facility catalog is complete.'
                            : 'More facilities remain.'
                    );

            } elseif (
                $action
                === 'catalog_restart'
            ) {
                llama_ridb_catalog_reset_sync(
                    $ridbDb
                );

                $notice =
                    'Facility catalog progress was reset. Existing cached facilities were not deleted.';

            } elseif (
                $action
                === 'reconcile_status'
            ) {
                $result =
                    llama_ridb_status_reconcile(
                        $ridbDb,
                        db()
                    );

                $notice =
                    number_format(
                        (int) (
                            $result['imported']
                            ?? 0
                        )
                    )
                    . ' imported RIDB facilities reconciled. '
                    . number_format(
                        (int) (
                            $result['source_updated']
                            ?? 0
                        )
                    )
                    . ' have newer RIDB source data available.';

            } elseif (
                $action
                === 'test_api'
            ) {
                llama_ridb_request(
                    '/facilities',
                    [
                        'limit' => 1,
                        'offset' => 0,
                    ]
                );

                $notice =
                    'RIDB API connection succeeded.';
            }

        } catch (Throwable $exception) {
            $mirrorAutoRunning = false;
            $mirrorAutoFeed = '';
            $extractAutoRunning = false;

            $error =
                $exception->getMessage();
        }
    }
}

/*
 * =========================================================
 * DASHBOARD DATA
 * =========================================================
 */

$mirrorFeeds =
    llama_ridb_mirror_feeds();

$mirrorCounts = [];
$mirrorStates = [];
$firstIncompleteMirror = null;

$extractState = [];
$extractCounts = [
    'total_campsites' => 0,
    'attributes' => 0,
    'equipment' => 0,
    'media' => 0,
];

$catalogCounts = [
    'facilities' => 0,
    'campsites' => 0,
    'attributes' => 0,
];

$catalogSyncState = [];

$filters =
    llama_ridb_catalog_filter(
        $_GET
    );

$filters['status'] =
    llama_ridb_status_filter(
        $_GET['status']
        ?? ''
    );

$catalog = [
    'rows' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
];

$types = [];
$states = [];
$importedLookup = [];

$statusCounts = [
    'facilities' => 0,
    'imported' => 0,
    'not_imported' => 0,
    'source_updated' => 0,
    'current' => 0,
    'missing_source' => 0,
];

if ($ridbDb instanceof PDO) {
    try {
        $mirrorCounts =
            llama_ridb_mirror_counts(
                $ridbDb
            );

        foreach (
            $mirrorFeeds
            as $feed => $definition
        ) {
            $mirrorStates[$feed] =
                llama_ridb_mirror_sync_state(
                    $ridbDb,
                    $feed,
                    $definition['endpoint']
                );
        }

        $firstIncompleteMirror =
            llama_ridb_mirror_first_incomplete_feed(
                $ridbDb
            );

        $extractState =
            llama_ridb_campsite_extract_state(
                $ridbDb
            );

        $extractCounts =
            llama_ridb_campsite_extract_counts(
                $ridbDb
            );

        $catalogCounts =
            llama_ridb_catalog_counts(
                $ridbDb
            );

        $catalogSyncState =
            llama_ridb_catalog_sync_state(
                $ridbDb
            );

        $statusCounts =
            llama_ridb_status_counts(
                $ridbDb
            );

        $catalog =
            llama_ridb_status_catalog_facilities(
                $ridbDb,
                $filters,
                50
            );

        $types =
            llama_ridb_catalog_distinct_types(
                $ridbDb
            );

        $states =
            llama_ridb_catalog_distinct_states(
                $ridbDb
            );

        $importedLookup =
            llama_ridb_catalog_imported_lookup(
                db(),
                array_column(
                    $catalog['rows'],
                    'ridb_facility_id'
                )
            );

    } catch (Throwable $exception) {
        if ($error === '') {
            $error =
                $exception->getMessage();
        }
    }
}

$mirrorCompleteCount = 0;

foreach ($mirrorStates as $state) {
    if (
        (int) (
            $state['complete']
            ?? 0
        ) === 1
    ) {
        $mirrorCompleteCount++;
    }
}

$mirrorAllComplete =
    $mirrorFeeds
    && $mirrorCompleteCount
        === count($mirrorFeeds);

$extractProcessed =
    (int) (
        $extractState['total_seen']
        ?? 0
    );

$extractTotal =
    (int) (
        $extractCounts['total_campsites']
        ?? 0
    );

$extractPercent =
    $extractTotal > 0
        ? min(
            100,
            (int) floor(
                (
                    $extractProcessed
                    / $extractTotal
                )
                * 100
            )
        )
        : 0;

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
        <p>RIDB control center</p>
        <h2>Recreation.gov / RIDB</h2>
    </div>

    <span class="admin-status-pill">
        <?= $mirrorAllComplete
            ? 'Source mirror complete'
            : 'Source mirror incomplete' ?>
    </span>
</header>

<div class="admin-integration-summary">
    <div>
        <strong>
            <?= number_format(
                (int) (
                    $mirrorCounts['facilities']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Mirrored facilities</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $mirrorCounts['campsites']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Mirrored campsites</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['attributes']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Extracted site attributes</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['equipment']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Extracted equipment</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['media']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Campsite media rows</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $catalogCounts['facilities']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Searchable facilities</span>
    </div>
</div>

<div class="admin-user-action-box">
    <p>
        The lossless RIDB mirror, campsite detail extraction, facility catalog,
        production imports, and data-mapping tools are controlled from this page.
        Source data stays in the dedicated RIDB database until you explicitly
        import a facility into Llama Scout.
    </p>

    <div class="admin-user-form-actions">
        <a
            class="admin-button is-secondary"
            href="/ridb-campsite-inspector.php"
        >
            Campsite Inspector
        </a>

        <a
            class="admin-button is-secondary"
            href="/ridb-schema.php"
        >
            Schema Explorer
        </a>

        <a
            class="admin-button is-secondary"
            href="/ridb-normalization.php"
        >
            Normalization
        </a>
    </div>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Lossless source archive</p>
        <h2>Source Mirror</h2>
    </div>

    <span class="admin-status-pill">
        <?= $mirrorAllComplete
            ? 'Complete'
            : (
                $mirrorAutoRunning
                    ? 'Running'
                    : 'Incomplete'
            ) ?>
    </span>
</header>

<div class="admin-user-action-box">
    <p>
        The source mirror stores RIDB records before Llama Scout interprets or
        normalizes them. Automatic synchronization processes 500 records per
        request, fully ends the PHP request, waits 3 seconds, then continues.
    </p>

    <div class="admin-user-form-actions">
        <?php if (
            $firstIncompleteMirror !== null
            && !$mirrorAutoRunning
            && !$extractAutoRunning
        ): ?>
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
                    value="mirror_auto_start"
                >
                <input
                    type="hidden"
                    name="feed"
                    value="<?= moderation_e(
                        $firstIncompleteMirror
                    ) ?>"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    Resume source mirror
                </button>
            </form>
        <?php endif; ?>

        <?php if ($mirrorAutoRunning): ?>
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
                    value="mirror_stop"
                >

                <button
                    class="admin-button is-secondary"
                    type="submit"
                >
                    Stop after this batch
                </button>
            </form>
        <?php endif; ?>

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
                value="test_api"
            >

            <button
                class="admin-button is-secondary"
                type="submit"
            >
                Test RIDB API
            </button>
        </form>
    </div>
</div>

<?php if ($mirrorAutoRunning): ?>
<div class="admin-user-notice">
    Source mirror is running:
    <strong>
        <?= moderation_e(
            $mirrorFeeds[
                $mirrorAutoFeed
            ]['label']
            ?? $mirrorAutoFeed
        ) ?>
    </strong>.
    Next 500-record batch starts in about 3 seconds.
</div>
<?php endif; ?>

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

<?php foreach (
    $mirrorFeeds
    as $feed => $definition
): ?>
<?php
$state =
    $mirrorStates[$feed]
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
        <small>
            <?= moderation_e(
                $definition['endpoint']
            ) ?>
        </small>
    </td>

    <td data-label="Cached">
        <?= number_format(
            (int) (
                $mirrorCounts[$feed]
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
                    $mirrorAutoRunning
                    && $mirrorAutoFeed === $feed
                        ? 'Running'
                        : 'Incomplete'
                ) ?>
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
                value="mirror_single_batch"
            >
            <input
                type="hidden"
                name="feed"
                value="<?= moderation_e($feed) ?>"
            >

            <button
                class="admin-button is-secondary"
                type="submit"
                <?= $complete
                    || $mirrorAutoRunning
                    || $extractAutoRunning
                        ? 'disabled'
                        : '' ?>
            >
                <?= $complete
                    ? 'Complete'
                    : 'Run 500 once' ?>
            </button>
        </form>
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
        <p>Local-only normalization</p>
        <h2>Campsite Detail Extraction</h2>
    </div>

    <span class="admin-status-pill">
        <?= (int) (
            $extractState['complete']
            ?? 0
        ) === 1
            ? 'Complete'
            : (
                $extractAutoRunning
                    ? 'Running'
                    : $extractPercent . '%'
            ) ?>
    </span>
</header>

<div class="admin-integration-summary">
    <div>
        <strong>
            <?= number_format(
                $extractProcessed
            ) ?>
        </strong>
        <span>Campsites processed</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                $extractTotal
            ) ?>
        </strong>
        <span>Campsites in mirror</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                $extractPercent
            ) ?>%
        </strong>
        <span>Extraction progress</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['attributes']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Attribute rows</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['equipment']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Equipment rows</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $extractCounts['media']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Campsite media rows</span>
    </div>
</div>

<div class="admin-user-action-box">
    <p>
        This pass never calls RIDB. It only extracts embedded campsite
        ATTRIBUTES, PERMITTEDEQUIPMENT, and ENTITYMEDIA from the completed local
        mirror. Each request handles at most 100 campsites, exits, waits 3
        seconds, then continues.
    </p>

    <div class="admin-user-form-actions">
        <?php if (
            (int) (
                $extractState['complete']
                ?? 0
            ) !== 1
            && !$extractAutoRunning
            && !$mirrorAutoRunning
        ): ?>
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
                    value="extract_auto_start"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    Resume campsite extraction
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
                    value="extract_run_once"
                >

                <button
                    class="admin-button is-secondary"
                    type="submit"
                >
                    Run 100 once
                </button>
            </form>
        <?php endif; ?>

        <?php if ($extractAutoRunning): ?>
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
                    value="extract_stop"
                >

                <button
                    class="admin-button is-secondary"
                    type="submit"
                >
                    Stop after this batch
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($extractAutoRunning): ?>
<div class="admin-user-notice">
    Campsite extraction is running.
    The next 100-campsite batch starts in about 3 seconds.
</div>
<?php endif; ?>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>RIDB vs Llama Scout</p>
        <h2>Production Sync Status</h2>
    </div>
</header>

<div class="admin-integration-summary">
    <div>
        <strong>
            <?= number_format(
                (int) (
                    $statusCounts['imported']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Imported facilities</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $statusCounts['not_imported']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>RIDB only / not imported</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $statusCounts['source_updated']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Source updates available</span>
    </div>

    <div>
        <strong>
            <?= number_format(
                (int) (
                    $statusCounts['current']
                    ?? 0
                )
            ) ?>
        </strong>
        <span>Imported and current</span>
    </div>
</div>

<div class="admin-user-action-box">
    <p>
        Reconciliation compares the RIDB source update timestamp with the
        source timestamp saved when each Llama Scout Place was last imported
        or synchronized. It never changes a Place.
    </p>

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
                value="reconcile_status"
            >

            <button
                class="admin-button"
                type="submit"
            >
                Reconcile production status
            </button>
        </form>

        <a
            class="admin-button is-secondary"
            href="/ridb.php?status=not_imported"
        >
            Browse not imported
        </a>

        <a
            class="admin-button is-secondary"
            href="/ridb.php?status=source_updated"
        >
            Browse source updates
        </a>
    </div>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Searchable facility index</p>
        <h2>Facility Catalog</h2>
    </div>

    <?php if (
        !empty(
            $catalogSyncState['complete']
        )
    ): ?>
        <span class="admin-status-pill">
            Complete
        </span>
    <?php endif; ?>
</header>

<div class="admin-user-action-box">
    <p>
        This normalized facility index powers RIDB search and production
        imports. Re-running it updates existing cached facilities and discovers
        newly-added source facilities.
    </p>

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
                value="catalog_sync"
            >

            <button
                class="admin-button"
                type="submit"
                <?= !empty(
                    $catalogSyncState['complete']
                )
                    ? 'disabled'
                    : '' ?>
            >
                <?= (int) (
                    $catalogSyncState['next_offset']
                    ?? 0
                ) > 0
                    ? 'Continue facility catalog'
                    : 'Start facility catalog' ?>
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
                value="catalog_restart"
            >

            <button
                class="admin-button is-secondary"
                type="submit"
            >
                Recheck catalog from beginning
            </button>
        </form>
    </div>
</div>
</section>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Reference database</p>
        <h2>
            <?= number_format(
                (int) (
                    $catalog['total']
                    ?? 0
                )
            ) ?>
            matching facilities
        </h2>
    </div>
</header>

<form
    class="admin-user-action-box"
    method="get"
>
<div class="admin-integration-summary">
<label>
    <span>Search</span>
    <input
        type="search"
        name="q"
        value="<?= moderation_e(
            (string) (
                $filters['q']
                ?? ''
            )
        ) ?>"
        placeholder="Campground, facility name, RIDB ID..."
    >
</label>

<label>
    <span>State</span>
    <select name="state">
        <option value="">
            All cached states
        </option>

        <?php foreach ($states as $state): ?>
            <option
                value="<?= moderation_e(
                    (string) $state
                ) ?>"
                <?= (
                    $filters['state']
                    ?? ''
                ) === $state
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    (string) $state
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Facility type</span>
    <select name="type">
        <option value="">
            All facility types
        </option>

        <?php foreach ($types as $type): ?>
            <option
                value="<?= moderation_e(
                    (string) $type
                ) ?>"
                <?= (
                    $filters['type']
                    ?? ''
                ) === $type
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    (string) $type
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Reservable</span>
    <select name="reservable">
        <option value="">All</option>
        <option
            value="yes"
            <?= (
                $filters['reservable']
                ?? ''
            ) === 'yes'
                ? 'selected'
                : '' ?>
        >
            Yes
        </option>
        <option
            value="no"
            <?= (
                $filters['reservable']
                ?? ''
            ) === 'no'
                ? 'selected'
                : '' ?>
        >
            No / unknown
        </option>
    </select>
</label>

<label>
    <span>Llama Scout status</span>
    <select name="status">
        <option value="">
            All source records
        </option>

        <option
            value="not_imported"
            <?= (
                $filters['status']
                ?? ''
            ) === 'not_imported'
                ? 'selected'
                : '' ?>
        >
            Not imported
        </option>

        <option
            value="imported"
            <?= (
                $filters['status']
                ?? ''
            ) === 'imported'
                ? 'selected'
                : '' ?>
        >
            Imported
        </option>

        <option
            value="source_updated"
            <?= (
                $filters['status']
                ?? ''
            ) === 'source_updated'
                ? 'selected'
                : '' ?>
        >
            Source updated
        </option>

        <option
            value="current"
            <?= (
                $filters['status']
                ?? ''
            ) === 'current'
                ? 'selected'
                : '' ?>
        >
            Imported and current
        </option>
    </select>
</label>
</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Apply filters
    </button>

    <a
        class="admin-button is-secondary"
        href="/ridb.php"
    >
        Clear filters
    </a>
</div>
</form>
</section>

<section class="admin-panel">
<?php if (
    empty(
        $catalog['rows']
    )
): ?>
    <div class="admin-empty-state">
        <h3>No matching RIDB facilities are cached yet.</h3>
    </div>
<?php else: ?>

<form
    method="post"
    action="/ridb-import.php"
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
    name="return_url"
    value="<?= moderation_e(
        llama_ridb_status_catalog_url(
            $filters
        )
    ) ?>"
>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Import / synchronize selected
    </button>
</div>

<div class="admin-integration-table-wrap">
<table class="admin-integration-table">
<thead>
<tr>
    <th>Select</th>
    <th>Facility</th>
    <th>Type</th>
    <th>Location</th>
    <th>Reservable</th>
    <th>Cached sites</th>
    <th>Llama Scout</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach (
    $catalog['rows']
    as $row
): ?>
<?php
$facilityId =
    (string) (
        $row['ridb_facility_id']
        ?? ''
    );

$imported =
    $importedLookup[
        $facilityId
    ]
    ?? null;
?>
<tr>
<td data-label="Select">
    <input
        type="checkbox"
        name="facility_ids[]"
        value="<?= moderation_e(
            $facilityId
        ) ?>"
    >
</td>

<td data-label="Facility">
    <strong>
        <?= moderation_e(
            (string) (
                $row['facility_name']
                ?? 'Unnamed facility'
            )
        ) ?>
    </strong>
    <small>
        RIDB <?= moderation_e($facilityId) ?>
    </small>
</td>

<td data-label="Type">
    <?= moderation_e(
        (string) (
            $row[
                'facility_type_description'
            ]
            ?? ''
        )
    ) ?>
</td>

<td data-label="Location">
    <?php
    $location =
        array_filter(
            [
                trim(
                    (string) (
                        $row['city']
                        ?? ''
                    )
                ),
                trim(
                    (string) (
                        $row['state_code']
                        ?? ''
                    )
                ),
            ]
        );
    ?>
    <?= $location
        ? moderation_e(
            implode(
                ', ',
                $location
            )
        )
        : 'Not indexed yet' ?>
</td>

<td data-label="Reservable">
    <?= (int) (
        $row['reservable']
        ?? 0
    ) === 1
        ? 'Yes'
        : 'No / unknown' ?>
</td>

<td data-label="Cached sites">
    <?= number_format(
        (int) (
            $row[
                'cached_campsite_count'
            ]
            ?? 0
        )
    ) ?>
</td>

<td data-label="Llama Scout">
    <?php
    $syncStatus =
        trim(
            (string) (
                $row['sync_status']
                ?? ''
            )
        );

    $statusPlaceId =
        (int) (
            $row['place_id']
            ?? (
                $imported['place_id']
                ?? 0
            )
        );
    ?>

    <?php if ($statusPlaceId > 0): ?>
        <strong>
            <?= $syncStatus === 'source_updated'
                ? 'Source updated'
                : 'Imported' ?>
        </strong>

        <small>
            Place #
            <?= number_format(
                $statusPlaceId
            ) ?>
        </small>

        <?php if (
            $syncStatus
            === 'source_updated'
        ): ?>
            <small>
                RIDB has newer source data
            </small>
        <?php elseif (
            $syncStatus === 'current'
        ): ?>
            <small>
                Source current
            </small>
        <?php endif; ?>
    <?php else: ?>
        Not imported
    <?php endif; ?>
</td>

<td data-label="Action">
    <div class="admin-user-form-actions">
        <a
            class="admin-button is-secondary"
            href="/ridb-facility.php?id=<?= rawurlencode(
                $facilityId
            ) ?>"
        >
            Inspect
        </a>

        <?php if ($imported): ?>
            <a
                class="admin-button"
                href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                    (string) (
                        $imported['slug']
                        ?? ''
                    )
                ) ?>"
                target="_blank"
                rel="noopener"
            >
                Open Place
            </a>
        <?php endif; ?>
    </div>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Import / synchronize selected
    </button>
</div>
</form>

<nav
    class="admin-user-form-actions"
    aria-label="RIDB catalog pages"
>
<?php if (
    (int) (
        $catalog['page']
        ?? 1
    ) > 1
): ?>
<?php
$previousFilters =
    $filters;

$previousFilters['page'] =
    (int) $catalog['page']
    - 1;
?>
<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_ridb_status_catalog_url(
            $previousFilters
        )
    ) ?>"
>
    Previous
</a>
<?php endif; ?>

<span>
    Page
    <?= number_format(
        (int) (
            $catalog['page']
            ?? 1
        )
    ) ?>
    of
    <?= number_format(
        (int) (
            $catalog['pages']
            ?? 1
        )
    ) ?>
</span>

<?php if (
    (int) (
        $catalog['page']
        ?? 1
    )
    < (int) (
        $catalog['pages']
        ?? 1
    )
): ?>
<?php
$nextFilters =
    $filters;

$nextFilters['page'] =
    (int) $catalog['page']
    + 1;
?>
<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_ridb_status_catalog_url(
            $nextFilters
        )
    ) ?>"
>
    Next
</a>
<?php endif; ?>
</nav>

<?php endif; ?>
</section>

<?php if (
    $mirrorAutoRunning
    && $mirrorAutoFeed !== ''
): ?>
<form
    id="ridb-auto-next"
    method="post"
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
        value="mirror_auto_tick"
    >
    <input
        type="hidden"
        name="feed"
        value="<?= moderation_e(
            $mirrorAutoFeed
        ) ?>"
    >
</form>

<script>
window.setTimeout(
    function () {
        var form =
            document.getElementById(
                'ridb-auto-next'
            );

        if (form) {
            form.submit();
        }
    },
    3000
);
</script>
<?php elseif ($extractAutoRunning): ?>
<form
    id="ridb-auto-next"
    method="post"
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
        value="extract_auto_tick"
    >
</form>

<script>
window.setTimeout(
    function () {
        var form =
            document.getElementById(
                'ridb-auto-next'
            );

        if (form) {
            form.submit();
        }
    },
    3000
);
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

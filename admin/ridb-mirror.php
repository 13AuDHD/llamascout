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

$ridbDb =
    ridb_db();

$feeds =
    llama_ridb_mirror_feeds();

$autoRunning = false;
$autoFeed = '';

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
             * One request handles at most 500 records.
             * The browser waits 15 seconds before requesting the next batch.
             */
            @set_time_limit(45);

            $action =
                trim(
                    (string) (
                        $_POST['action']
                        ?? ''
                    )
                );

            $requestedFeed =
                trim(
                    (string) (
                        $_POST['feed']
                        ?? ''
                    )
                );

            if (
                in_array(
                    $action,
                    [
                        'auto_start',
                        'auto_tick',
                    ],
                    true
                )
            ) {
                $autoFeed =
                    $requestedFeed !== ''
                        ? $requestedFeed
                        : (
                            llama_ridb_mirror_first_incomplete_feed(
                                $ridbDb
                            )
                            ?? ''
                        );

                if (
                    $autoFeed !== ''
                    && isset($feeds[$autoFeed])
                ) {
                    $result =
                        llama_ridb_mirror_sync_feed_batch(
                            $ridbDb,
                            $autoFeed,
                            500
                        );

                    $notice =
                        number_format(
                            (int) $result['seen']
                        )
                        . ' '
                        . $feeds[$autoFeed]['label']
                        . ' records processed. Offset '
                        . number_format(
                            (int) $result['next_offset']
                        )
                        . '.';

                    if (!empty($result['complete'])) {
                        $nextFeed =
                            llama_ridb_mirror_first_incomplete_feed(
                                $ridbDb
                            );

                        if ($nextFeed !== null) {
                            $autoFeed = $nextFeed;
                            $autoRunning = true;

                            $notice .=
                                ' That feed is complete. The importer will move to '
                                . $feeds[$nextFeed]['label']
                                . ' after the 15-second pause.';
                        } else {
                            $autoFeed = '';
                            $autoRunning = false;

                            $notice .=
                                ' Every global RIDB mirror feed is complete.';
                        }
                    } else {
                        $autoRunning = true;
                    }
                }
            } elseif ($action === 'single_batch') {
                if (!isset($feeds[$requestedFeed])) {
                    throw new InvalidArgumentException(
                        'Unknown RIDB mirror feed.'
                    );
                }

                $result =
                    llama_ridb_mirror_sync_feed_batch(
                        $ridbDb,
                        $requestedFeed,
                        500
                    );

                $notice =
                    number_format(
                        (int) $result['seen']
                    )
                    . ' '
                    . $feeds[$requestedFeed]['label']
                    . ' records processed. Offset '
                    . number_format(
                        (int) $result['next_offset']
                    )
                    . '.';
            } elseif ($action === 'reset_feed') {
                llama_ridb_mirror_reset_feed(
                    $ridbDb,
                    $requestedFeed
                );

                $notice =
                    'The selected feed was reset to the beginning.';
            } elseif ($action === 'stop_auto') {
                $notice =
                    'Automatic RIDB synchronization stopped.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();

            /*
             * Never continue automatically after an error.
             */
            $autoRunning = false;
            $autoFeed = '';
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

$firstIncomplete =
    llama_ridb_mirror_first_incomplete_feed(
        $ridbDb
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
        <h2>RIDB Slow Importer</h2>
    </div>
</header>

<p>
    Automatic mode processes one 500-record batch, ends the PHP request,
    waits 15 seconds in the browser, then starts the next batch. It continues
    from the saved RIDB offset and moves to the next incomplete global feed
    automatically.
</p>

<p>
    Closing this page, navigating away, or pressing Stop prevents another
    batch from starting. A failed batch also stops automatic mode.
</p>

<div class="admin-user-form-actions">

<a
    class="admin-button is-secondary"
    href="/ridb.php"
>
    Back to RIDB catalog
</a>

<?php if ($firstIncomplete !== null && !$autoRunning): ?>
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
        value="auto_start"
    >
    <input
        type="hidden"
        name="feed"
        value="<?= moderation_e(
            $firstIncomplete
        ) ?>"
    >

    <button
        class="admin-button"
        type="submit"
    >
        Start slow automatic import
    </button>
</form>
<?php endif; ?>

<?php if ($autoRunning): ?>
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
        value="stop_auto"
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

<?php if ($autoRunning && $autoFeed !== ''): ?>
<div class="admin-user-notice">
    Automatic import is running:
    <strong>
        <?= moderation_e(
            $feeds[$autoFeed]['label']
            ?? $autoFeed
        ) ?>
    </strong>.
    Next 500-record batch starts in about 15 seconds.
</div>
<?php endif; ?>

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

$isCurrent =
    $autoRunning
    && $autoFeed === $feed;
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
                    $isCurrent
                        ? 'Auto'
                        : 'In progress'
                ) ?>
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
                value="single_batch"
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
                <?= $complete || $autoRunning
                    ? 'disabled'
                    : '' ?>
            >
                <?= $complete
                    ? 'Complete'
                    : 'Run 500 once' ?>
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
                <?= $autoRunning
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

<?php if ($autoRunning && $autoFeed !== ''): ?>
<form
    id="ridb-slow-auto-next"
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
        value="auto_tick"
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
(function () {
    var nextForm =
        document.getElementById(
            'ridb-slow-auto-next'
        );

    if (!nextForm) {
        return;
    }

    window.setTimeout(
        function () {
            nextForm.submit();
        },
        15000
    );
}());
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

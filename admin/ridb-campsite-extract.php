<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-campsite-extract.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Campsite Detail Extraction';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

$ridbDb =
    ridb_db();

$notice = '';
$error = '';
$autoRunning = false;

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
            @set_time_limit(30);

            $action =
                trim(
                    (string) (
                        $_POST['action']
                        ?? ''
                    )
                );

            if (
                in_array(
                    $action,
                    [
                        'run_once',
                        'auto_start',
                        'auto_tick',
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
                    . ' media rows extracted.';

                $autoRunning =
                    in_array(
                        $action,
                        [
                            'auto_start',
                            'auto_tick',
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
                === 'stop_auto'
            ) {
                $notice =
                    'Automatic campsite detail extraction stopped.';
            } elseif (
                $action
                === 'reset_progress'
            ) {
                llama_ridb_campsite_extract_reset(
                    $ridbDb
                );

                $notice =
                    'Extraction progress was reset to the beginning. Existing extracted rows were not deleted.';
            }

        } catch (Throwable $exception) {
            $autoRunning = false;
            $error =
                $exception->getMessage();
        }
    }
}

$state =
    llama_ridb_campsite_extract_state(
        $ridbDb
    );

$counts =
    llama_ridb_campsite_extract_counts(
        $ridbDb
    );

$processed =
    (int) (
        $state['total_seen']
        ?? 0
    );

$total =
    max(
        0,
        (int) (
            $counts['total_campsites']
            ?? 0
        )
    );

$percent =
    $total > 0
        ? min(
            100,
            (int) floor(
                (
                    $processed
                    / $total
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
            <p>Local-only normalization</p>
            <h2>Campsite Embedded Detail Extraction</h2>
        </div>

        <a
            class="admin-button is-secondary"
            href="/ridb-campsite-inspector.php"
        >
            Campsite Inspector
        </a>
    </header>

    <div class="admin-user-action-box">
        <p>
            This reads only the completed local RIDB campsite mirror.
            It makes no RIDB API requests. Each request processes at most
            100 campsite records, exits, waits 15 seconds in the browser,
            then starts the next batch.
        </p>

        <p>
            Embedded ATTRIBUTES become campsite attribute mirror rows,
            PERMITTEDEQUIPMENT becomes campsite equipment mirror rows,
            and ENTITYMEDIA remains campsite-linked media.
        </p>
    </div>

    <div class="admin-integration-summary">
        <div>
            <strong>
                <?= number_format($processed) ?>
            </strong>
            <span>Campsites processed</span>
        </div>

        <div>
            <strong>
                <?= number_format($total) ?>
            </strong>
            <span>Campsites in mirror</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $counts['attributes']
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
                        $counts['equipment']
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
                        $counts['media']
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Campsite media rows</span>
        </div>

        <div>
            <strong>
                <?= number_format($percent) ?>%
            </strong>
            <span>Extraction progress</span>
        </div>
    </div>

    <div class="admin-user-form-actions">
        <?php if (
            (int) (
                $state['complete']
                ?? 0
            ) !== 1
            && !$autoRunning
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
                    value="auto_start"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    Start slow automatic extraction
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
                    value="run_once"
                >

                <button
                    class="admin-button is-secondary"
                    type="submit"
                >
                    Run 100 once
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

    <?php if ($autoRunning): ?>
        <div class="admin-user-notice">
            Automatic extraction is running.
            The next 100-campsite batch starts in about 15 seconds.
        </div>
    <?php endif; ?>
</section>

<?php if ($autoRunning): ?>
<form
    id="ridb-campsite-extract-next"
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
</form>

<script>
(function () {
    var form =
        document.getElementById(
            'ridb-campsite-extract-next'
        );

    if (!form) {
        return;
    }

    window.setTimeout(
        function () {
            form.submit();
        },
        15000
    );
}());
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

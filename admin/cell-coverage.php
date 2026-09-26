<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/cell-coverage-import.php';

$adminUser =
    moderation_require_admin();

$db = db();

$adminPageTitle =
    'Cell Coverage';

$adminPageEyebrow =
    'Map Data';

$adminActiveNav =
    'system';

$files = [];
$directoryError = '';

try {
    $files =
        llama_cell_import_files();
} catch (Throwable $e) {
    $directoryError =
        $e->getMessage();
}

$coverageRows = 0;
$coverageLatest = null;

try {
    $coverageRows =
        (int) $db
            ->query(
                'SELECT COUNT(*)
                 FROM cell_coverage_h3'
            )
            ->fetchColumn();

    $coverageLatest =
        $db
            ->query(
                'SELECT MAX(as_of_date)
                 FROM cell_coverage_h3'
            )
            ->fetchColumn();

    if (!$coverageLatest) {
        $coverageLatest = null;
    }
} catch (Throwable $e) {
    $coverageRows = 0;
    $coverageLatest = null;
}

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>FCC Mobile Broadband</p>
            <h2>Cell Coverage Import</h2>
        </div>

        <span>
            <?= number_format($coverageRows) ?>
            stored hex<?= $coverageRows === 1 ? '' : 'es' ?>
        </span>
    </header>

    <?php if ($coverageLatest): ?>
        <p>
            Latest imported FCC data:
            <strong>
                <?= moderation_e(
                    (string) $coverageLatest
                ) ?>
            </strong>
        </p>
    <?php endif; ?>

    <p>
        Place FCC H3 GeoPackage files in
        <code>private/fcc-imports</code>,
        then import them here.
    </p>

    <?php if (!class_exists('SQLite3')): ?>
        <div class="admin-alert is-danger">
            PHP SQLite3 is not enabled on this server.
            Enable the SQLite3 PHP extension before importing GeoPackage files.
        </div>
    <?php endif; ?>

    <?php if ($directoryError !== ''): ?>
        <div class="admin-alert is-danger">
            <?= moderation_e($directoryError) ?>
        </div>
    <?php endif; ?>
</section>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Importer</p>
            <h2>Import a GeoPackage</h2>
        </div>
    </header>

    <?php if (!$files): ?>

        <div class="admin-empty-state">
            <h3>No GeoPackage files found.</h3>

            <p>
                Upload an FCC mobile broadband
                H3 <code>.gpkg</code> file to
                <code>private/fcc-imports</code>.
            </p>
        </div>

    <?php else: ?>

        <form
            id="cell-coverage-import-form"
            method="post"
            action="/cell-coverage-import-run.php"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(
                    moderation_csrf_token()
                ) ?>"
            >

            <div class="admin-form-grid">

                <label>
                    <span>GeoPackage</span>

                    <select
                        name="filename"
                        required
                    >
                        <?php foreach ($files as $file): ?>
                            <option
                                value="<?= moderation_e(
                                    (string) $file['name']
                                ) ?>"
                            >
                                <?= moderation_e(
                                    (string) $file['name']
                                ) ?>
                                (<?= number_format(
                                    ((int) $file['bytes'])
                                    / 1048576,
                                    1
                                ) ?> MB)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>State FIPS</span>

                    <input
                        type="text"
                        name="state_fips"
                        inputmode="numeric"
                        maxlength="2"
                        pattern="\d{2}"
                        placeholder="08"
                        required
                    >

                    <small>
                        Two digits, including a leading zero.
                    </small>
                </label>

                <label>
                    <span>FCC data as-of date</span>

                    <input
                        type="date"
                        name="as_of_date"
                        required
                    >
                </label>

            </div>

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-button is-primary"
                    <?= !class_exists('SQLite3')
                        ? 'disabled'
                        : '' ?>
                >
                    Import Coverage
                </button>
            </div>
        </form>

        <div
            id="cell-coverage-import-progress"
            hidden
            aria-live="polite"
        >
            <p>
                <strong id="cell-import-status">
                    Preparing import...
                </strong>
            </p>

            <progress
                id="cell-import-progress-bar"
                value="0"
                max="100"
                style="width:100%"
            ></progress>

            <p id="cell-import-counts"></p>
        </div>

    <?php endif; ?>
</section>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Supported Data</p>
            <h2>What gets imported</h2>
        </div>
    </header>

    <p>
        The importer keeps AT&amp;T, T-Mobile, and Verizon
        4G LTE coverage at 5/1 Mbps or better and
        5G-NR coverage at 7/1 Mbps or better.
        Both outdoor-only and in-vehicle coverage are retained.
    </p>
</section>

<script
    src="<?= moderation_e(
        $siteUrl
        . '/js/admin-cell-coverage-import.js'
    ) ?>"
></script>

<?php
require __DIR__ . '/_footer.php';

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/cell-coverage-import.php';

$adminUser =
    moderation_require_admin();

$cellDb = cell_db();

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

$coverageCells = 0;
$coverageLatest = null;
$datasets = [];

try {
    $coverageCells =
        (int) $cellDb
            ->query(
                'SELECT COUNT(*)
                 FROM cell_coverage_cells'
            )
            ->fetchColumn();

    $coverageLatest =
        $cellDb
            ->query(
                'SELECT MAX(fcc_as_of_date)
                 FROM cell_coverage_datasets
                 WHERE status = "current"'
            )
            ->fetchColumn();

    if (!$coverageLatest) {
        $coverageLatest = null;
    }

    $datasets =
        $cellDb
            ->query(
                'SELECT
                    state_fips,
                    state_name,
                    provider_key,
                    technology,
                    fcc_as_of_date,
                    status,
                    source_rows,
                    cells_written,
                    completed_at
                 FROM cell_coverage_datasets
                 ORDER BY
                    state_name,
                    provider_key,
                    technology,
                    fcc_as_of_date DESC
                 LIMIT 100'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $e) {
    $coverageCells = 0;
    $coverageLatest = null;
    $datasets = [];
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
            <?= number_format(
                $coverageCells
            ) ?>
            compact cell<?= $coverageCells === 1 ? '' : 's' ?>
        </span>
    </header>

    <?php if ($coverageLatest): ?>
        <p>
            Latest current FCC data:
            <strong>
                <?= moderation_e(
                    (string) $coverageLatest
                ) ?>
            </strong>
        </p>
    <?php endif; ?>

    <p>
        GeoPackages are read from
        <code>private/fcc-imports</code>.
        Imports now write directly to the dedicated compact
        cellular database.
    </p>

    <?php if (!class_exists('SQLite3')): ?>
        <div class="admin-alert is-danger">
            PHP SQLite3 is not enabled on this server.
            Enable the SQLite3 PHP extension before importing GeoPackage files.
        </div>
    <?php endif; ?>

    <?php if ($directoryError !== ''): ?>
        <div class="admin-alert is-danger">
            <?= moderation_e(
                $directoryError
            ) ?>
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
                Upload one FCC Hexagon Coverage
                <code>.gpkg</code> file to
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
            <p>Dataset Ledger</p>
            <h2>Imported Coverage</h2>
        </div>

        <span>
            <?= count($datasets) ?>
            recent dataset<?= count($datasets) === 1 ? '' : 's' ?>
        </span>
    </header>

    <?php if (!$datasets): ?>

        <div class="admin-empty-state">
            <h3>No datasets recorded yet.</h3>
            <p>
                Completed V2 imports will appear here.
            </p>
        </div>

    <?php else: ?>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>State</th>
                        <th>Provider</th>
                        <th>Coverage</th>
                        <th>FCC date</th>
                        <th>Status</th>
                        <th>Source rows</th>
                        <th>Cells</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($datasets as $dataset): ?>
                        <tr>
                            <td>
                                <?= moderation_e(
                                    (string) (
                                        $dataset['state_name']
                                        ?? $dataset['state_fips']
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    match (
                                        (string) (
                                            $dataset['provider_key']
                                            ?? ''
                                        )
                                    ) {
                                        'tmobile' => 'T-Mobile',
                                        'verizon' => 'Verizon',
                                        'att' => 'AT&T',
                                        default =>
                                            (string) (
                                                $dataset['provider_key']
                                                ?? ''
                                            ),
                                    }
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    strtoupper(
                                        (string) (
                                            $dataset['technology']
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    (string) (
                                        $dataset['fcc_as_of_date']
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    ucfirst(
                                        (string) (
                                            $dataset['status']
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (int) (
                                        $dataset['source_rows']
                                        ?? 0
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (int) (
                                        $dataset['cells_written']
                                        ?? 0
                                    )
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
        . '/js/admin-cell-coverage-import.js?v=20260926-2'
    ) ?>"
></script>

<?php
require __DIR__ . '/_footer.php';

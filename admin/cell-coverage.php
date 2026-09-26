<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-sync.php';

$adminUser =
    moderation_require_admin();

$cellDb =
    cell_db();

$adminPageTitle =
    'Cell Coverage';

$adminPageEyebrow =
    'Map Data';

$adminActiveNav =
    'cell-coverage';

$fccConfigured =
    llama_fcc_sync_is_configured();

$sync =
    llama_fcc_sync_public_state();

$coverageCells = 0;
$coverageLatest = null;
$datasets = [];
$dbBytes = 0;

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

    $tableStatus =
        $cellDb
            ->query(
                "SHOW TABLE STATUS
                 LIKE 'cell_coverage_cells'"
            )
            ->fetch(
                PDO::FETCH_ASSOC
            );

    if ($tableStatus) {
        $dbBytes =
            (int) (
                $tableStatus['Data_length']
                ?? 0
            )
            + (int) (
                $tableStatus['Index_length']
                ?? 0
            );
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
                    source_filename,
                    status,
                    source_rows,
                    cells_written,
                    completed_at
                 FROM cell_coverage_datasets
                 ORDER BY
                    state_name,
                    provider_key,
                    technology,
                    fcc_as_of_date DESC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $e) {
    $coverageCells = 0;
    $coverageLatest = null;
    $datasets = [];
    $dbBytes = 0;
}

$states =
    llama_fcc_sync_states();

$matrix = [];

foreach ($states as $fips => $name) {
    $matrix[$fips] = [
        'state_name' => $name,
        'tmobile' => [
            '4g' => null,
            '5g' => null,
        ],
        'verizon' => [
            '4g' => null,
            '5g' => null,
        ],
        'att' => [
            '4g' => null,
            '5g' => null,
        ],
    ];
}

foreach ($datasets as $dataset) {
    $fips =
        (string) (
            $dataset['state_fips']
            ?? ''
        );

    $provider =
        (string) (
            $dataset['provider_key']
            ?? ''
        );

    $technology =
        (string) (
            $dataset['technology']
            ?? ''
        );

    if (
        !isset(
            $matrix[$fips][$provider][$technology]
        )
        && !array_key_exists(
            $technology,
            $matrix[$fips][$provider]
            ?? []
        )
    ) {
        continue;
    }

    /*
     * Rows are ordered newest first. Keep the first row for
     * each state/provider/technology cell in the matrix.
     */
    if (
        $matrix[$fips][$provider][$technology]
        === null
    ) {
        $matrix[$fips][$provider][$technology] =
            $dataset;
    }
}

function admin_cell_status_label(
    ?array $dataset
): string {
    if (!$dataset) {
        return 'Missing';
    }

    return ucfirst(
        (string) (
            $dataset['status']
            ?? 'unknown'
        )
    );
}


function admin_cell_status_class(
    ?array $dataset
): string {
    if (!$dataset) {
        return 'is-missing';
    }

    return match (
        (string) (
            $dataset['status']
            ?? ''
        )
    ) {
        'current' =>
            'is-current',

        'importing' =>
            'is-importing',

        'error' =>
            'is-error',

        'superseded' =>
            'is-outdated',

        default =>
            'is-missing',
    };
}


require __DIR__ . '/_header.php';
?>

<section class="cell-admin-summary">

    <article class="cell-admin-metric">
        <span>FCC vintage</span>
        <strong>
            <?= moderation_e(
                (string) (
                    $coverageLatest
                    ?: 'None'
                )
            ) ?>
        </strong>
    </article>

    <article class="cell-admin-metric">
        <span>Coverage cells</span>
        <strong>
            <?= number_format(
                $coverageCells
            ) ?>
        </strong>
    </article>

    <article class="cell-admin-metric">
        <span>Database size</span>
        <strong>
            <?= number_format(
                $dbBytes
                / 1048576,
                1
            ) ?>
            MB
        </strong>
    </article>

    <article class="cell-admin-metric">
        <span>Sync status</span>
        <strong
            id="cell-sync-summary-status"
        >
            <?= moderation_e(
                ucfirst(
                    (string) (
                        $sync['status']
                        ?? 'idle'
                    )
                )
            ) ?>
        </strong>
    </article>

</section>


<?php if (!$fccConfigured): ?>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Setup Required</p>
                <h2>Connect the FCC Public Data API</h2>
            </div>
        </header>

        <p>
            Add your FCC Broadband Data Collection API username
            and hash token to <code>private/config.php</code>.
            The token is never sent to the browser.
        </p>

        <pre class="cell-admin-code"><code>'fcc_bdc' =&gt; [
    'username' =&gt; 'you@example.com',
    'hash_value' =&gt; 'YOUR_FCC_API_TOKEN',
],</code></pre>
    </section>

<?php else: ?>

    <section class="admin-panel cell-sync-panel">
        <header class="admin-panel-header">
            <div>
                <p>Automatic Updates</p>
                <h2>FCC Coverage Sync</h2>
            </div>

            <span>
                50 states + D.C.
            </span>
        </header>

        <p>
            Llama Scout checks the latest FCC availability
            catalog, downloads one H3 GeoPackage at a time,
            installs it into the compact coverage database,
            and deletes the source file before moving on.
        </p>

        <div class="cell-sync-actions">
            <button
                type="button"
                class="admin-button is-primary"
                id="cell-sync-start"
            >
                Sync Latest FCC Coverage
            </button>

            <button
                type="button"
                class="admin-button"
                id="cell-sync-resume"
                <?= in_array(
                    (string) (
                        $sync['status']
                        ?? ''
                    ),
                    [
                        'running',
                        'error',
                    ],
                    true
                )
                    ? ''
                    : 'hidden' ?>
            >
                Resume Sync
            </button>
        </div>

        <div
            id="cell-sync-progress"
            class="cell-sync-progress"
            <?= ($sync['status'] ?? 'idle')
                === 'idle'
                    ? 'hidden'
                    : '' ?>
            data-endpoint="/cell-coverage-sync.php"
            data-csrf="<?= moderation_e(
                moderation_csrf_token()
            ) ?>"
        >
            <div class="cell-sync-progress-header">
                <strong id="cell-sync-message">
                    <?= moderation_e(
                        (string) (
                            $sync['message']
                            ?? 'Ready.'
                        )
                    ) ?>
                </strong>

                <span id="cell-sync-dataset-count">
                    <?= number_format(
                        (int) (
                            $sync['completed']
                            ?? 0
                        )
                    ) ?>
                    /
                    <?= number_format(
                        (int) (
                            $sync['total']
                            ?? 0
                        )
                    ) ?>
                </span>
            </div>

            <progress
                id="cell-sync-progress-bar"
                max="<?= max(
                    1,
                    (int) (
                        $sync['total']
                        ?? 1
                    )
                ) ?>"
                value="<?= min(
                    (int) (
                        $sync['completed']
                        ?? 0
                    ),
                    max(
                        1,
                        (int) (
                            $sync['total']
                            ?? 1
                        )
                    )
                ) ?>"
            ></progress>

            <p
                id="cell-sync-current"
                class="cell-sync-current"
            ></p>

            <p
                id="cell-sync-row-progress"
                class="cell-sync-row-progress"
            ></p>

            <p
                id="cell-sync-error"
                class="cell-sync-error"
                <?= empty(
                    $sync['error']
                )
                    ? 'hidden'
                    : '' ?>
            >
                <?= moderation_e(
                    (string) (
                        $sync['error']
                        ?? ''
                    )
                ) ?>
            </p>
        </div>
    </section>

<?php endif; ?>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Coverage Matrix</p>
            <h2>State Dataset Status</h2>
        </div>

        <span>
            4G LTE + 5G
        </span>
    </header>

    <div class="cell-coverage-table-wrap">
        <table class="cell-coverage-matrix">
            <thead>
                <tr>
                    <th rowspan="2">State</th>

                    <th colspan="2">
                        T-Mobile
                    </th>

                    <th colspan="2">
                        Verizon
                    </th>

                    <th colspan="2">
                        AT&amp;T
                    </th>
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
                <?php foreach ($matrix as $row): ?>
                    <tr>
                        <th>
                            <?= moderation_e(
                                (string) $row[
                                    'state_name'
                                ]
                            ) ?>
                        </th>

                        <?php foreach (
                            [
                                'tmobile',
                                'verizon',
                                'att',
                            ]
                            as $provider
                        ): ?>

                            <?php foreach (
                                [
                                    '4g',
                                    '5g',
                                ]
                                as $technology
                            ): ?>

                                <?php
                                $dataset =
                                    $row[$provider][
                                        $technology
                                    ];
                                ?>

                                <td>
                                    <span
                                        class="cell-dataset-status <?= moderation_e(
                                            admin_cell_status_class(
                                                $dataset
                                            )
                                        ) ?>"
                                        title="<?= moderation_e(
                                            $dataset
                                                ? (
                                                    (
                                                        $dataset[
                                                            'fcc_as_of_date'
                                                        ]
                                                        ?? ''
                                                    )
                                                    . ' · '
                                                    . (
                                                        $dataset[
                                                            'source_filename'
                                                        ]
                                                        ?? ''
                                                    )
                                                )
                                                : 'Not installed'
                                        ) ?>"
                                    >
                                        <?= moderation_e(
                                            admin_cell_status_label(
                                                $dataset
                                            )
                                        ) ?>
                                    </span>
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
            <p>Update History</p>
            <h2>Recent FCC Datasets</h2>
        </div>
    </header>

    <?php if (!$datasets): ?>

        <div class="admin-empty-state">
            <h3>No datasets recorded yet.</h3>
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
                        <th>Installed</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        array_slice(
                            $datasets,
                            0,
                            100
                        )
                        as $dataset
                    ): ?>
                        <tr>
                            <td>
                                <?= moderation_e(
                                    (string) (
                                        $dataset[
                                            'state_name'
                                        ]
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    match (
                                        (string) (
                                            $dataset[
                                                'provider_key'
                                            ]
                                            ?? ''
                                        )
                                    ) {
                                        'tmobile' =>
                                            'T-Mobile',

                                        'verizon' =>
                                            'Verizon',

                                        'att' =>
                                            'AT&T',

                                        default =>
                                            (string) (
                                                $dataset[
                                                    'provider_key'
                                                ]
                                                ?? ''
                                            ),
                                    }
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    strtoupper(
                                        (string) (
                                            $dataset[
                                                'technology'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    (string) (
                                        $dataset[
                                            'fcc_as_of_date'
                                        ]
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    ucfirst(
                                        (string) (
                                            $dataset[
                                                'status'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= moderation_e(
                                    (string) (
                                        $dataset[
                                            'completed_at'
                                        ]
                                        ?? ''
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


<script
    src="<?= moderation_e(
        $siteUrl
        . '/js/admin-cell-coverage-sync.js?v=20260926-1'
    ) ?>"
></script>

<?php
require __DIR__ . '/_footer.php';

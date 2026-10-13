<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-schema.php';
require_once dirname(__DIR__) . '/app/ridb-normalization.php';
require_once dirname(__DIR__) . '/app/ridb-coverage.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Normalization';

$adminPageEyebrow =
    'RIDB';

$adminActiveNav =
    'ridb';

$error = '';

$report = [
    'site_count' => 0,
    'source_attribute_count' => 0,
    'canonical_count' => 0,
    'mapped_source_attribute_count' => 0,
    'unmapped_source_attribute_count' => 0,
    'mapped_site_occurrences' => 0,
    'unmapped_site_occurrences' => 0,
    'mapped' => [],
    'unmapped' => [],
];

$auditScope =
    trim(
        (string) (
            $_GET['audit_scope']
            ?? 'facility'
        )
    );

$coverageReport = [
    'scope' => 'facility',
    'label' => 'Facilities',
    'record_count' => 0,
    'field_count' => 0,
    'imported_count' => 0,
    'preserved_count' => 0,
    'review_count' => 0,
    'rows' => [],
    'scopes' =>
        llama_ridb_coverage_scopes(),
];

try {
    $ridbDb =
        ridb_db();

    $report =
        llama_ridb_normalization_report(
            $ridbDb
        );

    $coverageReport =
        llama_ridb_coverage_report(
            $ridbDb,
            $auditScope
        );

} catch (Throwable $exception) {
    $error =
        $exception->getMessage();
}

$totalOccurrences =
    (int) $report[
        'mapped_site_occurrences'
    ]
    + (int) $report[
        'unmapped_site_occurrences'
    ];

$coverage =
    $totalOccurrences > 0
        ? (
            (int) $report[
                'mapped_site_occurrences'
            ]
            / $totalOccurrences
        ) * 100
        : 0.0;

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <div
        class="admin-user-notice is-error"
        role="alert"
    >
        <strong>RIDB normalization error</strong>
        <p><?= moderation_e($error) ?></p>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
    <div class="admin-user-form-actions">
        <a
            class="admin-button is-secondary"
            href="/ridb-schema.php"
        >
            Schema Explorer
        </a>

        <a
            class="admin-button is-secondary"
            href="/ridb.php"
        >
            Back to RIDB
        </a>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Complete source inventory</p>
            <h2>RIDB field coverage audit</h2>
        </div>

        <span class="admin-status-pill">
            <?= number_format(
                (int) (
                    $coverageReport['review_count']
                    ?? 0
                )
            ) ?>
            to review
        </span>
    </header>

    <div class="admin-user-action-box">
        <p>
            This compares the field paths actually observed in the local RIDB
            mirror with the fields the current importer explicitly maps or
            intentionally preserves. Any observed field without a registered
            treatment is surfaced here instead of being silently ignored.
        </p>

        <form method="get">
            <label for="ridb-audit-scope">
                Source dataset
            </label>

            <div class="admin-user-form-actions">
                <select
                    id="ridb-audit-scope"
                    name="audit_scope"
                >
                    <?php foreach (
                        (array) (
                            $coverageReport['scopes']
                            ?? []
                        )
                        as $scope => $label
                    ): ?>
                        <option
                            value="<?= moderation_e(
                                (string) $scope
                            ) ?>"
                            <?= (
                                $coverageReport['scope']
                                ?? ''
                            ) === $scope
                                ? 'selected'
                                : '' ?>
                        >
                            <?= moderation_e(
                                (string) $label
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button
                    class="admin-button"
                    type="submit"
                >
                    Audit dataset
                </button>
            </div>
        </form>
    </div>

    <div class="admin-integration-summary">
        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $coverageReport[
                            'record_count'
                        ]
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Records inspected</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $coverageReport[
                            'field_count'
                        ]
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Observed field paths</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $coverageReport[
                            'imported_count'
                        ]
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Explicitly mapped</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $coverageReport[
                            'preserved_count'
                        ]
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Preserved source-backed</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) (
                        $coverageReport[
                            'review_count'
                        ]
                        ?? 0
                    )
                ) ?>
            </strong>
            <span>Needs review</span>
        </div>
    </div>

    <?php if (
        !empty(
            $coverageReport['rows']
        )
    ): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>RIDB field path</th>
                    <th>Coverage</th>
                    <th>Llama Scout treatment</th>
                    <th>Records seen</th>
                    <th>Nonblank</th>
                    <th>Types</th>
                    <th>Examples</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $coverageReport['rows']
                    as $row
                ): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) (
                                        $row['path']
                                        ?? ''
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= moderation_e(
                                llama_ridb_coverage_status_label(
                                    (string) (
                                        $row[
                                            'coverage_status'
                                        ]
                                        ?? 'review'
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) (
                                    $row['target']
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) (
                                    $row[
                                        'records_seen'
                                    ]
                                    ?? 0
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) (
                                    $row[
                                        'records_nonblank'
                                    ]
                                    ?? 0
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ', ',
                                    (array) (
                                        $row['types']
                                        ?? []
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ' | ',
                                    (array) (
                                        $row['examples']
                                        ?? []
                                    )
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
            <p>Campsite normalization foundation</p>
            <h2>Federal standard + real RIDB attribute vocabulary</h2>
        </div>
    </header>

    <div class="admin-integration-summary">
        <div>
            <strong>
                <?= number_format(
                    (int) $report[
                        'site_count'
                    ]
                ) ?>
            </strong>
            <span>Inspected sites</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) $report[
                        'source_attribute_count'
                    ]
                ) ?>
            </strong>
            <span>Unique attribute names</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) $report[
                        'mapped_source_attribute_count'
                    ]
                ) ?>
            </strong>
            <span>Mapped source names</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    (int) $report[
                        'unmapped_source_attribute_count'
                    ]
                ) ?>
            </strong>
            <span>Still unmapped</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    $coverage,
                    1
                ) ?>%
            </strong>
            <span>Observed occurrence coverage</span>
        </div>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Canonical campsite vocabulary</p>
            <h2>Mapped concepts</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Canonical field</th>
                <th>Category</th>
                <th>Treatment</th>
                <th>RIDB aliases actually seen</th>
                <th>Site occurrences</th>
                <th>Examples</th>
                <th>Basis</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach (
                $report['mapped']
                as $row
            ): ?>
                <tr>
                    <td>
                        <strong>
                            <?= moderation_e(
                                (string) $row['label']
                            ) ?>
                        </strong>

                        <small>
                            <?= moderation_e(
                                (string) $row[
                                    'canonical'
                                ]
                            ) ?>
                        </small>
                    </td>

                    <td>
                        <?= moderation_e(
                            (string) $row[
                                'category'
                            ]
                        ) ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            llama_ridb_normalization_treatment_label(
                                (string) $row[
                                    'treatment'
                                ]
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            implode(
                                ' | ',
                                array_values(
                                    array_unique(
                                        (array) $row[
                                            'aliases_seen'
                                        ]
                                    )
                                )
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= number_format(
                            (int) $row[
                                'sites_seen'
                            ]
                        ) ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            implode(
                                ' | ',
                                (array) $row[
                                    'examples'
                                ]
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            (string) $row[
                                'standard_basis'
                            ]
                        ) ?>
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
            <p>Campsite reality check</p>
            <h2>RIDB attributes not normalized yet</h2>
        </div>

        <span class="admin-status-pill">
            <?= number_format(
                (int) $report[
                    'unmapped_source_attribute_count'
                ]
            ) ?>
        </span>
    </header>

    <?php if (
        empty(
            $report['unmapped']
        )
    ): ?>
        <div class="admin-empty-state">
            <p>
                Every observed campsite attribute currently maps to a
                canonical concept.
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>RIDB attribute</th>
                    <th>Sites seen</th>
                    <th>Nonblank</th>
                    <th>Examples</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $report['unmapped']
                    as $row
                ): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) $row[
                                        'attribute_name'
                                    ]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $row[
                                    'sites_seen'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $row[
                                    'sites_nonblank'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ' | ',
                                    (array) $row[
                                        'examples'
                                    ]
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

<?php require __DIR__ . '/_footer.php'; ?>

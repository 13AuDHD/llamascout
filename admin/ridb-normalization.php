<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-schema.php';
require_once dirname(__DIR__) . '/app/ridb-normalization.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Normalization';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

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

try {
    $report =
        llama_ridb_normalization_report(
            ridb_db()
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
    <strong>RIDB normalization error</strong>
    <p><?= moderation_e($error) ?></p>
</section>
<?php endif; ?>

<section class="admin-panel">
    <div class="admin-user-form-actions">
        <a
            class="admin-button is-muted"
            href="/ridb-schema.php"
        >
            Back to Schema Explorer
        </a>

        <a
            class="admin-button is-muted"
            href="/ridb.php"
        >
            Back to RIDB
        </a>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Normalization foundation</p>
            <h2>Federal standard + real RIDB vocabulary</h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>Inspected sites</dt>
            <dd>
                <?= number_format(
                    (int) $report[
                        'site_count'
                    ]
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Unique RIDB attribute names</dt>
            <dd>
                <?= number_format(
                    (int) $report[
                        'source_attribute_count'
                    ]
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Mapped source names</dt>
            <dd>
                <?= number_format(
                    (int) $report[
                        'mapped_source_attribute_count'
                    ]
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Still unmapped</dt>
            <dd>
                <?= number_format(
                    (int) $report[
                        'unmapped_source_attribute_count'
                    ]
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Canonical concepts</dt>
            <dd>
                <?= number_format(
                    (int) $report[
                        'canonical_count'
                    ]
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Observed occurrence coverage</dt>
            <dd>
                <?= number_format(
                    $coverage,
                    1
                ) ?>%
            </dd>
        </div>
    </dl>

    <p>
        This layer does not write anything into Llama Scout Places.
        It collapses agency and legacy RIDB labels into canonical
        concepts so the future importer has one stable vocabulary.
    </p>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Canonical vocabulary</p>
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
            <p>Reality check</p>
            <h2>RIDB attributes not normalized yet</h2>
        </div>

        <span>
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

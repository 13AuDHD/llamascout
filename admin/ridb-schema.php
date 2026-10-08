<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-schema.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Schema Explorer';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

$error = '';

$scope =
    strtolower(
        trim(
            (string) (
                $_GET['scope']
                ?? 'facility'
            )
        )
    );

$dataset = [
    'record_count' => 0,
    'field_count' => 0,
    'fields' => [],
    'scope' => 'facility',
    'label' => 'Facilities',
    'scopes' => [],
];

$attributeCatalog = [
    'site_count' => 0,
    'attribute_count' => 0,
    'attributes' => [],
];

$summary = [];

try {
    $ridbDb =
        ridb_db();

    $dataset =
        llama_ridb_schema_dataset(
            $ridbDb,
            $scope
        );

    $attributeCatalog =
        llama_ridb_schema_attribute_catalog(
            $ridbDb
        );

    $summary =
        llama_ridb_schema_summary(
            $ridbDb
        );
} catch (Throwable $exception) {
    $error =
        $exception->getMessage();
}

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <strong>RIDB schema explorer error</strong>
    <p><?= moderation_e($error) ?></p>
</section>
<?php endif; ?>


<section class="admin-panel">
    <div class="admin-user-form-actions">
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
            <p>Cached source inventory</p>
            <h2>What we have actually seen</h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>Facilities</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'facilities'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Campsites</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'campsites'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Addresses</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'addresses'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Media records</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'media'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Links</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'links'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Activities</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'activities'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Campsite attribute rows</dt>
            <dd>
                <?= number_format(
                    (int) (
                        $summary[
                            'attributes'
                        ]
                        ?? 0
                    )
                ) ?>
            </dd>
        </div>
    </dl>

    <p>
        This page reads the raw RIDB JSON already cached from
        facilities and campsites you have inspected. It does not
        guess a Llama Scout field mapping and does not write to
        the main Places database.
    </p>
</section>


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Schema union</p>
            <h2><?= moderation_e(
                (string) $dataset['label']
            ) ?></h2>
        </div>

        <span>
            <?= number_format(
                (int) $dataset[
                    'record_count'
                ]
            ) ?>
            records /
            <?= number_format(
                (int) $dataset[
                    'field_count'
                ]
            ) ?>
            field paths
        </span>
    </header>

    <form method="get">
        <label>
            <span>Dataset</span>

            <select
                name="scope"
                onchange="this.form.submit()"
            >
                <?php foreach (
                    $dataset['scopes']
                    as $scopeKey => $scopeLabel
                ): ?>
                    <option
                        value="<?= moderation_e(
                            (string) $scopeKey
                        ) ?>"
                        <?= $scopeKey
                            === $dataset['scope']
                                ? 'selected'
                                : '' ?>
                    >
                        <?= moderation_e(
                            (string) $scopeLabel
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <noscript>
            <button
                class="admin-button"
                type="submit"
            >
                Show dataset
            </button>
        </noscript>
    </form>

    <?php if (
        empty(
            $dataset['fields']
        )
    ): ?>
        <div class="admin-empty-state">
            <p>
                No cached JSON exists for this dataset yet.
                Inspect more RIDB facilities and campsites first.
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>RIDB field path</th>
                        <th>Type</th>
                        <th>Seen</th>
                        <th>Nonblank</th>
                        <th>Blank</th>
                        <th>Max items</th>
                        <th>Examples</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $dataset['fields']
                    as $field
                ): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) $field[
                                        'path'
                                    ]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ', ',
                                    (array) $field[
                                        'types'
                                    ]
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $field[
                                    'records_seen'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $field[
                                    'records_nonblank'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $field[
                                    'records_blank'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= $field[
                                'max_array_items'
                            ] === null
                                ? ''
                                : number_format(
                                    (int) $field[
                                        'max_array_items'
                                    ]
                                ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ' | ',
                                    (array) $field[
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


<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Campsite attribute vocabulary</p>
            <h2>Attribute names seen across inspected sites</h2>
        </div>

        <span>
            <?= number_format(
                (int) $attributeCatalog[
                    'attribute_count'
                ]
            ) ?>
            unique attributes across
            <?= number_format(
                (int) $attributeCatalog[
                    'site_count'
                ]
            ) ?>
            inspected sites
        </span>
    </header>

    <?php if (
        empty(
            $attributeCatalog[
                'attributes'
            ]
        )
    ): ?>
        <div class="admin-empty-state">
            <p>
                No campsite attributes have been cached yet.
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Attribute</th>
                        <th>Sites seen</th>
                        <th>Nonblank</th>
                        <th>Examples</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $attributeCatalog[
                        'attributes'
                    ]
                    as $attribute
                ): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) $attribute[
                                        'attribute_name'
                                    ]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $attribute[
                                    'sites_seen'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $attribute[
                                    'sites_nonblank'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                implode(
                                    ' | ',
                                    (array) $attribute[
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

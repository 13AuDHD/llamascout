<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-schema.php';

$adminUser =
    moderation_require_admin();

$facilityId =
    trim(
        (string) (
            $_GET['id']
            ?? ''
        )
    );

if ($facilityId === '') {
    http_response_code(400);
    exit('RIDB facility ID is required.');
}

$selectedCampsiteId =
    trim(
        (string) (
            $_GET['campsite']
            ?? ''
        )
    );

$error = '';
$notice = '';
$facility = [];
$addresses = [];
$media = [];
$links = [];
$activities = [];
$campsites = [];
$campsitesTruncated = false;
$selectedCampsite = [];
$selectedAttributes = [];

try {
    $ridbDb =
        ridb_db();

    $facilityResponse =
        llama_ridb_facility(
            $facilityId
        );

    $facility =
        llama_ridb_response_record(
            $facilityResponse
        );

    if (!$facility) {
        throw new RuntimeException(
            'RIDB did not return this facility.'
        );
    }

    llama_ridb_store_facilities(
        $ridbDb,
        [$facility]
    );

    $addressResponse =
        llama_ridb_facility_addresses(
            $facilityId
        );

    $addresses =
        $addressResponse['records'];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_facility_addresses',
        $facilityId,
        $addresses,
        'FacilityAddressID'
    );

    $mediaResponse =
        llama_ridb_facility_media(
            $facilityId
        );

    $media =
        $mediaResponse['records'];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_media',
        $facilityId,
        $media,
        'MediaID'
    );

    $linkResponse =
        llama_ridb_facility_links(
            $facilityId
        );

    $links =
        $linkResponse['records'];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_links',
        $facilityId,
        $links,
        'LinkID'
    );

    $activityResponse =
        llama_ridb_facility_activities(
            $facilityId
        );

    $activities =
        $activityResponse['records'];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_activities',
        $facilityId,
        $activities,
        'ActivityID'
    );

    $campsiteResponse =
        llama_ridb_facility_campsites(
            $facilityId,
            500
        );

    $campsites =
        $campsiteResponse['records'];

    $campsitesTruncated =
        !empty(
            $campsiteResponse['truncated']
        );

    llama_ridb_store_campsites(
        $ridbDb,
        $facilityId,
        $campsites
    );

    if ($selectedCampsiteId !== '') {
        $selectedResponse =
            llama_ridb_campsite(
                $selectedCampsiteId
            );

        $selectedCampsite =
            llama_ridb_response_record(
                $selectedResponse
            );

        if ($selectedCampsite) {
            llama_ridb_store_campsites(
                $ridbDb,
                $facilityId,
                [$selectedCampsite]
            );
        }

        $attributeResponse =
            llama_ridb_campsite_attributes(
                $selectedCampsiteId
            );

        $selectedAttributes =
            $attributeResponse['records'];

        llama_ridb_replace_campsite_attributes(
            $ridbDb,
            $selectedCampsiteId,
            $selectedAttributes
        );
    }

    $seen =
        1
        + count($addresses)
        + count($media)
        + count($links)
        + count($activities)
        + count($campsites)
        + count($selectedAttributes);

    llama_ridb_log_sync_run(
        $ridbDb,
        'facility_inspection',
        'success',
        $seen,
        $seen,
        'Facility '
        . $facilityId
    );
} catch (Throwable $exception) {
    $error =
        $exception->getMessage();
}

$facilityName =
    trim(
        (string) llama_ridb_record_value(
            $facility,
            [
                'FacilityName',
                'facilityName',
            ],
            'RIDB Facility'
        )
    );

$adminPageTitle =
    $facilityName;

$adminPageEyebrow =
    'RIDB Facility';

$adminActiveNav =
    'reference-data';

$mapping =
    llama_ridb_place_mapping_preview(
        $facility,
        $addresses,
        $media,
        $links,
        $activities,
        $campsites
    );

$description =
    trim(
        strip_tags(
            (string) llama_ridb_record_value(
                $facility,
                [
                    'FacilityDescription',
                    'facilityDescription',
                ],
                ''
            )
        )
    );

$facilityType =
    trim(
        (string) llama_ridb_record_value(
            $facility,
            [
                'FacilityTypeDescription',
                'facilityTypeDescription',
            ],
            ''
        )
    );

$latitude =
    llama_ridb_record_value(
        $facility,
        [
            'FacilityLatitude',
            'facilityLatitude',
        ]
    );

$longitude =
    llama_ridb_record_value(
        $facility,
        [
            'FacilityLongitude',
            'facilityLongitude',
        ]
    );

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
    <section class="admin-panel">
        <strong>RIDB error</strong>
        <p><?= moderation_e($error) ?></p>
    </section>
<?php endif; ?>

<section class="admin-panel">
    <div class="admin-user-form-actions">
        <a
            class="admin-button is-muted"
            href="/ridb.php"
        >
            Back to RIDB search
        </a>

        <a
            class="admin-button is-muted"
            href="/ridb-schema.php"
        >
            Schema explorer
        </a>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Source facility</p>
            <h2><?= moderation_e($facilityName) ?></h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>RIDB ID</dt>
            <dd><?= moderation_e($facilityId) ?></dd>
        </div>

        <div>
            <dt>Facility type</dt>
            <dd>
                <?= moderation_e(
                    $facilityType !== ''
                        ? $facilityType
                        : 'Not provided'
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Reservable</dt>
            <dd>
                <?= !empty(
                    llama_ridb_record_value(
                        $facility,
                        [
                            'Reservable',
                            'reservable',
                        ],
                        false
                    )
                )
                    ? 'Yes'
                    : 'No / unknown' ?>
            </dd>
        </div>

        <div>
            <dt>Coordinates</dt>
            <dd>
                <?= is_numeric($latitude)
                    && is_numeric($longitude)
                        ? moderation_e(
                            number_format(
                                (float) $latitude,
                                7
                            )
                            . ', '
                            . number_format(
                                (float) $longitude,
                                7
                            )
                        )
                        : 'Not provided' ?>
            </dd>
        </div>
    </dl>

    <?php if ($description !== ''): ?>
        <p><?= nl2br(moderation_e($description)) ?></p>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>What RIDB gives us</p>
            <h2>Source inventory</h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>Addresses</dt>
            <dd><?= number_format(count($addresses)) ?></dd>
        </div>

        <div>
            <dt>Media</dt>
            <dd><?= number_format(count($media)) ?></dd>
        </div>

        <div>
            <dt>Links</dt>
            <dd><?= number_format(count($links)) ?></dd>
        </div>

        <div>
            <dt>Activities</dt>
            <dd><?= number_format(count($activities)) ?></dd>
        </div>

        <div>
            <dt>Campsites</dt>
            <dd>
                <?= number_format(count($campsites)) ?>
                <?= $campsitesTruncated
                    ? ' (first 500)'
                    : '' ?>
            </dd>
        </div>
    </dl>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Translation preview</p>
            <h2>Preview as a Llama Scout Place</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Llama Scout field</th>
                    <th>RIDB status</th>
                    <th>Preview</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach (
                $mapping['rows']
                as $row
            ): ?>
                <tr>
                    <td>
                        <strong>
                            <?= moderation_e(
                                (string) $row['field']
                            ) ?>
                        </strong>
                    </td>

                    <td>
                        <?= $row['status']
                            === 'import'
                                ? 'Can import'
                                : 'Needs review' ?>
                    </td>

                    <td>
                        <?php
                        $value =
                            trim(
                                (string) (
                                    $row['value']
                                    ?? ''
                                )
                            );

                        if (
                            mb_strlen($value)
                            > 260
                        ) {
                            $value =
                                mb_substr(
                                    $value,
                                    0,
                                    257
                                )
                                . '...';
                        }
                        ?>

                        <?= moderation_e(
                            $value !== ''
                                ? $value
                                : 'Not provided'
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
            <p>Human layer</p>
            <h2>Still needs Llama Scout</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Scout contribution</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach (
                $mapping['needs_scout']
                as $need
            ): ?>
                <tr>
                    <td><?= moderation_e($need) ?></td>
                    <td>Update me</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($addresses): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB data</p>
            <h2>Addresses</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
            <?php foreach ($addresses as $address): ?>
                <tr>
                    <td>
                        <?= moderation_e(
                            trim(
                                implode(
                                    ', ',
                                    array_filter(
                                        [
                                            (string) llama_ridb_record_value(
                                                $address,
                                                ['FacilityStreetAddress1'],
                                                ''
                                            ),
                                            (string) llama_ridb_record_value(
                                                $address,
                                                ['City'],
                                                ''
                                            ),
                                            (string) llama_ridb_record_value(
                                                $address,
                                                ['AddressStateCode'],
                                                ''
                                            ),
                                            (string) llama_ridb_record_value(
                                                $address,
                                                ['PostalCode'],
                                                ''
                                            ),
                                        ]
                                    )
                                )
                            )
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($activities): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB data</p>
            <h2>Activities</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <td>
                        <?= moderation_e(
                            (string) llama_ridb_record_value(
                                $activity,
                                [
                                    'ActivityName',
                                    'activityName',
                                ],
                                'Unnamed activity'
                            )
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($links): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB data</p>
            <h2>Official links</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>URL</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($links as $link): ?>
                <?php
                $url =
                    trim(
                        (string) llama_ridb_record_value(
                            $link,
                            [
                                'URL',
                                'LinkURL',
                                'Url',
                            ],
                            ''
                        )
                    );
                ?>
                <tr>
                    <td>
                        <?= moderation_e(
                            (string) llama_ridb_record_value(
                                $link,
                                [
                                    'Title',
                                    'LinkTitle',
                                    'LinkType',
                                ],
                                'Link'
                            )
                        ) ?>
                    </td>

                    <td>
                        <?php if (
                            filter_var(
                                $url,
                                FILTER_VALIDATE_URL
                            )
                        ): ?>
                            <a
                                href="<?= moderation_e($url) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Open
                            </a>
                        <?php else: ?>
                            Not provided
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($media): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB data</p>
            <h2>Media</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Source</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($media as $item): ?>
                <?php
                $url =
                    trim(
                        (string) llama_ridb_record_value(
                            $item,
                            [
                                'URL',
                                'MediaURL',
                                'EmbedCode',
                            ],
                            ''
                        )
                    );
                ?>
                <tr>
                    <td>
                        <?= moderation_e(
                            (string) llama_ridb_record_value(
                                $item,
                                [
                                    'Title',
                                    'MediaTitle',
                                ],
                                'Media item'
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            (string) llama_ridb_record_value(
                                $item,
                                [
                                    'MediaType',
                                    'Type',
                                ],
                                ''
                            )
                        ) ?>
                    </td>

                    <td>
                        <?php if (
                            filter_var(
                                $url,
                                FILTER_VALIDATE_URL
                            )
                        ): ?>
                            <a
                                href="<?= moderation_e($url) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Open
                            </a>
                        <?php else: ?>
                            Stored in raw RIDB record
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB data</p>
            <h2>Individual campsites</h2>
        </div>
    </header>

    <?php if (!$campsites): ?>
        <div class="admin-empty-state">
            <p>
                RIDB did not return individual campsites for this facility.
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Type</th>
                        <th>Accessible</th>
                        <th>RIDB ID</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($campsites as $site): ?>
                    <?php
                    $siteId =
                        trim(
                            (string) llama_ridb_record_value(
                                $site,
                                [
                                    'CampsiteID',
                                    'campsiteID',
                                ],
                                ''
                            )
                        );
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) llama_ridb_record_value(
                                        $site,
                                        [
                                            'CampsiteName',
                                            'campsiteName',
                                        ],
                                        'Unnamed site'
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) llama_ridb_record_value(
                                    $site,
                                    [
                                        'CampsiteType',
                                        'CampsiteTypeDescription',
                                    ],
                                    ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <?php
                            $accessible =
                                llama_ridb_record_value(
                                    $site,
                                    [
                                        'CampsiteAccessible',
                                    ]
                                );
                            ?>

                            <?= $accessible === null
                                ? 'Unknown'
                                : (
                                    !empty($accessible)
                                        ? 'Yes'
                                        : 'No'
                                ) ?>
                        </td>

                        <td>
                            <?= moderation_e($siteId) ?>
                        </td>

                        <td>
                            <?php if ($siteId !== ''): ?>
                                <a
                                    class="admin-button"
                                    href="/ridb-facility.php?id=<?= rawurlencode(
                                        $facilityId
                                    ) ?>&campsite=<?= rawurlencode(
                                        $siteId
                                    ) ?>#ridb-campsite-detail"
                                >
                                    Inspect
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($selectedCampsiteId !== ''): ?>
<section
    class="admin-panel"
    id="ridb-campsite-detail"
>
    <header class="admin-panel-header">
        <div>
            <p>Campsite inspection</p>
            <h2>
                <?= moderation_e(
                    (string) llama_ridb_record_value(
                        $selectedCampsite,
                        [
                            'CampsiteName',
                            'campsiteName',
                        ],
                        'Campsite ' . $selectedCampsiteId
                    )
                ) ?>
            </h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>RIDB campsite ID</dt>
            <dd><?= moderation_e($selectedCampsiteId) ?></dd>
        </div>

        <div>
            <dt>Attributes returned</dt>
            <dd><?= number_format(count($selectedAttributes)) ?></dd>
        </div>
    </dl>

    <?php if ($selectedAttributes): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Attribute</th>
                        <th>Value</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $selectedAttributes
                    as $attribute
                ): ?>
                    <tr>
                        <td>
                            <?= moderation_e(
                                (string) llama_ridb_record_value(
                                    $attribute,
                                    [
                                        'AttributeName',
                                        'AttributeKey',
                                    ],
                                    'Attribute'
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) llama_ridb_record_value(
                                    $attribute,
                                    [
                                        'AttributeValue',
                                        'AttributeText',
                                    ],
                                    ''
                                )
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="admin-empty-state">
            <p>
                RIDB returned no campsite attributes for this site.
            </p>
        </div>
    <?php endif; ?>

    <details>
        <summary>Show raw campsite JSON</summary>

        <pre><?= moderation_e(
            json_encode(
                $selectedCampsite,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            ?: ''
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Development inspection</p>
            <h2>Raw facility JSON</h2>
        </div>
    </header>

    <details>
        <summary>Show raw facility JSON</summary>

        <pre><?= moderation_e(
            json_encode(
                $facility,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            ?: ''
        ) ?></pre>
    </details>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-schema.php';
require_once dirname(__DIR__) . '/app/ridb-facility-inspector.php';

$adminUser =
    moderation_require_admin();

$facilityId =
    trim(
        (string) (
            $_GET['id']
            ?? $_POST['id']
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
            ?? $_POST['campsite']
            ?? ''
        )
    );

$error = '';
$notice = '';

try {
    $ridbDb =
        ridb_db();

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
            throw new RuntimeException(
                'Your session token expired. Reload and try again.'
            );
        }

        $action =
            trim(
                (string) (
                    $_POST['action']
                    ?? ''
                )
            );

        if (
            $action
            === 'refresh_facility'
        ) {
            $result =
                llama_ridb_inspector_refresh_facility(
                    $ridbDb,
                    $facilityId
                );

            $notice =
                'Facility refreshed from RIDB. '
                . number_format(
                    (int) (
                        $result['seen']
                        ?? 0
                    )
                )
                . ' source records processed.';

            if (
                !empty(
                    $result['truncated']
                )
            ) {
                $notice .=
                    ' Campsite refresh reached the 500-site safety limit.';
            }

        } elseif (
            $action
            === 'refresh_campsite'
            && $selectedCampsiteId !== ''
        ) {
            $result =
                llama_ridb_inspector_refresh_campsite(
                    $ridbDb,
                    $facilityId,
                    $selectedCampsiteId
                );

            $notice =
                'Campsite refreshed from RIDB with '
                . number_format(
                    (int) (
                        $result['attributes']
                        ?? 0
                    )
                )
                . ' attribute rows.';
        }
    }

    $bundle =
        llama_ridb_inspector_load_facility(
            $ridbDb,
            $facilityId
        );

} catch (Throwable $exception) {
    $bundle = [
        'facility' => [],
        'addresses' => [],
        'media' => [],
        'links' => [],
        'activities' => [],
        'campsites' => [],
    ];

    $error =
        $exception->getMessage();
}

$facility =
    (array) (
        $bundle['facility']
        ?? []
    );

$addresses =
    (array) (
        $bundle['addresses']
        ?? []
    );

$media =
    (array) (
        $bundle['media']
        ?? []
    );

$links =
    (array) (
        $bundle['links']
        ?? []
    );

$activities =
    (array) (
        $bundle['activities']
        ?? []
    );

$campsites =
    (array) (
        $bundle['campsites']
        ?? []
    );

$campsiteDetail = [];

if (
    $error === ''
    && $selectedCampsiteId !== ''
) {
    try {
        $campsiteDetail =
            llama_ridb_inspector_load_campsite(
                $ridbDb,
                $facilityId,
                $selectedCampsiteId
            );
    } catch (Throwable $exception) {
        $error =
            $exception->getMessage();
    }
}

$selectedCampsite =
    (array) (
        $campsiteDetail['selected']
        ?? []
    );

$rawCampsite =
    (array) (
        $campsiteDetail['raw_record']
        ?? []
    );

$embeddedAttributes =
    (array) (
        $campsiteDetail[
            'embedded_attributes'
        ]
        ?? []
    );

$embeddedEquipment =
    (array) (
        $campsiteDetail[
            'embedded_equipment'
        ]
        ?? []
    );

$embeddedMedia =
    (array) (
        $campsiteDetail[
            'embedded_media'
        ]
        ?? []
    );

$mirroredAttributes =
    (array) (
        $campsiteDetail[
            'mirrored_attributes'
        ]
        ?? []
    );

$mirroredEquipment =
    (array) (
        $campsiteDetail[
            'mirrored_equipment'
        ]
        ?? []
    );

$mirroredMedia =
    (array) (
        $campsiteDetail[
            'mirrored_media'
        ]
        ?? []
    );

$normalizedAttributes =
    (array) (
        $campsiteDetail[
            'normalized_attributes'
        ]
        ?? []
    );

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
    $facilityName !== ''
        ? $facilityName
        : 'RIDB Facility';

$adminPageEyebrow =
    'RIDB Facility';

$adminActiveNav =
    'ridb';

$mapping =
    llama_ridb_place_mapping_preview(
        $facility,
        $addresses,
        $media,
        $links,
        $activities,
        array_map(
            static function (
                array $row
            ): array {
                $decoded =
                    llama_ridb_inspector_decode_json(
                        $row['source_json']
                        ?? ''
                    );

                return $decoded
                    ?: $row;
            },
            $campsites
        )
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
        <strong>RIDB error</strong>
        <p><?= moderation_e($error) ?></p>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Local RIDB cache</p>
            <h2>Facility inspection</h2>
        </div>

        <span class="admin-status-pill">
            Local-first
        </span>
    </header>

    <div class="admin-user-action-box">
        <p>
            Opening this page reads the local RIDB database only. Use the
            refresh button when you explicitly want to contact Recreation.gov
            for the latest facility data.
        </p>

        <div class="admin-user-form-actions">
            <a
                class="admin-button is-secondary"
                href="/ridb.php"
            >
                Back to RIDB
            </a>

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
                    name="id"
                    value="<?= moderation_e(
                        $facilityId
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="campsite"
                    value="<?= moderation_e(
                        $selectedCampsiteId
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="refresh_facility"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    Refresh facility from RIDB
                </button>
            </form>

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
            <p>Source facility</p>
            <h2><?= moderation_e($facilityName) ?></h2>
        </div>
    </header>

    <div class="admin-integration-summary">
        <div>
            <strong>
                <?= moderation_e($facilityId) ?>
            </strong>
            <span>RIDB facility ID</span>
        </div>

        <div>
            <strong>
                <?= moderation_e(
                    $facilityType !== ''
                        ? $facilityType
                        : 'Unknown'
                ) ?>
            </strong>
            <span>Facility type</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count($campsites)
                ) ?>
            </strong>
            <span>Cached campsites</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count($media)
                ) ?>
            </strong>
            <span>Facility media</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count($activities)
                ) ?>
            </strong>
            <span>Activities</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count($links)
                ) ?>
            </strong>
            <span>Official links</span>
        </div>
    </div>

    <dl class="admin-user-definition-list">
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
        <p>
            <?= nl2br(
                moderation_e(
                    $description
                )
            ) ?>
        </p>
    <?php endif; ?>
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
                ?? []
                as $row
            ): ?>
                <tr>
                    <td>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $row['field']
                                    ?? ''
                                )
                            ) ?>
                        </strong>
                    </td>

                    <td>
                        <?= (
                            $row['status']
                            ?? ''
                        ) === 'import'
                            ? 'Can import'
                            : 'Needs review' ?>
                    </td>

                    <td>
                        <?php
                        $preview =
                            trim(
                                (string) (
                                    $row['value']
                                    ?? ''
                                )
                            );

                        if (
                            mb_strlen($preview)
                            > 260
                        ) {
                            $preview =
                                mb_substr(
                                    $preview,
                                    0,
                                    257
                                )
                                . '...';
                        }
                        ?>

                        <?= moderation_e(
                            $preview !== ''
                                ? $preview
                                : 'Not provided'
                        ) ?>
                    </td>
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
            <p>Facility data</p>
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
                                    array_filter([
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
                                    ])
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
            <p>Facility data</p>
            <h2>Activities</h2>
        </div>
    </header>

    <div class="ridb-facility-pill-list">
        <?php foreach ($activities as $activity): ?>
            <span>
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
            </span>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($links): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Facility data</p>
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
                                href="<?= moderation_e(
                                    $url
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Open source link
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
            <p>Facility data</p>
            <h2>Facility media</h2>
        </div>
    </header>

    <div class="ridb-facility-media-grid">
        <?php foreach ($media as $item): ?>
            <?php
            $url =
                trim(
                    (string) llama_ridb_record_value(
                        $item,
                        [
                            'URL',
                            'MediaURL',
                            'Url',
                        ],
                        ''
                    )
                );

            $title =
                trim(
                    (string) llama_ridb_record_value(
                        $item,
                        [
                            'Title',
                            'MediaTitle',
                        ],
                        'Media item'
                    )
                );

            $credit =
                trim(
                    (string) llama_ridb_record_value(
                        $item,
                        [
                            'Credits',
                            'Credit',
                        ],
                        ''
                    )
                );
            ?>

            <article class="ridb-facility-media-card">
                <?php if (
                    preg_match(
                        '#^https?://#i',
                        $url
                    ) === 1
                ): ?>
                    <img
                        src="<?= moderation_e($url) ?>"
                        alt=""
                        loading="lazy"
                    >
                <?php endif; ?>

                <div>
                    <strong>
                        <?= moderation_e($title) ?>
                    </strong>

                    <?php if ($credit !== ''): ?>
                        <small>
                            Credit:
                            <?= moderation_e($credit) ?>
                        </small>
                    <?php endif; ?>

                    <?php if ($url !== ''): ?>
                        <a
                            href="<?= moderation_e($url) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Open source media
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Cached RIDB campsites</p>
            <h2>Individual campsites</h2>
        </div>

        <span>
            <?= number_format(
                count($campsites)
            ) ?>
        </span>
    </header>

    <?php if (!$campsites): ?>
        <div class="admin-empty-state">
            <p>
                No individual campsites are cached for this facility.
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
                            (string) (
                                $site[
                                    'ridb_campsite_id'
                                ]
                                ?? ''
                            )
                        );
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= moderation_e(
                                    (string) (
                                        $site[
                                            'campsite_name'
                                        ]
                                        ?: $siteId
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) (
                                    $site[
                                        'campsite_type'
                                    ]
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= (
                                $site[
                                    'campsite_accessible'
                                ]
                                ?? null
                            ) === null
                                ? 'Unknown'
                                : (
                                    !empty(
                                        $site[
                                            'campsite_accessible'
                                        ]
                                    )
                                        ? 'Yes'
                                        : 'No'
                                ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                $siteId
                            ) ?>
                        </td>

                        <td>
                            <?php if ($siteId !== ''): ?>
                                <a
                                    class="admin-button is-secondary"
                                    href="/ridb-facility.php?id=<?= rawurlencode(
                                        $facilityId
                                    ) ?>&amp;campsite=<?= rawurlencode(
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
            <p>Local campsite inspection</p>
            <h2>
                <?= moderation_e(
                    (string) (
                        $selectedCampsite[
                            'campsite_name'
                        ]
                        ?? (
                            $rawCampsite[
                                'CampsiteName'
                            ]
                            ?? $selectedCampsiteId
                        )
                    )
                ) ?>
            </h2>
        </div>

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
                name="id"
                value="<?= moderation_e(
                    $facilityId
                ) ?>"
            >
            <input
                type="hidden"
                name="campsite"
                value="<?= moderation_e(
                    $selectedCampsiteId
                ) ?>"
            >
            <input
                type="hidden"
                name="action"
                value="refresh_campsite"
            >

            <button
                class="admin-button"
                type="submit"
            >
                Refresh this campsite
            </button>
        </form>
    </header>

    <div class="admin-integration-summary">
        <div>
            <strong>
                <?= moderation_e(
                    $selectedCampsiteId
                ) ?>
            </strong>
            <span>RIDB campsite ID</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count(
                        $embeddedAttributes
                    )
                ) ?>
            </strong>
            <span>Embedded attributes</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count(
                        $embeddedEquipment
                    )
                ) ?>
            </strong>
            <span>Permitted equipment</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count($embeddedMedia)
                ) ?>
            </strong>
            <span>Embedded site photos</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count(
                        $mirroredAttributes
                    )
                ) ?>
            </strong>
            <span>Extracted attribute rows</span>
        </div>

        <div>
            <strong>
                <?= number_format(
                    count(
                        $mirroredEquipment
                    )
                ) ?>
            </strong>
            <span>Extracted equipment rows</span>
        </div>
    </div>
</section>

<?php if ($embeddedMedia || $mirroredMedia): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Campsite-specific media</p>
            <h2>Site photos</h2>
        </div>
    </header>

    <?php if ($embeddedMedia): ?>
        <div class="ridb-facility-media-grid">
            <?php foreach ($embeddedMedia as $item): ?>
                <?php
                $url =
                    trim(
                        (string) (
                            $item['URL']
                            ?? $item['Url']
                            ?? $item['url']
                            ?? ''
                        )
                    );

                $title =
                    trim(
                        (string) (
                            $item['Title']
                            ?? $item['title']
                            ?? 'Untitled media'
                        )
                    );

                $credit =
                    trim(
                        (string) (
                            $item['Credits']
                            ?? $item['credits']
                            ?? ''
                        )
                    );
                ?>

                <article class="ridb-facility-media-card">
                    <?php if (
                        preg_match(
                            '#^https?://#i',
                            $url
                        ) === 1
                    ): ?>
                        <img
                            src="<?= moderation_e(
                                $url
                            ) ?>"
                            alt=""
                            loading="lazy"
                        >
                    <?php endif; ?>

                    <div>
                        <strong>
                            <?= moderation_e(
                                $title
                            ) ?>
                        </strong>

                        <?php if (
                            $credit !== ''
                        ): ?>
                            <small>
                                Credit:
                                <?= moderation_e(
                                    $credit
                                ) ?>
                            </small>
                        <?php endif; ?>

                        <?php if ($url !== ''): ?>
                            <a
                                href="<?= moderation_e(
                                    $url
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open source media
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <details class="ridb-facility-json">
        <summary>
            Mirrored campsite media rows
            (<?= number_format(
                count($mirroredMedia)
            ) ?>)
        </summary>

        <pre><?= moderation_e(
            llama_ridb_inspector_pretty_json(
                $mirroredMedia
            )
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Campsite-specific data</p>
            <h2>Attributes & equipment</h2>
        </div>
    </header>

    <?php if ($normalizedAttributes): ?>
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
                    $normalizedAttributes
                    as $attribute
                ): ?>
                    <tr>
                        <td>
                            <?= moderation_e(
                                (string) (
                                    $attribute[
                                        'attribute_name'
                                    ]
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) (
                                    $attribute[
                                        'attribute_value'
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

    <div class="ridb-facility-json-columns">
        <details
            class="ridb-facility-json"
            open
        >
            <summary>
                Embedded ATTRIBUTES
                (<?= number_format(
                    count(
                        $embeddedAttributes
                    )
                ) ?>)
            </summary>

            <pre><?= moderation_e(
                llama_ridb_inspector_pretty_json(
                    $embeddedAttributes
                )
            ) ?></pre>
        </details>

        <details
            class="ridb-facility-json"
            open
        >
            <summary>
                Embedded PERMITTEDEQUIPMENT
                (<?= number_format(
                    count(
                        $embeddedEquipment
                    )
                ) ?>)
            </summary>

            <pre><?= moderation_e(
                llama_ridb_inspector_pretty_json(
                    $embeddedEquipment
                )
            ) ?></pre>
        </details>
    </div>

    <div class="ridb-facility-json-columns">
        <details class="ridb-facility-json">
            <summary>
                Extracted attribute mirror rows
                (<?= number_format(
                    count(
                        $mirroredAttributes
                    )
                ) ?>)
            </summary>

            <pre><?= moderation_e(
                llama_ridb_inspector_pretty_json(
                    $mirroredAttributes
                )
            ) ?></pre>
        </details>

        <details class="ridb-facility-json">
            <summary>
                Extracted equipment mirror rows
                (<?= number_format(
                    count(
                        $mirroredEquipment
                    )
                ) ?>)
            </summary>

            <pre><?= moderation_e(
                llama_ridb_inspector_pretty_json(
                    $mirroredEquipment
                )
            ) ?></pre>
        </details>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Lossless source record</p>
            <h2>Full campsite JSON</h2>
        </div>
    </header>

    <details
        class="ridb-facility-json"
        open
    >
        <summary>
            Raw RIDB campsite record
        </summary>

        <pre><?= moderation_e(
            llama_ridb_inspector_pretty_json(
                $rawCampsite
            )
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Lossless source record</p>
            <h2>Full facility JSON</h2>
        </div>
    </header>

    <details class="ridb-facility-json">
        <summary>
            Raw RIDB facility record
        </summary>

        <pre><?= moderation_e(
            llama_ridb_inspector_pretty_json(
                $facility
            )
        ) ?></pre>
    </details>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

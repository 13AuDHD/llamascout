<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-mirror.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Campsite Inspector';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

$ridbDb =
    ridb_db();

$query =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

$selectedId =
    trim(
        (string) (
            $_GET['campsite']
            ?? ''
        )
    );

$results = [];
$selected = null;
$rawRecord = null;
$embeddedAttributes = [];
$embeddedEquipment = [];
$embeddedMedia = [];
$mirroredAttributes = [];
$mirroredEquipment = [];
$mirroredMedia = [];
$error = '';

function ridb_inspector_embedded_list(
    array $record,
    array $keys
): array {
    foreach ($keys as $key) {
        $value =
            $record[$key]
            ?? null;

        if (!is_array($value)) {
            continue;
        }

        if (!$value) {
            return [];
        }

        if (
            isset($value[0])
            && is_array($value[0])
        ) {
            return array_values(
                array_filter(
                    $value,
                    'is_array'
                )
            );
        }

        return [$value];
    }

    return [];
}

function ridb_inspector_decode_json(
    mixed $json
): array {
    if (
        !is_string($json)
        || trim($json) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    return is_array($decoded)
        ? $decoded
        : [];
}

function ridb_inspector_pretty_json(
    mixed $value
): string {
    return
        json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        )
        ?: '{}';
}

try {
    if ($query !== '') {
        $like =
            '%'
            . $query
            . '%';

        $stmt =
            $ridbDb->prepare(
                'SELECT
                    c.ridb_campsite_id,
                    c.ridb_facility_id,
                    c.campsite_name,
                    c.campsite_type,
                    c.campsite_accessible,
                    c.latitude,
                    c.longitude,
                    f.facility_name
                 FROM ridb_campsites c
                 LEFT JOIN ridb_facilities f
                   ON f.ridb_facility_id = c.ridb_facility_id
                 WHERE
                    c.ridb_campsite_id = ?
                    OR c.ridb_facility_id = ?
                    OR c.campsite_name LIKE ?
                    OR f.facility_name LIKE ?
                 ORDER BY
                    CASE
                        WHEN c.ridb_campsite_id = ? THEN 0
                        WHEN c.ridb_facility_id = ? THEN 1
                        WHEN c.campsite_name = ? THEN 2
                        ELSE 3
                    END,
                    f.facility_name ASC,
                    c.campsite_name ASC
                 LIMIT 100'
            );

        $stmt->execute([
            $query,
            $query,
            $like,
            $like,
            $query,
            $query,
            $query,
        ]);

        $results =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    if ($selectedId !== '') {
        $stmt =
            $ridbDb->prepare(
                'SELECT
                    c.*,
                    f.facility_name,
                    f.facility_type_description
                 FROM ridb_campsites c
                 LEFT JOIN ridb_facilities f
                   ON f.ridb_facility_id = c.ridb_facility_id
                 WHERE c.ridb_campsite_id = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $selectedId,
        ]);

        $selected =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
            ?: null;

        $rawStmt =
            $ridbDb->prepare(
                'SELECT
                    id,
                    source_record_id,
                    source_endpoint,
                    entity_id,
                    entity_type,
                    parent_id,
                    parent_type,
                    source_json,
                    first_seen_at,
                    last_seen_at
                 FROM ridb_raw_records
                 WHERE feed = ?
                   AND source_record_id = ?
                 ORDER BY id DESC
                 LIMIT 1'
            );

        $rawStmt->execute([
            'campsites',
            $selectedId,
        ]);

        $rawRow =
            $rawStmt->fetch(
                PDO::FETCH_ASSOC
            )
            ?: null;

        if ($rawRow) {
            $rawRecord =
                ridb_inspector_decode_json(
                    $rawRow['source_json']
                    ?? ''
                );

            $embeddedAttributes =
                ridb_inspector_embedded_list(
                    $rawRecord,
                    [
                        'ATTRIBUTES',
                        'Attributes',
                        'attributes',
                    ]
                );

            $embeddedEquipment =
                ridb_inspector_embedded_list(
                    $rawRecord,
                    [
                        'PERMITTEDEQUIPMENT',
                        'PermittedEquipment',
                        'permittedEquipment',
                    ]
                );

            $embeddedMedia =
                ridb_inspector_embedded_list(
                    $rawRecord,
                    [
                        'ENTITYMEDIA',
                        'EntityMedia',
                        'entityMedia',
                    ]
                );
        }

        $relatedStmt =
            $ridbDb->prepare(
                'SELECT
                    feed,
                    source_record_id,
                    source_endpoint,
                    entity_id,
                    entity_type,
                    parent_id,
                    parent_type,
                    source_json,
                    first_seen_at,
                    last_seen_at
                 FROM ridb_raw_records
                 WHERE
                    (
                        feed = ?
                        AND (
                            entity_id = ?
                            OR parent_id = ?
                        )
                        AND LOWER(
                            COALESCE(
                                NULLIF(entity_type, ""),
                                parent_type
                            )
                        ) = "campsite"
                    )
                    OR (
                        feed IN (
                            "campsite_attributes",
                            "campsite_permitted_equipment"
                        )
                        AND parent_id = ?
                        AND LOWER(parent_type) = "campsite"
                    )
                 ORDER BY feed ASC, source_record_id ASC'
            );

        $relatedStmt->execute([
            'media',
            $selectedId,
            $selectedId,
            $selectedId,
        ]);

        foreach (
            $relatedStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as $row
        ) {
            $decoded =
                ridb_inspector_decode_json(
                    $row['source_json']
                    ?? ''
                );

            $item = [
                'meta' => [
                    'feed' =>
                        (string) (
                            $row['feed']
                            ?? ''
                        ),
                    'source_record_id' =>
                        (string) (
                            $row['source_record_id']
                            ?? ''
                        ),
                    'source_endpoint' =>
                        (string) (
                            $row['source_endpoint']
                            ?? ''
                        ),
                    'entity_id' =>
                        (string) (
                            $row['entity_id']
                            ?? ''
                        ),
                    'entity_type' =>
                        (string) (
                            $row['entity_type']
                            ?? ''
                        ),
                    'parent_id' =>
                        (string) (
                            $row['parent_id']
                            ?? ''
                        ),
                    'parent_type' =>
                        (string) (
                            $row['parent_type']
                            ?? ''
                        ),
                ],
                'record' => $decoded,
            ];

            if (
                (string) (
                    $row['feed']
                    ?? ''
                ) === 'media'
            ) {
                $mirroredMedia[] =
                    $item;
            } elseif (
                (string) (
                    $row['feed']
                    ?? ''
                ) === 'campsite_attributes'
            ) {
                $mirroredAttributes[] =
                    $item;
            } elseif (
                (string) (
                    $row['feed']
                    ?? ''
                ) === 'campsite_permitted_equipment'
            ) {
                $mirroredEquipment[] =
                    $item;
            }
        }
    }

} catch (Throwable $exception) {
    $error =
        $exception->getMessage();
}

$pageStyles = [
    'admin/pages/ridb-campsite-inspector.css',
];

require __DIR__ . '/_header.php';
?>

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
            <p>Read-only mirror inspection</p>
            <h2>RIDB Campsite Inspector</h2>
        </div>

        <a
            class="admin-button is-secondary"
            href="/ridb.php"
        >
            Back to RIDB
        </a>
    </header>

    <div class="admin-user-action-box">
        <p>
            Search the completed local RIDB mirror by campsite name,
            campsite ID, facility ID, or facility name. This page does not
            call RIDB and does not import anything into Llama Scout.
        </p>

        <form
            method="get"
            class="ridb-inspector-search"
        >
            <label for="ridb-inspector-query">
                Campsite or facility
            </label>

            <div class="ridb-inspector-search-row">
                <input
                    id="ridb-inspector-query"
                    name="q"
                    type="search"
                    value="<?= moderation_e($query) ?>"
                    placeholder="Target Tree, Site 13, campsite ID, or facility ID"
                    autocomplete="off"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    Search mirror
                </button>
            </div>
        </form>
    </div>
</section>

<?php if ($query !== ''): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Search results</p>
            <h2>
                <?= number_format(count($results)) ?>
                match<?= count($results) === 1 ? '' : 'es' ?>
            </h2>
        </div>
    </header>

    <?php if (!$results): ?>
        <div class="admin-user-notice">
            No cached campsites matched
            <strong><?= moderation_e($query) ?></strong>.
        </div>
    <?php else: ?>
        <div class="ridb-inspector-results">
            <?php foreach ($results as $row): ?>
                <a
                    class="ridb-inspector-result"
                    href="/ridb-campsite-inspector.php?campsite=<?= rawurlencode(
                        (string) (
                            $row['ridb_campsite_id']
                            ?? ''
                        )
                    ) ?>&amp;q=<?= rawurlencode($query) ?>"
                >
                    <strong>
                        <?= moderation_e(
                            (string) (
                                $row['campsite_name']
                                ?: $row['ridb_campsite_id']
                            )
                        ) ?>
                    </strong>

                    <span>
                        <?= moderation_e(
                            (string) (
                                $row['facility_name']
                                ?? 'Unknown facility'
                            )
                        ) ?>
                    </span>

                    <small>
                        Campsite ID:
                        <?= moderation_e(
                            (string) (
                                $row['ridb_campsite_id']
                                ?? ''
                            )
                        ) ?>
                        · Facility ID:
                        <?= moderation_e(
                            (string) (
                                $row['ridb_facility_id']
                                ?? ''
                            )
                        ) ?>
                    </small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($selectedId !== ''): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Selected campsite</p>
            <h2>
                <?= moderation_e(
                    (string) (
                        $selected['campsite_name']
                        ?? $selectedId
                    )
                ) ?>
            </h2>
        </div>
    </header>

    <?php if (!$selected && !$rawRecord): ?>
        <div class="admin-user-notice is-error">
            Campsite <?= moderation_e($selectedId) ?>
            was not found in the normalized cache or raw mirror.
        </div>
    <?php else: ?>
        <div class="admin-integration-summary">
            <div>
                <strong>
                    <?= number_format(
                        count($embeddedMedia)
                    ) ?>
                </strong>
                <span>Embedded media items</span>
            </div>

            <div>
                <strong>
                    <?= number_format(
                        count($mirroredMedia)
                    ) ?>
                </strong>
                <span>Mirrored campsite media rows</span>
            </div>

            <div>
                <strong>
                    <?= number_format(
                        count($embeddedAttributes)
                    ) ?>
                </strong>
                <span>Embedded attributes</span>
            </div>

            <div>
                <strong>
                    <?= number_format(
                        count($embeddedEquipment)
                    ) ?>
                </strong>
                <span>Permitted equipment items</span>
            </div>
        </div>

        <div class="ridb-inspector-facts">
            <div>
                <span>Campsite ID</span>
                <strong>
                    <?= moderation_e($selectedId) ?>
                </strong>
            </div>

            <div>
                <span>Facility</span>
                <strong>
                    <?= moderation_e(
                        (string) (
                            $selected['facility_name']
                            ?? 'Unknown'
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span>Facility ID</span>
                <strong>
                    <?= moderation_e(
                        (string) (
                            $selected['ridb_facility_id']
                            ?? (
                                $rawRecord['FacilityID']
                                ?? ''
                            )
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span>Type</span>
                <strong>
                    <?= moderation_e(
                        (string) (
                            $selected['campsite_type']
                            ?? (
                                $rawRecord['CampsiteType']
                                ?? 'Unknown'
                            )
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($embeddedMedia || $mirroredMedia): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Photo investigation</p>
            <h2>Campsite Media</h2>
        </div>
    </header>

    <?php if ($embeddedMedia): ?>
        <h3 class="ridb-inspector-subheading">
            Embedded ENTITYMEDIA
        </h3>

        <div class="ridb-inspector-media-grid">
            <?php foreach ($embeddedMedia as $media): ?>
                <?php
                $url =
                    trim(
                        (string) (
                            $media['URL']
                            ?? $media['Url']
                            ?? $media['url']
                            ?? ''
                        )
                    );

                $title =
                    trim(
                        (string) (
                            $media['Title']
                            ?? $media['title']
                            ?? ''
                        )
                    );

                $type =
                    trim(
                        (string) (
                            $media['MediaType']
                            ?? $media['mediaType']
                            ?? ''
                        )
                    );

                $credit =
                    trim(
                        (string) (
                            $media['Credits']
                            ?? $media['credits']
                            ?? ''
                        )
                    );
                ?>

                <article class="ridb-inspector-media-card">
                    <?php if (
                        $url !== ''
                        && preg_match(
                            '#^https?://#i',
                            $url
                        )
                    ): ?>
                        <img
                            src="<?= moderation_e($url) ?>"
                            alt=""
                            loading="lazy"
                        >
                    <?php endif; ?>

                    <div>
                        <strong>
                            <?= moderation_e(
                                $title !== ''
                                    ? $title
                                    : 'Untitled media'
                            ) ?>
                        </strong>

                        <?php if ($type !== ''): ?>
                            <span>
                                <?= moderation_e($type) ?>
                            </span>
                        <?php endif; ?>

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
    <?php endif; ?>

    <details class="ridb-inspector-json-block">
        <summary>
            Raw embedded media JSON
            (<?= number_format(count($embeddedMedia)) ?>)
        </summary>

        <pre><?= moderation_e(
            ridb_inspector_pretty_json(
                $embeddedMedia
            )
        ) ?></pre>
    </details>

    <details class="ridb-inspector-json-block">
        <summary>
            Mirrored media rows
            (<?= number_format(count($mirroredMedia)) ?>)
        </summary>

        <pre><?= moderation_e(
            ridb_inspector_pretty_json(
                $mirroredMedia
            )
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Embedded campsite details</p>
            <h2>Attributes & Equipment</h2>
        </div>
    </header>

    <div class="ridb-inspector-json-columns">
        <details
            class="ridb-inspector-json-block"
            open
        >
            <summary>
                Embedded ATTRIBUTES
                (<?= number_format(count($embeddedAttributes)) ?>)
            </summary>

            <pre><?= moderation_e(
                ridb_inspector_pretty_json(
                    $embeddedAttributes
                )
            ) ?></pre>
        </details>

        <details
            class="ridb-inspector-json-block"
            open
        >
            <summary>
                Embedded PERMITTEDEQUIPMENT
                (<?= number_format(count($embeddedEquipment)) ?>)
            </summary>

            <pre><?= moderation_e(
                ridb_inspector_pretty_json(
                    $embeddedEquipment
                )
            ) ?></pre>
        </details>
    </div>

    <div class="ridb-inspector-json-columns">
        <details class="ridb-inspector-json-block">
            <summary>
                Extracted attribute mirror rows
                (<?= number_format(count($mirroredAttributes)) ?>)
            </summary>

            <pre><?= moderation_e(
                ridb_inspector_pretty_json(
                    $mirroredAttributes
                )
            ) ?></pre>
        </details>

        <details class="ridb-inspector-json-block">
            <summary>
                Extracted equipment mirror rows
                (<?= number_format(count($mirroredEquipment)) ?>)
            </summary>

            <pre><?= moderation_e(
                ridb_inspector_pretty_json(
                    $mirroredEquipment
                )
            ) ?></pre>
        </details>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Lossless mirror</p>
            <h2>Full Campsite JSON</h2>
        </div>
    </header>

    <details
        class="ridb-inspector-json-block"
        open
    >
        <summary>
            Raw RIDB campsite record
        </summary>

        <pre><?= moderation_e(
            ridb_inspector_pretty_json(
                $rawRecord
                ?? []
            )
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

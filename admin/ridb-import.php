<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb-import.php';
require_once dirname(__DIR__) . '/app/ridb-canonical.php';
require_once dirname(__DIR__) . '/app/ridb-public-presentation.php';
require_once dirname(__DIR__) . '/app/ridb-complete-import.php';
require_once dirname(__DIR__) . '/app/ridb-facility-details.php';
require_once dirname(__DIR__) . '/app/ridb-safe-sync.php';
require_once dirname(__DIR__) . '/app/ridb-status.php';
require_once dirname(__DIR__) . '/app/ridb-useful-fields.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'RIDB Import';

$adminPageEyebrow =
    'RIDB';

$adminActiveNav =
    'ridb';

$error = '';
$results = [];

$facilityIds =
    array_values(
        array_filter(
            array_map(
                static fn (
                    mixed $value
                ): string =>
                    trim(
                        (string) $value
                    ),
                (array) (
                    $_POST['facility_ids']
                    ?? (
                        isset($_GET['id'])
                            ? [$_GET['id']]
                            : []
                    )
                )
            ),
            static fn (
                string $value
            ): bool =>
                $value !== ''
        )
    );

$returnUrl =
    trim(
        (string) (
            $_POST['return_url']
            ?? '/ridb.php'
        )
    );

if (
    !str_starts_with($returnUrl, '/')
    || str_starts_with($returnUrl, '//')
) {
    $returnUrl = '/ridb.php';
}

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
    } elseif (!$facilityIds) {
        $error =
            'Select at least one RIDB facility to import.';
    } else {
        @set_time_limit(0);

        foreach ($facilityIds as $facilityId) {
            try {
                $mainDb = db();
                $ridbDb = ridb_db();

                $userId =
                    (int) (
                        $adminUser['id']
                        ?? 0
                    );

                $existingPlaceId =
                    llama_ridb_import_find_place(
                        $mainDb,
                        $facilityId
                    );

                if ($existingPlaceId !== null) {
                    $result =
                        llama_ridb_safe_sync_facility(
                            $mainDb,
                            $ridbDb,
                            $userId,
                            $facilityId
                        );
                } else {
                    $result =
                        llama_ridb_import_facility(
                            $mainDb,
                            $ridbDb,
                            $userId,
                            $facilityId
                        );

                    $canonical =
                        llama_ridb_canonical_sync_place(
                            $mainDb,
                            $ridbDb,
                            (int) (
                                $result['place_id']
                                ?? 0
                            ),
                            $facilityId
                        );

                    $result['canonical_answers'] =
                        (int) (
                            $canonical['answer_count']
                            ?? 0
                        );

                    $result =
                        llama_ridb_public_finalize_import(
                            $mainDb,
                            $ridbDb,
                            $userId,
                            $facilityId,
                            $result
                        );

                    $complete =
                        llama_ridb_complete_campsite_import(
                            $mainDb,
                            $ridbDb,
                            $userId,
                            (int) (
                                $result['place_id']
                                ?? 0
                            ),
                            $facilityId
                        );

                    $result =
                        array_merge(
                            $result,
                            $complete
                        );

                    $result[
                        'facility_links_imported'
                    ] =
                        llama_ridb_import_facility_links(
                            $mainDb,
                            $ridbDb,
                            (int) (
                                $result['place_id']
                                ?? 0
                            ),
                            $facilityId
                        );

                    $result['sync_mode'] =
                        'new';
                }

                $useful =
                    llama_ridb_useful_sync(
                        $mainDb,
                        $ridbDb,
                        (int) (
                            $result['place_id']
                            ?? 0
                        ),
                        $facilityId
                    );

                $result =
                    array_merge(
                        $result,
                        $useful
                    );

                $results[] =
                    array_merge(
                        [
                            'facility_id' =>
                                $facilityId,
                            'ok' => true,
                        ],
                        $result
                    );

            } catch (Throwable $exception) {
                $results[] = [
                    'facility_id' =>
                        $facilityId,
                    'ok' => false,
                    'error' =>
                        $exception->getMessage(),
                ];
            }
        }

        try {
            llama_ridb_status_reconcile(
                ridb_db(),
                db()
            );
        } catch (Throwable $exception) {
            llama_log_caught_exception(
                $exception,
                'ridb.status.reconcile.after.import'
            );
        }
    }
}

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
        <p>Production import</p>
        <h2>RIDB to Llama Scout</h2>
    </div>
</header>

<p>
    New facilities receive a complete RIDB import. Existing imported
    Places use a conservative source synchronization that refreshes RIDB-owned
    facility and campsite data while preserving existing human-entered Place
    fields, amenity answers, Scout observations, and Scout photos.
</p>

<div class="admin-user-form-actions">
<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        $returnUrl
    ) ?>"
>
    Back to RIDB catalog
</a>
</div>
</section>

<?php if ($results): ?>
<section class="admin-panel">
<div class="admin-integration-table-wrap">
<table class="admin-integration-table">
<thead>
<tr>
    <th>RIDB facility</th>
    <th>Status</th>
    <th>Mode</th>
    <th>Place</th>
    <th>Campsites</th>
    <th>Attributes</th>
    <th>Site details</th>
    <th>Site photos</th>
    <th>Place answers</th>
    <th>Location fields</th>
    <th>Place photos</th>
    <th>Official links</th>
    <th></th>
</tr>
</thead>
<tbody>

<?php foreach ($results as $result): ?>
<tr>
<td data-label="RIDB facility">
    <?= moderation_e(
        (string) (
            $result['facility_id']
            ?? ''
        )
    ) ?>
</td>

<td data-label="Status">
    <?php if (!empty($result['ok'])): ?>
        <?= !empty($result['created'])
            ? 'Imported'
            : 'Synchronized' ?>
    <?php else: ?>
        Failed
        <small>
            <?= moderation_e(
                (string) (
                    $result['error']
                    ?? ''
                )
            ) ?>
        </small>
    <?php endif; ?>
</td>

<td data-label="Mode">
    <?php if (!empty($result['ok'])): ?>
        <?= (
            $result['sync_mode']
            ?? ''
        ) === 'safe'
            ? 'Safe source sync'
            : 'New import' ?>
    <?php endif; ?>
</td>

<td data-label="Place">
    <?php if (!empty($result['place_id'])): ?>
        #<?= number_format(
            (int) $result['place_id']
        ) ?>
    <?php endif; ?>
</td>

<td data-label="Campsites">
    <?= isset($result['campsites'])
        ? number_format(
            (int) $result['campsites']
        )
        : '' ?>
</td>

<td data-label="Attributes">
    <?= isset($result['attributes'])
        ? number_format(
            (int) $result['attributes']
        )
        : '' ?>
</td>

<td data-label="Site details">
    <?= isset(
        $result['site_metadata_imported']
    )
        ? number_format(
            (int) $result['site_metadata_imported']
        )
        : '' ?>
</td>

<td data-label="Site photos">
    <?= isset(
        $result['site_media_imported']
    )
        ? number_format(
            (int) $result['site_media_imported']
        )
        : '' ?>
</td>

<td data-label="Place answers">
    <?= isset($result['canonical_answers'])
        ? number_format(
            (int) $result['canonical_answers']
        )
        : '' ?>
</td>

<td data-label="Location fields">
    <?= isset($result['core_fields_imported'])
        ? number_format(
            (int) $result['core_fields_imported']
        )
        : '' ?>
</td>

<td data-label="Place photos">
    <?= isset($result['media_imported'])
        ? number_format(
            (int) $result['media_imported']
        )
        : '' ?>
</td>

<td data-label="Official links">
    <?= isset(
        $result['facility_links_imported']
    )
        ? number_format(
            (int) $result['facility_links_imported']
        )
        : '' ?>
</td>

<td data-label="Action">
    <?php if (!empty($result['slug'])): ?>
        <a
            class="admin-button"
            href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                (string) $result['slug']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Open Place
        </a>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</section>
<?php endif; ?>

<?php if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
): ?>
<section class="admin-panel">
<div class="admin-empty-state">
    <h3>Select facilities from the RIDB catalog.</h3>

    <a
        class="admin-button"
        href="/ridb.php"
    >
        Browse RIDB catalog
    </a>
</div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

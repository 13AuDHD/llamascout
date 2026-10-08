<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';
require_once dirname(__DIR__) . '/app/ridb-catalog.php';

$adminUser =
    moderation_require_admin();

$adminPageTitle =
    'Recreation.gov / RIDB';

$adminPageEyebrow =
    'Reference Data';

$adminActiveNav =
    'reference-data';

$notice = '';
$error = '';

try {
    $ridbDb =
        ridb_db();

    $ridbDb->query(
        'SELECT 1'
    );
} catch (Throwable $exception) {
    $ridbDb = null;

    $error =
        'RIDB database connection failed: '
        . $exception->getMessage();
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
    && $ridbDb instanceof PDO
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
    } else {
        $action =
            trim(
                (string) (
                    $_POST['action']
                    ?? ''
                )
            );

        try {
            if (
                $action
                === 'sync_catalog'
            ) {
                @set_time_limit(0);

                $result =
                    llama_ridb_catalog_sync_batch(
                        $ridbDb,
                        1000
                    );

                $notice =
                    number_format(
                        (int) $result['seen']
                    )
                    . ' facilities read from RIDB and '
                    . number_format(
                        (int) $result['stored']
                    )
                    . ' cached locally. '
                    . (
                        !empty(
                            $result['complete']
                        )
                            ? 'The national catalog is complete.'
                            : 'More facilities remain; run the next batch.'
                    );

            } elseif (
                $action
                === 'restart_catalog'
            ) {
                llama_ridb_catalog_reset_sync(
                    $ridbDb
                );

                $notice =
                    'RIDB catalog synchronization was reset to the beginning.';

            } elseif (
                $action
                === 'test_api'
            ) {
                llama_ridb_request(
                    '/facilities',
                    [
                        'limit' => 1,
                        'offset' => 0,
                    ]
                );

                $notice =
                    'RIDB API connection succeeded.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$filters =
    llama_ridb_catalog_filter(
        $_GET
    );

$catalog = [
    'rows' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
];

$counts = [
    'facilities' => 0,
    'campsites' => 0,
    'attributes' => 0,
];

$syncState = [];
$types = [];
$states = [];
$importedLookup = [];

if ($ridbDb instanceof PDO) {
    try {
        $catalog =
            llama_ridb_catalog_facilities(
                $ridbDb,
                $filters,
                50
            );

        $counts =
            llama_ridb_catalog_counts(
                $ridbDb
            );

        $syncState =
            llama_ridb_catalog_sync_state(
                $ridbDb
            );

        $types =
            llama_ridb_catalog_distinct_types(
                $ridbDb
            );

        $states =
            llama_ridb_catalog_distinct_states(
                $ridbDb
            );

        $importedLookup =
            llama_ridb_catalog_imported_lookup(
                db(),
                array_column(
                    $catalog['rows'],
                    'ridb_facility_id'
                )
            );
    } catch (Throwable $exception) {
        if ($error === '') {
            $error =
                $exception->getMessage();
        }
    }
}

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
        <?= moderation_e($error) ?>
    </div>
</section>
<?php endif; ?>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Local reference catalog</p>
        <h2>RIDB Synchronization</h2>
    </div>

    <?php if (
        !empty(
            $syncState['complete']
        )
    ): ?>
        <span class="admin-status-pill">
            Complete
        </span>
    <?php endif; ?>
</header>

<div class="admin-integration-summary">

<div>
    <strong>
        <?= number_format(
            (int) $counts['facilities']
        ) ?>
    </strong>
    <span>Facilities cached</span>
</div>

<div>
    <strong>
        <?= number_format(
            (int) $counts['campsites']
        ) ?>
    </strong>
    <span>Campsites cached</span>
</div>

<div>
    <strong>
        <?= number_format(
            (int) $counts['attributes']
        ) ?>
    </strong>
    <span>Campsite attributes cached</span>
</div>

<div>
    <strong>
        <?= number_format(
            (int) (
                $syncState['next_offset']
                ?? 0
            )
        ) ?>
    </strong>
    <span>Catalog offset</span>
</div>

</div>

<div class="admin-user-action-box">

<p>
    Recreation.gov facilities are synchronized into the dedicated
    RIDB reference database first. Importing a campground into
    Llama Scout is a separate action below.
</p>

<div class="admin-user-form-actions">

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
        name="action"
        value="sync_catalog"
    >

    <button
        class="admin-button"
        type="submit"
        <?= !empty(
            $syncState['complete']
        )
            ? 'disabled'
            : '' ?>
    >
        <?= (int) (
            $syncState['next_offset']
            ?? 0
        ) > 0
            ? 'Continue synchronization'
            : 'Start synchronization' ?>
    </button>
</form>

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
        name="action"
        value="restart_catalog"
    >

    <button
        class="admin-button is-secondary"
        type="submit"
    >
        Restart catalog sync
    </button>
</form>

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
        name="action"
        value="test_api"
    >

    <button
        class="admin-button is-secondary"
        type="submit"
    >
        Test API
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
        <p>Reference database</p>
        <h2>
            <?= number_format(
                (int) $catalog['total']
            ) ?>
            matching facilities
        </h2>
    </div>
</header>

<form
    class="admin-user-action-box"
    method="get"
>

<div class="admin-integration-summary">

<label>
    <span>Search</span>

    <input
        type="search"
        name="q"
        value="<?= moderation_e(
            (string) $filters['q']
        ) ?>"
        placeholder="Campground, facility name, RIDB ID..."
    >
</label>

<label>
    <span>State</span>

    <select name="state">
        <option value="">
            All cached states
        </option>

        <?php foreach ($states as $state): ?>
        <option
            value="<?= moderation_e(
                (string) $state
            ) ?>"
            <?= $filters['state'] === $state
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e(
                (string) $state
            ) ?>
        </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Facility type</span>

    <select name="type">
        <option value="">
            All facility types
        </option>

        <?php foreach ($types as $type): ?>
        <option
            value="<?= moderation_e(
                (string) $type
            ) ?>"
            <?= $filters['type'] === $type
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e(
                (string) $type
            ) ?>
        </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Reservable</span>

    <select name="reservable">
        <option value="">
            All
        </option>

        <option
            value="yes"
            <?= $filters['reservable'] === 'yes'
                ? 'selected'
                : '' ?>
        >
            Yes
        </option>

        <option
            value="no"
            <?= $filters['reservable'] === 'no'
                ? 'selected'
                : '' ?>
        >
            No / unknown
        </option>
    </select>
</label>

</div>

<div class="admin-user-form-actions">

<button
    class="admin-button"
    type="submit"
>
    Apply filters
</button>

<a
    class="admin-button is-secondary"
    href="/ridb.php"
>
    Clear filters
</a>

</div>

</form>
</section>


<section class="admin-panel">

<?php if (!$catalog['rows']): ?>

<div class="admin-empty-state">
    <h3>No matching RIDB facilities are cached yet.</h3>
</div>

<?php else: ?>

<form
    method="post"
    action="/ridb-import.php"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="return_url"
    value="<?= moderation_e(
        llama_ridb_catalog_url(
            $filters
        )
    ) ?>"
>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Import selected into Llama Scout
    </button>
</div>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Select</th>
    <th>Facility</th>
    <th>Type</th>
    <th>Location</th>
    <th>Reservable</th>
    <th>Cached sites</th>
    <th>Llama Scout</th>
    <th></th>
</tr>
</thead>

<tbody>

<?php foreach (
    $catalog['rows']
    as $row
): ?>

<?php
$facilityId =
    (string) $row[
        'ridb_facility_id'
    ];

$imported =
    $importedLookup[
        $facilityId
    ]
    ?? null;
?>

<tr>

<td data-label="Select">
    <input
        type="checkbox"
        name="facility_ids[]"
        value="<?= moderation_e(
            $facilityId
        ) ?>"
    >
</td>

<td data-label="Facility">
    <strong>
        <?= moderation_e(
            (string) (
                $row['facility_name']
                ?? 'Unnamed facility'
            )
        ) ?>
    </strong>

    <small>
        RIDB
        <?= moderation_e(
            $facilityId
        ) ?>
    </small>
</td>

<td data-label="Type">
    <?= moderation_e(
        (string) (
            $row['facility_type_description']
            ?? ''
        )
    ) ?>
</td>

<td data-label="Location">
    <?php
    $location =
        array_filter(
            [
                trim(
                    (string) (
                        $row['city']
                        ?? ''
                    )
                ),
                trim(
                    (string) (
                        $row['state_code']
                        ?? ''
                    )
                ),
            ]
        );
    ?>

    <?= $location
        ? moderation_e(
            implode(
                ', ',
                $location
            )
        )
        : 'Not indexed yet' ?>
</td>

<td data-label="Reservable">
    <?= (int) (
        $row['reservable']
        ?? 0
    ) === 1
        ? 'Yes'
        : 'No / unknown' ?>
</td>

<td data-label="Cached sites">
    <?= number_format(
        (int) (
            $row['cached_campsite_count']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Llama Scout">
    <?php if ($imported): ?>
        <strong>Imported</strong>

        <small>
            Place #
            <?= number_format(
                (int) $imported['place_id']
            ) ?>
        </small>
    <?php else: ?>
        Not imported
    <?php endif; ?>
</td>

<td data-label="Action">

<div class="admin-user-form-actions">

<a
    class="admin-button is-secondary"
    href="/ridb-facility.php?id=<?= rawurlencode(
        $facilityId
    ) ?>"
>
    Inspect
</a>

<?php if ($imported): ?>
<a
    class="admin-button"
    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
        (string) $imported['slug']
    ) ?>"
    target="_blank"
    rel="noopener"
>
    Open Place
</a>
<?php endif; ?>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Import selected into Llama Scout
    </button>
</div>

</form>

<nav
    class="admin-user-form-actions"
    aria-label="RIDB catalog pages"
>

<?php if (
    (int) $catalog['page']
    > 1
): ?>

<?php
$previousFilters =
    $filters;

$previousFilters['page'] =
    (int) $catalog['page']
    - 1;
?>

<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_ridb_catalog_url(
            $previousFilters
        )
    ) ?>"
>
    Previous
</a>

<?php endif; ?>

<span>
    Page
    <?= number_format(
        (int) $catalog['page']
    ) ?>
    of
    <?= number_format(
        (int) $catalog['pages']
    ) ?>
</span>

<?php if (
    (int) $catalog['page']
    < (int) $catalog['pages']
): ?>

<?php
$nextFilters =
    $filters;

$nextFilters['page'] =
    (int) $catalog['page']
    + 1;
?>

<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_ridb_catalog_url(
            $nextFilters
        )
    ) ?>"
>
    Next
</a>

<?php endif; ?>

</nav>

<?php endif; ?>

</section>


<?php require __DIR__ . '/_footer.php'; ?>

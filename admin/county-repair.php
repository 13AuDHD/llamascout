<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/moderation.php';
require_once dirname(__DIR__) . '/app/geography.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();

if (
    !user_has_role(
        'owner',
        (int) ($adminUser['id'] ?? 0)
    )
) {
    header(
        'Location: https://llamascout.com/safety.php?reason=permission'
    );
    exit;
}

$db = db();

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],

    'updates' =>
        $stats['updates'],

    'reports' =>
        $stats['reports'],

    'orders' =>
        $stats['orders'],

    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'County Data Repair';

$adminPageEyebrow =
    'Maintenance';

$adminActiveNav =
    'maintenance';

$csrfToken =
    moderation_csrf_token();

$batchSize = 10;

$notice = '';
$error = '';
$results = [];
$lastProcessedId = 0;
$remaining = 0;


/* =========================================================
   COUNTS
   ========================================================= */

$totalPlaces =
    (int) $db
        ->query(
            'SELECT COUNT(*)
             FROM places'
        )
        ->fetchColumn();

$placesWithCounty =
    (int) $db
        ->query(
            'SELECT COUNT(*)
             FROM places
             WHERE county IS NOT NULL
               AND TRIM(county) <> ""'
        )
        ->fetchColumn();

$placesWithCoordinates =
    (int) $db
        ->query(
            'SELECT COUNT(*)
             FROM places
             WHERE latitude IS NOT NULL
               AND longitude IS NOT NULL'
        )
        ->fetchColumn();


/* =========================================================
   REPAIR BATCH
   ========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    try {
        if (
            !moderation_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session token expired. Reload the page and try again.'
            );
        }

        $action =
            trim(
                (string) (
                    $_POST['county_repair_action']
                    ?? ''
                )
            );

        if ($action !== 'repair') {
            throw new InvalidArgumentException(
                'Choose a valid county repair action.'
            );
        }

        $afterId =
            max(
                0,
                (int) (
                    $_POST['after_id']
                    ?? 0
                )
            );

        /*
         * Keep each request small. Census lookups are external requests,
         * so processing the database in batches avoids PHP timeout issues.
         */
        @set_time_limit(180);

        $select =
            $db->prepare(
                'SELECT
                    id,
                    name,
                    county,
                    state,
                    latitude,
                    longitude
                 FROM places
                 WHERE id > ?
                 ORDER BY id ASC
                 LIMIT '
                . $batchSize
            );

        $select->execute([
            $afterId,
        ]);

        $rows =
            $select->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $updates = [];

        foreach ($rows as $row) {
            $placeId =
                (int) (
                    $row['id']
                    ?? 0
                );

            if ($placeId < 1) {
                continue;
            }

            $lastProcessedId =
                max(
                    $lastProcessedId,
                    $placeId
                );

            $oldCounty =
                trim(
                    (string) (
                        $row['county']
                        ?? ''
                    )
                );

            $latitude =
                is_numeric(
                    $row['latitude']
                    ?? null
                )
                    ? (float) $row['latitude']
                    : null;

            $longitude =
                is_numeric(
                    $row['longitude']
                    ?? null
                )
                    ? (float) $row['longitude']
                    : null;

            try {
                $newCounty =
                    llama_normalize_place_county(
                        $oldCounty !== ''
                            ? $oldCounty
                            : null,
                        $latitude,
                        $longitude
                    );

                $newCounty =
                    trim(
                        (string) $newCounty
                    );

                if ($newCounty === '') {
                    $results[] = [
                        'id' =>
                            $placeId,

                        'name' =>
                            (string) (
                                $row['name']
                                ?? 'Place'
                            ),

                        'before' =>
                            $oldCounty,

                        'after' =>
                            '',

                        'status' =>
                            'No county resolved',
                    ];

                    continue;
                }

                if (
                    $newCounty
                    === $oldCounty
                ) {
                    $results[] = [
                        'id' =>
                            $placeId,

                        'name' =>
                            (string) (
                                $row['name']
                                ?? 'Place'
                            ),

                        'before' =>
                            $oldCounty,

                        'after' =>
                            $newCounty,

                        'status' =>
                            'Already correct',
                    ];

                    continue;
                }

                $updates[] = [
                    'id' =>
                        $placeId,

                    'county' =>
                        $newCounty,
                ];

                $results[] = [
                    'id' =>
                        $placeId,

                    'name' =>
                        (string) (
                            $row['name']
                            ?? 'Place'
                        ),

                    'before' =>
                        $oldCounty,

                    'after' =>
                        $newCounty,

                    'status' =>
                        'Updated',
                ];

            } catch (Throwable $placeException) {
                llama_log_caught_exception(
                    $placeException,
                    'admin.county_repair.place',
                    [
                        'place_id' =>
                            $placeId,
                    ]
                );

                $results[] = [
                    'id' =>
                        $placeId,

                    'name' =>
                        (string) (
                            $row['name']
                            ?? 'Place'
                        ),

                    'before' =>
                        $oldCounty,

                    'after' =>
                        '',

                    'status' =>
                        'Lookup failed',
                ];
            }
        }


        /*
         * External lookups are complete before the transaction begins.
         * The database transaction therefore stays short and atomic.
         */
        if ($updates) {
            $db->beginTransaction();

            $update =
                $db->prepare(
                    'UPDATE places
                     SET county = ?
                     WHERE id = ?'
                );

            foreach ($updates as $change) {
                $update->execute([
                    $change['county'],
                    $change['id'],
                ]);
            }

            $db->commit();
        }


        if ($rows) {
            $remainingStmt =
                $db->prepare(
                    'SELECT COUNT(*)
                     FROM places
                     WHERE id > ?'
                );

            $remainingStmt->execute([
                $lastProcessedId,
            ]);

            $remaining =
                (int)
                $remainingStmt
                    ->fetchColumn();
        }


        if (!$rows) {
            $notice =
                'No more Places remain to check.';

        } elseif ($remaining > 0) {
            $notice =
                count($updates)
                . ' county '
                . (
                    count($updates) === 1
                        ? 'value was'
                        : 'values were'
                )
                . ' corrected in this batch. '
                . number_format($remaining)
                . ' Places remain to check.';

        } else {
            $notice =
                count($updates)
                . ' county '
                . (
                    count($updates) === 1
                        ? 'value was'
                        : 'values were'
                )
                . ' corrected in this batch. County repair is complete.';
        }

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.county_repair',
                [],
                [
                    InvalidArgumentException::class,
                    RuntimeException::class,
                ]
            );

        $error =
            $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'County repair could not be completed.',
                    $reference
                );
    }
}


require __DIR__ . '/_header.php';

?>


<?php if ($notice !== ''): ?>

<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>

<?php endif; ?>


<section class="admin-panel">

<header class="admin-panel-header">

    <div>
        <p>Geographic normalization</p>
        <h2>County Data Repair</h2>
    </div>

    <span>
        Owner only
    </span>

</header>


<p>
    This one-time repair checks existing Places against the official
    U.S. Census county-equivalent at each Place coordinate. It corrects
    variants such as <strong>La Plata</strong> and
    <strong>La Plata Co.</strong> to the official
    <strong>La Plata County</strong>.
</p>

<p>
    Places outside Census coverage are left alone except for safe text
    cleanup such as expanding an existing <strong>Co.</strong>,
    <strong>Par.</strong>, or <strong>Boro.</strong> suffix.
</p>


<div class="admin-campaign-summary-grid">

    <div class="admin-campaign-summary-card">
        <span>Total Places</span>
        <strong><?= number_format($totalPlaces) ?></strong>
    </div>

    <div class="admin-campaign-summary-card">
        <span>Have County</span>
        <strong><?= number_format($placesWithCounty) ?></strong>
    </div>

    <div class="admin-campaign-summary-card">
        <span>Have Coordinates</span>
        <strong><?= number_format($placesWithCoordinates) ?></strong>
    </div>

    <div class="admin-campaign-summary-card">
        <span>Batch Size</span>
        <strong><?= number_format($batchSize) ?></strong>
    </div>

</div>


<form method="post">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e($csrfToken) ?>"
    >

    <input
        type="hidden"
        name="county_repair_action"
        value="repair"
    >

    <input
        type="hidden"
        name="after_id"
        value="<?= (int) $lastProcessedId ?>"
    >

    <button
        class="admin-button"
        type="submit"
    >
        <?= $lastProcessedId > 0
            && $remaining > 0
                ? 'Repair Next Batch'
                : 'Run County Repair'
        ?>
    </button>

</form>


<?php if ($results): ?>

<div
    class="admin-table-scroll"
    style="margin-top: 18px;"
>

<table class="admin-table">

<thead>
<tr>
    <th>Place</th>
    <th>Before</th>
    <th>After</th>
    <th>Result</th>
</tr>
</thead>

<tbody>

<?php foreach ($results as $result): ?>

<tr>

    <td>
        <strong>
            <?= moderation_e(
                (string) $result['name']
            ) ?>
        </strong>

        <small>
            #<?= (int) $result['id'] ?>
        </small>
    </td>

    <td>
        <?= moderation_e(
            (string) (
                $result['before']
                !== ''
                    ? $result['before']
                    : 'Blank'
            )
        ) ?>
    </td>

    <td>
        <?= moderation_e(
            (string) (
                $result['after']
                !== ''
                    ? $result['after']
                    : 'No change'
            )
        ) ?>
    </td>

    <td>
        <?= moderation_e(
            (string) $result['status']
        ) ?>
    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>


<?php if ($remaining > 0): ?>

<p class="admin-table-muted">
    <?= number_format($remaining) ?>
    more Places remain. Use Repair Next Batch until the page reports
    that county repair is complete.
</p>

<?php endif; ?>


</section>


<?php

require __DIR__ . '/_footer.php';

?>

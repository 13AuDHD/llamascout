<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ridb.php';

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
$searchResults = [];
$rawResponse = null;
$databaseReady = false;
$apiReady = false;

try {
    $ridbDb =
        ridb_db();

    $ridbDb->query(
        'SELECT 1'
    );

    $databaseReady = true;
} catch (Throwable $exception) {
    $error =
        'RIDB database connection failed: '
        . $exception->getMessage();

    $ridbDb = null;
}

try {
    $config =
        llama_ridb_config();

    $apiReady =
        trim(
            (string) (
                $config['api_key']
                ?? ''
            )
        ) !== '';
} catch (Throwable $exception) {
    if ($error === '') {
        $error =
            'RIDB API configuration failed: '
            . $exception->getMessage();
    }
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
    } else {
        $action =
            trim(
                (string) (
                    $_POST['action']
                    ?? ''
                )
            );

        try {
            if ($action === 'test_api') {
                $response =
                    llama_ridb_request(
                        '/facilities',
                        [
                            'limit' => 1,
                            'offset' => 0,
                        ]
                    );

                $apiReady = true;

                $records =
                    llama_ridb_response_records(
                        $response
                    );

                $notice =
                    'RIDB API connection succeeded.';

                if ($ridbDb instanceof PDO) {
                    llama_ridb_log_sync_run(
                        $ridbDb,
                        'connection_test',
                        'success',
                        count($records),
                        0,
                        'RIDB API connection test'
                    );
                }

                $rawResponse =
                    $response['data']
                    ?? null;

            } elseif (
                $action === 'search_facilities'
            ) {
                $query =
                    trim(
                        (string) (
                            $_POST['query']
                            ?? ''
                        )
                    );

                if ($query === '') {
                    throw new InvalidArgumentException(
                        'Enter a facility name or search term.'
                    );
                }

                $response =
                    llama_ridb_facility_search(
                        $query,
                        25,
                        0
                    );

                $searchResults =
                    llama_ridb_response_records(
                        $response
                    );

                $rawResponse =
                    $response['data']
                    ?? null;

                $stored = 0;

                if ($ridbDb instanceof PDO) {
                    $stored =
                        llama_ridb_store_facilities(
                            $ridbDb,
                            $searchResults
                        );

                    llama_ridb_log_sync_run(
                        $ridbDb,
                        'facility_search',
                        'success',
                        count(
                            $searchResults
                        ),
                        $stored,
                        'Search: '
                        . $query
                    );
                }

                $notice =
                    number_format(
                        count(
                            $searchResults
                        )
                    )
                    . ' RIDB facilities returned. '
                    . number_format(
                        $stored
                    )
                    . ' cached in the RIDB reference database.';
            }
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();

            if (
                isset($ridbDb)
                && $ridbDb instanceof PDO
            ) {
                try {
                    llama_ridb_log_sync_run(
                        $ridbDb,
                        $action !== ''
                            ? $action
                            : 'unknown',
                        'failed',
                        0,
                        0,
                        $error
                    );
                } catch (Throwable) {
                    // Keep the original RIDB error.
                }
            }
        }
    }
}

$recentRuns = [];

if (
    isset($ridbDb)
    && $ridbDb instanceof PDO
) {
    try {
        $recentRuns =
            $ridbDb
                ->query(
                    'SELECT
                        id,
                        run_type,
                        status,
                        records_seen,
                        records_stored,
                        message,
                        completed_at
                     FROM ridb_sync_runs
                     ORDER BY id DESC
                     LIMIT 10'
                )
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];
    } catch (Throwable) {
        $recentRuns = [];
    }
}

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
    <section class="admin-panel">
        <strong><?= moderation_e($notice) ?></strong>
    </section>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <section class="admin-panel">
        <strong>RIDB error</strong>
        <p><?= moderation_e($error) ?></p>
    </section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Connection</p>
            <h2>RIDB Status</h2>
        </div>
    </header>

    <dl class="admin-user-definition-list">
        <div>
            <dt>RIDB database</dt>
            <dd>
                <?= $databaseReady
                    ? 'Connected'
                    : 'Not connected' ?>
            </dd>
        </div>

        <div>
            <dt>API configuration</dt>
            <dd>
                <?= $apiReady
                    ? 'Key loaded'
                    : 'Not ready' ?>
            </dd>
        </div>
    </dl>

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
            class="admin-button"
            type="submit"
        >
            Test Recreation.gov API
        </button>
    </form>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>RIDB lookup</p>
            <h2>Search Facilities</h2>
        </div>
    </header>

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
            value="search_facilities"
        >

        <label>
            <span>Facility name or search term</span>

            <input
                type="search"
                name="query"
                value="<?= moderation_e(
                    (string) (
                        $_POST['query']
                        ?? ''
                    )
                ) ?>"
                placeholder="e.g. Mesa Verde, Yosemite"
                required
            >
        </label>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                Search RIDB
            </button>
        </div>
    </form>
</section>

<?php if ($searchResults): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Search results</p>
            <h2>Facilities returned</h2>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Facility</th>
                    <th>Type</th>
                    <th>Reservable</th>
                    <th>Coordinates</th>
                    <th>RIDB ID</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach (
                $searchResults
                as $facility
            ): ?>
                <?php if (!is_array($facility)) {
                    continue;
                } ?>

                <tr>
                    <td>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $facility[
                                        'FacilityName'
                                    ]
                                    ?? 'Unnamed facility'
                                )
                            ) ?>
                        </strong>
                    </td>

                    <td>
                        <?= moderation_e(
                            (string) (
                                $facility[
                                    'FacilityTypeDescription'
                                ]
                                ?? ''
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= !empty(
                            $facility['Reservable']
                        )
                            ? 'Yes'
                            : 'No / unknown' ?>
                    </td>

                    <td>
                        <?php
                        $lat =
                            $facility[
                                'FacilityLatitude'
                            ]
                            ?? null;

                        $lng =
                            $facility[
                                'FacilityLongitude'
                            ]
                            ?? null;
                        ?>

                        <?= is_numeric($lat)
                            && is_numeric($lng)
                                ? moderation_e(
                                    number_format(
                                        (float) $lat,
                                        6
                                    )
                                    . ', '
                                    . number_format(
                                        (float) $lng,
                                        6
                                    )
                                )
                                : 'Not provided' ?>
                    </td>

                    <td>
                        <?= moderation_e(
                            (string) (
                                $facility[
                                    'FacilityID'
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
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>History</p>
            <h2>Recent RIDB Runs</h2>
        </div>
    </header>

    <?php if (!$recentRuns): ?>
        <div class="admin-empty-state">
            <p>
                No RIDB activity has been logged yet.
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Status</th>
                        <th>Seen</th>
                        <th>Stored</th>
                        <th>Completed</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (
                    $recentRuns
                    as $run
                ): ?>
                    <tr>
                        <td>
                            <?= moderation_e(
                                (string) $run[
                                    'run_type'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) $run[
                                    'status'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $run[
                                    'records_seen'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) $run[
                                    'records_stored'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= moderation_e(
                                (string) (
                                    $run[
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

<?php if (
    is_array($rawResponse)
): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Development inspection</p>
            <h2>Raw RIDB Response</h2>
        </div>
    </header>

    <details>
        <summary>Show raw JSON</summary>

        <pre><?= moderation_e(
            json_encode(
                $rawResponse,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            ?: ''
        ) ?></pre>
    </details>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

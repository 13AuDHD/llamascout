<?php

declare(strict_types=1);

/**
 * Issue #6 / Phase 1B: official campground fact comparison.
 *
 * Read-only: no changes to raw NPS, RIDB, source-identity reviews,
 * published Places, Scout contributions, or moderation decisions.
 * Only current, manually confirmed NPS/RIDB identities are compared.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/nps.php';
require_once dirname(__DIR__) . '/app/official-source-reconciliation.php';

$adminUser = moderation_require_admin();
$adminPageTitle = 'NPS / RIDB Field Reconciliation';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';

function llama_official_review_label(string $state): string
{
    return match ($state) {
        'conflict' => 'Not verified — sources disagree',
        'invalid_source' => 'Not verified — invalid source count',
        'ambiguous_source' => 'Not verified — ambiguous source values',
        'needs_review' => 'Location needs review',
        'agreement' => 'Sources agree (candidate)',
        'single_source' => 'One-source candidate',
        'compatible_candidates' => 'Locations nearby (candidate)',
        'missing' => 'No comparable value',
        default => 'Review required',
    };
}

function llama_official_review_preview(array $result): string
{
    $value = $result['canonical_preview'] ?? null;
    if ($value === null || $value === '') {
        return 'Not verified';
    }
    return (string) $value;
}

function llama_official_review_source_value(array $result, string $provider): string
{
    foreach ((array) ($result['facts'] ?? []) as $fact) {
        if (($fact['provider'] ?? '') === $provider) {
            return (string) ($fact['value'] ?? '');
        }
    }
    return '';
}

function llama_official_review_csv_safe(mixed $value): string
{
    $text = (string) ($value ?? '');
    if (preg_match('/^[\x00-\x20\x{FEFF}]*[=+@\-]/u', $text)) {
        return "'" . $text;
    }
    return $text;
}

$labels = [
    'site_total' => ['Number of campsites', 'Place', 'count'],
    'regular_nightly_fee_usd' => ['Regular nightly fee (USD)', 'Place / rate', 'USD per night'],
    'reservable_sites' => ['Reservable sites (count)', 'Place', 'count'],
    'location' => ['Campground coordinates', 'Place / access point', 'coordinates'],
    'place_rv_max_feet' => ['Maximum RV length (policy)', 'Place (not Site)', 'feet'],
];

$error = '';
$rows = [];
$stale = [];
$stats = [
    'current' => 0, 'stale' => 0, 'conflicts' => 0,
    'internal' => 0, 'location' => 0,
];

try {
    $npsDb = nps_db();
    $ridbDb = ridb_db();
    $statement = $npsDb->query(
        "SELECT n.park_code, n.record_id, n.record_name, n.source_hash,
                n.source_json, r.ridb_facility_id, r.nps_source_hash,
                r.reviewed_at
           FROM nps_raw_records n
           JOIN nps_ridb_review_events r
             ON r.nps_park_code = n.park_code
            AND r.nps_record_id = n.record_id
      LEFT JOIN nps_ridb_review_events newer
             ON newer.nps_park_code = r.nps_park_code
            AND newer.nps_record_id = r.nps_record_id
            AND newer.id > r.id
          WHERE n.resource_type = 'campgrounds'
            AND r.decision = 'confirmed_match'
            AND newer.id IS NULL
          ORDER BY n.park_code, n.record_name
          LIMIT 500"
    );
    $loadRidb = $ridbDb->prepare(
        'SELECT ridb_facility_id, facility_name, source_json,
                last_updated_date
           FROM ridb_facilities
          WHERE ridb_facility_id = ? LIMIT 1'
    );
    $claimed = [];
    while (($item = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $identity = (string) $item['park_code'] . ':' . (string) $item['record_id'];
        $ridbId = (string) ($item['ridb_facility_id'] ?? '');
        if ($ridbId === '' || isset($claimed[$ridbId])) {
            $stale[] = $identity . ' — invalid or duplicate RIDB identity';
            $stats['stale']++;
            continue;
        }
        $claimed[$ridbId] = true;
        if (!hash_equals((string) $item['nps_source_hash'], (string) $item['source_hash'])) {
            $stale[] = $identity . ' — NPS source changed since identity review';
            $stats['stale']++;
            continue;
        }
        $npsRecord = json_decode((string) $item['source_json'], true);
        $loadRidb->execute([$ridbId]);
        $ridbItem = $loadRidb->fetch(PDO::FETCH_ASSOC);
        $ridbRecord = is_array($ridbItem)
            ? json_decode((string) ($ridbItem['source_json'] ?? ''), true)
            : null;
        if (!is_array($npsRecord) || !is_array($ridbRecord)
            || (string) ($npsRecord['id'] ?? '') !== (string) $item['record_id']
            || (string) ($ridbRecord['FacilityID'] ?? '') !== $ridbId) {
            $stale[] = $identity . ' — missing or inconsistent source JSON';
            $stats['stale']++;
            continue;
        }
        $assessment = llama_official_nps_ridb_assessment($npsRecord, $ridbRecord);
        $stats['current']++;
        foreach ($assessment as $field => $result) {
            if ($result['state'] === 'conflict') {
                $stats['conflicts']++;
            }
            if ($result['state'] === 'invalid_source') {
                $stats['internal']++;
            }
            if ($field === 'location' && $result['state'] === 'needs_review') {
                $stats['location']++;
            }
        }
        $rows[] = [
            'park' => (string) $item['park_code'],
            'nps_id' => (string) $item['record_id'],
            'ridb_id' => $ridbId,
            'name' => (string) $item['record_name'],
            'ridb_name' => (string) $ridbItem['facility_name'],
            'nps_hash' => (string) $item['source_hash'],
            'ridb_source_updated' => (string) ($ridbItem['last_updated_date'] ?? ''),
            'assessment' => $assessment,
        ];
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if (($_GET['format'] ?? '') === 'csv' && $error === '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nps-ridb-field-reconciliation.csv"');
    header('Cache-Control: no-store');
    $handle = fopen('php://output', 'wb');
    if ($handle !== false) {
        fputcsv($handle, [
            'NPS park', 'Campground', 'NPS record ID', 'RIDB facility ID',
            'Canonical field candidate', 'Scope', 'NPS evidence', 'RIDB evidence',
            'Resolution status', 'Canonical preview (not published)',
            'Comparison units', 'Coordinate distance (miles)',
            'NPS source hash', 'RIDB last updated',
        ]);
        foreach ($rows as $row) {
            foreach ($row['assessment'] as $field => $result) {
                [$label, $scope, $unit] = $labels[$field];
                $preview = llama_official_review_preview($result);
                fputcsv($handle, array_map('llama_official_review_csv_safe', [
                    $row['park'], $row['name'], $row['nps_id'], $row['ridb_id'],
                    $label, $scope,
                    llama_official_review_source_value($result, 'nps'),
                    llama_official_review_source_value($result, 'ridb'),
                    llama_official_review_label($result['state']),
                    $preview === 'Not verified' ? '' : $preview,
                    $unit,
                    $field === 'location' && isset($result['distance_miles'])
                        ? round((float) $result['distance_miles'], 3) : '',
                    $row['nps_hash'], $row['ridb_source_updated'],
                ]));
            }
        }
        fclose($handle);
    }
    exit;
}

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Issue #6 — Phase 1B / Phase 3B foundation</p>
            <h2>Official source field reconciliation</h2>
        </div>
        <span class="admin-status-pill">Read-only comparison</span>
    </header>
    <p>
        Compare only current, manually confirmed NPS and Recreation.gov / RIDB
        identities. Disagreements withhold the canonical field preview while
        preserving both source records. Single-source and matching values are
        candidates, not approved changes to published Places.
    </p>
    <div class="admin-user-form-actions">
        <a class="admin-button is-secondary" href="/nps-ridb-matches.php">
            Back to identity review
        </a>
        <?php if ($error === ''): ?>
            <a class="admin-button" href="/nps-field-reconciliation.php?format=csv">
                Export field comparisons (CSV)
            </a>
        <?php endif; ?>
    </div>
</section>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <div class="admin-user-notice is-error" role="alert">
        <?= moderation_e($error) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($error === ''): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Source comparison</p>
            <h2>Verified identities, unverified values</h2>
        </div>
    </header>
    <div class="admin-integration-summary">
        <div><strong><?= number_format($stats['current']) ?></strong><span>Current reviewed pairs</span></div>
        <div><strong><?= number_format($stats['conflicts']) ?></strong><span>Conflicting field comparisons</span></div>
        <div><strong><?= number_format($stats['internal']) ?></strong><span>Internal source inconsistencies</span></div>
        <div><strong><?= number_format($stats['location']) ?></strong><span>Coordinates needing review</span></div>
        <div><strong><?= number_format($stats['stale']) ?></strong><span>Stale or invalid identities skipped</span></div>
    </div>
    <p>
        Fee comparisons are intentionally limited to clearly described regular
        nightly prices. Other rates, discounts, seasons, and parking charges
        remain distinct. Narrative campsite counts are evidence, not verified
        individual Site inventories. Coordinates never automatically replace a Place marker.
    </p>
</section>

<?php if ($stale): ?>
<section class="admin-panel">
    <header class="admin-panel-header"><div><h2>Identity reviews requiring attention</h2></div></header>
    <?php foreach ($stale as $message): ?>
        <p><?= moderation_e($message) ?></p>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div><p>Source-by-source evidence</p><h2>Canonical fact candidates</h2></div>
    </header>
    <?php if (!$rows): ?>
        <div class="admin-empty-state"><p>No current confirmed source pairs available.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Campground / source records</th>
                    <th>Fact and ownership</th>
                    <th>NPS</th>
                    <th>RIDB</th>
                    <th>Reconciliation</th>
                    <th>Canonical preview</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php foreach ($row['assessment'] as $field => $result): ?>
                            <?php [$label, $scope, $unit] = $labels[$field]; ?>
                            <tr>
                                <td>
                                    <strong><?= moderation_e($row['name']) ?></strong>
                                    <p>NPS <?= moderation_e(strtoupper($row['park'])) ?>:
                                    <?= moderation_e($row['nps_id']) ?></p>
                                    <p>RIDB: <?= moderation_e($row['ridb_id']) ?></p>
                                </td>
                                <td><strong><?= moderation_e($label) ?></strong>
                                    <p><?= moderation_e($scope) ?> · <?= moderation_e($unit) ?></p></td>
                                <td><?= moderation_e(llama_official_review_source_value($result, 'nps')) ?></td>
                                <td><?= moderation_e(llama_official_review_source_value($result, 'ridb')) ?></td>
                                <td>
                                    <strong><?= moderation_e(llama_official_review_label($result['state'])) ?></strong>
                                    <?php if ($field === 'location' && $result['distance_miles'] !== null): ?>
                                        <p><?= number_format($result['distance_miles'], 2) ?> mi apart</p>
                                    <?php endif; ?>
                                    <?php if ($field === 'place_rv_max_feet' && !empty($result['not_applicable'])): ?>
                                        <p>RV camping prohibited; zero is not a measured length.</p>
                                    <?php endif; ?>
                                </td>
                                <td><?= moderation_e(llama_official_review_preview($result)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <header class="admin-panel-header"><div><h2>What this page does not publish</h2></div></header>
    <p>
        This is a review preview only. No values are inserted into the primary
        Places database. Source conflicts must be resolved with reliable evidence
        before their canonical fields are populated. Current safety alerts,
        road closures, and service interruptions remain separately attributable
        NPS advisory records and must not be suppressed by fee/count conflicts.
    </p>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

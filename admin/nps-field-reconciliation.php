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

/** Short status for narrow viewports; full explanation remains alongside it. */
function llama_official_review_short_label(string $state): string
{
    return match ($state) {
        'conflict' => 'Sources disagree',
        'invalid_source' => 'Inconsistent count',
        'ambiguous_source' => 'Ambiguous evidence',
        'needs_review' => 'Review coordinates',
        'agreement' => 'Sources agree',
        'single_source' => 'One source',
        'compatible_candidates' => 'Nearby locations',
        'missing' => 'No data',
        default => 'Review required',
    };
}

function llama_official_review_attention_state(string $state): bool
{
    return in_array($state, [
        'conflict', 'invalid_source', 'ambiguous_source', 'needs_review',
    ], true);
}

function llama_official_review_evidence_label(array $result, string $provider): string
{
    $value = trim(llama_official_review_source_value($result, $provider));
    return $value === '' ? 'Not provided' : $value;
}

$view = (string) ($_GET['view'] ?? 'attention');
if (!in_array($view, ['attention', 'all'], true)) {
    $view = 'attention';
}

$attentionCount = 0;
$attentionCampgrounds = 0;
$totalComparisons = 0;
foreach ($rows as $row) {
    $hasAttention = false;
    foreach ($row['assessment'] as $result) {
        $totalComparisons++;
        if (llama_official_review_attention_state((string) $result['state'])) {
            $attentionCount++;
            $hasAttention = true;
        }
    }
    if ($hasAttention) {
        $attentionCampgrounds++;
    }
}

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Issue #6 · Official source reconciliation</p>
            <h2>NPS and Recreation.gov field review</h2>
        </div>
        <span class="admin-status-pill">Read only</span>
    </header>
    <div class="admin-panel-body">
        <p>
            <strong>What you need to do:</strong> Nothing needs to be approved on
            this page. It shows where the official NPS and RIDB data agrees,
            disagrees, or is incomplete. We will use the exported comparisons
            to decide what can safely populate the Scout Report later.
        </p>
        <p>
            <strong>When sources disagree, the proposed value remains blank.</strong>
            Their original values stay in their own source databases.
            Nothing here changes a published Place or a Scout contribution.
        </p>
        <div class="admin-user-form-actions">
            <?php if ($error === ''): ?>
                <a class="admin-button" href="/nps-field-reconciliation.php?format=csv">
                    Export the comparison CSV
                </a>
            <?php endif; ?>
            <a class="admin-button is-secondary" href="/nps-ridb-matches.php">
                View campground identity matches
            </a>
        </div>
    </div>
</section>

<?php if ($error !== ''): ?>
<section class="admin-panel">
    <div class="admin-panel-body">
        <div class="admin-user-notice is-error" role="alert">
            <?= moderation_e($error) ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($error === ''): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Review summary</p>
            <h2><?= number_format($stats['current']) ?> confirmed campground pairs</h2>
        </div>
    </header>
    <div class="admin-panel-body">
        <p>
            <strong><?= number_format($attentionCount) ?> facts needing attention</strong>
            across <?= number_format($attentionCampgrounds) ?> campgrounds.
            The full comparison contains <?= number_format($totalComparisons) ?> field assessments.
        </p>
        <p>
            <?= number_format($stats['conflicts']) ?> source disagreements ·
            <?= number_format($stats['internal']) ?> internal count inconsistencies ·
            <?= number_format($stats['location']) ?> coordinate discrepancies ·
            <?= number_format($stats['stale']) ?> stale source identities skipped.
        </p>
        <p>
            These are <strong>review flags, not live closures or verified public facts.</strong>
            NPS safety alerts remain separately attributed and must not be hidden
            because fee or site-count data conflicts.
        </p>
        <div class="admin-user-form-actions">
            <a class="admin-button<?= $view === 'attention' ? '' : ' is-secondary' ?>"
                href="/nps-field-reconciliation.php?view=attention"
                <?= $view === 'attention' ? 'aria-current="page"' : '' ?>>
                Needs attention (<?= number_format($attentionCount) ?>)
            </a>
            <a class="admin-button<?= $view === 'all' ? '' : ' is-secondary' ?>"
                href="/nps-field-reconciliation.php?view=all"
                <?= $view === 'all' ? 'aria-current="page"' : '' ?>>
                Show all (<?= number_format($totalComparisons) ?>)
            </a>
        </div>
    </div>
</section>

<?php if ($stale): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div><h2>Source identities needing a new review</h2></div>
    </header>
    <div class="admin-panel-body">
        <?php foreach ($stale as $message): ?>
            <p><?= moderation_e($message) ?></p>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!$rows): ?>
<section class="admin-panel">
    <div class="admin-panel-body">
        <p>No current confirmed source pairs are available.</p>
    </div>
</section>
<?php else: ?>
    <?php $displayedCampgrounds = 0; ?>
    <?php foreach ($rows as $row): ?>
        <?php
        $fieldsToShow = [];
        foreach ($row['assessment'] as $field => $result) {
            if ($view === 'all' || llama_official_review_attention_state((string) $result['state'])) {
                $fieldsToShow[$field] = $result;
            }
        }
        if (!$fieldsToShow) {
            continue;
        }
        $displayedCampgrounds++;
        ?>
        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p><?= moderation_e(strtoupper($row['park'])) ?> ·
                        RIDB <?= moderation_e($row['ridb_id']) ?></p>
                    <h2><?= moderation_e($row['name']) ?></h2>
                </div>
                <span class="admin-status-pill">
                    <?= number_format(count($fieldsToShow)) ?> fact<?= count($fieldsToShow) === 1 ? '' : 's' ?>
                </span>
            </header>
            <div class="admin-panel-body">
                <details>
                    <summary>Source record details</summary>
                    <p><strong>NPS ID:</strong> <?= moderation_e($row['nps_id']) ?></p>
                    <p><strong>RIDB ID:</strong> <?= moderation_e($row['ridb_id']) ?></p>
                    <p><strong>RIDB name:</strong> <?= moderation_e($row['ridb_name']) ?></p>
                    <p><strong>RIDB source updated:</strong>
                        <?= moderation_e($row['ridb_source_updated'] !== ''
                            ? $row['ridb_source_updated'] : 'Not supplied') ?></p>
                </details>
                <div class="admin-panel-stack">
                    <?php foreach ($fieldsToShow as $field => $result): ?>
                        <?php [$label, $scope, $unit] = $labels[$field]; ?>
                        <section class="admin-panel">
                            <header class="admin-panel-header">
                                <div>
                                    <p><?= moderation_e($scope) ?> · <?= moderation_e($unit) ?></p>
                                    <h2><?= moderation_e($label) ?></h2>
                                </div>
                                <span class="admin-status-pill">
                                    <?= moderation_e(llama_official_review_short_label($result['state'])) ?>
                                </span>
                            </header>
                            <div class="admin-panel-body">
                                <p><strong>NPS:</strong>
                                    <?= moderation_e(llama_official_review_evidence_label($result, 'nps')) ?>
                                </p>
                                <p><strong>Recreation.gov / RIDB:</strong>
                                    <?= moderation_e(llama_official_review_evidence_label($result, 'ridb')) ?>
                                </p>
                                <p><strong>Proposed value:</strong>
                                    <?= moderation_e(llama_official_review_preview($result)) ?>
                                </p>
                                <?php if ($field === 'location' && $result['distance_miles'] !== null): ?>
                                    <p>Source positions differ by
                                        <?= number_format((float) $result['distance_miles'], 2) ?> miles.
                                        Neither position automatically replaces a Place marker.</p>
                                <?php endif; ?>
                                <?php if ($field === 'place_rv_max_feet' && !empty($result['not_applicable'])): ?>
                                    <p>RV camping is prohibited; zero does not mean a measured zero-foot limit.</p>
                                <?php endif; ?>
                                <?php if (llama_official_review_attention_state((string) $result['state'])): ?>
                                    <p><strong>Why this is blank:</strong>
                                        <?= moderation_e(llama_official_review_label($result['state'])) ?>.
                                        We need reliable evidence before filling the canonical field.</p>
                                <?php elseif ($result['state'] === 'single_source'): ?>
                                    <p>Only one provider offers a comparable value.
                                        This is a candidate, not an approved publication.</p>
                                <?php elseif ($result['state'] === 'compatible_candidates'): ?>
                                    <p>The positions are close, but location still requires a deliberate choice.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($displayedCampgrounds === 0): ?>
        <section class="admin-panel">
            <div class="admin-panel-body">
                <p>There are no outstanding field discrepancies in the currently reviewed source pairs.</p>
                <p>Select “Show all” to inspect matching and one-source facts.</p>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div><h2>What comes next</h2></div>
    </header>
    <div class="admin-panel-body">
        <p>
            Use “Export the comparison CSV” to get the complete evidence report.
            We'll use it to define official-field provenance and safe Place linking.
            Do not resolve source conflicts by choosing whichever number looks newer.
        </p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

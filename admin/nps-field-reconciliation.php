<?php

declare(strict_types=1);

/**
 * Issue #6 / Phase 1B: official campground fact comparison.
 *
 * Source evidence is read-only. Admins may append field-level review decisions
 * to the separate NPS review ledger; no changes are made to raw source records,
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

/**
 * Persist reviewed interpretations separately from the raw source records.
 * No decision writes to the published Place or Scout Report tables.
 */
function llama_official_review_record_key(string $park, string $id, string $field): string
{
    return $park . ':' . $id . ':' . $field;
}

/** Exact source evidence, never a value inferred from a nearby facility. */
function llama_official_review_raw_value(array $assessment, string $provider): ?string
{
    foreach ((array) ($assessment['facts'] ?? []) as $fact) {
        if (($fact['provider'] ?? '') === $provider) {
            $value = trim((string) ($fact['value'] ?? ''));
            return $value === '' ? null : $value;
        }
    }
    return null;
}

function llama_official_review_normalize_value(string $field, string $raw): string
{
    $raw = trim($raw);
    if (in_array($field, ['site_total', 'reservable_sites', 'place_rv_max_feet'], true)) {
        if (!preg_match('/^[0-9]{1,6}$/D', $raw)) {
            throw new InvalidArgumentException('A non-negative whole number is required.');
        }
        return (string) (int) $raw;
    }
    if ($field === 'regular_nightly_fee_usd') {
        if (!preg_match('/^[0-9]{1,6}(?:\.[0-9]{1,2})?$/D', $raw)) {
            throw new InvalidArgumentException('Enter a valid USD amount, such as 30.00.');
        }
        return number_format((float) $raw, 2, '.', '');
    }
    if ($field === 'location') {
        $parts = explode(',', $raw);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Enter coordinates as latitude,longitude.');
        }
        $numbers = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (!preg_match('/^-?(?:[0-9]{1,3})(?:\.[0-9]{1,15})?$/D', $part)) {
                throw new InvalidArgumentException('Coordinates must be decimal numbers.');
            }
            $numbers[] = (float) $part;
        }
        if (abs($numbers[0]) > 90 || abs($numbers[1]) > 180
            || ($numbers[0] === 0.0 && $numbers[1] === 0.0)) {
            throw new InvalidArgumentException('Latitude or longitude is outside the permitted range.');
        }
        return number_format($numbers[0], 6, '.', '') . ','
            . number_format($numbers[1], 6, '.', '');
    }
    throw new InvalidArgumentException('This field cannot be resolved with a manual value.');
}

/** A map link is evidence for checking geometry, not automatic approval. */
function llama_official_review_map_url(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    try {
        $normalized = llama_official_review_normalize_value('location', $raw);
    } catch (InvalidArgumentException) {
        return null;
    }
    [$lat, $lon] = explode(',', $normalized);
    return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon
        . '#map=16/' . $lat . '/' . $lon;
}

function llama_official_review_latest(PDO $npsDb): array
{
    $stmt = $npsDb->query(
        'SELECT e.id, e.nps_park_code, e.nps_record_id, e.ridb_facility_id,
                e.field_key, e.decision, e.selected_value, e.nps_source_hash,
                e.ridb_source_hash, e.review_note, e.evidence_url,
                e.reviewer_user_id, e.reviewed_at
           FROM nps_field_review_events e
      LEFT JOIN nps_field_review_events newer
             ON newer.nps_park_code = e.nps_park_code
            AND newer.nps_record_id = e.nps_record_id
            AND newer.field_key = e.field_key
            AND newer.id > e.id
          WHERE newer.id IS NULL'
    );
    $latest = [];
    while (($event = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $latest[llama_official_review_record_key(
            (string) $event['nps_park_code'],
            (string) $event['nps_record_id'],
            (string) $event['field_key']
        )] = $event;
    }
    return $latest;
}

function llama_official_review_apply(array &$rows, array $latest): void
{
    foreach ($rows as &$row) {
        foreach ($row['assessment'] as $key => &$result) {
            $event = $latest[llama_official_review_record_key(
                $row['park'], $row['nps_id'], $key
            )] ?? null;
            $result['human_review'] = $event;
            $result['review_stale'] = $event !== null && (
                !hash_equals((string) $event['nps_source_hash'], $row['nps_hash'])
                || !hash_equals((string) $event['ridb_source_hash'], $row['ridb_hash'])
                || (string) $event['ridb_facility_id'] !== $row['ridb_id']
            );
        }
        unset($result);
    }
    unset($row);
}

function llama_official_review_save(PDO $npsDb, array $rows, array $post, int $adminId): void
{
    if ($adminId < 1) {
        throw new RuntimeException('An authenticated admin user is required.');
    }
    $park = trim((string) ($post['nps_park_code'] ?? ''));
    $id = trim((string) ($post['nps_record_id'] ?? ''));
    $field = trim((string) ($post['field_key'] ?? ''));
    $decision = trim((string) ($post['decision'] ?? ''));
    if (!in_array($decision, ['use_nps', 'use_ridb', 'verified_value', 'unresolved'], true)) {
        throw new InvalidArgumentException('Choose a review decision.');
    }
    $target = null;
    foreach ($rows as $row) {
        if ($row['park'] === $park && $row['nps_id'] === $id) {
            $target = $row;
            break;
        }
    }
    if ($target === null || !isset($target['assessment'][$field])) {
        throw new InvalidArgumentException('The campground or field is not in the current comparison.');
    }
    $result = $target['assessment'][$field];
    if (!llama_official_review_attention_state((string) $result['state'])) {
        throw new InvalidArgumentException('This fact is not flagged for conflict review.');
    }
    if (!hash_equals($target['nps_hash'], (string) ($post['nps_source_hash'] ?? ''))
        || !hash_equals($target['ridb_hash'], (string) ($post['ridb_source_hash'] ?? ''))) {
        throw new RuntimeException('Official source evidence has changed. Reload the page and review again.');
    }
    $note = trim((string) ($post['review_note'] ?? ''));
    $noteLength = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);
    if ($noteLength < 15 || $noteLength > 2000) {
        throw new InvalidArgumentException('Explain the evidence in 15–2,000 characters.');
    }
    $url = trim((string) ($post['evidence_url'] ?? ''));
    if ($url !== '') {
        $urlParts = parse_url($url);
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)
            || !is_array($urlParts)
            || ($urlParts['scheme'] ?? '') !== 'https'
            || empty($urlParts['host'])
            || isset($urlParts['user']) || isset($urlParts['pass'])) {
            throw new InvalidArgumentException('Supporting URL must be a valid HTTPS web link.');
        }
    }
    $selected = null;
    if ($decision === 'use_nps' || $decision === 'use_ridb') {
        $provider = $decision === 'use_nps' ? 'nps' : 'ridb';
        $raw = llama_official_review_raw_value($result, $provider);
        if ($raw === null) {
            throw new InvalidArgumentException('That provider has no comparable value.');
        }
        if (($result['state'] ?? '') === 'invalid_source' && $decision === 'use_nps') {
            throw new InvalidArgumentException(
                'NPS reports an internally inconsistent count. Enter an independently verified value instead.'
            );
        }
        $selected = llama_official_review_normalize_value($field, $raw);
    } elseif ($decision === 'verified_value') {
        $selected = llama_official_review_normalize_value(
            $field, (string) ($post['verified_value'] ?? '')
        );
    }
    $stmt = $npsDb->prepare(
        'INSERT INTO nps_field_review_events
         (nps_park_code, nps_record_id, ridb_facility_id, field_key,
          decision, selected_value, nps_source_hash, ridb_source_hash,
          review_note, evidence_url, reviewer_user_id, reviewed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $park, $id, $target['ridb_id'], $field, $decision, $selected,
        $target['nps_hash'], $target['ridb_hash'], $note,
        $url !== '' ? $url : null, $adminId,
    ]);
}

$error = '';
$rows = [];
$reviewMessage = '';
$reviewError = '';
$reviewReady = false;
$reviewInstallNeeded = false;
$latestReviews = [];
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
            'ridb_hash' => hash('sha256', (string) $ridbItem['source_json']),
            'assessment' => $assessment,
        ];
    }
    try {
        $latestReviews = llama_official_review_latest($npsDb);
        $reviewReady = true;
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1146) {
            $reviewInstallNeeded = true;
        } else {
            throw $exception;
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (string) ($_POST['action'] ?? '') === 'save_field_review') {
        try {
            if (!$reviewReady) {
                throw new RuntimeException('Install the field review table in the NPS database first.');
            }
            if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Session expired. Reload before recording a review.');
            }
            llama_official_review_save($npsDb, $rows, $_POST, (int) ($adminUser['id'] ?? 0));
            $latestReviews = llama_official_review_latest($npsDb);
            $reviewMessage = 'Review decision recorded in the NPS audit ledger. Published Places were not changed.';
        } catch (Throwable $exception) {
            $reviewError = $exception->getMessage();
        }
    }
    llama_official_review_apply($rows, $latestReviews);
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
            'Review decision', 'Review status', 'Reviewed candidate (not published)',
            'Review note', 'Evidence URL', 'Reviewed at (UTC)',
        ]);
        foreach ($rows as $row) {
            foreach ($row['assessment'] as $field => $result) {
                [$label, $scope, $unit] = $labels[$field];
                $preview = llama_official_review_preview($result);
                $review = $result['human_review'] ?? null;
                $reviewCurrent = $review !== null && empty($result['review_stale']);
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
                    $review['decision'] ?? '',
                    $review === null ? 'Not reviewed' : ($reviewCurrent ? 'Current' : 'Stale — re-review'),
                    $reviewCurrent ? ($review['selected_value'] ?? '') : '',
                    $review['review_note'] ?? '', $review['evidence_url'] ?? '',
                    $review['reviewed_at'] ?? '',
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

function llama_official_review_pending(array $result): bool
{
    if (!llama_official_review_attention_state((string) ($result['state'] ?? ''))) {
        return false;
    }
    $review = $result['human_review'] ?? null;
    return $review === null || !empty($result['review_stale'])
        || (string) $review['decision'] === 'unresolved';
}

$view = (string) ($_GET['view'] ?? 'attention');
if (!in_array($view, ['attention', 'reviewed', 'all'], true)) {
    $view = 'attention';
}

$attentionCount = 0;
$reviewedCount = 0;
$attentionCampgrounds = 0;
$totalComparisons = 0;
foreach ($rows as $row) {
    $hasAttention = false;
    foreach ($row['assessment'] as $result) {
        $totalComparisons++;
        if (llama_official_review_pending($result)) {
            $attentionCount++;
            $hasAttention = true;
        } elseif (($result['human_review'] ?? null) !== null
            && empty($result['review_stale'])
            && ($result['human_review']['decision'] ?? '') !== 'unresolved') {
            $reviewedCount++;
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
            <strong>How to use this page:</strong> Review disputed facts, inspect
            the source evidence, and record an evidence-based decision when you
            can verify an answer. You can choose NPS, RIDB, enter a verified value,
            or keep the question unresolved. Start with “Needs attention.”
        </p>
        <p>
            <strong>When sources disagree, the proposed value remains blank.</strong>
            Their original values stay in their own source databases.
            A reviewed value is an <strong>audited candidate only</strong>:
            nothing here changes a published Place or a Scout contribution.
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

<?php if ($reviewInstallNeeded || $reviewMessage !== '' || $reviewError !== ''): ?>
<section class="admin-panel">
    <div class="admin-panel-body">
        <?php if ($reviewInstallNeeded): ?>
            <p><strong>Field-review ledger is not installed.</strong>
            The comparisons still work. Create <code>nps_field_review_events</code>
            in your NPS database to enable manual decisions.</p>
        <?php endif; ?>
        <?php if ($reviewMessage !== ''): ?>
            <p role="status"><?= moderation_e($reviewMessage) ?></p>
        <?php endif; ?>
        <?php if ($reviewError !== ''): ?>
            <div class="admin-user-notice is-error" role="alert">
                <?= moderation_e($reviewError) ?>
            </div>
        <?php endif; ?>
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
            <strong><?= number_format($attentionCount) ?> facts still needing attention</strong>
            across <?= number_format($attentionCampgrounds) ?> campgrounds;
            <?= number_format($reviewedCount) ?> reviewed decisions are current.
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
            <a class="admin-button<?= $view === 'reviewed' ? '' : ' is-secondary' ?>"
                href="/nps-field-reconciliation.php?view=reviewed"
                <?= $view === 'reviewed' ? 'aria-current="page"' : '' ?>>
                Reviewed (<?= number_format($reviewedCount) ?>)
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
            if ($view === 'all'
                || ($view === 'attention' && llama_official_review_pending($result))
                || ($view === 'reviewed'
                    && ($result['human_review'] ?? null) !== null
                    && empty($result['review_stale'])
                    && ($result['human_review']['decision'] ?? '') !== 'unresolved')) {
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
                                <p><strong>Automatic candidate (before human review):</strong>
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
                                <?php if ($field === 'location'): ?>
                                    <?php foreach (['nps' => 'Inspect NPS location on map', 'ridb' => 'Inspect RIDB location on map'] as $provider => $mapLabel): ?>
                                        <?php $mapUrl = llama_official_review_map_url(
                                            llama_official_review_raw_value($result, $provider)
                                        ); ?>
                                        <?php if ($mapUrl !== null): ?>
                                            <p><a href="<?= moderation_e($mapUrl) ?>"
                                                target="_blank" rel="noopener noreferrer">
                                                <?= moderation_e($mapLabel) ?></a></p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php $review = $result['human_review'] ?? null; ?>
                                <?php if ($review !== null): ?>
                                    <p><strong>Latest human review:</strong>
                                        <?= !empty($result['review_stale'])
                                            ? 'Source changed — decision requires a new review'
                                            : moderation_e(match ($review['decision']) {
                                                'use_nps' => 'NPS evidence selected',
                                                'use_ridb' => 'RIDB evidence selected',
                                                'verified_value' => 'Independently verified value',
                                                default => 'Left unverified',
                                            }) ?>.
                                        <?php if (empty($result['review_stale']) && $review['selected_value'] !== null): ?>
                                            Candidate: <strong><?= moderation_e($review['selected_value']) ?></strong>
                                            (not published).
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Reason:</strong> <?= moderation_e($review['review_note']) ?></p>
                                    <?php if ((string) ($review['evidence_url'] ?? '') !== ''): ?>
                                        <p><a href="<?= moderation_e($review['evidence_url']) ?>"
                                            target="_blank" rel="noopener noreferrer">Supporting evidence</a></p>
                                    <?php endif; ?>
                                    <p>Reviewed: <?= moderation_e($review['reviewed_at']) ?> UTC</p>
                                <?php endif; ?>
                                <?php if ($reviewReady && llama_official_review_attention_state((string) $result['state'])): ?>
                                    <details>
                                        <summary><?= $review !== null ? 'Re-review this fact' : 'Review this fact' ?></summary>
                                        <form class="admin-form" method="post" action="/nps-field-reconciliation.php?view=attention">
                                            <input type="hidden" name="action" value="save_field_review">
                                            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                                            <input type="hidden" name="nps_park_code" value="<?= moderation_e($row['park']) ?>">
                                            <input type="hidden" name="nps_record_id" value="<?= moderation_e($row['nps_id']) ?>">
                                            <input type="hidden" name="field_key" value="<?= moderation_e($field) ?>">
                                            <input type="hidden" name="nps_source_hash" value="<?= moderation_e($row['nps_hash']) ?>">
                                            <input type="hidden" name="ridb_source_hash" value="<?= moderation_e($row['ridb_hash']) ?>">
                                            <label>
                                                <span>Review decision</span>
                                                <select name="decision" required>
                                                    <option value="">Select a decision</option>
                                                    <?php if (llama_official_review_raw_value($result, 'nps') !== null
                                                        && $result['state'] !== 'invalid_source'): ?>
                                                        <option value="use_nps">Use the NPS value</option>
                                                    <?php endif; ?>
                                                    <?php if (llama_official_review_raw_value($result, 'ridb') !== null): ?>
                                                        <option value="use_ridb">Use the RIDB value</option>
                                                    <?php endif; ?>
                                                    <option value="verified_value">Enter a separately verified value</option>
                                                    <option value="unresolved">Keep unverified</option>
                                                </select>
                                            </label>
                                            <label>
                                                <span>Verified value (only if using that decision)</span>
                                                <input name="verified_value" type="text" maxlength="255"
                                                    placeholder="<?= moderation_e($field === 'location'
                                                        ? 'Latitude,longitude (e.g. 36.000000,-112.000000)'
                                                        : ($field === 'regular_nightly_fee_usd' ? 'Amount in USD' : 'Whole number')) ?>">
                                            </label>
                                            <label>
                                                <span>Explanation and supporting facts (required)</span>
                                                <textarea name="review_note" rows="3" maxlength="2000" required
                                                    placeholder="Explain what changed and which evidence supports this choice."></textarea>
                                            </label>
                                            <label>
                                                <span>Supporting HTTPS link (optional)</span>
                                                <input name="evidence_url" type="url" maxlength="2048"
                                                    placeholder="https://...">
                                            </label>
                                            <button class="admin-button" type="submit">Record review decision</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <?php if (llama_official_review_attention_state((string) $result['state'])): ?>
                                    <p><strong>Why the automatic value is blank:</strong>
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
            Each reviewed choice stays in the NPS review ledger. The CSV includes
            decisions, explanations, timestamps, and stale-review warnings.
            The source-to-Place integration will later use only current, approved
            values and will protect Scout answers and original official records.
        </p>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

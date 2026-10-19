<?php

declare(strict_types=1);

/**
 * Issue #6 / Phase 1B: Read-only NPS ↔ RIDB identity audit.
 *
 * Comparison is read-only; one explicit admin-only action can cache an
 * official RIDB facility in the dedicated RIDB source database.
 * No Place writes, auto-linking, or publishing. Identity candidates are
 * NEVER approved automatically.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/nps.php';
require_once dirname(__DIR__) . '/app/ridb.php';

$adminUser = moderation_require_admin();

/** Preserve source identity and normalize only for comparison. */
function llama_nps_match_words(string $name): array
{
    $ascii = function_exists('iconv')
        ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)
        : false;
    $normalized = strtolower($ascii !== false ? $ascii : $name);
    $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);
    $parts = array_filter(explode(' ', trim($normalized)));
    $unimportant = [
        'campground' => true, 'campgrounds' => true, 'camping' => true,
        'campsite' => true, 'campsites' => true, 'park' => true,
        'national' => true, 'rv' => true, 'primitive' => true,
        'reservations' => true, 'required' => true,
    ];

    return array_values(array_unique(array_filter(
        $parts,
        static fn (string $word): bool => !isset($unimportant[$word])
    )));
}

function llama_nps_match_name_similarity(string $left, string $right): float
{
    $a = llama_nps_match_words($left);
    $b = llama_nps_match_words($right);
    if (!$a || !$b) {
        return 0.0;
    }

    $overlap = count(array_intersect($a, $b));
    if ($overlap === 0) {
        return 0.0;
    }

    $precision = $overlap / count($a);
    $recall = $overlap / count($b);
    return (2.0 * $precision * $recall) / ($precision + $recall);
}

function llama_nps_match_distance_miles(
    ?float $latA,
    ?float $lonA,
    ?float $latB,
    ?float $lonB
): ?float {
    if ($latA === null || $lonA === null || $latB === null || $lonB === null) {
        return null;
    }

    $a = deg2rad($latA);
    $b = deg2rad($latB);
    $latDifference = deg2rad($latB - $latA);
    $lonDifference = deg2rad($lonB - $lonA);
    $h = sin($latDifference / 2) ** 2
        + cos($a) * cos($b) * sin($lonDifference / 2) ** 2;

    return 3958.7613 * 2.0 * asin(min(1.0, sqrt(max(0.0, $h))));
}

function llama_nps_match_float(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $float = (float) $value;
    return is_finite($float) ? $float : null;
}

/** Recreation.gov facility ID is a candidate key, not proof of identity. */
function llama_nps_match_ridb_id(array $record): string
{
    $url = (string) ($record['reservationUrl'] ?? '');
    if (preg_match('~^https://(?:www\.)?recreation\.gov/camping/campgrounds/([0-9]+)(?:[/?#]|$)~i', $url, $m)) {
        return $m[1];
    }
    return '';
}

function llama_nps_match_category(
    bool $sameId,
    float $nameScore,
    ?float $distance
): array {
    // The source IDs can coincide while denoting different things. Never
    // auto-link; contradicting coordinates or names remain visible.
    if ($sameId && $distance !== null && $distance > 25.0) {
        return ['Identifier conflict', 0, 'Same reservation ID, but coordinates differ by over 25 miles'];
    }
    if ($sameId && $distance !== null && $distance > 5.0) {
        return ['Identifier needs review', 65, 'Matching reservation ID, but coordinates are more than 5 miles apart'];
    }
    if ($sameId && $distance !== null && $nameScore >= 0.4) {
        return ['Strong candidate — review', 100, 'Recreation.gov ID, name, and coordinates agree'];
    }
    if ($sameId && $distance === null && $nameScore >= 0.6) {
        return ['Strong candidate — review', 95, 'Recreation.gov ID and name agree; coordinates unavailable'];
    }
    if ($sameId) {
        return ['Identifier needs review', 65, 'Reservation ID matches; name or position needs checking'];
    }
    if ($distance !== null && $distance <= 1.5 && $nameScore >= 0.7) {
        return ['Possible match — review', 85, 'Similar name within 1.5 miles'];
    }
    if ($distance !== null && $distance <= 5.0 && $nameScore >= 0.5) {
        return ['Possible match — review', 72, 'Name overlap within 5 miles'];
    }
    if ($distance !== null && $distance <= 0.3 && $nameScore >= 0.25) {
        return ['Possible match — review', 60, 'Very close coordinates but different name'];
    }
    return ['Uncertain', 0, 'Not enough agreement for a proposed link'];
}

/** Keep the exported report safe when opened in spreadsheet applications. */
function llama_nps_match_csv_safe(mixed $value): string
{
    $cell = (string) ($value ?? '');
    // A leading Unicode BOM, whitespace, control character, or formula
    // prefix must never make untrusted source data executable as a formula.
    if (preg_match('/^[\x00-\x20\x{FEFF}]*[=+@\-]/u', $cell)) {
        return "'" . $cell;
    }
    return $cell;
}

function llama_nps_match_first_search_token(string $name): string
{
    $words = llama_nps_match_words($name);
    foreach ($words as $word) {
        if (strlen($word) >= 4) {
            return substr($word, 0, 4);
        }
    }
    return '';
}

/** Limit per-source cache reads; queries only known RIDB schema columns. */
function llama_nps_match_candidates(
    PDO $ridb,
    array $npsRecord,
    string $recordName
): array {
    $latitude = llama_nps_match_float($npsRecord['latitude'] ?? null);
    $longitude = llama_nps_match_float($npsRecord['longitude'] ?? null);
    $facilityId = llama_nps_match_ridb_id($npsRecord);
    $candidateRows = [];

    $addRows = static function (PDOStatement $statement) use (&$candidateRows): void {
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $candidateRows[(string) $row['ridb_facility_id']] = $row;
        }
    };

    if ($facilityId !== '') {
        $statement = $ridb->prepare(
            'SELECT ridb_facility_id, facility_name, latitude, longitude
               FROM ridb_facilities
              WHERE ridb_facility_id = ? LIMIT 1'
        );
        $statement->execute([$facilityId]);
        $addRows($statement);
    }

    $token = llama_nps_match_first_search_token($recordName);
    if ($token !== '') {
        $statement = $ridb->prepare(
            'SELECT ridb_facility_id, facility_name, latitude, longitude
               FROM ridb_facilities
              WHERE facility_name LIKE ? LIMIT 80'
        );
        $statement->execute([$token . '%']);
        $addRows($statement);
    }

    if ($latitude !== null && $longitude !== null) {
        // A small geographic range helps locate official records under
        // alternate names. Bounds are conservative and review-only.
        $statement = $ridb->prepare(
            'SELECT ridb_facility_id, facility_name, latitude, longitude
               FROM ridb_facilities
              WHERE latitude BETWEEN ? AND ?
                AND longitude BETWEEN ? AND ? LIMIT 120'
        );
        $statement->execute([
            $latitude - 0.15, $latitude + 0.15,
            $longitude - 0.18, $longitude + 0.18,
        ]);
        $addRows($statement);
    }

    $ranked = [];
    foreach ($candidateRows as $row) {
        $ridbId = (string) $row['ridb_facility_id'];
        $otherName = (string) $row['facility_name'];
        $distance = llama_nps_match_distance_miles(
            $latitude,
            $longitude,
            llama_nps_match_float($row['latitude'] ?? null),
            llama_nps_match_float($row['longitude'] ?? null)
        );
        $nameScore = llama_nps_match_name_similarity($recordName, $otherName);
        [$category, $rank, $reason] = llama_nps_match_category(
            $facilityId !== '' && $facilityId === $ridbId,
            $nameScore,
            $distance
        );
        if ($category === 'Uncertain') {
            continue;
        }
        $ranked[] = [
            'ridb_id' => $ridbId,
            'ridb_name' => $otherName,
            'distance' => $distance,
            'category' => $category,
            'rank' => $rank,
            'reason' => $reason,
            'name_agreement' => $nameScore,
        ];
    }

    usort($ranked, static function (array $a, array $b): int {
        $byRank = $b['rank'] <=> $a['rank'];
        return $byRank !== 0 ? $byRank : strcmp($a['ridb_id'], $b['ridb_id']);
    });

    // When official IDs agree, nearby but unrelated campgrounds/visitor
    // centers must not appear as competing source-identity candidates.
    if ($facilityId !== '') {
        foreach ($ranked as $candidate) {
            if ($candidate['ridb_id'] === $facilityId
                && $candidate['category'] === 'Strong candidate — review') {
                return [$candidate];
            }
        }
    }
    return array_slice($ranked, 0, 3);
}

/** Existing Place links are read only; they are not proof NPS is linked. */
function llama_nps_match_existing_places(PDO $main, array $ridbIds): array
{
    $ridbIds = array_values(array_unique(array_filter($ridbIds)));
    if (!$ridbIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ridbIds), '?'));
    $statement = $main->prepare(
        'SELECT s.source_external_id, p.id AS place_id, p.name AS place_name,
                p.slug AS place_slug
           FROM place_external_sources s
           JOIN places p ON p.id = s.place_id
          WHERE s.source_provider = \'ridb\'
            AND s.source_external_id IN (' . $placeholders . ')'
    );
    $statement->execute($ridbIds);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $result[(string) $row['source_external_id']] = $row;
    }
    return $result;
}

function llama_nps_match_audit(PDO $nps, PDO $ridb, ?PDO $main): array
{
    $stmt = $nps->query(
        "SELECT record_id, park_code, record_name, source_url, source_json,
                  source_hash
           FROM nps_raw_records
          WHERE resource_type = 'campgrounds'
          ORDER BY park_code, record_name
          LIMIT 100"
    );

    $rows = [];
    $seen = 0;
    $allIds = [];
    while (($item = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $json = json_decode((string) $item['source_json'], true);
        if (!is_array($json)) {
            continue;
        }
        $seen++;
        $name = trim((string) ($json['name'] ?? $item['record_name']));
        try {
            $matches = llama_nps_match_candidates($ridb, $json, $name);
        } catch (Throwable $exception) {
            // Include the source record being compared so failures are
            // actionable without exposing credentials or raw API responses.
            throw new RuntimeException(
                'RIDB candidate lookup failed for NPS record '
                . (string) $item['record_id'] . ' (' . $name . '): '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
        foreach ($matches as $candidate) {
            $allIds[] = $candidate['ridb_id'];
        }
        $rows[] = [
            'park' => (string) $item['park_code'],
            'nps_id' => (string) $item['record_id'],
            'nps_source_hash' => (string) $item['source_hash'],
            'name' => $name,
            'nps_url' => (string) ($item['source_url'] ?? ''),
            'reservation_id' => llama_nps_match_ridb_id($json),
            'nps_total_sites' => (string) ($json['campsites']['totalSites'] ?? ''),
            'nps_reservable_sites' => (string) ($json['numberOfSitesReservable'] ?? ''),
            'nps_rv_max_ft' => (string) ($json['accessibility']['rvMaxLength'] ?? ''),
            'matches' => $matches,
        ];
    }

    $links = [];
    $linkNotice = '';
    if ($main instanceof PDO && $allIds) {
        try {
            $links = llama_nps_match_existing_places($main, $allIds);
        } catch (Throwable) {
            $linkNotice = 'Published Place link lookup is unavailable. Matching still works without it.';
        }
    }

    foreach ($rows as &$row) {
        foreach ($row['matches'] as &$candidate) {
            $candidate['linked_place'] = $links[$candidate['ridb_id']] ?? null;
        }
        unset($candidate);
    }
    unset($row);

    return ['rows' => $rows, 'checked' => $seen, 'link_notice' => $linkNotice];
}

/**
 * Identify source IDs found in NPS reservation links but not yet available
 * in the local RIDB facility cache. No fuzzy matching is used here.
 */
function llama_nps_match_uncached_ridb_ids(PDO $ridb, array $audit): array
{
    $check = $ridb->prepare(
        'SELECT 1 FROM ridb_facilities WHERE ridb_facility_id = ? LIMIT 1'
    );
    $missing = [];
    foreach ((array) ($audit['rows'] ?? []) as $row) {
        $id = trim((string) ($row['reservation_id'] ?? ''));
        if (!preg_match('/^[0-9]{1,16}$/D', $id) || isset($missing[$id])) {
            continue;
        }
        $check->execute([$id]);
        if ($check->fetchColumn() === false) {
            $missing[$id] = (string) ($row['name'] ?? 'Unnamed NPS campground');
        }
    }
    return $missing;
}

/**
 * Review events are append-only. The latest event per park and NPS record
 * governs its review state; source records and published Places are untouched.
 */
function llama_nps_match_review_history(PDO $nps, array $audit): array
{
    // Bound the review lookup to the campground records currently on screen.
    // The complete historical log can grow without slowing each page load.
    $where = [];
    $parameters = [];
    foreach ($audit['rows'] as $row) {
        $where[] = '(nps_park_code = ? AND nps_record_id = ?)';
        $parameters[] = (string) $row['park'];
        $parameters[] = (string) $row['nps_id'];
    }
    if (!$where) {
        return [];
    }
    $statement = $nps->prepare(
        'SELECT id, nps_park_code, nps_record_id, decision, ridb_facility_id,
                nps_source_hash, ridb_name_at_review, reviewer_user_id,
                review_note, reviewed_at
           FROM nps_ridb_review_events
          WHERE ' . implode(' OR ', $where) . '
          ORDER BY id DESC'
    );
    $statement->execute($parameters);
    $latest = [];
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $key = (string) $row['nps_park_code'] . ':' . (string) $row['nps_record_id'];
        if (!isset($latest[$key])) {
            $latest[$key] = $row;
        }
    }
    return $latest;
}

/**
 * A unique exact Recreation.gov identifier is required for pair confirmation.
 * Fuzzy suggestions can never produce a permanent source-identity decision.
 */
function llama_nps_match_confirmable(array $row): ?array
{
    $reservationId = (string) ($row['reservation_id'] ?? '');
    if ($reservationId === '') {
        return null;
    }
    foreach ($row['matches'] as $candidate) {
        if ((string) $candidate['ridb_id'] !== $reservationId
            || (string) $candidate['category'] !== 'Strong candidate — review') {
            continue;
        }
        return $candidate;
    }
    return null;
}

function llama_nps_match_review_write(
    PDO $nps,
    PDO $ridb,
    array $audit,
    array $existing,
    string $action,
    string $park,
    string $id,
    string $notes,
    int $reviewer
): void {
    if (!in_array($action, ['confirm_pair', 'nps_only_reviewed', 'reopen_review'], true)) {
        throw new InvalidArgumentException('Unknown review action.');
    }
    $target = null;
    foreach ($audit['rows'] as $row) {
        if ($row['park'] === $park && $row['nps_id'] === $id) {
            $target = $row;
            break;
        }
    }
    if ($target === null) {
        throw new RuntimeException('That NPS campground is not in the current comparison.');
    }
    $decision = $action === 'confirm_pair' ? 'confirmed_match'
        : ($action === 'nps_only_reviewed' ? 'nps_only_reviewed' : 'unresolved');
    $ridbId = null;
    $ridbName = null;
    if ($action === 'confirm_pair') {
        $candidate = llama_nps_match_confirmable($target);
        if ($candidate === null) {
            throw new RuntimeException(
                'No verified exact-ID candidate is available. Do not confirm a nearby facility.'
            );
        }
        $ridbId = (string) $candidate['ridb_id'];
        $ridbName = (string) $candidate['ridb_name'];
        // Check the latest review for *every* NPS record, not merely the
        // currently displayed pilot parks. An older superseded review does
        // not reserve its former RIDB facility.
        $conflicts = $nps->prepare(
            'SELECT e.nps_park_code, e.nps_record_id
               FROM nps_ridb_review_events e
          LEFT JOIN nps_ridb_review_events later
                 ON later.nps_park_code = e.nps_park_code
                AND later.nps_record_id = e.nps_record_id
                AND later.id > e.id
              WHERE e.ridb_facility_id = ?
                AND e.decision = ?
                AND later.id IS NULL'
        );
        $conflicts->execute([$ridbId, 'confirmed_match']);
        foreach ($conflicts->fetchAll(PDO::FETCH_ASSOC) ?: [] as $link) {
            if ((string) $link['nps_record_id'] !== $id) {
                throw new RuntimeException(
                    'This RIDB facility has already been confirmed for a different NPS record.'
                );
            }
        }
        $check = $ridb->prepare(
            'SELECT facility_name FROM ridb_facilities WHERE ridb_facility_id = ? LIMIT 1'
        );
        $check->execute([$ridbId]);
        $name = $check->fetchColumn();
        if ($name === false) {
            throw new RuntimeException('The RIDB facility is no longer in the source cache.');
        }
        $ridbName = (string) $name;
    }
    if ($action === 'nps_only_reviewed' && $target['reservation_id'] !== '') {
        throw new RuntimeException(
            'An official Recreation.gov ID is present. Investigate it before declaring no RIDB match.'
        );
    }
    $notes = trim($notes);
    if (strlen($notes) > 1000) {
        throw new InvalidArgumentException('Review notes must be 1,000 bytes or shorter.');
    }
    if ($action === 'nps_only_reviewed' && $notes === '') {
        throw new InvalidArgumentException('Explain what was checked before marking an NPS-only record.');
    }
    $nps->prepare(
        'INSERT INTO nps_ridb_review_events
         (nps_park_code, nps_record_id, decision, ridb_facility_id,
          nps_source_hash, ridb_name_at_review, reviewer_user_id, review_note,
          reviewed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([
        $park, $id, $decision, $ridbId,
        (string) $target['nps_source_hash'], $ridbName, $reviewer, $notes,
    ]);
}

function llama_nps_match_apply_reviews(array &$data, array $reviews): void
{
    foreach ($data['rows'] as &$row) {
        $key = $row['park'] . ':' . $row['nps_id'];
        $row['review'] = $reviews[$key] ?? null;
        $row['confirmable'] = llama_nps_match_confirmable($row);
        $row['review_stale'] = $row['review'] !== null
            && (string) $row['review']['nps_source_hash'] !== $row['nps_source_hash'];
    }
    unset($row);
}

/**
 * Export the original RIDB facility JSON for manually reviewed, current
 * NPS/RIDB identity pairs. This does not fetch external APIs or change data.
 * The source cache is authoritative for the value being exported; matching
 * here only determines which record we are permitted to compare.
 */
function llama_nps_match_reviewed_ridb_source_export(
    PDO $ridb,
    array $rows
): array {
    $statement = $ridb->prepare(
        'SELECT ridb_facility_id, facility_name, facility_type_description,
                latitude, longitude, last_updated_date, source_json
           FROM ridb_facilities
          WHERE ridb_facility_id = ? LIMIT 1'
    );

    $records = [];
    $skipped = [];
    $confirmed = 0;
    $usedRidbIds = [];
    foreach ($rows as $row) {
        $review = $row['review'] ?? null;
        if (!is_array($review) || ($review['decision'] ?? '') !== 'confirmed_match') {
            continue;
        }
        $confirmed++;
        $npsIdentity = (string) ($row['park'] ?? '') . ':'
            . (string) ($row['nps_id'] ?? '');
        if (!empty($row['review_stale'])) {
            $skipped[] = [
                'nps_identity' => $npsIdentity,
                'reason' => 'NPS source changed since identity confirmation',
            ];
            continue;
        }
        $ridbId = trim((string) ($review['ridb_facility_id'] ?? ''));
        $verifiedCandidate = $row['confirmable'] ?? null;
        if ($ridbId === '' || !is_array($verifiedCandidate)
            || (string) $verifiedCandidate['ridb_id'] !== $ridbId) {
            $skipped[] = [
                'nps_identity' => $npsIdentity,
                'reason' => 'Reviewed RIDB identifier no longer matches a current exact-ID candidate',
            ];
            continue;
        }
        if (isset($usedRidbIds[$ridbId])) {
            $skipped[] = [
                'nps_identity' => $npsIdentity,
                'reason' => 'Duplicate reviewed RIDB identity in export',
            ];
            continue;
        }
        $usedRidbIds[$ridbId] = true;

        $statement->execute([$ridbId]);
        $source = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($source)) {
            $skipped[] = [
                'nps_identity' => $npsIdentity,
                'reason' => 'RIDB facility no longer available in the source cache',
            ];
            continue;
        }
        try {
            $raw = json_decode(
                (string) ($source['source_json'] ?? ''),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            $raw = null;
        }
        if (!is_array($raw) || array_is_list($raw)) {
            $skipped[] = [
                'nps_identity' => $npsIdentity,
                'reason' => 'RIDB facility source JSON is unavailable or invalid',
            ];
            continue;
        }
        $records[] = [
            'nps_identity' => [
                'park_code' => (string) $row['park'],
                'record_id' => (string) $row['nps_id'],
                'campground_name' => (string) $row['name'],
                'source_hash_at_review' => (string) $review['nps_source_hash'],
                'reviewed_at_utc' => (string) $review['reviewed_at'],
            ],
            'ridb_identity' => [
                'facility_id' => (string) $source['ridb_facility_id'],
                'facility_name' => (string) $source['facility_name'],
                'facility_type' => (string) $source['facility_type_description'],
                'latitude' => $source['latitude'],
                'longitude' => $source['longitude'],
                'last_updated_at_source' => $source['last_updated_date'],
            ],
            'ridb_original_record' => $raw,
        ];
    }
    return [
        'description' => 'Read-only raw RIDB facility records for confirmed NPS/RIDB identity pairs. No Llama Scout Place links are inferred.',
        'generated_at_utc' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'reviewed_confirmed_records' => $confirmed,
        'exported_facilities' => count($records),
        'skipped_facilities' => count($skipped),
        'records' => $records,
        'skipped' => $skipped,
    ];
}

$error = '';
$ridbError = '';
$npsError = '';
$sourceNotice = '';
$reviewNotice = '';
$reviewInstallNotice = '';
$reviewReady = false;
$reviewHistory = [];
$missingFacilityIds = [];
$data = ['rows' => [], 'checked' => 0, 'link_notice' => ''];
$ridbReady = false;
$npsReady = false;

try {
    $nps = nps_db();
    $nps->query(
        'SELECT record_id, park_code, record_name, source_url, source_json
         FROM nps_raw_records LIMIT 1'
    );
    $npsReady = true;
} catch (Throwable $exception) {
    $npsError = 'NPS source cache check failed: ' . $exception->getMessage();
}

try {
    $ridb = ridb_db();
    $ridb->query(
        'SELECT ridb_facility_id, facility_name, latitude, longitude
         FROM ridb_facilities LIMIT 1'
    );
    $ridbReady = true;
} catch (Throwable $exception) {
    $ridbError = 'RIDB facility cache check failed: ' . $exception->getMessage();
}

if ($npsReady && $ridbReady) {
    try {
        // Primary database is optional to this source-to-source comparison.
        $main = null;
        try {
            $main = db();
        } catch (Throwable) {
            // The RIDB/NPS comparison remains available independently.
        }
        $data = llama_nps_match_audit($nps, $ridb, $main);
        $missingFacilityIds = llama_nps_match_uncached_ridb_ids($ridb, $data);

        try {
            $reviewHistory = llama_nps_match_review_history($nps, $data);
            $reviewReady = true;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1146) {
                throw $exception;
            }
            $reviewInstallNotice = 'Source-identity review is unavailable until the NPS review table SQL is installed.';
        }

        if ($reviewReady
            && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && in_array((string) ($_POST['action'] ?? ''),
                ['confirm_pair', 'nps_only_reviewed', 'reopen_review'], true)) {
            if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Your session token expired. Reload and try again.');
            }
            $park = trim((string) ($_POST['nps_park_code'] ?? ''));
            $id = trim((string) ($_POST['nps_record_id'] ?? ''));
            $reviewAction = (string) $_POST['action'];
            llama_nps_match_review_write(
                $nps, $ridb, $data, $reviewHistory, $reviewAction,
                $park, $id, (string) ($_POST['review_note'] ?? ''),
                (int) ($adminUser['id'] ?? 0)
            );
            $reviewHistory = llama_nps_match_review_history($nps, $data);
            $reviewNotice = 'Review decision saved in the NPS audit trail. No Llama Scout Place was changed.';
        }
        llama_nps_match_apply_reviews($data, $reviewHistory);

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['action'] ?? '') === 'fetch_ridb_facility'
        ) {
            if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Your session token expired. Reload and try again.');
            }
            $requestedId = trim((string) ($_POST['ridb_facility_id'] ?? ''));
            if (!array_key_exists($requestedId, $missingFacilityIds)) {
                throw new InvalidArgumentException(
                    'Choose a Recreation.gov facility ID from the uncached list.'
                );
            }

            // This admin-triggered request reads a single official facility.
            // It writes ONLY to the existing dedicated RIDB source cache.
            // Published Places, campsite records, Scout facts, and source
            // linkages are intentionally not modified.
            $response = llama_ridb_facility($requestedId);
            $record = llama_ridb_response_record($response);
            $returnedId = trim((string) llama_ridb_record_value(
                $record, ['FacilityID', 'facilityID', 'facility_id'], ''
            ));
            if ($returnedId !== $requestedId) {
                throw new RuntimeException(
                    'RIDB did not return the requested facility ID. Nothing was cached.'
                );
            }
            $count = llama_ridb_store_facilities($ridb, [$record]);
            if ($count !== 1) {
                throw new RuntimeException(
                    'RIDB did not confirm the facility cache update.'
                );
            }
            $sourceNotice = 'RIDB facility ' . $requestedId
                . ' was added to the source cache. No Place was created or modified.';
            // Re-evaluate the report using the newly cached official record.
            $data = llama_nps_match_audit($nps, $ridb, $main);
            $missingFacilityIds = llama_nps_match_uncached_ridb_ids($ridb, $data);
            llama_nps_match_apply_reviews($data, $reviewHistory);
        }
    } catch (Throwable $exception) {
        // Admin-only page. Show the actual failed operation rather than a
        // misleading blanket assertion that the schemas are missing.
        $error = 'The source comparison failed: ' . $exception->getMessage();
    }
}

// The NPS source export was already supplied for Phase 1B. Exporting the
// paired original RIDB records completes the source-to-source evidence set.
// This endpoint remains owner/admin-only through moderation_require_admin().
if (($_GET['format'] ?? '') === 'ridb-source-json'
    && $error === '' && $npsReady && $ridbReady && $reviewReady) {
    try {
        $payload = llama_nps_match_reviewed_ridb_source_export(
            $ridb,
            (array) ($data['rows'] ?? [])
        );
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="nps-reviewed-ridb-sources.json"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    } catch (Throwable $exception) {
        $error = 'Reviewed RIDB source export failed: ' . $exception->getMessage();
    }
}

if (($_GET['format'] ?? '') === 'csv' && !$error && $npsReady && $ridbReady) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nps-ridb-match-audit.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    if ($out !== false) {
        fputcsv($out, [
            'NPS park code', 'NPS campground', 'NPS record ID', 'NPS source URL',
            'Recreation.gov campground ID', 'NPS total sites', 'NPS reservable sites',
            'NPS RV limit (ft)', 'RIDB facility ID', 'RIDB facility name',
            'Distance (miles)', 'Name agreement', 'Assessment', 'Reason',
            'Linked Llama Scout Place ID', 'Linked Llama Scout Place name',
            'Reviewed identity decision', 'Reviewed RIDB ID',
            'NPS source changed since review', 'Review notes', 'Reviewed UTC',
        ]);
        foreach ($data['rows'] as $row) {
            foreach ($row['matches'] ?: [[
                'ridb_id' => '', 'ridb_name' => '', 'distance' => null,
                'name_agreement' => 0, 'category' => 'No candidate in cached RIDB',
                'reason' => 'Does not establish that the facility is absent from RIDB',
                'linked_place' => null,
            ]] as $candidate) {
                $linked = (array) ($candidate['linked_place'] ?? []);
                fputcsv($out, array_map('llama_nps_match_csv_safe', [
                    $row['park'], $row['name'], $row['nps_id'], $row['nps_url'],
                    $row['reservation_id'], $row['nps_total_sites'],
                    $row['nps_reservable_sites'], $row['nps_rv_max_ft'],
                    $candidate['ridb_id'], $candidate['ridb_name'],
                    $candidate['distance'] === null ? '' : round($candidate['distance'], 2),
                    $candidate['name_agreement'], $candidate['category'],
                    $candidate['reason'], $linked['place_id'] ?? '',
                    $linked['place_name'] ?? '',
                    $row['review']['decision'] ?? 'unresolved',
                    $row['review']['ridb_facility_id'] ?? '',
                    !empty($row['review_stale']) ? 'Yes' : 'No',
                    $row['review']['review_note'] ?? '',
                    $row['review']['reviewed_at'] ?? '',
                ]));
            }
        }
        fclose($out);
    }
    exit;
}

$adminPageTitle = 'NPS / RIDB comparison';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';
require __DIR__ . '/_header.php';
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Issue #6 — Phase 1B</p>
            <h2>NPS / RIDB campground matching</h2>
        </div>
        <span class="admin-status-pill">Source comparison</span>
    </header>
    <p>
        Compare cached NPS campground records with RIDB facilities. Confirmed
        identities can also be exported with their original RIDB source JSON for
        field-level reconciliation. Names, coordinates, and Recreation.gov identifiers provide candidates only.
        You may cache one missing RIDB source record at a time for inspection.
        Reviewed identity decisions are stored in an append-only NPS audit log.
        Confirming an identity does not yet link or modify a published Place.
        No published Places are created, merged, approved, or modified here.
    </p>
    <div class="admin-user-form-actions">
        <a class="admin-button is-secondary" href="/nps.php">NPS source explorer</a>
        <a class="admin-button is-secondary" href="/ridb.php">RIDB control center</a>
        <?php if ($npsReady && $ridbReady && $error === ''): ?>
            <a class="admin-button" href="/nps-ridb-matches.php?format=csv">Export comparison CSV</a>
            <?php if ($reviewReady): ?>
                <a class="admin-button is-secondary" href="/nps-ridb-matches.php?format=ridb-source-json">Export confirmed RIDB sources (JSON)</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($reviewNotice !== ''): ?>
<section class="admin-panel"><div class="admin-user-notice" role="status">
    <?= moderation_e($reviewNotice) ?>
</div></section>
<?php endif; ?>

<?php if ($reviewInstallNotice !== ''): ?>
<section class="admin-panel"><div class="admin-user-notice is-error" role="status">
    <?= moderation_e($reviewInstallNotice) ?>
</div></section>
<?php endif; ?>

<?php if ($sourceNotice !== ''): ?>
<section class="admin-panel">
    <div class="admin-user-notice" role="status">
        <?= moderation_e($sourceNotice) ?>
    </div>
</section>
<?php endif; ?>

<?php foreach ([$error, $npsError, $ridbError, $data['link_notice']] as $notice): ?>
    <?php if ($notice !== ''): ?>
        <section class="admin-panel">
            <div class="admin-user-notice is-error" role="status">
                <?= moderation_e($notice) ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($npsReady && $ridbReady && $error === ''): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Cached source coverage</p>
            <h2><?= number_format($data['checked']) ?> NPS campground records compared</h2>
        </div>
    </header>
    <p>
        The comparison reads the existing RIDB source cache. Uncached
        Recreation.gov facility IDs can be checked individually against the
        official RIDB API using the controlled action below.
    </p>
    <?php if ($missingFacilityIds): ?>
        <form method="post" action="/nps-ridb-matches.php">
            <input type="hidden" name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="action" value="fetch_ridb_facility">
            <label>
                <span>Verify one uncached Recreation.gov facility ID</span>
                <select name="ridb_facility_id" required>
                    <?php foreach ($missingFacilityIds as $id => $name): ?>
                        <option value="<?= moderation_e((string) $id) ?>">
                            <?= moderation_e($name . ' — ' . $id) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="admin-user-form-actions">
                <button class="admin-button" type="submit">
                    Fetch one facility into RIDB source cache
                </button>
            </div>
            <p>
                Imports one official RIDB facility record into the dedicated
                RIDB source cache. Does not publish or link a Llama Scout Place.
                Repeat only for the IDs you want to inspect.
            </p>
        </form>
    <?php endif; ?>
    <?php if (!$data['rows']): ?>
        <div class="admin-empty-state">
            <p>No cached NPS campground records were found.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>NPS campground</th>
                        <th>NPS facts</th>
                        <th>RIDB candidate(s)</th>
                        <th>Existing Place link</th>
                        <th>Source-identity review</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td>
                            <strong><?= moderation_e($row['name']) ?></strong>
                            <p><?= moderation_e(strtoupper($row['park'])) ?>
                            · NPS ID: <?= moderation_e($row['nps_id']) ?></p>
                            <?php if (preg_match('~^https://~i', $row['nps_url'])): ?>
                                <a href="<?= moderation_e($row['nps_url']) ?>" target="_blank" rel="noopener noreferrer">NPS source</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <p>Sites: <?= moderation_e($row['nps_total_sites'] !== '' ? $row['nps_total_sites'] : 'Not provided') ?></p>
                            <p>Reservable: <?= moderation_e($row['nps_reservable_sites'] !== '' ? $row['nps_reservable_sites'] : 'Not provided') ?></p>
                            <p>RV limit: <?= moderation_e($row['nps_rv_max_ft'] !== '' ? $row['nps_rv_max_ft'] . ' ft' : 'Not provided') ?></p>
                        </td>
                        <td>
                            <?php if (!$row['matches']): ?>
                                <p>Not found in cached RIDB.</p>
                            <?php else: ?>
                                <?php foreach ($row['matches'] as $candidate): ?>
                                    <p>
                                        <strong><?= moderation_e($candidate['ridb_name']) ?></strong>
                                        (<?= moderation_e($candidate['ridb_id']) ?>)
                                    </p>
                                    <p><?= moderation_e($candidate['category']) ?></p>
                                    <p><?= moderation_e($candidate['reason']) ?></p>
                                    <?php if ($candidate['distance'] !== null): ?>
                                        <p>Distance: <?= number_format($candidate['distance'], 2) ?> miles</p>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $anyLink = false; ?>
                            <?php foreach ($row['matches'] as $candidate): ?>
                                <?php $linked = (array) ($candidate['linked_place'] ?? []); ?>
                                <?php if ($linked): $anyLink = true; ?>
                                    <p>
                                        <?= moderation_e($linked['place_name']) ?>
                                        (Place #<?= (int) $linked['place_id'] ?>)
                                    </p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!$anyLink): ?>
                                <p>No existing RIDB-linked Place identified.</p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $review = (array) ($row['review'] ?? []); ?>
                            <p><strong><?= moderation_e((string) ($review['decision'] ?? 'Unreviewed')) ?></strong></p>
                            <?php if (!empty($review['ridb_facility_id'])): ?>
                                <p>RIDB #<?= moderation_e((string) $review['ridb_facility_id']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($row['review_stale'])): ?>
                                <p><strong>NPS source changed since review. Recheck before use.</strong></p>
                            <?php endif; ?>
                            <?php if (!empty($review['review_note'])): ?>
                                <p><?= moderation_e((string) $review['review_note']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($review['reviewed_at'])): ?>
                                <p>Reviewed UTC: <?= moderation_e((string) $review['reviewed_at']) ?></p>
                            <?php endif; ?>
                            <?php if ($reviewReady): ?>
                                <?php if ($row['confirmable'] !== null
                                    && (($review['decision'] ?? '') !== 'confirmed_match'
                                        || !empty($row['review_stale']))): ?>
                                <form method="post" action="/nps-ridb-matches.php">
                                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="confirm_pair">
                                    <input type="hidden" name="nps_park_code" value="<?= moderation_e($row['park']) ?>">
                                    <input type="hidden" name="nps_record_id" value="<?= moderation_e($row['nps_id']) ?>">
                                    <p>Exact Recreation.gov ID: <?= moderation_e((string) $row['confirmable']['ridb_id']) ?></p>
                                    <button class="admin-button is-secondary" type="submit">Confirm same facility</button>
                                </form>
                                <?php elseif ($row['reservation_id'] === ''
                                    && (($review['decision'] ?? '') !== 'nps_only_reviewed'
                                        || !empty($row['review_stale']))): ?>
                                <form method="post" action="/nps-ridb-matches.php">
                                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="nps_only_reviewed">
                                    <input type="hidden" name="nps_park_code" value="<?= moderation_e($row['park']) ?>">
                                    <input type="hidden" name="nps_record_id" value="<?= moderation_e($row['nps_id']) ?>">
                                    <label><span>Investigation notes (optional)</span>
                                        <input name="review_note" maxlength="1000" required placeholder="Search performed or reason RIDB has no counterpart">
                                    </label>
                                    <button class="admin-button is-secondary" type="submit">Reviewed: no RIDB link identified</button>
                                </form>
                                <?php elseif (!$review): ?>
                                <p>Official facility ID not yet verified. Review the source records before confirmation.</p>
                                <?php endif; ?>
                                <?php if ($review): ?>
                                <form method="post" action="/nps-ridb-matches.php">
                                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="reopen_review">
                                    <input type="hidden" name="nps_park_code" value="<?= moderation_e($row['park']) ?>">
                                    <input type="hidden" name="nps_record_id" value="<?= moderation_e($row['nps_id']) ?>">
                                    <button class="admin-button is-muted" type="submit">Reopen for review</button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

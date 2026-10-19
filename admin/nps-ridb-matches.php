<?php

declare(strict_types=1);

/**
 * Issue #6 / Phase 1B: Read-only NPS ↔ RIDB identity audit.
 *
 * No writes, auto-linking, import, normalization, or published Place changes.
 * Identity candidates are NEVER approved automatically.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/nps.php';

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
        'national' => true, 'rv' => true, 'south' => true,
        'north' => true, 'rim' => true, 'primitive' => true,
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
    if ($sameId && $distance !== null && $distance <= 5.0 && $nameScore >= 0.4) {
        return ['Strong candidate — review', 100, 'Recreation.gov ID, name, and coordinates agree'];
    }
    if ($sameId && $nameScore >= 0.5) {
        return ['Strong candidate — review', 95, 'Recreation.gov ID and name agree'];
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
        "SELECT record_id, park_code, record_name, source_url, source_json
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

$error = '';
$ridbError = '';
$npsError = '';
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
    } catch (Throwable $exception) {
        // Admin-only page. Show the actual failed operation rather than a
        // misleading blanket assertion that the schemas are missing.
        $error = 'The source comparison failed: ' . $exception->getMessage();
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
        <span class="admin-status-pill">Read-only audit</span>
    </header>
    <p>
        Compare the NPS campground records already cached with existing RIDB
        facilities. Names, coordinates, and Recreation.gov identifiers provide
        candidates only. No records are merged, approved, or published here.
    </p>
    <div class="admin-user-form-actions">
        <a class="admin-button is-secondary" href="/nps.php">NPS source explorer</a>
        <a class="admin-button is-secondary" href="/ridb.php">RIDB control center</a>
        <?php if ($npsReady && $ridbReady && $error === ''): ?>
            <a class="admin-button" href="/nps-ridb-matches.php?format=csv">Export comparison CSV</a>
        <?php endif; ?>
    </div>
</section>

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
        The comparison uses only existing source-cache records. A missing RIDB
        candidate may mean that the facility has not been synchronized or that
        its name differs. This audit does not attempt live RIDB downloads.
    </p>
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
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

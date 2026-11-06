<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/nps-publication-readiness.php';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP: pdo_sqlite unavailable; NPS coverage database test\n";
    exit(0);
}
$create = static function (): PDO {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
};
$nps = $create();
$ridb = $create();
$main = $create();

$nps->exec('CREATE TABLE nps_raw_records (
    resource_type TEXT, park_code TEXT, record_id TEXT,
    record_name TEXT, source_hash TEXT
)');
$nps->exec('CREATE TABLE nps_ridb_review_events (
    id INTEGER PRIMARY KEY, nps_park_code TEXT, nps_record_id TEXT,
    ridb_facility_id TEXT, decision TEXT, nps_source_hash TEXT
)');
$nps->exec('CREATE TABLE nps_field_review_events (
    id INTEGER PRIMARY KEY, nps_park_code TEXT, nps_record_id TEXT,
    ridb_facility_id TEXT, field_key TEXT, decision TEXT,
    selected_value TEXT, nps_source_hash TEXT, ridb_source_hash TEXT,
    review_note TEXT, reviewed_at TEXT
)');
$ridb->exec('CREATE TABLE ridb_facilities (
    ridb_facility_id TEXT PRIMARY KEY, source_json TEXT
)');
$main->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT)');
$main->exec('CREATE TABLE place_external_sources (
    place_id INTEGER, source_provider TEXT, source_external_id TEXT
)');

$addNps = $nps->prepare('INSERT INTO nps_raw_records VALUES (?,?,?,?,?)');
$addReview = $nps->prepare('INSERT INTO nps_ridb_review_events VALUES (?,?,?,?,?,?)');
$addField = $nps->prepare(
    'INSERT INTO nps_field_review_events VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$addRidb = $ridb->prepare('INSERT INTO ridb_facilities VALUES (?,?)');
$addLink = $main->prepare('INSERT INTO place_external_sources VALUES (?,?,?)');

foreach ([
    ['yose', 'a', 'Alpha', '1', '101'], // Published + current field review
    ['yose', 'b', 'Bravo', '2', '102'], // Confirmed, RIDB facility not published
    ['yose', 'c', 'Charlie', '3', '103'], // Identity source hash is stale
    ['yose', 'd', 'Delta', '4', '104'], // Published but NPS linked elsewhere
] as [$park, $id, $name, $number, $ridbId]) {
    $hash = str_repeat($number, 64);
    $addNps->execute(['campgrounds', $park, $id, $name, $hash]);
    $addReview->execute([(int) $number, $park, $id, $ridbId,
        'confirmed_match', $id === 'c' ? str_repeat('f', 64) : $hash]);
    $addRidb->execute([$ridbId, '{"FacilityID":"' . $ridbId . '"}']);
}
$addNps->execute(['campgrounds', 'grca', 'unreviewed', 'Extra Campground', str_repeat('0', 64)]);
$main->exec("INSERT INTO places VALUES (12, 'Published Alpha'), (20, 'Published Delta'), (999, 'Other Place')");
$addLink->execute([12, 'ridb', '101']);
$addLink->execute([20, 'ridb', '104']);
$addLink->execute([999, 'nps', 'd']);

$ridbHash = hash('sha256', '{"FacilityID":"101"}');
$addField->execute([1, 'yose', 'a', '101', 'site_total', 'use_ridb',
    '88', str_repeat('1', 64), $ridbHash, 'RIDB authoritative',
    '2026-11-05 18:00:00']);
$addField->execute([2, 'yose', 'b', '102', 'regular_nightly_fee_usd', 'use_nps',
    '25.00', str_repeat('2', 64), hash('sha256', '{"FacilityID":"102"}'),
    'Use NPS', '2026-11-05 18:10:00']);

$run = llama_nps_publication_readiness($nps, $ridb, $main);
$totals = $run['totals'];
$assert($totals['cached_campgrounds'] === 5,
    'All cached campgrounds, including unreviewed, must count');
$assert($totals['confirmed_pairs'] === 4, 'Should count four current confirmed identities');
$assert($totals['published_places'] === 1, 'Only Alpha should be a valid linked Place');
$assert($totals['unpublished_ridb'] === 1, 'Bravo should need RIDB Place import');
$assert($totals['stale_identities'] === 1, 'Charlie identity must be re-reviewed');
$assert($totals['identity_conflicts'] === 1, 'Delta conflicting NPS link must be rejected');

$states = [];
foreach ($run['pairs'] as $pair) {
    $states[$pair['record_id']] = $pair;
}
$assert($states['a']['place_id'] === 12
    && $states['a']['status'] === 'published_place', 'Alpha link missing');
$assert($states['b']['place_id'] === null
    && $states['b']['status'] === 'unpublished_ridb', 'Bravo should not auto-publish');
$assert($states['c']['status'] === 'stale_identity', 'Stale identity bypassed');
$assert($states['d']['status'] === 'identity_conflict', 'NPS ownership conflict bypassed');

$fieldStates = [];
foreach ($run['field_reviews'] as $field) {
    $fieldStates[$field['record_id']] = $field['status'];
}
$assert($fieldStates['a'] === 'reviewed_not_published',
    'A source-reviewed decision is not itself a published canonical answer');
$assert($fieldStates['b'] === 'waiting_for_place',
    'Review cannot publish before matching RIDB Place exists');

$ridb->exec("UPDATE ridb_facilities SET source_json = '{\"FacilityID\":\"101\",\"changed\":true}' WHERE ridb_facility_id = '101'");
$changed = llama_nps_publication_readiness($nps, $ridb, $main);
$assert($changed['field_reviews'][0]['status'] === 'stale_sources',
    'Changed RIDB source hash must invalidate former field review');

$rejected = false;
try {
    llama_nps_publication_readiness($nps, $ridb, $main, 501);
} catch (InvalidArgumentException) {
    $rejected = true;
}
$assert($rejected, 'Unbounded source scans must not be allowed');

$assert((int) $main->query('SELECT COUNT(*) FROM places')->fetchColumn() === 3
    && (int) $main->query('SELECT COUNT(*) FROM place_external_sources')->fetchColumn() === 3,
    'Readiness report must not mutate Place or source links');

echo "PASS: NPS coverage detects published/missing/stale/conflicting identity links; validates reviewed source hashes and never publishes\n";

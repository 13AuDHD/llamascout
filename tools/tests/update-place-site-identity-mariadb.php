<?php

declare(strict_types=1);

// The test uses only an isolated CI schema. Production tables are untouched.
$dsn = (string) getenv('LLAMA_SITE_IDENTITY_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_site_identity_test')) {
    throw new RuntimeException('A dedicated llama_site_identity_test schema is required.');
}
require_once dirname(__DIR__, 2) . '/app/place-report/site-identity-update.php';

$db = new PDO($dsn, (string) getenv('LLAMA_SITE_IDENTITY_TEST_USER'),
    (string) getenv('LLAMA_SITE_IDENTITY_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
};

$db->exec('CREATE TABLE place_map_features (
    id BIGINT UNSIGNED PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    feature_type VARCHAR(32) NOT NULL,
    geometry_type VARCHAR(16) NOT NULL,
    geometry GEOMETRY NULL,
    label VARCHAR(160),
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB');

$db->exec('CREATE TABLE place_campsites (
    id BIGINT UNSIGNED PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    site_name VARCHAR(160) NULL,
    site_code VARCHAR(80) NULL,
    site_type VARCHAR(64) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    map_feature_id BIGINT UNSIGNED NULL,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB');
$db->exec("INSERT INTO place_map_features (id,place_id,feature_type,geometry_type,geometry,label)
 VALUES
 (30,1,'camping_site','point',ST_GeomFromText('POINT(-107.2192 37.25222)',4326),'Rocky Camp'),
 (31,1,'camping_site','point',ST_GeomFromText('POINT(-107.4 37.4)',4326),'Legacy Site'),
 (32,2,'camping_site','point',ST_GeomFromText('POINT(-107.8 37.8)',4326),'Foreign Site')");
$db->exec("INSERT INTO place_campsites
 (id,place_id,site_name,site_code,site_type,latitude,longitude,map_feature_id)
 VALUES (10,1,'Rocky Camp','14','tent_site',37.25222,-107.2192,30),
 (11,1,'Pine Camp','15','rv_site',37.3,-107.3,NULL),
 (12,2,'Foreign Camp','16','tent_site',37.8,-107.8,32)");

$t = ['scope'=>'site','source'=>'campsite_record','id'=>10,'label'=>'Rocky Camp'];
$before = llama_update_site_identity_current($db, 1, $t);
$assert($before['name'] === 'Rocky Camp'
    && $before['type'] === 'tent_site'
    && $before['coordinates'] === '37.2522200, -107.2192000',
    'Existing Site identity must be loaded exactly.');

$proposal = llama_update_site_identity_proposal($db, 1, $t, json_encode([
    'name'=>'Rocky Camp East', 'type'=>'rv_site',
    'coordinates'=>'37.2600000, -107.2600000',
], JSON_THROW_ON_ERROR));
$assert(count($proposal['changes']) === 3
    && count($proposal['original']) === 3,
    'A Site can propose independently editable name, type and GPS corrections.');
$unchanged = llama_update_site_identity_proposal($db, 1, $t,
    '{"name":"Rocky Camp","type":"tent_site","coordinates":"37.25222, -107.2192"}');
$assert($unchanged['changes'] === [], 'Unchanged fields must not be sent to moderation.');

$db->beginTransaction();
llama_update_site_identity_apply($db, 1, $t, $proposal['changes'], $proposal['original']);
$db->commit();
$after = llama_update_site_identity_current($db, 1, $t);
$assert($after['name'] === 'Rocky Camp East'
    && $after['type'] === 'rv_site'
    && $after['coordinates'] === '37.2600000, -107.2600000',
    'Approved edits must update the original canonical Site without a new row.');
$feature = $db->query('SELECT label, ST_AsText(geometry) AS wkt FROM place_map_features WHERE id=30')
    ->fetch(PDO::FETCH_ASSOC);
$assert($feature['label'] === 'Rocky Camp East'
    && str_contains($feature['wkt'], '-107.26')
    && str_contains($feature['wkt'], '37.26'),
    'Mapped Site point and label must follow approved canonical identity changes.');

$stale = llama_update_site_identity_proposal($db, 1, $t, '{"name":"Outdated Name"}');
$db->exec("UPDATE place_campsites SET site_name = 'Changed by another moderator' WHERE id=10");
$db->beginTransaction();
$conflict = false;
try {
    llama_update_site_identity_apply($db, 1, $t, $stale['changes'], $stale['original']);
} catch (RuntimeException) { $conflict = true; }
$db->rollBack();
$assert($conflict && llama_update_site_identity_current($db, 1, $t)['name'] === 'Changed by another moderator',
    'Moderation must reject stale Site identity updates without overwriting newer changes.');

$cross = false;
try {
    llama_update_site_identity_current($db, 1,
        ['scope'=>'site','source'=>'campsite_record','id'=>12]);
} catch (InvalidArgumentException) { $cross = true; }
$assert($cross, 'Another Place Site cannot be accessed by a forged target.');

$legacy = ['scope'=>'site','source'=>'map_feature','id'=>31,'label'=>'Legacy Site'];
$legacyCurrent = llama_update_site_identity_current($db, 1, $legacy);
$assert($legacyCurrent['name'] === 'Legacy Site'
    && $legacyCurrent['editable_coordinates'] === true
    && $legacyCurrent['editable_type'] === false,
    'An old mapped Site keeps its identity source and GPS editability.');
$legacyProposal = llama_update_site_identity_proposal($db, 1, $legacy,
    '{"name":"Renamed Old Site","coordinates":"37.4100, -107.4100"}');
$db->beginTransaction();
llama_update_site_identity_apply($db, 1, $legacy,
    $legacyProposal['changes'], $legacyProposal['original']);
$db->commit();
$assert(llama_update_site_identity_current($db, 1, $legacy)['name'] === 'Renamed Old Site',
    'Legacy map-feature Sites must not require a new campsite row.');

foreach (['{"type":"rv_site"}','{"coordinates":"92,1"}','{"bogus":"text"}'] as $bad) {
    $failed = false;
    try { llama_update_site_identity_proposal($db, 1, $legacy, $bad); }
    catch (InvalidArgumentException) { $failed = true; }
    $assert($failed, 'Invalid Site identity payload must be rejected: ' . $bad);
}

$assert((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 3,
    'Site identity corrections must never create duplicate Sites.');
echo "PASS: Site facts, reviewed point changes, map synchronization, cross-Place guards and stale approval conflicts.\n";

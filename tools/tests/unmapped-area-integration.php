<?php
declare(strict_types=1);

// Disposable MariaDB integration. Strict DSN guard: NEVER use a live database.
function llama_contributor_can(PDO $db, int $id, string $action): bool
{
    return $id === 7 && $action === 'submit_place';
}
require_once dirname(__DIR__, 2) . '/app/area-submissions.php';

$dsn = (string) getenv('LLAMA_UNMAPPED_AREA_DSN');
if (!str_contains($dsn, 'dbname=llama_unmapped_area_test')) {
    throw new RuntimeException('Dedicated llama_unmapped_area_test DSN required.');
}
$db = new PDO($dsn, (string) getenv('LLAMA_UNMAPPED_AREA_USER'),
    (string) getenv('LLAMA_UNMAPPED_AREA_PASS'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function areaCheck(bool $pass, string $message): void
{
    if (!$pass) throw new RuntimeException('FAIL: ' . $message);
}

$db->exec('CREATE TABLE places (
    id BIGINT PRIMARY KEY, name VARCHAR(150), state VARCHAR(40), status VARCHAR(30)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE users (
    id BIGINT PRIMARY KEY, display_name VARCHAR(200), username VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsites (
    id BIGINT PRIMARY KEY, place_id BIGINT, site_code VARCHAR(50),
    site_name VARCHAR(100), map_feature_id BIGINT NULL, is_active TINYINT DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_features (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, place_id BIGINT NOT NULL,
    feature_type VARCHAR(32), label VARCHAR(120), geometry_type VARCHAR(16) NOT NULL,
    geometry GEOMETRY NULL, source_type VARCHAR(20), accuracy_m DECIMAL(10,2),
    metadata_json LONGTEXT NULL, created_by BIGINT, updated_by BIGINT,
    verified_at DATETIME, sort_order INT, is_active TINYINT DEFAULT 1,
    created_at DATETIME, updated_at DATETIME
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_area_details (
    feature_id BIGINT, area_use VARCHAR(50), fee_status VARCHAR(25),
    overnight_status VARCHAR(25)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_camping_sites (
    feature_id BIGINT, camping_area_feature_id BIGINT, site_class_id BIGINT,
    site_code VARCHAR(80), site_type VARCHAR(40), parking_style VARCHAR(40),
    hookup_status VARCHAR(40), accessible_status VARCHAR(40)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_feature_rates (
    id BIGINT, feature_id BIGINT, rate_type VARCHAR(40), label VARCHAR(120),
    amount DECIMAL(12,2), currency VARCHAR(3), season_start DATE, season_end DATE,
    notes TEXT, sort_order INT, is_active TINYINT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_camping_site_classes (
    id BIGINT, camping_area_feature_id BIGINT, name VARCHAR(100),
    description TEXT, sort_order INT, is_active TINYINT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsite_area_links (
    place_id BIGINT, campsite_id BIGINT, area_feature_id BIGINT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_scoped_answers (
    place_id BIGINT, scope_type VARCHAR(16), scope_source VARCHAR(40),
    scope_id BIGINT, field_key VARCHAR(130), value_json LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY scoped (place_id,scope_type,scope_source,scope_id,field_key)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_current_answers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT, target_scope VARCHAR(16), target_source VARCHAR(40),
    target_id BIGINT, question_key VARCHAR(130), answer_state VARCHAR(20),
    value_json LONGTEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY current_answer (place_id,target_scope,target_source,target_id,question_key)
) ENGINE=InnoDB');
$ddl = file_get_contents(dirname(__DIR__, 2) . '/tools/migrations/20261114_unmapped_areas.sql');
preg_match('/CREATE TABLE IF NOT EXISTS place_area_submissions[\s\S]*?ENGINE=InnoDB[^;]*;/i', (string) $ddl, $m);
areaCheck(isset($m[0]), 'Migration must create pending Area table.');
$db->exec($m[0]);

$db->exec("INSERT INTO places VALUES
    (1,'North Mills River','NC','active'),
    (2,'Target Tree Campground','NC','active')");
$db->exec("INSERT INTO users VALUES (7,'Scout','scout'),(9,'Admin','admin')");
$db->exec("INSERT INTO place_campsites VALUES (5,1,'2','Site 2',NULL,1)");
$prepared = llama_area_submission_prepare($db, [
    'area_place_id'=>1,
    'area_name'=>'West Vault Toilets',
    'area_feature_type'=>'facility_area',
    'area_facility_kind'=>'restroom',
    'area_answers'=>[
        'facility_toilet_count'=>'2',
        'facility_accessible_stall'=>'1',
    ],
]);
areaCheck($prepared['answers']['facility_toilet_count'] === '2',
    'Canonical restroom answer parsing failed.');
areaCheck(count($prepared['answers']) === 2, 'Expected only two real Area answers.');
$notPlace = false;
try {
    llama_area_submission_prepare($db, [
        'area_place_id'=>999, 'area_name'=>'Wrong Place',
        'area_feature_type'=>'facility_area', 'area_facility_kind'=>'restroom',
    ]);
} catch (InvalidArgumentException) { $notPlace = true; }
areaCheck($notPlace, 'Unrecognized Place must not get an Area.');
$badKind = false;
try {
    llama_area_submission_prepare($db, [
        'area_place_id'=>1, 'area_name'=>'Bad Kind',
        'area_feature_type'=>'facility_area', 'area_facility_kind'=>'invalid',
    ]);
} catch (InvalidArgumentException) { $badKind = true; }
areaCheck($badKind, 'Invalid facility kind should be rejected.');

$submissionId = llama_area_submission_submit($db, 7, [
    'area_place_id'=>1,
    'area_name'=>'West Vault Toilets',
    'area_feature_type'=>'facility_area',
    'area_facility_kind'=>'restroom',
    'area_answers'=>[
        'facility_toilet_count'=>'2',
        'facility_accessible_stall'=>'1',
    ],
]);
areaCheck($submissionId > 0, 'Area submission not saved.');
areaCheck((int) $db->query('SELECT COUNT(*) FROM place_map_features')->fetchColumn() === 0,
    'Unapproved Area must not be a published map record.');
areaCheck(count(llama_area_submission_queue($db)) === 1,
    'New Area must be visible to moderation.');

$areaId = llama_area_submission_moderate($db, $submissionId, 9, 'approved', 'Verified');
areaCheck($areaId > 0, 'Approved Area not created.');
$stmt = $db->prepare('SELECT geometry_type, ST_AsGeoJSON(geometry) AS geo,
    metadata_json, label FROM place_map_features WHERE id = ?');
$stmt->execute([$areaId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
areaCheck($row['geo'] === null && $row['geometry_type'] === 'unmapped',
    'Approved Area must have NULL geometry, not a fake marker.');
areaCheck($row['label'] === 'West Vault Toilets', 'Area name missing.');
areaCheck(json_decode($row['metadata_json'], true)['facility_kind'] === 'restroom',
    'Area facility type missing.');
$target = llama_report_verified_target($db, 1, 'area', 'map_feature', $areaId);
areaCheck($target['label'] === 'West Vault Toilets', 'Area not listed as canonical target.');
areaCheck($target['facility_kind'] === 'restroom', 'Facility type not in canonical target.');
// Public Area list uses this same map-feature projection, including records
// with no geometry. The map JS intentionally draws only real geometries.
$publicAreas = llama_place_map_features($db, 1, true, true);
$unmappedPublic = array_values(array_filter(
    $publicAreas, static fn(array $feature): bool =>
        (int) $feature['id'] === $areaId
));
areaCheck(count($unmappedPublic) === 1,
    'Unmapped Area must remain visible in the public Area catalog.');
areaCheck($unmappedPublic[0]['geometry'] === null
    && $unmappedPublic[0]['is_mapped'] === false,
    'Public Area must not invent geometry for an unmapped Area.');
$stmt = $db->prepare('SELECT field_key,value_json FROM place_report_scoped_answers
    WHERE scope_type = "area" AND scope_id = ?');
$stmt->execute([$areaId]);
$stored = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
    $stored[$answer['field_key']] = json_decode((string) $answer['value_json'], true);
}
areaCheck(count($stored) === 2 && $stored['facility_toilet_count'] === 2,
    'Canonical scoped Area answers were not saved.');
$stmt = $db->prepare('SELECT COUNT(*) FROM place_report_current_answers
    WHERE target_scope = "area" AND target_id = ?');
$stmt->execute([$areaId]);
areaCheck((int) $stmt->fetchColumn() === 2,
    'Canonical current answer mirrors are missing.');
areaCheck((int) $db->query('SELECT COUNT(*) FROM place_campsites WHERE id = 5')->fetchColumn() === 1,
    'Publishing Area must not damage sibling Site.');
areaCheck((int) $db->query('SELECT COUNT(*) FROM places')->fetchColumn() === 2,
    'Publishing Area must not damage parent or other Places.');

// Adding map geometry later must keep the exact same Area ID and answers.
$stmt = $db->prepare('UPDATE place_map_features
    SET geometry_type = "point", geometry = ST_GeomFromText(?, 4326)
    WHERE id = ? AND geometry IS NULL');
$stmt->execute(['POINT(-82.6023 35.4551)', $areaId]);
areaCheck($stmt->rowCount() === 1, 'Could not add geometry to unmapped Area.');
areaCheck(llama_report_verified_target($db, 1, 'area', 'map_feature', $areaId)['id'] === $areaId,
    'Area identity changed when mapping the Area.');
$stmt = $db->prepare('SELECT COUNT(*) FROM place_report_scoped_answers WHERE scope_id = ?');
$stmt->execute([$areaId]);
areaCheck((int) $stmt->fetchColumn() === 2, 'Mapping Area lost original answers.');

// Review must not approve a submission twice.
$repeat = false;
try {
    llama_area_submission_moderate($db, $submissionId, 9, 'approved');
} catch (InvalidArgumentException) { $repeat = true; }
areaCheck($repeat, 'Double approval must be blocked.');

// A first Area can be included when the parent Place has just been
// approved, without waiting for a separate mapped record.
$firstAreaPayload = llama_area_submission_prepare_bundle([
    'area_name' => 'East Loop',
    'area_feature_type' => 'camping_area',
    'area_answers' => [
        'area_site_count' => '10',
        'area_surface' => 'gravel',
    ],
]);
$db->beginTransaction();
$firstAreaId = llama_area_publish_unmapped($db, 2, 7, 9, $firstAreaPayload);
$db->commit();
areaCheck($firstAreaId > 0 && $firstAreaId !== $areaId,
    'First Area bundled with new Place not created.');
$firstArea = llama_report_verified_target($db, 2, 'area', 'map_feature', $firstAreaId);
$firstAreaCatalog = llama_place_map_features($db, 2);
areaCheck(count($firstAreaCatalog) === 1
    && $firstAreaCatalog[0]['geometry'] === null,
    'An approved new Place must list its first Area without geometry.');
areaCheck($firstArea['label'] === 'East Loop',
    'Bundled Area must attach to the newly published parent.');
$stmt = $db->prepare('SELECT geometry FROM place_map_features WHERE id = ?');
$stmt->execute([$firstAreaId]);
areaCheck($stmt->fetchColumn() === null,
    'Bundled first Area must not require map coordinates.');
$stmt = $db->prepare('SELECT COUNT(*) FROM place_report_scoped_answers
    WHERE place_id = 2 AND scope_id = ?');
$stmt->execute([$firstAreaId]);
areaCheck((int) $stmt->fetchColumn() === 2,
    'Bundled first Area questions were not published.');
areaCheck((int) $db->query('SELECT COUNT(*) FROM place_campsites WHERE id = 5')->fetchColumn() === 1,
    'Bundled Area must not modify sibling Site.');

echo "PASS: Existing Place Area review, new Place first Area bundle, canonical answers, NULL geometry, later mapping, unchanged Site/Place and identities\n";

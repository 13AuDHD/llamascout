<?php

declare(strict_types=1);

// Extend the disposable Admin Site database test. Never connect to production.
$dsn = (string) getenv('LLAMA_ADMIN_SITE_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_admin_site_test')) {
    throw new RuntimeException('Dedicated llama_admin_site_test DSN required.');
}
require_once dirname(__DIR__, 2) . '/app/admin-place-facilities.php';
$db = new PDO(
    $dsn, (string) getenv('LLAMA_ADMIN_SITE_TEST_USER'),
    (string) getenv('LLAMA_ADMIN_SITE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$assert = static function (bool $pass, string $message): void {
    if (!$pass) throw new RuntimeException('FAIL: ' . $message);
};
$db->exec('CREATE TABLE place_map_features (
    id BIGINT PRIMARY KEY,
    place_id BIGINT NOT NULL,
    feature_type VARCHAR(32) NOT NULL,
    geometry_type VARCHAR(16) NOT NULL,
    geometry GEOMETRY NULL,
    label VARCHAR(160),
    metadata_json LONGTEXT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    updated_by BIGINT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB');
$db->exec("INSERT INTO place_map_features
 (id,place_id,feature_type,geometry_type,geometry,label,metadata_json)
 VALUES
 (20,1,'facility_area','point',ST_GeomFromText('POINT(-107.2200 37.2500)',4326),
  'Bath House','{\"facility_kind\":\"restroom\",\"custom\":\"keep-me\"}'),
 (21,1,'parking_area','polygon',
  ST_GeomFromText('POLYGON((-107.22 37.25,-107.21 37.25,-107.21 37.26,-107.22 37.25))',4326),
  'Parking Loop','{\"marker\":\"keep-me\"}'),
 (22,1,'facility_area','unmapped',NULL,
  'Old Water Spigot','{\"facility_kind\":\"water\"}'),
 (23,2,'facility_area','point',ST_GeomFromText('POINT(-107.2100 37.2400)',4326),
  'Another Place Facility',NULL)");
$db->exec('CREATE TABLE place_report_scoped_answers (
    place_id BIGINT, scope_type VARCHAR(16), scope_source VARCHAR(35),
    scope_id BIGINT, field_key VARCHAR(100), value_json LONGTEXT
) ENGINE=InnoDB');
$db->exec("INSERT INTO place_report_scoped_answers VALUES
    (1,'area','map_feature',20,'facility_toilet_count','3'),
    (1,'area','map_feature',21,'area_site_count','25')");

$feature = static function (int $id) use ($db): array {
    $q = $db->prepare('SELECT label, feature_type, geometry_type,
        ST_AsText(geometry) AS shape, metadata_json FROM place_map_features WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
};
$old = $feature(20);
$db->beginTransaction();
admin_place_save_map_facility($db, 9, 1, 20, [
    'facility_name'=>'Updated Bath House', 'facility_kind'=>'shower',
    'facility_notes'=>'Hot water and private stalls',
    'facility_coordinates'=>'37.2522200, -107.2192000',
    'facility_coordinates_changed'=>'1'
]);
$db->commit();
$updated = $feature(20);
$meta = json_decode((string) $updated['metadata_json'], true);
$assert($updated['label'] === 'Updated Bath House'
    && $updated['geometry_type'] === 'point'
    && str_contains((string) $updated['shape'], '-107.2192')
    && str_contains((string) $updated['shape'], '37.25222')
    && $meta['facility_kind'] === 'shower'
    && $meta['description'] === 'Hot water and private stalls'
    && $meta['custom'] === 'keep-me',
    'Mapped Facility must save location, name, type, notes and preserve other metadata.');

$db->beginTransaction();
admin_place_save_map_facility($db, 9, 1, 21, [
    'facility_name'=>'Overflow Parking',
    'facility_notes'=>'Good for longer vehicles',
    'facility_coordinates_changed'=>'0'
]);
$db->commit();
$polygon = $feature(21);
$assert($polygon['label'] === 'Overflow Parking'
    && $polygon['geometry_type'] === 'polygon'
    && str_starts_with((string) $polygon['shape'], 'POLYGON')
    && json_decode((string) $polygon['metadata_json'], true)['marker'] === 'keep-me',
    'Parking Facility metadata must save without changing its polygon.');

$badPolygon = false;
$db->beginTransaction();
try {
    admin_place_save_map_facility($db, 9, 1, 21, [
        'facility_name'=>'Bad Polygon Point',
        'facility_coordinates'=>'37.3, -107.2',
        'facility_coordinates_changed'=>'1'
    ]);
} catch (InvalidArgumentException) { $badPolygon = true; }
$db->rollBack();
$assert($badPolygon && $feature(21) === $polygon,
    'Polygon geometry cannot be changed by the point editor.');

$db->beginTransaction();
admin_place_save_map_facility($db, 9, 1, 22, [
    'facility_name'=>'Water Fill',
    'facility_kind'=>'water',
    'facility_coordinates'=>'37.2600000, -107.2600000',
    'facility_coordinates_changed'=>'1'
]);
$db->commit();
$assert($feature(22)['geometry_type'] === 'point'
    && str_contains((string) $feature(22)['shape'], '-107.26'),
    'Previously unmapped point Facility can receive coordinates.');

$foreign = false;
$db->beginTransaction();
try {
    admin_place_save_map_facility($db, 9, 1, 23, [
        'facility_name'=>'Foreign Edit', 'facility_coordinates_changed'=>'0'
    ]);
} catch (InvalidArgumentException) { $foreign = true; }
$db->rollBack();
$assert($foreign && $feature(23)['label'] === 'Another Place Facility',
    'Admin cannot modify another Place Facility.');

$db->beginTransaction();
admin_place_save_map_facility($db, 9, 1, 20, [
    'facility_name'=>'Rollback Bath House', 'facility_coordinates_changed'=>'0'
]);
$db->rollBack();
$assert($feature(20) === $updated, 'Failed transaction must roll back Facility changes.');

$answers = (int) $db->query(
    "SELECT COUNT(*) FROM place_report_scoped_answers WHERE scope_type = 'area'"
)->fetchColumn();
$assert($answers === 2,
    'Historic Area answers must remain on their original canonical targets.');
$assert((int) $db->query(
    "SELECT COUNT(*) FROM admin_audit_log WHERE action='place.facility_metadata_updated'"
)->fetchColumn() === 3,
    'Only committed Facility saves should create audit records.');
echo "PASS: MariaDB mapped Facility edits preserve map IDs, geometry, metadata, canonical answers and rollbacks.\n";

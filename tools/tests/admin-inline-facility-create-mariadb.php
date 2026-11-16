<?php

declare(strict_types=1);

// Disposable CI database only. No production rows, emails or photos.
$dsn = (string) getenv('LLAMA_ADMIN_SITE_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_admin_site_test')) {
    throw new RuntimeException('Dedicated llama_admin_site_test database required.');
}
require_once dirname(__DIR__, 2) . '/app/admin-resources.php';

$db = new PDO(
    $dsn, (string) getenv('LLAMA_ADMIN_SITE_TEST_USER'),
    (string) getenv('LLAMA_ADMIN_SITE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
};

$db->exec('CREATE TABLE places (
    id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(32) NOT NULL
) ENGINE=InnoDB');
$db->exec("INSERT INTO places (id, status) VALUES (1,'active'),(2,'active')");

// Exercise the same migrations used by the actual Facility storage.
$root = dirname(__DIR__, 2);
$migrations = [
    '20261101_v3_003_resources.sql',
    '20261101_v3_004_resource_pages.sql',
    '20261101_v3_005_resource_marker_service.sql',
    '20261101_v3_006_resource_hours.sql',
    '20261109_resource_county.sql',
    '20261115_facility_parent_place.sql',
    '20261115_resource_facility_experience.sql',
    '20261115_facility_names_per_place.sql',
];
foreach ($migrations as $migration) {
    $sql = file_get_contents($root . '/tools/migrations/' . $migration);
    if ($sql === false) throw new RuntimeException('Missing ' . $migration);
    // These migrations consist of conventional SQL statements and comments,
    // without stored procedures. Execute them against disposable storage.
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        if (trim($statement) !== '') $db->exec($statement);
    }
}

$input = [
    'name' => 'Restroom', 'resource_type' => 'restroom_facility',
    'coordinates' => '37.2522200, -107.2192000',
    'hours_mode' => '24_hours', 'status' => 'available',
    'parent_place_id' => 1, 'notes' => 'Two stalls',
    'experience_present' => '1', 'services' => [],
];

$db->beginTransaction();
$id = admin_resource_save($db, 9, 0, $input);
$db->commit();
$assert($id > 0, 'Admin must create a child Facility directly.');

$query = $db->prepare('SELECT name, slug, parent_place_id, latitude, longitude,
    street_address, notes FROM resources WHERE id = ?');
$query->execute([$id]);
$stored = $query->fetch(PDO::FETCH_ASSOC);
$assert((int) $stored['parent_place_id'] === 1
    && $stored['name'] === 'Restroom'
    && str_starts_with((string) $stored['slug'], 'facility-1-restroom')
    && round((float) $stored['latitude'], 7) === 37.25222
    && round((float) $stored['longitude'], 7) === -107.2192
    && $stored['street_address'] === null,
    'Facility must be linked to Place 1 and save only its GPS point, not a separate address.');

// Different Places must be allowed to have the same Facility name.
$db->beginTransaction();
$otherId = admin_resource_save($db, 9, 0, [
    ...$input, 'parent_place_id' => 2,
    'coordinates' => '37.4000000, -107.3000000'
]);
$db->commit();
$assert($otherId !== $id && $otherId > 0,
    'The same Facility name must work at another Place.');

$duplicateRejected = false;
$db->beginTransaction();
try {
    admin_resource_save($db, 9, 0, $input);
} catch (InvalidArgumentException) {
    $duplicateRejected = true;
}
$db->rollBack();
$assert($duplicateRejected,
    'Duplicate Facility names within one Parent Place must be rejected.');

$db->beginTransaction();
$updatedId = admin_resource_save($db, 9, $id, [
    ...$input, 'coordinates' => '37.2600000, -107.2600000',
    'notes' => 'Updated after checking the location',
]);
$db->commit();
$query->execute([$id]);
$updated = $query->fetch(PDO::FETCH_ASSOC);
$assert($updatedId === $id
    && round((float) $updated['latitude'], 7) === 37.26
    && round((float) $updated['longitude'], 7) === -107.26
    && (int) $updated['parent_place_id'] === 1
    && $updated['notes'] === 'Updated after checking the location',
    'Edit Facility must update the original record without changing its Place or ID.');

$assert((int) $db->query('SELECT COUNT(*) FROM resources')->fetchColumn() === 2,
    'Create, duplicate rejection, and edit must not generate extra records.');

echo "PASS: MariaDB creates and edits GPS-only Facilities without map features; names scoped by Place.\n";

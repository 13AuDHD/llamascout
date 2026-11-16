<?php

declare(strict_types=1);

// This test refuses all non-disposable databases.
$dsn = (string) getenv('LLAMA_ADMIN_SITE_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_admin_site_test')) {
    throw new RuntimeException('A dedicated llama_admin_site_test database is required.');
}

require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';

$db = new PDO(
    $dsn, (string) getenv('LLAMA_ADMIN_SITE_TEST_USER'),
    (string) getenv('LLAMA_ADMIN_SITE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$check = static function (bool $pass, string $message): void {
    if (!$pass) throw new RuntimeException('FAIL: ' . $message);
};

$db->exec('CREATE TABLE place_campsites (
    id BIGINT PRIMARY KEY,
    place_id BIGINT NOT NULL,
    site_name VARCHAR(120),
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(11,7) NULL,
    map_feature_id BIGINT NULL,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT,
    target_user_id BIGINT NULL,
    action VARCHAR(120),
    summary VARCHAR(255),
    metadata LONGTEXT NULL,
    ip_address VARCHAR(64) NULL
) ENGINE=InnoDB');
$db->exec("INSERT INTO place_campsites
    (id,place_id,site_name,latitude,longitude,map_feature_id)
    VALUES
    (12,1,'Unmapped site',37.2500000,-107.2200000,NULL),
    (13,1,'Mapped site',37.2800000,-107.2900000,77),
    (14,2,'Wrong Place',NULL,NULL,NULL)");

$location = static function (int $id) use ($db): array {
    $stmt = $db->prepare('SELECT latitude, longitude FROM place_campsites WHERE id = ?');
    $stmt->execute([$id]);
    return (array) $stmt->fetch(PDO::FETCH_ASSOC);
};

$first = $location(12);
$db->beginTransaction();
$changed = admin_place_save_selected_site_coordinates($db, 9, 1, 12, [
    'admin_site_coordinates_changed' => '0',
    'site_coordinates' => '38.0000, -108.0000',
]);
$db->commit();
$check($changed === false && $location(12) === $first,
    'Untouched coordinates must not be rewritten.');

$db->beginTransaction();
$changed = admin_place_save_selected_site_coordinates($db, 9, 1, 12, [
    'admin_site_coordinates_changed' => '1',
    'site_coordinates' => '37.2522200, -107.2192000',
]);
$db->commit();
$check($changed === true, 'Unmapped Site coordinate save did not report a change.');
$saved = $location(12);
$check(round((float) $saved['latitude'], 7) === 37.25222
    && round((float) $saved['longitude'], 7) === -107.2192,
    'MariaDB did not save child Site coordinates.');

$invalid = false;
$db->beginTransaction();
try {
    admin_place_save_selected_site_coordinates($db, 9, 1, 12, [
        'admin_site_coordinates_changed' => '1',
        'site_coordinates' => '95, -107',
    ]);
} catch (InvalidArgumentException) {
    $invalid = true;
}
$db->rollBack();
$check($invalid && $location(12) === $saved,
    'Invalid GPS must be rejected without corrupting existing Site location.');

$mappedDenied = false;
$db->beginTransaction();
try {
    admin_place_save_selected_site_coordinates($db, 9, 1, 13, [
        'admin_site_coordinates_changed' => '1',
        'site_coordinates' => '37.30000, -107.30000',
    ]);
} catch (InvalidArgumentException) {
    $mappedDenied = true;
}
$db->rollBack();
$check($mappedDenied && round((float) $location(13)['latitude'], 7) === 37.28,
    'Mapped Site GPS must not bypass the map editor.');

$otherPlaceDenied = false;
$db->beginTransaction();
try {
    admin_place_save_selected_site_coordinates($db, 9, 1, 14, [
        'admin_site_coordinates_changed' => '1',
        'site_coordinates' => '37.30000, -107.30000',
    ]);
} catch (InvalidArgumentException) {
    $otherPlaceDenied = true;
}
$db->rollBack();
$check($otherPlaceDenied && $location(14)['latitude'] === null,
    'Site coordinates must not save to another Place.');

$db->beginTransaction();
admin_place_save_selected_site_coordinates($db, 9, 1, 12, [
    'admin_site_coordinates_changed' => '1',
    'site_coordinates' => '37.3500000, -107.3500000',
]);
$db->rollBack();
$check($location(12) === $saved,
    'Database rollback must reverse Site coordinates and audit entries.');

$db->beginTransaction();
admin_place_save_selected_site_coordinates($db, 9, 1, 12, [
    'admin_site_coordinates_changed' => '1',
    'site_coordinates' => '',
]);
$db->commit();
$check($location(12)['latitude'] === null && $location(12)['longitude'] === null,
    'Clearing an unmapped Site location must preserve a valid NULL/NULL pair.');
$check((int) $db->query(
    "SELECT COUNT(*) FROM admin_audit_log WHERE action = 'place.site_location_updated'"
)->fetchColumn() === 2, 'Committed Site coordinate changes must be audited once each.');

echo "PASS: MariaDB Site GPS save, parent isolation, mapped pin guard, clear, audit and rollback.\n";

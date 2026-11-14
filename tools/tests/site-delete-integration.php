<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-site-delete.php';

/**
 * Disposable MariaDB integration test for permanent Site deletion.
 * Run against a dedicated EMPTY database, never production.
 */
$dsn = getenv('LLAMA_SITE_DELETE_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=llama_site_delete_test')) {
    throw new RuntimeException('A dedicated llama_site_delete_test database DSN is required.');
}
$db = new PDO($dsn, (string) getenv('LLAMA_SITE_DELETE_TEST_USER'),
    (string) getenv('LLAMA_SITE_DELETE_TEST_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

function ensure_site_deleted(bool $value, string $why): void
{
    if (!$value) throw new RuntimeException($why);
}
function site_delete_count(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$db->exec('CREATE TABLE places (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsites (
    id BIGINT PRIMARY KEY, place_id BIGINT NOT NULL,
    site_code VARCHAR(100), site_name VARCHAR(100), map_feature_id BIGINT NULL,
    latitude DECIMAL(12,7) NULL, longitude DECIMAL(12,7) NULL,
    is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsite_facts (
    campsite_id BIGINT PRIMARY KEY, max_people INT,
    CONSTRAINT fk_test_site_fact FOREIGN KEY(campsite_id)
        REFERENCES place_campsites(id) ON DELETE RESTRICT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsite_images (
    id BIGINT PRIMARY KEY, place_id BIGINT NOT NULL,
    campsite_id BIGINT NOT NULL, src VARCHAR(1024)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_images (
    id BIGINT PRIMARY KEY, place_id BIGINT NOT NULL, src VARCHAR(1024)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_site_visibility (
    place_id BIGINT, campsite_id BIGINT PRIMARY KEY, visibility VARCHAR(32)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_pricing_reviews (
    place_id BIGINT, site_id BIGINT, next_review_on DATE NULL
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_current_answers (
    place_id BIGINT, target_scope VARCHAR(16), target_source VARCHAR(64),
    target_id BIGINT, question_key VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_scoped_answers (
    place_id BIGINT, target_scope VARCHAR(16), target_source VARCHAR(64),
    target_id BIGINT, question_key VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_season_answers (
    place_id BIGINT, target_scope VARCHAR(16), target_source VARCHAR(64),
    target_id BIGINT, question_key VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_season_site_status (
    place_id BIGINT, target_source VARCHAR(64), target_id BIGINT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_rules (place_id BIGINT PRIMARY KEY, rate_cards_json LONGTEXT)');
$db->exec('CREATE TABLE place_map_features (
    id BIGINT PRIMARY KEY, place_id BIGINT, feature_type VARCHAR(32),
    is_active TINYINT DEFAULT 1, updated_by BIGINT NULL, updated_at DATETIME NULL
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_reports (
    id BIGINT PRIMARY KEY, place_id BIGINT, target_scope VARCHAR(16),
    target_source VARCHAR(64), target_id BIGINT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_answers (
    id BIGINT PRIMARY KEY, report_id BIGINT, question_key VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_photos (
    id BIGINT PRIMARY KEY, report_id BIGINT, photo_path VARCHAR(1024)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_moderation_events (
    id BIGINT PRIMARY KEY, report_id BIGINT
) ENGINE=InnoDB');
$db->exec("CREATE TRIGGER scout_reports_bd_immutable
    BEFORE DELETE ON scout_reports FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Scout Report history is append-only'");

$db->exec('INSERT INTO places VALUES (1),(2)');
$db->exec("INSERT INTO place_campsites VALUES
    (11,1,NULL,'Campsite',NULL,NULL,NULL,1),
    (12,1,NULL,'Populated Site',800,37.1234567,-107.1234567,1),
    (13,1,NULL,'Keep Site',NULL,37.1000000,-107.1000000,1),
    (21,2,NULL,'Other Place Site',NULL,NULL,NULL,1)");
$db->exec('INSERT INTO place_campsite_facts VALUES (12,4),(13,2)');
$db->exec("INSERT INTO place_campsite_images VALUES
    (1,1,12,'/uploads/places/__site_delete_ci__/b.jpg'),
    (2,1,13,'/uploads/places/__site_delete_ci__/c.jpg')");
$db->exec("INSERT INTO place_images VALUES
    (1,1,'/uploads/places/__site_delete_ci__/b.jpg')");
$db->exec("INSERT INTO place_site_visibility VALUES
    (1,12,'paid'),(1,13,'free')");
$db->exec("INSERT INTO place_pricing_reviews VALUES (1,12,NULL),(1,13,NULL)");
foreach (['place_report_current_answers','place_report_scoped_answers','place_season_answers'] as $table) {
    $db->exec("INSERT INTO `$table` VALUES
        (1,'site','campsite_record',12,'site_length_feet'),
        (1,'site','campsite_record',13,'site_length_feet')");
}
$db->exec("INSERT INTO place_season_site_status VALUES
    (1,'campsite_record',12),(1,'campsite_record',13)");
$db->exec("INSERT INTO place_map_features
    (id,place_id,feature_type,is_active) VALUES
    (800,1,'camping_site',1),(801,1,'place_boundary',1)");
$db->exec("INSERT INTO scout_reports VALUES
    (91,1,'site','campsite_record',12),
    (92,1,'site','campsite_record',13)");
$db->exec("INSERT INTO scout_report_answers VALUES
    (1,91,'site_length_feet'),(2,92,'site_length_feet')");
$db->exec("INSERT INTO scout_report_photos VALUES
    (1,91,'/uploads/places/__site_delete_ci__/b.jpg'),
    (2,92,'/uploads/places/__site_delete_ci__/c.jpg')");
$db->exec('INSERT INTO scout_report_moderation_events VALUES (1,91),(2,92)');
$doc = llama_pricing_empty();
$doc['rows'] = [
    ['id'=>'b','name'=>'B-only','match'=>['site_ids'=>[12],'site_selection'=>true],'price'=>20],
    ['id'=>'c','name'=>'C-only','match'=>['site_ids'=>[13],'site_selection'=>true],'price'=>35],
];
$doc = llama_pricing_validate($doc);
$db->prepare('INSERT INTO place_rules VALUES (1,?)')
    ->execute([json_encode($doc, JSON_THROW_ON_ERROR)]);

// A blank-GPS Site must be deletable without any history migration.
llama_site_delete_permanently($db, 1, 11, 1);
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_campsites WHERE id = 11') === 0,
    'Blank-GPS Site still exists');

// Before migration, a Site with protected history MUST remain untouched.
$blocked = false;
try {
    llama_site_delete_permanently($db, 1, 12, 1);
} catch (InvalidArgumentException $exception) {
    $blocked = str_contains($exception->getMessage(), 'permanent-site-deletion.sql');
}
ensure_site_deleted($blocked, 'Protected history deletion was not blocked before migration');
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_campsites WHERE id = 12') === 1,
    'Protected Site was partially deleted before migration');

// Install the *actual* migration from this PR, including all four guards.
$migration = file_get_contents(dirname(__DIR__, 2)
    . '/sql/2026-11-14-permanent-site-deletion.sql');
$migration = preg_replace('/^DELIMITER\s+.*$/m', '', $migration);
foreach (explode('$$', $migration) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') $db->exec($statement);
}
ensure_site_deleted(llama_site_delete_history_ready($db),
    'History trigger migration did not install');

// Guard test: ordinary history deletion must still fail.
$guarded = false;
try {
    $db->exec('DELETE FROM scout_reports WHERE id = 92');
} catch (PDOException) {
    $guarded = true;
}
ensure_site_deleted($guarded, 'Ordinary Site history deletes are no longer protected');

// Actual deletion of Site 12 with photos, scoped answers, and immutable history.
$result = llama_site_delete_permanently($db, 1, 12, 1);
ensure_site_deleted($result['site_id'] === 12 && $result['reports_deleted'] === 1,
    'Unexpected deletion result');
foreach ([
    ['place_campsites','id'], ['place_campsite_facts','campsite_id'],
    ['place_campsite_images','campsite_id'], ['place_site_visibility','campsite_id'],
    ['place_pricing_reviews','site_id'],
] as [$table,$column]) {
    ensure_site_deleted(site_delete_count($db,
        "SELECT COUNT(*) FROM `$table` WHERE `$column` = 12") === 0,
        "$table retains deleted Site #12");
}
foreach (['place_report_current_answers','place_report_scoped_answers',
           'place_season_answers','place_season_site_status'] as $table) {
    ensure_site_deleted(site_delete_count($db,
        "SELECT COUNT(*) FROM `$table` WHERE target_id = 12") === 0,
        "$table retains deleted Site #12");
}
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM scout_reports WHERE id = 91') === 0,
    'Deleted Site retains immutable report');
foreach (['scout_report_answers','scout_report_photos','scout_report_moderation_events'] as $table) {
    ensure_site_deleted(site_delete_count($db,
        "SELECT COUNT(*) FROM `$table` WHERE report_id = 91") === 0,
        "Deleted Site retains history in $table");
}
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_map_features WHERE id = 800 AND is_active = 0') === 1,
    'Legacy Site map feature was not retired');
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_map_features WHERE id = 801 AND is_active = 1') === 1,
    'Parent Place boundary was damaged');
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_campsites WHERE id IN (13,21)') === 2,
    'Unrelated Sites were deleted');
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM scout_reports WHERE id = 92') === 1,
    'Another Site lost its immutable report');
ensure_site_deleted(site_delete_count($db,
    'SELECT COUNT(*) FROM place_images WHERE id = 1') === 1,
    'A shared Place photo was removed');
$stmt = $db->query('SELECT rate_cards_json FROM place_rules WHERE place_id = 1');
$remainingRows = json_decode((string) $stmt->fetchColumn(), true)['rows'];
ensure_site_deleted(count($remainingRows) === 1 && $remainingRows[0]['id'] === 'c'
    && ($remainingRows[0]['match']['site_ids'] ?? []) === [13],
    'Site-specific pricing cleanup affected another Site');
echo "PASS: real MariaDB Site deletion (empty and populated), history guards, photos, scoped data, pricing, rollback, and Site isolation\n";

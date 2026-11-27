<?php

declare(strict_types=1);

// This test must NEVER run against a live or remotely named database.
$dsn = (string) getenv('LLAMA_SITE_SUBMISSION_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_site_submission_test(?:;|$)/', $dsn)) {
    throw new RuntimeException('Disposable llama_site_submission_test DSN required.');
}
function llama_contributor_can(PDO $db, int $userId, string $capability): bool
{
    return $userId === 7 && $capability === 'submit_place';
}
function community_role_at_submission(int $userId): string
{
    return $userId === 7 ? 'scout' : 'user';
}
require_once dirname(__DIR__, 2) . '/app/site-submissions.php';

$db = new PDO(
    $dsn,
    (string) getenv('LLAMA_SITE_SUBMISSION_TEST_USER'),
    (string) getenv('LLAMA_SITE_SUBMISSION_TEST_PASSWORD'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$check = static function (bool $okay, string $message): void {
    if (!$okay) {
        throw new RuntimeException('FAIL: ' . $message);
    }
};
$failure = static function (callable $run, string $message) use ($check): void {
    $failed = false;
    try {
        $run();
    } catch (InvalidArgumentException | RuntimeException $e) {
        $failed = true;
    }
    $check($failed, $message);
};

// Apply the real site migration to test the actual schema.
$sql = (string) file_get_contents(dirname(__DIR__, 2)
    . '/tools/migrations/20261118_standalone_site_submissions.sql');
$db->exec($sql);
$db->exec((string) file_get_contents(dirname(__DIR__, 2) . '/tools/migrations/20261127_site_pending_parent.sql'));
$db->exec('CREATE TABLE place_submissions (
    id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED, place_id BIGINT UNSIGNED NULL,
    place_name VARCHAR(150), status VARCHAR(30), submission_data LONGTEXT
) ENGINE=InnoDB');
$db->exec('CREATE TABLE places (
    id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(150), status VARCHAR(30),
    type VARCHAR(60), state VARCHAR(60), place_report_answer_state LONGTEXT NULL
) ENGINE=InnoDB');
// Parent context now reads canonical published Place answers. Build the
// legacy read fixture from the actual storage adapter (nullable columns),
// not a second manually maintained list of legacy answer fields.
$legacyTables = [];
foreach (llama_place_report_published_definitions() as $definition) {
    $table = $definition['table'];
    $column = $definition['column'];
    if (!llama_place_report_valid_storage_identifier($table)
        || !llama_place_report_valid_storage_identifier($column)) {
        throw new RuntimeException('Invalid legacy fixture identifier');
    }
    $legacyTables[$table][$column] = true;
}
foreach ($legacyTables as $table => $columns) {
    if ($table === 'places') {
        foreach (array_diff_key($columns, array_fill_keys(['id', 'name', 'type', 'status', 'state', 'place_report_answer_state'], true)) as $column => $_) {
            $db->exec("ALTER TABLE places ADD COLUMN `$column` TEXT NULL");
        }
        continue;
    }
    $definitions = ['place_id BIGINT UNSIGNED NOT NULL'];
    if ($table === 'place_sensory') {
        $definitions[] = 'period VARCHAR(30)';
    }
    foreach (array_diff_key($columns, array_fill_keys(['place_id', 'period'], true)) as $column => $_) {
        $definitions[] = "`$column` TEXT NULL";
    }
    $db->exec("CREATE TABLE `$table` (" . implode(', ', $definitions) . ') ENGINE=InnoDB');
}
$db->exec('CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY, display_name VARCHAR(150), username VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    site_code VARCHAR(100) NULL, site_name VARCHAR(120),
    latitude DECIMAL(10,7) NULL, longitude DECIMAL(11,7) NULL,
    map_feature_id BIGINT UNSIGNED NULL,
    raw_site_type VARCHAR(150), source_provider VARCHAR(80),
    source_external_id VARCHAR(160), is_active TINYINT DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsite_area_links (
    place_id BIGINT UNSIGNED, campsite_id BIGINT UNSIGNED, area_feature_id BIGINT UNSIGNED
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_features (
    id BIGINT UNSIGNED PRIMARY KEY, place_id BIGINT UNSIGNED,
    feature_type VARCHAR(40), label VARCHAR(150),
    metadata_json LONGTEXT, is_active TINYINT DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_map_camping_sites (
    feature_id BIGINT UNSIGNED, camping_area_feature_id BIGINT UNSIGNED,
    site_code VARCHAR(100)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_scoped_answers (
    place_id BIGINT UNSIGNED, scope_type VARCHAR(16), scope_source VARCHAR(40),
    scope_id BIGINT UNSIGNED, field_key VARCHAR(150), value_json LONGTEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scoped (place_id,scope_type,scope_source,scope_id,field_key)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_report_current_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT UNSIGNED, target_scope VARCHAR(16), target_source VARCHAR(40),
    target_id BIGINT UNSIGNED, question_key VARCHAR(150), answer_state VARCHAR(24),
    value_json LONGTEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_current (place_id,target_scope,target_source,target_id,question_key)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_schemas (
    schema_version CHAR(64) PRIMARY KEY, definition_json LONGTEXT NOT NULL,
    created_at DATETIME
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    logical_report_key VARCHAR(180), revision_number INT,
    prior_revision_id BIGINT NULL, amends_report_id BIGINT NULL,
    submission_bundle_key VARCHAR(180), place_id BIGINT UNSIGNED,
    target_scope VARCHAR(16), target_source VARCHAR(40),
    target_id BIGINT UNSIGNED, target_label VARCHAR(200),
    contributor_id BIGINT UNSIGNED, role_at_submission VARCHAR(50),
    visit_date DATE, submitted_at DATETIME,
    schema_version CHAR(64), source_channel VARCHAR(32),
    client_submission_key VARCHAR(220), content_sha256 CHAR(64),
    UNIQUE KEY uniq_client (contributor_id,client_submission_key)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED, question_key VARCHAR(150),
    answer_state VARCHAR(24), value_json LONGTEXT, created_at DATETIME
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED, photo_path VARCHAR(512),
    photo_path_sha256 CHAR(64), sort_order INT,
    photo_snapshot_json LONGTEXT, created_at DATETIME
) ENGINE=InnoDB');
$db->exec('CREATE TABLE scout_report_moderation_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED, event_type VARCHAR(32),
    actor_user_id BIGINT UNSIGNED, reason TEXT,
    metadata_json LONGTEXT, created_at DATETIME
) ENGINE=InnoDB');
$db->exec("INSERT INTO places (id,name,status,type) VALUES (1, 'Parent Place', 'active', 'developed-campground'),
    (2, 'Unrelated Place', 'active', 'public-parking')");
$db->exec("INSERT INTO place_amenities (place_id,wifi,toilet_type) VALUES (1,1,'flush')");
$db->exec("INSERT INTO users VALUES (7, 'Scout', 'scout'), (8, 'Another Member', 'guest')");
$db->exec("INSERT INTO place_campsites (id,place_id,site_code,site_name,raw_site_type)
    VALUES (55,1,'5','Existing Site','Llama Scout Site')");
$db->exec("INSERT INTO place_report_current_answers
    (place_id,target_scope,target_source,target_id,question_key,answer_state,value_json)
    VALUES (1,'place','place',1,'description','answered','\"Original Place answer\"'),
           (1,'site','campsite_record',55,'site_number','answered','\"5\"')");

$context = llama_site_parent_report_context($db, 1);
$check(($context['type'] ?? '') === 'developed-campground'
    && ($context['amenity_wifi'] ?? '') === '1'
    && ($context['amenity_toilets'] ?? '') === 'flush',
    'Site context must include real parent type and published amenity values.');
$db->exec("INSERT INTO place_report_current_answers
    (place_id,target_scope,target_source,target_id,question_key,answer_state,value_json)
    VALUES (1,'place','place',1,'amenity_wifi','unknown',NULL)");
$context = llama_site_parent_report_context($db, 1);
$check(($context['amenity_wifi'] ?? '') === llama_place_report_unknown_token(),
    'Explicit current Unknown must override legacy shared WiFi Yes.');
$db->exec("DELETE FROM place_report_current_answers WHERE place_id=1 AND target_scope='place'
    AND question_key='amenity_wifi'");

$payload = [
    'site_place_id' => 1,
    'site_name' => 'North Campsite',
    'site_coordinates' => '37.2522200, -107.2192000',
    'visited_at' => '2026-11-17',
    'site_answers' => [
        'site_number' => '12',
        'site_wifi_rating' => '4',
        'accessible_toilet' => '1',
        'visited_at' => '2026-11-17',
    ],
];
$siteSubmission = llama_site_submission_submit($db, 7, $payload);
$check($siteSubmission > 0, 'Site pending submission must be created.');
$check(count(llama_site_submission_queue($db)) === 1, 'Site queue should contain a pending submission.');
$check((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 1,
    'Submitting a Site must not publish a Site until approval.');

$failure(static fn () => llama_site_submission_moderate(
    $db, $siteSubmission, 9, 'needs-changes'
), 'Requesting changes without notes must fail.');
llama_site_submission_moderate($db, $siteSubmission, 9, 'needs-changes', 'Verify Site number.');
$check(count(llama_site_submission_queue($db)) === 0,
    'A needs-changes Site must leave the moderator approval queue until resubmitted.');
$check((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 1,
    'Requesting changes must not publish a Site.');

$revision = llama_site_submission_for_user($db, 7, $siteSubmission);
$check((string) $revision['status'] === 'needs-changes',
    'Original contributor must be able to reopen requested changes.');
$check(llama_site_submission_for_user($db, 8, $siteSubmission) === null,
    'Other members must never read this Site submission.');
$failure(static fn () => llama_site_submission_resubmit(
    $db, 8, $siteSubmission, $payload
), 'Other members must never resubmit an owned Site.');
$failure(static fn () => llama_site_submission_resubmit(
    $db, 7, $siteSubmission, array_replace($payload, ['site_place_id' => 2])
), 'A Site revision must not change the parent Place.');
$revisionPayload = array_replace($payload, ['site_name' => 'North Campsite revised']);
$check(llama_site_submission_resubmit($db, 7, $siteSubmission, $revisionPayload)
    === $siteSubmission, 'A revision must preserve its original submission ID.');
$check(count(llama_site_submission_queue($db)) === 1,
    'A resubmitted Site should return to the pending moderator queue.');

$siteId = llama_site_submission_moderate($db, $siteSubmission, 9, 'approved', 'Verified.');
$check($siteId > 55, 'Approved Site must have its own new identity.');
$check((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 2,
    'Exactly one additional Site should exist after approval.');

$stmt = $db->prepare('SELECT place_id,site_code,site_name,latitude,longitude
    FROM place_campsites WHERE id = ?');
$stmt->execute([$siteId]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);
$check((int) $site['place_id'] === 1 && $site['site_code'] === '12'
    && $site['site_name'] === 'North Campsite revised',
    'Approval must persist a single Site under the correct parent.');
$check(round((float) $site['latitude'], 7) === 37.25222
    && round((float) $site['longitude'], 7) === -107.2192,
    'Approved child coordinates must stay independent.');

$scoped = llama_scoped_report_load($db, 1, 'site', $siteId, 'campsite_record');
$check(($scoped['site_number'] ?? '') === '12',
    'The approved Site number was not published in current answers.');
$check((string) ($scoped['site_wifi_rating'] ?? '') === '4'
    && (string) ($scoped['accessible_toilet'] ?? '') === '1',
    'Applicable inherited-service child observations must survive approval.');
$other = llama_site_submission_prepare($db, array_replace($payload, ['site_place_id' => 2]));
$check(!isset($other['answers']['site_wifi_rating']) && !isset($other['answers']['accessible_toilet'])
    && !isset($other['answers']['site_number']),
    'Changing parent type must exclude inapplicable Site observations.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM scout_reports WHERE target_scope = "site"'
)->fetchColumn() === 1, 'One Site approval must record one immutable Site report.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM place_report_current_answers WHERE target_scope = "place"'
)->fetchColumn() === 1, 'The parent Place report must remain untouched.');
$check((string) $db->query(
    'SELECT value_json FROM place_report_current_answers
     WHERE target_scope = "site" AND target_id = 55 AND question_key = "site_number"'
)->fetchColumn() === '"5"', 'Sibling Site answers must remain untouched.');

$failure(static fn () => llama_site_submission_moderate(
    $db, $siteSubmission, 9, 'approved'
), 'The same approved Site must not be approved twice.');

$duplicate = llama_site_submission_submit($db, 7, $payload);
$failure(static fn () => llama_site_submission_moderate(
    $db, $duplicate, 9, 'approved'
), 'Duplicate Site number must block approval.');
$check((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 2,
    'Duplicate approval must roll back the attempted child Site.');
$stmt = $db->prepare('SELECT status FROM place_site_submissions WHERE id = ?');
$stmt->execute([$duplicate]);
$check($stmt->fetchColumn() === 'pending', 'Failed approval must leave submission pending.');

$rejected = llama_site_submission_submit($db, 7, array_replace($payload, [
    'site_name' => 'Not published',
    'site_answers' => ['site_number' => '99'],
]));
llama_site_submission_moderate($db, $rejected, 9, 'rejected', 'Wrong Site.');
$check((int) $db->query('SELECT COUNT(*) FROM place_campsites')->fetchColumn() === 2,
    'Reject must not publish any Site.');
$check((int) $db->query('SELECT COUNT(*) FROM scout_reports')->fetchColumn() === 1,
    'Only approved Sites may create immutable Scout Reports.');

// Published Place ID 1 and pending submission ID 1 intentionally collide.
$pendingInsert = $db->prepare('INSERT INTO place_submissions
    (id,user_id,place_id,place_name,status,submission_data) VALUES (?,?,NULL,?,?,?)');
$pendingData = json_encode(llama_place_report_build_data([
    'name'=>'Pending campground','type'=>'developed-campground','state'=>'CO',
    'amenity_wifi'=>'1','amenity_toilets'=>'flush',
]), JSON_THROW_ON_ERROR);
$pendingInsert->execute([1,7,'Pending campground','pending',$pendingData]);
$pendingInsert->execute([2,8,'Pending private campground','pending',$pendingData]);
$pendingInsert->execute([3,7,'Pending rejected campground','rejected',$pendingData]);
$pendingInsert->execute([4,7,'Pending revise campground','needs-changes',$pendingData]);
$choices = llama_site_parent_place_choices($db, 'Pending', 7);
$ids = array_column($choices, 'id');
$check($ids === ['pending:1','pending:4'], 'Search includes owned pending and needs-changes parents only.');
$check(count(llama_site_parent_place_choices($db, 'Parent Place', 7)) === 1,
    'Published parent remains searchable.');
$failure(static fn () => llama_site_parent_reference($db, 'pending:2', 7), 'Other member pending parent must be private.');
$failure(static fn () => llama_site_parent_reference($db, 'pending:3', 7), 'Rejected parent must be unavailable.');
$pendingContext = llama_site_parent_reference($db, 'pending:1', 7)['context'];
$check($pendingContext['type'] === 'developed-campground'
    && ($pendingContext['amenity_wifi'] ?? '') === '1'
    && ($pendingContext['amenity_toilets'] ?? '') === 'flush',
    'Pending parent context derives from the canonical stored submission.');
$pendingPayload = array_replace($payload, ['site_place_id'=>'pending:1','site_name'=>'Waiting Site',
    'site_answers'=>['site_number'=>'21']]);
$waiting = llama_site_submission_submit($db, 7, $pendingPayload);
$row = llama_site_submission_for_user($db, 7, $waiting);
$check($row['place_id'] === null && (int) $row['parent_submission_id'] === 1,
    'Pending parent is not confused with a published Place of the same ID.');
$check(in_array($waiting, array_map('intval',array_column(llama_site_submission_queue($db),'id')),true),
    'Waiting Site remains visible to moderators.');
$failure(static fn () => llama_site_submission_moderate($db, $waiting, 9, 'approved'),
    'Site cannot publish before parent approval.');
llama_site_submission_moderate($db, $waiting, 9, 'needs-changes', 'Verify the Site name.');
$check(llama_site_submission_resubmit($db, 7, $waiting, $pendingPayload) === $waiting,
    'Owned Site can be revised while parent is pending.');
$db->beginTransaction();
$db->exec("INSERT INTO places (id,name,status,type) VALUES (3,'Pending campground','active','developed-campground')");
$db->exec("UPDATE place_submissions SET place_id=3,status='approved' WHERE id=1");
llama_site_link_approved_parent($db,1,7,3);
$db->commit();
$row = llama_site_submission_for_user($db, 7, $waiting);
$check((int) $row['place_id'] === 3 && $row['status'] === 'pending',
    'Parent approval attaches the correct Place without approving the child.');
$check(llama_site_parent_reference($db, 'pending:1', 7)['place_id'] === 3,
    'Stale owned pending reference follows approval.');
$waitingSite = llama_site_submission_moderate($db, $waiting, 9, 'approved');
$stmt = $db->prepare('SELECT place_id FROM place_campsites WHERE id=?');
$stmt->execute([$waitingSite]);
$check((int) $stmt->fetchColumn() === 3, 'Site publishes only under the newly approved Parent Place.');

echo "PASS: Site submit, revision, rejection, one-target approval, immutable/current answers, duplicates and rollback in MariaDB.\n";

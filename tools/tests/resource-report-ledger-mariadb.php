<?php

declare(strict_types=1);

/**
 * Real MariaDB 10.11 integration for the proposed Resource Scout Report ledger.
 * It refuses ANY non-disposable database. This test never touches production.
 */
$dsn = (string) getenv('LLAMA_RESOURCE_LEDGER_TEST_DSN');
if (!preg_match('/(?:^|;)dbname=llama_resource_ledger_test(?:;|$)/', $dsn)) {
    throw new RuntimeException('Disposable llama_resource_ledger_test DSN required.');
}
$db = new PDO(
    $dsn,
    (string) getenv('LLAMA_RESOURCE_LEDGER_TEST_USER'),
    (string) getenv('LLAMA_RESOURCE_LEDGER_TEST_PASSWORD'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
require_once dirname(__DIR__, 2) . '/app/resource-report-history-candidate.php';

$check = static function (bool $condition, string $why): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $why);
    }
};
$reject = static function (callable $operation, string $why) use ($check): void {
    try {
        $operation();
    } catch (PDOException) {
        return;
    }
    $check(false, $why);
};
$apply = static function (string $path) use ($db): void {
    $sql = (string) file_get_contents($path);
    // Single-statement triggers in this proposed schema intentionally avoid
    // DELIMITER and BEGIN/END, which also makes phpMyAdmin paste straightforward.
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        if (trim($statement) !== '') {
            $db->exec($statement);
        }
    }
};

$root = dirname(__DIR__, 2);
$apply($root . '/tools/migrations/20261101_v3_003_resources.sql');
$apply($root . '/tools/migrations/20261108_resource_submissions.sql');
// A sentinel in the old Place/Site/Area ledger verifies the new migration
// and test never mutate the existing reporting subsystem.
$db->exec('CREATE TABLE scout_reports (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO scout_reports (id) VALUES (1)');
$migration = $root . '/tools/migrations/20261118_resource_scout_report_ledger.sql';
$apply($migration);
$apply($migration); // No duplicate tables/triggers on rerun.

$tables = [
    'resource_scout_report_schemas', 'resource_scout_reports',
    'resource_scout_report_answers', 'resource_scout_report_events',
    'resource_scout_report_photos', 'resource_scout_current_answers',
];
$existingTables = $db->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name LIKE 'resource_scout_%'"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $name) {
    $check(in_array($name, $existingTables, true),
        'Proposed Resource ledger table is missing: ' . $name);
}
$triggerCount = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.triggers
     WHERE trigger_schema = DATABASE()
       AND trigger_name LIKE 'rs_%_immutable_%'"
)->fetchColumn();
$check($triggerCount === 10,
    'Exactly ten immutable UPDATE/DELETE triggers are required, not duplicates.');

$db->exec(
    "INSERT INTO resources
        (resource_type,name,latitude,longitude,status)
     VALUES ('restroom_facility','Standalone Resource',37.1,-107.2,'available'),
            ('restroom_facility','Linked Facility',37.2,-107.3,'available')"
);
$db->exec(
    "INSERT INTO resource_submissions
        (submitted_by,name,form_data,status)
     VALUES (7,'Standalone Resource','{}','approved'),
            (7,'Linked Facility','{}','approved')"
);

$schema = llama_resource_history_candidate_schema();
$insertSchema = $db->prepare(
    'INSERT INTO resource_scout_report_schemas (schema_version, definition_json) VALUES (?, ?)'
);
$insertSchema->execute([$schema['schema_version'], $schema['definition_json']]);

$insertReport = $db->prepare(
    'INSERT INTO resource_scout_reports
        (resource_id,target_kind,parent_place_id,submission_id,logical_report_key,
         contributor_id,role_at_submission,submitted_at,reviewed_by,reviewed_at,
         schema_version,client_submission_key,content_sha256)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$writeReport = static function (
    int $resourceId,
    string $kind,
    ?int $parentId,
    int $submissionId
) use ($insertReport, $db, $schema): int {
    $client = 'submission:' . $submissionId . ':' . $kind . ':resource_record:' . $resourceId;
    $insertReport->execute([
        $resourceId, $kind, $parentId, $submissionId,
        'resource-submission:' . $submissionId . ':' . $kind . ':resource_record:' . $resourceId,
        7, 'scout', '2026-11-18 11:00:00', 8, '2026-11-18 12:00:00',
        $schema['schema_version'], $client, hash('sha256', $client),
    ]);
    return (int) $db->lastInsertId();
};

$resourceReport = $writeReport(1, 'resource', null, 1);
$facilityReport = $writeReport(2, 'facility', 42, 2);
$check($resourceReport > 0 && $facilityReport > $resourceReport,
    'Both standalone Resource and linked Facility must have distinct report identities.');

$reject(static fn () => $writeReport(1, 'facility', null, 1),
    'A Facility must require a parent Place snapshot.');
$reject(static fn () => $writeReport(1, 'resource', 42, 1),
    'A standalone Resource cannot pretend to have a parent Place.');
$reject(static fn () => $writeReport(1, 'area', null, 1),
    'Mapped Area cannot enter Resource-backed Facility history.');
$reject(static fn () => $writeReport(1, 'resource', null, 1),
    'The same Resource submission must never be recorded twice.');
$reject(static fn () => $writeReport(999, 'resource', null, 1),
    'A Resource report must point at an existing Resource record.');

$insertAnswer = $db->prepare(
    'INSERT INTO resource_scout_report_answers
        (report_id,service_type,group_key,question_key,canonical_question_key,
         answer_state,value_json,legacy_value_json)
     VALUES (?,?,?,?,?,?,?,?)'
);
$insertAnswer->execute([
    $resourceReport,'toilet','men','facility_cleanliness','facility_cleanliness',
    'answered','"3"','"3"',
]);
$insertAnswer->execute([
    $resourceReport,'toilet','women','facility_cleanliness','facility_cleanliness',
    'answered','"5"','"5"',
]);
$insertAnswer->execute([
    $resourceReport,'toilet','all_gender','facility_cleanliness','facility_cleanliness',
    'unknown','null','"unknown"',
]);
$insertAnswer->execute([
    $resourceReport,'fuel','general','service_fuel_grades',null,
    'answered','["diesel","85"]','["diesel","85"]',
]);
$insertAnswer->execute([
    $facilityReport,'toilet','men','facility_cleanliness','facility_cleanliness',
    'answered','"2"','"2"',
]);
$check((int) $db->query('SELECT COUNT(*) FROM resource_scout_report_answers')->fetchColumn() === 5,
    'Repeat question IDs across restroom groups and Resource/Facility must remain separate.');

$reject(static fn () => $insertAnswer->execute([
    $resourceReport,'toilet','men','facility_cleanliness','facility_cleanliness',
    'answered','"4"','"4"',
]), 'The exact same answer occurrence cannot be inserted twice.');
$reject(static fn () => $insertAnswer->execute([
    $resourceReport,'toilet','men','facility_soap','facility_soap',
    'unanswered','null','null',
]), 'Unanswered belongs to the mutable current view, not immutable history.');

$read = $db->query(
    "SELECT group_key,value_json FROM resource_scout_report_answers
     WHERE report_id = $resourceReport
       AND service_type = 'toilet' AND question_key = 'facility_cleanliness'
     ORDER BY group_key"
)->fetchAll(PDO::FETCH_ASSOC);
$found = [];
foreach ($read as $record) {
    $found[$record['group_key']] = json_decode((string) $record['value_json'], true);
}
$check($found === ['all_gender' => null, 'men' => '3', 'women' => '5'],
    'Repeated restroom observations and Unknown must be preserved separately.');

$db->prepare(
    'INSERT INTO resource_scout_report_events
        (report_id,event_type,actor_user_id,reason,metadata_json)
     VALUES (?, ?, ?, ?, ?)'
)->execute([$resourceReport,'approved',8,'Verified','{}']);
$db->prepare(
    'INSERT INTO resource_scout_report_photos
        (report_id,photo_path,photo_path_sha256,sort_order,photo_snapshot_json)
     VALUES (?,?,?,?,?)'
)->execute([$resourceReport,'/uploads/resources/1/photos/test.jpg',str_repeat('f', 64),0,'{}']);

$immutables = [
    ['resource_scout_report_schemas', 'schema_version'],
    ['resource_scout_reports', 'id'],
    ['resource_scout_report_answers', 'id'],
    ['resource_scout_report_events', 'id'],
    ['resource_scout_report_photos', 'id'],
];
foreach ($immutables as [$table, $key]) {
    $reject(
        static fn () => $db->exec("UPDATE $table SET $key = $key LIMIT 1"),
        'Immutable UPDATE trigger must prevent editing ' . $table
    );
    $reject(
        static fn () => $db->exec("DELETE FROM $table LIMIT 1"),
        'Immutable DELETE trigger must prevent deleting ' . $table
    );
}

$upsert = $db->prepare(
    'INSERT INTO resource_scout_current_answers
        (resource_id,target_kind,service_type,group_key,question_key,
         answer_state,value_json,source_report_id)
     VALUES (?,?,?,?,?,?,?,?)'
);
$upsert->execute([
    1,'resource','toilet','men','facility_cleanliness','answered','"3"',$resourceReport,
]);
$upsert->execute([
    2,'facility','toilet','men','facility_cleanliness','answered','"2"',$facilityReport,
]);
$upsert->execute([
    1,'resource','toilet','all_gender','facility_cleanliness','unknown','null',$resourceReport,
]);
$db->exec(
    "UPDATE resource_scout_current_answers
     SET answer_state='unanswered',value_json='null',row_version=row_version + 1
     WHERE resource_id=1 AND group_key='men'"
);
$check(
    (int) $db->query(
        "SELECT row_version FROM resource_scout_current_answers
         WHERE resource_id=1 AND group_key='men'"
    )->fetchColumn() === 2,
    'Mutable Resource current answer must support versioned edits and tombstones.'
);
$check(
    (string) $db->query(
        "SELECT answer_state FROM resource_scout_current_answers
         WHERE resource_id=2 AND group_key='men'"
    )->fetchColumn() === 'answered',
    'Changing Resource current answers must not change linked Facility answers.'
);
$reject(static fn () => $upsert->execute([
    1,'resource','toilet','men','facility_cleanliness','answered','"4"',$resourceReport,
]), 'A current answer occurrence must be unique by Resource/Service/group/question.');

$check((int) $db->query('SELECT COUNT(*) FROM scout_reports')->fetchColumn() === 1,
    'Existing Place/Site/Area report ledger must never be touched by this test.');

echo "PASS: MariaDB Resource/Facility schema, one-target identity, composite answers, immutability, idempotency and current states.\n";

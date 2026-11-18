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
require_once dirname(__DIR__, 2) . '/app/resource-report-history-writer.php';

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
$apply($root . '/tools/migrations/20261115_facility_parent_place.sql');
$apply($root . '/tools/migrations/20261115_resource_facility_experience.sql');
$db->exec('CREATE TABLE places (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO places (id) VALUES (42)');
// A sentinel in the old Place/Site/Area ledger verifies the new migration
// and test never mutate the existing reporting subsystem.
$db->exec('CREATE TABLE scout_reports (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO scout_reports (id) VALUES (1)');
$migration = $root . '/tools/migrations/20261118_resource_scout_report_ledger.sql';
$apply($migration);
$apply($migration); // No duplicate tables/triggers on rerun.

require_once $root . '/app/resource-ledger-readiness.php';
$beforeReadyCounts = (int) $db->query('SELECT COUNT(*) FROM resource_scout_reports')->fetchColumn();
$installation = llama_resource_ledger_readiness($db);
$check($installation['schema_ready']
    && $installation['status'] === 'schema_verified'
    && count($installation['checks']) === 10
    && $installation['activation_approved'] === false
    && $installation['flags']['history_writes_enabled'] === false
    && $installation['flags']['current_reads_enabled'] === false,
    'Read-only readiness must verify all six tables, triggers, keys, indexes '
    . 'and constraints without claiming activation or enabling flags.');
$check($installation['counts']['resource_scout_reports'] === 0
    && $installation['integrity']['current_answer_foreign_target'] === 0
    && $installation['integrity']['current_answer_wrong_kind'] === 0
    && (int) $db->query('SELECT COUNT(*) FROM resource_scout_reports')->fetchColumn()
        === $beforeReadyCounts,
    'Inspecting a new installed schema must be read-only and have zero history.');
$adminReadiness = (string) file_get_contents($root . '/admin/resource-ledger-readiness.php');
$resourcesAdmin = (string) file_get_contents($root . '/admin/resources.php');
$check(str_contains($adminReadiness, 'moderation_require_admin()')
    && str_contains($adminReadiness, 'llama_resource_ledger_readiness(db())')
    && str_contains($resourcesAdmin, '/resource-ledger-readiness.php')
    && !str_contains($adminReadiness, 'LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1'),
    'Only authenticated Admins can inspect the schema, and no UI path enables history.');

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

// ---- The guarded production-shaped history writer stays DISCONNECTED from
// llama_resource_review() until the migration is manually installed and
// deployed. Exercise it here against this disposable MariaDB only.
$db->exec(
    "INSERT INTO resources
        (resource_type,name,latitude,longitude,status,parent_place_id,
         created_by,experience_json)
     VALUES ('restroom_facility','Reviewed Rest Stop',37.4,-107.5,'available',
             NULL,7,
             '{\"toilet\":{\"men\":{\"facility_cleanliness\":\"3\"},
                          \"women\":{\"facility_cleanliness\":\"5\"}},
               \"fuel\":{\"general\":{\"service_fuel_grades\":[\"diesel\"],
                                    \"service_fuel_pumps\":\"0\"}}}'),
            ('restroom_facility','Reviewed Camp Facility',37.5,-107.6,'available',
             42,7,
             '{\"toilet\":{\"men\":{\"facility_cleanliness\":\"2\"},
                          \"women\":{\"facility_cleanliness\":\"4\"}}}')"
);
$db->exec(
    "INSERT INTO resource_services (resource_id,service_type,status)
     VALUES (3,'toilet','available'),(3,'fuel','available'),(4,'toilet','available')"
);
$db->exec(
    "INSERT INTO resource_submissions
        (submitted_by,name,target_resource_id,form_data,status)
     VALUES (7,'Reviewed Rest Stop',NULL,'{}','pending'),
            (7,'Reviewed Camp Facility',NULL,'{}','pending')"
);

$writerFile = $root . '/app/resource-report-history-writer.php';
$writerSource = (string) file_get_contents($writerFile);
$reviewSource = (string) file_get_contents($root . '/app/resource-submissions.php');
$check(str_contains($reviewSource, 'if (llama_resource_ledger_writer_enabled())')
    && str_contains($reviewSource, 'llama_resource_ledger_record_approved(')
    && str_contains($reviewSource, '$selectedPhotos,')
    && str_contains($reviewSource, '$copiedPhotoPaths')
    && strpos($reviewSource, 'llama_resource_ledger_record_approved(')
        < strpos($reviewSource, '$db->commit();', strpos($reviewSource, 'function llama_resource_review(')),
    'History must run only when enabled, after trusted photos and before moderation commit.');

$photoDir = $root . '/uploads/resources/3';
if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
    throw new RuntimeException('Could not create disposable photo evidence directory.');
}
$path = '/uploads/resources/3/history-writer-test.png';
$absolute = $root . $path;
file_put_contents($absolute, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9boWfrAAAAAASUVORK5CYII='
));
$submittedPhoto = [
    'path' => '/uploads/resource-submissions/3/historical-evidence.png',
    'alt' => 'Reviewed restroom',
];

$disabled = false;
try {
    llama_resource_ledger_record_approved($db, 3, 3, 8, 'Verified');
} catch (RuntimeException $error) {
    $disabled = str_contains($error->getMessage(), 'disabled');
}
$check($disabled, 'Without the explicit release flag, production-shaped writes must fail closed.');

putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1');
$noTransaction = false;
try {
    llama_resource_ledger_record_approved($db, 3, 3, 8, 'Verified');
} catch (RuntimeException $error) {
    $noTransaction = str_contains($error->getMessage(), 'transaction');
}
$check($noTransaction, 'Writer must require caller-owned Resource moderation transaction.');

$db->beginTransaction();
$rolledBack = llama_resource_ledger_record_approved(
    $db, 3, 3, 8, 'Verified', [$submittedPhoto], [$path]
);
$check($rolledBack > 0, 'Writer must stage history in the current transaction.');
$db->rollBack();
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_reports WHERE resource_id=3'
)->fetchColumn() === 0, 'Rolling back moderation must also remove the immutable report.');

$db->beginTransaction();
$reportOne = llama_resource_ledger_record_approved(
    $db, 3, 3, 8, 'Verified', [$submittedPhoto], [$path]
);
$retry = llama_resource_ledger_record_approved(
    $db, 3, 3, 8, 'Verified', [$submittedPhoto], [$path]
);
$check($retry === $reportOne, 'Same submission and content must return the same report.');
$reportTwo = llama_resource_ledger_record_approved(
    $db, 4, 4, 8, 'Verified Facility'
);
$db->commit();

// Every direct-writer approval stages current answers in the very same
// transaction. A retry of the same submission must not advance row versions.
$currentGet = static function (
    int $resourceId, string $service, string $group, string $key
) use ($db): ?array {
    $stmt = $db->prepare(
        'SELECT target_kind, answer_state, value_json, source_report_id,
                row_version
         FROM resource_scout_current_answers
         WHERE resource_id = ? AND service_type = ?
           AND group_key = ? AND question_key = ?'
    );
    $stmt->execute([$resourceId, $service, $group, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$menCurrent = $currentGet(3, 'toilet', 'men', 'facility_cleanliness');
$womenCurrent = $currentGet(3, 'toilet', 'women', 'facility_cleanliness');
$facilityCurrent = $currentGet(4, 'toilet', 'men', 'facility_cleanliness');
$fuelCurrent = $currentGet(3, 'fuel', 'general', 'service_fuel_pumps');
$check($menCurrent !== null
    && $menCurrent['answer_state'] === 'answered'
    && $menCurrent['value_json'] === '"3"'
    && (int) $menCurrent['source_report_id'] === $reportOne
    && (int) $menCurrent['row_version'] === 1
    && $womenCurrent['value_json'] === '"5"'
    && $facilityCurrent['target_kind'] === 'facility'
    && (int) $facilityCurrent['source_report_id'] === $reportTwo
    && $fuelCurrent['value_json'] === '"0"',
    'Initial current projection must preserve No/zero/restroom groups and separate Facility provenance.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_current_answers WHERE resource_id=3'
)->fetchColumn() === 4,
    'Resource 3 must have exactly four current answers after first approval.');
$check($reportTwo > $reportOne, 'Facility report must have a distinct identity.');

$rows = $db->query(
    'SELECT id,resource_id,target_kind,parent_place_id,revision_number,
            prior_report_id,visit_date,role_at_submission
     FROM resource_scout_reports WHERE resource_id IN (3,4) ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
$check(count($rows) === 2
    && $rows[0]['target_kind'] === 'resource'
    && $rows[0]['parent_place_id'] === null
    && $rows[1]['target_kind'] === 'facility'
    && (int) $rows[1]['parent_place_id'] === 42
    && $rows[0]['visit_date'] === null
    && $rows[0]['role_at_submission'] === 'not_recorded',
    'Standalone Resource and linked Facility identities/provenance must remain accurate.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_report_answers
     WHERE report_id IN (' . $reportOne . ',' . $reportTwo . ')'
)->fetchColumn() === 6,
    'Writer must preserve four Resource and two Facility compound question instances.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_report_photos WHERE report_id = ' . $reportOne
)->fetchColumn() === 1, 'Reviewed permanent photo must become immutable hash evidence.');
$fileHash = (string) $db->query(
    'SELECT JSON_UNQUOTE(JSON_EXTRACT(photo_snapshot_json,"$.file_sha256"))
     FROM resource_scout_report_photos WHERE report_id = ' . $reportOne
)->fetchColumn();
$check($fileHash === hash_file('sha256', $absolute),
    'Immutable photo evidence must hash the actual permanent file.');

$db->exec(
    "INSERT INTO resource_submissions
        (submitted_by,name,target_resource_id,form_data,status)
     VALUES (7,'Review Updated Rest Stop',3,'{}','pending'),
            (7,'Wrong Target Test',4,'{}','pending'),
            (7,'Retired Service Test',3,'{}','pending')"
);
$db->beginTransaction();
$wrongTarget = false;
try {
    llama_resource_ledger_record_approved($db, 3, 6, 8, 'Wrong Resource');
} catch (InvalidArgumentException) {
    $wrongTarget = true;
}
$check($wrongTarget,
    'An update to Facility 4 must not publish into Resource 3 even with the same contributor.');
$db->commit();

$db->exec(
    "UPDATE resources SET experience_json =
     '{\"toilet\":{\"men\":{\"facility_cleanliness\":\"4\"},
                  \"women\":{\"facility_cleanliness\":\"5\"}},
       \"fuel\":{\"general\":{\"service_fuel_grades\":[\"diesel\"],
                            \"service_fuel_pumps\":\"0\"}}}'
     WHERE id = 3"
);
$db->beginTransaction();
$revisionTwo = llama_resource_ledger_record_approved($db, 3, 5, 8, 'Updated');
$db->commit();
$chain = $db->query(
    'SELECT revision_number,prior_report_id FROM resource_scout_reports
     WHERE id=' . $revisionTwo
)->fetch(PDO::FETCH_ASSOC);
$check((int) $chain['revision_number'] === 2
    && (int) $chain['prior_report_id'] === $reportOne,
    'New Resource approvals must append revision 2 with a link to revision 1.');
$check((string) $db->query(
    'SELECT value_json FROM resource_scout_report_answers
     WHERE report_id=' . $reportOne . "
       AND service_type='toilet' AND group_key='men' AND question_key='facility_cleanliness'"
)->fetchColumn() === '"3"',
    'A later update must not rewrite the original Resource evidence.');
$changedCurrent = $currentGet(3, 'toilet', 'men', 'facility_cleanliness');
$unchangedCurrent = $currentGet(3, 'toilet', 'women', 'facility_cleanliness');
$unchangedFuel = $currentGet(3, 'fuel', 'general', 'service_fuel_pumps');
$check($changedCurrent['value_json'] === '"4"'
    && $changedCurrent['answer_state'] === 'answered'
    && (int) $changedCurrent['row_version'] === 2
    && (int) $changedCurrent['source_report_id'] === $revisionTwo,
    'Changed answers advance only their own current-answer version and provenance.');
$check((int) $unchangedCurrent['row_version'] === 1
    && (int) $unchangedCurrent['source_report_id'] === $reportOne
    && (int) $unchangedFuel['source_report_id'] === $reportOne,
    'Unchanged answers retain their original approved evidence and version.');

$db->exec(
    "UPDATE resources SET experience_json =
     '{\"toilet\":{\"men\":{\"facility_cleanliness\":\"4\"}},
       \"ice\":{\"general\":{\"service_ice_types\":[\"cubed\"]}}}'
     WHERE id = 3"
);
$db->beginTransaction();
$unmappedRejected = false;
try {
    llama_resource_ledger_record_approved($db, 3, 7, 8, 'Retired ice');
} catch (InvalidArgumentException) {
    $unmappedRejected = true;
}
$check($unmappedRejected,
    'Inactive or unexpected Service observations must not enter immutable history.');
$db->RollBack();

$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_current_answers'
)->fetchColumn() === 9,
    'Approved Resource and Facility history must synchronize six target-local current answers.');

// Clearing a whole Service or individual question must create Unanswered
// tombstones, never Unknown or No. New explicit No/zero/Unknown values remain
// real answers. This fourth (third Resource) report leaves history untouched.
$db->exec(
    "UPDATE resource_services SET is_active=0
     WHERE resource_id=3 AND service_type='fuel'"
);
$db->exec(
    "UPDATE resources SET experience_json =
     '{\"toilet\":{\"men\":{\"facility_cleanliness\":\"unknown\",
                           \"facility_soap\":\"no\"},
                   \"all_gender\":{\"facility_cleanliness\":\"0\"}}}'
     WHERE id=3"
);
$db->exec(
    "INSERT INTO resource_submissions
        (submitted_by,name,target_resource_id,form_data,status)
     VALUES (7,'Clear fuel and update restroom',3,'{}','pending')"
);
$clearSubmissionId = (int) $db->lastInsertId();
$db->beginTransaction();
$clearedReport = llama_resource_ledger_record_approved(
    $db, 3, $clearSubmissionId, 8, 'Cleared Fuel and updated restroom'
);
$db->commit();
$clearedMen = $currentGet(3, 'toilet', 'men', 'facility_cleanliness');
$clearedWomen = $currentGet(3, 'toilet', 'women', 'facility_cleanliness');
$clearedFuel = $currentGet(3, 'fuel', 'general', 'service_fuel_grades');
$clearedPumps = $currentGet(3, 'fuel', 'general', 'service_fuel_pumps');
$explicitNo = $currentGet(3, 'toilet', 'men', 'facility_soap');
$explicitZero = $currentGet(3, 'toilet', 'all_gender', 'facility_cleanliness');
$check($clearedMen['answer_state'] === 'unknown'
    && $clearedMen['value_json'] === 'null'
    && (int) $clearedMen['row_version'] === 3
    && (int) $clearedMen['source_report_id'] === $clearedReport,
    'An explicit Unknown must become a distinct current state, not unanswered.');
$check($explicitNo['answer_state'] === 'answered'
    && $explicitNo['value_json'] === '"no"'
    && $explicitZero['answer_state'] === 'answered'
    && $explicitZero['value_json'] === '"0"',
    'Explicit No and zero must remain answered, not cleared.');
foreach ([$clearedWomen, $clearedFuel, $clearedPumps] as $cleared) {
    $check($cleared['answer_state'] === 'unanswered'
        && $cleared['value_json'] === 'null'
        && (int) $cleared['row_version'] === 2
        && (int) $cleared['source_report_id'] === $clearedReport,
        'Removed Service/question answers must have source-linked Unanswered tombstones.');
}
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_current_answers WHERE resource_id=3'
)->fetchColumn() === 6,
    'Existing answer identities must survive clears and two new observations.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_current_answers'
)->fetchColumn() === 11,
    'Current projection must retain cross-target evidence, tombstones, and new observations.');
$check($currentGet(4, 'toilet', 'men', 'facility_cleanliness')['value_json'] === '"2"'
    && (int) $currentGet(4, 'toilet', 'men', 'facility_cleanliness')['row_version'] === 1,
    'Updating Resource 3 must not change Resource-backed Facility 4.');

// Simulate a corrupt current-answer provenance row in the *same* transaction
// as a new report. Projection must reject the wrong target and the caller
// must be able to roll back both report and current-answer changes.
$db->exec(
    "INSERT INTO resource_submissions
        (submitted_by,name,target_resource_id,form_data,status)
     VALUES (7,'Corrupt evidence test',3,'{}','pending')"
);
$badSubmission = (int) $db->lastInsertId();
$db->beginTransaction();
$db->prepare(
    'UPDATE resource_scout_current_answers SET source_report_id = ?
     WHERE resource_id=3 AND service_type = ? AND group_key = ?
       AND question_key = ?'
)->execute([$reportTwo, 'toilet', 'men', 'facility_cleanliness']);
$badEvidenceRejected = false;
try {
    llama_resource_ledger_record_approved($db, 3, $badSubmission, 8, 'Not acceptable');
} catch (RuntimeException $error) {
    $badEvidenceRejected = str_contains(
        $error->getMessage(), 'history from another target'
    );
}
$check($badEvidenceRejected,
    'Current projection must reject provenance belonging to another Resource/Facility.');
$db->rollBack();
$check($currentGet(3, 'toilet', 'men', 'facility_cleanliness')['source_report_id']
    == $clearedReport,
    'A failed projection must roll back its own attempted current-answer changes.');
$stmt = $db->prepare(
    'SELECT COUNT(*) FROM resource_scout_reports WHERE submission_id = ?'
);
$stmt->execute([$badSubmission]);
$check((int) $stmt->fetchColumn() === 0,
    'A failed current-answer projection must roll back the new immutable report.');


putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED');
@unlink($absolute);
@rmdir($photoDir);

echo "PASS: Guarded Resource and linked-Facility writer, transaction rollback, repeat submission, photos, revision chain and target isolation.\n";



// --- Exercise the ACTUAL moderator approval method with and without history.
require_once $root . '/app/resource-submissions.php';

// Older standalone Resource test migrations are upgraded only here, after
// the intentionally minimal ledger-schema smoke tests above.
$apply($root . '/tools/migrations/20261101_v3_004_resource_pages.sql');
$apply($root . '/tools/migrations/20261101_v3_005_resource_marker_service.sql');
$apply($root . '/tools/migrations/20261101_v3_006_resource_hours.sql');
$apply($root . '/tools/migrations/20261109_resource_county.sql');
$apply($root . '/tools/migrations/20261109_resource_moderation.sql');
$db->exec("ALTER TABLE places ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active'");

$moderatedForm = static function (string $name, int $parentId = 0): array {
    return [
        'name' => $name,
        'resource_type' => 'restroom_facility',
        'parent_place_id' => $parentId ? (string) $parentId : '',
        'marker_service_type' => 'toilet',
        'coordinates' => '37.6123456, -107.7123456',
        'status' => 'available',
        'hours_mode' => '24_hours',
        'services' => [
            'toilet' => ['enabled' => '1', 'status' => 'available', 'fee_status' => 'free'],
        ],
        'experience_present' => '1',
        'experience' => ['toilet' => [
            'men' => ['facility_cleanliness' => '3', 'facility_soap' => 'no'],
            'women' => ['facility_cleanliness' => '5'],
        ]],
    ];
};
$newSubmission = static function (string $name) use ($db): int {
    $stmt = $db->prepare(
        "INSERT INTO resource_submissions
            (submitted_by,name,form_data,status)
         VALUES (7,?,'{}','pending')"
    );
    $stmt->execute([$name]);
    return (int) $db->lastInsertId();
};
$findSubmittedReport = static function (int $id) use ($db): ?array {
    $stmt = $db->prepare(
        'SELECT * FROM resource_scout_reports WHERE submission_id = ?'
    );
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($result) ? $result : null;
};

$offId = $newSubmission('Legacy Approval With Feature Off');
$legacyId = llama_resource_review(
    $db, 8, $offId, $moderatedForm('Legacy Approval With Feature Off')
);
$check($legacyId > 0 && $findSubmittedReport($offId) === null,
    'Default-off must retain successful legacy moderation without writing history.');
$check((string) $db->query(
    'SELECT status FROM resource_submissions WHERE id=' . $offId
)->fetchColumn() === 'approved',
    'Default-off path must preserve existing approval status.');

putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1');
$onId = $newSubmission('Moderated Resource With History');
$onResourceId = llama_resource_review(
    $db, 8, $onId, $moderatedForm('Moderated Resource With History'), 'Approved Resource'
);
$onReport = $findSubmittedReport($onId);
$check($onResourceId > 0
    && is_array($onReport)
    && (int) $onReport['resource_id'] === $onResourceId
    && $onReport['target_kind'] === 'resource'
    && $onReport['parent_place_id'] === null,
    'Opt-in must write exactly one immutable history record for the approved Resource.');
$check((int) $db->query(
    'SELECT COUNT(*) FROM resource_scout_report_answers WHERE report_id = '
    . (int) $onReport['id']
)->fetchColumn() === 3,
    'Actual Resource approval must preserve independent restroom answers and No.');

$facilitySubmission = $newSubmission('Moderated Linked Facility With History');
$facilityId = llama_resource_review(
    $db, 8, $facilitySubmission,
    $moderatedForm('Moderated Linked Facility With History', 42), 'Approved Facility'
);
$facilityRecord = $findSubmittedReport($facilitySubmission);
$check($facilityId > 0 && is_array($facilityRecord)
    && $facilityRecord['target_kind'] === 'facility'
    && (int) $facilityRecord['parent_place_id'] === 42
    && (int) $facilityRecord['resource_id'] === $facilityId
    && (int) $facilityRecord['resource_id'] !== $onResourceId,
    'Actual linked-Facility approval must use its own Resource ID and stored Place parent.');

$photoSubmission = $newSubmission('Moderated Photo Evidence');
$sourceDirectory = $root . '/uploads/resource-submissions/' . $photoSubmission;
if (!is_dir($sourceDirectory)
    && !mkdir($sourceDirectory, 0755, true)
    && !is_dir($sourceDirectory)) {
    throw new RuntimeException('Could not create disposable submission photo.');
}
$sourceRelative = '/uploads/resource-submissions/' . $photoSubmission . '/approved.png';
$sourceAbsolute = $root . $sourceRelative;
file_put_contents($sourceAbsolute, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9boWfrAAAAAASUVORK5CYII='
));
$db->prepare('UPDATE resource_submissions SET form_data = ? WHERE id = ?')
    ->execute([json_encode(['_photos' => [
        ['path' => $sourceRelative, 'alt' => 'Moderated photo'],
    ]], JSON_THROW_ON_ERROR), $photoSubmission]);
$photoResourceId = llama_resource_review(
    $db, 8, $photoSubmission, $moderatedForm('Moderated Photo Evidence')
);
$photoReport = $findSubmittedReport($photoSubmission);
$check($photoResourceId > 0 && is_array($photoReport),
    'Actual moderator must successfully approve a Resource with photo evidence.');
$photoQuery = $db->prepare(
    'SELECT photo_path,photo_snapshot_json
     FROM resource_scout_report_photos WHERE report_id = ?'
);
$photoQuery->execute([(int) $photoReport['id']]);
$photoRecord = $photoQuery->fetch(PDO::FETCH_ASSOC);
$check(is_array($photoRecord)
    && str_starts_with($photoRecord['photo_path'], '/uploads/resources/' . $photoResourceId . '/')
    && is_file($root . $photoRecord['photo_path']),
    'Published photo evidence must use the moderator-verified permanent Resource path.');
$photoSnapshot = json_decode((string) $photoRecord['photo_snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
$check(($photoSnapshot['file_sha256'] ?? '') === hash_file('sha256', $root . $photoRecord['photo_path'])
    && !is_file($sourceAbsolute),
    'History must hash approved gallery photo and preserve existing submission cleanup.');

$collisionId = $newSubmission('Moderation Must Roll Back');
$resourceNamesBefore = (int) $db->query(
    "SELECT COUNT(*) FROM resources WHERE name = 'Moderation Must Roll Back'"
)->fetchColumn();
$photosBefore = glob($root . '/uploads/resources/*/collision.png') ?: [];
$db->prepare(
    'INSERT INTO resource_scout_reports
        (resource_id,target_kind,parent_place_id,submission_id,logical_report_key,
         contributor_id,submitted_at,reviewed_by,reviewed_at,schema_version,
         client_submission_key,content_sha256)
     VALUES (1, "resource", NULL, ?, ?, 7, CURRENT_TIMESTAMP, 8,
             CURRENT_TIMESTAMP, ?, ?, ?)'
)->execute([
    $collisionId,
    'collision:resource-submission:' . $collisionId,
    $schema['schema_version'],
    'collision-review:' . $collisionId,
    hash('sha256', 'unrelated historical content ' . $collisionId),
]);
$failed = false;
try {
    llama_resource_review(
        $db, 8, $collisionId, $moderatedForm('Moderation Must Roll Back')
    );
} catch (RuntimeException $error) {
    $failed = str_contains($error->getMessage(), 'another Resource report');
}
$check($failed,
    'An immutable submission collision must abort the entire Resource moderation approval.');
$check((int) $db->query(
    "SELECT COUNT(*) FROM resources WHERE name = 'Moderation Must Roll Back'"
)->fetchColumn() === $resourceNamesBefore
    && (string) $db->query(
        'SELECT status FROM resource_submissions WHERE id = ' . $collisionId
    )->fetchColumn() === 'pending',
    'A ledger error must roll back the Resource record and leave the submission pending.');

$check((int) $db->query('SELECT COUNT(*) FROM scout_reports')->fetchColumn() === 1,
    'Resource moderation must never modify the legacy Place/Site/Area report ledger.');
putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED');
@unlink($root . $photoRecord['photo_path']);
@rmdir(dirname($root . $photoRecord['photo_path']));

echo "PASS: Real Resource moderator respects default-off, stages opted-in Resource/Facility reports and photos atomically, and rolls back on history collision.\n";


// --- Resource/linked-Facility current read bridge remains separately gated.
// Resource pages and Admin must fall back to legacy Experience on old,
// incomplete, stale, cross-target or uninstalled current-answer data.
require_once $root . '/app/resource-report-current-read.php';
$readResource = llama_resource($db, $onResourceId);
$readFacility = llama_resource($db, $facilityId);
$readLegacyOnly = llama_resource($db, $legacyId);
$check($readResource !== [] && $readFacility !== [] && $readLegacyOnly !== [],
    'The read bridge tests must use real stored Resource and Facility records.');

putenv('LLAMA_RESOURCE_CURRENT_READS_ENABLED');
putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED');
$disabledRead = llama_resource_report_read($db, $readResource);
$check($disabledRead['source'] === 'legacy'
    && $disabledRead['reason'] === 'read_switch_off',
    'Public/Admin must continue legacy reading by default.');
putenv('LLAMA_RESOURCE_CURRENT_READS_ENABLED=1');
$missingHistoryFlag = llama_resource_report_read($db, $readResource);
$check($missingHistoryFlag['source'] === 'legacy',
    'Enabling only the read switch must not bypass the history-writer gate.');

putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1');
$readCurrent = llama_resource_report_read($db, $readResource);
$facilityCurrentRead = llama_resource_report_read($db, $readFacility);
$check($readCurrent['source'] === 'current_verified'
    && $readCurrent['reason'] === 'matching_approved_history'
    && $facilityCurrentRead['source'] === 'current_verified',
    'Verified recent Resource and Facility must qualify for current reads: '
    . $readCurrent['reason'] . ' / ' . $facilityCurrentRead['reason']);
$check(($readCurrent['experience']['toilet']['men']['facility_soap'] ?? null) === 'no'
    && ($readCurrent['experience']['toilet']['women']['facility_cleanliness'] ?? null) === '5',
    'Read bridge must keep existing No and independent restroom answers.');
$readSource = static function (array $result, string $wanted): ?array {
    foreach ($result['projection']['groups'] as $group) {
        foreach ($group['answers'] as $answer) {
            if ($answer['answer_key'] === $wanted) {
                return $answer;
            }
        }
    }
    return null;
};
$men = $readSource($readCurrent, 'toilet/men/facility_cleanliness');
$check($men['origin'] === 'resource_scout_current_answers'
    && (int) $men['source_report_id'] === (int) $onReport['id']
    && (int) $men['row_version'] === 1,
    'Current reads must preserve actual immutable provenance metadata.');
$check($readSource($facilityCurrentRead, 'toilet/men/facility_cleanliness')['value'] === '3'
    && $facilityCurrentRead['projection']['target']['parent_place_id'] === 42,
    'Linked Facility must keep its own parent and original observations.');

$oldRecordRead = llama_resource_report_read($db, $readLegacyOnly);
$check($oldRecordRead['source'] === 'legacy'
    && $oldRecordRead['reason'] === 'no_approved_history',
    'An older approved Resource without ledger history must use the legacy store.');

$adminCurrent = admin_resource_get($db, $onResourceId);
$check(($adminCurrent['experience']['toilet']['men']['facility_soap'] ?? null) === 'no',
    'Admin Resource reader must continue to preselect saved Experience answers.');

$db->beginTransaction();
$db->prepare('UPDATE resources SET experience_json = ? WHERE id = ?')
    ->execute([json_encode(['toilet' => [
        'men' => ['facility_cleanliness' => '4'],
        'women' => ['facility_cleanliness' => '5'],
    ]], JSON_THROW_ON_ERROR), $onResourceId]);
$drifted = llama_resource_report_read($db, llama_resource($db, $onResourceId));
$check($drifted['source'] === 'legacy'
    && in_array($drifted['reason'], [
        'record_newer_than_approval', 'current_legacy_mismatch',
    ], true),
    'Newer or mismatched legacy Experience cannot be replaced with old current answers.');
$db->rollBack();

$db->beginTransaction();
$db->prepare(
    'UPDATE resource_scout_current_answers
     SET source_report_id = ?
     WHERE resource_id = ? AND service_type = ?
       AND group_key = ? AND question_key = ?'
)->execute([(int) $facilityRecord['id'], $onResourceId,
            'toilet', 'men', 'facility_cleanliness']);
$crossTarget = llama_resource_report_read($db, $readResource);
$check($crossTarget['source'] === 'legacy'
    && $crossTarget['reason'] === 'current_integrity_error',
    'A current row citing another Facility report must never be displayed.');
$db->rollBack();

$db->beginTransaction();
$db->prepare(
    'DELETE FROM resource_scout_current_answers
     WHERE resource_id = ? AND service_type = ?
       AND group_key = ? AND question_key = ?'
)->execute([$onResourceId, 'toilet', 'women', 'facility_cleanliness']);
$incomplete = llama_resource_report_read($db, $readResource);
$check($incomplete['source'] === 'legacy'
    && $incomplete['reason'] === 'current_legacy_mismatch',
    'An incomplete current-answer projection must fall back to saved Experience.');
$db->rollBack();

$public = (string) file_get_contents($root . '/resource.php');
$admin = (string) file_get_contents($root . '/app/admin-resources.php');
$check(str_contains($public, 'llama_resource_report_read(db(), $resource)')
    && str_contains($admin, 'llama_resource_report_read($db, $resource)'),
    'Public and Admin Resource readers must share the same guarded contract.');

// Simulate PR #192 tables not installed. Only this disposable test database
// is modified, and no production database is ever accessed by the suite.
$db->exec('DROP TABLE resource_scout_current_answers');
$brokenReadiness = llama_resource_ledger_readiness($db);
$check(!$brokenReadiness['schema_ready']
    && $brokenReadiness['status'] === 'schema_incomplete'
    && $brokenReadiness['activation_approved'] === false,
    'Missing current-answer sidecar table must never be marked installed or ready.');
$withoutMigration = llama_resource_report_read($db, $readResource);
$check($withoutMigration['source'] === 'legacy'
    && $withoutMigration['reason'] === 'schema_unavailable',
    'Absent sidecar tables must never break public or Admin legacy reading.');
putenv('LLAMA_RESOURCE_CURRENT_READS_ENABLED');
putenv('LLAMA_RESOURCE_HISTORY_WRITES_ENABLED');

echo "PASS: Resource/Facility public+Admin read bridge chooses verified current data and safely falls back for disabled, legacy, drifted, corrupt, incomplete or missing schema.\n";

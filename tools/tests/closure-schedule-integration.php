<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/operating-closures.php';
require_once dirname(__DIR__, 2) . '/app/closure-map-status.php';
require_once dirname(__DIR__, 2) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__, 2) . '/app/place-report/season-public.php';

/**
 * Disposable MariaDB integration for scheduled Place and Site closures.
 * Never run against a production or nonempty database.
 */
$dsn = (string) getenv('LLAMA_CLOSURE_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_closure_schedule_test')) {
    throw new RuntimeException('A dedicated llama_closure_schedule_test DSN is required.');
}
$db = new PDO(
    $dsn,
    (string) getenv('LLAMA_CLOSURE_TEST_USER'),
    (string) getenv('LLAMA_CLOSURE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
function checkClosure(bool $pass, string $description): void
{
    if (!$pass) {
        throw new RuntimeException('FAILED: ' . $description);
    }
}
function plusDays(int $days, string $timezone = 'America/New_York'): string
{
    return (new DateTimeImmutable('today', new DateTimeZone($timezone)))
        ->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

// A fixed historical test ensures the requested Nov 16 closure is NOT active Nov 14.
$nov14 = new DateTimeImmutable('2026-11-14T20:48:00Z');
$nov16 = new DateTimeImmutable('2026-11-16T05:01:00Z');
checkClosure(llama_closure_local_today('NC', $nov14) === '2026-11-14',
    'North Carolina local date must be Nov 14');
checkClosure(llama_closure_local_today('NC', $nov16) === '2026-11-16',
    'North Carolina midnight must activate the schedule');
$sample = [
    'status' => 'temporary',
    'closure_began' => '2026-11-16',
    'expected_reopening' => '2026-11-19',
    'review_date' => '2026-11-17',
];
foreach ([
    ['2026-11-14', 'scheduled'],
    ['2026-11-15', 'scheduled'],
    ['2026-11-16', 'active'],
    ['2026-11-17', 'active'],
    ['2026-11-18', 'active'],
    ['2026-11-19', 'ended'],
    ['2026-11-22', 'ended'],
] as [$date, $phase]) {
    checkClosure(llama_closure_phase($sample, $date) === $phase,
        "Status should be $phase on $date");
}
$sample['review_date'] = '2026-11-14';
checkClosure(llama_closure_phase($sample, '2026-11-14') === 'scheduled',
    'Review date must never start the closure');
$sample['review_date'] = '2026-11-30';
checkClosure(llama_closure_phase($sample, '2026-11-19') === 'ended',
    'Review date must never postpone automatic reopening');
checkClosure(llama_closure_phase([
    'status' => 'temporary', 'closure_began' => '', 'expected_reopening' => '',
], '2026-11-14') === 'active', 'A closure with no start date begins immediately');
checkClosure(llama_closure_phase(['status' => 'open'], '2026-11-14') === 'open',
    'Manual open override must stay open');

$db->exec('CREATE TABLE places (
    id BIGINT PRIMARY KEY, name VARCHAR(120), state VARCHAR(100), status VARCHAR(20)
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_campsites (
    id BIGINT PRIMARY KEY, place_id BIGINT NOT NULL, site_code VARCHAR(100),
    site_name VARCHAR(100), is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB');
$db->exec('CREATE TABLE place_operating_closures (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT NOT NULL, campsite_id BIGINT NULL,
    target_key VARCHAR(120) NOT NULL UNIQUE,
    status VARCHAR(16) NOT NULL, reason VARCHAR(30) NOT NULL,
    closure_began DATE NULL, expected_reopening DATE NULL, review_date DATE NULL,
    source_url VARCHAR(2048) NULL, notes TEXT NULL,
    verified_at DATETIME NULL, verified_by BIGINT NOT NULL
) ENGINE=InnoDB');
$db->exec("INSERT INTO places VALUES (1,'North Mills River','NC','published'),
    (2,'Another Place','CO','published')");
$db->exec("INSERT INTO place_campsites VALUES
    (11,1,'11','North Site',1),(12,1,'12','South Site',1),(21,2,'21','Other Site',1)");
$record = [
    'place_id' => 1,
    'campsite_id' => '',
    'status' => 'temporary',
    'reason' => 'maintenance',
    'closure_began' => plusDays(2),
    'expected_reopening' => plusDays(4),
    'review_date' => plusDays(3),
    'notes' => 'Future maintenance closure',
];
llama_closure_save($db, $record, 99);
$mapInput = [
    ['id' => 1, 'state' => 'NC'],
    ['id' => 2, 'state' => 'CO'],
];
$map = llama_closure_enrich_map_places($db, $mapInput);
checkClosure(!$map[0]['is_closed'] && !$map[1]['is_closed'],
    'A future closure must not close a Place marker now');
checkClosure(llama_public_place_active_closure($db, 1) === null,
    'A future Place closure must not mark Sites closed');
$adminRows = llama_closure_admin_list($db);
checkClosure(count($adminRows) === 1 &&
    llama_closure_phase($adminRows[0], plusDays(0)) === 'scheduled',
    'Admin must retain a scheduled, editable closure');

// Today begins a Place-wide closure immediately, regardless of future review.
$record['closure_began'] = plusDays(0);
llama_closure_save($db, $record, 99);
$map = llama_closure_enrich_map_places($db, $mapInput);
checkClosure($map[0]['is_closed'] && !$map[1]['is_closed'],
    'An active Place closure affects only its Place marker');
checkClosure(llama_public_place_verified_closed($db, 1),
    'Site APIs must observe an active verified Place closure');
$closed = llama_public_place_active_closure($db, 1);
checkClosure(($closed['reason'] ?? '') === 'maintenance',
    'Active closure reason must remain available to the Site API');

// Review date may be overdue even while the schedule has not ended.
$record['review_date'] = plusDays(-2);
llama_closure_save($db, $record, 99);
checkClosure(llama_public_place_verified_closed($db, 1),
    'Overdue review must not end an active closure');

// The reopening date is exclusive: reopening ON that date is automatic.
$record['expected_reopening'] = plusDays(0);
$record['closure_began'] = plusDays(-2);
llama_closure_save($db, $record, 99);
checkClosure(!llama_public_place_verified_closed($db, 1),
    'A Place must automatically reopen on its end date');
$map = llama_closure_enrich_map_places($db, $mapInput);
checkClosure(!$map[0]['is_closed'],
    'The reopened Place map marker must return to normal');
$adminRows = llama_closure_admin_list($db);
checkClosure(count($adminRows) === 1 &&
    llama_closure_phase($adminRows[0], plusDays(0)) === 'ended',
    'An ended closure must remain editable and visible in Admin');

// Site-specific future and active closures must not close the parent.
$siteRecord = [
    'place_id' => 1, 'campsite_id' => 11, 'status' => 'seasonal',
    'reason' => 'maintenance', 'closure_began' => plusDays(1),
    'expected_reopening' => plusDays(3), 'review_date' => plusDays(4),
];
llama_closure_save($db, $siteRecord, 99);
$sites = llama_campsite_closure_enrich($db, 1, [['id'=>11],['id'=>12]]);
checkClosure(!$sites[0]['is_closed'] && !$sites[1]['is_closed'],
    'Scheduled Site closure must not close it early');
$siteRecord['closure_began'] = plusDays(0);
llama_closure_save($db, $siteRecord, 99);
$sites = llama_campsite_closure_enrich($db, 1, [['id'=>11],['id'=>12]]);
checkClosure($sites[0]['is_closed'] && !$sites[1]['is_closed'],
    'Individual active Site closure affects only its Site');
$siteRecord['expected_reopening'] = plusDays(0);
$siteRecord['closure_began'] = plusDays(-1);
llama_closure_save($db, $siteRecord, 99);
$sites = llama_campsite_closure_enrich($db, 1, [['id'=>11],['id'=>12]]);
checkClosure(!$sites[0]['is_closed'], 'Individual Site must reopen automatically');

$invalid = false;
try {
    $record['closure_began'] = plusDays(3);
    $record['expected_reopening'] = plusDays(2);
    llama_closure_save($db, $record, 99);
} catch (InvalidArgumentException) {
    $invalid = true;
}
checkClosure($invalid, 'A closure ending before it begins must be rejected');

echo "PASS: MariaDB scheduled closures, local dates, Site/Place isolation, automatic reopening and independent reviews\n";

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/nps-batch-fill.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(llama_nps_fill_batch_cursor('', '') === ['', ''], 'Empty cursor must start at first batch');
$assert(llama_nps_fill_batch_cursor('YOSE', 'uuid-17') === ['yose', 'uuid-17'],
    'Valid park and source IDs must survive cursor normalization');

foreach ([
    ['bad', 'uuid-17'],
    ['yose', ''],
    ['', 'uuid-17'],
    ['yose', "id\nnext"],
    ['yose', str_repeat('A', 129)],
] as [$park, $id]) {
    $failed = false;
    try {
        llama_nps_fill_batch_cursor($park, $id);
    } catch (InvalidArgumentException) {
        $failed = true;
    }
    $assert($failed, 'Malformed source pagination cursor was accepted');
}

$rows = [[
    'park_code' => 'yose',
    'record_id' => 'nps-campground-one',
    'ridb_facility_id' => '232400',
    'source_hash' => str_repeat('a', 64),
]];
$signature = llama_nps_fill_batch_signature($rows);
$assert(strlen($signature) === 64, 'Preview signature must be a SHA-256 digest');
$assert($signature === llama_nps_fill_batch_signature($rows),
    'Same preview must have a repeatable signature');

$changed = $rows;
$changed[0]['source_hash'] = str_repeat('b', 64);
$assert(!hash_equals($signature, llama_nps_fill_batch_signature($changed)),
    'Refreshed source must invalidate a previously previewed batch');
$changed = $rows;
$changed[0]['ridb_facility_id'] = '999999';
$assert(!hash_equals($signature, llama_nps_fill_batch_signature($changed)),
    'Changed confirmed source identity must invalidate a preview');

$batchSource = file_get_contents(dirname(__DIR__, 2) . '/app/nps-batch-fill.php');
$fillSource = file_get_contents(dirname(__DIR__, 2) . '/app/nps-place-fill.php');
$adminSource = file_get_contents(dirname(__DIR__, 2) . '/admin/nps-place-fill.php');
$assert(is_string($batchSource) && is_string($fillSource) && is_string($adminSource),
    'NPS batch integration files missing');
$assert(strpos($batchSource, "r.decision = 'confirmed_match'") !== false
    && strpos($batchSource, 'n.source_hash = r.nps_source_hash') !== false
    && strpos($batchSource, 'newer.id IS NULL') !== false,
    'Only latest, still-current human-confirmed identities may be batched');
$assert(strpos($batchSource, 'llama_nps_fill_apply(') !== false
    && strpos($batchSource, 'count($items) > 10') !== false,
    'Bounded batches must reuse existing per-Place transactional fill');
$assert(strpos($fillSource, 'if ($includePhotos)') !== false,
    'Bulk photo import must be opt-in, preserving old single-Place behavior');
$assert(strpos($adminSource, "moderation_verify_csrf(") !== false
    && strpos($adminSource, 'llama_nps_fill_batch_signature(') !== false
    && strpos($adminSource, 'llama_nps_fill_batch_apply(') !== false,
    'Admin batch save must verify CSRF and selected source identities');

if (extension_loaded('pdo_sqlite')) {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rejected = false;
    try {
        llama_nps_fill_batch_apply(
            $db, $db, ['items' => $rows], str_repeat('0', 64), 5
        );
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $assert($rejected, 'Invalid batch signature must fail before any writes');
    $db->exec('CREATE TABLE nps_raw_records (
        resource_type TEXT, park_code TEXT, record_id TEXT, record_name TEXT, source_hash TEXT
    )');
    $db->exec('CREATE TABLE nps_ridb_review_events (
        id INTEGER PRIMARY KEY, nps_park_code TEXT, nps_record_id TEXT,
        ridb_facility_id TEXT, decision TEXT, nps_source_hash TEXT
    )');
    $ins = $db->prepare(
        'INSERT INTO nps_raw_records VALUES (?, ?, ?, ?, ?)'
    );
    $review = $db->prepare(
        'INSERT INTO nps_ridb_review_events VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ([
        ['yose', 'a1', 'Alpha', '1'],
        ['yose', 'b2', 'Bravo', '2'],
        ['yose', 'c3', 'Charlie', '3'],
        ['zion', 'd4', 'Delta', '4'],
    ] as [$park, $id, $name, $number]) {
        $ins->execute(['campgrounds', $park, $id, $name, str_repeat($number, 64)]);
        $review->execute([(int) $number, $park, $id,
            '23' . $number, 'confirmed_match', str_repeat($number, 64)]);
    }
    // The latest review rejects "Bravo": it must never be auto-linked.
    $review->execute([5, 'yose', 'b2', null, 'reopen_review', str_repeat('2', 64)]);
    // A refreshed NPS record makes "Charlie" stale until re-reviewed.
    $db->prepare('UPDATE nps_raw_records SET source_hash = ? WHERE record_id = ?')
        ->execute([str_repeat('e', 64), 'c3']);

    $first = llama_nps_fill_batch_page($db, '', '', 1);
    $assert(count($first['items']) === 1
        && (string) $first['items'][0]['record_id'] === 'a1'
        && $first['has_more'] === true,
        'First page should include only the first current confirmed match');
    $second = llama_nps_fill_batch_page(
        $db, $first['after_park'], $first['after_id'], 1
    );
    $assert(count($second['items']) === 1
        && (string) $second['items'][0]['record_id'] === 'd4'
        && $second['has_more'] === false,
        'Keyset paging must skip reopened/stale reviews across parks');
    $assert(llama_nps_fill_batch_page($db, 'zion', 'd4', 1)['items'] === [],
        'End of matching records should terminate without restarting');
}

echo "PASS: NPS bulk page, current confirmed identities, source hash checks, photo opt-in and Admin submission contract\n";

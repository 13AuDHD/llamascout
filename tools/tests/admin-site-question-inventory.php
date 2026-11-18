<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';

$assert = static function (bool $ok, string $why): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $why);
    }
};

$scopedSite = llama_report_questions_for_scope('site', true);
$adminSite = array_fill_keys(admin_place_campsite_field_keys(), true);
$gap = admin_place_site_inventory_gap();
$assert($scopedSite !== [], 'Canonical Site registry is missing.');
$assert($adminSite !== [], 'Admin Site editor has no registered questions.');
$assert($gap !== [], 'Admin legacy Site editor gap unexpectedly disappeared.');

foreach ($gap as $key => $detail) {
    $assert(isset($scopedSite[$key]), 'Gap contains a noncanonical question.');
    $assert(!isset($adminSite[$key]), 'Gap includes a question Admin already edits.');
    $assert(!empty($detail['label']), 'Gap must carry the canonical label.');
}
$assert(count(array_intersect_key($gap, $adminSite)) === 0,
    'Site gap must not contain editable Admin keys.');

$allowedKey = array_key_first($adminSite);
$validated = admin_place_validate_site_answer_keys([$allowedKey => '0']);
$assert(($validated[$allowedKey] ?? null) === '0',
    'An explicit No/0 must survive the same Admin Site payload validation.');

$rejects = static function (array $answers) use ($assert): void {
    $blocked = false;
    try {
        admin_place_validate_site_answer_keys($answers);
    } catch (InvalidArgumentException) {
        $blocked = true;
    }
    $assert($blocked, 'Admin Site must reject an unsupported question instead of dropping it.');
};
$rejects(['not_registered_at_all' => '1']);
$rejects([array_key_first($gap) => '1']);
$rejects([$allowedKey => '1', array_key_first($gap) => '1']);

echo 'Admin Site canonical=' . count($scopedSite)
    . ', currently editable=' . count($adminSite)
    . ', uncovered=' . count($gap) . PHP_EOL;
foreach ($gap as $key => $metadata) {
    echo '  ' . $key . ' [' . $metadata['layout_section'] . '] '
        . $metadata['label'] . PHP_EOL;
}
echo "PASS: No unsupported Site answers can be silently discarded by the current Admin writer.\n";

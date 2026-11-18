<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';

$siteWritable = admin_place_campsite_field_keys();
if ($siteWritable === []) {
    throw new RuntimeException('Admin Site editor has no canonical writable questions.');
}
$site = llama_report_questions_for_scope('site', true);
$place = llama_report_questions_for_scope('place', true);
foreach ($siteWritable as $key) {
    if (!isset($site[$key])) {
        throw new RuntimeException('Admin Site accepts a non-Site question: ' . $key);
    }
}

$first = $siteWritable[0];
admin_place_assert_selected_site_answer_keys([$first => '1']);
admin_place_assert_selected_site_answer_keys([]);

$reject = static function (array $payload, string $description): void {
    try {
        admin_place_assert_selected_site_answer_keys($payload);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: Site writer accepted ' . $description);
};

$reject(['unregistered_question_xyz' => '1'], 'an unregistered question');
$foreignPlace = null;
foreach ($place as $key => $_field) {
    if (!isset($site[$key])) {
        $foreignPlace = $key;
        break;
    }
}
if ($foreignPlace === null) {
    throw new RuntimeException('No Place-only question found for isolation check.');
}
$reject([$foreignPlace => '1'], 'a Place-only question');

$siteButNotEditor = array_values(array_diff(array_keys($site), $siteWritable));
$siteButNotEditor = array_values(array_filter(
    $siteButNotEditor,
    static fn (string $key): bool => $key !== 'site_number'
        && $key !== 'visited_at'
));
if (!$siteButNotEditor) {
    throw new RuntimeException('Expected unresolved Admin Site inventory gap not found.');
}
$reject([$siteButNotEditor[0] => '1'], 'a Site-eligible but not-yet-rendered question');

// Existing explicit server writer must call the guard, not just define it.
$source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/admin-place-sites.php');
if (substr_count($source, 'admin_place_assert_selected_site_answer_keys($answers);') !== 1) {
    throw new RuntimeException('Admin Site save must validate all posted keys.');
}

echo 'PASS: Admin Site rejects foreign and unsupported questions without affecting valid current fields.' . PHP_EOL;

<?php

declare(strict_types=1);

/** Read-only, admin-authenticated cross-workflow Scout Report audit. */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-update.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$fields = llama_place_report_fields();
$groups = llama_place_report_approved_question_groups();
$updateDefinitions = llama_place_update_definitions();
$scopes = llama_place_report_scope_groups();
$overrides = llama_place_report_scope_overrides();
$approved = [];
$issues = [];
$mapped = 0;
$unmapped = 0;

foreach ($groups as $group => $definition) {
    foreach (($definition[1] ?? []) as $key) {
        $approved[$key] = $group;
    }
}

foreach ($scopes as $scope => $allowed) {
    foreach ($allowed as $entity) {
        if (!in_array($entity, ['place', 'area', 'site'], true)) {
            $issues[] = "INVALID SCOPE: {$scope} contains {$entity}";
        }
    }
}

foreach ($approved as $key => $group) {
    if (!isset($fields[$key])) {
        $issues[] = "MISSING FIELD: {$group}/{$key}";
        continue;
    }
    $field = $fields[$key];
    $storage = trim((string) ($field['storage'] ?? ''));
    if ($storage === '') {
        $issues[] = "MISSING STORAGE: {$group}/{$key}";
        continue;
    }
    if (isset($updateDefinitions[$storage])) {
        ++$mapped;
    } else {
        ++$unmapped;
        $issues[] = "NOT IN SUGGEST UPDATE: {$group}/{$key} ({$storage})";
    }
}

$lines = [
    'Scout Report workflow mapping audit',
    'Approved questions: ' . count($approved),
    'Registered fields: ' . count($fields),
    'Suggest Update mapped approved questions: ' . $mapped,
    'Suggest Update unmapped approved questions: ' . $unmapped,
    'Scope groups: ' . count($scopes),
    'Scope overrides: ' . count($overrides),
    'Issues: ' . count($issues),
    '',
    'FINDINGS',
    ...($issues ?: ['None']),
    '',
    'This audit verifies registry-to-update mapping and scope configuration only.',
    'It does not test database columns, submissions, moderation, or publishing.',
];

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Scout Report workflow audit</title></head><body>';
echo '<h1>Scout Report workflow audit</h1>';
echo '<p>Read-only diagnostic. Select the report to copy its findings.</p>';
echo '<textarea readonly rows="35" cols="100" style="width:100%;max-width:100%;box-sizing:border-box">';
echo htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '</textarea></body></html>';

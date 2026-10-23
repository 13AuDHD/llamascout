<?php

declare(strict_types=1);

/**
 * Read-only, authenticated Scout Report contract audit.
 * Install as admin/scout-report-audit.php.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();

$fields = llama_place_report_fields();
$groups = llama_place_report_approved_question_groups();
$errors = [];
$warnings = [];
$seen = [];
$slots = 0;
$groupCounts = [];
$storageOwners = [];

foreach ($groups as $groupKey => $definition) {
    if (!is_array($definition) || !isset($definition[0], $definition[1]) || !is_array($definition[1])) {
        $errors[] = 'Malformed approved group: ' . $groupKey;
        continue;
    }
    $groupCounts[$groupKey] = count($definition[1]);
    foreach ($definition[1] as $key) {
        ++$slots;
        if (!is_string($key) || $key === '') {
            $errors[] = 'Empty or invalid question key in ' . $groupKey;
            continue;
        }
        if (isset($seen[$key])) {
            $errors[] = 'Duplicate question ' . $key . ' in ' . $groupKey . ' (first: ' . $seen[$key] . ')';
            continue;
        }
        $seen[$key] = $groupKey;
        if (!isset($fields[$key])) {
            $errors[] = 'Missing registry definition: ' . $groupKey . '/' . $key;
            continue;
        }
        $field = $fields[$key];
        $storage = trim((string) ($field['storage'] ?? ''));
        if ($storage === '') {
            $errors[] = 'Missing storage path: ' . $groupKey . '/' . $key;
        } else {
            $storageOwners[$storage][] = $key;
        }
        $assigned = (string) ($field['approved_question_group'] ?? '');
        if ($assigned !== '' && $assigned !== $groupKey) {
            $errors[] = 'Approved group conflict: ' . $key . ' (' . $groupKey . ' vs ' . $assigned . ')';
        }
        if (trim((string) ($field['type'] ?? '')) === '') {
            $errors[] = 'Missing answer type: ' . $key;
        }
        if (trim((string) ($field['label'] ?? '')) === '') {
            $errors[] = 'Missing question label: ' . $key;
        }
    }
}

foreach ($fields as $key => $field) {
    if (!isset($seen[$key]) && !empty($field['approved_question_group'])) {
        $warnings[] = 'Registry question not in master list: ' . $key . ' (claims ' . $field['approved_question_group'] . ')';
    }
}
foreach ($storageOwners as $path => $keys) {
    if (count($keys) > 1) {
        $warnings[] = 'Shared storage path ' . $path . ': ' . implode(', ', $keys) . ' (verify intentional)';
    }
}

$lines = [
    'Scout Report contract audit',
    'Approved groups: ' . count($groups),
    'Approved question slots: ' . $slots,
    'Unique approved questions: ' . count($seen),
    'Registered fields: ' . count($fields),
    'Errors: ' . count($errors),
    'Warnings: ' . count($warnings),
    '',
    'GROUPS',
];
foreach ($groupCounts as $groupKey => $count) {
    $lines[] = $groupKey . ': ' . $count;
}
$lines[] = '';
$lines[] = 'ERRORS';
foreach ($errors as $message) {
    $lines[] = 'ERROR: ' . $message;
}
if (!$errors) {
    $lines[] = 'None';
}
$lines[] = '';
$lines[] = 'WARNINGS';
foreach ($warnings as $message) {
    $lines[] = 'WARNING: ' . $message;
}
if (!$warnings) {
    $lines[] = 'None';
}
$lines[] = '';
$lines[] = 'Scope: registry/master-list consistency only. Does not test browser rendering, submission, moderation, or database persistence.';
$report = implode("\n", $lines);

require __DIR__ . '/_header.php';
?>
<main class="admin-main">
    <h1>Scout Report Contract Audit</h1>
    <p>Read-only diagnostic. No database changes are made. Copy the complete report below and send it for review.</p>
    <p><strong><?= count($errors) ?> errors</strong> and <strong><?= count($warnings) ?> warnings</strong> across <?= count($groups) ?> approved groups.</p>
    <label for="scout-report-audit-output">Audit results</label>
    <textarea id="scout-report-audit-output" rows="28" readonly spellcheck="false"><?= htmlspecialchars($report, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
    <p>This checks the master question list against the shared registry. It does not yet prove the editable and public interfaces match.</p>
</main>
<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

/**
 * Run from the repository root:
 * php tools/scout-report-contract-audit.php
 *
 * Read-only audit. Never writes to the database or changes production files.
 */
$root = dirname(__DIR__);
require_once $root . '/app/place-report.php';

$groups = llama_place_report_approved_question_groups();
$fields = llama_place_report_fields();
$errors = [];
$warnings = [];
$seen = [];
$total = 0;

foreach ($groups as $groupKey => $definition) {
    if (!is_array($definition) || !isset($definition[0], $definition[1]) || !is_array($definition[1])) {
        $errors[] = "Malformed approved group: {$groupKey}";
        continue;
    }
    foreach ($definition[1] as $position => $key) {
        ++$total;
        if (isset($seen[$key])) {
            $errors[] = "Duplicate approved question {$key} in {$groupKey}; previously in {$seen[$key]}";
            continue;
        }
        $seen[$key] = $groupKey;
        if (!isset($fields[$key])) {
            $errors[] = "Approved question {$groupKey}/{$key} missing from field registry";
            continue;
        }
        $field = $fields[$key];
        if (trim((string) ($field['storage'] ?? '')) === '') {
            $errors[] = "Approved question {$groupKey}/{$key} lacks a storage path";
        }
        $approvedGroup = (string) ($field['approved_question_group'] ?? '');
        if ($approvedGroup !== '' && $approvedGroup !== $groupKey) {
            $errors[] = "Question {$key} approved group mismatch: {$groupKey} vs {$approvedGroup}";
        }
    }
}

foreach ($fields as $key => $field) {
    if (!isset($seen[$key]) && !empty($field['approved_question_group'])) {
        $warnings[] = "Registered question {$key} claims approved group {$field['approved_question_group']} but is absent from approved inventory";
    }
}

fwrite(STDOUT, "Scout Report contract audit\n");
fwrite(STDOUT, "Approved groups: " . count($groups) . "\nApproved question slots: {$total}\nRegistered fields: " . count($fields) . "\n");
foreach ($errors as $error) {
    fwrite(STDOUT, "ERROR: {$error}\n");
}
foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARNING: {$warning}\n");
}
fwrite(STDOUT, count($errors) . " error(s), " . count($warnings) . " warning(s)\n");
exit($errors ? 1 : 0);

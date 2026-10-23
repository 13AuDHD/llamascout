<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report/diagnostics.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$fields = llama_place_report_fields();
$issues = llama_place_report_contract_issues();
if (PHP_VERSION_ID < 80100) { $issues[] = 'PHP 8.1 or newer is required.'; }
foreach (['mbstring','pdo_mysql'] as $extension) {
    if (!extension_loaded($extension)) { $issues[] = 'Missing PHP extension: ' . $extension; }
}
try {
    $schemaIssues = llama_place_report_schema_issues(db());
} catch (Throwable $exception) {
    $schemaIssues = ['Live schema could not be inspected. Check the server error log.'];
    llama_log_caught_exception($exception, 'scout-report.schema-audit');
}
$lines = [
    'Canonical Scout Report preflight',
    'Registered fields: ' . count($fields),
    'Approved question groups: ' . count(llama_place_report_approved_question_groups()),
    'Registry issues: ' . count($issues),
    'Database schema issues: ' . count($schemaIssues),
    '', 'FINDINGS', ...($issues ?: ['Registry: passed']), ...($schemaIssues ?: ['Database schema: passed']),
    '', 'Read-only. This tool does not change answers, tables, permissions or moderation status.',
    'A passing preflight does not replace signed-in submission, approval, photo and rollback tests.',
];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Scout Report preflight</title></head><body><h1>Scout Report preflight</h1>';
echo '<textarea readonly rows="35" cols="115">';
echo htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '</textarea></body></html>';

<?php
declare(strict_types=1);

/** V3 launch smoke tests. No DB, network, or live data writes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-answers.php';
require_once dirname(__DIR__) . '/app/place-report/diagnostics.php';

$checks = 0;
$failures = [];
$check = static function (string $name, callable $fn) use (&$checks, &$failures): void {
    ++$checks;
    try { $fn(); echo "PASS: $name\n"; }
    catch (Throwable $e) { $failures[] = "$name: " . $e->getMessage(); echo "FAIL: $name\n"; }
};
$assert = static function (bool $condition, string $why): void {
    if (!$condition) throw new RuntimeException($why);
};
$fields = llama_place_report_fields();
$check('Registry loaded', static function () use ($fields, $assert): void {
    $assert(count($fields) > 100, 'Expected canonical field inventory');
    $assert(isset($fields['daytime_noise'], $fields['nighttime_noise'], $fields['site_length_feet']), 'Missing core questions');
});
$check('Explicit unanswered is distinct from Unknown', static function () use ($fields, $assert): void {
    $u = llama_place_report_parse_answer($fields['daytime_noise'], llama_place_report_unknown_token());
    $n = llama_place_report_parse_answer($fields['daytime_noise'], llama_place_report_unanswered_token());
    $assert($u['state'] === 'unknown' && $n['state'] === 'unanswered', 'States were conflated');
});
$check('Answered rating retains value', static function () use ($fields, $assert): void {
    $a = llama_place_report_parse_answer($fields['daytime_noise'], '3');
    $assert($a['state'] === 'answered' && $a['value'] === 3, 'Rating was modified');
});
$check('Out-of-range rating rejected', static function () use ($fields, $assert): void {
    try { llama_place_report_parse_answer($fields['nighttime_noise'], '9'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Invalid rating was accepted');
});
$check('Place/Site field ownership enforced', static function () use ($assert): void {
    try { llama_scoped_report_field('site_length_feet', 'place'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Site field accepted as a Place scoped override');
});
$check('Invalid scoped target rejected', static function () use ($assert): void {
    try { llama_scoped_report_validate_target('site', 0); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Zero Site ID accepted');
});
$check('Invalid scoped source rejected', static function () use ($assert): void {
    try { llama_scoped_report_validate_source('site', 'unknown'); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'Unknown source accepted');
});
$check('Canonical field contract', static function () use ($assert): void {
    $issues = llama_place_report_contract_issues();
    $assert(!$issues, 'Contract issues: ' . implode(' | ', array_slice($issues, 0, 12)) . ' (total ' . count($issues) . ')');
});
echo "\n$checks checks; " . count($failures) . " failures\n";
foreach ($failures as $f) echo " - $f\n";
exit($failures ? 1 : 0);

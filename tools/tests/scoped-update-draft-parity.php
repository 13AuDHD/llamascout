<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';
require_once dirname(__DIR__, 2) . '/app/place-report/scoped-update-form-values.php';

function scopedDraftCheck(bool $pass, string $description): void
{
    if (!$pass) {
        throw new RuntimeException('FAIL: ' . $description);
    }
}

$siteFields = llama_report_questions_for_scope('site', true);
$areaFields = llama_report_questions_for_scope('area', true);
scopedDraftCheck(isset($siteFields['site_number']),
    'Canonical Site number must exist in the scoped catalog.');

$multiKey = null;
foreach ($siteFields as $key => $field) {
    if (($field['type'] ?? '') === 'multiselect' && !empty($field['options'])) {
        $multiKey = $key;
        break;
    }
}
scopedDraftCheck($multiKey !== null, 'Canonical Site inventory must include a multiselect.');
$options = array_keys((array) $siteFields[$multiKey]['options']);
$chosen = array_slice($options, 0, 2);

$result = llama_scoped_update_form_values('site', [
    'site_number' => '0',
    $multiKey => $chosen,
    'not_a_real_question' => 'must-not-appear',
]);
scopedDraftCheck(($result['site_number'] ?? null) === '0',
    'Explicit 0 must survive a Site draft round-trip.');
scopedDraftCheck(($result[$multiKey] ?? null) === $chosen,
    'Multiselect selections must survive a Site draft round-trip.');
scopedDraftCheck(!array_key_exists('not_a_real_question', $result),
    'Unregistered question must not enter the Site editor draft.');

$empty = llama_scoped_update_form_values('site', [$multiKey => []]);
scopedDraftCheck(array_key_exists($multiKey, $empty) && $empty[$multiKey] === [],
    'An intentional empty multiselect must not become Unanswered.');

$unknown = llama_place_report_unknown_token();
$unknownResult = llama_scoped_update_form_values('site', [
    $multiKey => $unknown,
]);
scopedDraftCheck(($unknownResult[$multiKey] ?? null) === $unknown,
    'Explicit Unknown must retain the canonical token.');

$siteOnlyKey = null;
foreach ($siteFields as $key => $field) {
    if (!isset($areaFields[$key])) {
        $siteOnlyKey = $key;
        break;
    }
}
scopedDraftCheck($siteOnlyKey !== null, 'Site-only questions must exist.');
$areaDraft = llama_scoped_update_form_values('area', [
    $siteOnlyKey => 'leaked-site-answer',
]);
scopedDraftCheck(!isset($areaDraft[$siteOnlyKey]),
    'A Site-only answer must never enter a Facility revision draft.');

$failed = false;
try {
    llama_scoped_update_form_values('place', ['site_number' => '1']);
} catch (InvalidArgumentException) {
    $failed = true;
}
scopedDraftCheck($failed, 'Place updates must use their dedicated edit path.');

// The JSON transport used by Update JS must retain a typed array value.
$wire = json_decode(json_encode(['answers' => $result], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
scopedDraftCheck(($wire['answers'][$multiKey] ?? null) === $chosen,
    'JSON to the shared scoped editor must retain the multiselect array.');

$page = (string) file_get_contents(dirname(__DIR__, 2) . '/account/update-place.php');
scopedDraftCheck(substr_count($page, 'llama_scoped_update_form_values(') === 2,
    'POST error and needs-changes revisions must share the typed form adapter.');
echo "PASS: Scoped Update preserves canonical multiselect, [], 0, Unknown, and target ownership.\n";

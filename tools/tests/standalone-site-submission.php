<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/site-submissions.php';

function siteContractAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$fields = llama_site_submission_fields();
siteContractAssert(count($fields) > 0, 'Site inventory is empty.');
siteContractAssert(isset($fields['site_number']), 'Canonical Site number must be present.');
siteContractAssert(isset($fields['visited_at']), 'Site visit date must be present.');

foreach ($fields as $key => $field) {
    siteContractAssert(in_array('site', (array) $field['report_scopes'], true),
        'Site form includes a non-Site question: ' . $key);
    siteContractAssert(empty($field['derived']) && empty($field['pricing_managed'])
        && empty($field['location_field']), 'Site form includes an unwritable question: ' . $key);
}

$answers = llama_site_submission_parse_answers([
    'site_number' => '12',
    'visited_at' => '2026-11-17',
]);
siteContractAssert($answers['site_number'] === '12'
    && $answers['visited_at'] === '2026-11-17',
    'Canonical Site identity and visit date are preserved.');
siteContractAssert(llama_site_submission_parse_answers([
    'site_number' => '__LLAMA_UNANSWERED__',
]) === [], 'Unanswered must not create an authored fact.');

$invalid = static function (callable $f, string $message): void {
    try {
        $f();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: accepted ' . $message);
};
$invalid(
    static fn () => llama_site_submission_parse_answers(['name' => 'Do not edit Place']),
    'Place name as a Site answer'
);
$invalid(
    static fn () => llama_site_submission_parse_answers(['site_number' => '1', 'invalid_key' => '2']),
    'mixed target payload'
);
$invalid(
    static fn () => llama_site_submission_photos(
        ['photos' => [['path' => '/uploads/place-submissions/12/site-photos/photo.jpg']]], 7
    ),
    'another workflow photo'
);
$invalid(
    static fn () => llama_site_submission_photos(
        ['photos' => [['path' => '/uploads/site-submissions/8/photos/photo.jpg']]], 7
    ),
    'another Site submission photo'
);
$valid = llama_site_submission_photos(
    ['photos' => [['path' => '/uploads/site-submissions/7/photos/photo.jpg']]], 7
);
siteContractAssert(count($valid) === 1, 'Owned Site photo was rejected.');

$code = (string) file_get_contents(dirname(__DIR__, 2) . '/app/site-submissions.php');
siteContractAssert(str_contains($code, 'llama_scout_report_record_approved(')
    && str_contains($code, "llama_scoped_report_save(")
    && !str_contains($code, 'llama_place_report_publish_data('),
    'Site publisher must create a Site ledger and scoped answers, never Place answers.');
siteContractAssert(str_contains($code, 'FOR UPDATE')
    && str_contains($code, 'UPDATE place_site_submissions')
    && str_contains($code, 'WHERE place_id = ? AND site_code = ?'),
    'Moderation must lock and verify the single Site target and duplicate code.');
$addPage = (string) file_get_contents(dirname(__DIR__, 2) . '/add-site.php');
$siteForm = (string) file_get_contents(dirname(__DIR__, 2) . '/partials/place-report/form.php');
$identityFields = (string) file_get_contents(dirname(__DIR__, 2)
    . '/partials/place-report/site-identity-fields.php');
$parentJs = (string) file_get_contents(dirname(__DIR__, 2) . '/js/add-site-parent-search.js');
$parentCss = (string) file_get_contents(dirname(__DIR__, 2) . '/css/site/pages/add-place.css');
siteContractAssert(!str_contains($addPage, 'Select the parent Place</h2>')
    && !str_contains($addPage, 'site-place-search')
    && !str_contains($addPage, '<select name="site_place_id"')
    && str_contains($addPage, 'parent_place_suggestions')
    && str_contains($addPage, 'llama_site_parent_place_choices'),
    'Add Site must use only the dynamic parent search, not the old introductory search form.');
siteContractAssert(str_contains($siteForm, 'require $placeReportSiteIdentityPartial')
    && str_contains($siteForm, "\$approvedKey === 'site_info'")
    && str_contains($identityFields, 'name="site_place_id"')
    && str_contains($identityFields, 'name="site_name"'),
    'Parent Place and Site name must be the first Site Info fields.');
siteContractAssert(str_contains($code, 'status IN ("active", "featured")')
    && str_contains($code, 'ORDER BY name ASC, id ASC LIMIT 6')
    && str_contains($parentJs, 'aria-activedescendant')
    && str_contains($parentCss, '.site-parent-place-results[hidden]'),
    'Search must provide alphabetically ordered eligible Places and keyboard-accessible choices.');
echo "PASS: Independent Site submission, Site Info identity fields and live Parent Place selection.\n";

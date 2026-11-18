<?php

declare(strict_types=1);

/**
 * Read-only Admin report ownership audit.
 *
 * Expected parity is determined by the canonical registry, not by the
 * current Admin form. Missing Site keys are recorded as a product gap until
 * a target-local input exists. A Place answer may never be saved to a Site
 * simply because it appears in the shared form.
 */
require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';
require_once dirname(__DIR__, 2) . '/app/admin-place-areas.php';

$site = llama_report_questions_for_scope('site', true);
$allowedSite = array_fill_keys(admin_place_campsite_field_keys(), true);
$siteGap = [];
$siteRestricted = [];
foreach ($site as $key => $field) {
    if (!empty($field['derived']) || !empty($field['pricing_managed'])
        || !empty($field['location_field']) || $key === 'site_number') {
        $siteRestricted[] = $key;
        continue;
    }
    if (!isset($allowedSite[$key])) {
        $siteGap[$key] = [
            'section' => (string) ($field['layout_section'] ?? ''),
            'group' => (string) ($field['approved_question_group'] ?? ''),
        ];
    }
}
foreach ($allowedSite as $key => $_) {
    if (!isset($site[$key])) {
        throw new RuntimeException('Admin Site writer accepts a noncanonical Site question: ' . $key);
    }
}

$area = admin_place_area_fields();
$areaScoped = llama_report_questions_for_scope('area', true);
$areaGap = array_diff(array_keys($areaScoped), array_keys($area));
foreach ($area as $key => $field) {
    if (!isset($areaScoped[$key])) {
        throw new RuntimeException('Admin Area editor accepts a foreign question: ' . $key);
    }
}

echo 'Admin Site inventory: canonical=' . count($site)
    . ', saveable=' . count($allowedSite)
    . ', explicit_exclusions=' . count($siteRestricted)
    . ', missing=' . count($siteGap) . PHP_EOL;
foreach ($siteGap as $key => $metadata) {
    echo '  SITE QUESTION NOT IN ADMIN SITE EDITOR: ' . $key
        . ' (layout_section=' . $metadata['section'] . ')' . PHP_EOL;
}
echo 'Admin Area inventory: canonical=' . count($areaScoped)
    . ', available=' . count($area)
    . ', excluded=' . count($areaGap) . PHP_EOL;
if ($areaGap) {
    echo '  Admin Area excluded: ' . implode(', ', $areaGap) . PHP_EOL;
}
echo 'PASS: Admin Site/Area writer keys originate from their canonical target scopes.' . PHP_EOL;

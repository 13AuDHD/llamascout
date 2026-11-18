<?php

declare(strict_types=1);

/**
 * Update parity audit. Read-only; lists the exact canonical question keys
 * missing from the existing Update shared form and its scoped templates.
 *
 * The initial audit is informational. Do not silently broaden writers while
 * comparing field ownership, saved identity and moderation support.
 */
require_once dirname(__DIR__, 2) . '/app/place-update.php';
require_once dirname(__DIR__, 2) . '/app/admin-place-sites.php';

$layout = llama_place_report_layout_groups();
$allowedPaths = array_fill_keys(array_keys(llama_place_update_definitions()), true);

$sharedPlaceMarkup = [];
foreach (llama_report_questions() as $key => $field) {
    if (!empty($field['pricing_managed'])
        || ($field['report_scope_group'] ?? '') === 'area'
        || !isset($allowedPaths[(string) ($field['storage'] ?? '')])) {
        continue;
    }
    $section = (string) ($field['layout_section'] ?? '');
    $group = (string) ($field['layout_group'] ?? '');
    if (!isset($layout[$section][$group])) {
        continue;
    }
    $sharedPlaceMarkup[$key] = true;
}

$facilityTemplates = [];
foreach (llama_report_questions_for_scope('area', true) as $key => $field) {
    if (($field['report_scope_group'] ?? '') === 'area'
        && !empty($field['facility_kinds'])) {
        $facilityTemplates[$key] = true;
    }
}

foreach (['place', 'site', 'area'] as $scope) {
    $all = llama_report_questions_for_scope($scope, true);
    $writable = [];
    $restricted = [];
    $missing = [];
    $unsupportedLayout = [];

    foreach ($all as $key => $field) {
        if (!empty($field['derived']) || !empty($field['pricing_managed'])
            || !empty($field['location_field'])) {
            continue;
        }
        if (!llama_place_update_allows_field($field)) {
            $restricted[] = $key;
            continue;
        }
        $writable[] = $key;
        if (!isset($sharedPlaceMarkup[$key])
            && !($scope === 'area' && isset($facilityTemplates[$key]))) {
            $missing[] = $key;
        }
        if (!isset($layout[(string) ($field['layout_section'] ?? '')][(string) ($field['layout_group'] ?? '')])) {
            $unsupportedLayout[] = $key;
        }
    }
    echo strtoupper($scope) . ': canonical=' . count($all)
        . ', editable=' . count($writable)
        . ', excluded=' . count($restricted)
        . ', missingSharedMarkup=' . count($missing) . PHP_EOL;
    if ($restricted) {
        echo '  Excluded from Update: ' . implode(', ', $restricted) . PHP_EOL;
    }
    if ($missing) {
        echo '  MISSING TEMPLATE: ' . implode(', ', $missing) . PHP_EOL;
    }
    if ($unsupportedLayout) {
        echo '  MISSING LAYOUT: ' . implode(', ', $unsupportedLayout) . PHP_EOL;
    }
}

$adminSiteKeys = admin_place_campsite_field_keys();
echo 'ADMIN Site section: ' . count($adminSiteKeys)
    . ' keys (intentionally restricted to site layout section)' . PHP_EOL;

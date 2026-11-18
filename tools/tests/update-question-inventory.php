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
require_once dirname(__DIR__, 2) . '/app/place-report/update-field-templates.php';

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

// Update now supplies all missing Site/Area controls from the exact same
// PHP field renderer. The library is an inert fieldset until JS clones a
// selected target's field.
$scopedTemplates = llama_scoped_update_missing_field_templates($sharedPlaceMarkup);
$allTemplateKeys = array_fill_keys(
    array_merge(array_keys($sharedPlaceMarkup), array_keys($scopedTemplates)), true
);

foreach ([
    'site_accommodation_type', 'site_allowed_setups',
    'area_site_count', 'area_surface', 'area_night_lighting',
    'area_maneuvering_difficulty', 'area_shade', 'area_notes',
] as $previouslyMissingKey) {
    if (!isset($scopedTemplates[$previouslyMissingKey])) {
        throw new RuntimeException(
            'Canonical scoped Update control is missing: ' . $previouslyMissingKey
        );
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
        if (!isset($allTemplateKeys[$key])) {
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
        throw new RuntimeException('Missing canonical scoped Update templates: '
            . implode(', ', $missing));
    }
    if ($unsupportedLayout) {
        echo '  MISSING LAYOUT: ' . implode(', ', $unsupportedLayout) . PHP_EOL;
    }
}

$adminSiteKeys = admin_place_campsite_field_keys();
echo 'ADMIN Site section: ' . count($adminSiteKeys)
    . ' keys (intentionally restricted to site layout section)' . PHP_EOL;

echo 'PASS: Canonical Place/Site/Area Update questions have reusable PHP controls, with restrictions unchanged.' . PHP_EOL;

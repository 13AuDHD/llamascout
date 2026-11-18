<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/resource-experience.php';

$manifest = llama_resource_experience_manifest();
$requiredServices = [
    'shower', 'water_fill', 'potable_water', 'toilet', 'laundry',
    'fuel', 'ice', 'dump_station', 'propane', 'ev_charging',
    'air', 'trash', 'recycling', 'firewood', 'groceries', 'other',
];
foreach ($requiredServices as $service) {
    if (empty($manifest[$service])) {
        throw new RuntimeException('Missing Experience service: ' . $service);
    }
}
$shower = $manifest['shower']['general']['questions'];
$areaShower = llama_report_question('facility_hot_water');
if (($shower['facility_hot_water']['label'] ?? null) !== $areaShower['label']
    || ($shower['facility_hot_water']['type'] ?? null) !== $areaShower['type']) {
    throw new RuntimeException('Area and Resource/Facility shower questions drifted.');
}
foreach (['men', 'women', 'all_gender'] as $group) {
    if (!isset($manifest['toilet'][$group]['questions']['facility_cleanliness'])) {
        throw new RuntimeException('Missing independent restroom Experience group: ' . $group);
    }
}

$valid = [
    'shower' => ['general' => [
        'facility_hot_water' => 'yes',
        'facility_shower_stalls' => '4',
        'facility_cleanliness' => '5',
    ]],
    'fuel' => ['general' => [
        'service_fuel_grades' => ['85', 'diesel'],
        'service_fuel_pumps' => '8',
    ]],
    'toilet' => [
        'men' => ['facility_cleanliness' => '3', 'service_toilet_stalls' => '2'],
        'women' => ['facility_cleanliness' => '5', 'service_toilet_stalls' => '3'],
    ],
    'ice' => ['general' => ['service_ice_types' => ['cubed', 'pellet']]],
];
$selectedServices = $valid;
unset($selectedServices['ice']);
$clean = llama_resource_experience_clean($selectedServices, ['shower', 'toilet', 'fuel']);
if (isset($clean['ice']) || $clean['toilet']['men']['facility_cleanliness'] !== '3'
    || $clean['toilet']['women']['facility_cleanliness'] !== '5'
    || $clean['fuel']['general']['service_fuel_pumps'] !== '8') {
    throw new RuntimeException('Experience answers were mixed across Services or restrooms.');
}

$invalid = $valid;
$invalid['fuel']['general']['service_fuel_grades'] = ['diesel', 'rocket_fuel'];
try {
    llama_resource_experience_clean($invalid, ['fuel']);
    throw new RuntimeException('Unknown fuel choices were accepted.');
} catch (InvalidArgumentException) {
    // Correct: answer must match the single canonical list.
}

$invalid = $valid;
$invalid['fuel']['general']['service_fuel_grades'] = [['nested' => 'attack']];
try {
    llama_resource_experience_clean($invalid, ['fuel']);
    throw new RuntimeException('Nested multiselect input was accepted.');
} catch (InvalidArgumentException) {
    // Correct: only an array of listed scalar choices is valid.
}

$invalid = $valid;
$invalid['shower']['general']['facility_shower_stalls'] = '3.5';
try {
    llama_resource_experience_clean($invalid, ['shower']);
    throw new RuntimeException('Fractional stall counts were accepted.');
} catch (InvalidArgumentException) {
    // Correct.
}

// Neither Add nor Update nor Admin may silently discard a submitted
// Experience field that does not belong to the selected Resource/Facility.
$mustReject = static function (array $answers, array $services, string $why): void {
    try {
        llama_resource_experience_clean($answers, $services);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Expected Experience rejection: ' . $why);
};

$mustReject($valid, ['shower', 'toilet', 'fuel'], 'unselected Ice service');
$mustReject([
    'invented_service' => ['general' => ['service_access_notes' => 'text']],
], ['shower'], 'unregistered Service');
$mustReject([
    'shower' => ['men' => ['facility_hot_water' => 'yes']],
], ['shower'], 'foreign group');
$mustReject([
    'shower' => ['general' => ['service_fuel_pumps' => '5']],
], ['shower'], 'cross-Service question');
$mustReject([
    'toilet' => ['men' => ['invented_question' => 'yes']],
], ['toilet'], 'unknown question key');
$mustReject([
    'shower' => ['general' => 'invalid'],
], ['shower'], 'non-array group answers');

// Both Resource and Resource-backed Facility forms use the exact same
// service manifest. Canonical Area question definitions supply all reused
// labels, types and option choices.
foreach ($manifest as $service => $groups) {
    foreach ($groups as $group) {
        foreach ($group['questions'] as $key => $definition) {
            $canonical = llama_report_question($key);
            if (!$canonical) {
                continue; // Service-specific questions stay in this manifest.
            }
            if ($definition['label'] !== $canonical['label']
                || $definition['type'] !== $canonical['type']
                || $definition['options'] !== ($canonical['options'] ?? [])) {
                throw new RuntimeException('Canonical Area/Resource question drift: ' . $key);
            }
        }
    }
}

// The shared form sends an explicit presence marker even when all services
// are removed. Needs-changes revisions must clear, not preserve old answers.
require_once dirname(__DIR__, 2) . '/app/resource-submissions.php';
$cleared = llama_resource_submission_input(['experience_present' => '1']);
if (!array_key_exists('experience', $cleared) || $cleared['experience'] !== []) {
    throw new RuntimeException('Removing every Service must clear old pending Experience.');
}
$legacyNoMarker = llama_resource_submission_input(['name' => 'Legacy source']);
if (array_key_exists('experience', $legacyNoMarker)) {
    throw new RuntimeException('Legacy import without presence marker must retain existing Experience.');
}
$no = llama_resource_experience_clean([
    'shower' => ['general' => ['facility_hot_water' => 'no']],
    'fuel' => ['general' => ['service_fuel_pumps' => '0']],
], ['shower', 'fuel']);
if (($no['shower']['general']['facility_hot_water'] ?? null) !== 'no'
    || ($no['fuel']['general']['service_fuel_pumps'] ?? null) !== '0') {
    throw new RuntimeException('No and numeric zero must survive Resource/Facility Experience saves.');
}

echo "PASS: Shared Facility and Resource Experience catalog, service isolation and input validation.\n";

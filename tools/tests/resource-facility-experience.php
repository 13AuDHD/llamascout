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
$clean = llama_resource_experience_clean($valid, ['shower', 'toilet', 'fuel']);
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

echo "PASS: Shared Facility and Resource Experience catalog, service isolation and input validation.\n";

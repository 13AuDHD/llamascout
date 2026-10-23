<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';

function llama_place_report_campsite_feature_value(array $site, string $key): mixed
{
    $fallback = null;

    foreach ((array) ($site['features'] ?? []) as $feature) {
        if ((string) ($feature['feature_key'] ?? '') !== $key) {
            continue;
        }

        $value = $feature['feature_value'] ?? null;
        $provider = strtolower(trim((string) ($feature['source_provider'] ?? '')));

        if (str_contains($provider, 'llama scout')) {
            return $value;
        }

        if ($fallback === null) {
            $fallback = $value;
        }
    }

    return $fallback;
}

function llama_place_report_campsite_bool_form_value(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $normalized = strtoupper(trim((string) $value));

    if (in_array($normalized, ['1', 'Y', 'YES', 'TRUE'], true)) {
        return '1';
    }

    if (in_array($normalized, ['0', 'N', 'NO', 'FALSE'], true)) {
        return '0';
    }

    return llama_place_report_unknown_token();
}

function llama_place_report_campsite_surface_form_value(mixed $value): ?string
{
    $text = strtolower(trim((string) $value));

    if ($text === '' || in_array($text, ['n/a', 'na', 'none'], true)) {
        return null;
    }

    return match (true) {
        str_contains($text, 'concrete') => 'concrete',
        str_contains($text, 'paved'),
        str_contains($text, 'asphalt') => 'paved',
        str_contains($text, 'gravel') => 'graded-gravel',
        str_contains($text, 'packed') && str_contains($text, 'dirt') => 'hard-packed-dirt',
        str_contains($text, 'dirt') => 'dirt',
        str_contains($text, 'sand') => 'sand',
        str_contains($text, 'rock') => 'rock',
        str_contains($text, 'grass') => 'grass',
        str_contains($text, 'mixed') => 'mixed',
        default => null,
    };
}

function llama_place_report_campsite_grade_form_value(mixed $value): ?string
{
    $text = strtolower(trim((string) $value));

    if ($text === '' || in_array($text, ['n/a', 'na', 'none'], true)) {
        return null;
    }

    return match (true) {
        str_contains($text, 'very steep') => 'very-steep',
        str_contains($text, 'steep') => 'steep',
        str_contains($text, 'moderate') => 'moderate',
        str_contains($text, 'slight') => 'slight',
        str_contains($text, 'level') => 'level',
        str_contains($text, 'var') => 'varies',
        default => null,
    };
}

function llama_place_report_campsite_electric_service_form_value(mixed $value): ?string
{
    $text = preg_replace('/\s+/', '', strtolower(trim((string) $value))) ?? '';

    if ($text === '') {
        return null;
    }

    $has20 = str_contains($text, '20') || str_contains($text, '15');
    $has30 = str_contains($text, '30');
    $has50 = str_contains($text, '50');

    return match (true) {
        $has20 && $has30 && $has50 => '20-30-50a',
        $has30 && $has50 => '30-50a',
        $has20 && $has30 => '20-30a',
        $has50 => '50a',
        $has30 => '30a',
        $has20 => '20a',
        default => null,
    };
}

function llama_place_report_campsite_imported_form_values(array $site): array
{
    $unknown = llama_place_report_unknown_token();
    $values = [];

    $accessible = strtolower(trim((string) ($site['accessible_status'] ?? '')));

    if ($accessible === 'yes') {
        $values['site_accessible'] = '1';
    } elseif ($accessible === 'no') {
        $values['site_accessible'] = '0';
    } elseif ($accessible !== '') {
        $values['site_accessible'] = $unknown;
    }

    $simple = [
        'max_people' => 'max_people',
        'site_length_feet' => 'site_length_ft',
        'site_width_feet' => 'site_width_ft',
        'parking_length_feet' => 'driveway_length_ft',
        'overhead_clearance_feet' => 'overhead_clearance_ft',
        'max_vehicle_length_feet' => 'max_vehicle_length_ft',
        'tent_pad_length_feet' => 'tent_pad_length_ft',
        'tent_pad_width_feet' => 'tent_pad_width_ft',
    ];

    foreach ($simple as $fieldKey => $siteKey) {
        if (($site[$siteKey] ?? null) !== null && $site[$siteKey] !== '') {
            $values[$fieldKey] = (string) $site[$siteKey];
        }
    }

    $maxVehicles = (int) ($site['max_vehicles'] ?? 0);

    if ($maxVehicles > 0) {
        $values['vehicle_capacity'] = (string) min(11, $maxVehicles);
    }

    $surface = llama_place_report_campsite_surface_form_value($site['driveway_surface'] ?? null);

    if ($surface !== null) {
        $values['parking_surface'] = $surface;
    }

    $grade = llama_place_report_campsite_grade_form_value($site['driveway_grade'] ?? null);

    if ($grade !== null) {
        $values['parking_grade'] = $grade;
    }

    foreach (
        [
            'tent_pad' => 'tent_pad',
            'hookup_electric' => 'electric_hookup',
            'hookup_water' => 'water_hookup',
            'hookup_sewer' => 'sewer_hookup',
        ]
        as $fieldKey => $siteKey
    ) {
        $value = llama_place_report_campsite_bool_form_value($site[$siteKey] ?? null);

        if ($value !== null) {
            $values[$fieldKey] = $value;
        }
    }

    $electricService = llama_place_report_campsite_electric_service_form_value(
        $site['electric_service'] ?? null
    );

    if ($electricService !== null) {
        $values['hookup_electric_service'] = $electricService;
    }

    $hookupStatus = strtolower(trim((string) ($site['hookup_status'] ?? '')));

    if ($hookupStatus !== '') {
        $values['site_hookups_available'] = $hookupStatus === 'none' ? '0' : '1';
    }

    $parkingStyle = strtolower(trim((string) ($site['parking_style'] ?? '')));

    if ($parkingStyle !== '') {
        $values['pull_through'] = $parkingStyle === 'pull_through' ? '1' : '0';
        $values['back_in'] = $parkingStyle === 'back_in' ? '1' : '0';
    }

    /*
     * Imported campsite type provides the initial broad equipment suitability
     * values. Explicit Scout/Admin overrides loaded below still win.
     */
    $siteType = strtolower(trim((string) ($site['site_type'] ?? '')));
    $rawSiteType = strtoupper(trim((string) ($site['raw_site_type'] ?? '')));

    $isTentOnly =
        $siteType === 'tent_site'
        || str_contains($rawSiteType, 'TENT ONLY');

    $isStandard =
        $siteType === 'mixed_site'
        || str_contains($rawSiteType, 'STANDARD');

    $isRvOnly =
        $siteType === 'rv_site'
        || str_contains($rawSiteType, 'RV ONLY');

    if ($isTentOnly) {
        $values['tent_camping_suitable'] = '1';
        $values['rv_suitable'] = '0';
        $values['trailer_suitable'] = '0';
    } elseif ($isStandard) {
        $values['tent_camping_suitable'] = '1';
        $values['rv_suitable'] = '1';
        $values['trailer_suitable'] = '1';
    } elseif ($isRvOnly) {
        $values['tent_camping_suitable'] = '0';
        $values['rv_suitable'] = '1';
        $values['trailer_suitable'] = '1';
    }

    foreach (
        [
            'capacity_size_rating',
            'site_rating',
            'condition_rating',
            'location_rating',
        ]
        as $featureKey
    ) {
        $value = llama_place_report_campsite_feature_value($site, $featureKey);

        if ($value !== null && trim((string) $value) !== '') {
            $values[$featureKey] = trim((string) $value);
        }
    }

    $double = llama_place_report_campsite_bool_form_value(
        llama_place_report_campsite_feature_value($site, 'double_driveway')
    );

    if ($double !== null) {
        $values['double_driveway'] = $double;
    }

    $hike = llama_place_report_campsite_feature_value($site, 'hike_in_distance');

    if (
        $hike !== null
        && preg_match('/-?\d+(?:\.\d+)?/', (string) $hike, $match) === 1
    ) {
        $values['hike_in_distance_feet'] = $match[0];
    }

    return $values;
}


/** Imported facts are a baseline only; direct Scout overrides take precedence. */
function llama_place_report_campsite_answers(array $site, array $overrides = []): array
{
    $answers = llama_place_report_campsite_imported_form_values($site);
    $number = trim((string) ($site['site_code'] ?? ''));
    if ($number !== '') { $answers['site_number'] = $number; }
    return array_replace($answers, $overrides);
}

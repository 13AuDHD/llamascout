<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/resource-submissions.php';
require_once dirname(__DIR__, 2) . '/app/resource-report-projection.php';
require_once dirname(__DIR__, 2) . '/app/resource-report-field-reconciliation.php';

$check = static function (bool $ok, string $why): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $why);
    }
};

$expectedForm = [
    'name', 'parent_place_id', 'resource_type', 'marker_service_type', 'status', 'coordinates',
    'hours_mode', 'fixed_open_hour', 'fixed_close_hour', 'weekly_hours',
    'street_address', 'city', 'county', 'state_region', 'postal_code', 'country_code',
    'phone', 'website_url', 'services', 'notes', 'experience', 'experience_present',
];
$expectedLegacyDetails = [
    'name', 'resource_type', 'marker_service_type', 'status', 'notes',
    'latitude', 'longitude', 'street_address', 'city', 'county',
    'state_region', 'postal_code', 'country_code', 'phone', 'website_url',
    'hours_mode', 'fixed_open_hour', 'fixed_close_hour', 'hours_text', 'weekly_hours',
];
$reconciliation = llama_resource_report_field_reconciliation();
$base = $reconciliation['base_fields'];
$services = $reconciliation['service_fields'];
$experience = $reconciliation['experience_fields'];

$check(llama_resource_report_submittable_field_keys() === $expectedForm,
    'Resource Add/Update allowlist must exactly match the original, approved form fields.');
$check(llama_resource_report_legacy_detail_keys() === $expectedLegacyDetails,
    'Resource read projection must preserve the exact existing legacy_details output.');
$check(count($base) === count(array_unique(array_merge($expectedForm, $expectedLegacyDetails))),
    'Every Resource form/read field must have one and only one metadata classification.');
foreach ($base as $key => $entry) {
    $check($entry['storage'] !== '' && $entry['role'] !== '' && $entry['decision'] !== '',
        'Unclassified Resource field: ' . $key);
    $check(!str_starts_with($entry['storage'], 'place_')
        && !str_starts_with($entry['storage'], 'scout_reports.'),
        'Resource field cannot silently point to a Place or central report writer: ' . $key);
}
$check($base['parent_place_id']['role'] === 'parent_link'
    && $base['parent_place_id']['legacy_detail'] === false,
    'Facility parent ID is target identity, not a Scout Report answer.');
$check($base['notes']['decision'] === 'review_as_question',
    'Resource description ownership remains an explicit unresolved decision.');
$check($base['experience_present']['role'] === 'form_marker',
    'Experience presence marker cannot be published as a question.');

// Exercise the same input function that the contributor Create/Update path uses.
$input = array_fill_keys($expectedForm, 'sample');
$input['services'] = [];
$input['weekly_hours'] = [];
$input['experience'] = [];
$input['experience_present'] = '1';
$input['unsafe_place_answer'] = 'do not accept';
$parsed = llama_resource_submission_input($input);
$check(array_keys($parsed) === $expectedForm
    && !array_key_exists('unsafe_place_answer', $parsed),
    'Input parser must accept exactly the published Resource allow-list.');
$cleared = llama_resource_submission_input(['experience_present' => '1']);
$check(array_key_exists('experience', $cleared) && $cleared['experience'] === [],
    'Removing the last Service must still clear old Experience on a revision.');

// All Resource service-card inputs must be classified separately from the
// Resource's own status, general notes and Parent Place.
$expectedServiceCard = [
    'enabled', 'status', 'fee_status', 'fee_description', 'access_notes', 'notes',
];
foreach ($expectedServiceCard as $key) {
    $check(isset($services[$key]), 'Unclassified Service-card input: ' . $key);
}
$check($services['notes']['storage'] === 'resource_services.notes'
    && $base['notes']['storage'] === 'resources.notes',
    'Service notes must not overwrite Resource-level description.');
$check($services['status']['storage'] === 'resource_services.status'
    && $base['status']['storage'] === 'resources.status',
    'Service availability must not replace Resource availability.');
$check($services['fee_status']['decision'] === 'review_as_question'
    && $services['fee_description']['decision'] === 'review_as_question',
    'Service fee semantics require distinct ownership and provenance.');
$check(array_keys($reconciliation['service_types']) === array_keys(llama_resource_service_types()),
    'Actual allowed Services must be read from the Resource Service registry.');

$manifest = llama_resource_experience_manifest();
$expectedOccurrences = [];
foreach ($manifest as $service => $groups) {
    foreach ($groups as $group => $definition) {
        foreach ($definition['questions'] as $key => $field) {
            $path = $service . '/' . $group . '/' . $key;
            $expectedOccurrences[$path] = true;
            $record = $experience[$path] ?? null;
            $check(is_array($record), 'Unclassified Experience question: ' . $path);
            $check($record['key'] === $key && $record['service'] === $service
                && $record['group'] === $group
                && $record['source'] === 'resources.experience_json.'
                    . $service . '.' . $group . '.' . $key,
                'Experience identity/storage path mismatch: ' . $path);
            $check($record['label'] === $field['label']
                && $record['type'] === $field['type']
                && $record['options'] === ($field['options'] ?? []),
                'Experience field metadata drift: ' . $path);
            $central = llama_report_question($key);
            $check(
                $record['classification'] === ($central ? 'canonical_area_question' : 'service_only_question')
                && $record['canonical_question_key'] === ($central ? $key : null),
                'Experience question classification drift: ' . $path
            );
        }
    }
}
$check(count($experience) === count($expectedOccurrences),
    'An Experience path was duplicated or invented outside the rendered manifest.');

$men = 'toilet/men/facility_cleanliness';
$women = 'toilet/women/facility_cleanliness';
$allGender = 'toilet/all_gender/facility_cleanliness';
$check(isset($experience[$men], $experience[$women], $experience[$allGender])
    && $experience[$men]['canonical_question_key'] === 'facility_cleanliness'
    && $experience[$women]['source'] !== $experience[$men]['source'],
    'Repeatable restroom questions must never collapse into one answer row.');
$check($experience['fuel/general/service_fuel_grades']['classification']
    === 'service_only_question'
    && $experience['potable_water/general/facility_water_year_round']['classification']
    === 'canonical_area_question',
    'Service-only vs already-canonical Area questions must be distinguished.');

// The read-only projection now consumes the same metadata catalog, preserving
// the existing shape without writing into the Scout Report store.
$example = [
    'id' => 18, 'parent_place_id' => 32, 'name' => 'Camp Laundry',
    'status' => 'available', 'phone' => '(970) 555-0101',
    'notes' => 'Machine room', 'created_by' => 42,
    'services' => [['service_type' => 'laundry']],
    'experience' => ['laundry' => ['general' => ['facility_cleanliness' => '4']]],
];
$projected = llama_resource_report_projection($example);
$check($projected['target']['kind'] === 'facility'
    && $projected['target']['id'] === 18
    && $projected['target']['parent_place_id'] === 32
    && ($projected['legacy_details']['notes'] ?? '') === 'Machine room'
    && !isset($projected['legacy_details']['created_by']),
    'Existing Facility identity and Resource detail projection must remain unchanged.');

$summary = ['canonical_occurrences' => 0, 'service_only_occurrences' => 0];
foreach ($experience as $record) {
    $kind = $record['classification'] === 'canonical_area_question'
        ? 'canonical_occurrences' : 'service_only_occurrences';
    ++$summary[$kind];
}
$check($summary['canonical_occurrences'] > 0 && $summary['service_only_occurrences'] > 0,
    'Audit must include both canonically reused and Resource-only Experience questions.');
$check(count($reconciliation['unresolved']) >= 5,
    'Do not silently resolve outstanding ownership and provenance decisions.');

echo 'Resource fields: ' . count($base)
    . ', Service columns: ' . count($services)
    . ', Service types: ' . count($reconciliation['service_types'])
    . ', Experience occurrences: ' . count($experience)
    . ' (canonical Area: ' . $summary['canonical_occurrences']
    . ', service-only: ' . $summary['service_only_occurrences'] . ').' . PHP_EOL;
echo "PASS: Resource, Service and Experience fields classified without inventing mappings or changing persistence.\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-campsites.php';
require_once __DIR__ . '/place-map-features.php';
require_once __DIR__ . '/place-report/scoped-answers.php';
require_once __DIR__ . '/place-report/report-targets.php';

/**
 * Admin Place Site-report support.
 *
 * Owns:
 * - Site field discovery for Admin Place.
 * - Imported campsite-to-form translation.
 * - Site target discovery.
 * - Scoped Site override loading/saving.
 *
 * Does not own:
 * - Page rendering.
 * - Map geometry editing.
 */
function admin_place_campsite_field_keys(): array
{
    $keys = [];

    foreach (llama_place_report_fields() as $key => $field) {
        /*
         * Admin Place edits Place-level and selected-Site facts in one shared
         * form. The visible Site section changes target when a campsite is
         * selected, so every field rendered there that legally allows Site
         * scope belongs to the selected-Site load/save path.
         *
         * This deliberately includes both Site-only fields and shared
         * "all" fields such as sensory, safety, experience, and summaries.
         * Place-section fields remain Place-level even when their defined
         * scope also permits Site exceptions.
         */
        if (
            (string) (
                $field['layout_section']
                ?? ''
            ) !== 'site'
        ) {
            continue;
        }

        if (
            !in_array(
                'site',
                (array) (
                    $field['report_scopes']
                    ?? []
                ),
                true
            )
        ) {
            continue;
        }

        if (
            !empty($field['derived'])
            || $key === 'site_number'
        ) {
            continue;
        }

        $keys[] =
            (string) $key;
    }

    return $keys;
}

function admin_place_site_section_place_field_keys(): array
{
    $keys = [];

    foreach (llama_place_report_fields() as $key => $field) {
        if (
            (string) (
                $field['layout_section']
                ?? ''
            ) !== 'site'
        ) {
            continue;
        }

        if (
            !in_array(
                'place',
                (array) (
                    $field['report_scopes']
                    ?? []
                ),
                true
            )
        ) {
            continue;
        }

        if (
            !empty($field['derived'])
            || $key === 'site_number'
        ) {
            continue;
        }

        $keys[] =
            (string) $key;
    }

    return $keys;
}

function admin_place_without_campsite_fields(array $input): array
{
    if (trim((string) ($input['selected_campsite_id'] ?? '')) === '') {
        return $input;
    }

    foreach (admin_place_campsite_field_keys() as $key) {
        unset($input[$key]);
    }

    unset($input['site_number']);

    return $input;
}

function admin_place_campsite_feature_value(array $site, string $key): mixed
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

function admin_place_campsite_bool_form_value(mixed $value): ?string
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

function admin_place_campsite_surface_form_value(mixed $value): ?string
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

function admin_place_campsite_grade_form_value(mixed $value): ?string
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

function admin_place_campsite_electric_service_form_value(mixed $value): ?string
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

function admin_place_campsite_imported_form_values(array $site): array
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

    $surface = admin_place_campsite_surface_form_value($site['driveway_surface'] ?? null);

    if ($surface !== null) {
        $values['parking_surface'] = $surface;
    }

    $grade = admin_place_campsite_grade_form_value($site['driveway_grade'] ?? null);

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
        $value = admin_place_campsite_bool_form_value($site[$siteKey] ?? null);

        if ($value !== null) {
            $values[$fieldKey] = $value;
        }
    }

    $electricService = admin_place_campsite_electric_service_form_value(
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
        $value = admin_place_campsite_feature_value($site, $featureKey);

        if ($value !== null && trim((string) $value) !== '') {
            $values[$featureKey] = trim((string) $value);
        }
    }

    $double = admin_place_campsite_bool_form_value(
        admin_place_campsite_feature_value($site, 'double_driveway')
    );

    if ($double !== null) {
        $values['double_driveway'] = $double;
    }

    $hike = admin_place_campsite_feature_value($site, 'hike_in_distance');

    if (
        $hike !== null
        && preg_match('/-?\d+(?:\.\d+)?/', (string) $hike, $match) === 1
    ) {
        $values['hike_in_distance_feet'] = $match[0];
    }

    return $values;
}

function admin_place_apply_site_overrides(array $values, array $overrides): array
{
    $fields = llama_place_report_fields();

    foreach ($overrides as $key => $value) {
        if (!isset($fields[$key])) {
            continue;
        }

        $type = (string) ($fields[$key]['type'] ?? '');

        if ($value === llama_place_report_unknown_token()) {
            $values[$key] = llama_place_report_unknown_token();
        } elseif (
            in_array(
                $type,
                ['tri', 'checkbox'],
                true
            )
            && is_bool($value)
        ) {
            $values[$key] =
                $value
                    ? '1'
                    : '0';
        } elseif ($value !== null && !is_array($value)) {
            $values[$key] = (string) $value;
        }
    }

    return $values;
}

function admin_place_campsite_override_form_values(
    PDO $db,
    int $placeId,
    int $siteId
): array {
    if ($siteId < 1) {
        return [];
    }

    try {
        $overrides = llama_scoped_report_load(
            $db,
            $placeId,
            'site',
            $siteId,
            'campsite_record'
        );
    } catch (PDOException $exception) {
        $code = (string) ($exception->errorInfo[1] ?? '');

        if ($code === '1146' || $code === '42S02') {
            return [];
        }

        throw $exception;
    }

    return admin_place_apply_site_overrides(
        [],
        $overrides
    );
}

function admin_place_campsite_effective_form_values(
    PDO $db,
    int $placeId,
    array $site
): array {
    $values = admin_place_campsite_imported_form_values($site);
    $siteId = (int) ($site['id'] ?? 0);

    if ($siteId < 1) {
        return $values;
    }

    try {
        $overrides = llama_scoped_report_load(
            $db,
            $placeId,
            'site',
            $siteId,
            'campsite_record'
        );
    } catch (PDOException $exception) {
        $code = (string) ($exception->errorInfo[1] ?? '');

        if ($code === '1146' || $code === '42S02') {
            return $values;
        }

        throw $exception;
    }

    return admin_place_apply_site_overrides($values, $overrides);
}

function admin_place_map_site_effective_form_values(
    PDO $db,
    int $placeId,
    int $featureId
): array {
    if ($featureId < 1) {
        return [];
    }

    try {
        $overrides = llama_scoped_report_load(
            $db,
            $placeId,
            'site',
            $featureId,
            'map_feature'
        );
    } catch (PDOException $exception) {
        $code = (string) ($exception->errorInfo[1] ?? '');

        if ($code === '1146' || $code === '42S02') {
            return [];
        }

        throw $exception;
    }

    return admin_place_apply_site_overrides([], $overrides);
}

function admin_place_campsite_form_catalog(PDO $db, int $placeId): array
{
    $catalog = [];
    $canonicalSites = [];

    foreach (llama_place_campsites($db, $placeId) as $site) {
        $siteId = (int) ($site['id'] ?? 0);

        if ($siteId > 0) {
            $canonicalSites[$siteId] = $site;
        }
    }

    foreach (llama_report_targets($db, $placeId) as $target) {
        if ((string) ($target['scope'] ?? '') !== 'site') {
            continue;
        }

        $targetId = (int) ($target['id'] ?? 0);
        $source = (string) ($target['source'] ?? '');

        if (
            $targetId < 1
            || !in_array($source, ['campsite_record', 'map_feature'], true)
        ) {
            continue;
        }

        $label = trim((string) ($target['label'] ?? ''));

        if ($label === '') {
            $label = 'Site #' . $targetId;
        }

        $mapFeatureId = max(0, (int) ($target['map_feature_id'] ?? 0));
        $sourceProvider = '';
        $sourceExternalId = '';

        $parentAreaId =
            max(
                0,
                (int) (
                    $target['parent_area_id']
                    ?? 0
                )
            );

        $importedValues = [];
        $overrideValues = [];

        if ($source === 'campsite_record') {
            $site = $canonicalSites[$targetId] ?? null;

            if (!is_array($site)) {
                continue;
            }

            $importedValues =
                admin_place_campsite_imported_form_values(
                    $site
                );

            $overrideValues =
                admin_place_campsite_override_form_values(
                    $db,
                    $placeId,
                    $targetId
                );

            $formValues =
                admin_place_apply_site_overrides(
                    $importedValues,
                    $overrideValues
                );

            $sourceProvider = (string) ($site['source_provider'] ?? '');
            $sourceExternalId = (string) ($site['source_external_id'] ?? '');
        } else {
            $overrideValues =
                admin_place_map_site_effective_form_values(
                    $db,
                    $placeId,
                    $targetId
                );

            $formValues =
                $overrideValues;
        }

        $catalog[] = [
            'id' => $source . ':' . $targetId,
            'scope_id' => $targetId,
            'scope_source' => $source,
            'map_feature_id' => $mapFeatureId > 0 ? $mapFeatureId : null,
            'parent_area_id' => $parentAreaId > 0 ? $parentAreaId : null,
            'label' => $label,
            'source_provider' => $sourceProvider,
            'source_external_id' => $sourceExternalId,
            'imported_values' => $importedValues,
            'override_values' => $overrideValues,
            'form_values' => $formValues,
        ];
    }

    return $catalog;
}

function admin_place_parse_site_target(string $value): array
{
    if (
        preg_match(
            '/^(campsite_record|map_feature):([1-9][0-9]*)$/D',
            trim($value),
            $matches
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Site reporting target.'
        );
    }

    return [
        'source' => (string) $matches[1],
        'id' => (int) $matches[2],
    ];
}

function admin_place_save_selected_campsite_answers(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): ?int {
    $selectedTarget = trim(
        (string) ($input['selected_campsite_id'] ?? '')
    );

    if ($selectedTarget === '') {
        return null;
    }

    $parsed = admin_place_parse_site_target($selectedTarget);
    $source = (string) $parsed['source'];
    $siteId = (int) $parsed['id'];

    $target = llama_report_verified_target(
        $db,
        $placeId,
        'site',
        $source,
        $siteId
    );

    $fields = llama_place_report_fields();
    $unknownToken = llama_place_report_unknown_token();
    $unansweredToken = llama_place_report_unanswered_token();
    $importedBaseline = [];

    $sitePayload =
        trim(
            (string) (
                $input['admin_site_answers_json']
                ?? ''
            )
        );

    $submittedAnswers = null;

    if ($sitePayload !== '') {
        if (strlen($sitePayload) > 200000) {
            throw new InvalidArgumentException(
                'Site answers are too large.'
            );
        }

        $submittedAnswers =
            json_decode(
                $sitePayload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($submittedAnswers)
            || (
                array_is_list($submittedAnswers)
                && $submittedAnswers !== []
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid Site answer format.'
            );
        }
    }

    if ($source === 'campsite_record') {
        $site =
            llama_place_campsite(
                $db,
                $placeId,
                $siteId
            );

        if (is_array($site)) {
            $importedBaseline =
                admin_place_campsite_imported_form_values(
                    $site
                );
        }
    }

    $saved = 0;

    foreach (admin_place_campsite_field_keys() as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fieldType =
            (string) (
                $fields[$key]['type']
                ?? ''
            );

        if ($submittedAnswers !== null) {
            if (!array_key_exists($key, $submittedAnswers)) {
                continue;
            }

            $raw =
                $submittedAnswers[$key];
        } elseif (array_key_exists($key, $input)) {
            $raw =
                $input[$key];
        } elseif ($fieldType === 'checkbox') {
            /*
             * Compatibility fallback for older Admin clients that still post
             * the whole form instead of the dedicated Site payload.
             */
            $raw = '0';
        } else {
            continue;
        }

        if (is_array($raw)) {
            $unknownFields = [];
            $value =
                llama_place_report_parse_field(
                    $fields[$key],
                    $raw,
                    $unknownFields
                );

            llama_scoped_report_save(
                $db,
                $placeId,
                'site',
                $siteId,
                $source,
                $key,
                $value
            );

            $saved++;
            continue;
        }

        $rawString = trim((string) $raw);

        if ($rawString === '' || $rawString === $unansweredToken) {
            llama_scoped_report_clear_override(
                $db,
                $placeId,
                'site',
                $siteId,
                $source,
                $key
            );

            continue;
        }

        if ($rawString === $unknownToken) {
            $value = $unknownToken;
        } else {
            $unknownFields = [];
            $value = llama_place_report_parse_field(
                $fields[$key],
                $raw,
                $unknownFields
            );
        }

        /*
         * Imported campsite data is the source baseline, not an Admin
         * override. Saving an unchanged imported value must not duplicate it
         * into place_report_scoped_answers or it would shadow future import
         * updates for that Site.
         */
        if (
            $source === 'campsite_record'
            && array_key_exists(
                $key,
                $importedBaseline
            )
        ) {
            $baselineRaw =
                $importedBaseline[$key];

            if ($baselineRaw === $unknownToken) {
                $baselineValue =
                    $unknownToken;
            } else {
                $baselineUnknownFields = [];
                $baselineValue =
                    llama_place_report_parse_field(
                        $fields[$key],
                        $baselineRaw,
                        $baselineUnknownFields
                    );
            }

            if (
                llama_scoped_report_values_equal(
                    $value,
                    $baselineValue
                )
            ) {
                llama_scoped_report_clear_override(
                    $db,
                    $placeId,
                    'site',
                    $siteId,
                    $source,
                    $key
                );

                continue;
            }
        }

        llama_scoped_report_save(
            $db,
            $placeId,
            'site',
            $siteId,
            $source,
            $key,
            $value
        );

        $saved++;
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.site_report_updated',
        'Updated an individual Site report.',
        [
            'place_id' => $placeId,
            'site_id' => $siteId,
            'site_source' => $source,
            'map_feature_id' => $target['map_feature_id'] ?? null,
            'answers_saved' => $saved,
        ]
    );

    return $siteId;
}

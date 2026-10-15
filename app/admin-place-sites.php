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
 * - Canonical Site target discovery.
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
         * This deliberately includes both Site-only fields and canonical
         * "all" fields such as sensory, safety, experience, and summaries.
         * Place-section fields remain Place-level even when their canonical
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

        if ($source === 'campsite_record') {
            $site = $canonicalSites[$targetId] ?? null;

            if (!is_array($site)) {
                continue;
            }

            $formValues = admin_place_campsite_effective_form_values(
                $db,
                $placeId,
                $site
            );

            $sourceProvider = (string) ($site['source_provider'] ?? '');
            $sourceExternalId = (string) ($site['source_external_id'] ?? '');
        } else {
            $formValues = admin_place_map_site_effective_form_values(
                $db,
                $placeId,
                $targetId
            );
        }

        $catalog[] = [
            'id' => $source . ':' . $targetId,
            'scope_id' => $targetId,
            'scope_source' => $source,
            'map_feature_id' => $mapFeatureId > 0 ? $mapFeatureId : null,
            'label' =>
                $label
                . (
                    $mapFeatureId > 0
                        ? ' - Mapped'
                        : ' - No mapped geometry'
                ),
            'source_provider' => $sourceProvider,
            'source_external_id' => $sourceExternalId,
            'form_values' => $formValues,
        ];
    }

    return $catalog;
}


function admin_place_site_association_catalog(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        return [
            'campsites' => [],
            'mapped_sites' => [],
        ];
    }

    $associations =
        llama_place_campsite_map_associations(
            $db,
            $placeId
        );

    $mapFeatures =
        llama_place_map_features(
            $db,
            $placeId
        );

    $activeMappedSiteIds = [];

    foreach ($mapFeatures as $feature) {
        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) !== 'camping_site'
        ) {
            continue;
        }

        $featureId =
            (int) (
                $feature['id']
                ?? 0
            );

        if ($featureId > 0) {
            $activeMappedSiteIds[
                $featureId
            ] = true;
        }
    }

    $campsites = [];

    foreach (
        llama_place_campsite_map_options(
            $db,
            $placeId
        )
        as $site
    ) {
        $campsiteId =
            (int) (
                $site['id']
                ?? 0
            );

        if ($campsiteId < 1) {
            continue;
        }

        $mapFeatureId =
            max(
                0,
                (int) (
                    $site['map_feature_id']
                    ?? 0
                )
            );

        $staleMapFeatureId =
            max(
                0,
                (int) (
                    $site['stale_map_feature_id']
                    ?? 0
                )
            );

        $label =
            trim(
                (string) (
                    $site['label']
                    ?? ''
                )
            );

        if ($label === '') {
            $label =
                'Site #'
                . $campsiteId;
        }

        if ($mapFeatureId > 0) {
            $mappingLabel =
                ' - mapped to feature #'
                . $mapFeatureId;
        } elseif ($staleMapFeatureId > 0) {
            $mappingLabel =
                ' - stale mapping reference #'
                . $staleMapFeatureId;
        } else {
            $mappingLabel =
                ' - not mapped';
        }

        $campsites[] = [
            'id' => $campsiteId,
            'label' => $label,
            'map_feature_id' =>
                $mapFeatureId > 0
                    ? $mapFeatureId
                    : null,
            'stale_map_feature_id' =>
                $staleMapFeatureId > 0
                    ? $staleMapFeatureId
                    : null,
            'display_label' =>
                $label
                . $mappingLabel,
        ];
    }

    $mappedSites = [];

    foreach ($mapFeatures as $feature) {
        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) !== 'camping_site'
        ) {
            continue;
        }

        $featureId =
            (int) (
                $feature['id']
                ?? 0
            );

        if ($featureId < 1) {
            continue;
        }

        $siteCode =
            trim(
                (string) (
                    $feature['site_details']['site_code']
                    ?? ''
                )
            );

        $featureLabel =
            trim(
                (string) (
                    $feature['label']
                    ?? ''
                )
            );

        if ($siteCode !== '') {
            $label =
                'Site '
                . $siteCode;

            if (
                $featureLabel !== ''
                && $featureLabel !== $label
            ) {
                $label .=
                    ' - '
                    . $featureLabel;
            }
        } elseif ($featureLabel !== '') {
            $label =
                $featureLabel;
        } else {
            $label =
                'Mapped Site #'
                . $featureId;
        }

        $owner =
            $associations[
                $featureId
            ]
            ?? null;

        $ownerCampsiteId =
            is_array($owner)
                ? (int) (
                    $owner['campsite_id']
                    ?? 0
                )
                : 0;

        $ownerLabel =
            is_array($owner)
                ? trim(
                    (string) (
                        $owner['label']
                        ?? ''
                    )
                )
                : '';

        $conflict =
            is_array($owner)
            && !empty(
                $owner['conflict']
            );

        $displayLabel =
            $label
            . ' - feature #'
            . $featureId;

        if ($conflict) {
            $displayLabel .=
                ' - association conflict';
        } elseif ($ownerCampsiteId > 0) {
            $displayLabel .=
                ' - linked to '
                . (
                    $ownerLabel !== ''
                        ? $ownerLabel
                        : 'campsite #'
                            . $ownerCampsiteId
                );
        } else {
            $displayLabel .=
                ' - available';
        }

        $mappedSites[] = [
            'id' => $featureId,
            'label' => $label,
            'display_label' => $displayLabel,
            'campsite_id' =>
                $ownerCampsiteId > 0
                    ? $ownerCampsiteId
                    : null,
            'conflict' => $conflict,
        ];
    }

    return [
        'campsites' => $campsites,
        'mapped_sites' => $mappedSites,
    ];
}


function admin_place_save_site_association(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): void {
    $campsiteId =
        max(
            0,
            (int) (
                $input['canonical_campsite_id']
                ?? 0
            )
        );

    if ($campsiteId < 1) {
        throw new InvalidArgumentException(
            'Choose a canonical campsite.'
        );
    }

    $site =
        llama_place_campsite(
            $db,
            $placeId,
            $campsiteId
        );

    if (!$site) {
        throw new InvalidArgumentException(
            'The selected campsite is not available for this Place.'
        );
    }

    $currentFeatureId =
        max(
            0,
            (int) (
                $site['map_feature_id']
                ?? 0
            )
        );

    $nextFeatureId =
        max(
            0,
            (int) (
                $input['mapped_site_feature_id']
                ?? 0
            )
        );

    if ($nextFeatureId === $currentFeatureId) {
        return;
    }

    if ($nextFeatureId < 1) {
        if ($currentFeatureId > 0) {
            llama_place_campsite_map_association_clear(
                $db,
                $placeId,
                $currentFeatureId
            );
        }
    } else {
        llama_place_campsite_map_association_save(
            $db,
            $placeId,
            $nextFeatureId,
            $campsiteId
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.site_map_association_updated',
        'Updated a canonical Site to mapped Site association.',
        [
            'place_id' => $placeId,
            'campsite_id' => $campsiteId,
            'previous_map_feature_id' =>
                $currentFeatureId > 0
                    ? $currentFeatureId
                    : null,
            'map_feature_id' =>
                $nextFeatureId > 0
                    ? $nextFeatureId
                    : null,
        ]
    );
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

        if (array_key_exists($key, $input)) {
            $raw =
                $input[$key];
        } elseif ($fieldType === 'checkbox') {
            /*
             * Unchecked checkboxes are omitted by FormData. For an active
             * selected Site that omission means an explicit unchecked value,
             * not "leave the previous override unchanged".
             */
            $raw = '0';
        } else {
            continue;
        }

        if (is_array($raw)) {
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
         * Imported campsite data is the canonical baseline, not an Admin
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

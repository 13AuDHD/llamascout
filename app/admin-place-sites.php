<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-report/campsite-baseline.php';
require_once __DIR__ . '/place-campsites.php';
require_once __DIR__ . '/place-map-features.php';
require_once __DIR__ . '/place-report/scoped-answers.php';
require_once __DIR__ . '/place-report/report-targets.php';
require_once __DIR__ . '/place-report/target-model.php';

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
/**
 * Site-eligible Place-layout questions require independent Site controls.
 * The parent Place form must never be reused to save these overrides.
 *
 * @return list<string>
 */
function admin_place_site_shared_question_keys(): array
{
    $keys = [];
    foreach (llama_report_questions_for_scope('site', true) as $key => $field) {
        if ((string) ($field['layout_section'] ?? '') === 'site'
            || !empty($field['derived'])
            || !empty($field['pricing_managed'])
            || !empty($field['location_field'])
            || in_array($key, ['site_number', 'visited_at'], true)) {
            continue;
        }
        $keys[] = $key;
    }
    return $keys;
}

function admin_place_campsite_field_keys(): array
{
    $keys = [];

    foreach (llama_report_questions_for_scope('site', true) as $key => $field) {
        /*
         * Admin Place edits Place-level and selected-Site facts in one shared
         * form. The visible Site section changes target when a campsite is
         * selected, so every field rendered there that legally allows Site
         * scope belongs to the selected-Site load/save path.
         *
         * This deliberately includes both Site-only fields and shared
         * "all" fields such as sensory, safety, experience, and summaries.
         * Place-layout fields eligible on Sites are edited using separate
         * Site-only controls, not the parent Place controls.
         */
        if (
            (string) (
                $field['layout_section']
                ?? ''
            ) !== 'site'
            && !in_array($key, admin_place_site_shared_question_keys(), true)
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
            || !empty($field['pricing_managed'])
            || $key === 'site_number'
            || $key === 'visited_at'
            || !empty($field['location_field'])
        ) {
            continue;
        }

        $keys[] =
            (string) $key;
    }

    return $keys;
}

/**
 * Admin's legacy Site editor exposes only the Site layout section.
 * Reject extra submitted question keys instead of silently discarding them,
 * especially shared Place/Site questions which need separate target controls.
 *
 * @return array<string,mixed>
 */
function admin_place_validate_site_answer_keys(array $answers): array
{
    $allowed = array_fill_keys(admin_place_campsite_field_keys(), true);
    foreach ($answers as $key => $_value) {
        if (!is_string($key) || !isset($allowed[$key])) {
            throw new InvalidArgumentException(
                'This Site question is not editable in the current Admin Site form. '
                . 'Reload the selected Site and try again.'
            );
        }
    }
    return $answers;
}

/**
 * Report the Site-eligible canonical fields that Admin's current Site
 * layout cannot edit. Audit-only. Changing this list requires coordinated
 * controls, identity-specific prefilling and scoped persistence.
 */
function admin_place_site_inventory_gap(): array
{
    $editable = array_fill_keys(admin_place_campsite_field_keys(), true);
    $missing = [];
    foreach (llama_report_questions_for_scope('site', true) as $key => $field) {
        if (!empty($field['derived']) || !empty($field['pricing_managed'])
            || !empty($field['location_field'])
            || in_array($key, ['site_number', 'visited_at'], true)) {
            continue;
        }
        if (!isset($editable[$key])) {
            $missing[$key] = [
                'label' => (string) ($field['label'] ?? $key),
                'layout_section' => (string) ($field['layout_section'] ?? ''),
                'scope_group' => (string) ($field['report_scope_group'] ?? ''),
            ];
        }
    }
    return $missing;
}

function admin_place_site_section_place_field_keys(): array
{
    $keys = [];

    foreach (llama_report_questions_for_scope('place', true) as $key => $field) {
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
            || !empty($field['pricing_managed'])
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
    if (trim((string) ($input['selected_campsite_id'] ?? '')) === ''
        && trim((string) ($input['admin_bulk_site_answers_json'] ?? '')) === '') {
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
    return llama_place_report_campsite_feature_value($site, $key);
}

function admin_place_campsite_bool_form_value(mixed $value): ?string
{
    return llama_place_report_campsite_bool_form_value($value);
}

function admin_place_campsite_surface_form_value(mixed $value): ?string
{
    return llama_place_report_campsite_surface_form_value($value);
}

function admin_place_campsite_grade_form_value(mixed $value): ?string
{
    return llama_place_report_campsite_grade_form_value($value);
}

function admin_place_campsite_electric_service_form_value(mixed $value): ?string
{
    return llama_place_report_campsite_electric_service_form_value($value);
}

function admin_place_campsite_imported_form_values(array $site): array
{
    return llama_place_report_campsite_imported_form_values($site);
}

function admin_place_apply_site_overrides(array $values, array $overrides): array
{
    $fields = llama_place_report_fields();

    foreach ($overrides as $key => $value) {
        if (!isset($fields[$key])) {
            continue;
        }

        if (!is_array($value) || $fields[$key]['type'] === 'multiselect') {
            $values[$key] = llama_place_report_answer_form_value($fields[$key], $value);
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

        /*
         * Admin Place runs Site identity normalization before this catalog is
         * built. A Site that is still map_feature-only at this point is an
         * integrity conflict, not a second editable Site type.
         */
        if (
            $targetId < 1
            || $source !== 'campsite_record'
        ) {
            continue;
        }

        $site = $canonicalSites[$targetId] ?? null;

        if (!is_array($site)) {
            continue;
        }

        $label = trim((string) ($target['label'] ?? ''));

        if ($label === '') {
            $label = 'Site #' . $targetId;
        }

        $mapFeatureId =
            max(
                0,
                (int) (
                    $target['map_feature_id']
                    ?? 0
                )
            );

        $parentAreaId =
            max(
                0,
                (int) (
                    $target['parent_area_id']
                    ?? 0
                )
            );

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

        $catalog[] = [
            'id' =>
                'campsite_record:' . $targetId,

            'scope_id' =>
                $targetId,

            'scope_source' =>
                'campsite_record',

            'latitude' =>
                isset($site['latitude'])
                && is_numeric($site['latitude'])
                    ? (float) $site['latitude']
                    : null,

            'longitude' =>
                isset($site['longitude'])
                && is_numeric($site['longitude'])
                    ? (float) $site['longitude']
                    : null,

            'map_feature_id' =>
                $mapFeatureId > 0
                    ? $mapFeatureId
                    : null,

            'parent_area_id' =>
                $parentAreaId > 0
                    ? $parentAreaId
                    : null,

            'label' =>
                $label,

            'source_provider' =>
                (string) (
                    $site['source_provider']
                    ?? ''
                ),

            'source_external_id' =>
                (string) (
                    $site['source_external_id']
                    ?? ''
                ),

            'imported_values' =>
                $importedValues,

            'override_values' =>
                $overrideValues,

            'form_values' =>
                $formValues,
        ];
    }

    return $catalog;
}

function admin_place_parse_site_target(string $value): array
{
    if (
        preg_match(
            '/^campsite_record:([1-9][0-9]*)$/D',
            trim($value),
            $matches
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Site reporting target.'
        );
    }

    return [
        'source' =>
            'campsite_record',

        'id' =>
            (int) $matches[1],
    ];
}

/**
 * Save the child Site's optional GPS point without rewriting Place coordinates.
 * A mapped Site is moved only through Edit Map, which owns its linked geometry.
 * The caller has verified the Site belongs to the Place and owns the transaction.
 */
function admin_place_save_selected_site_coordinates(
    PDO $db, int $actorUserId, int $placeId, int $siteId, array $input
): bool {
    if ((string) ($input['admin_site_coordinates_changed'] ?? '') !== '1') {
        return false;
    }
    $coordinates = llama_place_report_parse_first_site_coordinates(
        $input['site_coordinates'] ?? ''
    );
    $lookup = $db->prepare(
        'SELECT latitude, longitude, map_feature_id
         FROM place_campsites WHERE id = ? AND place_id = ? AND is_active = 1 FOR UPDATE'
    );
    $lookup->execute([$siteId, $placeId]);
    $current = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new InvalidArgumentException('The selected Site was not found.');
    }
    if ((int) ($current['map_feature_id'] ?? 0) > 0) {
        throw new InvalidArgumentException(
            'This Site has a map pin. Use Edit Map to move the pin and its coordinates together.'
        );
    }

    $latitude = $coordinates['latitude'] ?? null;
    $longitude = $coordinates['longitude'] ?? null;
    if (($current['latitude'] === null && $latitude === null
            && $current['longitude'] === null && $longitude === null)
        || ($latitude !== null && $longitude !== null
            && $current['latitude'] !== null && $current['longitude'] !== null
            && round((float) $current['latitude'], 7) === $latitude
            && round((float) $current['longitude'], 7) === $longitude)) {
        return false;
    }

    $write = $db->prepare(
        'UPDATE place_campsites SET latitude = ?, longitude = ?
         WHERE id = ? AND place_id = ? AND is_active = 1'
    );
    $write->execute([$latitude, $longitude, $siteId, $placeId]);
    if ($write->rowCount() !== 1) {
        throw new RuntimeException('Site coordinates could not be saved.');
    }
    admin_users_audit($db, $actorUserId, null,
        'place.site_location_updated', 'Updated an individual Site location.',
        ['place_id' => $placeId, 'site_id' => $siteId,
         'latitude' => $latitude, 'longitude' => $longitude]);
    return true;
}

/**
 * Applicability of a new Admin answer uses the complete prospective target,
 * not just the fields in the POST. Clearing an override is allowed; it does
 * not constitute a new observation. Explicit Admin override is individual
 * only and cannot bypass target ownership or canonical answer parsing.
 */
function admin_place_assert_answer_applicability(
    array $effective, array $keys, bool $override = false, string $targetLabel = 'this report'
): void {
    if ($override) { return; }
    foreach ($keys as $key) {
        if (!llama_place_report_question_applicable($effective, (string) $key)) {
            $field = llama_report_question((string) $key);
            throw new InvalidArgumentException(
                ($field['label'] ?? $key) . ' does not apply to ' . $targetLabel
                . '. Check the parent answers or edit the individual report using the explicit Admin override.'
            );
        }
    }
}

function admin_place_site_applicability_input(array $values): array
{
    $input = [];
    foreach ($values as $key => $value) {
        $field = llama_report_question((string) $key);
        if ($field !== null) {
            $input[$key] = llama_place_report_answer_form_value($field, $value);
        }
    }
    return $input;
}

/** Determine actual bulk writes before validating the whole prospective Site. */
function admin_place_plan_bulk_site_answers(
    array $effective, array $existing, array $imported, array $parsed,
    bool $overwrite, string $targetLabel
): array {
    $writes = [];
    foreach ($parsed as $key => $value) {
        if (!$overwrite && (array_key_exists($key, $existing)
            || (array_key_exists($key, $imported)
                && $imported[$key] !== '' && $imported[$key] !== null
                && $imported[$key] !== llama_place_report_unanswered_token()))) {
            continue;
        }
        if (array_key_exists($key, $existing)
            && llama_scoped_report_values_equal($existing[$key], $value)) {
            continue;
        }
        $writes[$key] = $value;
    }
    admin_place_assert_answer_applicability(
        admin_place_site_applicability_input(array_replace($effective, $writes)),
        array_keys($writes), false, $targetLabel
    );
    return $writes;
}

function admin_place_save_selected_campsite_answers(PDO $db, int $actorUserId, int $placeId, array $input): ?int
{
    $selected = trim((string) ($input['selected_campsite_id'] ?? ''));
    if ($selected === '') {
        if ((string) ($input['admin_site_coordinates_changed'] ?? '') === '1') {
            throw new InvalidArgumentException('Choose an individual Site before saving Site coordinates.');
        }
        return null;
    }
    $parsed = admin_place_parse_site_target($selected);
    $source = (string) $parsed['source'];
    $siteId = (int) $parsed['id'];
    $target = llama_report_verified_target($db, $placeId, 'site', $source, $siteId);
    $site = llama_place_campsite($db, $placeId, $siteId);
    if (!$site) { throw new InvalidArgumentException('The selected Site is not available for this Place.'); }
    $payload = (string) ($input['admin_site_answers_json'] ?? '');
    if ($payload === '' || strlen($payload) > 200000) { throw new InvalidArgumentException('Site answers are missing or too large.'); }
    $answers = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($answers) || array_is_list($answers) && $answers !== []) {
        throw new InvalidArgumentException('Invalid Site answer format.');
    }
    // An unknown or currently unrendered Site-eligible key is a form/catalog
    // mismatch, not permission to drop the observation without warning.
    $answers = admin_place_validate_site_answer_keys($answers);
    $fields = llama_place_report_fields();
    $imported = llama_place_report_campsite_imported_form_values($site);
    $existing = llama_scoped_report_load($db, $placeId, 'site', $siteId, $source);
    $model = llama_place_report_target_model($db, $placeId, $target);
    $effective = $model['values'];
    $fallback = array_replace($model['inherited'] ?? [], $model['baseline'] ?? []);
    $newKeys = [];
    foreach ($answers as $key => $raw) {
        if ($raw === null || $raw === '' || $raw === llama_place_report_unanswered_token()
            || (array_key_exists($key, $imported)
                && llama_place_report_values_equal($raw, $imported[$key], $fields[$key]))) {
            unset($effective[$key]);
            if (array_key_exists($key, $fallback)) { $effective[$key] = $fallback[$key]; }
            continue;
        }
        if (array_key_exists($key, $existing)
            && llama_place_report_values_equal($raw, $existing[$key], $fields[$key])) { continue; }
        $answer = llama_place_report_submission_answer($fields[$key], $raw);
        if (!$answer['recorded']) {
            throw new InvalidArgumentException('Invalid Site answer. Clear the override explicitly instead.');
        }
        $effective[$key] = $answer['scoped_value'];
        $newKeys[] = $key;
    }
    $override = (string) ($input['admin_applicability_override'] ?? '') === '1';
    admin_place_assert_answer_applicability(
        admin_place_site_applicability_input($effective), $newKeys, $override, 'the selected Site'
    );
    $saved = 0;
    foreach (admin_place_campsite_field_keys() as $key) {
        if (!array_key_exists($key, $answers)) { continue; }
        $raw = $answers[$key];
        if ($raw === null || $raw === '' || $raw === llama_place_report_unanswered_token()) {
            llama_scoped_report_clear_override($db, $placeId, 'site', $siteId, $source, $key);
            continue;
        }
        if (array_key_exists($key, $imported)
            && llama_place_report_values_equal($raw, $imported[$key], $fields[$key])) {
            // The imported record stays the baseline for future import refreshes.
            llama_scoped_report_clear_override($db, $placeId, 'site', $siteId, $source, $key);
            continue;
        }
        if (array_key_exists($key, $existing)
            && llama_place_report_values_equal($raw, $existing[$key], $fields[$key])) { continue; }
        // Normalize exactly once in the shared scoped answer writer.
        llama_scoped_report_save($db, $placeId, 'site', $siteId, $source, $key, $raw);
        ++$saved;
    }
    admin_place_save_selected_site_coordinates(
        $db, $actorUserId, $placeId, $siteId, $input
    );
    admin_users_audit($db, $actorUserId, null, 'place.site_report_updated',
        'Updated an individual Site report.', ['place_id'=>$placeId,'site_id'=>$siteId,'answers_saved'=>$saved,
            'applicability_override'=>$override]);
    return $siteId;
}


/**
 * Explicit bulk Site answers. Never invoked by a normal Place save.
 * Each submitted key must be a writable Site field and a nonblank value.
 * Existing Site overrides and imported values are protected by default.
 * Caller owns the surrounding transaction and permission checks.
 */
function admin_place_bulk_save_site_answers(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): array {
    $raw = trim((string) ($input['admin_bulk_site_answers_json'] ?? ''));
    if ($raw === '') {
        return ['sites' => 0, 'answers' => 0];
    }
    if (trim((string) ($input['selected_campsite_id'] ?? '')) !== '') {
        throw new InvalidArgumentException('Bulk Site editing requires the Place target.');
    }
    if (strlen($raw) > 200000) {
        throw new InvalidArgumentException('Bulk Site answers are too large.');
    }
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || !isset($payload['answers']) || !is_array($payload['answers'])
        || (array_is_list($payload['answers']) && $payload['answers'] !== [])) {
        throw new InvalidArgumentException('Invalid bulk Site answer format.');
    }
    $overwrite = ($payload['overwrite'] ?? false) === true;
    $answers = $payload['answers'];
    if (!$answers) {
        throw new InvalidArgumentException('Choose at least one Site answer for bulk editing.');
    }
    $allowed = array_flip(admin_place_campsite_field_keys());
    $definitions = llama_place_report_fields();
    $unknownToken = llama_place_report_unknown_token();
    $unansweredToken = llama_place_report_unanswered_token();
    $parsed = [];
    foreach ($answers as $key => $rawValue) {
        if (!is_string($key) || !isset($allowed[$key]) || !isset($definitions[$key])) {
            throw new InvalidArgumentException('Bulk editing includes an invalid Site field.');
        }
        if (!empty($definitions[$key]['location_field'])) {
            throw new InvalidArgumentException('Location fields cannot be bulk edited.');
        }
        if (!is_string($rawValue) && !is_array($rawValue)) {
            throw new InvalidArgumentException('Invalid bulk Site answer value.');
        }
        if (is_string($rawValue)) {
            $rawValue = trim($rawValue);
            if ($rawValue === '' || $rawValue === $unansweredToken) {
                throw new InvalidArgumentException('Blank answers cannot be bulk applied.');
            }
        }
        $answer = llama_place_report_submission_answer($definitions[$key], $rawValue);
        if (!$answer['recorded']) {
            throw new InvalidArgumentException('Unanswered values cannot be bulk applied.');
        }
        $parsed[$key] = $answer['scoped_value'];
    }

    $sites = llama_place_campsites($db, $placeId);
    if (!$sites) {
        throw new InvalidArgumentException('There are no active Sites to update.');
    }
    // Resolve the same parent context for all Sites in one canonical batch
    // instead of querying every Place answer repeatedly for each Site.
    $placeValues = llama_place_report_current_answer_values(
        llama_place_report_current_place_answers($db, $placeId)
    );
    $targets = [];
    foreach ($sites as $site) {
        $id = (int) ($site['id'] ?? 0);
        if ($id > 0) {
            $targets[$id] = llama_report_verified_target($db, $placeId, 'site', 'campsite_record', $id);
        }
    }
    $resolved = llama_scoped_report_effective_answers_for_targets($db, $placeId, $placeValues, array_values($targets));
    $siteCount = 0;
    $savedCount = 0;
    $plans = [];
    foreach ($sites as $site) {
        $siteId = (int) ($site['id'] ?? 0);
        if ($siteId < 1) {
            continue;
        }
        $target = $targets[$siteId];
        $source = (string) $target['source'];
        $targetId = (int) $target['id'];
        $model = llama_place_report_with_campsite_baseline(
            $resolved['site:' . $source . ':' . $targetId], $site
        );
        $plans[$targetId] = admin_place_plan_bulk_site_answers(
            $model['values'], $model['overrides'], admin_place_campsite_imported_form_values($site),
            $parsed, $overwrite, (string) ($target['label'] ?? ('Site #' . $targetId))
        );
    }
    // All intended writes have passed validation before any Site is changed.
    // The surrounding transaction also protects against persistence failure.
    foreach ($plans as $targetId => $writes) {
        if (!$writes) { continue; }
        foreach ($writes as $key => $value) {
            llama_scoped_report_save($db, $placeId, 'site', $targetId, 'campsite_record', $key, $value);
            ++$savedCount;
        }
        ++$siteCount;
    }
    admin_users_audit($db, $actorUserId, null,
        'place.site_report_bulk_updated',
        'Bulk updated Site report answers.',
        [
            'place_id' => $placeId,
            'total_active_sites' => count($sites),
            'sites_updated' => $siteCount,
            'answers_saved' => $savedCount,
            'field_keys' => array_keys($parsed),
            'overwrite_existing' => $overwrite,
        ]
    );
    return ['sites' => $siteCount, 'answers' => $savedCount];
}

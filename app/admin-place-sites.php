<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-report/campsite-baseline.php';
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

        $type = (string) ($fields[$key]['type'] ?? '');

        if ($value === llama_place_report_unknown_token()) {
            $values[$key] = llama_place_report_unknown_token();
        } elseif ($value === null) {
            // A cleared answer must suppress the imported baseline on reload.
            $values[$key] = llama_place_report_answer_form_value($fields[$key], null);
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

function admin_place_save_selected_campsite_answers(PDO $db, int $actorUserId, int $placeId, array $input): ?int
{
    $selected = trim((string) ($input['selected_campsite_id'] ?? ''));
    if ($selected === '') { return null; }
    $parsed = admin_place_parse_site_target($selected);
    $source = (string) $parsed['source'];
    $siteId = (int) $parsed['id'];
    llama_report_verified_target($db, $placeId, 'site', $source, $siteId);
    $site = llama_place_campsite($db, $placeId, $siteId);
    if (!$site) { throw new InvalidArgumentException('The selected Site is not available for this Place.'); }
    $payload = (string) ($input['admin_site_answers_json'] ?? '');
    if ($payload === '' || strlen($payload) > 200000) { throw new InvalidArgumentException('Site answers are missing or too large.'); }
    $answers = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($answers) || array_is_list($answers) && $answers !== []) {
        throw new InvalidArgumentException('Invalid Site answer format.');
    }
    $fields = llama_place_report_fields();
    $imported = llama_place_report_campsite_imported_form_values($site);
    $existing = llama_scoped_report_load($db, $placeId, 'site', $siteId, $source);
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
    admin_users_audit($db, $actorUserId, null, 'place.site_report_updated',
        'Updated an individual Site report.', ['place_id'=>$placeId,'site_id'=>$siteId,'answers_saved'=>$saved]);
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
        $unknownFields = [];
        $parsed[$key] = $rawValue === $unknownToken
            ? $unknownToken
            : llama_place_report_parse_field($definitions[$key], $rawValue, $unknownFields);
    }

    $sites = llama_place_campsites($db, $placeId);
    if (!$sites) {
        throw new InvalidArgumentException('There are no active Sites to update.');
    }
    $siteCount = 0;
    $savedCount = 0;
    foreach ($sites as $site) {
        $siteId = (int) ($site['id'] ?? 0);
        if ($siteId < 1) {
            continue;
        }
        $target = llama_report_verified_target($db, $placeId, 'site', 'campsite_record', $siteId);
        $source = (string) ($target['source'] ?? 'campsite_record');
        $targetId = (int) ($target['id'] ?? $siteId);
        $existing = llama_scoped_report_load($db, $placeId, 'site', $targetId, $source);
        $imported = admin_place_campsite_imported_form_values($site);
        $siteSaved = false;
        foreach ($parsed as $key => $value) {
            if (!$overwrite && (array_key_exists($key, $existing)
                || (array_key_exists($key, $imported)
                    && $imported[$key] !== ''
                    && $imported[$key] !== null
                    && $imported[$key] !== $unansweredToken))) {
                continue;
            }
            if (array_key_exists($key, $existing)
                && llama_scoped_report_values_equal($existing[$key], $value)) {
                continue;
            }
            llama_scoped_report_save($db, $placeId, 'site', $targetId, $source, $key, $value);
            $savedCount++;
            $siteSaved = true;
        }
        if ($siteSaved) {
            $siteCount++;
        }
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

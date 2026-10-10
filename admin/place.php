<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-answers.php';
require_once dirname(__DIR__) . '/app/place-verifications.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$placeId =
    (int) (
        $_GET['id']
        ?? $_POST['place_id']
        ?? 0
    );

if ($placeId < 1) {
    header('Location: /places.php');
    exit;
}

function admin_place_shared_report_data(
    PDO $db,
    int $placeId
): array {
    $place =
        admin_place_get(
            $db,
            $placeId
        );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $place['amenities'] =
        admin_place_row(
            $db,
            'place_amenities',
            $placeId
        );

    $place['connectivity'] =
        admin_place_row(
            $db,
            'place_connectivity',
            $placeId
        );

    $place['details'] =
        admin_place_row(
            $db,
            'place_details',
            $placeId
        );

    $place['rules'] =
        admin_place_row(
            $db,
            'place_rules',
            $placeId
        );

    $place['experience'] =
        admin_place_row(
            $db,
            'place_experience',
            $placeId
        );

    $place['sensory'] = [
        'daytime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'daytime'
            ),
        'nighttime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'nighttime'
            ),
    ];

    $place['sensory_details'] =
        admin_place_row(
            $db,
            'place_sensory_details',
            $placeId
        );

    return
        llama_place_report_data_from_published_place(
            $place,
            llama_place_report_published_answer_state(
                $db,
                $placeId
            )
        );
}


function admin_place_campsite_field_keys(): array
{
    $keys = [];

    foreach (llama_place_report_fields() as $key => $field) {
        if ((string) ($field['section'] ?? '') !== 'site_vehicle') {
            continue;
        }

        if (!in_array('site', (array) ($field['report_scopes'] ?? []), true)) {
            continue;
        }

        if (!empty($field['derived']) || $key === 'site_number') {
            continue;
        }

        $keys[] = (string) $key;
    }

    return $keys;
}

function admin_place_without_campsite_fields(array $input): array
{
    if ((int) ($input['selected_campsite_id'] ?? 0) < 1) {
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
        str_contains($text, 'paved'), str_contains($text, 'asphalt') => 'paved',
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
    $text = preg_replace('/\\s+/', '', strtolower(trim((string) $value))) ?? '';

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
     * Campsite type is authoritative enough to answer the broad equipment
     * suitability questions. This prevents a Tent Only RIDB site from ever
     * looking like an ordinary RV-capable site in the Scout Report.
     *
     * Existing imports may predate the STANDARD -> mixed_site normalization,
     * so raw_site_type is also inspected. Explicit Scout overrides loaded
     * later still win over these imported defaults.
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
    if ($hike !== null && preg_match('/-?\\d+(?:\\.\\d+)?/', (string) $hike, $match) === 1) {
        $values['hike_in_distance_feet'] = $match[0];
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

    $fields = llama_place_report_fields();

    foreach ($overrides as $key => $value) {
        if (!isset($fields[$key])) {
            continue;
        }

        $type = (string) ($fields[$key]['type'] ?? '');

        if ($value === llama_place_report_unknown_token()) {
            $values[$key] = llama_place_report_unknown_token();
        } elseif ($type === 'tri' && is_bool($value)) {
            $values[$key] = $value ? '1' : '0';
        } elseif ($value !== null && !is_array($value)) {
            $values[$key] = (string) $value;
        }
    }

    return $values;
}

function admin_place_campsite_form_catalog(PDO $db, int $placeId): array
{
    $catalog = [];

    foreach (llama_place_campsites($db, $placeId) as $site) {
        $siteId = (int) ($site['id'] ?? 0);
        if ($siteId < 1) {
            continue;
        }

        $catalog[] = [
            'id' => $siteId,
            'label' => (string) ($site['display_name'] ?? ('Site ' . $siteId)),
            'source_provider' => (string) ($site['source_provider'] ?? ''),
            'source_external_id' => (string) ($site['source_external_id'] ?? ''),
            'form_values' => admin_place_campsite_effective_form_values(
                $db,
                $placeId,
                $site
            ),
        ];
    }

    return $catalog;
}

function admin_place_save_selected_campsite_answers(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): ?int {
    $campsiteId = (int) ($input['selected_campsite_id'] ?? 0);

    if ($campsiteId < 1) {
        return null;
    }

    $site = llama_place_campsite($db, $placeId, $campsiteId);
    if (!$site) {
        throw new InvalidArgumentException(
            'The selected campsite does not belong to this Place.'
        );
    }

    $fields = llama_place_report_fields();
    $imported = admin_place_campsite_imported_form_values($site);
    $unknownToken = llama_place_report_unknown_token();
    $unansweredToken = llama_place_report_unanswered_token();
    $saved = 0;

    foreach (admin_place_campsite_field_keys() as $key) {
        if (!array_key_exists($key, $input) || !isset($fields[$key])) {
            continue;
        }

        $raw = $input[$key];
        if (is_array($raw)) {
            continue;
        }

        $rawString = trim((string) $raw);

        if ($rawString === '' || $rawString === $unansweredToken) {
            llama_scoped_report_clear_override(
                $db,
                $placeId,
                'site',
                $campsiteId,
                'campsite_record',
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
         * Any explicit campsite answer is a deliberate campsite override.
         *
         * Do not discard it merely because its normalized numeric value
         * matches the imported source value. The Scout may be intentionally
         * converting an exact imported value such as 45.0 ft to the
         * standardized "About 45 ft" choice. Clearing that override makes the
         * imported presentation return the next time the campsite is loaded.
         *
         * The existing blank / unanswered path above remains the explicit
         * way to clear an override and fall back to imported data.
         */
        llama_scoped_report_save(
            $db,
            $placeId,
            'site',
            $campsiteId,
            'campsite_record',
            $key,
            $value
        );
        $saved++;
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.campsite_report_updated',
        'Updated an individual campsite report.',
        [
            'place_id' => $placeId,
            'campsite_id' => $campsiteId,
            'answers_saved' => $saved,
        ]
    );

    return $campsiteId;
}


function admin_place_save_shared_report(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): array {
    $place =
        admin_place_get(
            $db,
            $placeId
        );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $baseData =
        admin_place_shared_report_data(
            $db,
            $placeId
        );

    $placeLevelInput =
        admin_place_without_campsite_fields(
            $input
        );

    $reportData =
        llama_place_report_build_data(
            $placeLevelInput,
            $baseData
        );

    $coreData = [
        'name' =>
            $reportData['name']
            ?? $place['name']
            ?? '',
        'type' =>
            $reportData['type']
            ?? $place['type']
            ?? 'other',
        'slug' =>
            trim(
                (string) (
                    $input['admin_slug']
                    ?? $place['slug']
                    ?? ''
                )
            ),
        'source_type' =>
            trim(
                (string) (
                    $input['admin_source_type']
                    ?? $place['source_type']
                    ?? 'llama-scouted'
                )
            ),
        'description' =>
            $reportData['description']
            ?? null,
        'public_summary' =>
            trim(
                (string) (
                    $input['admin_public_summary']
                    ?? $place['public_summary']
                    ?? ''
                )
            ),
        'public_location_label' =>
            trim(
                (string) (
                    $input['admin_public_location_label']
                    ?? $place['public_location_label']
                    ?? ''
                )
            ),
        'latitude' =>
            $reportData['latitude']
            ?? null,
        'longitude' =>
            $reportData['longitude']
            ?? null,
        'public_latitude' =>
            $place['public_latitude']
            ?? null,
        'public_longitude' =>
            $place['public_longitude']
            ?? null,
        'elevation_feet' =>
            $reportData['elevation_feet']
            ?? null,
        'road' =>
            $reportData['road']
            ?? null,
        'city' =>
            $reportData['city']
            ?? null,
        'county' =>
            $reportData['county']
            ?? null,
        'state' =>
            $reportData['state']
            ?? null,
        'region' =>
            $reportData['region']
            ?? null,
        'land_manager' =>
            $reportData['land_manager']
            ?? null,
        'land_type' =>
            $reportData['land_type']
            ?? null,
        'sensory_summary' =>
            $reportData['sensory_summary']
            ?? null,
        'access_summary' =>
            $reportData['access_summary']
            ?? null,
    ];

    $amenities =
        is_array(
            $reportData['amenities']
            ?? null
        )
            ? $reportData['amenities']
            : [];

    $connectivity =
        is_array(
            $reportData['connectivity']
            ?? null
        )
            ? $reportData['connectivity']
            : [];

    $details =
        is_array(
            $reportData['details']
            ?? null
        )
            ? $reportData['details']
            : [];

    $rules =
        is_array(
            $reportData['rules']
            ?? null
        )
            ? $reportData['rules']
            : [];

    $experience =
        is_array(
            $reportData['experience']
            ?? null
        )
            ? $reportData['experience']
            : [];

    $sensory =
        is_array(
            $reportData['sensory']
            ?? null
        )
            ? $reportData['sensory']
            : [];

    $sensoryPayload =
        is_array(
            $sensory['details']
            ?? null
        )
            ? $sensory['details']
            : [];

    foreach (
        ['daytime', 'nighttime']
        as $period
    ) {
        $periodData =
            is_array(
                $sensory[$period]
                ?? null
            )
                ? $sensory[$period]
                : [];

        foreach (
            [
                'noise',
                'traffic',
                'crowds',
                'privacy',
                'light_pollution',
                'sensory_comfort',
                'social_interaction_likelihood',
            ]
            as $field
        ) {
            $sensoryPayload[
                $period . '_' . $field
            ] =
                $periodData[$field]
                ?? null;
        }
    }

    $ownsTransaction =
        !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        admin_place_save_core(
            $db,
            $actorUserId,
            $placeId,
            $coreData
        );

        admin_place_save_amenities(
            $db,
            $actorUserId,
            $placeId,
            $amenities
        );

        admin_place_save_connectivity(
            $db,
            $actorUserId,
            $placeId,
            $connectivity
        );

        admin_place_save_details(
            $db,
            $actorUserId,
            $placeId,
            $details
        );

        admin_place_save_sensory_details(
            $db,
            $actorUserId,
            $placeId,
            $sensoryPayload
        );

        admin_place_save_rules(
            $db,
            $actorUserId,
            $placeId,
            $rules
        );

        admin_place_save_experience(
            $db,
            $actorUserId,
            $placeId,
            $experience
        );

        llama_place_report_publish_answer_state(
            $db,
            $placeId,
            $reportData
        );

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.shared_report_updated',
            'Updated the shared Place Report.',
            [
                'place_id' =>
                    $placeId,
            ]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $reportData;
}


function admin_place_photo_url(
    mixed $photo
): string {
    if (is_array($photo)) {
        $src =
            trim(
                (string) (
                    $photo['src']
                    ?? $photo['path']
                    ?? ''
                )
            );
    } else {
        $src =
            trim(
                (string) $photo
            );
    }

    if ($src === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $src
        )
    ) {
        return $src;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $src,
            '/'
        );
}


/*
 * Add Place photos while an outer database transaction is already active.
 *
 * The shared admin_place_add_photos() helper owns its own transaction,
 * which is correct for standalone photo actions but cannot be nested inside
 * the all-or-nothing Place Report save. This local helper performs the same
 * database work without starting or committing a second transaction.
 *
 * $committedForCleanup receives the permanent photo paths that were moved
 * from staging. If the outer database transaction later fails, the caller
 * removes those moved files so the filesystem does not claim a save that
 * the database rolled back.
 */
function admin_place_add_photos_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $photoToken,
    array $photos,
    array &$committedForCleanup
): int {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Place Report photo attachment requires an active database transaction.'
        );
    }

    $existing =
        admin_place_images(
            $db,
            $placeId
        );

    $remaining =
        max(
            0,
            30 - count($existing)
        );

    if ($remaining < 1) {
        throw new RuntimeException(
            'This Place already has the maximum of 30 photos.'
        );
    }

    if (count($photos) > $remaining) {
        throw new RuntimeException(
            'You can add only '
            . $remaining
            . ' more photos.'
        );
    }

    $committed =
        llama_photo_commit_stage(
            'add-place',
            $actorUserId,
            $photoToken,
            $photos,
            '/uploads/places/'
            . $placeId
        );

    /*
     * Expose moved files immediately. Anything below this point can
     * still throw, including a DB insert or the outer transaction commit.
     */
    $committedForCleanup =
        $committed;

    $orderStmt =
        $db->prepare(
            'SELECT COALESCE(MAX(sort_order), -1)
             FROM place_images
             WHERE place_id = ?'
        );

    $orderStmt->execute([
        $placeId
    ]);

    $sortOrder =
        (int) $orderStmt->fetchColumn()
        + 1;

    $hasFeatured =
        false;

    foreach ($existing as $image) {
        if (
            (int) (
                $image['is_featured']
                ?? 0
            ) === 1
        ) {
            $hasFeatured =
                true;

            break;
        }
    }

    $insert =
        $db->prepare(
            'INSERT INTO place_images (
                place_id,
                src,
                alt_text,
                is_featured,
                sort_order,
                uploaded_by
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );

    $inserted =
        0;

    foreach ($committed as $photo) {
        $path =
            trim(
                (string) (
                    $photo['path']
                    ?? ''
                )
            );

        if ($path === '') {
            continue;
        }

        $isFeatured =
            !$hasFeatured
            && $inserted === 0;

        $insert->execute([
            $placeId,
            $path,
            trim(
                (string) (
                    $photo['alt']
                    ?? ''
                )
            ) ?: null,
            $isFeatured ? 1 : 0,
            $sortOrder++,
            $actorUserId,
        ]);

        $inserted++;
    }

    if ($inserted !== count($committed)) {
        throw new RuntimeException(
            'One or more Place photos could not be attached.'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.photos_added',
        'Added Place photos.',
        [
            'place_id' =>
                $placeId,
            'count' =>
                $inserted,
        ]
    );

    return
        $inserted;
}


/*
 * Remove a Place image record inside the outer Place Report transaction,
 * but deliberately leave the physical file alone until AFTER commit.
 *
 * Returning the path lets the caller perform the irreversible filesystem
 * deletion only after every database part of Save Place Report succeeds.
 */
function admin_place_delete_image_in_report_transaction(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId
): string {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Place Report photo removal requires an active database transaction.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_images
             WHERE id = ?
               AND place_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $imageId,
        $placeId,
    ]);

    $image =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$image) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

    $path =
        trim(
            (string) (
                $image['src']
                ?? ''
            )
        );

    $wasFeatured =
        (int) (
            $image['is_featured']
            ?? 0
        ) === 1;

    $delete =
        $db->prepare(
            'DELETE FROM place_images
             WHERE id = ?
               AND place_id = ?'
        );

    $delete->execute([
        $imageId,
        $placeId,
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'Place image could not be removed.'
        );
    }

    if ($wasFeatured) {
        $next =
            $db->prepare(
                'SELECT id
                 FROM place_images
                 WHERE place_id = ?
                 ORDER BY
                    sort_order ASC,
                    id ASC
                 LIMIT 1'
            );

        $next->execute([
            $placeId
        ]);

        $nextId =
            (int) (
                $next->fetchColumn()
                ?: 0
            );

        if ($nextId > 0) {
            $db->prepare(
                'UPDATE place_images
                 SET is_featured = 1
                 WHERE id = ?'
            )->execute([
                $nextId
            ]);
        }
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.image_deleted',
        'Deleted a Place image.',
        [
            'place_id' =>
                $placeId,
            'image_id' =>
                $imageId,
        ]
    );

    return
        $path;
}



function admin_place_background_save_requested(): bool
{
    return strtolower(
        trim(
            (string) (
                $_SERVER['HTTP_X_LLAMA_ADMIN_SAVE']
                ?? ''
            )
        )
    ) === 'place-report';
}

function admin_place_background_save_respond(
    int $status,
    array $payload
): never {
    http_response_code($status);
    header(
        'Content-Type: application/json; charset=utf-8'
    );
    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


$backgroundPlaceSave =
    admin_place_background_save_requested();


$notice = '';
$error = '';
$action = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    && (string) ($_GET['saved'] ?? '') === 'report'
) {
    $notice =
        'Place Report saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        (string) (
            $_POST['place_admin_action']
            ?? ''
        );

    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            $action === 'save-report'
                ? 'Your session token expired. Your Place Report changes are preserved below. Try Save Place Report again.'
                : 'Your session token expired. Reload and try again.';

        if (
            $backgroundPlaceSave
            && $action === 'save-report'
        ) {
            admin_place_background_save_respond(
                403,
                [
                    'success' => false,
                    'message' => $error,
                    'photos_added' => 0,
                ]
            );
        }
    } else {
        try {
            if ($action === 'save-report') {

                $photosAdded =
                    0;

                $committedPhotosForCleanup =
                    [];

                $deferredPhotoDeletes =
                    [];

                /*
                 * One database transaction owns the entire Place Report save.
                 *
                 * Report answers, answer-state, photo rows, featured-image
                 * reassignment, and audit records either all commit together
                 * or all roll back together.
                 */
                $db->beginTransaction();

                try {
                    admin_place_save_shared_report(
                        $db,
                        $actorUserId,
                        $placeId,
                        $_POST
                    );

                    admin_place_save_selected_campsite_answers(
                        $db,
                        $actorUserId,
                        $placeId,
                        $_POST
                    );

                    $removePaths =
                        is_array(
                            $_POST['remove_existing_photos']
                            ?? null
                        )
                            ? array_values(
                                array_unique(
                                    array_filter(
                                        array_map(
                                            'strval',
                                            $_POST['remove_existing_photos']
                                        )
                                    )
                                )
                            )
                            : [];

                    if ($removePaths) {
                        foreach (
                            admin_place_images(
                                $db,
                                $placeId
                            )
                            as $image
                        ) {
                            if (
                                !in_array(
                                    (string) (
                                        $image['src']
                                        ?? ''
                                    ),
                                    $removePaths,
                                    true
                                )
                            ) {
                                continue;
                            }

                            $path =
                                admin_place_delete_image_in_report_transaction(
                                    $db,
                                    $actorUserId,
                                    $placeId,
                                    (int) $image['id']
                                );

                            if ($path !== '') {
                                $deferredPhotoDeletes[] =
                                    $path;
                            }
                        }
                    }

                    $photoToken =
                        trim(
                            (string) (
                                $_POST['photo_stage_token']
                                ?? ''
                            )
                        );

                    $newPhotos =
                        llama_photo_decode_form_photos(
                            $_POST['photos_json']
                            ?? '[]'
                        );

                    if ($newPhotos) {
                        if ($photoToken === '') {
                            throw new RuntimeException(
                                'The photo upload session is missing. Upload the photos again.'
                            );
                        }

                        /*
                         * Verify that every staged path submitted by the
                         * browser still exists before any file is moved.
                         */
                        $manifest =
                            llama_photo_read_manifest(
                                'add-place',
                                $actorUserId,
                                $photoToken
                            );

                        $manifestPaths =
                            [];

                        foreach ($manifest as $photo) {
                            $path =
                                trim(
                                    (string) (
                                        $photo['path']
                                        ?? ''
                                    )
                                );

                            if ($path !== '') {
                                $manifestPaths[$path] =
                                    true;
                            }
                        }

                        $submittedPaths =
                            [];

                        foreach ($newPhotos as $photo) {
                            $path =
                                trim(
                                    (string) (
                                        $photo['path']
                                        ?? ''
                                    )
                                );

                            if ($path !== '') {
                                $submittedPaths[$path] =
                                    true;
                            }
                        }

                        $missingPaths =
                            array_diff_key(
                                $submittedPaths,
                                $manifestPaths
                            );

                        if ($missingPaths) {
                            throw new RuntimeException(
                                'One or more staged photos are no longer available. Upload those photos again before saving.'
                            );
                        }

                        $photosAdded =
                            admin_place_add_photos_in_report_transaction(
                                $db,
                                $actorUserId,
                                $placeId,
                                $photoToken,
                                $newPhotos,
                                $committedPhotosForCleanup
                            );

                        if (
                            $photosAdded
                            !== count($newPhotos)
                        ) {
                            throw new RuntimeException(
                                'The Place Report could not save all staged photos.'
                            );
                        }
                    }

                    /*
                     * This is the single database commit for Save Place Report.
                     */
                    $db->commit();

                } catch (Throwable $saveException) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    /*
                     * A staging commit physically moved these files before the
                     * DB transaction finished. If the DB rolls back, remove the
                     * moved copies so permanent storage cannot contradict the DB.
                     */
                    foreach (
                        $committedPhotosForCleanup
                        as $photo
                    ) {
                        llama_photo_delete_owned_permanent_path(
                            (string) (
                                $photo['path']
                                ?? ''
                            ),
                            [
                                'uploads/places',
                            ]
                        );
                    }

                    throw $saveException;
                }

                /*
                 * Existing files are deleted only AFTER the database commit.
                 * If anything above fails, their DB rows roll back and their
                 * physical files remain untouched.
                 */
                foreach (
                    array_unique(
                        $deferredPhotoDeletes
                    )
                    as $path
                ) {
                    if (
                        !llama_photo_delete_owned_permanent_path(
                            (string) $path,
                            [
                                'uploads/places',
                            ]
                        )
                    ) {
                        error_log(
                            'Llama Scout could not remove committed Place photo file: '
                            . (string) $path
                        );
                    }
                }

                if ($backgroundPlaceSave) {
                    admin_place_background_save_respond(
                        200,
                        [
                            'success' =>
                                true,
                            'message' =>
                                'Place Report saved.',
                            'photos_added' =>
                                $photosAdded,
                            'photo_total' =>
                                count(
                                    admin_place_images(
                                        $db,
                                        $placeId
                                    )
                                ),
                            'csrf_token' =>
                                moderation_csrf_token(),
                        ]
                    );
                }

                header(
                    'Location: /place.php?id='
                    . $placeId
                    . '&saved=report',
                    true,
                    303
                );

                exit;

            } elseif ($action === 'change-status') {

                admin_place_change_status(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['status']
                        ?? ''
                    ),
                    (string) (
                        $_POST['status_reason']
                        ?? ''
                    )
                );

                $notice =
                    'Place status updated.';

            } elseif ($action === 'add-verification') {

                llama_place_add_verification(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Verification added.';

            } elseif ($action === 'delete-verification') {

                llama_place_delete_verification(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['verification_id']
                        ?? 0
                    )
                );

                $notice =
                    'Verification deleted.';

            } elseif ($action === 'featured-image') {

                admin_place_set_featured_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Featured image updated.';

            } elseif ($action === 'delete-image') {

                admin_place_delete_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place image deleted.';

            } elseif ($action === 'save-image-meta') {

                admin_place_save_image_metadata(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['alt_text']
                        ?? ''
                    ),
                    (int) (
                        $_POST['sort_order']
                        ?? 0
                    )
                );

                $notice =
                    'Photo caption and order updated.';

            } elseif ($action === 'add-note') {

                admin_place_add_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['note']
                        ?? ''
                    )
                );

                $notice =
                    'Place note added.';

            } elseif ($action === 'delete-note') {

                admin_place_delete_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['note_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place note deleted.';
            }

        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();

            if (
                $backgroundPlaceSave
                && $action === 'save-report'
            ) {
                $status =
                    $exception instanceof PDOException
                        ? 500
                        : 422;

                $payload = [
                    'success' => false,
                    'message' => $error !== ''
                        ? $error
                        : 'The Place Report could not be saved.',
                    'photos_added' => 0,
                ];

                if (
                    $status === 500
                    && function_exists(
                        'llama_log_exception'
                    )
                ) {
                    $payload['reference'] =
                        llama_log_exception(
                            $exception,
                            'admin.place_report_save',
                            [
                                'place_id' =>
                                    $placeId,
                                'actor_user_id' =>
                                    $actorUserId,
                            ]
                        );
                }

                admin_place_background_save_respond(
                    $status,
                    $payload
                );
            }
        }
    }
}


$place =
    admin_place_get(
        $db,
        $placeId
    );

if (!$place) {
    header('Location: /places.php');
    exit;
}

$amenities =
    admin_place_row(
        $db,
        'place_amenities',
        $placeId
    );

$connectivity =
    admin_place_row(
        $db,
        'place_connectivity',
        $placeId
    );

$details =
    admin_place_row(
        $db,
        'place_details',
        $placeId
    );

$sensoryDetails =
    admin_place_row(
        $db,
        'place_sensory_details',
        $placeId
    );

$daytimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'daytime'
    );

$nighttimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'nighttime'
    );

$rules =
    admin_place_row(
        $db,
        'place_rules',
        $placeId
    );

$experience =
    admin_place_row(
        $db,
        'place_experience',
        $placeId
    );

$images =
    admin_place_images(
        $db,
        $placeId
    );

$verifications =
    admin_place_verifications(
        $db,
        $placeId
    );

$statusHistory =
    admin_place_status_history(
        $db,
        $placeId
    );

$provenance =
    admin_place_provenance(
        $db,
        $placeId
    );

$contributions =
    admin_place_contributions(
        $db,
        $placeId
    );

$updateHistory =
    admin_place_update_history(
        $db,
        $placeId
    );

$reportHistory =
    admin_place_reports_history(
        $db,
        $placeId
    );

$placeNotes =
    admin_place_notes(
        $db,
        $placeId
    );

$placeAuditHistory =
    admin_place_audit_history(
        $db,
        $placeId
    );

$llamaScouted =
    admin_place_llama_scouted_state(
        $db,
        $placeId
    );

$operationalCounts =
    admin_place_operational_counts(
        $db,
        $placeId
    );

$remainingPhotos =
    max(
        0,
        30 - count($images)
    );

$reportData =
    admin_place_shared_report_data(
        $db,
        $placeId
    );

$adminPlaceCampsites =
    admin_place_campsite_form_catalog(
        $db,
        $placeId
    );

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    (string) $place['name'];

$adminPageEyebrow =
    'Place Administration';

$adminActiveNav =
    'places';

$adminNeedsPhotoUploader =
    true;

require __DIR__
    . '/_header.php';

$e =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$placeReportValues =
    (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && ($action ?? '') === 'save-report'
        && $error !== ''
    )
        ? $_POST
        : llama_place_report_form_input_from_data(
            $reportData
        );

$placeReportMode =
    'moderator';

$placeReportExistingPhotos =
    $images;

$placeReportShowLocate =
    true;

$placeReportShowNameSuggestion =
    true;

$placeReportPhotoEndpoint =
    '/photo-upload.php';

$placeReportPhotoCsrf =
    llama_photo_csrf_token();

$placeReportPhotoMax =
    max(
        1,
        min(
            10,
            $remainingPhotos > 0
                ? $remainingPhotos
                : 1
        )
    );

$placeReportPhotoTitle =
    'Place photos';

$placeReportPhotoHelp =
    $remainingPhotos > 0
        ? 'Add up to '
            . min(10, $remainingPhotos)
            . ' new photos in this batch.'
        : 'This Place already has the maximum of 30 photos.';
?>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= $e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= $e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-place-shared-page">

    <section class="admin-place-summary">
        <div class="admin-place-summary-main">
            <div class="admin-place-summary-heading">
                <p>Place #<?= (int) $place['id'] ?></p>

                <h2>
                    <?= $e($place['name']) ?>
                </h2>

                <span class="admin-status-pill">
                    <?= $e(
                        ucfirst(
                            (string) $place['status']
                        )
                    ) ?>
                </span>
            </div>

            <span class="admin-place-summary-location">
                <?= $e(
                    implode(
                        ', ',
                        array_filter(
                            [
                                $place['city']
                                ?? null,
                                $place['state']
                                ?? null,
                            ]
                        )
                    )
                ) ?>
            </span>

            <div class="admin-place-summary-scout-status">
                <span>Scout status</span>

                <strong class="<?= !empty($llamaScouted['ever_scouted']) ? 'is-good' : '' ?>">
                    <i aria-hidden="true">
                        <?= llama_icon(
                            !empty($llamaScouted['ever_scouted'])
                                ? 'binoculars'
                                : 'circle-minus'
                        ) ?>
                    </i>

                    <?= !empty($llamaScouted['ever_scouted'])
                        ? 'Llama Scouted'
                        : 'Not yet Llama Scouted' ?>
                </strong>
            </div>
        </div>

        <div class="admin-place-summary-actions">
            <?php if (
                in_array(
                    (string) $place['status'],
                    ['active', 'featured'],
                    true
                )
            ): ?>
                <a
                    class="admin-button is-muted"
                    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                        (string) $place['slug']
                    ) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    View public Place
                </a>
            <?php endif; ?>
        </div>
    </section>


    <section
        class="admin-place-operations-strip"
        aria-label="Place operational summary"
    >
        <div>
            <span>Contributions</span>
            <strong><?= number_format((int) $operationalCounts['contributions']) ?></strong>
        </div>

        <div>
            <span>Pending updates</span>
            <strong><?= number_format((int) $operationalCounts['pending_updates']) ?></strong>
        </div>

        <div>
            <span>Open reports</span>
            <strong><?= number_format((int) $operationalCounts['open_reports']) ?></strong>
        </div>

        <div>
            <span>Photos</span>
            <strong><?= number_format(count($images)) ?></strong>
        </div>

        <div>
            <span>Verifications</span>
            <strong><?= number_format((int) $operationalCounts['verifications']) ?></strong>
        </div>
    </section>


    <nav class="admin-place-section-nav">
        <a href="#place-report">Place Report</a>
        <a href="#photos">Photos</a>
        <a href="#notes">Notes</a>
        <a href="#verification">Verification</a>
        <a href="#status">Status</a>
        <a href="#history">History</a>
    </nav>


    <form
        method="post"
        class="admin-place-report-form place-report-form"
        id="place-report"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(
                moderation_csrf_token()
            ) ?>"
        >

        <input
            type="hidden"
            name="place_id"
            value="<?= $placeId ?>"
        >

        <input
            type="hidden"
            name="place_admin_action"
            value="save-report"
        >

        <input
            type="hidden"
            name="photo_stage_token"
            value="<?= $e(
                (string) (
                    $_POST['photo_stage_token']
                    ?? ''
                )
            ) ?>"
        >

        <input
            type="hidden"
            name="photos_json"
            value="<?= $e(
                (string) (
                    $_POST['photos_json']
                    ?? '[]'
                )
            ) ?>"
        >

        <section class="admin-place-admin-meta">
            <header>
                <p>Admin metadata</p>
                <h2>Publishing + URL</h2>
            </header>

            <div class="admin-place-meta-grid">
                <label>
                    <span>URL slug</span>

                    <input
                        type="text"
                        name="admin_slug"
                        value="<?= $e(
                            $place['slug']
                            ?? ''
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>Record source</span>

                    <select name="admin_source_type">
                        <?php foreach (
                            [
                                'llama-scouted' => 'Llama Scouted',
                                'community-scouted' => 'Community Scouted',
                                'external' => 'External source',
                                'legacy' => 'Legacy',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= $e($value) ?>"
                                <?= (string) ($place['source_type'] ?? '') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

            </div>
        </section>


        <section class="admin-place-report-shell">
            <header class="admin-place-report-header">
                <p>Shared Place Report</p>
                <h2>Edit Place Report</h2>

                <span class="admin-place-muted">
                    This is the same question set and control system used by Add Place and moderation.
                </span>
            </header>

            <script
                type="application/json"
                data-admin-place-campsites
            ><?= json_encode(
                [
                    'sites' => $adminPlaceCampsites,
                    'site_field_keys' => admin_place_campsite_field_keys(),
                    'unknown_token' => llama_place_report_unknown_token(),
                    'unanswered_token' => llama_place_report_unanswered_token(),
                ],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) ?></script>

            <?php
            require dirname(__DIR__)
                . '/partials/place-report/form.php';
            ?>
        </section>


        <div class="admin-place-report-savebar">
            <button
                class="admin-button"
                type="submit"
                formnovalidate
                data-place-report-admin-save
            >
                <i aria-hidden="true">
                    <?= llama_icon('device-floppy') ?>
                </i>
                Save Place Report
            </button>
        </div>
    </form>


    <section
        class="admin-place-admin-section"
        id="photos"
    >
        <header>
            <p>Media</p>
            <h2>Photo management</h2>
        </header>

        <?php if ($images): ?>
            <div class="admin-place-photo-grid">
                <?php foreach ($images as $image): ?>
                    <?php
                    $imageUrl =
                        admin_place_photo_url(
                            $image
                        );
                    ?>

                    <article class="admin-place-photo-card">
                        <?php if ($imageUrl !== ''): ?>
                            <img
                                src="<?= $e($imageUrl) ?>"
                                alt="<?= $e(
                                    $image['alt_text']
                                    ?? ''
                                ) ?>"
                            >
                        <?php endif; ?>

                        <div class="admin-place-photo-card-body">
                            <?php if (
                                (int) (
                                    $image['is_featured']
                                    ?? 0
                                ) === 1
                            ): ?>
                                <strong>
                                    Featured image
                                </strong>
                            <?php endif; ?>

                            <form method="post">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= $e(
                                        moderation_csrf_token()
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="place_id"
                                    value="<?= $placeId ?>"
                                >

                                <input
                                    type="hidden"
                                    name="place_admin_action"
                                    value="save-image-meta"
                                >

                                <input
                                    type="hidden"
                                    name="image_id"
                                    value="<?= (int) $image['id'] ?>"
                                >

                                <label>
                                    <span>Alt text / caption</span>

                                    <input
                                        type="text"
                                        name="alt_text"
                                        value="<?= $e(
                                            $image['alt_text']
                                            ?? ''
                                        ) ?>"
                                    >
                                </label>

                                <label>
                                    <span>Sort order</span>

                                    <input
                                        type="number"
                                        name="sort_order"
                                        min="0"
                                        max="999"
                                        value="<?= (int) (
                                            $image['sort_order']
                                            ?? 0
                                        ) ?>"
                                    >
                                </label>

                                <button
                                    class="admin-button is-muted"
                                    type="submit"
                                >
                                    Save photo details
                                </button>
                            </form>

                            <div class="admin-place-photo-actions">
                                <?php if (
                                    (int) (
                                        $image['is_featured']
                                        ?? 0
                                    ) !== 1
                                ): ?>
                                    <form method="post">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $e(
                                                moderation_csrf_token()
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="place_id"
                                            value="<?= $placeId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="place_admin_action"
                                            value="featured-image"
                                        >

                                        <input
                                            type="hidden"
                                            name="image_id"
                                            value="<?= (int) $image['id'] ?>"
                                        >

                                        <button
                                            class="admin-button is-muted"
                                            type="submit"
                                        >
                                            Make featured
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $e(
                                            moderation_csrf_token()
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="place_id"
                                        value="<?= $placeId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="place_admin_action"
                                        value="delete-image"
                                    >

                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= (int) $image['id'] ?>"
                                    >

                                    <button
                                        class="admin-button is-danger"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No photos are attached to this Place.
            </div>
        <?php endif; ?>

        <p class="admin-place-muted">
            New photos can be added from the Photos section inside the shared Place Report above.
        </p>
    </section>


    <section
        class="admin-place-admin-section"
        id="notes"
    >
        <header>
            <p>Internal</p>
            <h2>Place Notes</h2>
        </header>

        <?php if ($placeNotes): ?>
            <div class="admin-place-note-list">
                <?php foreach ($placeNotes as $note): ?>
                    <article class="admin-place-note-card">
                        <header>
                            <strong>
                                <?= $e(
                                    $note['author_name']
                                    ?? 'System'
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $note['created_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <div>
                            <?= nl2br(
                                $e(
                                    $note['note']
                                    ?? ''
                                )
                            ) ?>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $e(
                                    moderation_csrf_token()
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="place_id"
                                value="<?= $placeId ?>"
                            >

                            <input
                                type="hidden"
                                name="place_admin_action"
                                value="delete-note"
                            >

                            <input
                                type="hidden"
                                name="note_id"
                                value="<?= (int) $note['id'] ?>"
                            >

                            <button
                                class="admin-button is-danger"
                                type="submit"
                            >
                                Delete note
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            class="admin-place-note-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="add-note"
            >

            <label>
                <span>Add internal note</span>

                <textarea
                    name="note"
                    rows="4"
                    maxlength="2000"
                ></textarea>
            </label>

            <button
                class="admin-button"
                type="submit"
            >
                Add note
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="verification"
    >
        <header>
            <p>Verification</p>
            <h2>Verification History</h2>
        </header>

        <div class="admin-place-verification-intro">
            <p>
                Verifications document how current Place information was confirmed.
                A Llama Scout field visit establishes the permanent Llama Scouted provenance.
            </p>
        </div>

        <?php if ($verifications): ?>
            <div class="admin-place-verification-list">
                <?php foreach ($verifications as $verification): ?>
                    <?php
                    $verificationType =
                        (string) (
                            $verification['verification_type']
                            ?? ''
                        );

                    $verificationLabel =
                        llama_place_verification_type_label(
                            $verificationType
                        );
                    ?>

                    <article class="admin-place-verification-card">
                        <div class="admin-place-verification-card-main">
                            <header>
                                <strong>
                                    <?= $e($verificationLabel) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $verification['verified_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <div class="admin-place-verification-card-meta">
                                <span>
                                    By
                                    <?= $e(
                                        $verification['verifier_name']
                                        ?? 'System'
                                    ) ?>
                                </span>

                                <?php if (!empty($verification['visited_at'])): ?>
                                    <span>
                                        Visited
                                        <?= $e(
                                            $verification['visited_at']
                                        ) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($verification['source'])): ?>
                                    <span>
                                        Source:
                                        <?= $e(
                                            $verification['source']
                                        ) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($verification['public_data_verified'])): ?>
                                    <span>
                                        Public data verified
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($verification['notes'])): ?>
                                <div class="admin-place-verification-card-notes">
                                    <?= nl2br(
                                        $e(
                                            $verification['notes']
                                        )
                                    ) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (
                                !isset(
                                    llama_place_verification_types()[
                                        $verificationType
                                    ]
                                )
                            ): ?>
                                <span class="admin-place-muted">
                                    Legacy verification type:
                                    <?= $e($verificationType) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $e(
                                    moderation_csrf_token()
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="place_id"
                                value="<?= $placeId ?>"
                            >

                            <input
                                type="hidden"
                                name="place_admin_action"
                                value="delete-verification"
                            >

                            <input
                                type="hidden"
                                name="verification_id"
                                value="<?= (int) $verification['id'] ?>"
                            >

                            <button
                                class="admin-button admin-place-verification-delete"
                                type="submit"
                                data-delete-verification
                            >
                                <i aria-hidden="true">
                                    <?= llama_icon('trash') ?>
                                </i>
                                Delete
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No verification history yet.
            </div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="add-verification"
            >

            <div class="admin-place-verification-grid">
                <label>
                    <span>Verification type</span>

                    <select
                        name="verification_type"
                        required
                    >
                        <option value="">
                            Select...
                        </option>

                        <?php foreach (
                            llama_place_verification_types()
                            as $verificationType => $verificationMeta
                        ): ?>
                            <option
                                value="<?= $e($verificationType) ?>"
                            >
                                <?= $e(
                                    $verificationMeta['label']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="admin-place-verification-help">
                        Field visit means a Llama Scout personally visited the Place.
                    </small>
                </label>

                <label>
                    <span>Date visited</span>

                    <input
                        type="date"
                        name="visited_at"
                    >

                    <small class="admin-place-verification-help">
                        Required for a Llama Scout field visit.
                    </small>
                </label>

                <label>
                    <span>Source</span>

                    <input
                        type="text"
                        name="source"
                        placeholder="USFS, BLM, county website..."
                    >

                    <small class="admin-place-verification-help">
                        Required for Official source verified. Field visits automatically use Llama Scouted.
                    </small>
                </label>

                <label>
                    <span>Public data verified</span>

                    <span>
                        <input
                            type="checkbox"
                            name="public_data_verified"
                            value="1"
                        >
                        Yes
                    </span>
                </label>

                <label class="admin-place-verification-wide">
                    <span>Notes</span>

                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="What was checked, confirmed, or observed?"
                    ></textarea>
                </label>
            </div>

            <button
                class="admin-button"
                type="submit"
            >
                <i aria-hidden="true">
                    <?= llama_icon('circle-check') ?>
                </i>
                Add verification
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="status"
    >
        <header>
            <p>Publishing</p>
            <h2>Status</h2>
        </header>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="change-status"
            >

            <div class="admin-place-status-grid">
                <label>
                    <span>Status</span>

                    <select name="status">
                        <?php foreach (
                            [
                                'draft',
                                'active',
                                'featured',
                                'unlisted',
                                'removed',
                                'archived',
                            ]
                            as $status
                        ): ?>
                            <option
                                value="<?= $e($status) ?>"
                                <?= (string) $place['status'] === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e(
                                    ucwords(
                                        str_replace(
                                            '-',
                                            ' ',
                                            $status
                                        )
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-place-status-wide">
                    <span>
                        Reason for status change
                    </span>

                    <textarea
                        name="status_reason"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <button
                class="admin-button"
                type="submit"
            >
                Update status
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="history"
    >
        <header>
            <p>Record</p>
            <h2>History + Provenance</h2>
        </header>

        <?php if ($provenance): ?>
            <div class="admin-place-history-group">
                <h3>Origin</h3>

                <article class="admin-place-history-card">
                    <strong>
                        <?= $e(
                            $provenance['origin_type']
                            ?? 'Unknown'
                        ) ?>
                    </strong>

                    <?php if (!empty($provenance['contributor_name'])): ?>
                        <span>
                            Original contributor:
                            <?= $e(
                                $provenance['contributor_name']
                            ) ?>
                        </span>
                    <?php endif; ?>

                    <span class="admin-place-muted">
                        Established
                        <?= $e(
                            $provenance['established_at']
                            ?? ''
                        ) ?>
                    </span>
                </article>
            </div>
        <?php endif; ?>


        <div class="admin-place-history-group">
            <h3>Status timeline</h3>

            <?php if ($statusHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($statusHistory as $entry): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ($entry['old_status'] ?? 'New')
                                        . ' -> '
                                        . ($entry['new_status'] ?? '')
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $entry['changed_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                By
                                <?= $e(
                                    $entry['changed_by_name']
                                    ?? 'System'
                                ) ?>
                            </span>

                            <?php if (!empty($entry['reason'])): ?>
                                <div>
                                    <?= nl2br(
                                        $e(
                                            $entry['reason']
                                        )
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No status history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Contributions
                (<?= count($contributions) ?>)
            </h3>

            <?php if ($contributions): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($contributions as $contribution): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        $contribution['contribution_type']
                                        ?? 'Contribution'
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $contribution['approved_at']
                                        ?? $contribution['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $contribution['contributor_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No contribution history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Update submissions
                (<?= count($updateHistory) ?>)
            </h3>

            <?php if ($updateHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($updateHistory as $update): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ucfirst(
                                            (string) (
                                                $update['status']
                                                ?? 'pending'
                                            )
                                        )
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $update['submitted_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $update['contributor_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No Place Update history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Reports
                (<?= count($reportHistory) ?>)
            </h3>

            <?php if ($reportHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($reportHistory as $report): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ucfirst(
                                            (string) (
                                                $report['status']
                                                ?? 'open'
                                            )
                                        )
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $report['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $report['reporter_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No reports.
                </div>
            <?php endif; ?>
        </div>


        <?php if ($placeAuditHistory): ?>
            <div class="admin-place-history-group">
                <h3>
                    Admin audit
                    (<?= count($placeAuditHistory) ?>)
                </h3>

                <div class="admin-place-history-list">
                    <?php foreach ($placeAuditHistory as $audit): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        $audit['action']
                                        ?? 'Admin action'
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $audit['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $audit['actor_name']
                                    ?? 'System'
                                ) ?>
                            </span>

                            <?php if (!empty($audit['summary'])): ?>
                                <div>
                                    <?= $e(
                                        $audit['summary']
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

</div>


<script src="https://llamascout.com/js/add-place-location.js"></script>
<script src="https://llamascout.com/js/add-place-name.js"></script>
<script src="https://llamascout.com/js/place-report-form.js"></script>

<script
    src="https://llamascout.com/js/admin/place-report-save.js?v=<?= rawurlencode(
        (string) (
            @filemtime(
                dirname(__DIR__)
                . '/js/admin/place-report-save.js'
            )
            ?: time()
        )
    ) ?>"
></script>

<script src="https://llamascout.com/js/admin/place-verifications.js"></script>

<?php
require __DIR__
    . '/_footer.php';
?>

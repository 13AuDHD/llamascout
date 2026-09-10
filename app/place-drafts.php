<?php

declare(strict_types=1);


function llama_place_draft_csrf_token(): string
{
    if (empty($_SESSION['place_draft_csrf'])) {
        $_SESSION['place_draft_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['place_draft_csrf'];
}

function llama_place_draft_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['place_draft_csrf'] ?? '');

    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function llama_place_draft_clean_form_data(array $input): array
{
    $blocked = [
        'csrf_token',
        'place_draft_csrf',
        'save_for_later',
        'submit_for_review',
        'remove_saved_place',
    ];

    $clean = [];

    foreach ($input as $key => $value) {
        $key = (string) $key;

        if (in_array($key, $blocked, true)) {
            continue;
        }

        if (is_array($value)) {
            $clean[$key] = array_values(
                array_map(
                    static fn (mixed $item): string =>
                        mb_substr((string) $item, 0, 5000),
                    $value
                )
            );
            continue;
        }

        $clean[$key] = mb_substr((string) $value, 0, 10000);
    }

    unset(
        $clean['photo_stage_token'],
        $clean['photos_json'],
        $clean['draft_id']
    );

    return $clean;
}

function llama_place_draft_photo_relative_dir(int $userId, int $draftId): string
{
    return '/uploads/drafts/place/user-' . $userId . '/draft-' . $draftId;
}

function llama_place_draft_photo_absolute_dir(int $userId, int $draftId): string
{
    return dirname(__DIR__) . llama_place_draft_photo_relative_dir($userId, $draftId);
}

function llama_place_draft_decode_json_array(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function llama_place_draft_snapshot_photos(
    int $userId,
    int $draftId,
    string $stageToken,
    array $submittedPhotos
): array {
    $destinationRelative = llama_place_draft_photo_relative_dir($userId, $draftId);
    $destinationAbsolute = llama_place_draft_photo_absolute_dir($userId, $draftId);

    if (!$submittedPhotos) {
        llama_photo_remove_tree($destinationAbsolute);

        if ($stageToken !== '') {
            try {
                llama_photo_stage_abandon('add-place', $userId, $stageToken);
            } catch (Throwable) {
            }
        }

        return [];
    }

    if ($stageToken === '') {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }

    $stageToken = llama_photo_stage_token($stageToken);
    $manifest = llama_photo_read_manifest('add-place', $userId, $stageToken);
    $manifestByPath = [];

    foreach ($manifest as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path = trim((string) ($photo['path'] ?? ''));
        if ($path !== '') {
            $manifestByPath[$path] = $photo;
        }
    }

    $expectedPrefix = llama_photo_stage_relative_dir('add-place', $userId, $stageToken) . '/';
    $tmpAbsolute = $destinationAbsolute . '-tmp-' . bin2hex(random_bytes(5));

    if (!mkdir($tmpAbsolute, 0755, true) && !is_dir($tmpAbsolute)) {
        throw new RuntimeException('The draft photo directory could not be created.');
    }

    $saved = [];

    try {
        foreach ($submittedPhotos as $submitted) {
            if (!is_array($submitted)) {
                continue;
            }

            $sourceRelative = trim((string) ($submitted['path'] ?? ''));

            if (
                $sourceRelative === ''
                || !str_starts_with($sourceRelative, $expectedPrefix)
                || !isset($manifestByPath[$sourceRelative])
            ) {
                continue;
            }

            $sourceAbsolute = dirname(__DIR__) . $sourceRelative;
            if (!is_file($sourceAbsolute)) {
                throw new RuntimeException('A staged photo is missing. Please upload it again.');
            }

            $manifestPhoto = $manifestByPath[$sourceRelative];
            $filename = basename((string) ($manifestPhoto['filename'] ?? $sourceRelative));

            if ($filename === '') {
                throw new RuntimeException('A draft photo has an invalid filename.');
            }

            if (!copy($sourceAbsolute, $tmpAbsolute . '/' . $filename)) {
                throw new RuntimeException('A photo could not be saved with this draft.');
            }

            $savedRelative = $destinationRelative . '/' . $filename;

            $saved[] = [
                'path' => $savedRelative,
                'url' => llama_photo_public_url($savedRelative),
                'filename' => $filename,
                'original_name' => (string) ($manifestPhoto['original_name'] ?? ''),
                'mime_type' => (string) ($manifestPhoto['mime_type'] ?? 'image/jpeg'),
                'width' => (int) ($manifestPhoto['width'] ?? 0),
                'height' => (int) ($manifestPhoto['height'] ?? 0),
                'size' => (int) ($manifestPhoto['size'] ?? 0),
                'alt' => mb_substr(
                    trim((string) ($submitted['alt'] ?? $manifestPhoto['alt'] ?? '')),
                    0,
                    300
                ),
            ];
        }

        if (!$saved) {
            throw new RuntimeException('No uploaded photos were available to save with this draft.');
        }

        $backupAbsolute = $destinationAbsolute . '-backup-' . bin2hex(random_bytes(5));
        $hadExisting = is_dir($destinationAbsolute);

        if ($hadExisting && !rename($destinationAbsolute, $backupAbsolute)) {
            throw new RuntimeException('The previous draft photos could not be prepared for replacement.');
        }

        try {
            if (!rename($tmpAbsolute, $destinationAbsolute)) {
                throw new RuntimeException('The draft photos could not be finalized.');
            }

            if ($hadExisting) {
                llama_photo_remove_tree($backupAbsolute);
            }
        } catch (Throwable $exception) {
            if ($hadExisting && is_dir($backupAbsolute) && !is_dir($destinationAbsolute)) {
                @rename($backupAbsolute, $destinationAbsolute);
            }
            throw $exception;
        }

        llama_photo_stage_abandon('add-place', $userId, $stageToken);

        return $saved;
    } catch (Throwable $exception) {
        llama_photo_remove_tree($tmpAbsolute);
        throw $exception;
    }
}

function llama_place_draft_restore_photos(
    int $userId,
    int $draftId,
    array $savedPhotos
): array {
    if (!$savedPhotos) {
        return ['token' => '', 'photos' => []];
    }

    $token = llama_photo_stage_token();
    $stageRelative = llama_photo_stage_relative_dir('add-place', $userId, $token);
    $stageAbsolute = llama_photo_stage_absolute_dir('add-place', $userId, $token);

    if (!mkdir($stageAbsolute, 0755, true) && !is_dir($stageAbsolute)) {
        throw new RuntimeException('The draft photos could not be prepared for editing.');
    }

    $restored = [];

    try {
        $allowedPrefix = llama_place_draft_photo_relative_dir($userId, $draftId) . '/';

        foreach ($savedPhotos as $photo) {
            if (!is_array($photo)) {
                continue;
            }

            $sourceRelative = trim((string) ($photo['path'] ?? ''));

            if ($sourceRelative === '' || !str_starts_with($sourceRelative, $allowedPrefix)) {
                continue;
            }

            $sourceAbsolute = dirname(__DIR__) . $sourceRelative;
            if (!is_file($sourceAbsolute)) {
                continue;
            }

            $filename = basename((string) ($photo['filename'] ?? $sourceRelative));
            if (!copy($sourceAbsolute, $stageAbsolute . '/' . $filename)) {
                throw new RuntimeException('A saved draft photo could not be restored.');
            }

            $stagePath = $stageRelative . '/' . $filename;
            $restored[] = [
                'path' => $stagePath,
                'url' => llama_photo_public_url($stagePath),
                'filename' => $filename,
                'original_name' => (string) ($photo['original_name'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? 'image/jpeg'),
                'width' => (int) ($photo['width'] ?? 0),
                'height' => (int) ($photo['height'] ?? 0),
                'size' => (int) ($photo['size'] ?? 0),
                'alt' => mb_substr(trim((string) ($photo['alt'] ?? '')), 0, 300),
            ];
        }

        if (!$restored) {
            llama_photo_remove_tree($stageAbsolute);
            return ['token' => '', 'photos' => []];
        }

        llama_photo_write_manifest('add-place', $userId, $token, $restored);

        return ['token' => $token, 'photos' => $restored];
    } catch (Throwable $exception) {
        llama_photo_remove_tree($stageAbsolute);
        throw $exception;
    }
}

function llama_place_draft_for_user(PDO $db, int $userId, int $draftId): ?array
{
    if ($userId < 1 || $draftId < 1) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT * FROM place_drafts WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$draftId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $row['form_data'] = llama_place_draft_decode_json_array($row['form_data'] ?? '[]');
    $row['photos'] = llama_place_draft_decode_json_array($row['photos_json'] ?? '[]');

    return $row;
}

function llama_place_drafts_for_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT * FROM place_drafts WHERE user_id = ? ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['form_data'] = llama_place_draft_decode_json_array($row['form_data'] ?? '[]');
        $row['photos'] = llama_place_draft_decode_json_array($row['photos_json'] ?? '[]');
    }
    unset($row);

    return $rows;
}

function llama_place_draft_count(PDO $db, int $userId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM place_drafts WHERE user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function llama_place_draft_save(PDO $db, int $userId, int $draftId, array $input): int
{
    if ($userId < 1) {
        throw new InvalidArgumentException('A signed-in account is required.');
    }

    $formData = llama_place_draft_clean_form_data($input);
    $name = trim((string) ($formData['name'] ?? ''));
    $name = $name !== '' ? mb_substr($name, 0, 200) : 'Untitled Place';
    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if ($draftId > 0) {
        if (!llama_place_draft_for_user($db, $userId, $draftId)) {
            throw new RuntimeException('That saved Place could not be found.');
        }

        $stmt = $db->prepare(
            'UPDATE place_drafts
             SET draft_name = ?, form_data = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $formJson, $draftId, $userId]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO place_drafts
                (user_id, draft_name, form_data, photos_json, created_at, updated_at)
             VALUES (?, ?, ?, "[]", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([$userId, $name, $formJson]);
        $draftId = (int) $db->lastInsertId();
    }

    $stageToken = trim((string) ($input['photo_stage_token'] ?? ''));
    $submittedPhotos = llama_photo_decode_form_photos($input['photos_json'] ?? '[]');
    $savedPhotos = llama_place_draft_snapshot_photos(
        $userId,
        $draftId,
        $stageToken,
        $submittedPhotos
    );

    $photosJson = json_encode(
        $savedPhotos,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $stmt = $db->prepare(
        'UPDATE place_drafts
         SET photos_json = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$photosJson, $draftId, $userId]);

    return $draftId;
}

function llama_place_draft_delete(PDO $db, int $userId, int $draftId): bool
{
    if (!llama_place_draft_for_user($db, $userId, $draftId)) {
        return false;
    }

    $stmt = $db->prepare('DELETE FROM place_drafts WHERE id = ? AND user_id = ?');
    $stmt->execute([$draftId, $userId]);

    llama_photo_remove_tree(llama_place_draft_photo_absolute_dir($userId, $draftId));

    return true;
}

function llama_place_draft_has_answer(array $data, string $key): bool
{
    if (!array_key_exists($key, $data)) {
        return false;
    }

    $value = $data[$key];

    if (is_array($value)) {
        return count($value) > 0;
    }

    return trim((string) $value) !== '';
}

function llama_place_draft_scoring_groups(): array
{
    return [
        'Site + Vehicle' => [
            'vehicle_capacity','max_vehicle_length_feet','parking_surface','ground_condition',
            'tent_camping_suitable','rv_suitable','trailer_suitable','leveling_required',
            'turnaround_space','pull_through','back_in','levelness','site_open_sky','tree_cover','site_shade',
        ],
        'Road Access' => [
            'road_surface','road_width','sedan_accessible','high_clearance_recommended',
            'four_wheel_drive_recommended','water_crossings','downed_tree_risk','seasonal_closure',
            'site_access_difficulty','road_overall_difficulty','road_stress','rocks','washboards',
            'potholes','mud_risk','steep_grades','drop_off_exposure',
        ],
        'Amenities' => [
            'amenity_none','amenity_toilets','amenity_potable_water','amenity_trash','amenity_fire_ring',
            'amenity_picnic_table','amenity_bear_box','amenity_showers','amenity_electricity',
            'amenity_dump_station','amenity_food_storage_required',
        ],
        'Connectivity' => [
            'connectivity_overall','connectivity_t_mobile','connectivity_verizon','connectivity_att',
            'connectivity_other_cell','connectivity_starlink','connectivity_starlink_tested','connectivity_starlink_note',
        ],
        'Sensory' => [
            'daytime_noise','daytime_traffic','daytime_crowds','daytime_privacy','daytime_light_pollution',
            'daytime_sensory_comfort','daytime_social_interaction','nighttime_noise','nighttime_traffic',
            'nighttime_crowds','nighttime_privacy','nighttime_light_pollution','nighttime_sensory_comfort',
            'nighttime_social_interaction','sensory_dust_from_traffic','sensory_generator_noise',
            'sensory_aircraft_noise','sensory_road_noise','sensory_human_activity','sensory_wildlife_noise',
            'sensory_wind_noise','sensory_smoke_risk','sensory_strong_odors','sensory_visual_exposure','sensory_predictability',
        ],
        'Environment' => [
            'environment_forest','environment_mountains','environment_water_nearby','environment_water_view',
            'environment_mountain_view','environment_forest_view','environment_wildlife','environment_bugs',
            'environment_wind_exposure','environment_sun_exposure','environment_shade','environment_open_sky',
        ],
        'Accessibility' => [
            'wheelchair_friendly','mobility_device_friendly','flat_walking_surface','step_free_access',
            'accessible_toilet','accessible_picnic_table','walking_distance_from_vehicle',
        ],
        'Safety + Warnings' => [
            'felt_safe_daytime','felt_safe_nighttime','flash_flood_risk','wildfire_risk','fall_hazard',
            'cliff_exposure','rockfall_risk','wildlife_risk','traffic_hazard','emergency_access',
            'warning_exposed_to_road','warning_zero_privacy','warning_passing_vehicle_dust',
            'warning_possible_downed_trees','warning_no_tent_camping','warning_limited_vehicle_length',
            'warning_leveling_may_be_required','warning_no_amenities','warning_motorized_recreation_traffic',
            'warning_blind_turn_traffic_nearby',
        ],
        'Seasons + Rules + Services' => [
            'best_months','winter_access','snow_risk','mud_season_risk','monsoon_risk','seasonal_access_note',
            'overnight_camping_allowed','dispersed_camping_allowed','permit_required','campfire_allowed',
            'existing_sites_encouraged','pack_it_in_pack_it_out','residential_use_prohibited','stay_limit_days',
            'fee','current_fire_restrictions_url','nearest_town','nearest_fuel','nearest_grocery','nearest_water',
            'nearest_toilet','nearest_hospital',
        ],
        'Experience + Recommendations' => [
            'experience_sunrise_view','experience_sunset_view','experience_mountain_view','experience_forest_view',
            'experience_night_sky','experience_stargazing','experience_quiet_evening','experience_overnight_comfort',
            'experience_extended_stay_comfort','experience_sensory_retreat','experience_remote_work',
            'experience_overall_scenery','recommended_overnight_stop','recommended_quiet_evening',
            'recommended_extended_stay','recommended_sensory_retreat','recommended_stargazing',
            'recommended_remote_work','recommended_solo_travel','recommended_families','recommended_large_groups',
            'not_recommended_for',
        ],
    ];
}

function llama_place_draft_progress(array $data, int $photoCount): array
{
    $groups = llama_place_draft_scoring_groups();
    $categoryRows = [];
    $estimatedPoints = 0;
    $answeredTotal = 0;
    $fieldTotal = 0;

    foreach ($groups as $label => $fields) {
        $answered = 0;
        foreach ($fields as $field) {
            if (llama_place_draft_has_answer($data, $field)) {
                $answered++;
            }
        }

        $total = count($fields);
        $answeredTotal += $answered;
        $fieldTotal += $total;

        $points = in_array($label, ['Amenities', 'Connectivity'], true)
            ? ($answered > 0 ? 10 : 0)
            : ($total > 0 ? (int) round(10 * ($answered / $total)) : 0);

        $points = max(0, min(10, $points));
        $estimatedPoints += $points;

        $categoryRows[] = [
            'label' => $label,
            'answered' => $answered,
            'total' => $total,
            'points' => $points,
            'started' => $answered > 0,
        ];
    }

    $otherFields = [
        'name','type','visited_at','description',
        'latitude','longitude','elevation_feet','road','city','county','state','region','land_manager','land_type',
        'access_summary','sensory_summary','contributor_notes',
    ];

    foreach ($otherFields as $field) {
        $fieldTotal++;
        if (llama_place_draft_has_answer($data, $field)) {
            $answeredTotal++;
        }
    }

    $fieldTotal++;
    if ($photoCount > 0) {
        $answeredTotal++;
    }

    $missingMinimum = [];

    if (!llama_place_draft_has_answer($data, 'name')) {
        $missingMinimum[] = 'Basic information';
    }

    if (
        !llama_place_draft_has_answer($data, 'latitude')
        || !llama_place_draft_has_answer($data, 'longitude')
    ) {
        $missingMinimum[] = 'Location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] = '1 photo';
    }

    return [
        'completion_percent' => $fieldTotal > 0
            ? (int) round(100 * ($answeredTotal / $fieldTotal))
            : 0,
        'estimated_points' => max(0, min(100, $estimatedPoints)),
        'categories_started' => count(
            array_filter(
                $categoryRows,
                static fn (array $row): bool => !empty($row['started'])
            )
        ),
        'categories' => $categoryRows,
        'minimum_ready' => !$missingMinimum,
        'missing_minimum' => $missingMinimum,
    ];
}

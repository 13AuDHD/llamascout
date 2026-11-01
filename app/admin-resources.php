<?php

declare(strict_types=1);

require_once __DIR__ . '/resources.php';
require_once __DIR__ . '/photo-upload.php';

function admin_resources_tables_ready(PDO $db): bool
{
    try {
        $db->query(
            'SELECT
                slug,
                marker_service_type,
                hours_mode,
                fixed_open_hour,
                fixed_close_hour
             FROM resources
             LIMIT 1'
        );
        $db->query('SELECT 1 FROM resource_services LIMIT 1');
        $db->query('SELECT 1 FROM resource_images LIMIT 1');
        $db->query('SELECT 1 FROM resource_weekly_hours LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function admin_resources_list(PDO $db): array
{
    return llama_resources($db, null, false);
}

function admin_resource_get(PDO $db, int $resourceId): array
{
    if ($resourceId < 1) {
        return [];
    }

    $rows = llama_resources(
        $db,
        $resourceId,
        false
    );

    return $rows[0] ?? [];
}

function admin_resource_datetime_value(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));

    if ($raw === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', $raw);

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat(
            '!' . $format,
            $normalized
        );

        if (
            $date instanceof DateTimeImmutable
            && $date->format($format) === $normalized
        ) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    throw new InvalidArgumentException(
        'Enter a valid date and time.'
    );
}

function admin_resource_clean_url(mixed $value, string $label): ?string
{
    $url = trim((string) ($value ?? ''));

    if ($url === '') {
        return null;
    }

    if (
        filter_var($url, FILTER_VALIDATE_URL) === false
        || !preg_match('/^https?:\/\//i', $url)
    ) {
        throw new InvalidArgumentException(
            $label . ' must be a valid http or https URL.'
        );
    }

    return mb_substr($url, 0, 2000);
}

function admin_resource_service_rows(array $input): array
{
    $available = llama_resource_service_types();
    $statuses = llama_resource_statuses();
    $fees = llama_resource_fee_statuses();

    $rawServices =
        is_array($input['services'] ?? null)
            ? $input['services']
            : [];

    $services = [];

    foreach ($available as $serviceType => $_label) {
        $row =
            is_array($rawServices[$serviceType] ?? null)
                ? $rawServices[$serviceType]
                : [];

        if (empty($row['enabled'])) {
            continue;
        }

        $status =
            trim(
                (string) (
                    $row['status']
                    ?? 'unknown'
                )
            );

        if (!array_key_exists($status, $statuses)) {
            throw new InvalidArgumentException(
                'Choose a valid status for '
                . llama_resource_service_type_label($serviceType)
                . '.'
            );
        }

        $feeStatus =
            trim(
                (string) (
                    $row['fee_status']
                    ?? 'unknown'
                )
            );

        if (!array_key_exists($feeStatus, $fees)) {
            throw new InvalidArgumentException(
                'Choose a valid fee status for '
                . llama_resource_service_type_label($serviceType)
                . '.'
            );
        }

        $services[$serviceType] = [
            'service_type' => $serviceType,
            'status' => $status,
            'fee_status' => $feeStatus,
            'fee_description' =>
                mb_substr(
                    trim(
                        (string) (
                            $row['fee_description']
                            ?? ''
                        )
                    ),
                    0,
                    255
                ),
            'access_notes' =>
                mb_substr(
                    trim(
                        (string) (
                            $row['access_notes']
                            ?? ''
                        )
                    ),
                    0,
                    4000
                ),
            'notes' =>
                mb_substr(
                    trim(
                        (string) (
                            $row['notes']
                            ?? ''
                        )
                    ),
                    0,
                    4000
                ),
        ];
    }

    if (!$services) {
        throw new InvalidArgumentException(
            'Select at least one Service for this Resource.'
        );
    }

    return $services;
}

function admin_resource_save(
    PDO $db,
    int $actorUserId,
    int $resourceId,
    array $input
): int {
    if (!admin_resources_tables_ready($db)) {
        throw new RuntimeException(
            'Resource tables are not installed yet.'
        );
    }

    $name =
        mb_substr(
            trim(
                (string) (
                    $input['name']
                    ?? ''
                )
            ),
            0,
            160
        );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Resource name is required.'
        );
    }

    $slug =
        llama_resource_slug(
            $name
        );

    if ($slug === '') {
        throw new InvalidArgumentException(
            'Resource name must produce a valid URL slug.'
        );
    }

    $duplicateName =
        $db->prepare(
            'SELECT id
             FROM resources
             WHERE name = ?
               AND id <> ?
             LIMIT 1'
        );

    $duplicateName->execute([
        $name,
        max(0, $resourceId),
    ]);

    if ($duplicateName->fetchColumn()) {
        throw new InvalidArgumentException(
            'A Resource with that name already exists. Include the store or station number so every Resource name stays unique.'
        );
    }

    $duplicateSlug =
        $db->prepare(
            'SELECT id
             FROM resources
             WHERE slug = ?
               AND id <> ?
             LIMIT 1'
        );

    $duplicateSlug->execute([
        $slug,
        max(0, $resourceId),
    ]);

    if ($duplicateSlug->fetchColumn()) {
        throw new InvalidArgumentException(
            'That Resource name produces a URL already used by another Resource. Make the name more specific.'
        );
    }

    $resourceType =
        trim(
            (string) (
                $input['resource_type']
                ?? ''
            )
        );

    if (
        !array_key_exists(
            $resourceType,
            llama_resource_types()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Resource type.'
        );
    }

    $coordinates =
        trim(
            (string) (
                $input['coordinates']
                ?? ''
            )
        );

    $coordinateParts =
        array_map(
            'trim',
            explode(
                ',',
                $coordinates
            )
        );

    if (
        count($coordinateParts) !== 2
        || !llama_resource_coordinates_valid(
            $coordinateParts[0] ?? null,
            $coordinateParts[1] ?? null
        )
    ) {
        throw new InvalidArgumentException(
            'Enter GPS coordinates as latitude, longitude.'
        );
    }

    $latitude =
        round(
            (float) $coordinateParts[0],
            7
        );

    $longitude =
        round(
            (float) $coordinateParts[1],
            7
        );

    $status =
        trim(
            (string) (
                $input['status']
                ?? 'unknown'
            )
        );

    if (
        !array_key_exists(
            $status,
            llama_resource_statuses()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Resource status.'
        );
    }

    $countryCode =
        strtoupper(
            trim(
                (string) (
                    $input['country_code']
                    ?? ''
                )
            )
        );

    if (
        $countryCode !== ''
        && preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1
    ) {
        throw new InvalidArgumentException(
            'Country code must be two letters, such as US.'
        );
    }

    $websiteUrl =
        admin_resource_clean_url(
            $input['website_url'] ?? '',
            'Website'
        );

    $sourceUrl =
        admin_resource_clean_url(
            $input['source_url'] ?? '',
            'Source URL'
        );

    $sourceUpdatedAt =
        admin_resource_datetime_value(
            $input['source_updated_at']
            ?? ''
        );

    $verifiedAt =
        admin_resource_datetime_value(
            $input['verified_at']
            ?? ''
        );

    $services =
        admin_resource_service_rows(
            $input
        );

    $markerServiceType =
        strtolower(
            trim(
                (string) (
                    $input['marker_service_type']
                    ?? ''
                )
            )
        );

    if (
        $markerServiceType !== ''
        && !array_key_exists(
            $markerServiceType,
            $services
        )
    ) {
        throw new InvalidArgumentException(
            'The map marker Service must be one of the Services enabled for this Resource.'
        );
    }

    $hoursMode =
        trim(
            (string) (
                $input['hours_mode']
                ?? ''
            )
        );

    if (
        !array_key_exists(
            $hoursMode,
            llama_resource_hours_modes()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid hours type.'
        );
    }

    $fixedOpenHour = null;
    $fixedCloseHour = null;
    $weeklyHours = [];

    $validateHour =
        static function (
            mixed $value,
            string $label
        ): int {
            if (
                !is_numeric($value)
                || (int) $value < 0
                || (int) $value > 23
                || (string) (int) $value !== trim((string) $value)
            ) {
                throw new InvalidArgumentException(
                    'Choose a valid time for ' . $label . '.'
                );
            }

            return (int) $value;
        };

    if ($hoursMode === 'fixed_hours') {
        $fixedOpenHour =
            $validateHour(
                $input['fixed_open_hour']
                ?? null,
                'opening time'
            );

        $fixedCloseHour =
            $validateHour(
                $input['fixed_close_hour']
                ?? null,
                'closing time'
            );
    }

    if ($hoursMode === 'varied_hours') {
        $submittedWeekly =
            is_array(
                $input['weekly_hours']
                ?? null
            )
                ? $input['weekly_hours']
                : [];

        foreach (
            llama_resource_weekdays()
            as $day => $dayLabel
        ) {
            $row =
                is_array(
                    $submittedWeekly[$day]
                    ?? null
                )
                    ? $submittedWeekly[$day]
                    : [];

            $weeklyHours[$day] = [
                'open_hour' =>
                    $validateHour(
                        $row['open_hour']
                        ?? null,
                        $dayLabel . ' opening time'
                    ),
                'close_hour' =>
                    $validateHour(
                        $row['close_hour']
                        ?? null,
                        $dayLabel . ' closing time'
                    ),
            ];
        }
    }

    $hoursText =
        llama_resource_hours_summary([
            'hours_mode' => $hoursMode,
            'fixed_open_hour' => $fixedOpenHour,
            'fixed_close_hour' => $fixedCloseHour,
            'hours_text' => '',
        ]);

    $fields = [
        'street_address' => 255,
        'city' => 120,
        'state_region' => 120,
        'postal_code' => 32,
        'phone' => 64,
        'source_provider' => 160,
        'source_external_id' => 255,
    ];

    $clean = [];

    foreach ($fields as $key => $max) {
        $clean[$key] =
            mb_substr(
                trim(
                    (string) (
                        $input[$key]
                        ?? ''
                    )
                ),
                0,
                $max
            );
    }

    $notes =
        mb_substr(
            trim(
                (string) (
                    $input['notes']
                    ?? ''
                )
            ),
            0,
            10000
        );

    $creating = $resourceId < 1;

    if (!$creating && !admin_resource_get($db, $resourceId)) {
        throw new InvalidArgumentException(
            'Resource not found.'
        );
    }

    $db->beginTransaction();

    try {
        if ($creating) {
            $stmt =
                $db->prepare(
                    'INSERT INTO resources (
                        resource_type,
                        marker_service_type,
                        name,
                        slug,
                        latitude,
                        longitude,
                        street_address,
                        city,
                        state_region,
                        postal_code,
                        country_code,
                        phone,
                        website_url,
                        hours_text,
                        hours_mode,
                        fixed_open_hour,
                        fixed_close_hour,
                        status,
                        notes,
                        source_provider,
                        source_external_id,
                        source_url,
                        source_updated_at,
                        verified_at,
                        created_by,
                        updated_by
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     )'
                );

            $stmt->execute([
                $resourceType,
                $markerServiceType !== ''
                    ? $markerServiceType
                    : null,
                $name,
                $slug,
                $latitude,
                $longitude,
                $clean['street_address'] ?: null,
                $clean['city'] ?: null,
                $clean['state_region'] ?: null,
                $clean['postal_code'] ?: null,
                $countryCode ?: null,
                $clean['phone'] ?: null,
                $websiteUrl,
                $hoursText,
                $hoursMode,
                $fixedOpenHour,
                $fixedCloseHour,
                $status,
                $notes ?: null,
                $clean['source_provider'] ?: null,
                $clean['source_external_id'] ?: null,
                $sourceUrl,
                $sourceUpdatedAt,
                $verifiedAt,
                $actorUserId > 0 ? $actorUserId : null,
                $actorUserId > 0 ? $actorUserId : null,
            ]);

            $resourceId =
                (int) $db->lastInsertId();

            if ($resourceId < 1) {
                throw new RuntimeException(
                    'Resource could not be created.'
                );
            }
        } else {
            $stmt =
                $db->prepare(
                    'UPDATE resources
                     SET resource_type = ?,
                         marker_service_type = ?,
                         name = ?,
                         slug = ?,
                         latitude = ?,
                         longitude = ?,
                         street_address = ?,
                         city = ?,
                         state_region = ?,
                         postal_code = ?,
                         country_code = ?,
                         phone = ?,
                         website_url = ?,
                         hours_text = ?,
                         hours_mode = ?,
                         fixed_open_hour = ?,
                         fixed_close_hour = ?,
                         status = ?,
                         notes = ?,
                         source_provider = ?,
                         source_external_id = ?,
                         source_url = ?,
                         source_updated_at = ?,
                         verified_at = ?,
                         updated_by = ?,
                         is_active = 1
                     WHERE id = ?'
                );

            $stmt->execute([
                $resourceType,
                $markerServiceType !== ''
                    ? $markerServiceType
                    : null,
                $name,
                $slug,
                $latitude,
                $longitude,
                $clean['street_address'] ?: null,
                $clean['city'] ?: null,
                $clean['state_region'] ?: null,
                $clean['postal_code'] ?: null,
                $countryCode ?: null,
                $clean['phone'] ?: null,
                $websiteUrl,
                $hoursText,
                $hoursMode,
                $fixedOpenHour,
                $fixedCloseHour,
                $status,
                $notes ?: null,
                $clean['source_provider'] ?: null,
                $clean['source_external_id'] ?: null,
                $sourceUrl,
                $sourceUpdatedAt,
                $verifiedAt,
                $actorUserId > 0 ? $actorUserId : null,
                $resourceId,
            ]);
        }

        $disable =
            $db->prepare(
                'UPDATE resource_services
                 SET is_active = 0
                 WHERE resource_id = ?'
            );

        $disable->execute([
            $resourceId,
        ]);

        $upsert =
            $db->prepare(
                'INSERT INTO resource_services (
                    resource_id,
                    service_type,
                    status,
                    fee_status,
                    fee_description,
                    access_notes,
                    notes,
                    sort_order,
                    is_active
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 1
                 )
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    fee_status = VALUES(fee_status),
                    fee_description = VALUES(fee_description),
                    access_notes = VALUES(access_notes),
                    notes = VALUES(notes),
                    sort_order = VALUES(sort_order),
                    is_active = 1'
            );

        $sortOrder = 0;

        foreach ($services as $service) {
            $upsert->execute([
                $resourceId,
                $service['service_type'],
                $service['status'],
                $service['fee_status'],
                $service['fee_description'] ?: null,
                $service['access_notes'] ?: null,
                $service['notes'] ?: null,
                $sortOrder++,
            ]);
        }

        $deleteWeekly =
            $db->prepare(
                'DELETE FROM resource_weekly_hours
                 WHERE resource_id = ?'
            );

        $deleteWeekly->execute([
            $resourceId,
        ]);

        if ($hoursMode === 'varied_hours') {
            $insertWeekly =
                $db->prepare(
                    'INSERT INTO resource_weekly_hours (
                        resource_id,
                        day_of_week,
                        open_hour,
                        close_hour
                     ) VALUES (?, ?, ?, ?)'
                );

            foreach ($weeklyHours as $day => $row) {
                $insertWeekly->execute([
                    $resourceId,
                    $day,
                    $row['open_hour'],
                    $row['close_hour'],
                ]);
            }
        }

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $resourceId;
}

function admin_resource_deactivate(
    PDO $db,
    int $actorUserId,
    int $resourceId
): void {
    if ($resourceId < 1 || !admin_resource_get($db, $resourceId)) {
        throw new InvalidArgumentException(
            'Resource not found.'
        );
    }

    $db->beginTransaction();

    try {
        $stmt =
            $db->prepare(
                'UPDATE resources
                 SET is_active = 0,
                     updated_by = ?
                 WHERE id = ?'
            );

        $stmt->execute([
            $actorUserId > 0 ? $actorUserId : null,
            $resourceId,
        ]);

        /*
         * Preserve the Resource's Service configuration. Public readers already
         * exclude an inactive Resource, and keeping Service state intact makes
         * later reactivation deterministic.
         */
        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function admin_resource_add_photos(
    PDO $db,
    int $actorUserId,
    int $resourceId,
    array $files
): int {
    $resource =
        admin_resource_get(
            $db,
            $resourceId
        );

    if (!$resource) {
        throw new InvalidArgumentException(
            'Resource not found.'
        );
    }

    $existing =
        llama_resource_images(
            $db,
            $resourceId
        );

    $remaining =
        3 - count($existing);

    if ($remaining < 1) {
        throw new InvalidArgumentException(
            'This Resource already has the maximum of 3 photos.'
        );
    }

    $saved =
        llama_store_uploaded_photos(
            $files,
            $actorUserId,
            'resources',
            $remaining
        );

    $inserted = 0;

    try {
        foreach ($saved as $photo) {
            $path =
                trim(
                    (string) (
                        $photo['url']
                        ?? $photo['path']
                        ?? ''
                    )
                );

            if ($path === '') {
                continue;
            }

            llama_resource_image_insert(
                $db,
                $resourceId,
                $path,
                (string) (
                    $resource['name']
                    ?? 'Resource'
                ),
                $actorUserId
            );

            $inserted++;
        }

        if ($saved && $inserted < 1) {
            throw new RuntimeException(
                'The Resource photo was processed but could not be attached.'
            );
        }
    } catch (Throwable $exception) {
        foreach ($saved as $photo) {
            $path =
                trim(
                    (string) (
                        $photo['url']
                        ?? $photo['path']
                        ?? ''
                    )
                );

            if ($path !== '') {
                llama_delete_managed_photo(
                    $path,
                    $actorUserId,
                    'resources'
                );
            }
        }

        throw $exception;
    }

    return $inserted;
}

function admin_resource_delete_photo(
    PDO $db,
    int $resourceId,
    int $imageId
): void {
    $image =
        llama_resource_image(
            $db,
            $resourceId,
            $imageId
        );

    if (!$image) {
        throw new InvalidArgumentException(
            'Resource photo not found.'
        );
    }

    $path =
        trim(
            (string) (
                $image['src']
                ?? ''
            )
        );

    $uploadedBy =
        (int) (
            $image['uploaded_by']
            ?? 0
        );

    $delete =
        $db->prepare(
            'DELETE FROM resource_images
             WHERE id = ?
               AND resource_id = ?'
        );

    $delete->execute([
        $imageId,
        $resourceId,
    ]);

    $remaining =
        llama_resource_images(
            $db,
            $resourceId
        );

    if ($remaining) {
        llama_resource_images_reorder(
            $db,
            $resourceId,
            array_map(
                static fn (array $row): int =>
                    (int) $row['id'],
                $remaining
            )
        );
    }

    if ($path !== '' && $uploadedBy > 0) {
        llama_delete_managed_photo(
            $path,
            $uploadedBy,
            'resources'
        );
    }
}

function admin_resource_make_hero(
    PDO $db,
    int $resourceId,
    int $imageId
): void {
    $images =
        llama_resource_images(
            $db,
            $resourceId
        );

    $ids =
        array_values(
            array_map(
                static fn (array $image): int =>
                    (int) ($image['id'] ?? 0),
                $images
            )
        );

    $position =
        array_search(
            $imageId,
            $ids,
            true
        );

    if ($position === false) {
        throw new InvalidArgumentException(
            'Resource photo not found.'
        );
    }

    unset($ids[$position]);

    array_unshift(
        $ids,
        $imageId
    );

    llama_resource_images_reorder(
        $db,
        $resourceId,
        array_values($ids)
    );
}

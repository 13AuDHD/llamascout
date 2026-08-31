<?php

declare(strict_types=1);

function admin_places_list(
    PDO $db,
    string $search = '',
    string $status = '',
    string $state = ''
): array {
    $where = ['1 = 1'];
    $params = [];

    $search = trim($search);
    $status = trim($status);
    $state = trim($state);

    if ($search !== '') {
        $where[] = '(
            p.name LIKE ?
            OR p.slug LIKE ?
            OR p.city LIKE ?
            OR p.county LIKE ?
            OR p.state LIKE ?
            OR p.road LIKE ?
            OR p.land_manager LIKE ?
            OR CAST(p.id AS CHAR) = ?
        )';

        $needle = '%' . $search . '%';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    $allowedStatuses = [
        'draft',
        'active',
        'featured',
        'unlisted',
        'removed',
        'archived',
    ];

    if (in_array($status, $allowedStatuses, true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    if ($state !== '') {
        $where[] = 'p.state = ?';
        $params[] = $state;
    }

    $sql =
        'SELECT
            p.*,
            pi.src AS featured_image,
            (
                SELECT COUNT(*)
                FROM place_images pix
                WHERE pix.place_id = p.id
            ) AS image_count,
            (
                SELECT COUNT(*)
                FROM place_reports pr
                WHERE pr.place_id = p.id
                  AND pr.status IN ("open","investigating")
            ) AS open_report_count,
            (
                SELECT COUNT(*)
                FROM place_verifications pv
                WHERE pv.place_id = p.id
            ) AS verification_count
         FROM places p
         LEFT JOIN place_images pi
            ON pi.place_id = p.id
           AND pi.is_featured = 1
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY
            FIELD(
                p.status,
                "featured",
                "active",
                "draft",
                "unlisted",
                "archived",
                "removed"
            ),
            p.updated_at DESC,
            p.name ASC
         LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_places_states(PDO $db): array
{
    $stmt = $db->query(
        'SELECT DISTINCT state
         FROM places
         WHERE state IS NOT NULL
           AND state <> ""
         ORDER BY state ASC'
    );

    return array_values(
        array_filter(
            array_map(
                'strval',
                $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            )
        )
    );
}

function admin_place_get(
    PDO $db,
    int $placeId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$placeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_place_row(
    PDO $db,
    string $table,
    int $placeId
): array {
    $allowed = [
        'place_amenities',
        'place_connectivity',
        'place_details',
        'place_sensory_details',
        'place_rules',
        'place_experience',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Invalid Place data table.'
        );
    }

    $stmt = $db->prepare(
        "SELECT *
         FROM `$table`
         WHERE place_id = ?
         LIMIT 1"
    );

    $stmt->execute([$placeId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function admin_place_images(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM place_images
         WHERE place_id = ?
         ORDER BY
            is_featured DESC,
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_place_verifications(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            pv.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "System"
            ) AS verifier_name
         FROM place_verifications pv
         LEFT JOIN users u
            ON u.id = pv.verified_by
         WHERE pv.place_id = ?
         ORDER BY
            pv.verified_at DESC,
            pv.id DESC
         LIMIT 50'
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_place_status_history(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            h.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "System"
            ) AS changed_by_name
         FROM place_status_history h
         LEFT JOIN users u
            ON u.id = h.changed_by
         WHERE h.place_id = ?
         ORDER BY
            h.changed_at DESC,
            h.id DESC
         LIMIT 50'
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_place_save_core(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $place = admin_place_get(
        $db,
        $placeId
    );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $type = trim(
        (string) ($data['type'] ?? '')
    );

    if ($name === '') {
        throw new RuntimeException(
            'Place name is required.'
        );
    }

    if ($type === '') {
        throw new RuntimeException(
            'Place type is required.'
        );
    }

    $nullableText = static function (
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    };

    $nullableDecimal = static function (
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException(
                'Latitude and longitude values must be numeric.'
            );
        }

        return (string) $value;
    };

    $elevation =
        trim((string) ($data['elevation_feet'] ?? ''));

    if (
        $elevation !== ''
        && !preg_match('/^-?\d+$/', $elevation)
    ) {
        throw new RuntimeException(
            'Elevation must be a whole number.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE places
         SET
            name = ?,
            type = ?,
            description = ?,
            public_summary = ?,
            public_location_label = ?,
            latitude = ?,
            longitude = ?,
            public_latitude = ?,
            public_longitude = ?,
            elevation_feet = ?,
            road = ?,
            city = ?,
            county = ?,
            state = ?,
            region = ?,
            land_manager = ?,
            land_type = ?,
            sensory_summary = ?,
            access_summary = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $name,
        $type,
        $nullableText($data['description'] ?? ''),
        $nullableText($data['public_summary'] ?? ''),
        $nullableText($data['public_location_label'] ?? ''),
        $nullableDecimal($data['latitude'] ?? ''),
        $nullableDecimal($data['longitude'] ?? ''),
        $nullableDecimal($data['public_latitude'] ?? ''),
        $nullableDecimal($data['public_longitude'] ?? ''),
        $elevation !== '' ? (int) $elevation : null,
        $nullableText($data['road'] ?? ''),
        $nullableText($data['city'] ?? ''),
        $nullableText($data['county'] ?? ''),
        $nullableText($data['state'] ?? ''),
        $nullableText($data['region'] ?? ''),
        $nullableText($data['land_manager'] ?? ''),
        $nullableText($data['land_type'] ?? ''),
        $nullableText($data['sensory_summary'] ?? ''),
        $nullableText($data['access_summary'] ?? ''),
        $placeId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.core_updated',
        'Updated Place "' .
            $name .
            '".',
        [
            'place_id' => $placeId,
            'before_name' => $place['name'],
            'after_name' => $name,
        ]
    );
}

function admin_place_upsert_row(
    PDO $db,
    string $table,
    int $placeId,
    array $allowedFields,
    array $data
): void {
    $allowedTables = [
        'place_amenities',
        'place_connectivity',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException(
            'Invalid Place child table.'
        );
    }

    $values = [];

    foreach ($allowedFields as $field) {
        $raw = $data[$field] ?? null;

        if ($raw === '' || $raw === null) {
            $values[$field] = null;
            continue;
        }

        if (
            $table === 'place_connectivity'
            && $field === 'starlink_note'
        ) {
            $values[$field] =
                trim((string) $raw) ?: null;

            continue;
        }

        $values[$field] = (int) $raw;
    }

    $existing = admin_place_row(
        $db,
        $table,
        $placeId
    );

    if ($existing) {
        $sets = [];
        $params = [];

        foreach ($values as $field => $value) {
            $sets[] = "`$field` = ?";
            $params[] = $value;
        }

        $params[] = $placeId;

        $stmt = $db->prepare(
            "UPDATE `$table`
             SET " .
                implode(', ', $sets) .
            ' WHERE place_id = ?'
        );

        $stmt->execute($params);

        return;
    }

    $fields = array_keys($values);

    $stmt = $db->prepare(
        "INSERT INTO `$table` (
            place_id,
            " .
            implode(
                ', ',
                array_map(
                    static fn(string $field): string =>
                        "`$field`",
                    $fields
                )
            ) .
        ') VALUES (
            ?,
            ' .
            implode(
                ', ',
                array_fill(
                    0,
                    count($fields),
                    '?'
                )
            ) .
        ')'
    );

    $stmt->execute(
        array_merge(
            [$placeId],
            array_values($values)
        )
    );
}

function admin_place_save_amenities(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $fields = [
        'toilets',
        'potable_water',
        'trash',
        'fire_ring',
        'picnic_table',
        'bear_box',
        'showers',
        'electricity',
        'dump_station',
        'food_storage_required',
    ];

    admin_place_upsert_row(
        $db,
        'place_amenities',
        $placeId,
        $fields,
        $data
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.amenities_updated',
        'Updated Place amenities.',
        ['place_id' => $placeId]
    );
}

function admin_place_save_connectivity(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $fields = [
        'overall',
        't_mobile',
        'verizon',
        'att',
        'other_cell',
        'starlink',
        'starlink_tested',
        'starlink_note',
    ];

    admin_place_upsert_row(
        $db,
        'place_connectivity',
        $placeId,
        $fields,
        $data
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.connectivity_updated',
        'Updated Place connectivity.',
        ['place_id' => $placeId]
    );
}

function admin_place_change_status(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $newStatus,
    string $reason
): void {
    $allowed = [
        'draft',
        'active',
        'featured',
        'unlisted',
        'removed',
        'archived',
    ];

    if (!in_array($newStatus, $allowed, true)) {
        throw new RuntimeException(
            'Invalid Place status.'
        );
    }

    $place = admin_place_get(
        $db,
        $placeId
    );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $oldStatus =
        (string) $place['status'];

    $reason = trim($reason);

    if (
        $oldStatus !== $newStatus
        && $reason === ''
    ) {
        throw new RuntimeException(
            'Enter a reason for the status change.'
        );
    }

    if ($oldStatus === $newStatus) {
        return;
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'UPDATE places
             SET
                status = ?,
                status_reason = ?,
                status_changed_at = NOW(),
                status_changed_by = ?,
                published_at = CASE
                    WHEN ? IN ("active","featured")
                         AND published_at IS NULL
                        THEN NOW()
                    ELSE published_at
                END
             WHERE id = ?'
        );

        $stmt->execute([
            $newStatus,
            $reason !== '' ? $reason : null,
            $actorUserId,
            $newStatus,
            $placeId,
        ]);

        $history = $db->prepare(
            'INSERT INTO place_status_history (
                place_id,
                old_status,
                new_status,
                reason,
                changed_by
             ) VALUES (?, ?, ?, ?, ?)'
        );

        $history->execute([
            $placeId,
            $oldStatus,
            $newStatus,
            $reason !== '' ? $reason : null,
            $actorUserId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.status_updated',
            'Changed Place "' .
                (string) $place['name'] .
                '" from ' .
                $oldStatus .
                ' to ' .
                $newStatus .
                '.',
            [
                'place_id' => $placeId,
                'before' => $oldStatus,
                'after' => $newStatus,
                'reason' => $reason,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_place_add_verification(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $place = admin_place_get(
        $db,
        $placeId
    );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $type = trim(
        (string) ($data['verification_type'] ?? '')
    );

    if ($type === '') {
        throw new RuntimeException(
            'Verification type is required.'
        );
    }

    $visitedAt = trim(
        (string) ($data['visited_at'] ?? '')
    );

    $source = trim(
        (string) ($data['source'] ?? '')
    );

    $notes = trim(
        (string) ($data['notes'] ?? '')
    );

    $publicDataVerified =
        isset($data['public_data_verified'])
            ? 1
            : 0;

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'INSERT INTO place_verifications (
                place_id,
                verification_type,
                visited_at,
                verified_by,
                source,
                public_data_verified,
                notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $placeId,
            $type,
            $visitedAt !== '' ? $visitedAt : null,
            $actorUserId,
            $source !== '' ? $source : null,
            $publicDataVerified,
            $notes !== '' ? $notes : null,
        ]);

        $db->prepare(
            'UPDATE places
             SET last_verified_at = NOW()
             WHERE id = ?'
        )->execute([$placeId]);

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.verification_added',
            'Added verification to Place "' .
                (string) $place['name'] .
                '".',
            [
                'place_id' => $placeId,
                'verification_type' => $type,
                'visited_at' => $visitedAt,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_place_add_photos(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $photoToken,
    array $photos
): int {
    $existing = admin_place_images(
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
            'You can add only ' .
            $remaining .
            ' more photos.'
        );
    }

    $committed = [];

    try {
        $db->beginTransaction();

        $committed =
            llama_photo_commit_stage(
                'add-place',
                $actorUserId,
                $photoToken,
                $photos,
                '/uploads/places/' .
                    $placeId
            );

        $orderStmt = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), -1)
             FROM place_images
             WHERE place_id = ?'
        );

        $orderStmt->execute([$placeId]);

        $sortOrder =
            (int) $orderStmt->fetchColumn()
            + 1;

        $hasFeatured = false;

        foreach ($existing as $image) {
            if ((int) $image['is_featured'] === 1) {
                $hasFeatured = true;
                break;
            }
        }

        $insert = $db->prepare(
            'INSERT INTO place_images (
                place_id,
                src,
                alt_text,
                is_featured,
                sort_order,
                uploaded_by
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($committed as $index => $photo) {
            $path =
                (string) ($photo['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $isFeatured =
                !$hasFeatured
                && $index === 0;

            $insert->execute([
                $placeId,
                $path,
                trim(
                    (string) ($photo['alt'] ?? '')
                ) ?: null,
                $isFeatured ? 1 : 0,
                $sortOrder++,
                $actorUserId,
            ]);
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.photos_added',
            'Added Place photos.',
            [
                'place_id' => $placeId,
                'count' => count($committed),
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($committed as $photo) {
            llama_photo_delete_owned_permanent_path(
                (string) ($photo['path'] ?? ''),
                ['uploads/places']
            );
        }

        throw $exception;
    }

    return count($committed);
}

function admin_place_set_featured_image(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId
): void {
    $stmt = $db->prepare(
        'SELECT id
         FROM place_images
         WHERE id = ?
           AND place_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $imageId,
        $placeId,
    ]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

    $db->beginTransaction();

    try {
        $db->prepare(
            'UPDATE place_images
             SET is_featured = 0
             WHERE place_id = ?'
        )->execute([$placeId]);

        $db->prepare(
            'UPDATE place_images
             SET is_featured = 1
             WHERE id = ?
               AND place_id = ?'
        )->execute([
            $imageId,
            $placeId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.featured_image_updated',
            'Changed the featured Place image.',
            [
                'place_id' => $placeId,
                'image_id' => $imageId,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_place_delete_image(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId
): void {
    $stmt = $db->prepare(
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
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

    $path =
        (string) $image['src'];

    $wasFeatured =
        (int) $image['is_featured'] === 1;

    $db->beginTransaction();

    try {
        $db->prepare(
            'DELETE FROM place_images
             WHERE id = ?
               AND place_id = ?'
        )->execute([
            $imageId,
            $placeId,
        ]);

        if ($wasFeatured) {
            $next = $db->prepare(
                'SELECT id
                 FROM place_images
                 WHERE place_id = ?
                 ORDER BY
                    sort_order ASC,
                    id ASC
                 LIMIT 1'
            );

            $next->execute([$placeId]);

            $nextId =
                (int) ($next->fetchColumn() ?: 0);

            if ($nextId > 0) {
                $db->prepare(
                    'UPDATE place_images
                     SET is_featured = 1
                     WHERE id = ?'
                )->execute([$nextId]);
            }
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.image_deleted',
            'Deleted a Place image.',
            [
                'place_id' => $placeId,
                'image_id' => $imageId,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    llama_photo_delete_owned_permanent_path(
        $path,
        ['uploads/places']
    );
}

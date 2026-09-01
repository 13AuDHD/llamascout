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


function admin_place_provenance(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            pp.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                CASE
                    WHEN pp.original_contributor_id IS NULL
                        THEN NULL
                    ELSE "Former Llama Scout Member"
                END
            ) AS contributor_name,
            u.username AS contributor_username
         FROM place_provenance pp
         LEFT JOIN users u
            ON u.id = pp.original_contributor_id
         WHERE pp.place_id = ?
         LIMIT 1'
    );

    $stmt->execute([$placeId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}


function admin_place_contributions(
    PDO $db,
    int $placeId,
    int $limit = 50
): array {
    $limit = max(1, min(100, $limit));

    $stmt = $db->prepare(
        'SELECT
            pc.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS contributor_name,
            u.username AS contributor_username,
            COALESCE(
                NULLIF(m.display_name, ""),
                NULLIF(m.username, ""),
                CASE
                    WHEN pc.moderated_by IS NULL
                        THEN NULL
                    ELSE "System"
                END
            ) AS moderator_name
         FROM place_contributions pc
         LEFT JOIN users u
            ON u.id = pc.user_id
         LEFT JOIN users m
            ON m.id = pc.moderated_by
         WHERE pc.place_id = ?
         ORDER BY
            COALESCE(pc.approved_at, pc.created_at) DESC,
            pc.id DESC
         LIMIT ' . $limit
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function admin_place_update_history(
    PDO $db,
    int $placeId,
    int $limit = 50
): array {
    $limit = max(1, min(100, $limit));

    $stmt = $db->prepare(
        'SELECT
            pus.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS contributor_name,
            u.username AS contributor_username,
            COALESCE(
                NULLIF(r.display_name, ""),
                NULLIF(r.username, ""),
                CASE
                    WHEN pus.reviewed_by IS NULL
                        THEN NULL
                    ELSE "System"
                END
            ) AS reviewer_name
         FROM place_update_submissions pus
         LEFT JOIN users u
            ON u.id = pus.user_id
         LEFT JOIN users r
            ON r.id = pus.reviewed_by
         WHERE pus.place_id = ?
         ORDER BY
            pus.submitted_at DESC,
            pus.id DESC
         LIMIT ' . $limit
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function admin_place_reports_history(
    PDO $db,
    int $placeId,
    int $limit = 50
): array {
    $limit = max(1, min(100, $limit));

    $stmt = $db->prepare(
        'SELECT
            pr.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS reporter_name,
            u.username AS reporter_username,
            COALESCE(
                NULLIF(r.display_name, ""),
                NULLIF(r.username, ""),
                CASE
                    WHEN pr.reviewed_by IS NULL
                        THEN NULL
                    ELSE "System"
                END
            ) AS reviewer_name,
            (
                SELECT COUNT(*)
                FROM place_report_images pri
                WHERE pri.report_id = pr.id
            ) AS image_count
         FROM place_reports pr
         LEFT JOIN users u
            ON u.id = pr.user_id
         LEFT JOIN users r
            ON r.id = pr.reviewed_by
         WHERE pr.place_id = ?
         ORDER BY
            CASE pr.status
                WHEN "open" THEN 1
                WHEN "investigating" THEN 2
                ELSE 3
            END,
            pr.created_at DESC,
            pr.id DESC
         LIMIT ' . $limit
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function admin_place_notes(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            pn.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "System"
            ) AS author_name
         FROM place_notes pn
         LEFT JOIN users u
            ON u.id = pn.created_by
         WHERE pn.place_id = ?
         ORDER BY
            pn.sort_order ASC,
            pn.id ASC'
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function admin_place_audit_history(
    PDO $db,
    int $placeId,
    int $limit = 50
): array {
    $limit = max(1, min(100, $limit));

    try {
        $stmt = $db->prepare(
            'SELECT
                aal.*,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "System"
                ) AS actor_name
             FROM admin_audit_log aal
             LEFT JOIN users u
                ON u.id = aal.actor_user_id
             WHERE aal.action LIKE "place.%"
               AND JSON_UNQUOTE(
                    JSON_EXTRACT(
                        aal.metadata,
                        "$.place_id"
                    )
               ) = ?
             ORDER BY
                aal.created_at DESC,
                aal.id DESC
             LIMIT ' . $limit
        );

        $stmt->execute([(string) $placeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}


function admin_place_llama_scouted_state(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            pv.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Llama Scout"
            ) AS scout_name,
            u.username AS scout_username
         FROM place_verifications pv
         LEFT JOIN users u
            ON u.id = pv.verified_by
         WHERE pv.place_id = ?
           AND (
                pv.verification_type = "field-verified"
                OR LOWER(COALESCE(pv.source, "")) = "llama scouted"
           )
         ORDER BY
            COALESCE(pv.visited_at, DATE(pv.verified_at)) ASC,
            pv.id ASC
         LIMIT 1'
    );

    $stmt->execute([$placeId]);

    $first = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$first) {
        return [
            'ever_scouted' => false,
            'first' => [],
        ];
    }

    return [
        'ever_scouted' => true,
        'first' => $first,
    ];
}


function admin_place_operational_counts(
    PDO $db,
    int $placeId
): array {
    $queries = [
        'contributions' =>
            'SELECT COUNT(*) FROM place_contributions WHERE place_id = ?',
        'updates' =>
            'SELECT COUNT(*) FROM place_update_submissions WHERE place_id = ?',
        'pending_updates' =>
            'SELECT COUNT(*) FROM place_update_submissions WHERE place_id = ? AND status IN ("pending","needs-changes")',
        'reports' =>
            'SELECT COUNT(*) FROM place_reports WHERE place_id = ?',
        'open_reports' =>
            'SELECT COUNT(*) FROM place_reports WHERE place_id = ? AND status IN ("open","investigating")',
        'verifications' =>
            'SELECT COUNT(*) FROM place_verifications WHERE place_id = ?',
        'notes' =>
            'SELECT COUNT(*) FROM place_notes WHERE place_id = ?',
    ];

    $counts = [];

    foreach ($queries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$placeId]);
        $counts[$key] = (int) $stmt->fetchColumn();
    }

    return $counts;
}


function admin_place_save_image_metadata(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $imageId,
    string $altText,
    int $sortOrder
): void {
    if ($imageId < 1) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

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

    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new RuntimeException(
            'Place image not found.'
        );
    }

    $altText = trim($altText);
    $sortOrder = max(0, min(999, $sortOrder));

    $db->prepare(
        'UPDATE place_images
         SET
            alt_text = ?,
            sort_order = ?
         WHERE id = ?
           AND place_id = ?'
    )->execute([
        $altText !== '' ? $altText : null,
        $sortOrder,
        $imageId,
        $placeId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.image_metadata_updated',
        'Updated Place photo caption/order.',
        [
            'place_id' => $placeId,
            'image_id' => $imageId,
            'before_alt_text' => $image['alt_text'] ?? null,
            'after_alt_text' => $altText !== '' ? $altText : null,
            'before_sort_order' => (int) ($image['sort_order'] ?? 0),
            'after_sort_order' => $sortOrder,
        ]
    );
}


function admin_place_add_note(
    PDO $db,
    int $actorUserId,
    int $placeId,
    string $note
): void {
    $note = trim($note);

    if ($note === '') {
        throw new RuntimeException(
            'Enter a Place note.'
        );
    }

    if (mb_strlen($note) > 2000) {
        throw new RuntimeException(
            'Place notes must be 2,000 characters or fewer.'
        );
    }

    $orderStmt = $db->prepare(
        'SELECT COALESCE(MAX(sort_order), -1)
         FROM place_notes
         WHERE place_id = ?'
    );

    $orderStmt->execute([$placeId]);

    $sortOrder =
        (int) $orderStmt->fetchColumn() + 1;

    $stmt = $db->prepare(
        'INSERT INTO place_notes (
            place_id,
            note,
            sort_order,
            created_by
         ) VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $placeId,
        $note,
        $sortOrder,
        $actorUserId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.note_added',
        'Added an internal/field note to a Place.',
        [
            'place_id' => $placeId,
            'note_id' => (int) $db->lastInsertId(),
        ]
    );
}


function admin_place_delete_note(
    PDO $db,
    int $actorUserId,
    int $placeId,
    int $noteId
): void {
    $stmt = $db->prepare(
        'SELECT *
         FROM place_notes
         WHERE id = ?
           AND place_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $noteId,
        $placeId,
    ]);

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        throw new RuntimeException(
            'Place note not found.'
        );
    }

    $db->prepare(
        'DELETE FROM place_notes
         WHERE id = ?
           AND place_id = ?'
    )->execute([
        $noteId,
        $placeId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.note_deleted',
        'Deleted a Place note.',
        [
            'place_id' => $placeId,
            'note_id' => $noteId,
            'note' => $note['note'] ?? null,
        ]
    );
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

    $incomingSlug =
        trim(
            (string) (
                $data['slug']
                ?? ''
            )
        );

    $slugSource =
        $incomingSlug !== ''
            ? $incomingSlug
            : $name;

    $slug = strtolower(
        trim(
            $slugSource
        )
    );

    $slug = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $slug
    ) ?? '';

    $slug = trim($slug, '-');

    $sourceType = trim(
        (string) (
            $data['source_type']
            ?? $place['source_type']
            ?? 'llama-scouted'
        )
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

    $allowedPlaceTypes = [
        'dispersed-camping',
        'campground',
        'overnight-parking',
        'boondocking',
        'primitive-camping',
        'backcountry-camping',
        'other',
    ];

    $currentStoredType =
        strtolower(
            trim(
                (string) (
                    $place['type']
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $type,
            $allowedPlaceTypes,
            true
        )
        &&
        $type !== $currentStoredType
    ) {
        throw new RuntimeException(
            'Choose a valid Place type.'
        );
    }

    if ($slug === '') {
        throw new RuntimeException(
            'URL slug is required.'
        );
    }

    if (strlen($slug) > 190) {
        throw new RuntimeException(
            'URL slug must be 190 characters or fewer.'
        );
    }

    if (!in_array(
        $sourceType,
        [
            'llama-scouted',
            'community-scouted',
            'external',
            'legacy',
        ],
        true
    )) {
        throw new RuntimeException(
            'Choose a valid Place source.'
        );
    }

    $slugStmt = $db->prepare(
        'SELECT id
         FROM places
         WHERE slug = ?
           AND id <> ?
         LIMIT 1'
    );

    $slugStmt->execute([
        $slug,
        $placeId,
    ]);

    if ($slugStmt->fetchColumn()) {
        throw new RuntimeException(
            'That URL slug is already used by another Place.'
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
            slug = ?,
            name = ?,
            type = ?,
            source_type = ?,
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
        $slug,
        $name,
        $type,
        $sourceType,
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
            'before_slug' => $place['slug'] ?? null,
            'after_slug' => $slug,
            'before_source_type' => $place['source_type'] ?? null,
            'after_source_type' => $sourceType,
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


function admin_place_sensory_period(
    PDO $db,
    int $placeId,
    string $period
): array {
    if (!in_array($period, ['daytime', 'nighttime'], true)) {
        throw new InvalidArgumentException(
            'Invalid sensory period.'
        );
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM place_sensory
         WHERE place_id = ?
           AND period = ?
         LIMIT 1'
    );

    $stmt->execute([
        $placeId,
        $period,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function admin_place_normalize_value(
    mixed $raw,
    string $type
): mixed {
    if ($raw === '' || $raw === null) {
        return null;
    }

    if ($type === 'bool') {
        $value = (int) $raw;

        if (!in_array($value, [0, 1], true)) {
            throw new RuntimeException(
                'Yes/No values must be valid.'
            );
        }

        return $value;
    }

    if ($type === 'rating') {
        $value = (int) $raw;

        if ($value < 1 || $value > 5) {
            throw new RuntimeException(
                'Ratings must be from 1 to 5.'
            );
        }

        return $value;
    }

    if ($type === 'int') {
        if (!preg_match('/^-?\d+$/', (string) $raw)) {
            throw new RuntimeException(
                'Whole-number fields must contain a valid number.'
            );
        }

        return (int) $raw;
    }

    if ($type === 'decimal') {
        if (!is_numeric($raw)) {
            throw new RuntimeException(
                'Numeric fields must contain a valid number.'
            );
        }

        return (string) $raw;
    }

    return trim((string) $raw) !== ''
        ? trim((string) $raw)
        : null;
}

function admin_place_save_child_row(
    PDO $db,
    int $placeId,
    string $table,
    array $fieldTypes,
    array $data
): void {
    $allowedTables = [
        'place_details',
        'place_sensory_details',
        'place_rules',
        'place_experience',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException(
            'Invalid Place report table.'
        );
    }

    $values = [];

    foreach ($fieldTypes as $field => $type) {
        $values[$field] =
            admin_place_normalize_value(
                $data[$field] ?? null,
                $type
            );
    }

    $existing = admin_place_row(
        $db,
        $table,
        $placeId
    );

    if ($existing) {
        $set = [];
        $params = [];

        foreach ($values as $field => $value) {
            $set[] = "`$field` = ?";
            $params[] = $value;
        }

        $params[] = $placeId;

        $stmt = $db->prepare(
            "UPDATE `$table`
             SET " .
                implode(', ', $set) .
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

function admin_place_save_details(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $fields = [
        'vehicle_capacity' => 'int',
        'max_vehicle_length_feet' => 'int',
        'tent_camping_suitable' => 'bool',
        'rv_suitable' => 'bool',
        'trailer_suitable' => 'bool',
        'parking_surface' => 'text',
        'levelness' => 'rating',
        'leveling_required' => 'bool',
        'turnaround_space' => 'bool',
        'pull_through' => 'bool',
        'back_in' => 'bool',
        'ground_condition' => 'text',
        'site_open_sky' => 'rating',
        'tree_cover' => 'rating',
        'site_shade' => 'rating',
        'site_access_difficulty' => 'rating',
        'road_overall_difficulty' => 'rating',
        'road_difficulty' => 'rating',
        'road_stress' => 'rating',
        'sedan_accessible' => 'bool',
        'high_clearance_recommended' => 'bool',
        'four_wheel_drive_recommended' => 'bool',
        'road_surface' => 'text',
        'road_width' => 'text',
        'rocks' => 'rating',
        'washboards' => 'rating',
        'potholes' => 'rating',
        'mud_risk' => 'rating',
        'steep_grades' => 'rating',
        'drop_off_exposure' => 'rating',
        'water_crossings' => 'bool',
        'downed_tree_risk' => 'bool',
        'seasonal_closure' => 'bool',
        'forest' => 'bool',
        'mountains' => 'bool',
        'water_nearby' => 'bool',
        'water_view' => 'bool',
        'mountain_view' => 'bool',
        'forest_view' => 'bool',
        'wildlife' => 'bool',
        'bugs' => 'bool',
        'wind_exposure' => 'rating',
        'sun_exposure' => 'rating',
        'environment_shade' => 'rating',
        'environment_open_sky' => 'rating',
        'wheelchair_friendly' => 'bool',
        'mobility_device_friendly' => 'bool',
        'flat_walking_surface' => 'bool',
        'walking_distance_from_vehicle' => 'text',
        'step_free_access' => 'bool',
        'accessible_toilet' => 'bool',
        'accessible_picnic_table' => 'bool',
        'felt_safe_daytime' => 'bool',
        'felt_safe_nighttime' => 'bool',
        'flash_flood_risk' => 'bool',
        'wildfire_risk' => 'bool',
        'fall_hazard' => 'bool',
        'cliff_exposure' => 'bool',
        'rockfall_risk' => 'bool',
        'wildlife_risk' => 'bool',
        'traffic_hazard' => 'bool',
        'emergency_access' => 'bool',
        'warning_exposed_to_road' => 'bool',
        'warning_zero_privacy' => 'bool',
        'warning_passing_vehicle_dust' => 'bool',
        'warning_possible_downed_trees' => 'bool',
        'warning_no_tent_camping' => 'bool',
        'warning_limited_vehicle_length' => 'bool',
        'warning_leveling_may_be_required' => 'bool',
        'warning_no_amenities' => 'bool',
        'warning_motorized_recreation_traffic' => 'bool',
        'warning_blind_turn_traffic_nearby' => 'bool',
    ];

    admin_place_save_child_row(
        $db,
        $placeId,
        'place_details',
        $fields,
        $data
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.scout_report_details_updated',
        'Updated road, access, environment, safety, and warning details.',
        ['place_id' => $placeId]
    );
}

function admin_place_save_sensory_details(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $detailFields = [
        'dust_from_traffic' => 'rating',
        'generator_noise' => 'rating',
        'aircraft_noise' => 'rating',
        'road_noise' => 'rating',
        'human_activity' => 'rating',
        'wildlife_noise' => 'rating',
        'wind_noise' => 'rating',
        'smoke_risk' => 'rating',
        'strong_odors' => 'rating',
        'visual_exposure' => 'rating',
        'predictability' => 'rating',
    ];

    admin_place_save_child_row(
        $db,
        $placeId,
        'place_sensory_details',
        $detailFields,
        $data
    );

    $periodFields = [
        'noise',
        'traffic',
        'crowds',
        'privacy',
        'light_pollution',
        'sensory_comfort',
        'social_interaction_likelihood',
    ];

    foreach (['daytime', 'nighttime'] as $period) {
        $values = [];

        foreach ($periodFields as $field) {
            $values[$field] =
                admin_place_normalize_value(
                    $data[$period . '_' . $field] ?? null,
                    'rating'
                );
        }

        $existing =
            admin_place_sensory_period(
                $db,
                $placeId,
                $period
            );

        if ($existing) {
            $sets = [];
            $params = [];

            foreach ($values as $field => $value) {
                $sets[] = "`$field` = ?";
                $params[] = $value;
            }

            $params[] = $placeId;
            $params[] = $period;

            $stmt = $db->prepare(
                'UPDATE place_sensory
                 SET ' . implode(', ', $sets) . '
                 WHERE place_id = ?
                   AND period = ?'
            );

            $stmt->execute($params);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO place_sensory (
                    place_id,
                    period,
                    noise,
                    traffic,
                    crowds,
                    privacy,
                    light_pollution,
                    sensory_comfort,
                    social_interaction_likelihood
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )'
            );

            $stmt->execute([
                $placeId,
                $period,
                $values['noise'],
                $values['traffic'],
                $values['crowds'],
                $values['privacy'],
                $values['light_pollution'],
                $values['sensory_comfort'],
                $values['social_interaction_likelihood'],
            ]);
        }
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.sensory_updated',
        'Updated daytime, nighttime, and detailed sensory conditions.',
        ['place_id' => $placeId]
    );
}

function admin_place_save_rules(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $fields = [
        'best_months' => 'text',
        'winter_access' => 'bool',
        'snow_risk' => 'rating',
        'mud_season_risk' => 'rating',
        'monsoon_risk' => 'rating',
        'recommended_travel_season' => 'text',
        'seasonal_access_note' => 'text',
        'overnight_camping_allowed' => 'bool',
        'dispersed_camping_allowed' => 'bool',
        'stay_limit_days' => 'int',
        'maximum_days_per_60_day_period' => 'int',
        'move_distance_after_stay_miles' => 'decimal',
        'permit_required' => 'bool',
        'fee' => 'decimal',
        'campfire_allowed' => 'bool',
        'current_fire_restrictions_url' => 'text',
        'vehicle_distance_from_road_max_feet' => 'int',
        'minimum_distance_from_water_feet' => 'int',
        'existing_sites_encouraged' => 'bool',
        'pack_it_in_pack_it_out' => 'bool',
        'residential_use_prohibited' => 'bool',
        'nearest_town' => 'text',
        'nearest_fuel' => 'text',
        'nearest_grocery' => 'text',
        'nearest_water' => 'text',
        'nearest_toilet' => 'text',
        'nearest_hospital' => 'text',
    ];

    admin_place_save_child_row(
        $db,
        $placeId,
        'place_rules',
        $fields,
        $data
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.rules_updated',
        'Updated Place rules, seasonal access, fees, and nearby services.',
        ['place_id' => $placeId]
    );
}

function admin_place_save_experience(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $data
): void {
    $fields = [
        'sunrise_view' => 'rating',
        'sunset_view' => 'rating',
        'mountain_view' => 'rating',
        'forest_view' => 'rating',
        'night_sky' => 'rating',
        'stargazing' => 'rating',
        'quiet_evening' => 'rating',
        'overnight_comfort' => 'rating',
        'extended_stay_comfort' => 'rating',
        'sensory_retreat' => 'rating',
        'remote_work' => 'rating',
        'overall_scenery' => 'rating',
        'recommended_overnight_stop' => 'rating',
        'recommended_quiet_evening' => 'rating',
        'recommended_extended_stay' => 'rating',
        'recommended_sensory_retreat' => 'rating',
        'recommended_stargazing' => 'rating',
        'recommended_remote_work' => 'rating',
        'recommended_solo_travel' => 'bool',
        'recommended_families' => 'bool',
        'recommended_large_groups' => 'bool',
        'not_recommended_for' => 'text',
    ];

    admin_place_save_child_row(
        $db,
        $placeId,
        'place_experience',
        $fields,
        $data
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'place.experience_updated',
        'Updated Place experience ratings and recommendations.',
        ['place_id' => $placeId]
    );
}

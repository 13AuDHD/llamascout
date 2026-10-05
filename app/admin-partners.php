<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-places.php';


/*
 * =========================================================
 * PARTNER ADMIN HELPERS
 * =========================================================
 */


function admin_partner_categories(): array
{
    return [
        'travel-centers' =>
            'Travel Centers',
        'fuel-stations' =>
            'Fuel Stations',
        'retail' =>
            'Retail',
        'restaurants' =>
            'Restaurants',
        'campgrounds-rv' =>
            'Campgrounds / RV',
        'outdoor-retail' =>
            'Outdoor Retail',
        'automotive' =>
            'Automotive',
        'hospitality' =>
            'Hospitality',
        'other' =>
            'Other',
    ];
}


function admin_partner_statuses(): array
{
    return [
        'prospect' =>
            'Prospect',
        'active' =>
            'Active',
        'inactive' =>
            'Inactive',
        'archived' =>
            'Archived',
    ];
}


function admin_partner_slug(
    string $value
): string {
    $value =
        strtolower(
            trim($value)
        );

    $value =
        preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        )
        ?? '';

    return
        trim(
            $value,
            '-'
        );
}


function admin_partner_list(
    PDO $db
): array {
    $stmt =
        $db->query(
            'SELECT *
             FROM partners
             ORDER BY
                CASE status
                    WHEN "active" THEN 1
                    WHEN "prospect" THEN 2
                    WHEN "inactive" THEN 3
                    WHEN "archived" THEN 4
                    ELSE 5
                END,
                name ASC'
        );

    return
        $stmt
            ? $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            : [];
}


function admin_partner_get(
    PDO $db,
    int $partnerId
): ?array {
    if ($partnerId < 1) {
        return null;
    }

    $stmt =
        $db->prepare(
            'SELECT *
             FROM partners
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $partnerId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        is_array($row)
            ? $row
            : null;
}


function admin_partner_counts(
    array $partners
): array {
    $counts = [
        'total' => 0,
        'prospect' => 0,
        'active' => 0,
        'inactive' => 0,
        'archived' => 0,
    ];

    foreach ($partners as $partner) {
        $counts['total']++;

        $status =
            strtolower(
                trim(
                    (string) (
                        $partner['status']
                        ?? ''
                    )
                )
            );

        if (
            array_key_exists(
                $status,
                $counts
            )
        ) {
            $counts[$status]++;
        }
    }

    return $counts;
}


function admin_partner_create(
    PDO $db,
    int $actorUserId,
    array $input
): int {
    $name =
        trim(
            (string) (
                $input['name']
                ?? ''
            )
        );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Partner name is required.'
        );
    }

    $slug =
        admin_partner_slug(
            (string) (
                $input['slug']
                ?? $name
            )
        );

    if ($slug === '') {
        $slug =
            admin_partner_slug(
                $name
            );
    }

    if ($slug === '') {
        throw new InvalidArgumentException(
            'Partner slug is required.'
        );
    }

    $categories =
        admin_partner_categories();

    $category =
        trim(
            (string) (
                $input['category']
                ?? 'other'
            )
        );

    if (
        !array_key_exists(
            $category,
            $categories
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid partner category.'
        );
    }

    $statuses =
        admin_partner_statuses();

    $status =
        trim(
            (string) (
                $input['status']
                ?? 'prospect'
            )
        );

    if (
        !array_key_exists(
            $status,
            $statuses
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid partner status.'
        );
    }

    $duplicate =
        $db->prepare(
            'SELECT COUNT(*)
             FROM partners
             WHERE slug = ?'
        );

    $duplicate->execute([
        $slug,
    ]);

    if (
        (int) $duplicate
            ->fetchColumn() > 0
    ) {
        throw new InvalidArgumentException(
            'A partner with that slug already exists.'
        );
    }

    $stmt =
        $db->prepare(
            'INSERT INTO partners (
                name,
                slug,
                category,
                status,
                marker_icon,
                created_by_user_id,
                updated_by_user_id
             ) VALUES (
                ?, ?, ?, ?, "brand-partner", ?, ?
             )'
        );

    $stmt->execute([
        $name,
        $slug,
        $category,
        $status,
        $actorUserId > 0
            ? $actorUserId
            : null,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);

    return
        (int) $db->lastInsertId();
}


function admin_partner_save(
    PDO $db,
    int $actorUserId,
    int $partnerId,
    array $input
): void {
    $partner =
        admin_partner_get(
            $db,
            $partnerId
        );

    if (!$partner) {
        throw new InvalidArgumentException(
            'Partner not found.'
        );
    }

    $name =
        trim(
            (string) (
                $input['name']
                ?? ''
            )
        );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Partner name is required.'
        );
    }

    $slug =
        admin_partner_slug(
            (string) (
                $input['slug']
                ?? ''
            )
        );

    if ($slug === '') {
        $slug =
            admin_partner_slug(
                $name
            );
    }

    if ($slug === '') {
        throw new InvalidArgumentException(
            'Partner slug is required.'
        );
    }

    $categories =
        admin_partner_categories();

    $category =
        trim(
            (string) (
                $input['category']
                ?? 'other'
            )
        );

    if (
        !array_key_exists(
            $category,
            $categories
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid partner category.'
        );
    }

    $statuses =
        admin_partner_statuses();

    $status =
        trim(
            (string) (
                $input['status']
                ?? 'prospect'
            )
        );

    if (
        !array_key_exists(
            $status,
            $statuses
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid partner status.'
        );
    }

    $duplicate =
        $db->prepare(
            'SELECT COUNT(*)
             FROM partners
             WHERE slug = ?
               AND id <> ?'
        );

    $duplicate->execute([
        $slug,
        $partnerId,
    ]);

    if (
        (int) $duplicate
            ->fetchColumn() > 0
    ) {
        throw new InvalidArgumentException(
            'Another partner already uses that slug.'
        );
    }

    $logoPath =
        trim(
            (string) (
                $input['logo_path']
                ?? ''
            )
        );

    $markerIcon =
        admin_partner_slug(
            (string) (
                $input['marker_icon']
                ?? 'brand-partner'
            )
        );

    if ($markerIcon === '') {
        $markerIcon =
            'brand-partner';
    }

    $primaryColor =
        strtoupper(
            trim(
                (string) (
                    $input['primary_color']
                    ?? ''
                )
            )
        );

    $accentColor =
        strtoupper(
            trim(
                (string) (
                    $input['accent_color']
                    ?? ''
                )
            )
        );

    foreach (
        [
            'Primary color' =>
                $primaryColor,
            'Accent color' =>
                $accentColor,
        ]
        as $label => $value
    ) {
        if (
            $value !== ''
            && !preg_match(
                '/^#[0-9A-F]{6}$/',
                $value
            )
        ) {
            throw new InvalidArgumentException(
                $label
                . ' must use a six-digit hex value such as #2563EB.'
            );
        }
    }

    $trademarkOwner =
        trim(
            (string) (
                $input['trademark_owner']
                ?? ''
            )
        );

    $brandingRightsNote =
        trim(
            (string) (
                $input['branding_rights_note']
                ?? ''
            )
        );

    $notes =
        trim(
            (string) (
                $input['notes']
                ?? ''
            )
        );

    $stmt =
        $db->prepare(
            'UPDATE partners
             SET
                name = ?,
                slug = ?,
                category = ?,
                status = ?,
                branding_permitted = ?,
                show_map_markers = ?,
                use_branded_cards = ?,
                use_branded_place_pages = ?,
                logo_path = ?,
                marker_icon = ?,
                primary_color = ?,
                accent_color = ?,
                trademark_owner = ?,
                branding_rights_note = ?,
                notes = ?,
                updated_by_user_id = ?
             WHERE id = ?'
        );

    $stmt->execute([
        $name,
        $slug,
        $category,
        $status,
        isset(
            $input['branding_permitted']
        )
            ? 1
            : 0,
        isset(
            $input['show_map_markers']
        )
            ? 1
            : 0,
        isset(
            $input['use_branded_cards']
        )
            ? 1
            : 0,
        isset(
            $input['use_branded_place_pages']
        )
            ? 1
            : 0,
        $logoPath !== ''
            ? $logoPath
            : null,
        $markerIcon,
        $primaryColor !== ''
            ? $primaryColor
            : null,
        $accentColor !== ''
            ? $accentColor
            : null,
        $trademarkOwner !== ''
            ? $trademarkOwner
            : null,
        $brandingRightsNote !== ''
            ? $brandingRightsNote
            : null,
        $notes !== ''
            ? $notes
            : null,
        $actorUserId > 0
            ? $actorUserId
            : null,
        $partnerId,
    ]);
}


function admin_partner_asset_url(
    string $siteUrl,
    ?string $path
): string {
    $path =
        trim(
            (string) $path
        );

    if ($path === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $path
        )
    ) {
        return $path;
    }

    return
        rtrim(
            $siteUrl,
            '/'
        )
        . '/'
        . ltrim(
            $path,
            '/'
        );
}


function admin_partner_marker_url(
    string $siteUrl,
    string $markerIcon
): string {
    $markerIcon =
        admin_partner_slug(
            $markerIcon
        );

    if ($markerIcon === '') {
        $markerIcon =
            'brand-partner';
    }

    return
        rtrim(
            $siteUrl,
            '/'
        )
        . '/assets/icons/'
        . rawurlencode(
            $markerIcon
        )
        . '.svg';
}


/*
 * =========================================================
 * PARTNER LOCATIONS
 * =========================================================
 */


function admin_partner_locations(
    PDO $db,
    int $partnerId
): array {
    $stmt =
        $db->prepare(
            'SELECT
                pp.*,
                p.name,
                p.slug,
                p.type,
                p.status AS place_status,
                p.city,
                p.state
             FROM place_partners pp
             INNER JOIN places p
                ON p.id = pp.place_id
             WHERE pp.partner_id = ?
             ORDER BY
                pp.is_primary DESC,
                p.name ASC,
                p.id ASC'
        );

    $stmt->execute([
        $partnerId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function admin_partner_attach_place(
    PDO $db,
    int $actorUserId,
    int $partnerId,
    int $placeId,
    string $relationshipType = 'official'
): void {
    if ($partnerId < 1) {
        throw new InvalidArgumentException(
            'Partner is required.'
        );
    }

    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Choose a Place.'
        );
    }

    if (
        !admin_partner_get(
            $db,
            $partnerId
        )
    ) {
        throw new InvalidArgumentException(
            'Partner not found.'
        );
    }

    $placeStmt =
        $db->prepare(
            'SELECT
                id,
                status
             FROM places
             WHERE id = ?
               AND status <> "removed"
               AND status <> "archived"
             LIMIT 1'
        );

    $placeStmt->execute([
        $placeId,
    ]);

    $place =
        $placeStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$place) {
        throw new InvalidArgumentException(
            'That Place is not available for partner assignment.'
        );
    }

    $allowedTypes = [
        'official',
        'demo',
        'affiliate',
        'other',
    ];

    if (
        !in_array(
            $relationshipType,
            $allowedTypes,
            true
        )
    ) {
        $relationshipType =
            'official';
    }

    $existingStmt =
        $db->prepare(
            'SELECT
                relationship_type,
                previous_place_status
             FROM place_partners
             WHERE partner_id = ?
               AND place_id = ?
             LIMIT 1'
        );

    $existingStmt->execute([
        $partnerId,
        $placeId,
    ]);

    $existing =
        $existingStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    $currentStatus =
        (string) (
            $place['status']
            ?? 'draft'
        );

    $previousPlaceStatus =
        null;

    if ($relationshipType === 'demo') {
        if (
            is_array($existing)
            && (string) (
                $existing['relationship_type']
                ?? ''
            ) === 'demo'
            && trim(
                (string) (
                    $existing['previous_place_status']
                    ?? ''
                )
            ) !== ''
        ) {
            $previousPlaceStatus =
                trim(
                    (string) $existing[
                        'previous_place_status'
                    ]
                );
        } else {
            $previousPlaceStatus =
                $currentStatus;
        }

        if ($currentStatus !== 'draft') {
            admin_place_change_status(
                $db,
                $actorUserId,
                $placeId,
                'draft',
                'Partner demo location. Hidden from the public site while demo access is active.'
            );

            $currentStatus =
                'draft';
        }

    } elseif (
        is_array($existing)
        && (string) (
            $existing['relationship_type']
            ?? ''
        ) === 'demo'
        && $currentStatus === 'draft'
    ) {
        $restoreStatus =
            trim(
                (string) (
                    $existing['previous_place_status']
                    ?? ''
                )
            );

        $restorableStatuses = [
            'draft',
            'active',
            'featured',
            'unlisted',
        ];

        if (
            !in_array(
                $restoreStatus,
                $restorableStatuses,
                true
            )
        ) {
            $restoreStatus =
                'active';
        }

        if ($restoreStatus !== 'draft') {
            admin_place_change_status(
                $db,
                $actorUserId,
                $placeId,
                $restoreStatus,
                'Partner demo relationship ended. Restored the Place status that existed before demo mode.'
            );
        }
    }

    $stmt =
        $db->prepare(
            'INSERT INTO place_partners (
                partner_id,
                place_id,
                relationship_type,
                previous_place_status,
                branding_enabled,
                is_primary,
                created_by_user_id,
                updated_by_user_id
             ) VALUES (
                ?, ?, ?, ?, 1, 1, ?, ?
             )
             ON DUPLICATE KEY UPDATE
                relationship_type =
                    VALUES(relationship_type),
                previous_place_status =
                    VALUES(previous_place_status),
                branding_enabled = 1,
                updated_by_user_id =
                    VALUES(updated_by_user_id),
                updated_at =
                    CURRENT_TIMESTAMP'
        );

    $stmt->execute([
        $partnerId,
        $placeId,
        $relationshipType,
        $relationshipType === 'demo'
            ? $previousPlaceStatus
            : null,
        $actorUserId > 0
            ? $actorUserId
            : null,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);
}


function admin_partner_detach_place(
    PDO $db,
    int $partnerId,
    int $placeId,
    int $actorUserId = 0
): void {
    $existingStmt =
        $db->prepare(
            'SELECT
                relationship_type,
                previous_place_status
             FROM place_partners
             WHERE partner_id = ?
               AND place_id = ?
             LIMIT 1'
        );

    $existingStmt->execute([
        $partnerId,
        $placeId,
    ]);

    $existing =
        $existingStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;

    if (
        is_array($existing)
        && (string) (
            $existing['relationship_type']
            ?? ''
        ) === 'demo'
    ) {
        $placeStmt =
            $db->prepare(
                'SELECT status
                 FROM places
                 WHERE id = ?
                 LIMIT 1'
            );

        $placeStmt->execute([
            $placeId,
        ]);

        $currentStatus =
            trim(
                (string) (
                    $placeStmt->fetchColumn()
                    ?: ''
                )
            );

        if ($currentStatus === 'draft') {
            $restoreStatus =
                trim(
                    (string) (
                        $existing['previous_place_status']
                        ?? ''
                    )
                );

            $restorableStatuses = [
                'draft',
                'active',
                'featured',
                'unlisted',
            ];

            if (
                !in_array(
                    $restoreStatus,
                    $restorableStatuses,
                    true
                )
            ) {
                $restoreStatus =
                    'active';
            }

            if ($restoreStatus !== 'draft') {
                admin_place_change_status(
                    $db,
                    $actorUserId,
                    $placeId,
                    $restoreStatus,
                    'Partner demo relationship removed. Restored the Place status that existed before demo mode.'
                );
            }
        }
    }

    $stmt =
        $db->prepare(
            'DELETE FROM place_partners
             WHERE partner_id = ?
               AND place_id = ?'
        );

    $stmt->execute([
        $partnerId,
        $placeId,
    ]);
}


function admin_partner_set_place_branding(
    PDO $db,
    int $actorUserId,
    int $partnerId,
    int $placeId,
    bool $enabled
): void {
    $stmt =
        $db->prepare(
            'UPDATE place_partners
             SET
                branding_enabled = ?,
                updated_by_user_id = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE partner_id = ?
               AND place_id = ?'
        );

    $stmt->execute([
        $enabled
            ? 1
            : 0,
        $actorUserId > 0
            ? $actorUserId
            : null,
        $partnerId,
        $placeId,
    ]);
}

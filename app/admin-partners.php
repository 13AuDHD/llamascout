<?php

declare(strict_types=1);


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

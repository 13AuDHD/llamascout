<?php

declare(strict_types=1);


/*
 * =========================================================
 * PAD-US REFERENCE REVIEW HELPERS
 * =========================================================
 */


function llama_pad_us_review_limit(
    mixed $value,
    int $default = 50,
    int $max = 200
): int {
    $limit = (int) $value;

    if ($limit < 1) {
        $limit = $default;
    }

    return min(
        $max,
        max(
            10,
            $limit
        )
    );
}


function llama_pad_us_review_page(
    mixed $value
): int {
    return max(
        1,
        (int) $value
    );
}


function llama_pad_us_review_unit_filters(
    array $input
): array {
    $states =
        llama_pad_us_states();

    $stateCode =
        strtoupper(
            trim(
                (string) (
                    $input['state']
                    ?? ''
                )
            )
        );

    if (
        $stateCode !== ''
        && !isset(
            $states[$stateCode]
        )
    ) {
        $stateCode = '';
    }

    return [
        'state' =>
            $stateCode,
        'property_type_slug' =>
            trim(
                (string) (
                    $input['property_type_slug']
                    ?? ''
                )
            ),
        'organization_id' =>
            max(
                0,
                (int) (
                    $input['organization_id']
                    ?? 0
                )
            ),
        'designation' =>
            trim(
                (string) (
                    $input['designation']
                    ?? ''
                )
            ),
        'access' =>
            trim(
                (string) (
                    $input['access']
                    ?? ''
                )
            ),
        'q' =>
            trim(
                (string) (
                    $input['q']
                    ?? ''
                )
            ),
        'page' =>
            llama_pad_us_review_page(
                $input['page']
                ?? 1
            ),
        'limit' =>
            llama_pad_us_review_limit(
                $input['limit']
                ?? 50
            ),
    ];
}


function llama_pad_us_review_units(
    PDO $db,
    array $filters
): array {
    $source =
        llama_pad_us_source(
            $db
        );

    $where = [
        'u.source_id = ?',
        'u.active = 1',
    ];

    $params = [
        (int) $source['id'],
    ];

    if (
        ($filters['state'] ?? '')
        !== ''
    ) {
        $where[] =
            'u.state_code = ?';

        $params[] =
            $filters['state'];
    }

    if (
        ($filters['property_type_slug'] ?? '')
        !== ''
    ) {
        $where[] =
            'u.property_type_slug = ?';

        $params[] =
            $filters['property_type_slug'];
    }

    if (
        (int) (
            $filters['organization_id']
            ?? 0
        ) > 0
    ) {
        $where[] =
            'u.organization_id = ?';

        $params[] =
            (int) $filters[
                'organization_id'
            ];
    }

    if (
        ($filters['designation'] ?? '')
        !== ''
    ) {
        $where[] =
            'u.source_designation = ?';

        $params[] =
            $filters['designation'];
    }

    if (
        ($filters['access'] ?? '')
        !== ''
    ) {
        $where[] =
            'u.public_access = ?';

        $params[] =
            $filters['access'];
    }

    $query =
        trim(
            (string) (
                $filters['q']
                ?? ''
            )
        );

    if ($query !== '') {
        $where[] =
            '(
                u.name LIKE ?
                OR u.source_unit_name LIKE ?
                OR u.source_local_name LIKE ?
                OR u.source_designation LIKE ?
                OR u.source_local_designation LIKE ?
                OR u.source_manager_name LIKE ?
                OR u.source_local_manager LIKE ?
                OR o.name LIKE ?
            )';

        $like =
            '%' . $query . '%';

        for ($i = 0; $i < 8; $i++) {
            $params[] = $like;
        }
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    $count =
        $db->prepare(
            'SELECT COUNT(*)
             FROM reference_units u
             LEFT JOIN reference_organizations o
                ON o.id = u.organization_id
             WHERE '
             . $whereSql
        );

    $count->execute(
        $params
    );

    $total =
        (int) $count
            ->fetchColumn();

    $limit =
        (int) (
            $filters['limit']
            ?? 50
        );

    $page =
        (int) (
            $filters['page']
            ?? 1
        );

    $pages =
        max(
            1,
            (int) ceil(
                $total
                / max(
                    1,
                    $limit
                )
            )
        );

    $page =
        min(
            $pages,
            max(
                1,
                $page
            )
        );

    $offset =
        ($page - 1)
        * $limit;

    $stmt =
        $db->prepare(
            'SELECT
                u.*,
                o.name AS organization_name,
                o.short_name AS organization_short_name
             FROM reference_units u
             LEFT JOIN reference_organizations o
                ON o.id = u.organization_id
             WHERE '
             . $whereSql
             . '
             ORDER BY
                u.state_code ASC,
                u.name ASC,
                u.id ASC
             LIMIT '
             . $limit
             . '
             OFFSET '
             . $offset
        );

    $stmt->execute(
        $params
    );

    return [
        'rows' =>
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ),
        'total' =>
            $total,
        'page' =>
            $page,
        'pages' =>
            $pages,
        'limit' =>
            $limit,
    ];
}


function llama_pad_us_review_organizations(
    PDO $db,
    string $stateCode = ''
): array {
    $source =
        llama_pad_us_source(
            $db
        );

    $params = [
        (int) $source['id'],
    ];

    $where =
        'o.source_id = ?
         AND o.active = 1';

    if ($stateCode !== '') {
        $where .=
            ' AND (
                o.state_code = ?
                OR o.state_code IS NULL
                OR o.nationwide = 1
            )';

        $params[] =
            $stateCode;
    }

    $stmt =
        $db->prepare(
            'SELECT
                o.id,
                o.name,
                o.short_name
             FROM reference_organizations o
             WHERE '
             . $where
             . '
             ORDER BY o.name ASC'
        );

    $stmt->execute(
        $params
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function llama_pad_us_review_distinct(
    PDO $db,
    string $column,
    string $stateCode = ''
): array {
    $allowed = [
        'property_type_slug',
        'source_designation',
        'public_access',
    ];

    if (
        !in_array(
            $column,
            $allowed,
            true
        )
    ) {
        return [];
    }

    $source =
        llama_pad_us_source(
            $db
        );

    $params = [
        (int) $source['id'],
    ];

    $where =
        'source_id = ?
         AND active = 1
         AND '
        . $column
        . ' IS NOT NULL
         AND '
        . $column
        . ' <> ""';

    if ($stateCode !== '') {
        $where .=
            ' AND state_code = ?';

        $params[] =
            $stateCode;
    }

    $stmt =
        $db->prepare(
            'SELECT DISTINCT '
            . $column
            . ' AS value
             FROM reference_units
             WHERE '
             . $where
             . '
             ORDER BY '
             . $column
             . ' ASC'
        );

    $stmt->execute(
        $params
    );

    $values = [];

    while (
        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {
        $value =
            trim(
                (string) (
                    $row['value']
                    ?? ''
                )
            );

        if ($value !== '') {
            $values[] = $value;
        }
    }

    return $values;
}


function llama_pad_us_review_classifications(
    PDO $db,
    string $stateCode = ''
): array {
    $source =
        llama_pad_us_source(
            $db
        );

    $params = [
        (int) $source['id'],
    ];

    $where =
        'u.source_id = ?
         AND u.active = 1
         AND u.source_designation IS NOT NULL
         AND u.source_designation <> ""';

    if ($stateCode !== '') {
        $where .=
            ' AND u.state_code = ?';

        $params[] =
            $stateCode;
    }

    $stmt =
        $db->prepare(
            'SELECT
                u.source_designation,
                MIN(u.source_designation_code)
                    AS source_designation_code,
                COUNT(*) AS unit_count,
                SUM(
                    CASE
                        WHEN u.property_type_slug IS NULL
                          OR u.property_type_slug = ""
                        THEN 1
                        ELSE 0
                    END
                ) AS unmapped_count,
                MIN(
                    NULLIF(
                        u.property_type_slug,
                        ""
                    )
                ) AS property_type_slug,
                m.reviewed,
                m.surface_in_place_form,
                m.notes
             FROM reference_units u
             LEFT JOIN reference_property_type_mappings m
                ON m.source_id = u.source_id
               AND m.source_designation =
                    u.source_designation
               AND m.active = 1
             WHERE '
             . $where
             . '
             GROUP BY
                u.source_designation,
                m.reviewed,
                m.surface_in_place_form,
                m.notes
             ORDER BY
                unmapped_count DESC,
                unit_count DESC,
                u.source_designation ASC'
        );

    $stmt->execute(
        $params
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function llama_pad_us_review_url(
    string $path,
    array $params
): string {
    $clean = [];

    foreach (
        $params
        as $key => $value
    ) {
        if (
            $value === ''
            || $value === null
        ) {
            continue;
        }

        $clean[$key] =
            $value;
    }

    return
        $path
        . (
            $clean
                ? '?'
                    . http_build_query(
                        $clean,
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    )
                : ''
        );
}

<?php

declare(strict_types=1);


/*
 * =========================================================
 * PAD-US REVIEW / BROWSE HELPERS
 * =========================================================
 */


function llama_pad_us_review_states(): array
{
    return function_exists(
        'llama_pad_us_states'
    )
        ? llama_pad_us_states()
        : [];
}


function llama_pad_us_review_limit(
    mixed $value,
    int $default = 50,
    int $max = 200
): int {
    $limit =
        (int) $value;

    if ($limit < 1) {
        $limit =
            $default;
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


function llama_pad_us_review_metadata(
    mixed $raw
): array {
    if (!is_string($raw)) {
        return [];
    }

    $raw =
        trim(
            $raw
        );

    if ($raw === '') {
        return [];
    }

    $decoded =
        json_decode(
            $raw,
            true
        );

    return
        is_array($decoded)
            ? $decoded
            : [];
}


function llama_pad_us_review_issue_filters(
    array $input
): array {
    $states =
        llama_pad_us_review_states();

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

    $severity =
        strtolower(
            trim(
                (string) (
                    $input['severity']
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $severity,
            [
                '',
                'info',
                'warning',
                'error',
            ],
            true
        )
    ) {
        $severity = '';
    }

    $resolved =
        strtolower(
            trim(
                (string) (
                    $input['resolved']
                    ?? '0'
                )
            )
        );

    if (
        !in_array(
            $resolved,
            [
                '',
                '0',
                '1',
            ],
            true
        )
    ) {
        $resolved = '0';
    }

    return [
        'state' =>
            $stateCode,
        'severity' =>
            $severity,
        'resolved' =>
            $resolved,
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


function llama_pad_us_review_issues(
    PDO $db,
    array $filters
): array {
    $where = [
        'r.source_key = "pad-us"',
    ];

    $params = [];

    if (
        ($filters['state'] ?? '')
        !== ''
    ) {
        $where[] =
            'i.state_code = ?';

        $params[] =
            $filters['state'];
    }

    if (
        ($filters['severity'] ?? '')
        !== ''
    ) {
        $where[] =
            'i.severity = ?';

        $params[] =
            $filters['severity'];
    }

    if (
        ($filters['resolved'] ?? '')
        !== ''
    ) {
        $where[] =
            'i.resolved = ?';

        $params[] =
            (int) $filters['resolved'];
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
                i.message LIKE ?
                OR i.issue_type LIKE ?
                OR i.source_record_id LIKE ?
                OR i.source_data_json LIKE ?
            )';

        $like =
            '%' . $query . '%';

        array_push(
            $params,
            $like,
            $like,
            $like,
            $like
        );
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    $count =
        $db->prepare(
            'SELECT COUNT(*)
             FROM place_taxonomy_import_issues i
             INNER JOIN place_taxonomy_import_runs r
                ON r.id = i.import_run_id
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
                i.*,
                r.source_version,
                r.created_at AS run_created_at
             FROM place_taxonomy_import_issues i
             INNER JOIN place_taxonomy_import_runs r
                ON r.id = i.import_run_id
             WHERE '
             . $whereSql
             . '
             ORDER BY
                i.resolved ASC,
                FIELD(
                    i.severity,
                    "error",
                    "warning",
                    "info"
                ),
                i.id DESC
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


function llama_pad_us_review_unit_filters(
    array $input
): array {
    $states =
        llama_pad_us_review_states();

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
        'property_type_id' =>
            max(
                0,
                (int) (
                    $input[
                        'property_type_id'
                    ]
                    ?? 0
                )
            ),
        'organization_id' =>
            max(
                0,
                (int) (
                    $input[
                        'organization_id'
                    ]
                    ?? 0
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


function llama_pad_us_review_property_types(
    PDO $db
): array {
    $stmt =
        $db->query(
            'SELECT
                id,
                name
             FROM place_property_types
             WHERE active = 1
             ORDER BY
                sort_order ASC,
                name ASC'
        );

    return
        $stmt
            ? $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            : [];
}


function llama_pad_us_review_organizations(
    PDO $db,
    string $stateCode = ''
): array {
    $params = [];

    $where =
        'WHERE o.active = 1';

    if ($stateCode !== '') {
        $where .=
            ' AND (
                o.nationwide = 1
                OR EXISTS (
                    SELECT 1
                    FROM place_organization_states os
                    WHERE os.organization_id = o.id
                      AND os.state_code = ?
                      AND os.active = 1
                )
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
             FROM place_organizations o
             '
             . $where
             . '
             ORDER BY
                o.name ASC'
        );

    $stmt->execute(
        $params
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function llama_pad_us_review_units(
    PDO $db,
    array $filters
): array {
    $where = [
        'l.source_name = "PAD-US"',
        'l.active = 1',
    ];

    $params = [];

    if (
        ($filters['state'] ?? '')
        !== ''
    ) {
        $where[] =
            'l.state_code = ?';

        $params[] =
            $filters['state'];
    }

    if (
        (int) (
            $filters[
                'property_type_id'
            ]
            ?? 0
        ) > 0
    ) {
        $where[] =
            'l.property_type_id = ?';

        $params[] =
            (int) $filters[
                'property_type_id'
            ];
    }

    if (
        (int) (
            $filters[
                'organization_id'
            ]
            ?? 0
        ) > 0
    ) {
        $where[] =
            'l.organization_id = ?';

        $params[] =
            (int) $filters[
                'organization_id'
            ];
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
                l.name LIKE ?
                OR o.name LIKE ?
                OR o.short_name LIKE ?
                OR pt.name LIKE ?
                OR l.source_external_id LIKE ?
                OR l.metadata_json LIKE ?
            )';

        $like =
            '%' . $query . '%';

        array_push(
            $params,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like
        );
    }

    $access =
        trim(
            (string) (
                $filters['access']
                ?? ''
            )
        );

    if ($access !== '') {
        $where[] =
            'JSON_UNQUOTE(
                JSON_EXTRACT(
                    l.metadata_json,
                    "$.pad_us.public_access"
                )
             ) = ?';

        $params[] =
            $access;
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    $count =
        $db->prepare(
            'SELECT COUNT(*)
             FROM place_organization_locations l
             LEFT JOIN place_organizations o
                ON o.id = l.organization_id
             LEFT JOIN place_property_types pt
                ON pt.id = l.property_type_id
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
                l.*,
                o.name AS organization_name,
                o.short_name AS organization_short_name,
                pt.name AS property_type_name,
                pt.slug AS property_type_slug
             FROM place_organization_locations l
             LEFT JOIN place_organizations o
                ON o.id = l.organization_id
             LEFT JOIN place_property_types pt
                ON pt.id = l.property_type_id
             WHERE '
             . $whereSql
             . '
             ORDER BY
                l.state_code ASC,
                l.name ASC,
                l.id ASC
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


function llama_pad_us_review_access_values(
    PDO $db,
    string $stateCode = ''
): array {
    $params = [];

    $where =
        'source_name = "PAD-US"
         AND active = 1';

    if ($stateCode !== '') {
        $where .=
            ' AND state_code = ?';

        $params[] =
            $stateCode;
    }

    try {
        $stmt =
            $db->prepare(
                'SELECT DISTINCT
                    JSON_UNQUOTE(
                        JSON_EXTRACT(
                            metadata_json,
                            "$.pad_us.public_access"
                        )
                    ) AS access_value
                 FROM place_organization_locations
                 WHERE '
                 . $where
                 . '
                 ORDER BY access_value ASC'
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
                        $row[
                            'access_value'
                        ]
                        ?? ''
                    )
                );

            if (
                $value !== ''
                && strtolower(
                    $value
                ) !== 'null'
            ) {
                $values[] =
                    $value;
            }
        }

        return $values;

    } catch (Throwable) {
        return [];
    }
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

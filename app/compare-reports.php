<?php

declare(strict_types=1);

require_once __DIR__ . '/compare-places.php';
require_once __DIR__ . '/place-report.php';


const LLAMA_COMPARE_MIN_REPORTS = 2;
const LLAMA_COMPARE_MAX_REPORTS = 4;


/* =========================================================
   REQUESTS
   ========================================================= */

function llama_compare_report_slug(
    mixed $value
): string {
    $slug =
        strtolower(
            trim(
                (string) $value
            )
        );

    if (
        $slug === ''
        || !preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $slug
        )
    ) {
        return '';
    }

    return $slug;
}


function llama_compare_requested_report_keys(
    mixed $value
): array {
    if (is_string($value)) {
        $value = explode(
            ',',
            $value
        );
    }

    if (!is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key) {
        $key =
            strtolower(
                trim(
                    (string) $key
                )
            );

        if (
            !preg_match(
                '/^(submission|update)-[1-9][0-9]*$/',
                $key
            )
        ) {
            continue;
        }

        if (
            !in_array(
                $key,
                $keys,
                true
            )
        ) {
            $keys[] = $key;
        }

        if (
            count($keys)
            >= LLAMA_COMPARE_MAX_REPORTS
        ) {
            break;
        }
    }

    return $keys;
}


function llama_compare_reports_url(
    string $slug,
    array $keys = []
): string {
    $slug =
        llama_compare_report_slug(
            $slug
        );

    if ($slug === '') {
        return
            'https://llamascout.com/compare.php?mode=reports';
    }

    $url =
        'https://llamascout.com/compare.php?mode=reports&place='
        . rawurlencode(
            $slug
        );

    $keys =
        llama_compare_requested_report_keys(
            $keys
        );

    if ($keys) {
        $url .=
            '&reports='
            . rawurlencode(
                implode(
                    ',',
                    $keys
                )
            );
    }

    return $url;
}


/* =========================================================
   SMALL HELPERS
   ========================================================= */

function llama_compare_report_decode_json(
    mixed $value
): array {
    if (is_array($value)) {
        return $value;
    }

    if (
        !is_string($value)
        || trim($value) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $value,
            true
        );

    return
        is_array($decoded)
            ? $decoded
            : [];
}


function llama_compare_report_date_label(
    mixed $value
): string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return 'Date not recorded';
    }

    try {
        $date =
            new DateTimeImmutable(
                $value
            );

        return
            $date->format(
                'M j, Y'
            );
    } catch (Throwable $exception) {
        return $value;
    }
}


function llama_compare_report_role_label(
    mixed $value
): string {
    $role =
        strtolower(
            trim(
                (string) $value
            )
        );

    return match ($role) {
        'master-scout',
        'master_scout' =>
            'Master Scout',

        'scout' =>
            'Scout',

        'owner' =>
            'Owner',

        'admin' =>
            'Admin',

        'member',
        'user' =>
            'Member',

        default =>
            $role !== ''
                ? ucwords(
                    str_replace(
                        [
                            '_',
                            '-',
                        ],
                        ' ',
                        $role
                    )
                )
                : 'Contributor',
    };
}


function llama_compare_report_contributor_name(
    array $row
): string {
    $name =
        trim(
            (string) (
                $row['contributor_name']
                ?? ''
            )
        );

    return
        $name !== ''
            ? $name
            : 'Former Llama Scout Member';
}


function llama_compare_report_latest_unknown_fields(
    array $row
): array {
    $history =
        llama_compare_report_decode_json(
            $row['revision_history']
            ?? null
        );

    for (
        $index = count($history) - 1;
        $index >= 0;
        $index--
    ) {
        $event =
            $history[$index]
            ?? null;

        if (
            !is_array($event)
            || !array_key_exists(
                'unknown_fields',
                $event
            )
        ) {
            continue;
        }

        $unknown =
            is_array(
                $event['unknown_fields']
            )
                ? $event['unknown_fields']
                : [];

        return
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn (
                                mixed $value
                            ): string =>
                                trim(
                                    (string) $value
                                ),
                            $unknown
                        ),
                        static fn (
                            string $value
                        ): bool =>
                            $value !== ''
                    )
                )
            );
    }

    return [];
}


function llama_compare_report_field_key_for_storage(
    string $storage
): string {
    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        if (
            (string) (
                $field['storage']
                ?? ''
            ) === $storage
        ) {
            return
                (string) $fieldKey;
        }
    }

    return '';
}


function llama_compare_report_apply_answer_state(
    array &$snapshot,
    array $proposed,
    array $explicitUnknownFields
): void {
    $unknownLookup =
        array_fill_keys(
            llama_place_report_unknown_fields(
                $snapshot
            ),
            true
        );

    $submittedUnknownLookup =
        array_fill_keys(
            array_map(
                'strval',
                $explicitUnknownFields
            ),
            true
        );

    foreach ($proposed as $storage => $_value) {
        $fieldKey =
            llama_compare_report_field_key_for_storage(
                (string) $storage
            );

        if ($fieldKey === '') {
            continue;
        }

        unset(
            $unknownLookup[$fieldKey]
        );

        if (
            isset(
                $submittedUnknownLookup[
                    $fieldKey
                ]
            )
        ) {
            $unknownLookup[$fieldKey] =
                true;
        }
    }

    $snapshot['_answer_state'] =
        array_values(
            array_keys(
                $unknownLookup
            )
        );
}


/* =========================================================
   BUILD HISTORICAL REPORT VERSIONS
   ========================================================= */

function llama_compare_report_versions(
    string $slug
): array {
    $slug =
        llama_compare_report_slug(
            $slug
        );

    if ($slug === '') {
        return [];
    }

    $place =
        place_member_by_slug(
            $slug
        );

    if (!$place) {
        return [];
    }

    $placeId =
        (int) (
            $place['id']
            ?? 0
        );

    if ($placeId < 1) {
        return [];
    }

    $db =
        db();

    /*
     * New Place submissions retain their complete submission_data
     * after approval. That gives us the first full historical report.
     */
    $initialStmt =
        $db->prepare(
            'SELECT
                ps.*,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Former Llama Scout Member"
                ) AS contributor_name,
                u.username AS contributor_username
             FROM place_submissions ps
             LEFT JOIN users u
                ON u.id = ps.user_id
             WHERE ps.place_id = ?
               AND ps.status = "approved"
             ORDER BY
                COALESCE(
                    ps.reviewed_at,
                    ps.submitted_at
                ) ASC,
                ps.id ASC
             LIMIT 1'
        );

    $initialStmt->execute([
        $placeId,
    ]);

    $initial =
        $initialStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$initial) {
        return [];
    }

    $snapshot =
        llama_compare_report_decode_json(
            $initial['submission_data']
            ?? null
        );

    if (!$snapshot) {
        return [];
    }

    $versions = [];

    $initialVisitedAt =
        trim(
            (string) (
                $snapshot['visited_at']
                ?? ''
            )
        );

    $versions[] = [
        'key' =>
            'submission-'
            . (int) $initial['id'],

        'kind' =>
            'initial',

        'label' =>
            'Initial report',

        'contributor_name' =>
            llama_compare_report_contributor_name(
                $initial
            ),

        'contributor_username' =>
            (string) (
                $initial['contributor_username']
                ?? ''
            ),

        'role_at_time' =>
            (string) (
                $initial['role_at_submission']
                ?? ''
            ),

        'visited_at' =>
            $initialVisitedAt,

        'report_date' =>
            $initialVisitedAt !== ''
                ? $initialVisitedAt
                : (
                    $initial['submitted_at']
                    ?? $initial['reviewed_at']
                    ?? ''
                ),

        'reviewed_at' =>
            (string) (
                $initial['reviewed_at']
                ?? ''
            ),

        'changed_paths' =>
            array_values(
                array_map(
                    static fn (
                        array $field
                    ): string =>
                        (string) (
                            $field['storage']
                            ?? ''
                        ),
                    array_values(
                        llama_place_report_fields()
                    )
                )
            ),

        'data' =>
            $snapshot,
    ];

    /*
     * Approved updates preserve original_values and proposed_changes.
     * Apply them in approval order to recreate the published report
     * exactly as it stood after each approved contribution.
     */
    $updateStmt =
        $db->prepare(
            'SELECT
                pus.*,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Former Llama Scout Member"
                ) AS contributor_name,
                u.username AS contributor_username
             FROM place_update_submissions pus
             LEFT JOIN users u
                ON u.id = pus.user_id
             WHERE pus.place_id = ?
               AND pus.status = "approved"
             ORDER BY
                COALESCE(
                    pus.reviewed_at,
                    pus.submitted_at
                ) ASC,
                pus.id ASC'
        );

    $updateStmt->execute([
        $placeId,
    ]);

    $updates =
        $updateStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    foreach ($updates as $update) {
        $proposed =
            llama_compare_report_decode_json(
                $update['proposed_changes']
                ?? null
            );

        if (!$proposed) {
            continue;
        }

        foreach (
            $proposed
            as $storage => $value
        ) {
            llama_place_report_set_path(
                $snapshot,
                (string) $storage,
                $value
            );
        }

        llama_compare_report_apply_answer_state(
            $snapshot,
            $proposed,
            llama_compare_report_latest_unknown_fields(
                $update
            )
        );

        $visitedAt =
            trim(
                (string) (
                    $update['visited_at']
                    ?? ''
                )
            );

        if ($visitedAt !== '') {
            $snapshot['visited_at'] =
                substr(
                    $visitedAt,
                    0,
                    10
                );
        }

        $versions[] = [
            'key' =>
                'update-'
                . (int) $update['id'],

            'kind' =>
                'update',

            'label' =>
                'Approved update',

            'contributor_name' =>
                llama_compare_report_contributor_name(
                    $update
                ),

            'contributor_username' =>
                (string) (
                    $update['contributor_username']
                    ?? ''
                ),

            'role_at_time' =>
                (string) (
                    $update['role_at_submission']
                    ?? ''
                ),

            'visited_at' =>
                $visitedAt,

            'report_date' =>
                $visitedAt !== ''
                    ? $visitedAt
                    : (
                        $update['submitted_at']
                        ?? $update['reviewed_at']
                        ?? ''
                    ),

            'reviewed_at' =>
                (string) (
                    $update['reviewed_at']
                    ?? ''
                ),

            'changed_paths' =>
                array_values(
                    array_map(
                        'strval',
                        array_keys(
                            $proposed
                        )
                    )
                ),

            'data' =>
                $snapshot,
        ];
    }

    /*
     * Picker/history reads newest first. The reconstruction itself
     * was intentionally done oldest to newest above.
     */
    $versions =
        array_reverse(
            $versions
        );

    if ($versions) {
        $versions[0]['is_latest'] =
            true;
    }

    foreach ($versions as $index => &$version) {
        if ($index !== 0) {
            $version['is_latest'] =
                false;
        }
    }
    unset($version);

    return $versions;
}


/* =========================================================
   SELECTION
   ========================================================= */

function llama_compare_reports_by_keys(
    array $versions,
    array $keys
): array {
    $keys =
        llama_compare_requested_report_keys(
            $keys
        );

    if (!$keys) {
        return [];
    }

    $lookup = [];

    foreach ($versions as $version) {
        $key =
            (string) (
                $version['key']
                ?? ''
            );

        if ($key !== '') {
            $lookup[$key] =
                $version;
        }
    }

    $selected = [];

    foreach ($keys as $key) {
        if (
            isset(
                $lookup[$key]
            )
        ) {
            $selected[] =
                $lookup[$key];
        }
    }

    return $selected;
}


function llama_compare_report_row_was_updated(
    array $report,
    array $row
): bool {
    if (
        (string) (
            $report['kind']
            ?? ''
        ) === 'initial'
    ) {
        return true;
    }

    $path =
        $row['path']
        ?? null;

    if (!is_array($path)) {
        return false;
    }

    $storage =
        implode(
            '.',
            array_map(
                'strval',
                $path
            )
        );

    return
        in_array(
            $storage,
            is_array(
                $report['changed_paths']
                ?? null
            )
                ? $report['changed_paths']
                : [],
            true
        );
}

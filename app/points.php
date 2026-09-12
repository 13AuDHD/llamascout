<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';

/* =========================================================
   LLAMA SCOUT POINTS

   points_policy remains the single configuration source for
   point VALUES. Place Report field membership comes from the
   shared Place Report schema.
   ========================================================= */

function llama_points_policy(
    PDO $db,
    string $key,
    int $default = 0
): int {
    try {
        $stmt = $db->prepare(
            'SELECT points_value
             FROM points_policy
             WHERE policy_key = ?
             LIMIT 1'
        );

        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false
            ? $default
            : (int) $value;
    } catch (Throwable) {
        return $default;
    }
}

function llama_points_policy_required(
    PDO $db,
    string $key
): int {
    $stmt = $db->prepare(
        'SELECT points_value
         FROM points_policy
         WHERE policy_key = ?
         LIMIT 1'
    );

    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        throw new RuntimeException(
            'Points policy setting "' . $key . '" is not configured.'
        );
    }

    return max(0, (int) $value);
}

function llama_points_total(
    PDO $db,
    int $userId
): int {
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points), 0)
             FROM points_ledger
             WHERE user_id = ?'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points_awarded), 0)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}

function llama_points_record(
    PDO $db,
    int $userId,
    int $points,
    string $sourceType,
    ?int $sourceId,
    string $reason,
    ?int $awardedBy = null,
    ?int $contributionId = null
): int {
    if ($points === 0) {
        return 0;
    }

    if ($contributionId) {
        $exists = $db->prepare(
            'SELECT id
             FROM points_ledger
             WHERE contribution_id = ?
             LIMIT 1'
        );

        $exists->execute([$contributionId]);

        $existingId =
            (int) ($exists->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO points_ledger (
            user_id,
            points,
            source_type,
            source_id,
            contribution_id,
            reason,
            awarded_by,
            created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $stmt->execute([
        $userId,
        $points,
        $sourceType,
        $sourceId,
        $contributionId,
        $reason,
        $awardedBy,
    ]);

    return (int) $db->lastInsertId();
}


/* =========================================================
   NEW PLACE SCORING POLICY

   Category names, mode, and field membership are derived from
   app/place-report.php. The point amount for each category is
   still loaded from Admin Points / points_policy.
   ========================================================= */

function llama_points_new_place_categories(): array
{
    $categories =
        llama_place_report_category_definitions();

    $fields =
        llama_place_report_fields();

    foreach ($categories as $slug => &$category) {
        $category['fields'] = [];

        foreach ($fields as $fieldKey => $field) {
            if (
                in_array(
                    $slug,
                    (array) (
                        $field['points_categories']
                        ?? []
                    ),
                    true
                )
            ) {
                $category['fields'][] =
                    $fieldKey;
            }
        }
    }
    unset($category);

    return $categories;
}

function llama_points_has_answer(
    array $data,
    string $key
): bool {
    return llama_place_report_is_answered_input(
        $data,
        $key
    );
}

function llama_points_new_place_max_points(
    PDO $db
): int {
    $total = 0;

    foreach (
        llama_points_new_place_categories()
        as $category
    ) {
        $total +=
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );
    }

    return $total;
}

function llama_points_estimate_new_place(
    PDO $db,
    array $data,
    int $photoCount
): array {
    $categoryRows = [];
    $estimatedPoints = 0;
    $maxPoints = 0;
    $answeredTotal = 0;
    $fieldTotal = 0;

    foreach (
        llama_points_new_place_categories()
        as $slug => $category
    ) {
        $fields =
            (array) $category['fields'];

        $answered = 0;

        foreach ($fields as $field) {
            if (
                llama_points_has_answer(
                    $data,
                    (string) $field
                )
            ) {
                $answered++;
            }
        }

        $fieldCount =
            count($fields);

        $fieldTotal +=
            $fieldCount;

        $answeredTotal +=
            $answered;

        $categoryMax =
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );

        $maxPoints +=
            $categoryMax;

        if (
            (string) $category['mode']
            === 'any'
        ) {
            $points =
                $answered > 0
                    ? $categoryMax
                    : 0;
        } else {
            $points =
                $fieldCount > 0
                    ? (int) round(
                        $categoryMax
                        * ($answered / $fieldCount)
                    )
                    : 0;
        }

        $points =
            max(
                0,
                min(
                    $categoryMax,
                    $points
                )
            );

        $estimatedPoints +=
            $points;

        $categoryRows[] = [
            'slug' => $slug,
            'label' =>
                (string) $category['label'],
            'policy_key' =>
                (string) $category['policy_key'],
            'mode' =>
                (string) $category['mode'],
            'answered' =>
                $answered,
            'total' =>
                $fieldCount,
            'points' =>
                $points,
            'max_points' =>
                $categoryMax,
            'started' =>
                $answered > 0,
        ];
    }

    $pointFieldLookup = [];

    foreach (
        llama_points_new_place_categories()
        as $category
    ) {
        foreach (
            (array) $category['fields']
            as $fieldKey
        ) {
            $pointFieldLookup[
                (string) $fieldKey
            ] = true;
        }
    }

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        if (
            isset(
                $pointFieldLookup[$fieldKey]
            )
        ) {
            continue;
        }

        $fieldTotal++;

        if (
            llama_points_has_answer(
                $data,
                $fieldKey
            )
        ) {
            $answeredTotal++;
        }
    }

    /*
     * Photo presence is included in form completion, but not in
     * contribution points.
     */
    $fieldTotal++;

    if ($photoCount > 0) {
        $answeredTotal++;
    }

    $missingMinimum = [];

    if (
        !llama_points_has_answer(
            $data,
            'name'
        )
    ) {
        $missingMinimum[] =
            'Basic information';
    }

    if (
        !llama_points_has_answer(
            $data,
            'latitude'
        )
        ||
        !llama_points_has_answer(
            $data,
            'longitude'
        )
    ) {
        $missingMinimum[] =
            'Location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] =
            '1 photo';
    }

    return [
        'completion_percent' =>
            $fieldTotal > 0
                ? (int) round(
                    100
                    * (
                        $answeredTotal
                        / $fieldTotal
                    )
                )
                : 0,

        'estimated_points' =>
            max(
                0,
                min(
                    $maxPoints,
                    $estimatedPoints
                )
            ),

        'max_points' =>
            $maxPoints,

        'categories_started' =>
            count(
                array_filter(
                    $categoryRows,
                    static fn (
                        array $row
                    ): bool =>
                        !empty(
                            $row['started']
                        )
                )
            ),

        'category_count' =>
            count(
                $categoryRows
            ),

        'categories' =>
            $categoryRows,

        'minimum_ready' =>
            !$missingMinimum,

        'missing_minimum' =>
            $missingMinimum,
    ];
}


/* =========================================================
   PLACE UPDATE SCORING POLICY

   Updates use the same Place Report category membership, but
   every category is weighted by the specific fields changed.
   "Any information" categories used by New Places do not get
   an all-or-nothing award here.

   A legitimate approved change to one scored field earns at
   least 1 point when that category has a nonzero policy value.
   This includes answer-state improvements such as Unknown ->
   a measured value, because those changes are present in the
   proposed-change map.
   ========================================================= */

function llama_points_place_update_categories(): array
{
    $categories =
        llama_place_report_category_definitions();

    $fields =
        llama_place_report_fields();

    foreach ($categories as $slug => &$category) {
        $category['policy_key'] =
            'place_update_' . $slug;

        $category['mode'] =
            'weighted';

        $category['fields'] = [];

        foreach ($fields as $fieldKey => $field) {
            if (
                in_array(
                    $slug,
                    (array) (
                        $field['points_categories']
                        ?? []
                    ),
                    true
                )
                && (string) (
                    $field['type']
                    ?? ''
                ) !== 'derived'
            ) {
                $category['fields'][] =
                    (string) $fieldKey;
            }
        }
    }
    unset($category);

    return $categories;
}

function llama_points_place_update_max_points(
    PDO $db
): int {
    $total = 0;

    foreach (
        llama_points_place_update_categories()
        as $category
    ) {
        $total +=
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );
    }

    return $total;
}

function llama_points_estimate_place_update(
    PDO $db,
    array $proposedChanges
): array {
    $fields =
        llama_place_report_fields();

    $changedStorageLookup =
        array_fill_keys(
            array_map(
                'strval',
                array_keys(
                    $proposedChanges
                )
            ),
            true
        );

    $scoredStorageLookup = [];
    $categoryRows = [];
    $estimatedPoints = 0;
    $maxPoints = 0;
    $scoredChangedFields = 0;

    foreach (
        llama_points_place_update_categories()
        as $slug => $category
    ) {
        $eligibleFieldKeys =
            (array) (
                $category['fields']
                ?? []
            );

        $eligibleStorage = [];
        $changed = 0;

        foreach ($eligibleFieldKeys as $fieldKey) {
            $field =
                $fields[(string) $fieldKey]
                ?? null;

            if (!$field) {
                continue;
            }

            $storage =
                trim(
                    (string) (
                        $field['storage']
                        ?? ''
                    )
                );

            if (
                $storage === ''
                || str_starts_with(
                    $storage,
                    'computed.'
                )
            ) {
                continue;
            }

            $eligibleStorage[$storage] =
                true;

            $scoredStorageLookup[$storage] =
                true;

            if (
                isset(
                    $changedStorageLookup[$storage]
                )
            ) {
                $changed++;
            }
        }

        $fieldCount =
            count($eligibleStorage);

        $categoryMax =
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );

        $maxPoints +=
            $categoryMax;

        $points = 0;

        if (
            $changed > 0
            && $fieldCount > 0
            && $categoryMax > 0
        ) {
            $points =
                (int) round(
                    $categoryMax
                    * (
                        $changed
                        / $fieldCount
                    )
                );

            /*
             * A real, moderator-approved single-field improvement
             * should still earn something even in a large category.
             */
            $points =
                max(
                    1,
                    $points
                );

            $points =
                min(
                    $categoryMax,
                    $points
                );
        }

        $estimatedPoints +=
            $points;

        $scoredChangedFields +=
            $changed;

        $categoryRows[] = [
            'slug' =>
                (string) $slug,
            'label' =>
                (string) (
                    $category['label']
                    ?? $slug
                ),
            'policy_key' =>
                (string) $category['policy_key'],
            'mode' =>
                'weighted',
            'changed' =>
                $changed,
            'total' =>
                $fieldCount,
            'points' =>
                $points,
            'max_points' =>
                $categoryMax,
            'started' =>
                $changed > 0,
        ];
    }

    $unscoredChangedFields = 0;

    foreach (
        array_keys(
            $changedStorageLookup
        )
        as $storage
    ) {
        if (
            !isset(
                $scoredStorageLookup[
                    (string) $storage
                ]
            )
        ) {
            $unscoredChangedFields++;
        }
    }

    return [
        'estimated_points' =>
            max(
                0,
                min(
                    $maxPoints,
                    $estimatedPoints
                )
            ),

        'max_points' =>
            $maxPoints,

        'changed_fields' =>
            count(
                $changedStorageLookup
            ),

        'scored_changed_fields' =>
            $scoredChangedFields,

        'unscored_changed_fields' =>
            $unscoredChangedFields,

        'categories_started' =>
            count(
                array_filter(
                    $categoryRows,
                    static fn (
                        array $row
                    ): bool =>
                        !empty(
                            $row['started']
                        )
                )
            ),

        'category_count' =>
            count(
                $categoryRows
            ),

        'categories' =>
            $categoryRows,
    ];
}

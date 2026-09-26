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

function llama_points_standalone_place_fields(): array
{
    return [
        'description' => [
            'label' => 'Description',
            'new_policy_key' => 'new_place_description',
            'update_policy_key' => 'place_update_description',
        ],
        'access_summary' => [
            'label' => 'Access Summary',
            'new_policy_key' => 'new_place_access_summary',
            'update_policy_key' => 'place_update_access_summary',
        ],
        'sensory_summary' => [
            'label' => 'Sensory Summary',
            'new_policy_key' => 'new_place_sensory_summary',
            'update_policy_key' => 'place_update_sensory_summary',
        ],
        'not_recommended_for' => [
            'label' => 'Not Recommended For',
            'new_policy_key' => 'new_place_not_recommended_for',
            'update_policy_key' => 'place_update_not_recommended_for',
        ],
        'seasonal_access_note' => [
            'label' => 'Seasonal Access Notes',
            'new_policy_key' => 'new_place_seasonal_access_note',
            'update_policy_key' => 'place_update_seasonal_access_note',
        ],
        'current_fire_restrictions_url' => [
            'label' => 'Current Fire Restrictions URL',
            'new_policy_key' => 'new_place_current_fire_restrictions_url',
            'update_policy_key' => 'place_update_current_fire_restrictions_url',
        ],
    ];
}

function llama_points_optional_new_place_fields(): array
{
    return [
        'connectivity_starlink_note' => true,
        'scout_note_1' => true,
        'scout_note_2' => true,
        'scout_note_3' => true,
        'contributor_notes' => true,
    ];
}

function llama_points_new_place_categories(): array
{
    $categories =
        llama_place_report_category_definitions();

    $fields =
        llama_place_report_fields();

    $standaloneFields =
        llama_points_standalone_place_fields();

    $optionalFields =
        llama_points_optional_new_place_fields();

    foreach ($categories as $slug => &$category) {
        $category['fields'] = [];

        foreach ($fields as $fieldKey => $field) {
            $fieldKey =
                (string) $fieldKey;

            /*
             * Standalone point fields receive their own configured
             * point values and must never dilute a category.
             */
            if (isset($standaloneFields[$fieldKey])) {
                continue;
            }

            if (isset($optionalFields[$fieldKey])) {
                continue;
            }

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
    $fields =
        llama_place_report_fields();

    $field =
        $fields[$key]
        ?? null;

    /*
     * Amenity checkboxes are one observed yes/no set. The Add Place form says
     * explicitly that an unchecked amenity means it was not present. Raw HTML
     * submits only checked boxes, however, so the old estimator interpreted
     * every unchecked amenity as "unanswered" even after the contributor had
     * completed the section.
     *
     * Once any checkbox in that same section is present, the section has been
     * answered and the other unchecked boxes are legitimate No answers. This
     * also fixes amenity_none inside Safety + Warnings, which was silently
     * costing a fully completed report a point whenever actual amenities were
     * present.
     */
    if (
        is_array($field)
        && (string) ($field['type'] ?? '') === 'checkbox'
        && !array_key_exists($key, $data)
    ) {
        $section =
            (string) ($field['section'] ?? '');

        foreach ($fields as $otherKey => $otherField) {
            if (
                (string) ($otherField['type'] ?? '') !== 'checkbox'
                || (string) ($otherField['section'] ?? '') !== $section
            ) {
                continue;
            }

            if (array_key_exists((string) $otherKey, $data)) {
                return true;
            }
        }

        return false;
    }

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

    foreach (
        llama_points_standalone_place_fields()
        as $field
    ) {
        $total +=
            llama_points_policy_required(
                $db,
                (string) $field['new_policy_key']
            );
    }

    return $total;
}


/* =========================================================
   PLACE REPORT COMPLETENESS

   This measures how much of the shared structured report has
   actually been answered. Deliberate Unknown answers count as
   completed observations. Photo presence counts once. Fields
   excluded from new-Place scoring remain excluded here too so
   the percentage matches the contribution system members see.
   ========================================================= */

function llama_place_report_completion_summary(
    array $data,
    int $photoCount = 0
): array {
    $answeredTotal = 0;
    $fieldTotal = 0;
    $pointFieldLookup = [];

    foreach (
        llama_points_new_place_categories()
        as $category
    ) {
        foreach (
            (array) $category['fields']
            as $fieldKey
        ) {
            $fieldKey = (string) $fieldKey;
            $pointFieldLookup[$fieldKey] = true;
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
    }

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        if (isset($pointFieldLookup[$fieldKey])) {
            continue;
        }

        if (
            isset(
                llama_points_optional_new_place_fields()[
                    (string) $fieldKey
                ]
            )
        ) {
            continue;
        }

        $fieldTotal++;

        if (
            llama_points_has_answer(
                $data,
                (string) $fieldKey
            )
        ) {
            $answeredTotal++;
        }
    }

    $fieldTotal++;

    if ($photoCount > 0) {
        $answeredTotal++;
    }

    $missingMinimum = [];

    if (!llama_points_has_answer($data, 'name')) {
        $missingMinimum[] = 'Place name';
    }

    if (
        !llama_points_has_answer($data, 'latitude')
        || !llama_points_has_answer($data, 'longitude')
    ) {
        $missingMinimum[] = 'Exact location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] = '1 current photo';
    }

    return [
        'answered' => $answeredTotal,
        'total' => $fieldTotal,
        'percent' =>
            $fieldTotal > 0
                ? (int) round(
                    100 * ($answeredTotal / $fieldTotal)
                )
                : 0,
        'missing_minimum' => $missingMinimum,
        'minimum_met' => !$missingMinimum,
    ];
}

function llama_points_estimate_new_place(
    PDO $db,
    array $data,
    int $photoCount
): array {
    $categoryRows = [];
    $standaloneRows = [];

    $estimatedPoints = 0;
    $maxPoints = 0;

    /*
     * =====================================================
     * NORMAL CATEGORY SCORING
     * =====================================================
     *
     * Amenities and Connectivity retain their "any"
     * behavior. Every other category is proportional.
     */
    $categories =
        llama_points_new_place_categories();

    foreach (
        $categories
        as $slug => $category
    ) {
        $fields =
            (array) (
                $category['fields']
                ?? []
            );

        $answered = 0;

        foreach ($fields as $fieldKey) {
            if (
                llama_points_has_answer(
                    $data,
                    (string) $fieldKey
                )
            ) {
                $answered++;
            }
        }

        $fieldCount =
            count($fields);

        $categoryMax =
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );

        $maxPoints +=
            $categoryMax;

        if (
            (string) (
                $category['mode']
                ?? 'weighted'
            ) === 'any'
        ) {
            /*
             * Amenities:
             * Any amenity OR No Amenities = full points.
             *
             * Connectivity:
             * Any one tested carrier/service = full points.
             */
            $points =
                $answered > 0
                    ? $categoryMax
                    : 0;
        } else {
            $points =
                $fieldCount > 0
                    ? (int) round(
                        $categoryMax
                        * (
                            $answered
                            / $fieldCount
                        )
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
                (string) (
                    $category['mode']
                    ?? 'weighted'
                ),

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


    /*
     * =====================================================
     * STANDALONE FIELD SCORING
     * =====================================================
     *
     * These six fields have their own configured point
     * values and are not divided into another category.
     */
    foreach (
        llama_points_standalone_place_fields()
        as $fieldKey => $definition
    ) {
        $answered =
            llama_points_has_answer(
                $data,
                (string) $fieldKey
            );

        $fieldMax =
            llama_points_policy_required(
                $db,
                (string) $definition['new_policy_key']
            );

        $points =
            $answered
                ? $fieldMax
                : 0;

        $maxPoints +=
            $fieldMax;

        $estimatedPoints +=
            $points;

        $standaloneRows[] = [
            'field' =>
                (string) $fieldKey,

            'label' =>
                (string) $definition['label'],

            'policy_key' =>
                (string) $definition['new_policy_key'],

            'answered' =>
                $answered ? 1 : 0,

            'total' =>
                1,

            'points' =>
                $points,

            'max_points' =>
                $fieldMax,
        ];
    }


    /*
     * =====================================================
     * COMPLETION PERCENTAGE
     * =====================================================
     *
     * Count each actual form question only once even when a
     * field participates in more than one point category.
     */
    $completionFields = [];

    foreach ($categories as $category) {
        foreach (
            (array) (
                $category['fields']
                ?? []
            )
            as $fieldKey
        ) {
            $completionFields[
                (string) $fieldKey
            ] = true;
        }
    }

    foreach (
        llama_points_standalone_place_fields()
        as $fieldKey => $_
    ) {
        $completionFields[
            (string) $fieldKey
        ] = true;
    }

    $optionalFields =
        llama_points_optional_new_place_fields();

    /*
     * Fields outside the points system still count toward
     * completion unless they are explicitly optional.
     */
    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $fieldKey =
            (string) $fieldKey;

        if (
            isset(
                $optionalFields[
                    $fieldKey
                ]
            )
        ) {
            continue;
        }

        $completionFields[
            $fieldKey
        ] = true;
    }

    $fieldTotal =
        count($completionFields);

    $answeredTotal = 0;

    foreach (
        array_keys($completionFields)
        as $fieldKey
    ) {
        if (
            llama_points_has_answer(
                $data,
                (string) $fieldKey
            )
        ) {
            $answeredTotal++;
        }
    }

    /*
     * Photo presence counts once toward completion, but photos
     * do not directly award contribution points.
     */
    $fieldTotal++;

    if ($photoCount > 0) {
        $answeredTotal++;
    }


    /*
     * =====================================================
     * MINIMUM SUBMISSION REQUIREMENTS
     * =====================================================
     */
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

        'standalone_fields' =>
            $standaloneRows,

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

    $standaloneFields =
        llama_points_standalone_place_fields();

    foreach ($categories as $slug => &$category) {
        $category['policy_key'] =
            'place_update_' . $slug;

        $category['mode'] =
            'weighted';

        $category['fields'] = [];

        foreach ($fields as $fieldKey => $field) {
            $fieldKey =
                (string) $fieldKey;

            /*
             * Standalone fields have their own update point values.
             * They must not also dilute or score inside a category.
             */
            if (isset($standaloneFields[$fieldKey])) {
                continue;
            }

            if (
                in_array(
                    $slug,
                    (array) (
                        $field['points_categories']
                        ?? []
                    ),
                    true
                )
                &&
                (string) (
                    $field['type']
                    ?? ''
                ) !== 'derived'
            ) {
                $category['fields'][] =
                    $fieldKey;
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

    foreach (
        llama_points_standalone_place_fields()
        as $definition
    ) {
        $total +=
            llama_points_policy_required(
                $db,
                (string) $definition['update_policy_key']
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
    $standaloneRows = [];

    $estimatedPoints = 0;
    $maxPoints = 0;
    $scoredChangedFields = 0;


    /*
     * =====================================================
     * CATEGORY CHANGES
     * =====================================================
     */
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
                ||
                str_starts_with(
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
                    $changedStorageLookup[
                        $storage
                    ]
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
             * One legitimate approved changed field should
             * still earn at least one point when this category
             * has a configured value.
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


    /*
     * =====================================================
     * STANDALONE FIELD CHANGES
     * =====================================================
     */
    foreach (
        llama_points_standalone_place_fields()
        as $fieldKey => $definition
    ) {
        $field =
            $fields[(string) $fieldKey]
            ?? null;

        $fieldMax =
            llama_points_policy_required(
                $db,
                (string) $definition['update_policy_key']
            );

        $maxPoints +=
            $fieldMax;

        $storage = '';

        if (is_array($field)) {
            $storage =
                trim(
                    (string) (
                        $field['storage']
                        ?? ''
                    )
                );
        }

        $changed =
            $storage !== ''
            &&
            !str_starts_with(
                $storage,
                'computed.'
            )
            &&
            isset(
                $changedStorageLookup[
                    $storage
                ]
            );

        if ($storage !== '') {
            $scoredStorageLookup[$storage] =
                true;
        }

        $points =
            $changed
                ? $fieldMax
                : 0;

        $estimatedPoints +=
            $points;

        if ($changed) {
            $scoredChangedFields++;
        }

        $standaloneRows[] = [
            'field' =>
                (string) $fieldKey,

            'label' =>
                (string) $definition['label'],

            'policy_key' =>
                (string) $definition['update_policy_key'],

            'changed' =>
                $changed ? 1 : 0,

            'total' =>
                1,

            'points' =>
                $points,

            'max_points' =>
                $fieldMax,

            'started' =>
                $changed,
        ];
    }


    /*
     * Anything changed that belongs to neither a category nor
     * one of the six standalone point fields remains unscored.
     */
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

        'standalone_fields' =>
            $standaloneRows,
    ];
}

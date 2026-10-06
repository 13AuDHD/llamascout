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
    return
        llama_points_policy_required(
            $db,
            'new_place_max_points'
        );
}


/* =========================================================
   PLACE REPORT COMPLETENESS

   Completion is owned by app/place-report.php.

   This wrapper is retained because other Llama Scout code may
   already call llama_place_report_completion_summary(). It now
   delegates to the canonical deterministic completion engine.

   Important:
   - applicability is handled by the Place Report schema;
   - grouped questions such as Amenities count once;
   - explicitly optional fields do not count;
   - Unknown counts as a completed observation; untouched questions do not;
   - minimum character requirements are enforced centrally;
   - photo evidence counts once.
   ========================================================= */

function llama_place_report_completion_summary(
    array $data,
    int $photoCount = 0
): array {
    return
        llama_place_report_question_completion_summary(
            $data,
            $photoCount
        );
}

function llama_points_estimate_new_place(
    PDO $db,
    array $data,
    int $photoCount
): array {
    $completion =
        llama_place_report_question_completion_summary(
            $data,
            $photoCount
        );

    $completionPercent =
        max(
            0,
            min(
                100,
                (int) (
                    $completion['percent']
                    ?? 0
                )
            )
        );

    $pointsPerPercent =
        llama_points_policy_required(
            $db,
            'new_place_points_per_percent'
        );

    $maxPoints =
        llama_points_policy_required(
            $db,
            'new_place_max_points'
        );

    $multiplierPercent =
        llama_points_policy_required(
            $db,
            'points_global_multiplier'
        );

$basePoints =
    max(
        0,
        min(
            $maxPoints,
            $completionPercent
            * $pointsPerPercent
        )
    );

$estimatedPoints =
    (int) round(
        $basePoints
        * (
            $multiplierPercent
            / 100
        )
    );

$effectiveMaxPoints =
    (int) round(
        $maxPoints
        * (
            $multiplierPercent
            / 100
        )
    );

    return [
        'completion_percent' =>
            $completionPercent,

        'completion_answered' =>
            (int) (
                $completion['answered']
                ?? 0
            ),

        'completion_total' =>
            (int) (
                $completion['total']
                ?? 0
            ),

        'completion_missing' =>
            array_values(
                (array) (
                    $completion['missing']
                    ?? []
                )
            ),

        'estimated_points' =>
            $estimatedPoints,

        'max_points' =>
            $effectiveMaxPoints,

        'minimum_ready' =>
            !empty(
                $completion['minimum_met']
            ),

        'missing_minimum' =>
            array_values(
                (array) (
                    $completion['missing_minimum']
                    ?? []
                )
            ),

        'categories_started' => 0,
        'category_count' => 0,
        'categories' => [],
        'standalone_fields' => [],
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

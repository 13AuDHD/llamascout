<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report.php';

/* =========================================================
   LLAMA SCOUT POINTS

   Place Report scoring uses one shared completion model.

   New Places:
   - completion percentage determines the base award;
   - Admin Points controls points per percent and the base cap.

   Place Updates:
   - additions and corrections are treated identically;
   - changed applicable completion items determine the update
     percentage;
   - Admin Points controls percent per point and the base cap.

   The global multiplier is applied after the normal base cap.
   ========================================================= */


/* =========================================================
   POLICY
   ========================================================= */

function llama_points_policy(
    PDO $db,
    string $key,
    int $default = 0
): int {
    try {
        $stmt =
            $db->prepare(
                'SELECT points_value
                 FROM points_policy
                 WHERE policy_key = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $key,
        ]);

        $value =
            $stmt->fetchColumn();

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
    $stmt =
        $db->prepare(
            'SELECT points_value
             FROM points_policy
             WHERE policy_key = ?
             LIMIT 1'
        );

    $stmt->execute([
        $key,
    ]);

    $value =
        $stmt->fetchColumn();

    if ($value === false) {
        throw new RuntimeException(
            'Points policy setting "'
            . $key
            . '" is not configured.'
        );
    }

    return
        max(
            0,
            (int) $value
        );
}


function llama_points_multiplier_percent(
    PDO $db
): int {
    return
        llama_points_policy_required(
            $db,
            'points_global_multiplier'
        );
}


function llama_points_apply_multiplier(
    int $points,
    int $multiplierPercent
): int {
    return
        max(
            0,
            (int) round(
                max(
                    0,
                    $points
                )
                * (
                    max(
                        0,
                        $multiplierPercent
                    )
                    / 100
                )
            )
        );
}


/* =========================================================
   LEDGER
   ========================================================= */

function llama_points_total(
    PDO $db,
    int $userId
): int {
    try {
        $stmt =
            $db->prepare(
                'SELECT COALESCE(SUM(points), 0)
                 FROM points_ledger
                 WHERE user_id = ?'
            );

        $stmt->execute([
            $userId,
        ]);

        return
            (int) $stmt->fetchColumn();
    } catch (Throwable) {
        $stmt =
            $db->prepare(
                'SELECT COALESCE(SUM(points_awarded), 0)
                 FROM place_contributions
                 WHERE user_id = ?
                   AND status = "approved"'
            );

        $stmt->execute([
            $userId,
        ]);

        return
            (int) $stmt->fetchColumn();
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
        $exists =
            $db->prepare(
                'SELECT id
                 FROM points_ledger
                 WHERE contribution_id = ?
                 LIMIT 1'
            );

        $exists->execute([
            $contributionId,
        ]);

        $existingId =
            (int) (
                $exists->fetchColumn()
                ?: 0
            );

        if ($existingId > 0) {
            return $existingId;
        }
    }

    $stmt =
        $db->prepare(
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

    return
        (int) $db->lastInsertId();
}


/* =========================================================
   LEGACY SCHEMA HELPERS

   These remain available because other parts of Llama Scout
   may still reference them. They no longer determine point
   values.
   ========================================================= */

function llama_points_standalone_place_fields(): array
{
    return [
        'description' => [
            'label' =>
                'Description',
        ],

        'access_summary' => [
            'label' =>
                'Access Summary',
        ],

        'sensory_summary' => [
            'label' =>
                'Sensory Summary',
        ],

        'not_recommended_for' => [
            'label' =>
                'Not Recommended For',
        ],

        'seasonal_access_note' => [
            'label' =>
                'Seasonal Access Notes',
        ],

        'current_fire_restrictions_url' => [
            'label' =>
                'Current Fire Restrictions URL',
        ],
    ];
}


function llama_points_optional_new_place_fields(): array
{
    return [
        'connectivity_starlink_note' =>
            true,

        'scout_note_1' =>
            true,

        'scout_note_2' =>
            true,

        'scout_note_3' =>
            true,

        'contributor_notes' =>
            true,
    ];
}


function llama_points_new_place_categories(): array
{
    $categories =
        llama_place_report_category_definitions();

    $fields =
        llama_place_report_fields();

    foreach (
        $categories
        as $slug => &$category
    ) {
        $category['fields'] = [];

        foreach (
            $fields
            as $fieldKey => $field
        ) {
            if (
                in_array(
                    (string) $slug,
                    (array) (
                        $field['points_categories']
                        ?? []
                    ),
                    true
                )
            ) {
                $category['fields'][] =
                    (string) $fieldKey;
            }
        }
    }

    unset($category);

    return $categories;
}


function llama_points_place_update_categories(): array
{
    $categories =
        llama_points_new_place_categories();

    foreach (
        $categories
        as $slug => &$category
    ) {
        $category['mode'] =
            'percentage';

        $category['policy_key'] =
            'place_update_'
            . (string) $slug;
    }

    unset($category);

    return $categories;
}


function llama_points_has_answer(
    array $data,
    string $key
): bool {
    return
        llama_place_report_is_answered_input(
            $data,
            $key
        );
}


/* =========================================================
   PLACE REPORT COMPLETION

   app/place-report.php remains the source of truth for:
   - applicability;
   - completion groups;
   - answered vs untouched;
   - Unknown answers;
   - minimum text lengths;
   - photo evidence.
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


/* =========================================================
   NEW PLACE POINTS

   Base formula:

       completion percentage
       Ã configured points per percent

   The result is capped at new_place_max_points.

   The global multiplier is applied AFTER the base cap.

   Example at default settings:

       54% = 54 base points
       100% = 100 base points

   At a 2.00x multiplier:

       54% = 108 awarded points
       100% = 200 awarded points
   ========================================================= */

function llama_points_new_place_max_points(
    PDO $db
): int {
    return
        llama_points_policy_required(
            $db,
            'new_place_max_points'
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

    $baseMaxPoints =
        llama_points_policy_required(
            $db,
            'new_place_max_points'
        );

    $multiplierPercent =
        llama_points_multiplier_percent(
            $db
        );

    $rawBasePoints =
        $completionPercent
        * $pointsPerPercent;

    $basePoints =
        max(
            0,
            min(
                $baseMaxPoints,
                $rawBasePoints
            )
        );

    $estimatedPoints =
        llama_points_apply_multiplier(
            $basePoints,
            $multiplierPercent
        );

    $effectiveMaxPoints =
        llama_points_apply_multiplier(
            $baseMaxPoints,
            $multiplierPercent
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

        'base_points' =>
            $basePoints,

        'estimated_points' =>
            $estimatedPoints,

        'base_max_points' =>
            $baseMaxPoints,

        'max_points' =>
            $effectiveMaxPoints,

        'multiplier_percent' =>
            $multiplierPercent,

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

        /*
         * Retained for compatibility with older UI code.
         * Category weighting is no longer used.
         */
        'categories_started' =>
            0,

        'category_count' =>
            0,

        'categories' =>
            [],

        'standalone_fields' =>
            [],
    ];
}


/* =========================================================
   UPDATE / CORRECTION POINTS

   Additions and corrections are treated exactly the same.

   Each applicable completion item changed in an approved
   submission contributes to the percentage of the report
   changed.

   Example:

       100 applicable items
       24 changed items
       = 24% changed

       2% per point
       = 12 base points

   A correction from one valid answer to another counts exactly
   the same as filling a previously unanswered question.

   The global multiplier is applied after the base update cap.
   ========================================================= */

function llama_points_place_update_max_points(
    PDO $db
): int {
    return
        llama_points_policy_required(
            $db,
            'place_update_max_points'
        );
}


/*
 * Convert a Place Report field definition into the storage path
 * used by Place Update submissions.
 */
function llama_points_field_storage_path(
    array $field
): string {
    return
        trim(
            (string) (
                $field['storage']
                ?? ''
            )
        );
}


/*
 * Returns a lookup of:

     storage path => completion item key

 * Completion groups deliberately collapse multiple underlying
 * fields into one report item so update scoring follows the
 * exact same "one question = one item" model as completion.
 */
function llama_points_completion_storage_lookup(
    array $finalInput
): array {
    $lookup = [];

    $applicableItems =
        llama_place_report_completion_items(
            $finalInput
        );

    $fields =
        llama_place_report_fields();

    foreach (
        $applicableItems
        as $item
    ) {
        $itemKey =
            (string) (
                $item['key']
                ?? ''
            );

        if ($itemKey === '') {
            continue;
        }

        foreach (
            (array) (
                $item['fields']
                ?? []
            )
            as $fieldKey
        ) {
            $fieldKey =
                (string) $fieldKey;

            $field =
                $fields[$fieldKey]
                ?? null;

            if (!is_array($field)) {
                continue;
            }

            $storage =
                llama_points_field_storage_path(
                    $field
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

            $lookup[$storage] =
                $itemKey;
        }
    }

    return $lookup;
}


/*
 * Determine which applicable completion items were touched by
 * this update.

 * Multiple changed fields belonging to one completion group
 * still count as one changed completion item.
 */
function llama_points_changed_completion_items(
    array $finalInput,
    array $proposedChanges,
    int $photoCount = 0
): array {
    $storageLookup =
        llama_points_completion_storage_lookup(
            $finalInput
        );

    $changedItems = [];

    foreach (
        array_keys(
            $proposedChanges
        )
        as $storage
    ) {
        $storage =
            (string) $storage;

        if (
            !isset(
                $storageLookup[
                    $storage
                ]
            )
        ) {
            continue;
        }

        $itemKey =
            (string) $storageLookup[
                $storage
            ];

        $changedItems[
            $itemKey
        ] =
            true;
    }

    if ($photoCount > 0) {
        $changedItems['evidence:photo'] =
            true;
    }

    return
        array_values(
            array_keys(
                $changedItems
            )
        );
}


/*
 * $finalInput must represent the Place Report after the proposed
 * update has been applied.

 * $proposedChanges is the approved update submission's storage
 * path => proposed value map.
 *
 * $photoCount is the number of photos contributed by this update.
 * Photo evidence is one canonical completion item regardless of
 * how many photos were added.

 * This separation is intentional:
 * - finalInput determines which questions are applicable;
 * - proposedChanges determines which questions this contributor
 *   actually changed;
 * - photoCount determines whether this update changed the single
 *   photo-evidence completion item.
 */
function llama_points_estimate_place_update(
    PDO $db,
    array $finalInput,
    array $proposedChanges,
    int $photoCount = 0
): array {
    $applicableItems =
        llama_place_report_completion_items(
            $finalInput
        );

    /*
     * Photo evidence is part of the canonical Place Report
     * completion model even though it is not a normal field.
     * It therefore always belongs in the update denominator.
     */
    $applicableItems[] = [
        'key' =>
            'evidence:photo',
        'label' =>
            '1 current photo',
        'fields' =>
            [],
        'answered' =>
            $photoCount > 0,
    ];

    $applicableItemCount =
        count(
            $applicableItems
        );

    $changedItems =
        llama_points_changed_completion_items(
            $finalInput,
            $proposedChanges,
            $photoCount
        );

    $changedItemCount =
        count(
            $changedItems
        );

    $changedPercent =
        $applicableItemCount > 0
            ? (
                100
                * (
                    $changedItemCount
                    / $applicableItemCount
                )
            )
            : 0.0;

    $percentPerPoint =
        llama_points_policy_required(
            $db,
            'place_update_percent_per_point'
        );

    /*
     * A zero percent-per-point value would make the formula
     * undefined. Treat it as one rather than allowing a
     * division-by-zero failure.
     */
    $percentPerPoint =
        max(
            1,
            $percentPerPoint
        );

    /*
     * Convert the changed percentage to points and round to the
     * nearest whole point. This keeps the configured
     * percent-per-point rule while avoiding systematic
     * under-awarding from fractional results.
     */
    $rawBasePoints =
        (int) round(
            $changedPercent
            / $percentPerPoint
        );

    $baseMaxPoints =
        llama_points_policy_required(
            $db,
            'place_update_max_points'
        );

    $basePoints =
        max(
            0,
            min(
                $baseMaxPoints,
                $rawBasePoints
            )
        );

    $multiplierPercent =
        llama_points_multiplier_percent(
            $db
        );

    $estimatedPoints =
        llama_points_apply_multiplier(
            $basePoints,
            $multiplierPercent
        );

    $effectiveMaxPoints =
        llama_points_apply_multiplier(
            $baseMaxPoints,
            $multiplierPercent
        );

    return [
        'changed_items' =>
            $changedItemCount,

        'applicable_items' =>
            $applicableItemCount,

        'changed_percent' =>
            round(
                $changedPercent,
                2
            ),

        'percent_per_point' =>
            $percentPerPoint,

        'base_points' =>
            $basePoints,

        'estimated_points' =>
            $estimatedPoints,

        'base_max_points' =>
            $baseMaxPoints,

        'max_points' =>
            $effectiveMaxPoints,

        'multiplier_percent' =>
            $multiplierPercent,

        'changed_item_keys' =>
            $changedItems,

        /*
         * Compatibility keys retained for older callers.
         */
        'changed_fields' =>
            count(
                $proposedChanges
            ),

        'scored_changed_fields' =>
            $changedItemCount,

        'unscored_changed_fields' =>
            max(
                0,
                count(
                    $proposedChanges
                )
                - $changedItemCount
            ),

        'categories_started' =>
            0,

        'category_count' =>
            0,

        'categories' =>
            [],

        'standalone_fields' =>
            [],
    ];
}

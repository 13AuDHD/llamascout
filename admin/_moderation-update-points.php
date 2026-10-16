<?php

declare(strict_types=1);

$scopedReview =
    is_array(
        $proposed['__scoped_report__']
        ?? null
    )
        ? $proposed['__scoped_report__']
        : null;

if ($scopedReview) {
    require_once dirname(__DIR__)
        . '/app/place-report/report-targets.php';

    require_once dirname(__DIR__)
        . '/app/place-report/scoped-answers.php';

    $scopedScope =
        trim(
            (string) (
                $scopedReview['scope']
                ?? ''
            )
        );

    $scopedSource =
        trim(
            (string) (
                $scopedReview['source']
                ?? ''
            )
        );

    $scopedTargetId =
        (int) (
            $scopedReview['id']
            ?? 0
        );

    $scopedTargetLabel =
        trim(
            (string) (
                $scopedReview['target_label']
                ?? ''
            )
        );

    $scopedTargetType =
        $scopedScope === 'site'
            ? 'Site'
            : (
                $scopedScope === 'area'
                    ? 'Area'
                    : 'Scoped target'
            );

    $scopedAnswers =
        (array) (
            $scopedReview['answers']
            ?? []
        );

    /*
     * Compatibility with the original single-answer scoped-update payload.
     */
    if (
        !$scopedAnswers
        && isset(
            $scopedReview['field_key']
        )
    ) {
        $scopedAnswers = [
            (string) $scopedReview['field_key'] =>
                $scopedReview['value']
                ?? null,
        ];
    }

    $scopedOriginal =
        is_array(
            $original['__scoped_report__']
            ?? null
        )
            ? $original['__scoped_report__']
            : [];

    $scopedOriginalAnswers =
        (array) (
            $scopedOriginal['answers']
            ?? []
        );

    /*
     * Compatibility with the original single-answer original snapshot.
     */
    if (
        !$scopedOriginalAnswers
        && isset(
            $scopedReview['field_key']
        )
    ) {
        $legacyKey =
            (string) $scopedReview['field_key'];

        $scopedOriginalAnswers = [
            $legacyKey => [
                'recorded' =>
                    !empty(
                        $scopedOriginal['recorded']
                    ),

                'value' =>
                    $scopedOriginal['value']
                    ?? null,
            ],
        ];
    }

    $scopedFields =
        llama_place_report_fields();

    $scopedSections =
        llama_place_report_sections();

    $scopedUnknownToken =
        llama_place_report_unknown_token();

    $scopedInherited =
        [];

    /*
     * Build the effective parent inheritance visible to the moderator.
     *
     * Direct target answers are intentionally not included here. Those are
     * represented by the original snapshot below.
     */
    try {
        $verifiedTarget =
            llama_report_verified_target(
                $db,
                (int) $item['place_id'],
                $scopedScope,
                $scopedSource,
                $scopedTargetId
            );

        if ($scopedTargetLabel === '') {
            $scopedTargetLabel =
                (string) (
                    $verifiedTarget['label']
                    ?? ''
                );
        }

        $placeValues =
            llama_place_update_shared_form_values(
                llama_place_update_current_values(
                    $db,
                    (int) $item['place_id']
                ),
                llama_place_report_published_answer_state(
                    $db,
                    (int) $item['place_id']
                )
            );

        foreach (
            $scopedFields
            as $fieldKey => $field
        ) {
            if (
                !in_array(
                    $scopedScope,
                    (array) (
                        $field['report_scopes']
                        ?? []
                    ),
                    true
                )
            ) {
                continue;
            }

            if (
                array_key_exists(
                    $fieldKey,
                    $placeValues
                )
            ) {
                $scopedInherited[$fieldKey] = [
                    'value' =>
                        $placeValues[$fieldKey],

                    'from' =>
                        'Place',
                ];
            }
        }

        if (
            $scopedScope === 'site'
            && !empty(
                $verifiedTarget['parent_area_id']
            )
        ) {
            $parentArea =
                llama_report_verified_target(
                    $db,
                    (int) $item['place_id'],
                    'area',
                    'map_feature',
                    (int) $verifiedTarget['parent_area_id']
                );

            $parentAnswers =
                llama_scoped_report_load(
                    $db,
                    (int) $item['place_id'],
                    'area',
                    (int) $parentArea['id'],
                    'map_feature'
                );

            foreach (
                $parentAnswers
                as $fieldKey => $value
            ) {
                $scopedInherited[$fieldKey] = [
                    'value' =>
                        $value,

                    'from' =>
                        'Area: '
                        . (
                            (string) (
                                $parentArea['label']
                                ?? ''
                            )
                        ),
                ];
            }
        }
    } catch (Throwable $exception) {
        /*
         * The approval service performs authoritative target and conflict
         * validation. A display-only inheritance lookup must not make the
         * moderation page unusable.
         */
    }

    $formatScopedValue =
        static function (
            array $field,
            mixed $value
        ) use (
            $scopedUnknownToken
        ): string {
            if ($value === $scopedUnknownToken) {
                return 'Unknown';
            }

            if (
                $value === null
                || $value === ''
            ) {
                return 'Not recorded';
            }

            $type =
                (string) (
                    $field['type']
                    ?? ''
                );

            if (
                in_array(
                    $type,
                    [
                        'tri',
                        'checkbox',
                    ],
                    true
                )
            ) {
                return (
                    (string) $value === '1'
                    || $value === true
                    || $value === 1
                )
                    ? 'Yes'
                    : 'No';
            }

            if ($type === 'permission') {
                return match ((string) $value) {
                    '1' => 'Yes',
                    '2' => 'With Permit',
                    '3' => 'Conditional',
                    default => 'No',
                };
            }

            if ($type === 'rating') {
                return
                    (string) $value
                    . '/5';
            }

            if (
                in_array(
                    $type,
                    [
                        'select',
                        'radio',
                    ],
                    true
                )
            ) {
                $options =
                    (array) (
                        $field['options']
                        ?? []
                    );

                return
                    (string) (
                        $options[
                            (string) $value
                        ]
                        ?? $value
                    );
            }

            if (
                $type === 'multiselect'
                || is_array($value)
            ) {
                $options =
                    (array) (
                        $field['options']
                        ?? []
                    );

                $values =
                    is_array($value)
                        ? $value
                        : llama_place_report_multiselect_values(
                            $value
                        );

                $labels = [];

                foreach (
                    $values
                    as $selected
                ) {
                    $labels[] =
                        (string) (
                            $options[
                                (string) $selected
                            ]
                            ?? $selected
                        );
                }

                return $labels
                    ? implode(
                        ', ',
                        $labels
                    )
                    : 'Not recorded';
            }

            if (is_bool($value)) {
                return $value
                    ? 'Yes'
                    : 'No';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            return 'Recorded';
        };

    $groupedScopedChanges =
        [];

    foreach (
        $scopedAnswers
        as $fieldKey => $proposedValue
    ) {
        $fieldKey =
            (string) $fieldKey;

        $field =
            $scopedFields[$fieldKey]
            ?? null;

        /*
         * Do not silently invent labels or field ownership for data that no
         * longer exists in the canonical question catalog.
         */
        if (!is_array($field)) {
            $groupedScopedChanges['other']['meta'] =
                $scopedSections['other']
                ?? [
                    'label' =>
                        'Other',

                    'icon' =>
                        'edit',
                ];

            $groupedScopedChanges['other']['changes'][] = [
                'label' =>
                    $fieldKey,

                'before' =>
                    'Canonical field definition unavailable',

                'after' =>
                    is_scalar($proposedValue)
                        ? (string) $proposedValue
                        : 'Recorded',

                'warning' =>
                    true,
            ];

            continue;
        }

        $section =
            (string) (
                $field['section']
                ?? 'other'
            );

        $groupedScopedChanges[$section]['meta'] =
            $scopedSections[$section]
            ?? [
                'label' =>
                    (string) (
                        $field['group']
                        ?? 'Other'
                    ),

                'icon' =>
                    'edit',
            ];

        $prior =
            $scopedOriginalAnswers[$fieldKey]
            ?? [
                'recorded' =>
                    false,

                'value' =>
                    null,
            ];

        $priorRecorded =
            is_array($prior)
            && !empty(
                $prior['recorded']
            );

        if ($priorRecorded) {
            $beforeText =
                'Existing '
                . $scopedTargetType
                . ' override: '
                . $formatScopedValue(
                    $field,
                    $prior['value']
                    ?? null
                );
        } elseif (
            isset(
                $scopedInherited[$fieldKey]
            )
        ) {
            $beforeText =
                'Inherited from '
                . (
                    (string) (
                        $scopedInherited[$fieldKey]['from']
                        ?? 'parent'
                    )
                )
                . ': '
                . $formatScopedValue(
                    $field,
                    $scopedInherited[$fieldKey]['value']
                    ?? null
                );
        } else {
            $beforeText =
                'No direct '
                . $scopedTargetType
                . ' answer or inherited value';
        }

        $groupedScopedChanges[$section]['changes'][] = [
            'label' =>
                (string) (
                    $field['label']
                    ?? $fieldKey
                ),

            'before' =>
                $beforeText,

            'after' =>
                'Proposed '
                . $scopedTargetType
                . ' override: '
                . $formatScopedValue(
                    $field,
                    $proposedValue
                ),

            'warning' =>
                false,
        ];
    }

    ?>

    <section class="admin-update-changes">

        <header>

            <div>

                <p class="admin-update-eyebrow">
                    Scoped update
                </p>

                <h2>
                    <?= moderation_e(
                        $scopedTargetType
                        . ' changes'
                    ) ?>
                </h2>

                <p>
                    <strong>
                        Target:
                    </strong>

                    <?= moderation_e(
                        $scopedTargetLabel !== ''
                            ? $scopedTargetLabel
                            : (
                                $scopedTargetType
                                . ' #'
                                . $scopedTargetId
                            )
                    ) ?>
                </p>

            </div>

            <span class="admin-update-change-count">

                <?= count(
                    $scopedAnswers
                ) ?>

                question<?= count(
                    $scopedAnswers
                ) === 1
                    ? ''
                    : 's' ?>

            </span>

        </header>


        <?php foreach (
            $groupedScopedChanges
            as $group
        ): ?>

            <?php

            $meta =
                $group['meta']
                ?? [];

            $changes =
                $group['changes']
                ?? [];

            ?>

            <section class="admin-update-change-group">

                <h3>

                    <i aria-hidden="true">
                        <?= llama_icon(
                            (string) (
                                $meta['icon']
                                ?? 'edit'
                            )
                        ) ?>
                    </i>

                    <?= moderation_e(
                        $meta['label']
                        ?? 'Other'
                    ) ?>

                </h3>


                <div class="admin-update-change-grid">

                    <?php foreach (
                        $changes
                        as $change
                    ): ?>

                        <article class="admin-update-change-card">

                            <h4>
                                <?= moderation_e(
                                    $change['label']
                                    ?? ''
                                ) ?>
                            </h4>


                            <div class="admin-update-before-after">

                                <div class="is-before">

                                    <span>
                                        Current / inherited
                                    </span>

                                    <strong>
                                        <?= moderation_e(
                                            $change['before']
                                            ?? ''
                                        ) ?>
                                    </strong>

                                </div>


                                <i aria-hidden="true">
                                    <?= llama_icon(
                                        'arrow-right'
                                    ) ?>
                                </i>


                                <div class="is-after">

                                    <span>
                                        Proposed
                                    </span>

                                    <strong>
                                        <?= moderation_e(
                                            $change['after']
                                            ?? ''
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <?php if (
                                !empty(
                                    $change['warning']
                                )
                            ): ?>

                                <p class="admin-moderation-notice">
                                    This answer no longer has a canonical field
                                    definition. Do not approve it until the
                                    question mapping is repaired.
                                </p>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endforeach; ?>


        <p class="admin-moderation-notice">
            Approval writes these answers only to the selected
            <?= moderation_e(
                $scopedTargetType
            ) ?>.
            Parent Place and sibling Area/Site answers are not changed.
            Scoped contribution points remain disabled until Phase 15.
        </p>

    </section>

    <?php

    /*
     * The canonical scoped review is rendered above. Prevent the legacy raw
     * field-key list below moderate-update.php from rendering a second copy.
     */
    $proposed['__scoped_report__'] =
        null;

    return;
}

if (
    !isset($updatePointEstimate)
    || !is_array($updatePointEstimate)
) {
    return;
}

$estimatedPoints =
    (int) (
        $updatePointEstimate['estimated_points']
        ?? 0
    );

$maxPoints =
    (int) (
        $updatePointEstimate['max_points']
        ?? 0
    );

$basePoints =
    (int) (
        $updatePointEstimate['base_points']
        ?? 0
    );

$changedItems =
    (int) (
        $updatePointEstimate['changed_items']
        ?? 0
    );

$applicableItems =
    (int) (
        $updatePointEstimate['applicable_items']
        ?? 0
    );

$changedPercent =
    (float) (
        $updatePointEstimate['changed_percent']
        ?? 0
    );

$percentPerPoint =
    max(
        1,
        (int) (
            $updatePointEstimate['percent_per_point']
            ?? 1
        )
    );

$multiplierPercent =
    (int) (
        $updatePointEstimate['multiplier_percent']
        ?? 100
    );

$multiplier =
    $multiplierPercent
    / 100;
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/features/moderation-points.css"
>


<section
    class="admin-moderation-detail admin-moderation-points"
    aria-labelledby="update-points-heading"
>
    <header class="admin-moderation-points-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i aria-hidden="true">
                    <?= llama_icon('star') ?>
                </i>
                Points
            </p>

            <h2 id="update-points-heading">
                Update Points
            </h2>

            <p>
                Corrections and additions are scored the same way.
                The award is based on the percentage of applicable
                Place Report questions changed by this approved update.
            </p>
        </div>

        <strong class="admin-moderation-points-total">
            <?= number_format($estimatedPoints) ?>
            <span>
                /
                <?= number_format($maxPoints) ?>
            </span>
        </strong>
    </header>


    <div class="admin-moderation-points-grid">

        <div class="admin-moderation-points-row">
            <span>
                <strong>
                    Questions changed
                </strong>

                <small>
                    Completion items changed by this update
                </small>
            </span>

            <strong>
                <?= number_format($changedItems) ?>
                <small>
                    /
                    <?= number_format($applicableItems) ?>
                </small>
            </strong>
        </div>


        <div class="admin-moderation-points-row">
            <span>
                <strong>
                    Report changed
                </strong>

                <small>
                    Changed questions divided by applicable questions
                </small>
            </span>

            <strong>
                <?= number_format($changedPercent, 2) ?>%
            </strong>
        </div>


        <div class="admin-moderation-points-row">
            <span>
                <strong>
                    Base award
                </strong>

                <small>
                    1 point for each
                    <?= number_format($percentPerPoint) ?>%
                    changed
                </small>
            </span>

            <strong>
                <?= number_format($basePoints) ?>
            </strong>
        </div>


        <div class="admin-moderation-points-row">
            <span>
                <strong>
                    Points multiplier
                </strong>

                <small>
                    Applied after the normal update cap
                </small>
            </span>

            <strong>
                <?= number_format($multiplier, 2) ?>x
            </strong>
        </div>

    </div>


    <?php if ($changedItems < 1): ?>
        <p class="admin-moderation-points-note">
            This update does not change an applicable Place Report
            completion item, so it does not earn update points.
        </p>
    <?php endif; ?>

</section>

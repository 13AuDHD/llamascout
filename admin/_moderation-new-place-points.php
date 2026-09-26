<?php

declare(strict_types=1);

if (
    !isset($newPlacePointEstimate)
    || !is_array($newPlacePointEstimate)
) {
    return;
}

$estimatedPoints =
    (int) (
        $newPlacePointEstimate['estimated_points']
        ?? 0
    );

$maxPoints =
    (int) (
        $newPlacePointEstimate['max_points']
        ?? 0
    );

$categories =
    is_array(
        $newPlacePointEstimate['categories']
        ?? null
    )
        ? $newPlacePointEstimate['categories']
        : [];

$standaloneFields =
    is_array(
        $newPlacePointEstimate['standalone_fields']
        ?? null
    )
        ? $newPlacePointEstimate['standalone_fields']
        : [];
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/features/moderation-points.css"
>


<section
    class="admin-moderation-detail admin-moderation-points"
    aria-labelledby="new-place-points-heading"
>
    <header class="admin-moderation-points-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i aria-hidden="true">
                    <?= llama_icon('star') ?>
                </i>
                Points
            </p>

            <h2 id="new-place-points-heading">
                Contribution Point Calculation
            </h2>

            <p>
                Calculated automatically from the current
                Admin Points policy. Moderators cannot override
                this value here.
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


    <?php if ($categories || $standaloneFields): ?>
        <div class="admin-moderation-points-grid">

            <?php foreach ($categories as $category): ?>
                <?php
                $points =
                    (int) (
                        $category['points']
                        ?? 0
                    );

                $categoryMax =
                    (int) (
                        $category['max_points']
                        ?? 0
                    );

                $answered =
                    (int) (
                        $category['answered']
                        ?? 0
                    );

                $fieldTotal =
                    (int) (
                        $category['total']
                        ?? 0
                    );
                ?>

                <div class="admin-moderation-points-row">
                    <span>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $category['label']
                                    ?? ''
                                )
                            ) ?>
                        </strong>

                    <small>
                        <?php if (
                            (string) (
                                $category['mode']
                                ?? ''
                            )
                            === 'any'
                        ): ?>
                            <?= $answered > 0
                                ? 'Information supplied'
                                : 'No information supplied'
                            ?>
                        <?php else: ?>
                            <?= number_format($answered) ?>
                            of
                            <?= number_format($fieldTotal) ?>
                            scored fields answered
                    
                            <?php
                            $missingFields =
                                is_array(
                                    $category['missing_fields']
                                    ?? null
                                )
                                    ? $category['missing_fields']
                                    : [];
                    
                            $missingLabels =
                                array_values(
                                    array_filter(
                                        array_map(
                                            static fn (
                                                array $field
                                            ): string =>
                                                trim(
                                                    (string) (
                                                        $field['label']
                                                        ?? ''
                                                    )
                                                ),
                                            $missingFields
                                        ),
                                        static fn (
                                            string $label
                                        ): bool =>
                                            $label !== ''
                                    )
                                );
                            ?>
                    
                            <?php if ($missingLabels): ?>
                                <br>
                                Missing:
                                <?= moderation_e(
                                    implode(
                                        ', ',
                                        $missingLabels
                                    )
                                ) ?>
                            <?php endif; ?>
                    
                        <?php endif; ?>
                    </small>
                        
                    </span>

                    <strong>
                        <?= number_format($points) ?>
                        <small>
                            /
                            <?= number_format($categoryMax) ?>
                        </small>
                    </strong>
                </div>
            <?php endforeach; ?>

                        <?php foreach ($standaloneFields as $field): ?>
                <?php
                $points =
                    (int) (
                        $field['points']
                        ?? 0
                    );

                $fieldMax =
                    (int) (
                        $field['max_points']
                        ?? 0
                    );

                $answered =
                    !empty(
                        $field['answered']
                    );
                ?>

                <div class="admin-moderation-points-row">
                    <span>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $field['label']
                                    ?? ''
                                )
                            ) ?>
                        </strong>

                        <small>
                            <?= $answered
                                ? 'Answered'
                                : 'Not answered'
                            ?>
                        </small>
                    </span>

                    <strong>
                        <?= number_format($points) ?>
                        <small>
                            /
                            <?= number_format($fieldMax) ?>
                        </small>
                    </strong>
                </div>

            <?php endforeach; ?>

        </div>
    <?php endif; ?>

</section>

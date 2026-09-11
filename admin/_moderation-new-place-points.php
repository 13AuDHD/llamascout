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
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/features/moderation-points.css"
>

<section
    class="admin-moderation-detail admin-moderation-points"
    aria-labelledby="amenities-observation-heading"
>
    <header class="admin-moderation-points-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i
                    class="fa-solid fa-circle-info"
                    aria-hidden="true"
                ></i>
                Amenities
            </p>

            <h2 id="amenities-observation-heading">
                Amenities Observation
            </h2>

            <?php if ($reportedNoAmenities): ?>
                <p>
                    The contributor explicitly reported that
                    this Place has no amenities.
                </p>
            <?php elseif ($reportedAmenities): ?>
                <p>
                    The contributor explicitly reported the
                    amenities listed below.
                </p>
            <?php else: ?>
                <p>
                    The contributor did not answer the Amenities
                    category. This category should receive no
                    Amenities points.
                </p>
            <?php endif; ?>
        </div>

        <strong class="admin-moderation-points-total">
            <?php if ($reportedNoAmenities): ?>
                None
            <?php elseif ($reportedAmenities): ?>
                <?= number_format(count($reportedAmenities)) ?>
            <?php else: ?>
                Unanswered
            <?php endif; ?>
        </strong>
    </header>

    <?php if ($reportedNoAmenities): ?>
        <div class="admin-moderation-points-grid">
            <div class="admin-moderation-points-row">
                <span>
                    <strong>No amenities</strong>
                    <small>
                        Explicitly selected by the contributor
                    </small>
                </span>

                <strong>Yes</strong>
            </div>
        </div>
    <?php elseif ($reportedAmenities): ?>
        <div class="admin-moderation-points-grid">
            <?php foreach ($reportedAmenities as $amenityLabel): ?>
                <div class="admin-moderation-points-row">
                    <span>
                        <strong>
                            <?= moderation_e($amenityLabel) ?>
                        </strong>
                    </span>

                    <strong>Yes</strong>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


<section
    class="admin-moderation-detail admin-moderation-points"
    aria-labelledby="new-place-points-heading"
>
    <header class="admin-moderation-points-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i
                    class="fa-solid fa-star"
                    aria-hidden="true"
                ></i>
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


    <?php if ($categories): ?>
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

        </div>
    <?php endif; ?>

</section>

<?php

declare(strict_types=1);

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

$categories =
    is_array(
        $updatePointEstimate['categories']
        ?? null
    )
        ? $updatePointEstimate['categories']
        : [];

$scoredChanged =
    (int) (
        $updatePointEstimate['scored_changed_fields']
        ?? 0
    );

$unscoredChanged =
    (int) (
        $updatePointEstimate['unscored_changed_fields']
        ?? 0
    );
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
                <i
                    class="fa-solid fa-star"
                    aria-hidden="true"
                ></i>
                Points
            </p>

            <h2 id="update-points-heading">
                Weighted Update Points
            </h2>

            <p>
                Only fields actually changed by this approved update are
                scored. Each category is weighted independently from the
                current Admin Points policy.
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
                $changed =
                    (int) (
                        $category['changed']
                        ?? 0
                    );

                if ($changed < 1) {
                    continue;
                }

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
                            <?= number_format($changed) ?>
                            of
                            <?= number_format($fieldTotal) ?>
                            scored fields changed
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


    <?php if ($unscoredChanged > 0): ?>
        <p class="admin-moderation-points-note">
            <?= number_format($unscoredChanged) ?>
            changed field<?= $unscoredChanged === 1 ? '' : 's' ?>
            fall outside the weighted point categories and do not add points.
            This includes core identity or location metadata that is not part
            of a scored Place Report category.
        </p>
    <?php elseif ($scoredChanged < 1): ?>
        <p class="admin-moderation-points-note">
            This update does not change a point-bearing Place Report field.
        </p>
    <?php endif; ?>

</section>

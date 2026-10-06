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

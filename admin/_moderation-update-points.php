<?php

declare(strict_types=1);


/*
 * =========================================================
 * PLACE UPDATE POINTS
 *
 * Expected variable:
 *
 * $updatePointValue
 *
 * This value comes from the current Admin Points policy for
 * approved_place_update.
 * =========================================================
 */


if (
    !isset($updatePointValue)
) {
    return;
}


$points =
    max(
        0,
        (int) $updatePointValue
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
                Contribution Points
            </h2>


            <p>
                Points are calculated automatically from the current
                Admin Points policy. Moderators cannot override the
                value during review.
            </p>

        </div>


        <strong class="admin-moderation-points-total">

            <?= number_format(
                $points
            ) ?>

            <span>
                points
            </span>

        </strong>


    </header>


    <div class="admin-moderation-points-grid">


        <div class="admin-moderation-points-row">


            <span>

                <strong>
                    Approved Place Update
                </strong>

                <small>
                    Awarded if this contribution is approved
                </small>

            </span>


            <strong>

                <?= number_format(
                    $points
                ) ?>

                <small>
                    points
                </small>

            </strong>


        </div>


    </div>


</section>

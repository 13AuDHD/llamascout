<?php

$placeEnvironmentKeys = [
    'forest',
    'mountains',
    'water_nearby',
    'water_view',
    'mountain_view',
    'forest_view',
    'wildlife',
    'bugs',
    'wind_exposure',
    'sun_exposure',
    'environment_shade',
    'environment_open_sky',
];

$hasEnvironmentData = false;

foreach ($placeEnvironmentKeys as $environmentKey) {
    if (
        array_key_exists(
            $environmentKey,
            $details
        )
        && $details[$environmentKey] !== null
        && $details[$environmentKey] !== ''
    ) {
        $hasEnvironmentData = true;
        break;
    }
}
?>

<?php if ($hasEnvironmentData): ?>

    <section class="scout-report-section">

        <h3>
            <i
                class="fa-solid fa-tree"
                aria-hidden="true"
            ></i>

            Environment
        </h3>

        <div class="scout-report-grid">
            <?php
            place_report_item(
                'Forest',
                place_yes_no(
                    $details['forest']
                    ?? null
                ),
                'fa-tree'
            );
            ?>

            <?php
            place_report_item(
                'Mountains',
                place_yes_no(
                    $details['mountains']
                    ?? null
                ),
                'fa-mountain'
            );
            ?>

            <?php
            place_report_item(
                'Water nearby',
                place_yes_no(
                    $details['water_nearby']
                    ?? null
                ),
                'fa-water'
            );
            ?>

            <?php
            place_report_item(
                'Water view',
                place_yes_no(
                    $details['water_view']
                    ?? null
                ),
                'fa-water'
            );
            ?>

            <?php
            place_report_item(
                'Mountain view',
                place_yes_no(
                    $details['mountain_view']
                    ?? null
                ),
                'fa-mountain-sun'
            );
            ?>

            <?php
            place_report_item(
                'Forest view',
                place_yes_no(
                    $details['forest_view']
                    ?? null
                ),
                'fa-tree'
            );
            ?>

            <?php
            place_report_item(
                'Wildlife observed',
                place_yes_no(
                    $details['wildlife']
                    ?? null
                ),
                'fa-paw'
            );
            ?>

            <?php
            place_report_item(
                'Bugs / insects',
                place_yes_no(
                    $details['bugs']
                    ?? null
                ),
                'fa-bug'
            );
            ?>
        </div>

        <div class="scout-report-grid">
            <?php
            place_report_rating_item(
                'Wind exposure',
                $details['wind_exposure']
                ?? null
            );
            ?>

            <?php
            place_report_rating_item(
                'Sun exposure',
                $details['sun_exposure']
                ?? null
            );
            ?>

            <?php
            place_report_rating_item(
                'Environmental shade',
                $details['environment_shade']
                ?? null
            );
            ?>

            <?php
            place_report_rating_item(
                'Environmental open sky',
                $details['environment_open_sky']
                ?? null
            );
            ?>
        </div>

    </section>

<?php endif; ?>

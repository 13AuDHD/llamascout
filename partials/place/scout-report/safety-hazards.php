<?php

$placeSafetyKeys = [
    'felt_safe_daytime',
    'felt_safe_nighttime',
    'flash_flood_risk',
    'wildfire_risk',
    'fall_hazard',
    'cliff_exposure',
    'rockfall_risk',
    'wildlife_risk',
    'traffic_hazard',
    'emergency_access',
];

$hasSafetyData = false;

foreach ($placeSafetyKeys as $safetyKey) {
    if (
        array_key_exists(
            $safetyKey,
            $details
        )
        && $details[$safetyKey] !== null
        && $details[$safetyKey] !== ''
    ) {
        $hasSafetyData = true;
        break;
    }
}
?>

<?php if ($hasSafetyData): ?>

    <section class="scout-report-section">

        <h3>
            <i
                class="fa-solid fa-shield-halved"
                aria-hidden="true"
            ></i>

            Safety &amp; hazards
        </h3>

        <div class="scout-report-subsection">

            <h4>Safety observations</h4>

            <div class="scout-report-grid">

                <?php
                place_report_item(
                    'Felt safe during daytime',
                    place_yes_no(
                        $details['felt_safe_daytime']
                        ?? null
                    ),
                    'fa-sun'
                );
                ?>

                <?php
                place_report_item(
                    'Felt safe at nighttime',
                    place_yes_no(
                        $details['felt_safe_nighttime']
                        ?? null
                    ),
                    'fa-moon'
                );
                ?>

                <?php
                place_report_item(
                    'Emergency access',
                    place_yes_no(
                        $details['emergency_access']
                        ?? null
                    ),
                    'fa-truck-medical'
                );
                ?>

            </div>

        </div>

        <div class="scout-report-subsection">

            <h4>Reported hazards</h4>

            <div class="scout-report-grid">

                <?php
                place_report_item(
                    'Flash flood risk',
                    place_yes_no(
                        $details['flash_flood_risk']
                        ?? null
                    ),
                    'fa-water'
                );
                ?>

                <?php
                place_report_item(
                    'Wildfire risk',
                    place_yes_no(
                        $details['wildfire_risk']
                        ?? null
                    ),
                    'fa-fire-flame-curved'
                );
                ?>

                <?php
                place_report_item(
                    'Fall hazard',
                    place_yes_no(
                        $details['fall_hazard']
                        ?? null
                    ),
                    'fa-person-falling'
                );
                ?>

                <?php
                place_report_item(
                    'Cliff exposure',
                    place_yes_no(
                        $details['cliff_exposure']
                        ?? null
                    ),
                    'fa-mountain'
                );
                ?>

                <?php
                place_report_item(
                    'Rockfall risk',
                    place_yes_no(
                        $details['rockfall_risk']
                        ?? null
                    ),
                    'fa-hill-rockslide'
                );
                ?>

                <?php
                place_report_item(
                    'Wildlife risk',
                    place_yes_no(
                        $details['wildlife_risk']
                        ?? null
                    ),
                    'fa-paw'
                );
                ?>

                <?php
                place_report_item(
                    'Traffic hazard',
                    place_yes_no(
                        $details['traffic_hazard']
                        ?? null
                    ),
                    'fa-car-burst'
                );
                ?>

            </div>

        </div>

    </section>

<?php endif; ?>

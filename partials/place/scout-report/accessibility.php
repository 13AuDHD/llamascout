<?php

$placeAccessibilityKeys = [
    'wheelchair_friendly',
    'mobility_device_friendly',
    'flat_walking_surface',
    'walking_distance_from_vehicle',
    'step_free_access',
    'accessible_toilet',
    'accessible_picnic_table',
];

$hasAccessibilityData = false;

foreach (
    $placeAccessibilityKeys
    as $accessibilityKey
) {
    if (
        array_key_exists(
            $accessibilityKey,
            $details
        )
        && $details[$accessibilityKey] !== null
        && $details[$accessibilityKey] !== ''
    ) {
        $hasAccessibilityData = true;
        break;
    }
}
?>

<?php if ($hasAccessibilityData): ?>

    <section class="scout-report-section">

        <h3>
            <i
                class="fa-solid fa-universal-access"
                aria-hidden="true"
            ></i>

            Accessibility
        </h3>

        <div class="scout-report-grid">

            <?php
            place_report_item(
                'Wheelchair friendly',
                place_yes_no(
                    $details['wheelchair_friendly']
                    ?? null
                ),
                'fa-wheelchair'
            );
            ?>

            <?php
            place_report_item(
                'Mobility device friendly',
                place_yes_no(
                    $details['mobility_device_friendly']
                    ?? null
                ),
                'fa-universal-access'
            );
            ?>

            <?php
            place_report_item(
                'Flat walking surface',
                place_yes_no(
                    $details['flat_walking_surface']
                    ?? null
                ),
                'fa-person-walking'
            );
            ?>

            <?php
            place_report_item(
                'Walking distance from vehicle',
                $details['walking_distance_from_vehicle']
                ?? null,
                'fa-person-walking-arrow-right'
            );
            ?>

            <?php
            place_report_item(
                'Step-free access',
                place_yes_no(
                    $details['step_free_access']
                    ?? null
                ),
                'fa-route'
            );
            ?>

            <?php
            place_report_item(
                'Accessible toilet',
                place_yes_no(
                    $details['accessible_toilet']
                    ?? null
                ),
                'fa-restroom'
            );
            ?>

            <?php
            place_report_item(
                'Accessible picnic table',
                place_yes_no(
                    $details['accessible_picnic_table']
                    ?? null
                ),
                'fa-table-picnic'
            );
            ?>

        </div>

    </section>

<?php endif; ?>

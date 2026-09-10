<?php

$placeQuickWarnings = [
    'warning_exposed_to_road' => [
        'Exposed to road',
        'fa-road',
    ],
    'warning_zero_privacy' => [
        'Zero privacy',
        'fa-eye',
    ],
    'warning_passing_vehicle_dust' => [
        'Passing vehicle dust',
        'fa-smog',
    ],
    'warning_possible_downed_trees' => [
        'Possible downed trees',
        'fa-tree',
    ],
    'warning_no_tent_camping' => [
        'No tent camping',
        'fa-tent-arrow-turn-left',
    ],
    'warning_limited_vehicle_length' => [
        'Limited vehicle length',
        'fa-ruler-horizontal',
    ],
    'warning_leveling_may_be_required' => [
        'Leveling may be required',
        'fa-scale-balanced',
    ],
    'warning_no_amenities' => [
        'No amenities',
        'fa-circle-xmark',
    ],
    'warning_motorized_recreation_traffic' => [
        'Motorized recreation traffic',
        'fa-motorcycle',
    ],
    'warning_blind_turn_traffic_nearby' => [
        'Blind-turn traffic nearby',
        'fa-triangle-exclamation',
    ],
];

$activeQuickWarnings = [];

foreach (
    $placeQuickWarnings
    as $warningKey => $warningDefinition
) {
    if (
        array_key_exists(
            $warningKey,
            $details
        )
        && (int) $details[$warningKey] === 1
    ) {
        $activeQuickWarnings[$warningKey] =
            $warningDefinition;
    }
}
?>

<?php if ($activeQuickWarnings): ?>

    <section class="scout-report-section scout-report-warning-section">

        <h3>
            <i
                class="fa-solid fa-triangle-exclamation"
                aria-hidden="true"
            ></i>

            Quick warnings
        </h3>

        <p class="scout-report-summary">
            Important conditions reported for this Place.
            Review these before relying on the rest of the Scout Report.
        </p>

        <div class="scout-report-grid">

            <?php foreach (
                $activeQuickWarnings
                as $warningDefinition
            ): ?>

                <?php
                place_report_item(
                    (string) $warningDefinition[0],
                    'Warning',
                    (string) $warningDefinition[1]
                );
                ?>

            <?php endforeach; ?>

        </div>

    </section>

<?php endif; ?>

<?php if (!empty($details)): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-campground" aria-hidden="true"></i> Site &amp; vehicle</h3>
        <div class="scout-report-grid">
            <?php place_report_item('Vehicle capacity', $details['vehicle_capacity'] ?? null, 'fa-car-side'); ?>
            <?php place_report_item('Maximum vehicle length', isset($details['max_vehicle_length_feet']) && $details['max_vehicle_length_feet'] !== null ? $details['max_vehicle_length_feet'] . ' ft' : null, 'fa-ruler-horizontal'); ?>
            <?php place_report_item('Tent camping', place_yes_no($details['tent_camping_suitable'] ?? null), 'fa-tent'); ?>
            <?php place_report_item('RV suitable', place_yes_no($details['rv_suitable'] ?? null), 'fa-caravan'); ?>
            <?php place_report_item('Trailer suitable', place_yes_no($details['trailer_suitable'] ?? null), 'fa-trailer'); ?>
            <?php place_report_item('Parking surface', $details['parking_surface'] ?? null, 'fa-square-parking'); ?>
            <?php place_report_item('Ground condition', $details['ground_condition'] ?? null, 'fa-mountain-sun'); ?>
            <?php place_report_rating_item('Levelness', $details['levelness'] ?? null); ?>
            <?php place_report_item('Leveling required', place_yes_no($details['leveling_required'] ?? null), 'fa-scale-balanced'); ?>
            <?php place_report_item('Turnaround space', place_yes_no($details['turnaround_space'] ?? null), 'fa-rotate'); ?>
            <?php place_report_item('Pull-through', place_yes_no($details['pull_through'] ?? null), 'fa-arrow-right'); ?>
            <?php place_report_item('Back-in', place_yes_no($details['back_in'] ?? null), 'fa-arrow-left'); ?>
            <?php place_report_rating_item('Open sky', $details['site_open_sky'] ?? null); ?>
            <?php place_report_rating_item('Tree cover', $details['tree_cover'] ?? null); ?>
            <?php place_report_rating_item('Shade', $details['site_shade'] ?? null); ?>
        </div>
    </section>

    <section class="scout-report-section">
        <h3><i class="fa-solid fa-road" aria-hidden="true"></i> Road &amp; access</h3>
        <?php if (!empty($place['access_summary'])): ?>
            <p class="scout-report-summary"><?= nl2br(place_h($place['access_summary'])) ?></p>
        <?php endif; ?>
        <div class="scout-report-grid">
            <?php place_report_rating_item('Site access difficulty', $details['site_access_difficulty'] ?? null); ?>
            <?php place_report_rating_item('Road difficulty', $details['road_overall_difficulty'] ?? null); ?>
            <?php place_report_rating_item('Road stress', $details['road_stress'] ?? null); ?>
            <?php place_report_item('Road surface', $details['road_surface'] ?? null, 'fa-road'); ?>
            <?php place_report_item('Road width', $details['road_width'] ?? null, 'fa-arrows-left-right'); ?>
            <?php place_report_item('Sedan accessible', place_yes_no($details['sedan_accessible'] ?? null), 'fa-car'); ?>
            <?php place_report_item('High clearance recommended', place_yes_no($details['high_clearance_recommended'] ?? null), 'fa-truck-pickup'); ?>
            <?php place_report_item('4WD recommended', place_yes_no($details['four_wheel_drive_recommended'] ?? null), 'fa-truck-monster'); ?>
            <?php place_report_rating_item('Rocks', $details['rocks'] ?? null); ?>
            <?php place_report_rating_item('Washboards', $details['washboards'] ?? null); ?>
            <?php place_report_rating_item('Potholes', $details['potholes'] ?? null); ?>
            <?php place_report_rating_item('Mud risk', $details['mud_risk'] ?? null); ?>
            <?php place_report_rating_item('Steep grades', $details['steep_grades'] ?? null); ?>
            <?php place_report_rating_item('Drop-off exposure', $details['drop_off_exposure'] ?? null); ?>
            <?php place_report_item('Water crossings', place_yes_no($details['water_crossings'] ?? null), 'fa-water'); ?>
            <?php place_report_item('Downed-tree risk', place_yes_no($details['downed_tree_risk'] ?? null), 'fa-tree'); ?>
            <?php place_report_item('Seasonal closure', place_yes_no($details['seasonal_closure'] ?? null), 'fa-calendar-xmark'); ?>
        </div>
    </section>
<?php endif; ?>

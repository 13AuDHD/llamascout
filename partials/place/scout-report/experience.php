<?php if (!empty($experience)): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-binoculars" aria-hidden="true"></i> Experience &amp; recommendations</h3>

        <div class="scout-report-subsection">
            <h4>Experience</h4>
            <div class="scout-report-grid">
                <?php place_report_rating_item('Sunrise view', $experience['sunrise_view'] ?? null); ?>
                <?php place_report_rating_item('Sunset view', $experience['sunset_view'] ?? null); ?>
                <?php place_report_rating_item('Mountain view', $experience['mountain_view'] ?? null); ?>
                <?php place_report_rating_item('Forest view', $experience['forest_view'] ?? null); ?>
                <?php place_report_rating_item('Night sky', $experience['night_sky'] ?? null); ?>
                <?php place_report_rating_item('Stargazing', $experience['stargazing'] ?? null); ?>
                <?php place_report_rating_item('Quiet evening', $experience['quiet_evening'] ?? null); ?>
                <?php place_report_rating_item('Overnight comfort', $experience['overnight_comfort'] ?? null); ?>
                <?php place_report_rating_item('Extended stay comfort', $experience['extended_stay_comfort'] ?? null); ?>
                <?php place_report_rating_item('Sensory retreat', $experience['sensory_retreat'] ?? null); ?>
                <?php place_report_rating_item('Remote work', $experience['remote_work'] ?? null); ?>
                <?php place_report_rating_item('Overall scenery', $experience['overall_scenery'] ?? null); ?>
            </div>
        </div>

        <div class="scout-report-subsection">
            <h4>Recommended for</h4>
            <div class="scout-report-grid">
                <?php place_report_rating_item('Overnight stop', $experience['recommended_overnight_stop'] ?? null); ?>
                <?php place_report_rating_item('Quiet evening', $experience['recommended_quiet_evening'] ?? null); ?>
                <?php place_report_rating_item('Extended stay', $experience['recommended_extended_stay'] ?? null); ?>
                <?php place_report_rating_item('Sensory retreat', $experience['recommended_sensory_retreat'] ?? null); ?>
                <?php place_report_rating_item('Stargazing', $experience['recommended_stargazing'] ?? null); ?>
                <?php place_report_rating_item('Remote work', $experience['recommended_remote_work'] ?? null); ?>
                <?php place_report_item('Solo travel', place_yes_no($experience['recommended_solo_travel'] ?? null), 'fa-person-hiking'); ?>
                <?php place_report_item('Families', place_yes_no($experience['recommended_families'] ?? null), 'fa-people-roof'); ?>
                <?php place_report_item('Large groups', place_yes_no($experience['recommended_large_groups'] ?? null), 'fa-people-group'); ?>
                <?php place_report_item('Not recommended for', $experience['not_recommended_for'] ?? null, 'fa-circle-exclamation'); ?>
            </div>
        </div>
    </section>
<?php endif; ?>

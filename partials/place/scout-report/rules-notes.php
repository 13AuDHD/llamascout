<?php if (!empty($rules)): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Season &amp; rules</h3>
        <?php if (!empty($rules['seasonal_access_note'])): ?>
            <p class="scout-report-summary"><?= nl2br(place_h($rules['seasonal_access_note'])) ?></p>
        <?php endif; ?>
        <div class="scout-report-grid">
            <?php place_report_item('Best months', $rules['best_months'] ?? null, 'fa-calendar-check'); ?>
            <?php place_report_item('Recommended season', $rules['recommended_travel_season'] ?? null, 'fa-leaf'); ?>
            <?php place_report_item('Winter access', place_yes_no($rules['winter_access'] ?? null), 'fa-snowflake'); ?>
            <?php place_report_rating_item('Snow risk', $rules['snow_risk'] ?? null); ?>
            <?php place_report_rating_item('Mud-season risk', $rules['mud_season_risk'] ?? null); ?>
            <?php place_report_rating_item('Monsoon risk', $rules['monsoon_risk'] ?? null); ?>
            <?php place_report_item('Overnight camping', place_yes_no($rules['overnight_camping_allowed'] ?? null), 'fa-moon'); ?>
            <?php place_report_item('Dispersed camping', place_yes_no($rules['dispersed_camping_allowed'] ?? null), 'fa-campground'); ?>
            <?php place_report_item('Stay limit', isset($rules['stay_limit_days']) && $rules['stay_limit_days'] !== null ? $rules['stay_limit_days'] . ' days' : null, 'fa-calendar-day'); ?>
            <?php place_report_item('Permit required', place_yes_no($rules['permit_required'] ?? null), 'fa-file-signature'); ?>
            <?php place_report_item('Fee', isset($rules['fee']) && $rules['fee'] !== null ? '$' . number_format((float) $rules['fee'], 2) : null, 'fa-dollar-sign'); ?>
            <?php place_report_item('Campfire allowed', place_yes_no($rules['campfire_allowed'] ?? null), 'fa-fire'); ?>
            <?php place_report_item('Pack it in, pack it out', place_yes_no($rules['pack_it_in_pack_it_out'] ?? null), 'fa-trash-arrow-up'); ?>
            <?php place_report_item('Existing sites encouraged', place_yes_no($rules['existing_sites_encouraged'] ?? null), 'fa-signs-post'); ?>
            <?php place_report_item('Nearest town', $rules['nearest_town'] ?? null, 'fa-city'); ?>
            <?php place_report_item('Nearest fuel', $rules['nearest_fuel'] ?? null, 'fa-gas-pump'); ?>
            <?php place_report_item('Nearest grocery', $rules['nearest_grocery'] ?? null, 'fa-cart-shopping'); ?>
            <?php place_report_item('Nearest water', $rules['nearest_water'] ?? null, 'fa-faucet-drip'); ?>
            <?php place_report_item('Nearest toilet', $rules['nearest_toilet'] ?? null, 'fa-restroom'); ?>
            <?php place_report_item('Nearest hospital', $rules['nearest_hospital'] ?? null, 'fa-hospital'); ?>
        </div>
        <?php if (!empty($rules['current_fire_restrictions_url'])): ?>
            <p class="scout-report-note">
                <a href="<?= place_h($rules['current_fire_restrictions_url']) ?>" target="_blank" rel="noopener noreferrer">
                    Check current fire restrictions
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!empty($place['notes'])): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Scout notes</h3>
        <ul class="scout-report-notes-list">
            <?php foreach ($place['notes'] as $note): ?>
                <?php if (!empty($note['note'])): ?><li><?= place_h($note['note']) ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

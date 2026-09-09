<?php if (!empty($sensory) || !empty($sensoryDetails) || !empty($place['sensory_summary'])): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-ear-listen" aria-hidden="true"></i> Sensory</h3>
        <?php if (!empty($place['sensory_summary'])): ?>
            <p class="scout-report-summary"><?= nl2br(place_h($place['sensory_summary'])) ?></p>
        <?php endif; ?>
        <?php foreach (['daytime' => 'Daytime', 'nighttime' => 'Nighttime'] as $periodKey => $periodLabel): ?>
            <?php if (!empty($sensory[$periodKey])): ?>
                <div class="scout-report-subsection">
                    <h4><?= place_h($periodLabel) ?></h4>
                    <div class="scout-report-grid">
                        <?php place_report_rating_item('Noise', $sensory[$periodKey]['noise'] ?? null); ?>
                        <?php place_report_rating_item('Traffic', $sensory[$periodKey]['traffic'] ?? null); ?>
                        <?php place_report_rating_item('Crowds', $sensory[$periodKey]['crowds'] ?? null); ?>
                        <?php place_report_rating_item('Privacy', $sensory[$periodKey]['privacy'] ?? null); ?>
                        <?php place_report_rating_item('Light pollution', $sensory[$periodKey]['light_pollution'] ?? null); ?>
                        <?php place_report_rating_item('Sensory comfort', $sensory[$periodKey]['sensory_comfort'] ?? null); ?>
                        <?php place_report_rating_item('Social interaction', $sensory[$periodKey]['social_interaction_likelihood'] ?? null); ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!empty($sensoryDetails)): ?>
            <div class="scout-report-subsection">
                <h4>Other sensory conditions</h4>
                <div class="scout-report-grid">
                    <?php place_report_rating_item('Traffic dust', $sensoryDetails['dust_from_traffic'] ?? null); ?>
                    <?php place_report_rating_item('Generator noise', $sensoryDetails['generator_noise'] ?? null); ?>
                    <?php place_report_rating_item('Aircraft noise', $sensoryDetails['aircraft_noise'] ?? null); ?>
                    <?php place_report_rating_item('Road noise', $sensoryDetails['road_noise'] ?? null); ?>
                    <?php place_report_rating_item('Human activity', $sensoryDetails['human_activity'] ?? null); ?>
                    <?php place_report_rating_item('Wildlife noise', $sensoryDetails['wildlife_noise'] ?? null); ?>
                    <?php place_report_rating_item('Wind noise', $sensoryDetails['wind_noise'] ?? null); ?>
                    <?php place_report_rating_item('Smoke risk', $sensoryDetails['smoke_risk'] ?? null); ?>
                    <?php place_report_rating_item('Strong odors', $sensoryDetails['strong_odors'] ?? null); ?>
                    <?php place_report_rating_item('Visual exposure', $sensoryDetails['visual_exposure'] ?? null); ?>
                    <?php place_report_rating_item('Predictability', $sensoryDetails['predictability'] ?? null); ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

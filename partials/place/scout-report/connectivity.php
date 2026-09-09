<?php if (!empty($connectivity)): ?>
    <section class="scout-report-section">
        <h3><i class="fa-solid fa-signal" aria-hidden="true"></i> Connectivity</h3>
        <div class="scout-report-grid">
            <?php place_report_rating_item('Overall', $connectivity['overall'] ?? null); ?>
            <?php place_report_rating_item('T-Mobile', $connectivity['t_mobile'] ?? null); ?>
            <?php place_report_rating_item('Verizon', $connectivity['verizon'] ?? null); ?>
            <?php place_report_rating_item('AT&T', $connectivity['att'] ?? null); ?>
            <?php place_report_rating_item('Other cell', $connectivity['other_cell'] ?? null); ?>
            <?php place_report_rating_item('Starlink', $connectivity['starlink'] ?? null); ?>
            <?php place_report_item('Starlink tested', place_yes_no($connectivity['starlink_tested'] ?? null), 'fa-satellite'); ?>
        </div>
        <?php if (!empty($connectivity['starlink_note'])): ?>
            <p class="scout-report-note"><strong>Starlink note:</strong> <?= place_h($connectivity['starlink_note']) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

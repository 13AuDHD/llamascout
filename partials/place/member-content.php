<?php if ($hasMemberAccess): ?>

    <?php if (!empty($place['description'])): ?>
        <section class="place-section">
            <h2>About this place</h2>
            <p><?= nl2br(place_h($place['description'])) ?></p>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/scout-report/index.php'; ?>

<?php else: ?>
    <section class="member-preview">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <div>
            <h2>Scout Report</h2>
            <p>
                Members can see the exact location, full photo gallery,
                access information, sensory details, connectivity, and
                detailed site information.
            </p>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasMemberAccess): ?>

    <?php if (!empty($place['description'])): ?>
        <section class="place-section">
            <h2>About this place</h2>
            <p><?= nl2br(place_h($place['description'])) ?></p>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/scout-report/index.php'; ?>

<?php else: ?>

    <?php
    $lockedScoutSections = [
        [
            'title' => 'Site & vehicle',
            'icon' => 'fa-campground',
            'summary' => false,
            'fields' => [
                'Levelness',
                'Open sky',
                'Tree cover',
                'Shade',
                'Vehicle capacity',
                'Maximum vehicle length',
                'Tent camping',
                'RV suitable',
                'Parking surface',
                'Turnaround space',
            ],
        ],
        [
            'title' => 'Road & access',
            'icon' => 'fa-road',
            'summary' => true,
            'fields' => [
                'Site access difficulty',
                'Road difficulty',
                'Road stress',
                'Rocks',
                'Washboards',
                'Potholes',
                'Mud risk',
                'Steep grades',
                'Drop-off exposure',
                'Road surface',
                'Road width',
                'Sedan accessible',
                'High clearance recommended',
                '4WD recommended',
                'Water crossings',
                'Downed-tree risk',
            ],
        ],
        [
            'title' => 'Connectivity',
            'icon' => 'fa-signal',
            'summary' => false,
            'fields' => [
                'Overall',
                'T-Mobile',
                'Verizon',
                'AT&T',
                'Other cell',
                'Starlink',
                'Starlink tested',
            ],
        ],
        [
            'title' => 'Sensory',
            'icon' => 'fa-ear-listen',
            'summary' => true,
            'fields' => [
                'Daytime noise',
                'Daytime traffic',
                'Daytime crowds',
                'Daytime privacy',
                'Nighttime noise',
                'Nighttime crowds',
                'Light pollution',
                'Sensory comfort',
                'Generator noise',
                'Road noise',
                'Human activity',
                'Visual exposure',
            ],
        ],
        [
            'title' => 'Season & rules',
            'icon' => 'fa-calendar-days',
            'summary' => true,
            'fields' => [
                'Best months',
                'Recommended season',
                'Winter access',
                'Snow risk',
                'Mud-season risk',
                'Overnight camping',
                'Dispersed camping',
                'Stay limit',
                'Permit required',
                'Fee',
                'Campfire allowed',
                'Nearest fuel',
                'Nearest water',
                'Nearest hospital',
            ],
        ],
        [
            'title' => 'Experience & recommendations',
            'icon' => 'fa-binoculars',
            'summary' => false,
            'fields' => [
                'Sunrise view',
                'Sunset view',
                'Mountain view',
                'Forest view',
                'Night sky',
                'Stargazing',
                'Quiet evening',
                'Overnight comfort',
                'Extended stay comfort',
                'Sensory retreat',
                'Remote work',
                'Overall scenery',
                'Solo travel',
            ],
        ],
    ];
    ?>

    <section class="place-section locked-place-description" aria-labelledby="locked-about-heading">
        <div class="locked-content-heading">
            <h2 id="locked-about-heading">About this place</h2>
            <span class="locked-member-pill">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Members only
            </span>
        </div>

        <div class="locked-description-preview" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <p class="locked-content-note">
            The full place description is available with a Llama Scout membership.
        </p>
    </section>

    <section class="scout-report scout-report-locked" aria-labelledby="scout-report-heading">
        <header class="scout-report-header locked-scout-header">
            <div>
                <p class="eyebrow">Member details</p>
                <h2 id="scout-report-heading">Scout Report</h2>
            </div>

            <a class="locked-scout-upgrade" href="/membership.php">
                <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                Unlock this Scout Report
            </a>
        </header>

        <p class="locked-scout-intro">
            This Place has more planning information available. Join Llama Scout
            to unlock the answers, exact location, complete photo gallery, and
            detailed Scout Report.
        </p>

        <?php foreach ($lockedScoutSections as $lockedSection): ?>
            <section class="scout-report-section locked-scout-section">
                <h3>
                    <i class="fa-solid <?= place_h($lockedSection['icon']) ?>" aria-hidden="true"></i>
                    <?= place_h($lockedSection['title']) ?>
                </h3>

                <?php if (!empty($lockedSection['summary'])): ?>
                    <div class="locked-summary-preview" aria-label="Members-only written details">
                        <div class="locked-summary-bars" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="locked-summary-label">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            Detailed notes for members
                        </span>
                    </div>
                <?php endif; ?>

                <div class="scout-report-grid locked-scout-grid">
                    <?php foreach ($lockedSection['fields'] as $lockedField): ?>
                        <div class="scout-report-item locked-scout-card">
                            <span class="locked-scout-card-title">
                                <?= place_h($lockedField) ?>
                            </span>

                            <strong class="locked-scout-card-value">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                Members only
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="locked-scout-cta">
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>

            <div>
                <h3>Know the place before you go.</h3>
                <p>
                    Unlock exact locations, Scout Reports, full photo galleries,
                    sensory details, connectivity, road access, and more.
                </p>
            </div>

            <a href="/membership.php">View Membership</a>
        </div>
    </section>

<?php endif; ?>

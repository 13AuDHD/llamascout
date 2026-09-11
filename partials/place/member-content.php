<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/place-report.php';

if ($hasMemberAccess):
?>

    <?php if (!empty($place['description'])): ?>
        <section class="place-section">
            <h2>About this place</h2>
            <p>
                <?= nl2br(
                    place_h(
                        $place['description']
                    )
                ) ?>
            </p>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/scout-report/index.php'; ?>

<?php else: ?>

    <?php
    $lockedScoutSections = [];
    $schemaFields = llama_place_report_fields();

    foreach (
        llama_place_report_sections()
        as $sectionKey => $section
    ) {
        if (
            in_array(
                $sectionKey,
                [
                    'basic',
                    'location',
                    'summaries',
                ],
                true
            )
        ) {
            continue;
        }

        $labels = [];

        foreach ($schemaFields as $field) {
            if (
                (string) $field['section']
                !== $sectionKey
            ) {
                continue;
            }

            $labels[] =
                rtrim(
                    (string) $field['label'],
                    '*'
                );
        }

        if (!$labels) {
            continue;
        }

        $lockedScoutSections[] = [
            'title' =>
                (string) $section['label'],
            'icon' =>
                (string) $section['icon'],
            'fields' =>
                $labels,
        ];
    }
    ?>

    <section
        class="place-section locked-place-description"
        aria-labelledby="locked-about-heading"
    >
        <div class="locked-content-heading">
            <h2 id="locked-about-heading">
                About this place
            </h2>

            <span class="locked-member-pill">
                <i
                    class="fa-solid fa-lock"
                    aria-hidden="true"
                ></i>
                Members only
            </span>
        </div>

        <div
            class="locked-description-preview"
            aria-hidden="true"
        >
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <p class="locked-content-note">
            The full place description is available with a Llama Scout membership.
        </p>
    </section>

    <section
        class="scout-report scout-report-locked"
        aria-labelledby="scout-report-heading"
    >
        <header class="scout-report-header locked-scout-header">
            <div>
                <p class="eyebrow">Member details</p>
                <h2 id="scout-report-heading">
                    Scout Report
                </h2>
            </div>

            <a
                class="locked-scout-upgrade"
                href="/membership.php"
            >
                <i
                    class="fa-solid fa-lock-open"
                    aria-hidden="true"
                ></i>
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
                    <i
                        class="fa-solid <?= place_h($lockedSection['icon']) ?>"
                        aria-hidden="true"
                    ></i>

                    <?= place_h($lockedSection['title']) ?>
                </h3>

                <div class="scout-report-grid locked-scout-grid">
                    <?php foreach ($lockedSection['fields'] as $lockedField): ?>
                        <div class="scout-report-item locked-scout-card">
                            <span class="locked-scout-card-title">
                                <?= place_h($lockedField) ?>
                            </span>

                            <strong class="locked-scout-card-value">
                                <i
                                    class="fa-solid fa-lock"
                                    aria-hidden="true"
                                ></i>
                                Members only
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="locked-scout-cta">
            <i
                class="fa-solid fa-binoculars"
                aria-hidden="true"
            ></i>

            <div>
                <h3>
                    Know the place before you go.
                </h3>

                <p>
                    Unlock exact locations, Scout Reports, full photo galleries,
                    sensory details, connectivity, road access, accessibility,
                    hazards, quick warnings, and more.
                </p>

                <p>
                    Not sure what a complete report looks like?
                    <a href="/scout-report-demo.php">
                        See a complete example Scout Report.
                    </a>
                </p>
            </div>

            <a href="/membership.php">
                View Membership
            </a>
        </div>
    </section>

<?php endif; ?>

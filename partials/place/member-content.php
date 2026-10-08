<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/place-report.php';

$placeDescriptionParagraphs = [];
$placeDescriptionLong = false;

if (!empty($place['description'])) {
    $description =
        html_entity_decode(
            (string) $place['description'],
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

    $description =
        preg_replace(
            '/<\s*br\s*\/?\s*>/i',
            "\n",
            $description
        )
        ?? $description;

    $description =
        preg_replace(
            '/<\s*\/\s*(?:p|h[1-6]|li|div|section)\s*>/i',
            "\n\n",
            $description
        )
        ?? $description;

    $description =
        preg_replace(
            '/<\s*(?:p|h[1-6]|li|div|section)(?:\s[^>]*)?>/i',
            '',
            $description
        )
        ?? $description;

    $description =
        strip_tags(
            $description
        );

    $description =
        str_replace(
            "\u{00A0}",
            ' ',
            $description
        );

    $description =
        preg_replace(
            "/[ \t]+\n/u",
            "\n",
            $description
        )
        ?? $description;

    $description =
        preg_replace(
            "/\n{3,}/u",
            "\n\n",
            $description
        )
        ?? $description;

    $description =
        trim(
            preg_replace(
                '/[ \t]{2,}/u',
                ' ',
                $description
            )
            ?? $description
        );

    if ($description !== '') {
        $placeDescriptionParagraphs =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        preg_split(
                            "/\n{2,}/u",
                            $description
                        )
                        ?: []
                    ),
                    static fn (
                        string $paragraph
                    ): bool =>
                        $paragraph !== ''
                )
            );

        $placeDescriptionLong =
            mb_strlen(
                $description,
                'UTF-8'
            ) > 900;
    }
}

if ($hasMemberAccess):
?>

    <?php if (!empty($hasContributorPlaceAccess) && empty($hasGlobalMemberAccess)): ?>
        <section class="place-section place-contributor-access-note">
            <i aria-hidden="true"><?= llama_icon('key') ?></i>
            <div>
                <strong>Complete Access to this Place</strong>
                <p>
                    You originally contributed this Place, so its complete report,
                    exact location, gallery, and historical reports remain available
                    to your account even without a paid membership.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($placeDescriptionParagraphs): ?>
        <section
            class="place-section place-description"
            data-place-description-section
        >
            <h2>About this place</h2>

            <div
                class="place-description-copy<?= $placeDescriptionLong ? ' is-collapsed' : '' ?>"
                data-place-description-copy
            >
                <?php foreach ($placeDescriptionParagraphs as $paragraph): ?>
                    <p><?= place_h($paragraph) ?></p>
                <?php endforeach; ?>
            </div>

            <?php if ($placeDescriptionLong): ?>
                <button
                    type="button"
                    class="place-description-toggle"
                    data-place-description-toggle
                    aria-expanded="false"
                >
                    <span data-place-description-toggle-label>
                        Expand description
                    </span>

                    <?= llama_icon(
                        'chevron-down',
                        [
                            'class' =>
                                'place-description-toggle-icon',
                        ]
                    ) ?>
                </button>
            <?php endif; ?>
        </section>

        <?php if ($placeDescriptionLong): ?>
            <script src="/js/place-description.js"></script>
        <?php endif; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/overnight-options.php'; ?>

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
                    'amenities',
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
                (string) (
                    $section['icon']
                    ?? 'info-circle'
                ),
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
                <i aria-hidden="true"><?= llama_icon('lock') ?></i>
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
                <i aria-hidden="true"><?= llama_icon('key') ?></i>
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
                    <i aria-hidden="true">
                        <?= llama_icon((string) $lockedSection['icon']) ?>
                    </i>

                    <?= place_h($lockedSection['title']) ?>
                </h3>

                <div class="scout-report-grid locked-scout-grid">
                    <?php foreach ($lockedSection['fields'] as $lockedField): ?>
                        <div class="scout-report-item locked-scout-card">
                            <span class="locked-scout-card-title">
                                <?= place_h($lockedField) ?>
                            </span>

                            <strong class="locked-scout-card-value">
                                <i aria-hidden="true"><?= llama_icon('lock') ?></i>
                                Members only
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="locked-scout-cta">
            <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>

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

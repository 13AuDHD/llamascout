<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/place-report.php';

if ($hasMemberAccess):
?>

    <?php if (!empty($hasContributorPlaceAccess) && empty($hasGlobalMemberAccess)): ?>
        <section class="place-section place-contributor-access-note">
            <i aria-hidden="true"><?= llama_icon('key') ?></i>
            <div>
                <strong>Complete Access to this Place</strong>
                <p>
                    You originally contributed this Place, so its complete report,
                    restricted location access, gallery, and historical reports remain available
                    to your account even without a paid membership.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/overnight-options.php'; ?>

    <?php require __DIR__ . '/scout-report/index.php'; ?>

<?php else: ?>

    <?php
    $lockedScoutSections = [];
    $schemaFields = llama_place_report_fields();
    $approvedGroups =
        llama_place_report_approved_question_groups();

    $descriptionField =
        $schemaFields['description']
        ?? null;

    $lockedDescriptionIsCommunity =
        is_array($descriptionField)
        && !llama_place_report_field_visibility_allows(
            $descriptionField,
            false
        )
        && llama_place_report_field_visibility_allows(
            $descriptionField,
            true
        );

    foreach ($approvedGroups as $groupKey => [$groupLabel, $keys]) {
        $labels = [];
        $renderedCompoundQuestions = [];
        $groupIcon = 'info-circle';

        foreach ((array) $keys as $key) {
            $key =
                (string) $key;

            if (
                $key === 'description'
                || !isset($schemaFields[$key])
            ) {
                continue;
            }

            $field =
                $schemaFields[$key];

            if (
                llama_place_report_field_visibility_allows(
                    $field,
                    false
                )
                || !llama_place_report_field_visibility_allows(
                    $field,
                    true
                )
            ) {
                continue;
            }

            $compoundKey =
                trim(
                    (string) (
                        $field['approved_compound_question']
                        ?? ''
                    )
                );

            if ($compoundKey !== '') {
                if (isset($renderedCompoundQuestions[$compoundKey])) {
                    continue;
                }

                $renderedCompoundQuestions[$compoundKey] =
                    true;

                $labels[] =
                    (string) (
                        $field['approved_compound_question_label']
                        ?? $field['label']
                        ?? $compoundKey
                    );
            } else {
                $labels[] =
                    rtrim(
                        (string) (
                            $field['label']
                            ?? $key
                        ),
                        '*'
                    );
            }

            if ($groupIcon === 'info-circle') {
                $groupIcon =
                    llama_place_report_field_icon(
                        $key,
                        $field
                    );
            }
        }

        if (!$labels) {
            continue;
        }

        $lockedScoutSections[] = [
            'key' =>
                (string) $groupKey,
            'title' =>
                (string) $groupLabel,
            'icon' =>
                $groupIcon,
            'fields' =>
                $labels,
        ];
    }
    ?>

    <?php if ($lockedDescriptionIsCommunity): ?>
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
    <?php endif; ?>

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
            to unlock the answers, complete photo gallery, and
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
                    Unlock restricted locations, Scout Reports, full photo galleries,
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

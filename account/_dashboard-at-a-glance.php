<?php

declare(strict_types=1);

require_once __DIR__ . '/_verification-dashboard-alert.php';

$dashboardScoutRank =
    $showActiveScout
    && function_exists(
        'llama_current_scout_rank'
    )
        ? llama_current_scout_rank(
            $db,
            $userId
        )
        : 'none';

$dashboardScoutIsMaster =
    in_array(
        $dashboardScoutRank,
        [
            'master-scout',
            'master_scout',
        ],
        true
    );

$dashboardScoutTitle =
    $dashboardScoutIsMaster
        ? 'Master Scout Basecamp'
        : 'Scout Basecamp';

$dashboardScoutDescription =
    $dashboardScoutIsMaster
        ? 'Your Master Scout status, field work, moderation tools, and Scout activity.'
        : 'Your Scout status, field-work requirements, contributions, and Master Scout progress.';

$dashboardProfileStats =
    function_exists('llama_profile_stats')
        ? llama_profile_stats(
            $db,
            $userId
        )
        : [];
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/dashboard-layout.css"
>

<section
    class="account-dashboard-status-section"
    aria-labelledby="account-overview-heading"
>
    <div class="account-section-heading">
        <div>
            <p class="account-eyebrow">At a glance</p>
            <h2 id="account-overview-heading">
                Your Llama Scout activity
            </h2>
        </div>
    </div>

    <div
        class="account-dashboard-stat-grid"
        aria-label="Account activity"
    >

        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <?= llama_icon('star') ?>
            </span>

            <div>
                <strong>
                    <?= number_format($pointsBalance) ?>
                </strong>

                <span>Contribution points</span>
            </div>
        </div>


        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <?= llama_icon('map-pin') ?>
            </span>

            <div>
                <strong>
                    <?= number_format(
                        (int) (
                            $dashboardProfileStats['places_submitted']
                            ?? 0
                        )
                    ) ?>
                </strong>

                <span>New Places</span>
            </div>
        </div>


        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <?= llama_icon('edit') ?>
            </span>

            <div>
                <strong>
                    <?= number_format(
                        (int) (
                            $dashboardProfileStats['places_improved']
                            ?? 0
                        )
                    ) ?>
                </strong>

                <span>Updates</span>
            </div>
        </div>


        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <?= llama_icon('check') ?>
            </span>

            <div>
                <strong>
                    <?= number_format(
                        (int) (
                            $contributionCounts['total']
                            ?? 0
                        )
                    ) ?>
                </strong>

                <span>
                    Contribution<?= (int) ($contributionCounts['total'] ?? 0) === 1 ? '' : 's' ?>
                </span>
            </div>
        </div>


        <?php if ($showActiveScout): ?>

            <a
                class="account-action-card account-scout-basecamp-card account-dashboard-scout-card"
                href="/scout.php"
            >
                <?= llama_icon('binoculars') ?>

                <span>
                    <strong>
                        <?= htmlspecialchars(
                            $dashboardScoutTitle,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars(
                            $dashboardScoutDescription,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </small>
                </span>

                <i class="account-action-arrow" aria-hidden="true">
                    <?= llama_icon('arrow-right') ?>
                </i>
            </a>

        <?php endif; ?>

    </div>

</section>

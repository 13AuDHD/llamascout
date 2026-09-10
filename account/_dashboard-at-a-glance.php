<?php

declare(strict_types=1);

require_once __DIR__ . '/_verification-dashboard-alert.php';
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
            <h2 id="account-overview-heading">Your Llama Scout activity</h2>
        </div>
    </div>

    <div
        class="account-dashboard-stat-grid"
        aria-label="Account activity and achievements"
    >

        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <i
                    class="fa-solid fa-star"
                    aria-hidden="true"
                ></i>
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
                <i
                    class="fa-solid fa-award"
                    aria-hidden="true"
                ></i>
            </span>

            <div>
                <strong>
                    <?= number_format(count($earnedBadges)) ?>
                </strong>

                <span>
                    Badge<?= count($earnedBadges) === 1 ? '' : 's' ?> earned
                </span>
            </div>
        </div>


        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <i
                    class="fa-solid fa-location-dot"
                    aria-hidden="true"
                ></i>
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


        <div class="account-dashboard-stat-card">
            <span class="account-glance-icon">
                <i
                    class="fa-solid fa-bookmark"
                    aria-hidden="true"
                ></i>
            </span>

            <div>
                <strong>
                    <?= number_format(count($savedPlaces)) ?>
                </strong>

                <span>
                    Saved place<?= count($savedPlaces) === 1 ? '' : 's' ?>
                </span>
            </div>
        </div>

    </div>

</section>

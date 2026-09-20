<?php

declare(strict_types=1);

$freshnessState =
    (string) ($placeFreshness['state'] ?? 'never');

$overallLevel =
    trim((string) ($placeFreshness['overall_level'] ?? ''));
?>

<section
    class="place-freshness-bar is-<?= place_h($freshnessState) ?>"
    aria-labelledby="place-freshness-heading"
>
    <div class="place-detail-container place-freshness-inner">
        <div class="place-freshness-summary">
            <span class="place-freshness-light" aria-hidden="true"></span>

            <div>
                <p class="place-detail-eyebrow">Field freshness</p>
                <h2 id="place-freshness-heading">
                    <?php if (!empty($placeFreshness['overall_last_checked_at'])): ?>
                        Last field checked <?= place_h((string) $placeFreshness['overall_relative']) ?>
                    <?php else: ?>
                        No field check recorded yet
                    <?php endif; ?>
                </h2>

                <p>
                    <?= place_h((string) ($placeFreshness['state_description'] ?? '')) ?>
                    <?php if (!empty($placeFreshness['overall_last_checked_at'])): ?>
                        <span>
                            <?= place_h((string) $placeFreshness['overall_date_label']) ?>
                            <?php if ($overallLevel !== ''): ?>
                                · <?= place_h(llama_contribution_level_short_label($overallLevel)) ?> check
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="place-freshness-breakdown" aria-label="Field check history summary">
            <div>
                <span>Community / Member</span>
                <strong><?= place_h((string) ($placeFreshness['community_relative'] ?? 'No check yet')) ?></strong>
                <small><?= place_h((string) ($placeFreshness['community_date_label'] ?? 'No check recorded')) ?></small>
            </div>

            <div>
                <span>Scout / Admin</span>
                <strong><?= place_h((string) ($placeFreshness['scout_relative'] ?? 'No check yet')) ?></strong>
                <small><?= place_h((string) ($placeFreshness['scout_date_label'] ?? 'No check recorded')) ?></small>
            </div>
        </div>
    </div>
</section>

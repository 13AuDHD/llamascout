<?php

declare(strict_types=1);

$freshnessState = (string) ($placeFreshness['state'] ?? 'never');
$overallLevel = trim((string) ($placeFreshness['overall_level'] ?? ''));
$recordSourceValue = strtolower(trim((string) ($place['source_type'] ?? '')));

$recordSourceLabel = match ($recordSourceValue) {
    'external', 'ridb' => 'External Source',
    'community-scouted' => 'Community Scouted',
    'llama-scouted' => 'Llama Scouted',
    'legacy' => 'Legacy',
    default => 'Llama Scout',
};

$recordIsExternal = $recordSourceLabel === 'External Source';
$hasCommunityCheck = !empty($placeFreshness['community_last_checked_at'])
    || (!empty($placeFreshness['community_date_label'])
        && (string) $placeFreshness['community_date_label'] !== 'No check recorded');
$hasScoutCheck = !empty($placeFreshness['scout_last_checked_at'])
    || (!empty($placeFreshness['scout_date_label'])
        && (string) $placeFreshness['scout_date_label'] !== 'No check recorded');
?>
<link rel="stylesheet" href="/css/site/features/place-freshness-status.css">
<section
    class="place-freshness-bar place-freshness-compact is-<?= place_h($freshnessState) ?>"
    aria-labelledby="place-freshness-heading"
>
    <div class="place-detail-container place-freshness-inner">
        <div class="place-freshness-summary">
            <span class="place-freshness-light" aria-hidden="true"></span>
            <div>
                <p class="place-detail-eyebrow">Field freshness</p>
                <h2 id="place-freshness-heading">
                    <?php if (!empty($placeFreshness['overall_last_checked_at'])): ?>
                        Last field checked <?= place_h((string) ($placeFreshness['overall_relative'] ?? '')) ?>
                    <?php else: ?>
                        No field check recorded yet
                    <?php endif; ?>
                </h2>
                <p>
                    <?= place_h((string) ($placeFreshness['state_description'] ?? '')) ?>
                    <?php if (!empty($placeFreshness['overall_last_checked_at'])): ?>
                        <span>
                            <?= place_h((string) ($placeFreshness['overall_date_label'] ?? '')) ?>
                            <?php if ($overallLevel !== ''): ?>
                                · <?= place_h(llama_contribution_level_short_label($overallLevel)) ?> check
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="place-freshness-breakdown" aria-label="Record source and field check status">
            <div class="place-freshness-status-card is-complete">
                <span>Record Source</span>
                <strong><?= place_h($recordSourceLabel) ?></strong>
                <small><?= $recordIsExternal
                    ? 'Imported from an external recreation data source'
                    : 'Added through Llama Scout' ?></small>
            </div>
            <div class="place-freshness-status-card <?= $hasCommunityCheck ? 'is-complete' : 'is-pending' ?>">
                <span>Community / Member</span>
                <strong><?= place_h((string) ($placeFreshness['community_relative'] ?? 'No check yet')) ?></strong>
                <small><?= place_h((string) ($placeFreshness['community_date_label'] ?? 'No check recorded')) ?></small>
            </div>
            <div class="place-freshness-status-card <?= $hasScoutCheck ? 'is-complete' : 'is-pending' ?>">
                <span>Scout / Master Scout / Admin</span>
                <strong><?= place_h((string) ($placeFreshness['scout_relative'] ?? 'No check yet')) ?></strong>
                <small><?= place_h((string) ($placeFreshness['scout_date_label'] ?? 'No check recorded')) ?></small>
            </div>
        </div>
    </div>
</section>

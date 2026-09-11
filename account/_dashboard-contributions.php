<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/place-drafts.php';

$savedDraftCount = llama_place_draft_count(
    db(),
    (int) ($userId ?? 0)
);
?>

<section
    class="account-section"
    aria-labelledby="contributions-heading"
>
    <div class="account-section-heading">
        <div>
            <p class="account-eyebrow">Community</p>
            <h2 id="contributions-heading">Contributions</h2>
        </div>

        <span class="account-section-count">
            <?= (int) ($contributionCounts['total'] ?? 0) ?>
        </span>
    </div>

    <div class="account-action-grid">

        <a
            class="account-action-card"
            href="<?= htmlspecialchars(
                $siteUrl . '/add-place.php',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>

            <span>
                <strong>Add a place</strong>
                <small>
                    Submit a new campsite or outdoor place.
                </small>
            </span>
        </a>

        <a
            class="account-action-card"
            href="/contributions.php"
        >
            <i
                class="fa-solid fa-clock-rotate-left"
                aria-hidden="true"
            ></i>

            <span>
                <strong>My contributions</strong>
                <small>
                    <?= (int) ($contributionCounts['open'] ?? 0) ?>
                    currently awaiting review.
                </small>
            </span>
        </a>

        <a
            class="account-action-card"
            href="/saved-later.php"
        >
            <i
                class="fa-solid fa-floppy-disk"
                aria-hidden="true"
            ></i>

            <span>
                <strong>Saved for Later</strong>
                <small>
                    You have <?= $savedDraftCount ?>
                    place<?= $savedDraftCount === 1 ? '' : 's' ?>
                    to continue editing.
                </small>
            </span>
        </a>

    </div>
</section>

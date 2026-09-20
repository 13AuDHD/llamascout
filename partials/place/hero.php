<header class="place-detail-hero<?= $heroImage ? ' has-image' : ' no-image' ?>">

    <?php if ($heroImage): ?>
        <img
            class="place-detail-hero-image"
            src="<?= place_h(place_image_url($heroImage['src'] ?? '')) ?>"
            alt="<?= place_h(($heroImage['alt_text'] ?? '') ?: $place['name']) ?>"
        >
    <?php endif; ?>

    <div class="place-detail-hero-shade" aria-hidden="true"></div>

    <div class="place-detail-hero-inner">

        <a class="place-detail-back" href="/map.php">
            <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
            Explore Map
        </a>

        <div class="place-detail-hero-content">

            <div class="place-detail-title-block">
                <p class="place-detail-eyebrow">
                    <?= place_h(ucwords(str_replace(['-', '_'], ' ', (string) $place['type']))) ?>
                </p>

                <h1><?= place_h($place['name']) ?></h1>

                <?php if ($locationParts): ?>
                    <p class="place-detail-location">
                        <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                        <?= place_h(implode(', ', $locationParts)) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($place['land_manager'])): ?>
                    <p class="place-detail-land">
                        <?= place_h($place['land_manager']) ?>
                        <?php if (!empty($place['land_type'])): ?>
                            / <?= place_h($place['land_type']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="place-documentation-meta">
                    <?php if (!empty($documentationLevel['label'])): ?>
                        <div
                            class="place-contribution-level is-<?= place_h((string) $documentationLevel['level']) ?>"
                            title="<?= place_h((string) ($documentationLevel['description'] ?? '')) ?>"
                        >
                            <i aria-hidden="true"><?= llama_icon((string) ($documentationLevel['icon'] ?? 'users')) ?></i>
                            <span><?= place_h((string) $documentationLevel['label']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div
                        class="place-report-completeness"
                        title="How much of the structured Place Report currently has an observed answer."
                    >
                        <i aria-hidden="true"><?= llama_icon('list-check') ?></i>
                        <span>
                            Report completeness
                            <?= (int) ($reportCompleteness['percent'] ?? 0) ?>%
                        </span>
                    </div>
                </div>
            </div>

            <div class="place-detail-actions">
                <?php if ($userId > 0): ?>
                    <form method="post" class="place-save-form">
                        <input type="hidden" name="csrf_token" value="<?= place_h(saved_places_csrf_token()) ?>">
                        <input type="hidden" name="saved_place_action" value="<?= $isSaved ? 'remove' : 'save' ?>">
                        <button
                            type="submit"
                            class="place-detail-action-button<?= $isSaved ? ' is-saved' : '' ?>"
                            aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                        >
                            <i aria-hidden="true"><?= llama_icon('bookmark') ?></i>
                            <?= $isSaved ? 'Saved' : 'Save Place' ?>
                        </button>
                    </form>

                    <?php if ($hasGlobalMemberAccess): ?>
                        <a
                            class="place-detail-action-button"
                            href="/compare.php?places=<?= rawurlencode((string) $place['slug']) ?>"
                        >
                            <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>
                            Compare
                        </a>
                    <?php endif; ?>

                    <?php if ($canCheckIn): ?>
                        <a
                            class="place-detail-action-button"
                            href="/check-in.php?place=<?= rawurlencode((string) $place['slug']) ?>"
                        >
                            <i aria-hidden="true"><?= llama_icon('current-location') ?></i>
                            Check In
                        </a>
                    <?php endif; ?>

                    <?php if ($canSuggestUpdate): ?>
                        <a
                            class="place-detail-action-button"
                            href="https://account.llamascout.com/update-place.php?slug=<?= rawurlencode((string) $place['slug']) ?>"
                        >
                            <i aria-hidden="true"><?= llama_icon('edit') ?></i>
                            Suggest Update
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="place-detail-action-button" href="https://account.llamascout.com/login.php">
                        <i aria-hidden="true"><?= llama_icon('bookmark') ?></i>
                        Sign in to save
                    </a>
                <?php endif; ?>

                <button
                    class="place-detail-action-button llama-share-button"
                    type="button"
                    data-share
                    data-share-title="<?= place_h($place['name'] . ' | Llama Scout') ?>"
                    data-share-text="<?= place_h('Check out ' . $place['name'] . ' on Llama Scout.') ?>"
                    data-share-url="<?= place_h($canonicalUrl) ?>"
                >
                    <i aria-hidden="true"><?= llama_icon('share') ?></i>
                    <span data-share-label>Share</span>
                </button>
            </div>

        </div>
    </div>
</header>

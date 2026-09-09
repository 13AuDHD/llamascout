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
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
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
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
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
                            <i class="<?= $isSaved ? 'fa-solid' : 'fa-regular' ?> fa-bookmark" aria-hidden="true"></i>
                            <?= $isSaved ? 'Saved' : 'Save Place' ?>
                        </button>
                    </form>

                    <a
                        class="place-detail-action-button"
                        href="https://account.llamascout.com/update-place.php?place_id=<?= (int) $place['id'] ?>"
                    >
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        Suggest Update
                    </a>
                <?php else: ?>
                    <a class="place-detail-action-button" href="https://account.llamascout.com/login.php">
                        <i class="fa-regular fa-bookmark" aria-hidden="true"></i>
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
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    <span data-share-label>Share</span>
                </button>
            </div>

        </div>
    </div>
</header>

<?php

declare(strict_types=1);

// The Place gallery is rendered by overnight-options.php, immediately after
// the location selectors for Complete Access. Public Place pages render this
// same partial above the locked Scout Report. Site gallery rules are unchanged.
if ($canPreviewPlacePhotos && $galleryImages):
?>
    <section
        class="place-photo-gallery-section"
        data-explorer-place-photos
        aria-labelledby="place-photo-gallery-heading"
    >
        <div class="place-photo-gallery-heading">
            <div>
                <p class="place-detail-eyebrow">Photos</p>
                <h2 id="place-photo-gallery-heading">Photo gallery</h2>
            </div>
            <span><?= count($galleryImages) ?> <?= count($galleryImages) === 1 ? 'photo' : 'photos' ?></span>
        </div>

        <div
            class="place-photo-gallery<?= $canOpenPlaceGallery ? '' : ' is-preview' ?>"
            <?= $canOpenPlaceGallery ? 'data-place-gallery' : '' ?>
        >
            <?php foreach ($galleryImages as $index => $image): ?>
                <?php if ($canOpenPlaceGallery): ?>
                    <button
                        class="place-photo-thumb<?= !empty($image['is_featured']) ? ' is-featured' : '' ?>"
                        type="button"
                        data-gallery-open="<?= (int) $index ?>"
                        aria-label="Open photo <?= (int) $index + 1 ?> of <?= count($galleryImages) ?>"
                    >
                        <img
                            src="<?= place_h(place_image_url($image['src'] ?? '')) ?>"
                            alt="<?= place_h(($image['alt_text'] ?? '') ?: $place['name']) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                        <?php if (!empty($image['is_featured'])): ?>
                            <span class="place-photo-featured-label">Hero</span>
                        <?php endif; ?>
                    </button>
                <?php else: ?>
                    <div class="place-photo-thumb is-preview">
                        <img
                            src="<?= place_h(place_image_url($image['src'] ?? '')) ?>"
                            alt="<?= place_h(($image['alt_text'] ?? '') ?: $place['name']) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                        <?php if (!empty($image['is_featured'])): ?>
                            <span class="place-photo-featured-label">Hero</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="place-photo-gallery-help">
            <?= $canOpenPlaceGallery
                ? 'Tap a photo to see it larger.'
                : 'Sign in with a free account to enlarge and browse these photos.' ?>
        </p>
    </section>

    <?php if ($canOpenPlaceGallery): ?>
        <dialog class="place-gallery-lightbox" id="place-gallery-lightbox" aria-label="Photo viewer">
            <div class="place-gallery-lightbox-inner">
                <div class="place-gallery-lightbox-top">
                    <span id="place-gallery-counter"></span>
                    <button type="button" class="place-gallery-close" id="place-gallery-close" aria-label="Close photo viewer">
                        <i aria-hidden="true"><?= llama_icon('x') ?></i>
                    </button>
                </div>
                <div class="place-gallery-stage">
                    <button type="button" class="place-gallery-arrow is-previous" id="place-gallery-previous" aria-label="Previous photo">
                        <i aria-hidden="true"><?= llama_icon('chevron-left') ?></i>
                    </button>
                    <img id="place-gallery-large-image" src="" alt="">
                    <button type="button" class="place-gallery-arrow is-next" id="place-gallery-next" aria-label="Next photo">
                        <i aria-hidden="true"><?= llama_icon('chevron-right') ?></i>
                    </button>
                </div>
                <p class="place-gallery-caption" id="place-gallery-caption"></p>
                <div class="place-gallery-lightbox-thumbs" id="place-gallery-lightbox-thumbs"></div>
            </div>
        </dialog>
    <?php endif; ?>
<?php endif; ?>

<?php if ($hasMemberAccess && $galleryImages): ?>
    <section class="place-photo-gallery-section" aria-labelledby="place-photo-gallery-heading">
        <div class="place-detail-container">
            <div class="place-photo-gallery-heading">
                <div>
                    <p class="place-detail-eyebrow">Photos</p>
                    <h2 id="place-photo-gallery-heading">Photo gallery</h2>
                </div>
                <span><?= count($galleryImages) ?> <?= count($galleryImages) === 1 ? 'photo' : 'photos' ?></span>
            </div>

            <div class="place-photo-gallery" data-place-gallery>
                <?php foreach ($galleryImages as $index => $image): ?>
                    <button
                        class="place-photo-thumb<?= !empty($image['is_featured']) ? ' is-featured' : '' ?>"
                        type="button"
                        data-place-gallery-open="<?= (int) $index ?>"
                        aria-label="Open photo <?= (int) $index + 1 ?> of <?= count($galleryImages) ?>"
                    >
                        <img
                            src="<?= place_h(place_image_url($image['src'] ?? '')) ?>"
                            alt="<?= place_h(($image['alt_text'] ?? '') ?: $place['name']) ?>"
                            loading="<?= $index < 4 ? 'eager' : 'lazy' ?>"
                        >
                        <?php if (!empty($image['is_featured'])): ?>
                            <span class="place-photo-featured-label">Hero</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="place-photo-gallery-help">
                Tap any photo to view it larger and move through the full gallery.
            </p>
        </div>
    </section>

    <dialog class="place-gallery-lightbox" id="place-gallery-lightbox" aria-label="Place photo viewer">
        <div class="place-gallery-lightbox-inner">
            <div class="place-gallery-lightbox-top">
                <span id="place-gallery-counter"></span>
                <button type="button" class="place-gallery-close" id="place-gallery-close" aria-label="Close photo viewer">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="place-gallery-stage">
                <button type="button" class="place-gallery-arrow is-previous" id="place-gallery-previous" aria-label="Previous photo">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>

                <img id="place-gallery-large-image" src="" alt="">

                <button type="button" class="place-gallery-arrow is-next" id="place-gallery-next" aria-label="Next photo">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>

            <p class="place-gallery-caption" id="place-gallery-caption"></p>

            <div class="place-gallery-lightbox-thumbs" id="place-gallery-lightbox-thumbs">
                <?php foreach ($galleryImages as $index => $image): ?>
                    <button type="button" data-place-gallery-jump="<?= (int) $index ?>" aria-label="View photo <?= (int) $index + 1 ?>">
                        <img src="<?= place_h(place_image_url($image['src'] ?? '')) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </dialog>
<?php endif; ?>

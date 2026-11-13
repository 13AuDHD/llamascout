<?php

declare(strict_types=1);

/**
 * Contributor photos are evidence in a Resource submission, not public
 * gallery content until an Admin approves that submission.
 */
$pendingPhotos = is_array($form['_photos'] ?? null)
    ? (array) $form['_photos']
    : [];
$publishedPhotos = $resource && !empty($resource['id'])
    ? llama_resource_images($db, (int) $resource['id'])
    : [];
$publicPhotoCount = count($publishedPhotos);

// A revision can carry a previously proposed order. Show that proposal
// without changing the public gallery until the moderator approves it.
$proposedOrder = is_array($form['_published_photo_order'] ?? null)
    ? array_values(array_map('intval', (array) $form['_published_photo_order']))
    : [];
$publishedIds = array_map(static fn (array $photo): int => (int) $photo['id'], $publishedPhotos);
$sortedPublishedIds = $publishedIds;
$sortedProposedIds = $proposedOrder;
sort($sortedPublishedIds);
sort($sortedProposedIds);
if ($proposedOrder && $sortedProposedIds === $sortedPublishedIds) {
    $byId = array_column($publishedPhotos, null, 'id');
    $publishedPhotos = array_values(array_map(
        static fn (int $id): array => $byId[$id],
        $proposedOrder
    ));
}
$resourcePhotoLimit = llama_location_gallery_photo_limit('resource');
$photoSpace = max(0, $resourcePhotoLimit - $publicPhotoCount - count($pendingPhotos));
$photoContext = !empty($contributionResourceId) ? 'update-resource' : 'add-resource';
?>
<details open class="contribution-section contribution-resource-photos">
    <summary><span><?= llama_icon('camera') ?> Resource Photos</span></summary>
    <div class="contribution-section-body">
        <p>
            Add photos that help document this Resource: entrances, facilities,
            pumps, toilets, water, signs, and other useful details.
            Up to <?= (int) $resourcePhotoLimit ?> photos per Resource.
            New photos are reviewed before publication.
        </p>
        <?php if ($publishedPhotos): ?>
            <div class="add-place-existing-photos resource-published-photos">
                <strong>Published Resource photos (<?= (int) $publicPhotoCount ?>)</strong>
                <p>These photos are already on the Resource page. Drag a handle
                   to propose a different order. The first photo is the Hero.
                   Any change is reviewed before it appears publicly.</p>
                <script type="application/json" data-resource-photo-catalog><?=
                    json_encode(array_map(static fn (array $photo): array => [
                        'id' => (int) $photo['id'],
                        'src' => (string) $photo['src'],
                        'url' => llama_photo_public_url((string) $photo['src']),
                        'alt' => (string) ($photo['alt_text'] ?? ''),
                    ], $publishedPhotos),
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                        | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                ?></script>
                <input type="hidden" name="resource_published_photo_order"
                       value="<?= $e(json_encode(
                           array_map(static fn (array $photo): int => (int) $photo['id'], $publishedPhotos),
                           JSON_THROW_ON_ERROR
                       )) ?>">
                <div class="add-place-existing-photo-grid admin-resource-gallery resource-existing-gallery"
                     data-resource-photo-gallery data-resource-gallery-proposal
                     data-resource-gallery-id="<?= (int) ($resource['id'] ?? 0) ?>">
                    <?php foreach ($publishedPhotos as $index => $photo): ?>
                        <article class="add-place-existing-photo admin-attached-photo"
                                 data-admin-photo-id="<?= (int) $photo['id'] ?>">
                            <div class="admin-attached-photo-preview">
                                <img src="<?= $e(llama_photo_public_url((string) $photo['src'])) ?>"
                                     alt="<?= $e((string) ($photo['alt_text'] ?? 'Resource photo')) ?>"
                                     loading="lazy" draggable="false">
                                <?php if ($index === 0): ?>
                                    <span class="admin-attached-photo-hero">Hero</span>
                                <?php endif; ?>
                                <button class="admin-attached-photo-handle" type="button"
                                        aria-label="Drag photo to reorder" title="Drag to reorder">⠿</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($pendingPhotos): ?>
            <div class="add-place-existing-photos">
                <strong>Photos already submitted for this Resource</strong>
                <div class="add-place-existing-photo-grid">
                    <?php foreach ($pendingPhotos as $photo): ?>
                        <label class="add-place-existing-photo">
                            <img src="<?= $e(llama_photo_public_url((string) ($photo['path'] ?? ''))) ?>"
                                 alt="<?= $e((string) ($photo['alt'] ?? 'Submitted Resource photo')) ?>"
                                 loading="lazy">
                            <span>
                                <input type="checkbox" name="resource_remove_photos[]"
                                       value="<?= $e((string) ($photo['path'] ?? '')) ?>">
                                Remove on resubmission
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($photoSpace > 0): ?>
            <input type="hidden" name="resource_photo_stage_token"
                   value="<?= $e((string) ($_POST['resource_photo_stage_token'] ?? '')) ?>">
            <input type="hidden" name="resource_photos_json"
                   value="<?= $e((string) ($_POST['resource_photos_json'] ?? '[]')) ?>">
            <div data-photo-uploader
                 data-photo-context="<?= $e($photoContext) ?>"
                 data-photo-existing-count="<?= (int) ($publicPhotoCount + count($pendingPhotos)) ?>"
                 data-photo-max="<?= (int) $photoSpace ?>"
                 data-photo-token-field="resource_photo_stage_token"
                 data-photo-field="resource_photos_json"
                 data-photo-csrf="<?= $e(llama_photo_csrf_token()) ?>"
                 data-photo-endpoint="/photo-upload.php"
                 data-photo-title="Photos of this Resource"
                 data-photo-save-action="Submit Resource for Review"
                 data-photo-help="Photos are attached to this Resource submission and reviewed before they appear publicly."></div>
        <?php else: ?>
            <p>All 10 Resource photo positions are occupied. Remove an existing
               submission photo before adding another.</p>
        <?php endif; ?>
    </div>
</details>

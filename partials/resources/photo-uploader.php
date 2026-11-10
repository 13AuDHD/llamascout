<?php

declare(strict_types=1);

/**
 * Shared Admin Resource uploader. Place and Site use this same staged
 * photo component, but Resource photos commit to resource_images.
 *
 * No standalone duplicate Resource upload form is required.
 */
if (empty($resourceStaffFields) || empty($resource['id'])) {
    return;
}

$resourcePhotoTargetId = (int) $resource['id'];
$resourceSavedPhotos = llama_resource_images($db, $resourcePhotoTargetId);
$resourcePhotoLimit = llama_location_gallery_photo_limit('resource');
$resourcePhotoRemaining = max(0, $resourcePhotoLimit - count($resourceSavedPhotos));
?>
<details open class="contribution-section contribution-resource-photos">
    <summary><span><?= llama_icon('camera') ?> Resource Photos</span></summary>
    <div class="contribution-section-body">
        <p>
            <?= (int) count($resourceSavedPhotos) ?> of <?= (int) $resourcePhotoLimit ?>
            photos attached to this Resource. Photos of services, entrances,
            facilities, and helpful signs belong here.
        </p>
        <p>Drag photos to reorder. The first remaining photo becomes Hero.
           Changes to descriptions, order and removals save with the Resource.</p>
        <script type="application/json" data-resource-photo-catalog><?=
            json_encode(array_map(static fn (array $photo): array => [
                'id' => (int) $photo['id'],
                'src' => (string) $photo['src'],
                'url' => llama_resource_image_url($photo['src']),
                'alt' => (string) ($photo['alt_text'] ?? ''),
                'sort_order' => (int) ($photo['sort_order'] ?? 0),
                'is_featured' => (int) ($photo['is_featured'] ?? 0),
            ], $resourceSavedPhotos), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        ?></script>
        <input type="hidden" name="resource_gallery_json" value="">
        <div class="add-place-existing-photo-grid admin-resource-gallery"
             data-resource-photo-gallery data-resource-gallery-id="<?= (int) $resourcePhotoTargetId ?>"></div>
        <?php if ($resourcePhotoRemaining > 0): ?>
        <input type="hidden" name="resource_photo_stage_token"
               value="<?= $e($_POST['resource_photo_stage_token'] ?? '') ?>">
        <input type="hidden" name="resource_photos_json"
               value="<?= $e($_POST['resource_photos_json'] ?? '[]') ?>">
        <div data-photo-uploader
             data-photo-context="admin-resource"
             data-photo-max="<?= (int) $resourcePhotoRemaining ?>"
             data-photo-token-field="resource_photo_stage_token"
             data-photo-field="resource_photos_json"
             data-photo-csrf="<?= $e(llama_photo_csrf_token()) ?>"
             data-photo-endpoint="/photo-upload.php"
             data-photo-title="Photos of <?= $e((string) ($resource['name'] ?? 'this Resource')) ?>"
             data-photo-save-action="Save Resource"
             data-photo-help="Upload photos here, then save the Resource to attach them. You may add <?= (int) $resourcePhotoRemaining ?> more."></div>
        <?php else: ?>
        <p>This Resource has reached its 10-photo limit. Remove an existing photo before adding another.</p>
        <?php endif; ?>
    </div>
</details>

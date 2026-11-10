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
        <?php if ($resourceSavedPhotos): ?>
        <div class="add-place-existing-photo-grid">
            <?php foreach ($resourceSavedPhotos as $photo): ?>
                <div class="add-place-existing-photo">
                    <img src="<?= $e(llama_resource_image_url($photo['src'] ?? '')) ?>"
                         alt="<?= $e($photo['alt_text'] ?? 'Resource photo') ?>" loading="lazy">
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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

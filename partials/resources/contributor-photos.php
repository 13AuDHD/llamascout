<?php

declare(strict_types=1);

/**
 * Contributor photos are evidence in a Resource submission, not public
 * gallery content until an Admin approves that submission.
 */
$pendingPhotos = is_array($form['_photos'] ?? null)
    ? (array) $form['_photos']
    : [];
$publicPhotoCount = $resource && !empty($resource['id'])
    ? count(llama_resource_images($db, (int) $resource['id']))
    : 0;
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

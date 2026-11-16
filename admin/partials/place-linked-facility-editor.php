<?php

declare(strict_types=1);

// This is the Admin editor for an approved, Place-linked Facility. It is NOT
// the contributor Add Facility workflow and must never create a new record.
if (!is_array($selectedFacilityResource ?? null)) {
    return;
}

$resource = $selectedFacilityResource;
$form = (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($action ?? '') === 'save-linked-facility'
    && ($error ?? '') !== ''
    && (int) ($_POST['facility_resource_id'] ?? 0) === (int) $resource['id']
) ? array_replace($resource, $_POST) : $resource;
$resourceStaffFields = true;
$contributionIsFacility = true;
$contributionParentPlaceName = (string) ($place['name'] ?? '');
?>
<section class="admin-place-linked-facility-editor"
         data-admin-resource-facility-editor hidden>
    <header class="admin-place-report-header">
        <p>Place Administration</p>
        <h2>Edit Facility</h2>
        <span class="admin-place-muted">
            Edit this Facility's location, Services, Details, Experience, and Photos.
            Changes save directly to the existing Facility.
        </span>
    </header>

    <form method="post" class="resource-editor admin-linked-facility-form"
          data-resource-editor action="/place.php?id=<?= (int) $placeId ?>">
        <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
        <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
        <input type="hidden" name="place_admin_action" value="save-linked-facility">
        <input type="hidden" name="facility_resource_id" value="<?= (int) $resource['id'] ?>">
        <input type="hidden" name="parent_place_id" value="<?= (int) $placeId ?>">
        <?php require dirname(__DIR__, 2) . '/partials/resources/fields.php'; ?>
        <?php require dirname(__DIR__, 2) . '/partials/resources/photo-uploader.php'; ?>
        <div class="admin-place-report-savebar">
            <button class="admin-button" type="submit">
                <?= llama_icon('device-floppy') ?> Save Facility
            </button>
        </div>
    </form>
</section>

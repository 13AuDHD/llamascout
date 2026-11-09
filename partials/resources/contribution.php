<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/resource-submissions.php';

$resource = $contributionResource ?? [];
$form = $contributionResourceInput ?? $resource;
$resourceStaffFields = $contributionResourceAdmin ?? false;
$resourceEditorUrl = $resourceStaffFields
    ? 'https://admin.llamascout.com/resources.php'
    : 'https://llamascout.com/resource-contribution.php';
?>
<div data-contribution-resource <?= empty($standaloneResourceForm) ? 'hidden' : '' ?>>
    <?php if ($resourceStaffFields && isset($contributionPlaceId)): ?>
        <form method="get" action="/place.php" class="resource-target-picker">
            <input type="hidden" name="id" value="<?= (int) $contributionPlaceId ?>">
            <input type="hidden" name="mode" value="resource">
            <label>
                <span>Resource to edit</span>
                <select name="resource_id" required>
                    <option value="">Choose a Resource</option>
                    <?php foreach ($contributionResourceOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= (int) ($resource['id'] ?? 0) === (int) $option['id'] ? 'selected' : '' ?>>
                            <?= $e($option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="admin-button is-muted">Open</button>
        </form>
    <?php endif; ?>

    <?php if (!$resourceStaffFields && isset($contributionResourceOptions)): ?>
        <form method="get" action="https://account.llamascout.com/update-place.php" class="resource-target-picker">
            <input type="hidden" name="slug" value="<?= $e($contributionReturnPlace) ?>">
            <input type="hidden" name="mode" value="resource">
            <label>
                <span>Resource to update</span>
                <select name="resource_id" required>
                    <option value="">Choose a Resource</option>
                    <?php foreach ($contributionResourceOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= (int) ($resource['id'] ?? 0) === (int) $option['id'] ? 'selected' : '' ?>>
                            <?= $e($option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="contribution-submit">Open</button>
        </form>
    <?php endif; ?>

    <details open class="contribution-section contribution-resource-panel">
        <summary><span><?= llama_icon('info-circle') ?> Resources</span></summary>
        <div class="contribution-section-body">
            <?php if (!admin_resources_tables_ready($db) || (!$resourceStaffFields && !llama_resource_submissions_ready($db))): ?>
                <p>Resource submissions are currently unavailable.</p>
            <?php elseif (!$resource && ($resourceStaffFields || isset($contributionResourceOptions))): ?>
                <p>Choose a Resource above to <?= $resourceStaffFields ? 'edit it' : 'suggest an update' ?>.</p>
            <?php else: ?>
                <form method="post" action="<?= $e($resourceEditorUrl) ?>" class="contribution-resource-form resource-editor" data-resource-editor>
                    <input type="hidden" name="csrf_token" value="<?= $e($resourceStaffFields ? moderation_csrf_token() : community_csrf_token()) ?>">
                    <?php if ($resourceStaffFields): ?>
                        <input type="hidden" name="resource_action" value="save">
                        <input type="hidden" name="resource_id" value="<?= (int) ($resource['id'] ?? 0) ?>">
                        <input type="hidden" name="return_place_id" value="<?= (int) ($contributionPlaceId ?? 0) ?>">
                    <?php else: ?>
                        <input type="hidden" name="resource_record_id" value="<?= (int) ($contributionResourceId ?? $resource['id'] ?? 0) ?>">
                        <input type="hidden" name="resource_revision_id" value="<?= (int) ($contributionRevisionId ?? 0) ?>">
                        <input type="hidden" name="resource_snapshot" value="<?= $e($contributionResourceSnapshot ?? ($resource ? llama_resource_snapshot($resource) : '')) ?>">
                        <input type="hidden" name="return_place" value="<?= $e($contributionReturnPlace ?? '') ?>">
                        <input type="hidden" name="return_draft" value="<?= (int) ($contributionReturnDraft ?? 0) ?>">
                    <?php endif; ?>
                    <?php require __DIR__ . '/fields.php'; ?>
                    <div class="<?= $resourceStaffFields ? 'admin-place-report-savebar' : 'contribution-actions add-place-submit-bar' ?>">
                        <button class="<?= $resourceStaffFields ? 'admin-button' : 'contribution-submit' ?>" type="submit">
                            <?= llama_icon($resourceStaffFields ? 'device-floppy' : 'send') ?>
                            <?= $resourceStaffFields ? 'Save Resource' : (!empty($contributionRevisionId)
                                ? 'Resubmit for Review' : ($resource ? 'Submit Update for Review' : 'Submit Resource for Review')) ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </details>
</div>
<script defer src="https://llamascout.com/js/admin/resources.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/js/admin/resources.js') ?>"></script>

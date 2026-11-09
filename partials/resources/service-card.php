<?php

declare(strict_types=1);

$fieldName = 'services[' . $e($serviceType) . ']';
?>
<section class="resource-service-card" data-resource-service-card="<?= $e($serviceType) ?>">
    <header>
        <h4 data-resource-service-title><?= $e($serviceLabel) ?></h4>
        <button type="button" class="resource-service-button" data-resource-service-remove hidden
            aria-label="<?= $e('Remove ' . $serviceLabel) ?>">Remove</button>
    </header>

    <input type="hidden" name="<?= $fieldName ?>[enabled]" value="1">
    <div class="admin-resource-form-grid">
        <label>
            <span>Status</span>
            <select name="<?= $fieldName ?>[status]">
                <?php foreach (llama_resource_statuses() as $value => $label): ?>
                    <option value="<?= $e($value) ?>" <?= ($service['status'] ?? 'unknown') === $value ? 'selected' : '' ?>>
                        <?= $e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Fee</span>
            <select name="<?= $fieldName ?>[fee_status]">
                <?php foreach (llama_resource_fee_statuses() as $value => $label): ?>
                    <option value="<?= $e($value) ?>" <?= ($service['fee_status'] ?? 'unknown') === $value ? 'selected' : '' ?>>
                        <?= $e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="admin-resource-field-wide">
            <span>Fee details</span>
            <input type="text" name="<?= $fieldName ?>[fee_description]" maxlength="255"
                value="<?= $e($service['fee_description'] ?? '') ?>" placeholder="Example: $10 or free with fuel">
        </label>

        <label class="admin-resource-field-wide">
            <span>Access notes</span>
            <input type="text" name="<?= $fieldName ?>[access_notes]"
                value="<?= $e($service['access_notes'] ?? '') ?>" placeholder="Example: Ask cashier for key">
        </label>

        <label class="admin-resource-field-wide">
            <span>Service notes</span>
            <input type="text" name="<?= $fieldName ?>[notes]" value="<?= $e($service['notes'] ?? '') ?>">
        </label>
    </div>
</section>

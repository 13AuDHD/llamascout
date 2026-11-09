<section class="admin-panel admin-user-filter-panel">

<form class="admin-place-filters" method="get">
    <label class="admin-user-search">
        <span>Search <?= moderation_e($inventoryName) ?>s</span>
        <div>
            <i aria-hidden="true"><?= llama_icon('search') ?></i>
            <input type="search" name="q" value="<?= moderation_e($search) ?>"
                placeholder="<?= moderation_e($searchPlaceholder) ?>">
        </div>
    </label>

    <label>
        <span><?= moderation_e($filterLabel) ?></span>
        <select name="<?= moderation_e($filterName) ?>">
            <option value=""><?= moderation_e($filterPlaceholder) ?></option>
            <?php foreach ($filterOptions as $option => $label): ?>
                <option value="<?= moderation_e($option) ?>" <?= $filterValue === $option ? 'selected' : '' ?>>
                    <?= moderation_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <span>State</span>
        <select name="state">
            <option value="">All states</option>
            <?php foreach ($states as $option): ?>
                <option value="<?= moderation_e($option) ?>" <?= $state === $option ? 'selected' : '' ?>>
                    <?= moderation_e($option) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div class="admin-user-filter-actions">
        <button class="admin-button" type="submit">Filter</button>
        <a class="admin-button is-muted" href="<?= moderation_e($inventoryUrl) ?>">Clear</a>
    </div>
</form>

</section>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Canonical <?= moderation_e($inventoryName) ?> Database</p>
        <h2><?= number_format(count($inventoryRows)) ?> <?= moderation_e($inventoryName) ?>s shown</h2>
    </div>

    <a class="admin-button" href="<?= moderation_e($submissionsUrl) ?>">Review new submissions</a>
</header>

<?php if (!$inventoryRows): ?>

<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('map') ?></i>
    <h3>No <?= moderation_e($inventoryName) ?>s found.</h3>
    <p>Try changing the current filters.</p>
</div>

<?php else: ?>

<div class="admin-place-list">
<?php foreach ($inventoryRows as $row): ?>
    <?php
    $services = $row['services'] ?? [];
    $location = implode(' · ', array_filter($row['location']));
    ?>
    <article class="admin-place-list-card<?= $services ? ' has-service-pills' : '' ?>">
        <div class="admin-place-list-image">
            <?php if ($row['image_url'] !== ''): ?>
                <img src="<?= moderation_e($row['image_url']) ?>" alt="" loading="lazy">
            <?php else: ?>
                <i aria-hidden="true"><?= llama_icon('photo') ?></i>
            <?php endif; ?>
        </div>

        <div class="admin-place-list-main">
            <div class="admin-place-list-heading">
                <div>
                    <span><?= moderation_e($inventoryName) ?> #<?= (int) $row['id'] ?></span>
                    <h2><?= moderation_e($row['name']) ?></h2>
                </div>
                <?php if ($services): ?>
                    <div class="admin-resource-list-badges">
                <?php endif; ?>
                <span class="admin-status-pill" data-status-tone="<?= moderation_e($row['status_tone']) ?>">
                    <?= moderation_e($row['status']) ?>
                </span>
                <?php if ($services): ?>
                        <ul class="admin-resource-list-services" aria-label="Services">
                            <?php foreach ($services as $service): ?>
                                <li title="<?= moderation_e($service) ?>"><?= moderation_e($service) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <p<?= $services && $location !== '' ? ' title="' . moderation_e($location) . '"' : '' ?>><?= moderation_e($location) ?></p>

            <div class="admin-place-list-facts">
                <?php foreach ($row['facts'] as $fact): ?>
                    <span<?= !empty($fact['alert']) ? ' class="has-alert"' : '' ?>>
                        <?php if (!empty($fact['icon'])): ?>
                            <i aria-hidden="true"><?= llama_icon($fact['icon']) ?></i>
                        <?php endif; ?>
                        <?= moderation_e($fact['text']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-place-list-actions">
            <a class="admin-button" href="<?= moderation_e($row['manage_url']) ?>">Manage</a>
            <?php if ($row['view_url'] !== ''): ?>
                <a class="admin-button is-muted" href="<?= moderation_e($row['view_url']) ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>

<?php endif; ?>

</section>

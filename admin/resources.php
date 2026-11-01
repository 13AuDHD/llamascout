<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-resources.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$tablesReady = admin_resources_tables_ready($db);
$notice = '';
$error = '';
$resourceId =
    max(
        0,
        (int) (
            $_GET['id']
            ?? $_POST['resource_id']
            ?? 0
        )
    );

if (
    $tablesReady
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['resource_action']
                        ?? ''
                    )
                );

            if ($action === 'save') {
                $resourceId =
                    admin_resource_save(
                        $db,
                        $actorUserId,
                        $resourceId,
                        $_POST
                    );

                header(
                    'Location: /resources.php?id='
                    . $resourceId
                    . '&saved=1'
                );

                exit;
            }

            if ($action === 'deactivate') {
                admin_resource_deactivate(
                    $db,
                    $actorUserId,
                    $resourceId
                );

                header(
                    'Location: /resources.php?deactivated=1'
                );

                exit;
            }

            throw new InvalidArgumentException(
                'Choose a valid Resource action.'
            );

        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.resources',
                    [
                        'resource_id' =>
                            $resourceId,
                    ],
                    [
                        InvalidArgumentException::class,
                        RuntimeException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Resource could not be saved.',
                        $reference
                    );
        }
    }
}

if (
    $tablesReady
    && (string) ($_GET['saved'] ?? '') === '1'
) {
    $notice = 'Resource saved.';
}

if (
    $tablesReady
    && (string) ($_GET['deactivated'] ?? '') === '1'
) {
    $notice = 'Resource deactivated.';
}

$resources =
    $tablesReady
        ? admin_resources_list($db)
        : [];

$resource =
    $tablesReady
    && $resourceId > 0
        ? admin_resource_get(
            $db,
            $resourceId
        )
        : [];

$editing =
    !empty($resource);

$form =
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $error !== ''
        ? $_POST
        : $resource;

$selectedServices = [];

foreach (
    (array) (
        $resource['services']
        ?? []
    )
    as $service
) {
    if (!is_array($service)) {
        continue;
    }

    $serviceType =
        (string) (
            $service['service_type']
            ?? ''
        );

    if ($serviceType !== '') {
        $selectedServices[$serviceType] =
            $service;
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $error !== ''
) {
    $selectedServices =
        is_array($_POST['services'] ?? null)
            ? $_POST['services']
            : [];
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    $editing
        ? 'Edit Resource'
        : 'Resources';

$adminPageEyebrow =
    'Map Data';

$adminActiveNav =
    'resources';

require __DIR__ . '/_header.php';

$e =
    static fn (mixed $value): string =>
        moderation_e(
            (string) $value
        );

$datetimeLocal =
    static function (mixed $value): string {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($raw))
                ->format('Y-m-d\TH:i');
        } catch (Throwable) {
            return '';
        }
    };
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= $e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= $e($error) ?>
</div>
<?php endif; ?>

<?php if (!$tablesReady): ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Setup</p>
            <h2>Resource database migration required</h2>
        </div>
    </header>

    <div class="admin-user-action-box">
        <p>
            Run
            <code>tools/migrations/20261101_v3_003_resources.sql</code>
            against the Llama Scout database, then reload this page.
        </p>
    </div>
</section>

<?php else: ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>
                <?= $editing ? 'Standalone map entity' : 'New map entity' ?>
            </p>

            <h2>
                <?= $editing
                    ? 'Edit ' . $e($resource['name'] ?? 'Resource')
                    : 'Create Resource' ?>
            </h2>
        </div>

        <?php if ($editing): ?>
        <a
            class="admin-button is-muted"
            href="/resources.php"
        >
            New Resource
        </a>
        <?php endif; ?>
    </header>

    <form
        class="admin-user-action-box"
        method="post"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="resource_action"
            value="save"
        >

        <input
            type="hidden"
            name="resource_id"
            value="<?= (int) $resourceId ?>"
        >

        <div class="admin-resource-form-grid">

            <label>
                <span>Resource name</span>
                <input
                    type="text"
                    name="name"
                    maxlength="160"
                    required
                    value="<?= $e($form['name'] ?? '') ?>"
                    placeholder="Highway Market"
                >
            </label>

            <label>
                <span>Resource type</span>
                <select
                    name="resource_type"
                    required
                >
                    <option value="">Choose type</option>

                    <?php foreach (
                        llama_resource_types()
                        as $value => $label
                    ): ?>
                    <option
                        value="<?= $e($value) ?>"
                        <?= (string) ($form['resource_type'] ?? '') === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Overall status</span>
                <select name="status">
                    <?php foreach (
                        llama_resource_statuses()
                        as $value => $label
                    ): ?>
                    <option
                        value="<?= $e($value) ?>"
                        <?= (string) ($form['status'] ?? 'unknown') === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Latitude</span>
                <input
                    type="number"
                    name="latitude"
                    step="0.0000001"
                    min="-90"
                    max="90"
                    required
                    value="<?= $e($form['latitude'] ?? '') ?>"
                    placeholder="37.2753000"
                >
            </label>

            <label>
                <span>Longitude</span>
                <input
                    type="number"
                    name="longitude"
                    step="0.0000001"
                    min="-180"
                    max="180"
                    required
                    value="<?= $e($form['longitude'] ?? '') ?>"
                    placeholder="-107.8801000"
                >
            </label>

            <label>
                <span>Hours</span>
                <input
                    type="text"
                    name="hours_text"
                    maxlength="255"
                    value="<?= $e($form['hours_text'] ?? '') ?>"
                    placeholder="24 hours"
                >
            </label>

        </div>

        <h3 class="admin-resource-section-title">Address and contact</h3>

        <div class="admin-resource-form-grid">

            <label>
                <span>Street address</span>
                <input
                    type="text"
                    name="street_address"
                    maxlength="255"
                    value="<?= $e($form['street_address'] ?? '') ?>"
                >
            </label>

            <label>
                <span>City</span>
                <input
                    type="text"
                    name="city"
                    maxlength="120"
                    value="<?= $e($form['city'] ?? '') ?>"
                >
            </label>

            <label>
                <span>State / region</span>
                <input
                    type="text"
                    name="state_region"
                    maxlength="120"
                    value="<?= $e($form['state_region'] ?? '') ?>"
                >
            </label>

            <label>
                <span>Postal code</span>
                <input
                    type="text"
                    name="postal_code"
                    maxlength="32"
                    value="<?= $e($form['postal_code'] ?? '') ?>"
                >
            </label>

            <label>
                <span>Country code</span>
                <input
                    type="text"
                    name="country_code"
                    maxlength="2"
                    value="<?= $e($form['country_code'] ?? 'US') ?>"
                    placeholder="US"
                >
            </label>

            <label>
                <span>Phone</span>
                <input
                    type="text"
                    name="phone"
                    maxlength="64"
                    value="<?= $e($form['phone'] ?? '') ?>"
                >
            </label>

            <label>
                <span>Website</span>
                <input
                    type="url"
                    name="website_url"
                    value="<?= $e($form['website_url'] ?? '') ?>"
                    placeholder="https://"
                >
            </label>

        </div>

        <h3 class="admin-resource-section-title">Services</h3>

        <p class="admin-resource-section-copy">
            A Resource can provide more than one Service. Check every Service
            available at this exact location.
        </p>

        <div class="admin-integration-table-wrap">
            <table class="admin-integration-table admin-resource-service-table">
                <thead>
                <tr>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Fee</th>
                    <th>Fee details</th>
                    <th>Access / notes</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach (
                    llama_resource_service_types()
                    as $serviceType => $serviceLabel
                ): ?>
                <?php
                $service =
                    is_array(
                        $selectedServices[$serviceType]
                        ?? null
                    )
                        ? $selectedServices[$serviceType]
                        : [];

                $enabled =
                    !empty($service)
                    && (
                        !array_key_exists('enabled', $service)
                        || !empty($service['enabled'])
                    );
                ?>
                <tr>
                    <td data-label="Service">
                        <label>
                            <input
                                type="checkbox"
                                name="services[<?= $e($serviceType) ?>][enabled]"
                                value="1"
                                <?= $enabled ? 'checked' : '' ?>
                            >
                            <strong><?= $e($serviceLabel) ?></strong>
                        </label>
                    </td>

                    <td data-label="Status">
                        <select
                            name="services[<?= $e($serviceType) ?>][status]"
                        >
                            <?php foreach (
                                llama_resource_statuses()
                                as $value => $label
                            ): ?>
                            <option
                                value="<?= $e($value) ?>"
                                <?= (string) ($service['status'] ?? 'unknown') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td data-label="Fee">
                        <select
                            name="services[<?= $e($serviceType) ?>][fee_status]"
                        >
                            <?php foreach (
                                llama_resource_fee_statuses()
                                as $value => $label
                            ): ?>
                            <option
                                value="<?= $e($value) ?>"
                                <?= (string) ($service['fee_status'] ?? 'unknown') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td data-label="Fee details">
                        <input
                            type="text"
                            name="services[<?= $e($serviceType) ?>][fee_description]"
                            maxlength="255"
                            value="<?= $e($service['fee_description'] ?? '') ?>"
                            placeholder="$10, free with fuel, etc."
                        >
                    </td>

                    <td data-label="Access / notes">
                        <div class="admin-resource-service-notes">
                        <input
                            type="text"
                            name="services[<?= $e($serviceType) ?>][access_notes]"
                            value="<?= $e($service['access_notes'] ?? '') ?>"
                            placeholder="Ask cashier for key, rear lot, etc."
                        >

                        <input
                            type="text"
                            name="services[<?= $e($serviceType) ?>][notes]"
                            value="<?= $e($service['notes'] ?? '') ?>"
                            placeholder="Service-specific notes"
                        >
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="admin-resource-section-title">Resource notes and source</h3>

        <div class="admin-resource-form-grid">

            <label>
                <span>Resource notes</span>
                <textarea
                    name="notes"
                    rows="4"
                ><?= $e($form['notes'] ?? '') ?></textarea>
            </label>

            <label>
                <span>Source / provider</span>
                <input
                    type="text"
                    name="source_provider"
                    maxlength="160"
                    value="<?= $e($form['source_provider'] ?? '') ?>"
                    placeholder="Official website, Scout, USFS, etc."
                >
            </label>

            <label>
                <span>Source external ID</span>
                <input
                    type="text"
                    name="source_external_id"
                    maxlength="255"
                    value="<?= $e($form['source_external_id'] ?? '') ?>"
                >
            </label>

            <label>
                <span>Source URL</span>
                <input
                    type="url"
                    name="source_url"
                    value="<?= $e($form['source_url'] ?? '') ?>"
                    placeholder="https://"
                >
            </label>

            <label>
                <span>Source updated</span>
                <input
                    type="datetime-local"
                    name="source_updated_at"
                    value="<?= $e(
                        $datetimeLocal(
                            $form['source_updated_at']
                            ?? ''
                        )
                    ) ?>"
                >
            </label>

            <label>
                <span>Verified</span>
                <input
                    type="datetime-local"
                    name="verified_at"
                    value="<?= $e(
                        $datetimeLocal(
                            $form['verified_at']
                            ?? ''
                        )
                    ) ?>"
                >
            </label>

        </div>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                <?= $editing
                    ? 'Save Resource'
                    : 'Create Resource' ?>
            </button>
        </div>
    </form>

    <?php if ($editing && !empty($resource['is_active'])): ?>
    <form
        class="admin-user-action-box"
        method="post"
        onsubmit="return confirm('Deactivate this Resource?');"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="resource_action"
            value="deactivate"
        >

        <input
            type="hidden"
            name="resource_id"
            value="<?= (int) $resourceId ?>"
        >

        <button
            class="admin-button is-muted"
            type="submit"
        >
            Deactivate Resource
        </button>
    </form>
    <?php endif; ?>

</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Standalone map data</p>
            <h2>Resources</h2>
        </div>

        <span><?= number_format(count($resources)) ?></span>
    </header>

    <?php if (!$resources): ?>
    <div class="admin-empty-state">
        <h3>No Resources yet.</h3>
        <p>
            Create the first standalone Resource above.
        </p>
    </div>
    <?php else: ?>

    <div class="admin-integration-table-wrap">
        <table class="admin-integration-table">
            <thead>
            <tr>
                <th>Resource</th>
                <th>Type</th>
                <th>Services</th>
                <th>Status</th>
                <th>GPS</th>
                <th>Manage</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resources as $row): ?>
            <?php
            $serviceNames =
                array_values(
                    array_filter(
                        array_map(
                            static fn (array $service): string =>
                                trim(
                                    (string) (
                                        $service['service_type_label']
                                        ?? ''
                                    )
                                ),
                            (array) (
                                $row['services']
                                ?? []
                            )
                        )
                    )
                );
            ?>
            <tr class="<?= empty($row['is_active']) ? 'admin-resource-inactive' : '' ?>">
                <td data-label="Resource">
                    <strong><?= $e($row['name'] ?? 'Resource') ?></strong>
                </td>

                <td data-label="Type">
                    <?= $e(
                        $row['resource_type_label']
                        ?? $row['resource_type']
                        ?? ''
                    ) ?>
                </td>

                <td data-label="Services">
                    <?= $e(
                        $serviceNames
                            ? implode(', ', $serviceNames)
                            : 'None'
                    ) ?>
                </td>

                <td data-label="Status">
                    <span class="admin-status-pill">
                        <?= $e(
                            !empty($row['is_active'])
                                ? (
                                    llama_resource_statuses()[
                                        (string) (
                                            $row['status']
                                            ?? 'unknown'
                                        )
                                    ]
                                    ?? ucfirst(
                                        (string) (
                                            $row['status']
                                            ?? 'unknown'
                                        )
                                    )
                                )
                                : 'Inactive'
                        ) ?>
                    </span>
                </td>

                <td
                    data-label="GPS"
                    class="admin-resource-gps"
                >
                    <?php if (!empty($row['has_coordinates'])): ?>
                        <?= $e(
                            number_format(
                                (float) $row['latitude'],
                                6,
                                '.',
                                ''
                            )
                        ) ?>,
                        <?= $e(
                            number_format(
                                (float) $row['longitude'],
                                6,
                                '.',
                                ''
                            )
                        ) ?>
                    <?php else: ?>
                        Missing
                    <?php endif; ?>
                </td>

                <td data-label="Manage">
                    <a
                        class="admin-button"
                        href="/resources.php?id=<?= (int) $row['id'] ?>"
                    >
                        Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

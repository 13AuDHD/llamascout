<?php

declare(strict_types=1);

// The standalone and embedded editors submit the same Resource fields.
$postFailed = !$resource || $form !== $resource;
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

if ($postFailed) {
    $selectedServices = is_array($form['services'] ?? null) ? $form['services'] : [];
}

$serviceTypes = llama_resource_service_types();
$enabledServices = array_filter(
    $serviceTypes,
    static fn (string $type): bool => !empty($selectedServices[$type][$postFailed ? 'enabled' : 'is_active']),
    ARRAY_FILTER_USE_KEY
);

$coordinatesValue =
    trim(
        (string) (
            $form['coordinates']
            ?? ''
        )
    );

if (
    $coordinatesValue === ''
    && isset(
        $form['latitude'],
        $form['longitude']
    )
) {
    $coordinatesValue =
        rtrim(
            rtrim(
                number_format(
                    (float) $form['latitude'],
                    7,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        )
        . ', '
        . rtrim(
            rtrim(
                number_format(
                    (float) $form['longitude'],
                    7,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
}

$hoursModeValue =
    trim(
        (string) (
            $form['hours_mode']
            ?? ''
        )
    );

if ($hoursModeValue === '') {
    $hoursModeValue = '24_hours';
}

$fixedOpenValue =
    array_key_exists(
        'fixed_open_hour',
        $form
    )
        && $form['fixed_open_hour'] !== null
        ? (string) $form['fixed_open_hour']
        : '';

$fixedCloseValue =
    array_key_exists(
        'fixed_close_hour',
        $form
    )
        && $form['fixed_close_hour'] !== null
        ? (string) $form['fixed_close_hour']
        : '';

$weeklyFormHours =
    is_array(
        $form['weekly_hours']
        ?? null
    )
        ? $form['weekly_hours']
        : (
            is_array(
                $resource['weekly_hours']
                ?? null
            )
                ? $resource['weekly_hours']
                : []
        );

$hourOptions =
    llama_resource_hour_options();

$weekdayLabels =
    llama_resource_weekdays();

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
        <details class="contribution-section" open>
            <summary><span>Basic</span></summary>
            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field">
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

                    <label class="contribution-field">
                        <span>Resource name *</span>
                        <input
                            type="text"
                            name="name"
                            maxlength="160"
                            required
                            value="<?= $e($form['name'] ?? '') ?>"
                            placeholder="Highway Market"
                        >
                    </label>

                    <label class="contribution-field contribution-field-wide">
                        <span>Description</span>
                        <textarea name="notes" rows="5"><?= $e($form['notes'] ?? '') ?></textarea>
                    </label>
                </div>
            </div>
        </details>

        <details class="contribution-section" open>
            <summary><span>Location</span></summary>
            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <div class="contribution-field add-place-locate-panel">
                        <span>At the Resource right now?</span>
                        <button class="add-place-locate-button" type="button" data-resource-locate disabled>
                            <?= llama_icon('current-location') ?>
                            Locate me
                        </button>
                    </div>
                    <div class="contribution-field add-place-coordinate-field">
                        <label class="place-report-field-label" for="resource-coordinates">Coordinates</label>
                        <div class="add-place-coordinate-row">
                            <input id="resource-coordinates" type="text" name="coordinates" required
                                value="<?= $e($coordinatesValue) ?>" placeholder="37.2694035, -107.9126176"
                                inputmode="decimal" autocomplete="off" spellcheck="false">
                            <button class="add-place-coordinate-search" type="button" data-resource-search disabled>Search</button>
                        </div>
                    </div>
                    <div class="add-place-location-status is-visible is-info" data-resource-location-status role="status">
                        At the Resource? Tap Locate me. Otherwise, enter coordinates and tap Search.
                    </div>

                    <label class="contribution-field">
                        <span>Street address</span>
                        <input
                            type="text"
                            name="street_address"
                            maxlength="255"
                            value="<?= $e($form['street_address'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Town / city</span>
                        <input
                            type="text"
                            name="city"
                            maxlength="120"
                            value="<?= $e($form['city'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>County / parish / municipality</span>
                        <input type="text" name="county" maxlength="120" value="<?= $e($form['county'] ?? '') ?>">
                    </label>

                    <label class="contribution-field">
                        <span>State / region</span>
                        <input
                            type="text"
                            name="state_region"
                            maxlength="120"
                            value="<?= $e($form['state_region'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>ZIP / postal code</span>
                        <input
                            type="text"
                            name="postal_code"
                            maxlength="32"
                            value="<?= $e($form['postal_code'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Country code</span>
                        <input
                            type="text"
                            name="country_code"
                            maxlength="2"
                            value="<?= $e($form['country_code'] ?? 'US') ?>"
                            placeholder="US"
                        >
                    </label>
                </div>
            </div>
        </details>

        <details class="contribution-section" open>
            <summary><span>Services</span></summary>
            <div class="contribution-section-body resource-services" data-resource-services>
                <div class="resource-service-picker" data-resource-service-picker hidden>
                    <select aria-label="Service to add">
                        <option value="">Choose a service</option>
                        <?php foreach ($serviceTypes as $serviceType => $serviceLabel): ?>
                            <option value="<?= $e($serviceType) ?>"><?= $e($serviceLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="resource-service-button" data-resource-service-add>Add service</button>
                </div>

                <p class="resource-services-empty" data-resource-services-empty <?= $enabledServices ? 'hidden' : '' ?>>
                    No services added yet.
                </p>
                <div class="resource-service-cards" data-resource-service-cards <?= $enabledServices ? '' : 'hidden' ?>>
                    <?php foreach ($enabledServices as $serviceType => $serviceLabel): ?>
                        <?php $service = $selectedServices[$serviceType]; require __DIR__ . '/service-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <label class="resource-primary-service" data-resource-primary-service <?= $enabledServices ? '' : 'hidden' ?>>
                    <span>Primary service</span>
                    <select name="marker_service_type">
                        <option value="">Auto: first enabled service</option>
                        <?php foreach ($enabledServices as $serviceType => $serviceLabel): ?>
                            <option value="<?= $e($serviceType) ?>" <?= ($form['marker_service_type'] ?? '') === $serviceType ? 'selected' : '' ?>>
                                <?= $e($serviceLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <template data-resource-service-template>
                    <?php
                    $serviceType = '__type__';
                    $serviceLabel = '';
                    $service = [];
                    require __DIR__ . '/service-card.php';
                    ?>
                </template>
            </div>
        </details>

        <details class="contribution-section">
            <summary><span>Details</span></summary>
            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field">
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

                    <label class="contribution-field">
                        <span>Hours</span>
                        <select
                            name="hours_mode"
                            data-resource-hours-mode
                            required
                        >
                            <?php foreach (
                                llama_resource_hours_modes()
                                as $value => $label
                            ): ?>
                            <option
                                value="<?= $e($value) ?>"
                                <?= $hoursModeValue === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div
                        class="admin-resource-hours-panel admin-resource-field-wide"
                        data-resource-hours-panel="fixed_hours"
                        <?= $hoursModeValue === 'fixed_hours' ? '' : 'hidden' ?>
                    >
                        <label class="contribution-field">
                            <span>Open</span>
                            <select name="fixed_open_hour">
                                <option value="">Choose time</option>
                                <?php foreach ($hourOptions as $hour => $label): ?>
                                <option
                                    value="<?= (int) $hour ?>"
                                    <?= $fixedOpenValue === (string) $hour
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= $e($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="contribution-field">
                            <span>Close</span>
                            <select name="fixed_close_hour">
                                <option value="">Choose time</option>
                                <?php foreach ($hourOptions as $hour => $label): ?>
                                <option
                                    value="<?= (int) $hour ?>"
                                    <?= $fixedCloseValue === (string) $hour
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= $e($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div
                        class="admin-resource-weekly-hours admin-resource-field-wide"
                        data-resource-hours-panel="varied_hours"
                        <?= $hoursModeValue === 'varied_hours' ? '' : 'hidden' ?>
                    >
                        <div class="admin-resource-weekly-heading">
                            <span>Day</span>
                            <span>Open</span>
                            <span>Close</span>
                        </div>

                        <?php foreach ($weekdayLabels as $day => $dayLabel): ?>
                        <?php
                        $dayHours =
                            is_array(
                                $weeklyFormHours[$day]
                                ?? null
                            )
                                ? $weeklyFormHours[$day]
                                : [];

                        $dayOpen =
                            array_key_exists(
                                'open_hour',
                                $dayHours
                            )
                                ? (string) $dayHours['open_hour']
                                : '';

                        $dayClose =
                            array_key_exists(
                                'close_hour',
                                $dayHours
                            )
                                ? (string) $dayHours['close_hour']
                                : '';
                        ?>
                        <div class="admin-resource-weekly-row">
                            <strong><?= $e($dayLabel) ?></strong>

                            <label class="contribution-field">
                                <span class="sr-only">
                                    <?= $e($dayLabel) ?> open
                                </span>
                                <select
                                    name="weekly_hours[<?= (int) $day ?>][open_hour]"
                                >
                                    <option value="">Choose</option>
                                    <?php foreach ($hourOptions as $hour => $label): ?>
                                    <option
                                        value="<?= (int) $hour ?>"
                                        <?= $dayOpen === (string) $hour
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $e($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="contribution-field">
                                <span class="sr-only">
                                    <?= $e($dayLabel) ?> close
                                </span>
                                <select
                                    name="weekly_hours[<?= (int) $day ?>][close_hour]"
                                >
                                    <option value="">Choose</option>
                                    <?php foreach ($hourOptions as $hour => $label): ?>
                                    <option
                                        value="<?= (int) $hour ?>"
                                        <?= $dayClose === (string) $hour
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $e($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <label class="contribution-field">
                        <span>Phone</span>
                        <input
                            type="text"
                            name="phone"
                            maxlength="64"
                            value="<?= $e($form['phone'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Website</span>
                        <input
                            type="url"
                            name="website_url"
                            value="<?= $e($form['website_url'] ?? '') ?>"
                            placeholder="https://"
                        >
                    </label>

                    <?php if ($resourceStaffFields): ?>
                    <label class="contribution-field">
                        <span>Source / provider</span>
                        <input
                            type="text"
                            name="source_provider"
                            maxlength="160"
                            value="<?= $e($form['source_provider'] ?? '') ?>"
                            placeholder="Official website, Scout, USFS, etc."
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Source external ID</span>
                        <input
                            type="text"
                            name="source_external_id"
                            maxlength="255"
                            value="<?= $e($form['source_external_id'] ?? '') ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Source URL</span>
                        <input
                            type="url"
                            name="source_url"
                            value="<?= $e($form['source_url'] ?? '') ?>"
                            placeholder="https://"
                        >
                    </label>

                    <label class="contribution-field">
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

                    <label class="contribution-field">
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

                    <?php endif; ?>
                </div>
            </div>
        </details>

<?php

declare(strict_types=1);

/*
 * Llama Scout one-time updater
 * Landscape + setting structured fields, chips, and related Place Report cleanup.
 *
 * Upload this file to the Llama Scout repository root and open it once with
 * the token shown in the included README. It verifies the expected source
 * versions, creates private backups, writes the changes, and deletes itself
 * after a successful update.
 */

$requiredToken = 'LS-20260925-7f94c3a15d8e';

header('Content-Type: text/plain; charset=UTF-8');

if (!hash_equals($requiredToken, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Invalid or missing update token.\n";
    exit;
}

$root = __DIR__;

$targets = [
    'app/place-report.php' => '2cbd87bfc2826479afd400a83e03aa9f207ac8a9',
    'partials/place-report/form.php' => '4e45bc03ddaa16c41a908127fcb334a50e2b4470',
    'partials/place-report/read-only.php' => '7c668ff04146910ed824309b1e3c268bce63292d',
    'css/site/features/place-report-form.css' => 'ca50bb8eb9918126e6b72e3782af150a88d1dbab',
];

$gitBlobSha = static function (string $content): string {
    return sha1('blob ' . strlen($content) . "\0" . $content);
};

$replaceOnce = static function (
    string $text,
    string $old,
    string $new,
    string $label
): string {
    $count = substr_count($text, $old);

    if ($count !== 1) {
        throw new RuntimeException(
            $label . ': expected exactly 1 match, found ' . $count
        );
    }

    return str_replace($old, $new, $text);
};

$regexOnce = static function (
    string $text,
    string $pattern,
    string $replacement,
    string $label
): string {
    $matchCount = preg_match_all($pattern, $text, $matches);

    if ($matchCount !== 1) {
        throw new RuntimeException(
            $label . ': expected exactly 1 regex match, found '
            . (string) $matchCount
        );
    }

    $result = preg_replace_callback(
        $pattern,
        static fn (): string => $replacement,
        $text,
        1
    );

    if (!is_string($result)) {
        throw new RuntimeException($label . ': replacement failed.');
    }

    return $result;
};

$atomicWrite = static function (string $path, string $content): void {
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create directory: ' . $dir);
    }

    $temp = $path . '.llama-update-' . bin2hex(random_bytes(4)) . '.tmp';

    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary file: ' . $temp);
    }

    if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Could not replace file: ' . $path);
    }
};

try {
    $source = [];

    foreach ($targets as $relative => $expectedSha) {
        $path = $root . '/' . $relative;

        if (!is_file($path)) {
            throw new RuntimeException('Missing required file: ' . $relative);
        }

        $content = file_get_contents($path);

        if (!is_string($content)) {
            throw new RuntimeException('Could not read: ' . $relative);
        }

        $actualSha = $gitBlobSha($content);

        if (!hash_equals($expectedSha, $actualSha)) {
            throw new RuntimeException(
                $relative
                . " has changed since this updater was prepared.\n"
                . 'Expected blob: ' . $expectedSha . "\n"
                . 'Current blob:  ' . $actualSha . "\n"
                . 'Nothing was changed. Ask ChatGPT to refresh the update against the latest GitHub version.'
            );
        }

        $source[$relative] = $content;
    }

    /* ========================================================
     * app/place-report.php
     * ======================================================== */
    $text = $source['app/place-report.php'];

    $text = $replaceOnce(
        $text,
        <<<'OLD'
        'environment_accessibility' => [
            'label' => 'Environment and accessibility',
            'description' => 'Forest, mountain, water, desert, exposure, mobility, and walking distance',
            'icon' => 'at-landscape',
        ],
OLD,
        <<<'NEW'
        'landscape_setting' => [
            'label' => 'Landscape and setting',
            'description' => 'Setting, nearby features, views, exposure, wildlife, and other conditions',
            'icon' => 'at-landscape',
        ],
        'accessibility' => [
            'label' => 'Accessibility',
            'description' => 'Mobility access, walking surface, facilities, and distance from the vehicle',
            'icon' => 'wheelchair',
        ],
        'environment_accessibility' => [
            'label' => 'Environment details',
            'description' => 'Earlier forest, mountain, water, and desert observations',
            'icon' => 'at-landscape',
        ],
NEW,
        'Split Environment and accessibility sections'
    );

    $text = $replaceOnce(
        $text,
        "        'environment_forest' => 'trees',\n",
        "        'landscape_primary' => 'at-landscape',\n"
        . "        'landscape_details' => 'at-landscape',\n"
        . "        'landscape_views' => 'eye',\n"
        . "        'critter_activity' => 'deer',\n"
        . "        'environment_forest' => 'trees',\n",
        'Add landscape field icons'
    );

    $text = $replaceOnce(
        $text,
        "        'monsoon_risk' => 'at-heavy-rain',\n",
        "        'monsoon_risk' => 'at-heavy-rain',\n"
        . "        'hurricane_risk' => 'at-heavy-rain',\n",
        'Add hurricane icon fallback'
    );

    $text = $replaceOnce(
        $text,
        "        'environment_accessibility' => 'at-landscape',\n",
        "        'landscape_setting' => 'at-landscape',\n"
        . "        'accessibility' => 'wheelchair',\n"
        . "        'environment_accessibility' => 'at-landscape',\n",
        'Add section fallback icons'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    $add('max_trailer_length_feet', 'Maximum trailer length', 'site_vehicle', 'select', 'details.max_trailer_length_feet', [
        'options' => [
            '10' => 'About 10 ft', '15' => 'About 15 ft', '20' => 'About 20 ft',
OLD,
        <<<'NEW'
    $add('max_trailer_length_feet', 'Maximum trailer length', 'site_vehicle', 'select', 'details.max_trailer_length_feet', [
        'options' => [
            'not-recommended' => 'Not recommended',
            '10' => 'About 10 ft', '15' => 'About 15 ft', '20' => 'About 20 ft',
NEW,
        'Add Not recommended trailer option'
    );

    $text = $replaceOnce(
        $text,
        "        'daytime_crowds' => ['Crowds', 'Empty', 'Crowded', 'sensory.daytime.crowds', 'Daytime'],\n",
        "        'daytime_crowds' => ['People', 'None', 'Many', 'sensory.daytime.crowds', 'Daytime'],\n",
        'Rename daytime Crowds to People'
    );

    $text = $replaceOnce(
        $text,
        "        'nighttime_crowds' => ['Crowds', 'Empty', 'Crowded', 'sensory.nighttime.crowds', 'Nighttime'],\n",
        "        'nighttime_crowds' => ['People', 'None', 'Many', 'sensory.nighttime.crowds', 'Nighttime'],\n",
        'Rename nighttime Crowds to People'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
        'sensory_aircraft_noise' => ['Aircraft noise', 'Low', 'High', 'sensory.details.aircraft_noise', 'Specific sensory conditions'],
        'sensory_road_noise' => ['Road noise', 'Low', 'High', 'sensory.details.road_noise', 'Specific sensory conditions'],
OLD,
        <<<'NEW'
        'sensory_aircraft_noise' => ['Aircraft noise', 'Low', 'High', 'sensory.details.aircraft_noise', 'Specific sensory conditions'],
        'sensory_train_noise' => ['Train noise', 'Low', 'High', 'sensory.details.train_noise', 'Specific sensory conditions'],
        'sensory_dog_barking' => ['Dog barking', 'Low', 'High', 'sensory.details.dog_barking', 'Specific sensory conditions'],
        'sensory_road_noise' => ['Road noise', 'Low', 'High', 'sensory.details.road_noise', 'Specific sensory conditions'],
NEW,
        'Add train noise and dog barking'
    );

    $landscapeBlock = <<<'NEW'
    /* Landscape and setting */
    $add('landscape_primary', 'Primary setting', 'landscape_setting', 'select', 'details.landscape_primary', [
        'wide' => true,
        'options' => [
            'forest-woodland' => 'Forest / woodland',
            'mountain-alpine' => 'Mountain / alpine',
            'canyon' => 'Canyon',
            'grassland-prairie' => 'Grassland / prairie',
            'shrubland-scrubland' => 'Shrubland / scrubland',
            'high-desert' => 'High desert',
            'low-desert' => 'Low desert',
            'wetland' => 'Wetland / swamp / bog',
            'beach-coastal' => 'Beach / coastal',
            'lakeside-reservoir' => 'Lakeside / reservoir',
            'riverside-creekside' => 'Riverside / creekside',
            'urban-city' => 'Urban / city',
            'suburban' => 'Suburban',
            'farmland-ranchland' => 'Farmland / ranchland',
            'mixed-transitional' => 'Mixed / transitional',
            'other' => 'Other',
        ],
        'allow_unknown' => true,
        'points_categories' => ['environment'],
    ]);

    $add('landscape_details', 'Setting details', 'landscape_setting', 'multiselect', 'details.landscape_details', [
        'wide' => true,
        'summary' => '+ Add setting detail',
        'search_placeholder' => 'Search setting details...',
        'options' => [
            'rocky' => 'Rocky',
            'sandy' => 'Sandy',
            'mud-prone' => 'Mud-prone',
            'tree-cover' => 'Tree cover',
            'open-meadow' => 'Open meadow',
            'riparian' => 'Riparian',
            'dunes' => 'Dunes',
            'cliffs-dropoffs' => 'Cliffs / drop-offs',
            'agricultural-nearby' => 'Agricultural nearby',
            'residential-nearby' => 'Residential nearby',
            'industrial-nearby' => 'Industrial nearby',
        ],
        'points_categories' => ['environment'],
    ]);

    $add('landscape_views', 'Views', 'landscape_setting', 'multiselect', 'details.landscape_views', [
        'wide' => true,
        'summary' => '+ Add view',
        'search_placeholder' => 'Search views...',
        'options' => [
            'forest-woodland' => 'Forest / woodland',
            'mountain' => 'Mountain',
            'water' => 'Water',
            'high-desert' => 'High desert',
            'low-desert' => 'Low desert',
            'canyon' => 'Canyon',
            'grassland-prairie' => 'Grassland / prairie',
            'wetland' => 'Wetland',
            'beach-coast' => 'Beach / coast',
            'city-urban' => 'City / urban',
            'night-sky' => 'Night sky',
        ],
        'points_categories' => ['environment'],
    ]);

    /* Preserve old classifiers for historical reports without showing them on new forms. */
    foreach ([
        'environment_forest' => ['Forest environment?', 'details.forest'],
        'environment_forest_view' => ['Forest view?', 'details.forest_view'],
        'environment_mountains' => ['Mountain environment?', 'details.mountains'],
        'environment_mountain_view' => ['Mountain view?', 'details.mountain_view'],
        'environment_water_view' => ['Water view?', 'details.water_view'],
        'environment_desert' => ['Desert environment?', 'details.desert'],
        'environment_desert_view' => ['Desert view?', 'details.desert_view'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'environment_accessibility', 'tri', $storage, [
            'allow_unknown' => true,
            'hide_form' => true,
            'points_categories' => ['environment'],
        ]);
    }

    foreach ([
        'environment_water_nearby' => ['Water nearby?', 'details.water_nearby'],
        'environment_wildlife' => ['Wildlife common?', 'details.wildlife'],
        'environment_bugs' => ['Bugs significant?', 'details.bugs'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'landscape_setting', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['environment'],
        ]);
    }

    foreach ([
        'environment_wind_exposure' => ['Wind exposure', 'Protected', 'Very exposed', 'details.wind_exposure'],
        'environment_sun_exposure' => ['Sun exposure', 'Low', 'Full sun', 'details.sun_exposure'],
        'environment_shade' => ['Environment shade', 'None', 'Heavy', 'details.environment_shade'],
        'environment_open_sky' => ['Open sky', 'Low', 'Wide open', 'details.environment_open_sky'],
        'critter_activity' => ['Critter activity', 'None noticed', 'Heavy / persistent', 'details.critter_activity'],
    ] as $key => [$label, $low, $high, $storage]) {
        $add($key, $label, 'landscape_setting', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['environment'],
        ]);
    }

    /* Accessibility */
    foreach ([
        'wheelchair_friendly' => ['Wheelchair friendly?', 'details.wheelchair_friendly'],
        'mobility_device_friendly' => ['Outdoor mobility device friendly?', 'details.mobility_device_friendly'],
        'flat_walking_surface' => ['Flat walking surface?', 'details.flat_walking_surface'],
        'step_free_access' => ['Step-free access?', 'details.step_free_access'],
        'accessible_toilet' => ['Accessible toilet?', 'details.accessible_toilet'],
        'accessible_picnic_table' => ['Accessible picnic table?', 'details.accessible_picnic_table'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'accessibility', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['accessibility'],
        ]);
    }

    $add('walking_distance_from_vehicle', 'Walking distance from vehicle', 'accessibility', 'select', 'details.walking_distance_from_vehicle', [
        'allow_unknown' => true,
        'options' => [
            'at-vehicle' => 'At / beside vehicle',
            'under-50-ft' => 'Under 50 ft',
            '50-100-ft' => '50-100 ft',
            '100-250-ft' => '100-250 ft',
            '250-500-ft' => '250-500 ft',
            '500-plus-ft' => '500+ ft / short hike',
        ],
        'points_categories' => ['accessibility'],
    ]);
NEW;

    $text = $regexOnce(
        $text,
        '~    /\* Environment and accessibility \*/\n.*?\n    /\* Safety and warnings \*/~s',
        $landscapeBlock . "\n\n    /* Safety and warnings */",
        'Replace environment/accessibility schema'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    $add('winter_access', 'Winter access?', 'rules', 'tri', 'rules.winter_access', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
OLD,
        <<<'NEW'
    $add('winter_access', 'Winter access?', 'rules', 'tri', 'rules.winter_access', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('hurricane_risk', 'Hurricane risk?', 'rules', 'tri', 'rules.hurricane_risk', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
NEW,
        'Add Hurricane risk'
    );

    $experienceBlock = <<<'NEW'
    /* Experience and recommendations */
    foreach ([
        'experience_sunrise_view' => ['Sunrise view', 'experience.sunrise_view'],
        'experience_sunset_view' => ['Sunset view', 'experience.sunset_view'],
        'experience_overall_scenery' => ['Overall scenery', 'experience.overall_scenery'],
        'experience_night_sky' => ['Night sky', 'experience.night_sky'],
        'experience_stargazing' => ['Stargazing', 'experience.stargazing'],
        'experience_quiet_evening' => ['Quiet evening', 'experience.quiet_evening'],
        'experience_overnight_comfort' => ['Overnight comfort', 'experience.overnight_comfort'],
        'experience_extended_stay_comfort' => ['Extended-stay comfort', 'experience.extended_stay_comfort'],
        'experience_sensory_retreat' => ['Sensory retreat', 'experience.sensory_retreat'],
        'experience_remote_work' => ['Remote work', 'experience.remote_work'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'experience', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => 'Poor',
            'high' => 'Excellent',
            'points_categories' => ['experience_recommendations'],
        ]);
    }

    /* Preserve the retired duplicate ratings in historical data. */
    foreach ([
        'experience_mountain_view' => ['Mountain view', 'experience.mountain_view'],
        'experience_forest_view' => ['Forest view', 'experience.forest_view'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'legacy_experience_views', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => 'Poor',
            'high' => 'Excellent',
            'points_categories' => ['experience_recommendations'],
        ]);
    }

    foreach ([
        'recommended_overnight_stop' => 'Recommended for overnight stop?',
        'recommended_quiet_evening' => 'Recommended for a quiet evening?',
        'recommended_extended_stay' => 'Recommended for an extended stay?',
        'recommended_sensory_retreat' => 'Recommended for a sensory retreat?',
        'recommended_stargazing' => 'Recommended for stargazing?',
        'recommended_remote_work' => 'Recommended for remote work?',
        'recommended_solo_travel' => 'Good for solo travel?',
        'recommended_families' => 'Good for families?',
        'recommended_large_groups' => 'Good for large groups?',
    ] as $key => $label) {
        $add($key, $label, 'experience', 'tri', 'experience.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['experience_recommendations'],
        ]);
    }
    $add('not_recommended_for', 'Not recommended for', 'experience', 'textarea', 'experience.not_recommended_for', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Example: low-clearance vehicles, people sensitive to road noise, large trailers...',
        'points_categories' => ['experience_recommendations'],
    ]);
NEW;

    $text = $regexOnce(
        $text,
        '~    /\* Experience and recommendations \*/\n.*?\n    /\* Scout notes \*/~s',
        $experienceBlock . "\n\n    /* Scout notes */",
        'Reorder Experience fields'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
        'daytime_sensory_comfort' =>
            'Give an overall daytime sensory rating using what you observed: noise, traffic, crowds, light, smells, movement, and unpredictability. This is broader than any one sensory question.',
        'nighttime_sensory_comfort' =>
            'Give an overall nighttime sensory rating using what you observed: noise, traffic, crowds, lighting, smells, movement, and unpredictability. Day and night can be very different.',
OLD,
        <<<'NEW'
        'daytime_sensory_comfort' =>
            'Give an overall daytime sensory rating using what you observed: noise, traffic, people, light, smells, movement, and unpredictability. This is broader than any one sensory question.',
        'nighttime_sensory_comfort' =>
            'Give an overall nighttime sensory rating using what you observed: noise, traffic, people, lighting, smells, movement, and unpredictability. Day and night can be very different.',
        'landscape_primary' =>
            'Choose the one setting that best describes the Place itself. Use Mixed / transitional when two major settings genuinely overlap.',
        'critter_activity' =>
            'Rate rodents and other small animals that are likely to get into vehicles, food, trash, or gear. This is separate from dangerous wildlife risk.',
        'hurricane_risk' =>
            'Choose Yes when hurricanes or hurricane-force tropical systems are a realistic seasonal concern for this Place or its access routes.',
NEW,
        'Update Place Report help text'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    if ((string) $field['type'] === 'checkbox') {
        return $value === true || $value === 1 || $value === '1'
            ? 'answered'
            : 'unanswered';
    }

    if ($value === null || $value === '') {
OLD,
        <<<'NEW'
    if ((string) $field['type'] === 'checkbox') {
        return $value === true || $value === 1 || $value === '1'
            ? 'answered'
            : 'unanswered';
    }

    if ((string) $field['type'] === 'multiselect') {
        return is_array($value) && count($value) > 0
            ? 'answered'
            : 'unanswered';
    }

    if ($value === null || $value === '') {
NEW,
        'Support multiselect answer state'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
        if ((string) $field['type'] === 'checkbox') {
            if ($value === '1') {
                $input[$key] = '1';
            }

            continue;
        }

        if ($value !== null) {
            $input[$key] = (string) $value;
        }
OLD,
        <<<'NEW'
        if ((string) $field['type'] === 'checkbox') {
            if ($value === '1') {
                $input[$key] = '1';
            }

            continue;
        }

        if ((string) $field['type'] === 'multiselect') {
            $input[$key] = is_array($value)
                ? array_values($value)
                : [];
            continue;
        }

        if ($value !== null) {
            $input[$key] = (string) $value;
        }
NEW,
        'Support multiselect form input'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    if ($type === 'checkbox') {
        return (string) $raw === '1';
    }

    if ($type === 'tri') {
OLD,
        <<<'NEW'
    if ($type === 'checkbox') {
        return (string) $raw === '1';
    }

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $rawValues = is_array($raw)
            ? $raw
            : (($raw === null || $raw === '') ? [] : [$raw]);
        $values = [];

        foreach ($rawValues as $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $cleanValue = trim((string) $rawValue);

            if (
                $cleanValue !== ''
                && array_key_exists($cleanValue, $options)
            ) {
                $values[$cleanValue] = true;
            }
        }

        return array_keys($values);
    }

    if ($type === 'tri') {
NEW,
        'Support multiselect parsing'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    if ($type === 'checkbox') {
        return $value ? 'Yes' : null;
    }

    if ($type === 'select') {
OLD,
        <<<'NEW'
    if ($type === 'checkbox') {
        return $value ? 'Yes' : null;
    }

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $labels = [];

        foreach ((array) $value as $selected) {
            $selectedKey = (string) $selected;

            if (array_key_exists($selectedKey, $options)) {
                $labels[] = (string) $options[$selectedKey];
            }
        }

        return $labels ? implode(', ', $labels) : null;
    }

    if ($type === 'select') {
NEW,
        'Support multiselect display values'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
    if (
        $state('max_trailer_length_feet') === 'answered'
        && is_numeric($value('max_trailer_length_feet'))
        && (float) $value('max_trailer_length_feet') <= 25
    ) {
        $add(
            'warning_limited_trailer_length',
            'Limited trailer length',
            'warning_limited_trailer_length'
        );
    }
OLD,
        <<<'NEW'
    if ($state('max_trailer_length_feet') === 'answered') {
        $maxTrailer = $value('max_trailer_length_feet');

        if ((string) $maxTrailer === 'not-recommended') {
            $add(
                'warning_limited_trailer_length',
                'Trailers not recommended',
                'warning_limited_trailer_length'
            );
        } elseif (
            is_numeric($maxTrailer)
            && (float) $maxTrailer <= 25
        ) {
            $add(
                'warning_limited_trailer_length',
                'Limited trailer length',
                'warning_limited_trailer_length'
            );
        }
    }
NEW,
        'Update trailer quick warning'
    );

    $output = [];
    $output['app/place-report.php'] = $text;

    /* ========================================================
     * partials/place-report/form.php
     * ======================================================== */
    $text = $source['partials/place-report/form.php'];

    $text = $replaceOnce(
        $text,
        <<<'OLD'
$valueFor =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportValues
    ): string {
OLD,
        <<<'NEW'
$valueFor =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportValues
    ): mixed {
NEW,
        'Allow array values in shared form'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
            return is_scalar($value)
                ? (string) $value
                : '';
OLD,
        <<<'NEW'
            if (is_array($value)) {
                return array_values($value);
            }

            return is_scalar($value)
                ? (string) $value
                : '';
NEW,
        'Return multiselect arrays to shared form'
    );

    $multiselectForm = <<<'NEW'
        /*
         * =====================================================
         * MULTISELECT WITH CHIPS
         * =====================================================
         */

        if ($type === 'multiselect') {
            $options = (array) ($field['options'] ?? []);
            $selectedValues = is_array($current)
                ? array_values(array_map('strval', $current))
                : [];
            $fieldId =
                'place-report-multiselect-'
                . preg_replace(
                    '/[^a-z0-9_-]+/i',
                    '-',
                    $key
                );
            ?>

            <div
                class="contribution-field contribution-field-wide place-report-multiselect-field"
                data-place-report-multiselect
            >
                <span class="place-report-field-label">
                    <span><?= $e($field['label']) ?></span>
                </span>

                <details class="place-report-multiselect-picker">
                    <summary>
                        <span data-place-report-multiselect-summary>
                            <?= $e($field['summary'] ?? '+ Add option') ?>
                        </span>
                        <span aria-hidden="true">▾</span>
                    </summary>

                    <div class="place-report-multiselect-menu">
                        <input
                            type="search"
                            class="place-report-multiselect-search"
                            placeholder="<?= $e($field['search_placeholder'] ?? 'Search...') ?>"
                            autocomplete="off"
                            data-place-report-multiselect-search
                        >

                        <div class="place-report-multiselect-options">
                            <?php foreach ($options as $optionValue => $optionLabel): ?>
                                <?php
                                $optionId =
                                    $fieldId
                                    . '-'
                                    . preg_replace(
                                        '/[^a-z0-9_-]+/i',
                                        '-',
                                        (string) $optionValue
                                    );
                                ?>
                                <label
                                    class="place-report-multiselect-option"
                                    data-place-report-multiselect-option
                                    data-search-text="<?= $e(strtolower((string) $optionLabel)) ?>"
                                >
                                    <input
                                        id="<?= $e($optionId) ?>"
                                        type="checkbox"
                                        name="<?= $e($key) ?>[]"
                                        value="<?= $e($optionValue) ?>"
                                        data-chip-label="<?= $e($optionLabel) ?>"
                                        <?= in_array((string) $optionValue, $selectedValues, true)
                                            ? 'checked'
                                            : '' ?>
                                    >
                                    <span><?= $e($optionLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>

                <div
                    class="place-report-chip-list"
                    data-place-report-chip-list
                    aria-live="polite"
                ></div>
            </div>

            <?php
            return;
        }

NEW;

    $text = $replaceOnce(
        $text,
        "        \$class =\n            'contribution-field'\n",
        $multiselectForm . "        \$class =\n            'contribution-field'\n",
        'Render multiselect chips in shared form'
    );

    $text = $replaceOnce(
        $text,
        <<<'OLD'
                ) === $sectionKey
        );
OLD,
        <<<'NEW'
                ) === $sectionKey
                && empty($field['hide_form'])
        );
NEW,
        'Hide legacy fields from editable forms'
    );

    if (!str_contains($text, '/js/place-report-multiselect.js')) {
        $text = rtrim($text)
            . "\n\n<script src=\"/js/place-report-multiselect.js\" defer></script>\n";
    }

    $output['partials/place-report/form.php'] = $text;

    /* ========================================================
     * partials/place-report/read-only.php
     * ======================================================== */
    $text = $source['partials/place-report/read-only.php'];

    $text = $replaceOnce(
        $text,
        <<<'OLD'
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
OLD,
        <<<'NEW'
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
<link
    rel="stylesheet"
    href="/css/site/features/place-report-landscape.css"
>
NEW,
        'Load Landscape read-only CSS'
    );

    $readOnlyMultiselect = <<<'NEW'
        if (
            $placeReportReadMode === 'scout-report'
            && $state === 'unanswered'
            && in_array(
                $key,
                ['landscape_primary', 'landscape_details', 'landscape_views'],
                true
            )
        ) {
            return;
        }

        if ($type === 'multiselect') {
            $rawValues =
                llama_place_report_get_path(
                    $placeReportData,
                    (string) $field['storage']
                );
            $options = (array) ($field['options'] ?? []);
            $labels = [];

            foreach ((array) $rawValues as $rawValue) {
                $rawKey = (string) $rawValue;

                if (array_key_exists($rawKey, $options)) {
                    $labels[] = (string) $options[$rawKey];
                }
            }
            ?>
            <div class="scout-report-item scout-report-chip-item scout-report-item-wide<?= $state === 'unanswered' ? ' is-unanswered' : '' ?>">
                <span class="scout-report-chip-label">
                    <?= $e(rtrim((string) $field['label'], '*')) ?>
                </span>

                <?php if ($labels): ?>
                    <div class="scout-report-chip-list">
                        <?php foreach ($labels as $label): ?>
                            <span class="scout-report-chip"><?= $e($label) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <strong>Not provided</strong>
                <?php endif; ?>
            </div>
            <?php
            return;
        }

NEW;

    $text = $replaceOnce(
        $text,
        <<<'OLD'
        $isRating =
            $type === 'rating';
OLD,
        $readOnlyMultiselect . <<<'NEW'
        $isRating =
            $type === 'rating';
NEW,
        'Render read-only multiselect chips'
    );

    $compatibility = <<<'NEW'
$hasStructuredLandscape = false;
foreach (['landscape_primary', 'landscape_details', 'landscape_views'] as $landscapeKey) {
    if (
        llama_place_report_answer_state(
            $placeReportData,
            $landscapeKey
        ) === 'answered'
    ) {
        $hasStructuredLandscape = true;
        break;
    }
}

NEW;

    $text = $replaceOnce(
        $text,
        <<<'OLD'
foreach (
    $placeReportSections
    as $sectionKey => $section
):
OLD,
        $compatibility . <<<'NEW'
foreach (
    $placeReportSections
    as $sectionKey => $section
):
    if (
        $sectionKey === 'environment_accessibility'
        && $hasStructuredLandscape
    ) {
        continue;
    }
NEW,
        'Preserve legacy environment display for old reports'
    );

    $output['partials/place-report/read-only.php'] = $text;

    /* ========================================================
     * css/site/features/place-report-form.css
     * ======================================================== */
    $text = rtrim($source['css/site/features/place-report-form.css']);

    if (!str_contains($text, 'LANDSCAPE MULTISELECT + CHIPS')) {
        $text .= <<<'CSS'


/* =========================================================
   LANDSCAPE MULTISELECT + CHIPS
   ========================================================= */

.place-report-form .place-report-multiselect-field {
    display: grid;
    gap: 7px;
}

.place-report-form .place-report-multiselect-picker {
    position: relative;
}

.place-report-form .place-report-multiselect-picker > summary {
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--background);
    color: var(--text);
    font-size: .78rem;
    font-weight: 760;
    cursor: pointer;
    list-style: none;
}

.place-report-form .place-report-multiselect-picker > summary::-webkit-details-marker {
    display: none;
}

.place-report-form .place-report-multiselect-picker[open] > summary {
    border-color: color-mix(in srgb, var(--text) 45%, var(--border));
    border-radius: 9px 9px 0 0;
}

.place-report-form .place-report-multiselect-menu {
    position: absolute;
    z-index: 30;
    inset: calc(100% - 1px) 0 auto;
    max-height: 320px;
    overflow: auto;
    padding: 9px;
    border: 1px solid var(--border);
    border-radius: 0 0 9px 9px;
    background: var(--background);
    box-shadow: 0 12px 28px rgb(0 0 0 / .16);
}

.place-report-form .place-report-multiselect-search {
    width: 100%;
    min-height: 38px;
    box-sizing: border-box;
    margin: 0 0 8px;
}

.place-report-form .place-report-multiselect-options {
    display: grid;
    gap: 4px;
}

.place-report-form .place-report-multiselect-option {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 9px;
    border-radius: 7px;
    cursor: pointer;
}

.place-report-form .place-report-multiselect-option:hover,
.place-report-form .place-report-multiselect-option:has(input:focus-visible) {
    background: color-mix(in srgb, var(--text) 6%, transparent);
}

.place-report-form .place-report-multiselect-option input {
    flex: 0 0 auto;
}

.place-report-form .place-report-multiselect-option span {
    font-size: .76rem;
    font-weight: 700;
}

.place-report-form .place-report-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.place-report-form .place-report-chip-list:empty {
    display: none;
}

.place-report-form .place-report-chip {
    min-height: 30px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px 5px 10px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: color-mix(in srgb, var(--text) 5%, var(--background));
    color: var(--text);
    font: inherit;
    font-size: .7rem;
    font-weight: 760;
    cursor: pointer;
}

.place-report-form .place-report-chip span[aria-hidden="true"] {
    font-size: 1rem;
    font-weight: 500;
    line-height: .8;
    color: var(--text-muted);
}

.place-report-form .place-report-chip:hover,
.place-report-form .place-report-chip:focus-visible {
    border-color: var(--text);
}

@media (max-width: 700px) {
    .place-report-form .place-report-multiselect-menu {
        max-height: 280px;
    }
}
CSS;
    }

    $output['css/site/features/place-report-form.css'] = $text . "\n";

    $output['css/site/features/place-report-landscape.css'] = <<<'CSS'
/* Landscape and setting display chips. */
.scout-report-chip-item {
    display: grid;
    gap: 8px;
}

.scout-report-chip-label {
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 760;
}

.scout-report-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.scout-report-chip {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 9px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: color-mix(in srgb, var(--text) 5%, var(--background));
    color: var(--text);
    font-size: .7rem;
    font-weight: 760;
}
CSS;

    $output['js/place-report-multiselect.js'] = <<<'JS'
(() => {
    'use strict';

    if (window.__llamaPlaceReportMultiselectLoaded) {
        return;
    }
    window.__llamaPlaceReportMultiselectLoaded = true;

    const enhance = (root) => {
        const details = root.querySelector('.place-report-multiselect-picker');
        const summary = root.querySelector('[data-place-report-multiselect-summary]');
        const search = root.querySelector('[data-place-report-multiselect-search]');
        const chips = root.querySelector('[data-place-report-chip-list]');
        const options = Array.from(root.querySelectorAll('[data-place-report-multiselect-option]'));
        const inputs = options
            .map((option) => option.querySelector('input[type="checkbox"]'))
            .filter((input) => input instanceof HTMLInputElement);

        if (!details || !summary || !chips || inputs.length === 0) {
            return;
        }

        const baseSummary = summary.textContent.trim();

        const sync = () => {
            const selected = inputs.filter((input) => input.checked);
            chips.replaceChildren();

            selected.forEach((input) => {
                const label = input.dataset.chipLabel || input.value;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'place-report-chip';
                button.setAttribute('aria-label', `Remove ${label}`);

                const text = document.createElement('span');
                text.textContent = label;
                button.appendChild(text);

                const close = document.createElement('span');
                close.setAttribute('aria-hidden', 'true');
                close.textContent = '×';
                button.appendChild(close);

                button.addEventListener('click', () => {
                    input.checked = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.focus({ preventScroll: true });
                });

                chips.appendChild(button);
            });

            summary.textContent = selected.length > 0
                ? `${baseSummary.replace(/^\+\s*/, '')} (${selected.length} selected)`
                : baseSummary;
        };

        inputs.forEach((input) => input.addEventListener('change', sync));

        if (search instanceof HTMLInputElement) {
            search.addEventListener('input', () => {
                const query = search.value.trim().toLowerCase();

                options.forEach((option) => {
                    const haystack = (option.dataset.searchText || option.textContent || '').toLowerCase();
                    option.hidden = query !== '' && !haystack.includes(query);
                });
            });

            details.addEventListener('toggle', () => {
                if (details.open) {
                    window.setTimeout(() => search.focus({ preventScroll: true }), 0);
                } else {
                    search.value = '';
                    options.forEach((option) => {
                        option.hidden = false;
                    });
                }
            });
        }

        sync();
        window.setTimeout(sync, 0);
        window.setTimeout(sync, 150);
    };

    document.querySelectorAll('[data-place-report-multiselect]').forEach(enhance);
})();
JS;

    /* Validate that modified PHP still parses before touching live files. */
    $lintDir = sys_get_temp_dir() . '/llama-landscape-' . bin2hex(random_bytes(4));
    if (!mkdir($lintDir, 0775, true) && !is_dir($lintDir)) {
        throw new RuntimeException('Could not create temporary lint directory.');
    }

    foreach ([
        'app/place-report.php',
        'partials/place-report/form.php',
        'partials/place-report/read-only.php',
    ] as $relative) {
        $lintFile = $lintDir . '/' . basename($relative);
        file_put_contents($lintFile, $output[$relative]);

        if (function_exists('exec')) {
            $command = 'php -l ' . escapeshellarg($lintFile) . ' 2>&1';
            $lines = [];
            $status = 0;
            exec($command, $lines, $status);

            if ($status !== 0) {
                throw new RuntimeException(
                    'PHP syntax check failed for ' . $relative . ":\n"
                    . implode("\n", $lines)
                );
            }
        }
    }

    foreach (glob($lintDir . '/*') ?: [] as $lintFile) {
        @unlink($lintFile);
    }
    @rmdir($lintDir);

    $backupRoot = $root
        . '/private/update-backups/landscape-setting-'
        . gmdate('Ymd-His');

    foreach ($source as $relative => $content) {
        $backupPath = $backupRoot . '/' . $relative;
        $backupDir = dirname($backupPath);

        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Could not create backup directory: ' . $backupDir);
        }

        if (file_put_contents($backupPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('Could not back up: ' . $relative);
        }
    }

    $written = [];

    try {
        foreach ($output as $relative => $content) {
            $atomicWrite($root . '/' . $relative, $content);
            $written[] = $relative;
        }
    } catch (Throwable $writeError) {
        foreach ($source as $relative => $content) {
            try {
                $atomicWrite($root . '/' . $relative, $content);
            } catch (Throwable) {
                /* Keep the original error. The private backup remains available. */
            }
        }

        foreach ([
            'css/site/features/place-report-landscape.css',
            'js/place-report-multiselect.js',
        ] as $newRelative) {
            if (!array_key_exists($newRelative, $source)) {
                @unlink($root . '/' . $newRelative);
            }
        }

        throw $writeError;
    }

    echo "Llama Scout Landscape + setting update applied.\n\n";
    echo "Changed:\n";
    foreach ($written as $relative) {
        echo "  - " . $relative . "\n";
    }
    echo "\nBackup:\n  " . $backupRoot . "\n";
    echo "\nThe updater is deleting itself now.\n";

    @unlink(__FILE__);

} catch (Throwable $error) {
    http_response_code(500);
    echo "UPDATE STOPPED\n\n";
    echo $error->getMessage() . "\n\n";
    echo "No intentional source changes were made unless the message above says a write failed.\n";
    exit;
}

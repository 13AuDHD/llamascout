<?php

declare(strict_types=1);

// Run with: php tools/tests/resource-hours.php
require_once dirname(__DIR__, 2) . '/app/resources.php';

function resource_hours_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    }

    echo "PASS: {$message}\n";
}

$options =
    llama_resource_hour_options();

resource_hours_assert(
    count($options) === 24,
    'Hour selector exposes exactly 24 hourly values.'
);

resource_hours_assert(
    ($options[0] ?? '') === '12 AM'
        && ($options[11] ?? '') === '11 AM'
        && ($options[12] ?? '') === '12 PM'
        && ($options[23] ?? '') === '11 PM',
    'Hour labels run from 12 AM through 11 PM.'
);

resource_hours_assert(
    llama_resource_hours_summary([
        'hours_mode' => '24_hours',
    ]) === '24 Hours',
    '24-hour Resources keep the compact 24 Hours label.'
);

resource_hours_assert(
    llama_resource_hours_summary([
        'hours_mode' => 'fixed_hours',
        'fixed_open_hour' => 8,
        'fixed_close_hour' => 17,
    ]) === '8 AM - 5 PM',
    'Fixed hours render a compact public summary.'
);

resource_hours_assert(
    llama_resource_hours_summary([
        'hours_mode' => 'varied_hours',
    ]) === 'Varied Hours',
    'Varied hours keep a compact public summary.'
);

echo "Resource hours checks passed.\n";

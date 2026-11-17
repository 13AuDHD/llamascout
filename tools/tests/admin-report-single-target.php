<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report/admin-save-routing.php';

$expected = [
    'place' => ['place'],
    'site' => ['site'],
    'facility' => ['area'],
    'area' => ['area'], // legacy mapped-Area editor, not a second target
];

foreach ($expected as $mode => $writers) {
    $actual = llama_admin_report_save_targets($mode);
    if ($actual !== $writers) {
        throw new RuntimeException("Unexpected Admin write target for {$mode}.");
    }
    if (count($actual) !== 1) {
        throw new RuntimeException("Admin save {$mode} must have only one writer.");
    }
}

if (llama_admin_report_save_targets('', true) !== []) {
    throw new RuntimeException('Photo-only saves must not update report answers.');
}

foreach (['', 'all', 'resource', 'site,place'] as $invalid) {
    try {
        llama_admin_report_save_targets($invalid);
        throw new RuntimeException("Invalid report mode was accepted: {$invalid}");
    } catch (InvalidArgumentException) {
        // A stale form must not fall back to multi-target writes.
    }
}

echo "PASS: Admin report save routes to exactly one target type.\n";

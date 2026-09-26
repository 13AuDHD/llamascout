<?php

declare(strict_types=1);

/*
 * CLI worker for cPanel Cron.
 *
 * Example:
 * php /full/path/to/public_html/jobs/cell-coverage-sync.php
 *
 * The script performs as much work as it safely can for about
 * four minutes, then exits. A later cron run resumes from the
 * persistent private sync state file.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fcc-cell-sync.php';

set_time_limit(0);

try {
    if (!llama_fcc_sync_is_configured()) {
        fwrite(
            STDERR,
            "FCC API credentials are not configured.\n"
        );

        exit(2);
    }

    $state =
        llama_fcc_sync_load_state();

    /*
     * When no run exists, or the previous run is complete and
     * the last FCC check is over 12 hours old, create a fresh
     * plan. This makes the cron worker double as the update
     * checker without hammering the FCC API.
     */
    $shouldPlan =
        !$state;

    if (
        $state
        && ($state['status'] ?? '')
            === 'complete'
    ) {
        $lastCheck =
            strtotime(
                (string) (
                    $state['last_check_at']
                    ?? ''
                )
            );

        $shouldPlan =
            !$lastCheck
            || (time() - $lastCheck)
                >= 43200;
    }

    if ($shouldPlan) {
        $state =
            llama_fcc_sync_create_plan();
    }

    if (
        ($state['status'] ?? '')
        !== 'complete'
    ) {
        $state =
            llama_fcc_sync_worker(
                240
            );
    }

    $public =
        llama_fcc_sync_public_state(
            $state
        );

    fwrite(
        STDOUT,
        json_encode(
            $public,
            JSON_UNESCAPED_SLASHES
        )
        . PHP_EOL
    );

    exit(
        ($public['status'] ?? '')
            === 'error'
                ? 1
                : 0
    );

} catch (Throwable $e) {
    fwrite(
        STDERR,
        $e->getMessage()
        . PHP_EOL
    );

    exit(1);
}

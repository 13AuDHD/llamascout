<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT
   PUBLIC CELL COVERAGE UPDATE BANNER

   Intentionally lightweight. The public map only needs to know
   whether the Admin-controlled banner switch is enabled; it does
   not load the FCC worker/sync implementation.
   ========================================================= */

function llama_cell_coverage_update_banner_enabled(): bool
{
    try {
        $db = cell_db();

        $table = $db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?"
        );
        $table->execute([
            'cell_coverage_v2_settings',
        ]);

        if ((int) $table->fetchColumn() !== 1) {
            return false;
        }

        $stmt = $db->prepare(
            'SELECT setting_value
             FROM cell_coverage_v2_settings
             WHERE setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([
            'map_update_banner_enabled',
        ]);

        return (string) ($stmt->fetchColumn() ?: '0') === '1';

    } catch (Throwable $exception) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception(
                $exception,
                'cell_coverage.public_banner'
            );
        }

        return false;
    }
}

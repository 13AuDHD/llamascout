<?php

declare(strict_types=1);

/**
 * Canonical applicability orchestrator.
 *
 * Applicability controls whether a question belongs to the current form and
 * completion denominator. It never deletes stored answers and does not award
 * Scout points.
 */
require_once __DIR__ . '/applicability/common.php';
require_once __DIR__ . '/applicability/camping.php';
require_once __DIR__ . '/applicability/overnight.php';
require_once __DIR__ . '/applicability/facilities.php';
require_once __DIR__ . '/applicability/parking.php';

function llama_place_report_apply_applicability(
    array &$fields
): void {
    llama_place_report_apply_common_applicability(
        $fields
    );

    llama_place_report_apply_camping_profiles(
        $fields
    );

    llama_place_report_apply_overnight_applicability(
        $fields
    );

    llama_place_report_apply_facility_profiles(
        $fields
    );

    llama_place_report_apply_parking_profiles(
        $fields
    );
}

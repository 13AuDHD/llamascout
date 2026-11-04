<?php

declare(strict_types=1);

/**
 * Legacy overnight applicability.
 *
 * V3 owner review removed the old broad "overnight permitted" parent from
 * current approved questions. Current dependencies are defined by the
 * canonical section-specific rules instead:
 *
 * - Camping & Overnight Stays questions are independent.
 * - Fees/Reservations use their own direct Yes -> detail relationships.
 * - Rules use their own direct parent/child relationships.
 * - Nighttime sensory and Experience questions are independent Site facts.
 *
 * Historical answers remain readable; this function intentionally adds no
 * applicability rules to the active registry.
 */
function llama_place_report_apply_overnight_applicability(
    array &$fields
): void {
}

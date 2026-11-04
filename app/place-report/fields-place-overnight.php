<?php

declare(strict_types=1);

/**
 * Historical overnight-mode questions were retired during the V3 owner review.
 *
 * The active approved Place questionnaire keeps Overnight Stay Permitted,
 * Dispersed Camping Permitted, and Designated Sites Only as independent facts.
 * Historical values for the retired questions remain in old reports/storage.
 */
function llama_place_report_add_overnight_permission_fields(
    callable $add
): void {
    // Intentionally empty. Keep the registration hook so older includes and
    // deployment paths do not need a parallel compatibility branch.
}

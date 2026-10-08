<?php

declare(strict_types=1);

/*
 * LLAMA SCOUT PLACE REPORT
 * SINGLE SOURCE OF TRUTH
 *
 * Public loader. The implementation is split into focused files under
 * /app/place-report/ so field work no longer requires editing one 155 KB file.
 */

require_once __DIR__ . '/place-report/core.php';
require_once __DIR__ . '/place-report/definitions.php';
require_once __DIR__ . '/place-report/fields-basic-location.php';
require_once __DIR__ . '/place-report/fields-site.php';
require_once __DIR__ . '/place-report/fields-experience.php';
require_once __DIR__ . '/place-report/fields-summaries.php';
require_once __DIR__ . '/place-report/applicability.php';
require_once __DIR__ . '/place-report/fields.php';
require_once __DIR__ . '/place-report/runtime.php';

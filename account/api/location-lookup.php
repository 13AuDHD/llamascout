<?php

declare(strict_types=1);

/*
 * Same-origin bridge for shared Place Report JavaScript running on
 * account.llamascout.com. The canonical implementation stays in /api.
 */
require dirname(__DIR__, 2)
    . '/api/location-lookup.php';

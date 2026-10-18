<?php

declare(strict_types=1);

/**
 * Llama Scout's single source for logo assets.
 * Change only the filename here when the approved master logo changes.
 * This module has no database, session, or bootstrap dependencies.
 */
function llama_brand_logo_url(): string
{
    return 'https://llamascout.com/images/logo-png.png';
}

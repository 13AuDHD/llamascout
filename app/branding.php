<?php

declare(strict_types=1);

/**
 * Central Llama Scout branding assets.
 *
 * Change logo filenames here, not in individual page templates.
 * Both images are served from the public site's /images directory.
 */
function llama_brand_logo_url(): string
{
    // Single-image fallback for emails and existing templates.
    return 'https://llamascout.com/images/logo-lt.png';
}

function llama_brand_logo_dark_url(): string
{
    // Dark lettering, intended for light backgrounds.
    return 'https://llamascout.com/images/logo-dk.png';
}

function llama_brand_logo_light_url(): string
{
    // White lettering, intended for dark backgrounds.
    return 'https://llamascout.com/images/logo-lt.png';
}

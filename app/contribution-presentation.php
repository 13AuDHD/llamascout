<?php

declare(strict_types=1);

require_once __DIR__ . '/maintenance-mode.php';

const LLAMA_CONTRIBUTION_PHOTO_SETTING = 'contribution_fallback_photo';

function llama_contribution_photo_path(string $value): ?string
{
    // Use existing public images from the Llama Scout host.
    return preg_match('~^/images/(?:[a-z0-9_-]+/)*[a-z0-9_.-]+\.(?:jpe?g|png|webp|avif|svg)$~iD', $value)
        ? $value
        : null;
}

function llama_contribution_photo_url(PDO $db): string
{
    $path = llama_site_setting($db, LLAMA_CONTRIBUTION_PHOTO_SETTING, '') ?? '';

    return 'https://llamascout.com' . (
        llama_contribution_photo_path($path) ?? '/images/demo/llama-scout-headquarters-hero.jpeg'
    );
}

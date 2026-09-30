<?php

declare(strict_types=1);

/*
 * Place-page map helpers.
 *
 * These mirror the base-map choices used by map.php without requiring the
 * Place page to download the complete /api/places.php response just to obtain
 * the two authenticated Geoapify tile URLs.
 */

function llama_place_map_geoapify_api_key(): string
{
    $config = llama_config();

    $candidates = [
        $config['geoapify']['api_key'] ?? null,
        $config['geoapify']['key'] ?? null,
        $config['geoapify_api_key'] ?? null,
        $config['services']['geoapify']['api_key'] ?? null,
        $config['services']['geoapify']['key'] ?? null,
        $config['apis']['geoapify']['api_key'] ?? null,
        $config['apis']['geoapify']['key'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);

        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}


function llama_place_map_member_tiles(): array
{
    $apiKey = llama_place_map_geoapify_api_key();

    if ($apiKey === '') {
        return [
            'available' => false,
            'light' => '',
            'dark' => '',
        ];
    }

    $encodedKey = rawurlencode($apiKey);

    return [
        'available' => true,

        'light' =>
            'https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png'
            . '?apiKey=' . $encodedKey,

        'dark' =>
            'https://maps.geoapify.com/v1/tile/dark-matter/{z}/{x}/{y}.png'
            . '?apiKey=' . $encodedKey,
    ];
}

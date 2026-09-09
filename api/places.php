<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');


/*
 * Llama Scout has used a few private-config layouts over time.
 * Resolve the existing Geoapify key without hardcoding it into public JS.
 */
function llama_geoapify_api_key(): string
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


function llama_member_map_tiles(): array
{
    $apiKey = llama_geoapify_api_key();

    if ($apiKey === '') {
        return [
            'geoapify_available' => false,
            'light' => null,
            'dark' => null,
        ];
    }

    $encodedKey = rawurlencode($apiKey);

    return [
        'geoapify_available' => true,

        /*
         * Geoapify raster tiles work directly with Leaflet.
         * The key is restricted to authenticated member-map responses.
         */
        'light' =>
            'https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png'
            . '?apiKey=' . $encodedKey,

        'dark' =>
            'https://maps.geoapify.com/v1/tile/dark-matter/{z}/{x}/{y}.png'
            . '?apiKey=' . $encodedKey,
    ];
}


try {
    /*
     * Never send exact coordinates unless the current authenticated account
     * actually has member access. The browser is not trusted to enforce this.
     */
    $hasMemberMapAccess = user_has_member_access();
    $places = places_map($hasMemberMapAccess);

    echo json_encode(
        [
            'ok' => true,
            'count' => count($places),
            'member_map_access' => $hasMemberMapAccess,
            'coordinate_precision' => $hasMemberMapAccess ? 'exact' : 'approximate',
            'max_zoom' => $hasMemberMapAccess ? 20 : 11,

            /*
             * Tile URLs are only supplied to member sessions.
             * Free/logged-out users continue using the existing public OSM layer.
             */
            'member_tiles' =>
                $hasMemberMapAccess
                    ? llama_member_map_tiles()
                    : null,

            'places' => $places,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    $reference = llama_log_caught_exception($e, 'api_places');
    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'error' => llama_error_message_with_reference('Unable to load places.', $reference),
            'reference' => $reference,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

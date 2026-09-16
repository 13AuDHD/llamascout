<?php

declare(strict_types=1);

/*
 * Protect server-side proxy endpoints that can generate paid or
 * rate-limited third-party traffic.
 *
 * The lookup pages all load app/bootstrap.php, including the Account
 * and Admin same-origin bridge endpoints, so one guard covers every
 * hostname without duplicating security logic.
 */

function llama_external_lookup_json_response(
    int $status,
    string $message
): never {
    http_response_code($status);

    if (!headers_sent()) {
        header(
            'Content-Type: application/json; charset=UTF-8'
        );

        header(
            'Cache-Control: no-store, max-age=0'
        );
    }

    echo json_encode(
        [
            'success' => false,
            'message' => $message,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


function llama_external_lookup_endpoint(): ?string
{
    $script =
        strtolower(
            str_replace(
                '\\',
                '/',
                (string) (
                    $_SERVER['SCRIPT_NAME']
                    ?? ''
                )
            )
        );

    if (
        $script === ''
        || !str_contains(
            $script,
            '/api/'
        )
    ) {
        return null;
    }

    $basename =
        basename(
            $script
        );

    return match ($basename) {
        'location-lookup.php' =>
            'location_lookup',

        'address-autocomplete.php' =>
            'address_autocomplete',

        default =>
            null,
    };
}


function llama_external_lookup_rate_limit(
    string $bucket,
    int $limit,
    int $windowSeconds
): void {
    $limit =
        max(
            1,
            $limit
        );

    $windowSeconds =
        max(
            1,
            $windowSeconds
        );

    $now =
        time();

    if (
        !isset(
            $_SESSION[
                'llama_external_lookup_limits'
            ]
        )
        || !is_array(
            $_SESSION[
                'llama_external_lookup_limits'
            ]
        )
    ) {
        $_SESSION[
            'llama_external_lookup_limits'
        ] = [];
    }

    $limits =
        &$_SESSION[
            'llama_external_lookup_limits'
        ];

    $entry =
        $limits[$bucket]
        ?? null;

    if (
        !is_array($entry)
        || (int) (
            $entry['reset_at']
            ?? 0
        ) <= $now
    ) {
        $entry = [
            'count' => 0,
            'reset_at' =>
                $now
                + $windowSeconds,
        ];
    }

    $count =
        max(
            0,
            (int) (
                $entry['count']
                ?? 0
            )
        );

    $resetAt =
        max(
            $now + 1,
            (int) (
                $entry['reset_at']
                ?? (
                    $now
                    + $windowSeconds
                )
            )
        );

    if ($count >= $limit) {
        $limits[$bucket] =
            $entry;

        $retryAfter =
            max(
                1,
                $resetAt
                - $now
            );

        if (!headers_sent()) {
            header(
                'Retry-After: '
                . $retryAfter
            );

            header(
                'X-RateLimit-Limit: '
                . $limit
            );

            header(
                'X-RateLimit-Remaining: 0'
            );
        }

        llama_external_lookup_json_response(
            429,
            'Too many lookup requests. Wait a moment and try again.'
        );
    }

    $count++;

    $entry['count'] =
        $count;

    $entry['reset_at'] =
        $resetAt;

    $limits[$bucket] =
        $entry;

    if (!headers_sent()) {
        header(
            'X-RateLimit-Limit: '
            . $limit
        );

        header(
            'X-RateLimit-Remaining: '
            . max(
                0,
                $limit
                - $count
            )
        );
    }
}


function llama_protect_external_lookup_request(): void
{
    $endpoint =
        llama_external_lookup_endpoint();

    if ($endpoint === null) {
        return;
    }

    /*
     * These endpoints exist only to support authenticated Llama Scout
     * workflows. Returning JSON here avoids require_login() redirecting an
     * AJAX request to an HTML login page.
     */
    if (!is_logged_in()) {
        llama_external_lookup_json_response(
            401,
            'Sign in to use this lookup service.'
        );
    }

    $user =
        current_user();

    $userId =
        (int) (
            $user['id']
            ?? 0
        );

    if ($userId < 1) {
        llama_external_lookup_json_response(
            401,
            'Sign in to use this lookup service.'
        );
    }

    /*
     * Location Lookup is intentionally much tighter because one request
     * can fan out to Nominatim, Overpass, and Open-Meteo.
     *
     * Address autocomplete is allowed a larger burst so ordinary typing
     * and reverse-address lookup remain responsive.
     */
    if ($endpoint === 'location_lookup') {
        llama_external_lookup_rate_limit(
            'location_lookup:' . $userId,
            10,
            60
        );

        return;
    }

    llama_external_lookup_rate_limit(
        'address_autocomplete:' . $userId,
        60,
        60
    );
}

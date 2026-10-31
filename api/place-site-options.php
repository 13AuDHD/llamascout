<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';

header('Content-Type: application/json; charset=utf-8');

function llama_place_site_options_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    llama_place_site_options_json(
        [
            'ok' => false,
            'error' => 'Method not allowed.',
        ],
        405
    );
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if ($userId < 1) {
    llama_place_site_options_json(
        [
            'ok' => false,
            'error' => 'Sign in is required.',
        ],
        401
    );
}

$placeId = max(
    0,
    (int) ($_GET['place_id'] ?? 0)
);

$slug = trim(
    (string) ($_GET['slug'] ?? '')
);

try {
    $db = db();

    if ($placeId < 1 && $slug !== '') {
        $visiblePlace = place_public_by_slug($slug);

        if (!$visiblePlace) {
            llama_place_site_options_json(
                ['ok' => false, 'error' => 'Place not found.'],
                404
            );
        }

        $placeId = (int) ($visiblePlace['id'] ?? 0);
    }

    if ($placeId < 1) {
        llama_place_site_options_json(
            [
                'ok' => false,
                'error' => 'A valid Place is required.',
            ],
            400
        );
    }

    $placeStmt = $db->prepare(
        'SELECT id, slug, name, type
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $placeStmt->execute([
        $placeId,
    ]);

    $place =
        $placeStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$place) {
        llama_place_site_options_json(
            [
                'ok' => false,
                'error' => 'Place not found.',
            ],
            404
        );
    }

    // Do not reveal even the Place identity to unauthorized accounts
    // through this alternate Site selector endpoint.
    if (
        llama_place_is_member_only($place)
        && !llama_place_viewer_can_read_member_only($placeId, $userId)
    ) {
        llama_place_site_options_json(
            ['ok' => false, 'error' => 'Place not found.'],
            404
        );
    }

    $sites =
        llama_place_campsites(
            $db,
            $placeId
        );

    $hasCompleteAccess = user_has_place_complete_access($placeId, $userId);
    $restrictedSiteIds = $hasCompleteAccess
        ? []
        : llama_site_restricted_visibility_ids($db, $placeId);
    $sites = array_values(array_filter(
        $sites,
        static fn(array $site): bool =>
            llama_site_can_read($place, $site, $hasCompleteAccess, $restrictedSiteIds)
    ));

    $options = [];

    foreach ($sites as $site) {
        $siteId =
            (int) ($site['id'] ?? 0);

        if ($siteId < 1) {
            continue;
        }

        $siteCode =
            trim(
                (string) (
                    $site['site_code']
                    ?? ''
                )
            );

        $siteName =
            trim(
                (string) (
                    $site['site_name']
                    ?? ''
                )
            );

        $displayName =
            trim(
                (string) (
                    $site['display_name']
                    ?? ''
                )
            );

        if ($displayName === '') {
            $displayName =
                $siteCode !== ''
                    ? $siteCode
                    : (
                        $siteName !== ''
                            ? $siteName
                            : 'Site ' . $siteId
                    );
        }

        $canonicalIdentifier =
            $siteCode !== ''
                ? $siteCode
                : $siteName;

        $options[] = [
            'id' => $siteId,
            'site_code' => $siteCode,
            'site_name' => $siteName,
            'display_name' => $displayName,
            'canonical_identifier' =>
                $canonicalIdentifier,
        ];
    }

    llama_place_site_options_json([
        'ok' => true,
        'place' => [
            'id' =>
                (int) $place['id'],
            'slug' =>
                (string) $place['slug'],
            'name' =>
                (string) $place['name'],
            'type' =>
                (string) ($place['type'] ?? ''),
        ],
        'sites' => $options,
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'place.site_options.api',
        [
            'place_id' => $placeId,
            'slug' => $slug,
            'user_id' => $userId,
        ]
    );

    llama_place_site_options_json(
        [
            'ok' => false,
            'error' =>
                'Campsite options could not be loaded.',
        ],
        500
    );
}

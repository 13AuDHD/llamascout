<?php

declare(strict_types=1);

/**
 * V3 Site visibility policy. The database opt-in is deliberately fail-closed.
 *
 * A Place's public listing does not make its Sites public. Dispersed Sites
 * remain member-only, including imported and featured/curated records.
 * Developed campground Sites become public only after an explicit review
 * of both Site classification and visibility.
 */
function llama_site_public_visibility_ids(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        return [];
    }

    try {
        $stmt = $db->prepare(
            'SELECT campsite_id FROM place_site_visibility
             WHERE place_id = ? AND site_kind = "developed" AND visibility = "public"'
        );
        $stmt->execute([$placeId]);
        return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    } catch (PDOException $exception) {
        // Deploying PHP before its SQL migration must not expose Sites.
        // Other database errors should still be logged by the caller.
        $code = (string) $exception->getCode();
        if ($code === '42S02' || $code === '1146') {
            return [];
        }
        throw $exception;
    }
}

/**
 * True only for a specifically reviewed, ordinary developed-campground Site.
 * Do not infer public access from a missing row or a "primitive" label.
 */
function llama_site_is_public(array $place, array $site, array $publicSiteIds): bool
{
    $placeType = strtolower(trim((string) ($place['type'] ?? '')));
    if (!in_array($placeType, ['developed-campground', 'rv-park-resort'], true)) {
        return false;
    }

    // Site type is not a privacy classification. These explicit markers
    // only act as additional restrictions, never as permission to publish.
    foreach (['raw_site_type', 'site_type'] as $field) {
        if (str_contains(strtolower((string) ($site[$field] ?? '')), 'dispersed')) {
            return false;
        }
    }

    $siteId = (int) ($site['id'] ?? 0);
    return $siteId > 0 && isset($publicSiteIds[$siteId]);
}

/**
 * All authorized members and Place-specific contributors can see every
 * Site. Everyone else sees only explicitly public developed Sites.
 */
function llama_site_can_read(
    array $place,
    array $site,
    bool $hasCompletePlaceAccess,
    array $publicSiteIds
): bool {
    if ($hasCompletePlaceAccess) {
        return true;
    }
    return llama_site_is_public($place, $site, $publicSiteIds);
}

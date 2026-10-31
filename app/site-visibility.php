<?php

declare(strict_types=1);

/**
 * V3 Site privacy is derived from the canonical Place type.
 *
 * Ordinary Sites at developed campgrounds and RV parks have public basic
 * catalog/map identity. Sites at dispersed and other Places are member-only.
 * Site-specific records in place_site_visibility are RESTRICTIONS only.
 * They can never make a dispersed Place or Site public.
 *
 * The existing table is retained for mixed-use exceptions and old reviews.
 * Missing table/schema is an error, not permission to publish.
 */
function llama_site_restricted_visibility_ids(PDO $db, int $placeId): array
{
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT campsite_id FROM place_site_visibility
         WHERE place_id = ?
           AND (visibility = "members"
                OR site_kind IN ("dispersed", "unreviewed"))'
    );
    $stmt->execute([$placeId]);
    return array_fill_keys(
        array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
        true
    );
}

/**
 * The third argument contains restricted Site IDs. Absence of an override
 * follows Place type.
 */
function llama_site_is_public(array $place, array $site, array $restrictedSiteIds): bool
{
    $placeType = strtolower(trim((string) ($place['type'] ?? '')));
    if (!in_array($placeType, ['developed-campground', 'rv-park-resort'], true)) {
        return false;
    }

    // Legacy imports do not always use the word "dispersed".
    // Treat primitive/backcountry labels as restricted until explicitly
    // reconciled; a public developed Place cannot silently publish them.
    foreach (['raw_site_type', 'site_type'] as $field) {
        $siteType = strtolower((string) ($site[$field] ?? ''));
        foreach (['dispersed', 'primitive', 'backcountry', 'boondock'] as $marker) {
            if (str_contains($siteType, $marker)) {
                return false;
            }
        }
    }

    $siteId = (int) ($site['id'] ?? 0);
    return $siteId > 0 && !isset($restrictedSiteIds[$siteId]);
}

function llama_site_can_read(
    array $place,
    array $site,
    bool $hasCompletePlaceAccess,
    array $restrictedSiteIds
): bool {
    if ($hasCompletePlaceAccess) {
        return true;
    }
    return llama_site_is_public($place, $site, $restrictedSiteIds);
}

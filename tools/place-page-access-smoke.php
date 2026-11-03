<?php

declare(strict_types=1);

// Run with: php tools/place-page-access-smoke.php

function place_page_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    }

    echo "PASS: {$message}\n";
}

$root =
    dirname(__DIR__);

$placePage =
    file_get_contents(
        $root . '/place.php'
    );

$placesSource =
    file_get_contents(
        $root . '/app/places.php'
    );

$siteListApi =
    file_get_contents(
        $root . '/api/place-campsites.php'
    );

$siteDetailApi =
    file_get_contents(
        $root . '/api/place-campsite-detail.php'
    );

$publicAreaApi =
    file_get_contents(
        $root . '/api/place-map-public-areas.php'
    );

$publicScoutFacts =
    file_get_contents(
        $root . '/partials/place/public-scout-facts.php'
    );

$sitemap =
    file_get_contents(
        $root . '/sitemap.php'
    );

place_page_assert(
    str_contains(
        $placePage,
        'place_presentation_by_slug('
    ),
    'Place page uses the canonical Place presentation loader.'
);

place_page_assert(
    !str_contains(
        $placePage,
        'place_public_by_slug('
    )
    && !str_contains(
        $placePage,
        'place_member_by_slug('
    ),
    'Place page no longer selects public/member Place loaders.'
);

place_page_assert(
    !str_contains(
        $publicScoutFacts,
        'place_member_by_slug('
    )
    && str_contains(
        $publicScoutFacts,
        '$placeReportSource'
    ),
    'Public Scout facts reuse the canonical Place presentation source.'
);

place_page_assert(
    str_contains(
        $placesSource,
        'function place_presentation_by_slug('
    )
    && str_contains(
        $placesSource,
        "llama_place_visibility_sql('p')"
    ),
    'Canonical Place presentation loader preserves the Place visibility boundary.'
);

foreach ([
    "'place_details'",
    "'place_connectivity'",
    "'place_sensory_details'",
    "'place_rules'",
    "'place_experience'",
    'place_gallery_images(',
    'place_member_sensory(',
] as $needle) {
    place_page_assert(
        str_contains(
            $placesSource,
            $needle
        ),
        'Canonical Place presentation model hydrates ' . $needle . '.'
    );
}

place_page_assert(
    str_contains(
        $siteListApi,
        '$publicPlace = place_public_by_slug($slug);'
    ),
    'Site list API authorizes the parent Place before returning Site data.'
);

place_page_assert(
    str_contains(
        $siteDetailApi,
        '$publicPlace = place_public_by_slug($slug);'
    )
    && str_contains(
        $siteDetailApi,
        'llama_site_can_read('
    ),
    'Site detail API enforces parent Place and Site visibility.'
);

place_page_assert(
    str_contains(
        $publicAreaApi,
        'place_public_by_slug('
    ),
    'Public Area endpoint authorizes the parent Place first.'
);

place_page_assert(
    str_contains(
        $sitemap,
        'type NOT IN ("dispersed-camping", "vehicle-pulloff")'
    ),
    'Sitemap excludes restricted Place types.'
);

echo "Place page access contract: passed\n";

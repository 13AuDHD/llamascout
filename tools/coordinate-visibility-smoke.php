<?php

declare(strict_types=1);

$testViewer = 0;
$testCompleteAccess = [];
$testContributedPlaces = [];

function current_user(): ?array
{
    global $testViewer;

    return $testViewer > 0
        ? ['id' => $testViewer]
        : null;
}

function user_has_member_access(?int $userId = null): bool
{
    global $testCompleteAccess;

    return isset($testCompleteAccess[(int) $userId]);
}

function user_original_contributed_place_ids(int $userId): array
{
    global $testContributedPlaces;

    return $testContributedPlaces[$userId] ?? [];
}

function user_has_place_complete_access(int $placeId, ?int $userId = null): bool
{
    return user_has_member_access($userId)
        || in_array(
            $placeId,
            user_original_contributed_place_ids((int) $userId),
            true
        );
}

require_once dirname(__DIR__) . '/app/site-visibility.php';

function visibility_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$guestSql = llama_place_visibility_sql('p', 0);
visibility_assert(
    str_contains($guestSql, 'dispersed-camping')
        && !str_contains($guestSql, 'p.id IN'),
    'Guest Place query must exclude restricted types.'
);

$freeSql = llama_place_visibility_sql('p', 10);
visibility_assert(
    str_contains($freeSql, 'vehicle-pulloff')
        && !str_contains($freeSql, 'p.id IN'),
    'Ordinary Free Member Place query must exclude restricted types.'
);

$testContributedPlaces[11] = [42];
$earnedSql = llama_place_visibility_sql('p', 11);
visibility_assert(
    str_contains($earnedSql, 'p.id IN (42)'),
    'Earned Place access must include the contributed restricted Place.'
);

$testCompleteAccess[12] = true;
visibility_assert(
    llama_place_visibility_sql('p', 12) === '1 = 1',
    'Complete Access must include restricted Places.'
);

$developedPlace = ['id' => 1, 'type' => 'developed-campground'];
$ordinarySite = [
    'id' => 100,
    'site_type' => 'rv_site',
    'raw_site_type' => 'STANDARD NONELECTRIC',
];
$primitiveSite = [
    'id' => 101,
    'site_type' => 'tent_site',
    'raw_site_type' => 'Primitive backcountry',
];
$vehiclePulloffSite = [
    'id' => 102,
    'site_type' => 'vehicle-pulloff',
    'raw_site_type' => 'Vehicle pull-off',
];

visibility_assert(
    llama_site_is_public($developedPlace, $ordinarySite, []),
    'Ordinary developed-campground Site must remain public.'
);
visibility_assert(
    !llama_site_is_public($developedPlace, $primitiveSite, []),
    'Primitive Site nested in a developed campground must remain restricted.'
);
visibility_assert(
    !llama_site_is_public($developedPlace, $vehiclePulloffSite, []),
    'Vehicle pulloff Site nested in a developed campground must remain restricted.'
);
visibility_assert(
    !llama_site_is_public($developedPlace, $ordinarySite, [100 => true]),
    'Reviewed Site restriction must override developed-campground defaults.'
);
visibility_assert(
    !llama_site_is_public(
        ['id' => 2, 'type' => 'dispersed-camping'],
        $ordinarySite,
        []
    ),
    'Sites in a dispersed Place must remain restricted.'
);
visibility_assert(
    llama_site_can_read(
        ['id' => 2, 'type' => 'dispersed-camping'],
        $ordinarySite,
        true,
        [100 => true]
    ),
    'Complete Place access must reveal restricted Sites.'
);

$officialSite = $ordinarySite + [
    'source_provider' => 'Recreation.gov / RIDB',
    'site_length_ft' => '40',
    'max_people' => '8',
];
visibility_assert(
    count(llama_site_public_catalog_details($officialSite)) === 2,
    'Official public Site facts must remain available.'
);
visibility_assert(
    llama_site_public_catalog_details(
        $ordinarySite + ['source_provider' => 'Community']
    ) === [],
    'Non-official Site facts must remain gated.'
);

visibility_assert(
    llama_coordinate_status('37.123456', '-107.654321', '2026-10-31')
        === 'verified',
    'Stored valid coordinates should be marked verified.'
);
visibility_assert(
    llama_coordinate_status('37.123456', '-107.654321') === 'unverified',
    'Stored coordinates without verification should be flagged.'
);
visibility_assert(
    llama_coordinate_status(null, null) === 'missing',
    'Missing coordinates must not be invented.'
);

echo "coordinate visibility smoke: passed\n";

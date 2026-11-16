<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/admin-place-image-status.php';

$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
};

$neutral = llama_admin_place_image_status([
    'status' => 'active', 'camping_fee_status' => 'unknown', 'is_closed' => false,
]);
$check($neutral['state'] === 'neutral' && $neutral['badge'] === '',
    'An active Place without closure/pricing evidence must keep a neutral border.');
$closed = llama_admin_place_image_status([
    'status' => 'featured', 'camping_fee_status' => 'paid', 'is_closed' => true,
    'map_partner' => [
        'id' => 6, 'use_branded_cards' => true, 'primary_color' => '#FBD318',
        'relationship' => 'affiliate',
    ],
]);
$check($closed['state'] === 'closed' && $closed['badge'] === 'Closed'
    && str_contains($closed['title'], 'Featured')
    && str_contains($closed['title'], 'Paid')
    && str_contains($closed['title'], 'Affiliate'),
    'Confirmed closure must take priority without losing other status information.');
$partner = llama_admin_place_image_status([
    'status' => 'featured', 'camping_fee_status' => 'paid',
    'map_partner' => [
        'id' => 23, 'relationship' => 'affiliate',
        'use_branded_cards' => true, 'primary_color' => '#A0B1C2',
    ],
]);
$check($partner['state'] === 'partner' && $partner['badge'] === 'Affiliate'
    && $partner['partner_id'] === 23 && $partner['partner_color'] === '#A0B1C2',
    'Approved partner card styling must take precedence over Featured and Paid.');
$featured = llama_admin_place_image_status([
    'status' => 'featured', 'camping_fee_status' => 'mixed',
    'map_partner' => [
        'id' => 23, 'relationship' => 'affiliate',
        'use_branded_cards' => false, 'primary_color' => '#A0B1C2',
    ],
]);
$check($featured['state'] === 'featured' && $featured['partner_id'] === 0
    && $featured['partner_color'] === '',
    'Partner records without branded cards should not change the map border priority.');
$paid = llama_admin_place_image_status([
    'status' => 'active', 'camping_fee_status' => 'mixed',
]);
$check($paid['state'] === 'paid' && str_contains($paid['title'], 'Mixed free/paid'),
    'Mixed pricing gets the green border just like the map, not an all-free claim.');
$invalidColor = llama_admin_place_image_status([
    'status' => 'active',
    'map_partner' => [
        'id' => 11, 'use_branded_cards' => true,
        'primary_color' => '#aaa; background:red',
    ],
]);
$check($invalidColor['state'] === 'partner' && $invalidColor['partner_color'] === '',
    'Unsafe partner colors must never reach rendered CSS.');

$root = dirname(__DIR__, 2);
$admin = file_get_contents($root . '/admin/places.php');
$shared = file_get_contents($root . '/admin/partials/place-inventory.php');
$css = file_get_contents($root . '/css/admin/pages/places.css');
$mapCss = file_get_contents($root . '/css/map.css');
$script = file_get_contents($root . '/js/admin/place-image-borders.js');
foreach ([
    'llama_camping_fee_enrich_map_places',
    'llama_map_partner_branding',
    'llama_closure_enrich_map_places',
] as $publicSource) {
    $check(str_contains($admin, $publicSource),
        'Admin inventory must use the canonical public map data source: ' . $publicSource);
}
$check(str_contains($shared, "is_array(\$row['image_status'] ?? null)")
    && str_contains($shared, 'admin-place-image-badge'),
    'Shared inventory must apply border styling only when the Place row provides an image status.');
$check(str_contains($admin, 'image_status')
    && !str_contains($root . '/admin/resources.php', 'image_status'),
    'Only Place rows must be enriched with Place-specific borders.');
foreach ([
    'is-closed' => '#c9363e',
    'is-featured' => '#226CD4',
    'is-paid' => '#237A4B',
] as $state => $color) {
    $check(str_contains($css, '.admin-place-list-image.' . $state)
        && stripos($css, $color) !== false
        && stripos($mapCss, $color) !== false,
        'Admin image state must use the exact public map border color: ' . $state);
}
$check(str_contains($css, '#FBD318') && str_contains($script, 'data-partner-color'),
    'Admin must use the map partner yellow fallback and vetted partner primary color.');

echo "PASS: Admin thumbnail borders match map closure, partner, Featured and Paid precedence.\n";

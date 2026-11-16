<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once dirname(__DIR__) . '/app/camping-fee-map.php';
require_once dirname(__DIR__) . '/app/map-partner-branding.php';
require_once dirname(__DIR__) . '/app/closure-map-status.php';
require_once dirname(__DIR__) . '/app/admin-place-image-status.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$search =
    trim((string) ($_GET['q'] ?? ''));

$status =
    trim((string) ($_GET['status'] ?? ''));

$state =
    trim((string) ($_GET['state'] ?? ''));

$places = admin_places_list(
    $db,
    $search,
    $status,
    $state
);

// Reuse the exact same confirmed pricing, partner and Place closure data
// that powers the main map. "Active" does not automatically mean "Open".
$places = llama_camping_fee_enrich_map_places($db, $places);
$places = llama_map_partner_branding($db, $places);
$places = llama_closure_enrich_map_places($db, $places);

$states =
    admin_places_states($db);

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Places';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'places';

$inventoryName = 'Place';
$inventoryUrl = '/places.php';
$submissionsUrl = '/submissions.php';
$searchPlaceholder = 'Name, road, town, land manager, or ID';
$filterName = 'status';
$filterLabel = 'Status';
$filterPlaceholder = 'All statuses';
$filterValue = $status;
$filterOptions = [
    'featured' => 'Featured', 'active' => 'Active', 'draft' => 'Draft',
    'unlisted' => 'Unlisted', 'archived' => 'Archived', 'removed' => 'Removed',
];
$inventoryRows = array_map(static fn (array $place): array => [
    'id' => $place['id'],
    'name' => (string) $place['name'],
    'status' => ucfirst((string) $place['status']),
    'status_tone' => match ($place['status']) {
        'draft', 'unlisted' => 'yellow',
        'active' => 'green',
        'featured' => 'blue',
        'removed' => 'red',
        'archived' => 'gray',
        default => 'neutral',
    },
    'image_url' => empty($place['featured_image']) ? '' : llama_photo_public_url((string) $place['featured_image']),
    'image_status' => llama_admin_place_image_status($place),
    'location' => [$place['city'] ?? null, $place['county'] ?? null, $place['state'] ?? null, $place['land_manager'] ?? null],
    'facts' => [
        ['icon' => 'photo', 'text' => number_format((int) $place['image_count'])],
        ['icon' => 'binoculars', 'text' => number_format((int) $place['verification_count'])],
        [
            'icon' => 'alert-triangle',
            'text' => number_format((int) $place['open_report_count']),
            'alert' => (int) $place['open_report_count'] > 0,
        ],
        ['text' => 'Last checked: ' . (!empty($place['last_verified_at'])
            ? llama_format_viewer_datetime((string) $place['last_verified_at'])
            : 'Never')],
    ],
    'manage_url' => '/place.php?id=' . (int) $place['id'],
    'view_url' => in_array($place['status'], ['active', 'featured'], true)
        ? 'https://llamascout.com/place.php?slug=' . rawurlencode((string) $place['slug'])
        : '',
], $places);

require __DIR__ . '/_header.php';
?>
<script defer src="https://llamascout.com/js/admin/place-image-borders.js?v=<?= (int) filemtime(dirname(__DIR__) . '/js/admin/place-image-borders.js') ?>"></script>
<?php
require __DIR__ . '/partials/place-inventory.php';
require __DIR__ . '/_footer.php';

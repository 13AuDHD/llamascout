<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db =
    db();

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],
    'updates' =>
        $stats['updates'],
    'reports' =>
        $stats['reports'],
    'orders' =>
        $stats['orders'],
    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Reference Data';

$adminPageEyebrow =
    'Configuration';

$adminActiveNav =
    'reference-data';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>External catalogs</p>
        <h2>Reference Data</h2>
    </div>
</header>

<div class="admin-user-action-box">

<p>
    Manage large external datasets that Llama Scout uses as reference
    information without turning every source record into a Llama Scout Place.
</p>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Public lands</p>
        <h2>PAD-US</h2>
    </div>
</header>

<div class="admin-user-action-box">

<p>
    Protected Areas Database of the United States reference catalog.
</p>

<div class="admin-user-form-actions">

<a
    class="admin-button"
    href="/pad-us-sync.php"
>
    PAD-US Sync
</a>

<a
    class="admin-button is-secondary"
    href="/pad-us-units.php?state=CO"
>
    Browse Reference Units
</a>

<a
    class="admin-button is-secondary"
    href="/pad-us-classifications.php?state=CO"
>
    Review Classifications
</a>

</div>

</div>

</section>


<?php require __DIR__ . '/_footer.php'; ?>

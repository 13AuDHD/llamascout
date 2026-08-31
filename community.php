<?php

declare(strict_types=1);

http_response_code(404);
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/app/bootstrap.php';
$pageTitle = 'Not Found | Llama Scout';
require __DIR__ . '/partials/header.php';
?>
<section class="account-empty-state">
    <i class="fa-solid fa-map" aria-hidden="true"></i>
    <h1>There is no member directory here.</h1>
    <p>Llama Scout profiles are reached directly from a member's username or activity around the site.</p>
    <a class="place-save-button" href="/map.php">Explore the map</a>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

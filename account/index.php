<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$savedPlaces = saved_places_for_user($userId);

$config = llama_config();
$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$pageTitle = 'Your Account | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';
?>

<section class="account-page">

    <header class="account-page-header">
        <div>
            <p class="account-eyebrow">Your account</p>
            <h1><?= htmlspecialchars(
                (string) ($user['display_name'] ?? $user['username'] ?? $user['email']),
                ENT_QUOTES,
                'UTF-8'
            ) ?></h1>
        </div>

        <a class="account-logout-link" href="/logout.php">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            Log out
        </a>
    </header>

    <section class="account-section" aria-labelledby="saved-places-heading">
        <div class="account-section-heading">
            <div>
                <p class="account-eyebrow">Saved for later</p>
                <h2 id="saved-places-heading">Saved places</h2>
            </div>

            <span class="account-section-count">
                <?= count($savedPlaces) ?>
            </span>
        </div>

        <?php if (!$savedPlaces): ?>
            <div class="account-empty-state">
                <i class="fa-regular fa-bookmark" aria-hidden="true"></i>
                <h3>No saved places yet</h3>
                <p>Save places from the map or a place page and they will appear here.</p>
                <a class="place-save-button" href="<?= htmlspecialchars($siteUrl . '/map.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-map" aria-hidden="true"></i>
                    Explore the map
                </a>
            </div>
        <?php else: ?>
            <div class="saved-places-grid">
                <?php foreach ($savedPlaces as $saved): ?>
                    <?php
                    $slug = trim((string) ($saved['slug'] ?? ''));
                    $name = (string) ($saved['name'] ?? 'Saved place');
                    $isAvailable = $saved['place_id'] !== null
                        && $slug !== ''
                        && (string) ($saved['status'] ?? '') === 'published';

                    $location = array_values(array_filter([
                        $saved['city'] ?? null,
                        $saved['state'] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== ''));
                    ?>

                    <article class="saved-place-card">
                        <?php if (!empty($saved['featured_image'])): ?>
                            <div class="saved-place-card-image">
                                <img
                                    src="<?= htmlspecialchars((string) $saved['featured_image'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt=""
                                    loading="lazy"
                                >
                            </div>
                        <?php endif; ?>

                        <div class="saved-place-card-body">
                            <div class="saved-place-card-title-row">
                                <h3><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h3>
                                <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                            </div>

                            <?php if ($location): ?>
                                <p class="saved-place-card-location">
                                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                    <?= htmlspecialchars(implode(', ', $location), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($saved['elevation_feet'] !== null): ?>
                                <p class="saved-place-card-meta">
                                    <?= number_format((int) $saved['elevation_feet']) ?> ft elevation
                                </p>
                            <?php endif; ?>

                            <?php if ($isAvailable): ?>
                                <a
                                    class="saved-place-card-link"
                                    href="<?= htmlspecialchars($siteUrl . '/place.php?slug=' . rawurlencode($slug), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    View place
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <p class="saved-place-card-unavailable">
                                    This saved place is not currently available.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

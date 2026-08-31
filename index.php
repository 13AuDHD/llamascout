<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Llama Scout | Know the Place Before You Go';
$pageDescription = 'Explore outdoor places with practical information about access, weather, amenities, sensory conditions, connectivity, and what to expect before you arrive.';
$canonicalUrl = 'https://llamascout.com/';

$db = db();

$featuredStmt = $db->query(
    "SELECT
        p.id,
        p.slug,
        p.name,
        p.type,
        p.city,
        p.county,
        p.state,
        p.elevation_feet,
        p.land_manager,
        p.land_type,
        p.public_location_label,
        (
            SELECT pi.src
            FROM place_images pi
            WHERE pi.place_id = p.id
            ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.id ASC
            LIMIT 1
        ) AS featured_image,
        (
            SELECT pi.alt_text
            FROM place_images pi
            WHERE pi.place_id = p.id
            ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.id ASC
            LIMIT 1
        ) AS featured_image_alt
     FROM places p
     WHERE p.status IN ('active','featured')
     ORDER BY
        CASE WHEN p.status = 'featured' THEN 0 ELSE 1 END,
        COALESCE(p.published_at, p.created_at) DESC,
        p.id DESC
     LIMIT 4"
);

$featuredPlaces = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

$guideFile = __DIR__ . '/data/field-guides.json';
$fieldGuides = [];

if (is_file($guideFile)) {
    $decoded = json_decode((string) file_get_contents($guideFile), true);

    if (is_array($decoded)) {
        $fieldGuides = array_slice($decoded, 0, 3);
    }
}

function home_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function home_image_url(?string $src): ?string
{
    $src = trim((string) $src);

    if ($src === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $src)) {
        return $src;
    }

    return '/' . ltrim($src, '/');
}

require __DIR__ . '/partials/header.php';
?>

<section class="public-home">

    <section class="public-home-hero">
        <div class="public-home-container public-home-hero-grid">

            <div class="public-home-hero-copy">
                <p class="public-home-eyebrow">Llama Scout</p>

                <h1>
                    Know the place
                    <span>before you go.</span>
                </h1>

                <p class="public-home-lede">
                    Find outdoor places with the details that change a trip:
                    where you are actually going, what the road is like,
                    what you may hear or encounter, what services are nearby,
                    and whether the place fits the way you travel.
                </p>

                <div class="public-home-actions">
                    <a class="public-home-button is-primary" href="/map.php">
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        Explore the Map
                    </a>

                    <a class="public-home-button" href="/membership">
                        Compare Membership
                    </a>
                </div>

                <ul class="public-home-trust-list" aria-label="Llama Scout highlights">
                    <li><i class="fa-solid fa-road" aria-hidden="true"></i> Road and vehicle access</li>
                    <li><i class="fa-solid fa-ear-listen" aria-hidden="true"></i> Sensory details</li>
                    <li><i class="fa-solid fa-signal" aria-hidden="true"></i> Connectivity</li>
                    <li><i class="fa-solid fa-cloud-sun" aria-hidden="true"></i> Place-aware weather</li>
                </ul>
            </div>

            <div class="public-home-hero-art">
                <img
                    src="/images/hero-art.jpg"
                    alt="A quiet mountain campsite beside a stream"
                >
                <div class="public-home-hero-note">
                    <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
                    <div>
                        <strong>Less guessing.</strong>
                        <span>More useful context before the pavement ends.</span>
                    </div>
                </div>
            </div>

        </div>
    </section>


    <section class="public-home-map-section">
        <div class="public-home-container">

            <div class="public-home-section-heading">
                <div>
                    <p class="public-home-eyebrow">Explore</p>
                    <h2>Start with the map.</h2>
                    <p>
                        Public markers show the general area. Paid members can
                        unlock exact locations and the complete field report.
                    </p>
                </div>

                <a href="/map.php">
                    Open full map
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="public-home-map-card">
                <div id="public-home-map" aria-label="Llama Scout place map"></div>

                <div class="public-home-map-overlay">
                    <strong id="public-home-map-status">Loading places…</strong>
                    <span>Approximate public locations</span>
                </div>
            </div>

        </div>
    </section>


    <section class="public-home-section">
        <div class="public-home-container">

            <div class="public-home-section-heading">
                <div>
                    <p class="public-home-eyebrow">Freshly Scouted</p>
                    <h2>Places worth a closer look.</h2>
                </div>

                <a href="/map.php">
                    See all places
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <?php if ($featuredPlaces): ?>
                <div class="public-home-place-grid">
                    <?php foreach ($featuredPlaces as $place): ?>
                        <?php
                        $image = home_image_url($place['featured_image'] ?? null);
                        $location = trim((string) ($place['public_location_label'] ?? ''));

                        if ($location === '') {
                            $parts = array_filter([
                                $place['city'] ?? null,
                                $place['state'] ?? null,
                            ]);
                            $location = implode(', ', $parts);
                        }
                        ?>
                        <article class="public-home-place-card">
                            <a class="public-home-place-image" href="/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>">
                                <?php if ($image): ?>
                                    <img
                                        src="<?= home_e($image) ?>"
                                        alt="<?= home_e($place['featured_image_alt'] ?: $place['name']) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <span class="public-home-place-placeholder">
                                        <i class="fa-solid fa-mountain-sun" aria-hidden="true"></i>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <div class="public-home-place-body">
                                <p class="public-home-place-type">
                                    <?= home_e(ucwords(str_replace(['-', '_'], ' ', (string) $place['type']))) ?>
                                </p>

                                <h3>
                                    <a href="/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>">
                                        <?= home_e($place['name']) ?>
                                    </a>
                                </h3>

                                <?php if ($location !== ''): ?>
                                    <p>
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                        <?= home_e($location) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="public-home-place-meta">
                                    <?php if (!empty($place['elevation_feet'])): ?>
                                        <span><?= number_format((int) $place['elevation_feet']) ?> ft</span>
                                    <?php endif; ?>

                                    <?php if (!empty($place['land_manager'])): ?>
                                        <span><?= home_e($place['land_manager']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="public-home-empty">
                    Published places will appear here as they are added.
                </div>
            <?php endif; ?>

        </div>
    </section>


    <section class="public-home-section public-home-guides-section">
        <div class="public-home-container">

            <div class="public-home-section-heading">
                <div>
                    <p class="public-home-eyebrow">Field Guides</p>
                    <h2>Useful information before you lose cell service.</h2>
                </div>

                <a href="/field-guides">
                    Read the Field Guides
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="public-home-guide-grid">
                <?php foreach ($fieldGuides as $guide): ?>
                    <article class="public-home-guide-card">
                        <?php if (!empty($guide['image'])): ?>
                            <a
                                class="public-home-guide-image"
                                href="/field-guides/<?= rawurlencode((string) $guide['slug']) ?>"
                            >
                                <img
                                    src="/<?= home_e(ltrim((string) $guide['image'], '/')) ?>"
                                    alt="<?= home_e($guide['imageAlt'] ?? $guide['title']) ?>"
                                    loading="lazy"
                                >
                            </a>
                        <?php endif; ?>

                        <div class="public-home-guide-body">
                            <p><?= home_e($guide['category'] ?? 'Field Guide') ?></p>
                            <h3>
                                <a href="/field-guides/<?= rawurlencode((string) $guide['slug']) ?>">
                                    <?= home_e($guide['title'] ?? '') ?>
                                </a>
                            </h3>
                            <span><?= home_e($guide['readTime'] ?? '') ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>


    <section class="public-home-membership">
        <div class="public-home-container public-home-membership-grid">

            <div>
                <p class="public-home-eyebrow">Membership</p>
                <h2>The public view gives you context. The full report tells you what the place is actually like.</h2>
                <p>
                    Llama Scout keeps useful planning information public.
                    Paid membership unlocks exact coordinates, complete photos,
                    road and access details, connectivity, sensory conditions,
                    Scout notes, and the full place forecast.
                </p>
            </div>

            <div class="public-home-membership-actions">
                <a class="public-home-button is-primary" href="/membership">
                    Compare Access
                </a>
                <a class="public-home-button" href="https://account.llamascout.com/register.php">
                    Create Free Account
                </a>
            </div>

        </div>
    </section>

</section>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/home.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

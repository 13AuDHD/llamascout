<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

$hasMemberAccess = user_has_member_access();

$place = null;

if ($slug !== '') {
    $place = $hasMemberAccess
        ? place_member_by_slug($slug)
        : place_public_by_slug($slug);
}

if (!$place) {
    http_response_code(404);

    $pageTitle = 'Place Not Found | Llama Scout';

    require __DIR__ . '/partials/header.php';
    ?>

    <section class="place-not-found">
        <h1>Place not found</h1>

        <p>
            This place is unavailable or has not been published.
        </p>

        <p>
            <a href="/map.php">Return to the map</a>
        </p>
    </section>

    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}


$pageTitle = $place['name'] . ' | Llama Scout';

$locationParts = array_filter([
    $place['city'] ?? null,
    !empty($place['county'])
        ? $place['county'] . ' County'
        : null,
    $place['state'] ?? null,
]);

$amenityLabels = [
    'toilets' => ['fa-restroom', 'Toilets'],
    'potable_water' => ['fa-faucet-drip', 'Potable water'],
    'trash' => ['fa-trash-can', 'Trash'],
    'fire_ring' => ['fa-fire', 'Fire ring'],
    'picnic_table' => ['fa-table-picnic', 'Picnic table'],
    'bear_box' => ['fa-box', 'Bear box'],
    'showers' => ['fa-shower', 'Showers'],
    'electricity' => ['fa-bolt', 'Electricity'],
    'dump_station' => ['fa-truck-droplet', 'Dump station'],
    'food_storage_required' => ['fa-box-archive', 'Food storage required'],
];

require __DIR__ . '/partials/header.php';
?>

<article class="place-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="place-header">

        <p class="eyebrow">
            <?= htmlspecialchars(
                ucwords(
                    str_replace(
                        '-',
                        ' ',
                        (string) $place['type']
                    )
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <h1>
            <?= htmlspecialchars(
                $place['name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>


        <?php if ($locationParts): ?>

            <p class="place-location">

                <i
                    class="fa-solid fa-location-dot"
                    aria-hidden="true"
                ></i>

                <?= htmlspecialchars(
                    implode(', ', $locationParts),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

        <?php endif; ?>


        <?php if (!empty($place['land_manager'])): ?>

            <p class="place-land">

                <?= htmlspecialchars(
                    $place['land_manager'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                <?php if (!empty($place['land_type'])): ?>

                    · <?= htmlspecialchars(
                        $place['land_type'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php endif; ?>

            </p>

        <?php endif; ?>

    </header>



    <!-- =====================================================
         PHOTOS
         FREE: FEATURED IMAGE ONLY
         MEMBER: COMPLETE GALLERY
         ===================================================== -->

    <?php if ($hasMemberAccess && !empty($place['images'])): ?>

        <section
            class="place-gallery"
            aria-label="Place photos"
        >

            <?php foreach ($place['images'] as $image): ?>

                <img
                    src="/<?= htmlspecialchars(
                        ltrim($image['src'], '/'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="<?= htmlspecialchars(
                        $image['alt_text']
                            ?: $place['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    loading="lazy"
                >

            <?php endforeach; ?>

        </section>


    <?php elseif (!empty($place['featured_image'])): ?>

        <section
            class="place-hero-image"
            aria-label="Place photo"
        >

            <img
                src="/<?= htmlspecialchars(
                    ltrim(
                        $place['featured_image']['src'],
                        '/'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                alt="<?= htmlspecialchars(
                    $place['featured_image']['alt_text']
                        ?: $place['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

        </section>

    <?php endif; ?>



    <!-- =====================================================
         BASIC PUBLIC INFORMATION
         ===================================================== -->

    <section
        class="place-facts"
        aria-label="Place details"
    >

        <?php if (!empty($place['elevation_feet'])): ?>

            <div class="place-fact">

                <i
                    class="fa-solid fa-mountain"
                    aria-hidden="true"
                ></i>

                <span>Elevation</span>

                <strong>
                    <?= number_format(
                        (int) $place['elevation_feet']
                    ) ?> ft
                </strong>

            </div>

        <?php endif; ?>


        <?php if ($hasMemberAccess && !empty($place['road'])): ?>

            <div class="place-fact">

                <i
                    class="fa-solid fa-road"
                    aria-hidden="true"
                ></i>

                <span>Road</span>

                <strong>
                    <?= htmlspecialchars(
                        $place['road'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

        <?php endif; ?>


        <?php
        if (
            $hasMemberAccess
            && $place['latitude'] !== null
            && $place['longitude'] !== null
        ):
        ?>

            <div class="place-fact">

                <i
                    class="fa-solid fa-location-crosshairs"
                    aria-hidden="true"
                ></i>

                <span>GPS coordinates</span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $place['latitude'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>,
                    <?= htmlspecialchars(
                        (string) $place['longitude'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

        <?php endif; ?>

    </section>



    <!-- =====================================================
         AMENITIES
         AVAILABLE TO EVERYONE
         ===================================================== -->

    <?php if (!empty($place['amenities'])): ?>

        <section class="place-section">

            <h2>Amenities</h2>

            <div class="amenity-grid">

                <?php
                foreach (
                    $amenityLabels
                    as $key => [$icon, $label]
                ):
                ?>

                    <?php
                    $value =
                        $place['amenities'][$key]
                        ?? null;

                    if ($value === null) {
                        continue;
                    }
                    ?>

                    <div
                        class="amenity-item <?= $value
                            ? 'is-available'
                            : 'is-unavailable'
                        ?>"
                    >

                        <i
                            class="fa-solid <?= htmlspecialchars(
                                $icon,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            aria-hidden="true"
                        ></i>

                        <span>
                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <strong>
                            <?= $value ? 'Yes' : 'No' ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>



    <!-- =====================================================
         MEMBER-ONLY PLACE INFORMATION
         ===================================================== -->

    <?php if ($hasMemberAccess): ?>


        <?php if (!empty($place['description'])): ?>

            <section class="place-section">

                <h2>About this place</h2>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $place['description'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>
                </p>

            </section>

        <?php endif; ?>



        <?php if (!empty($place['access_summary'])): ?>

            <section class="place-section">

                <h2>
                    <i
                        class="fa-solid fa-road"
                        aria-hidden="true"
                    ></i>

                    Access
                </h2>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $place['access_summary'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>
                </p>

            </section>

        <?php endif; ?>



        <?php if (!empty($place['sensory_summary'])): ?>

            <section class="place-section">

                <h2>
                    <i
                        class="fa-solid fa-ear-listen"
                        aria-hidden="true"
                    ></i>

                    Sensory notes
                </h2>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $place['sensory_summary'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>
                </p>

            </section>

        <?php endif; ?>


    <?php else: ?>

        <section class="member-preview">

            <i
                class="fa-solid fa-lock"
                aria-hidden="true"
            ></i>

            <div>

                <h2>Scout Report</h2>

                <p>
                    Members can see the exact location, full photo
                    gallery, access information, sensory details,
                    connectivity, and detailed site information.
                </p>

            </div>

        </section>

    <?php endif; ?>


</article>

<?php require __DIR__ . '/partials/footer.php'; ?>

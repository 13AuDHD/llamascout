<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/resources.php';

$slug =
    trim(
        (string) (
            $_GET['slug']
            ?? ''
        )
    );

$resource =
    $slug !== ''
        ? llama_resource_by_slug(
            db(),
            $slug
        )
        : [];

if (!$resource) {
    http_response_code(404);

    $pageTitle =
        'Resource not found | Llama Scout';

    $pageDescription =
        'The requested Llama Scout Resource could not be found.';

    require __DIR__ . '/partials/header.php';
    ?>
    <main class="resource-not-found">
        <div class="place-detail-container">
            <p class="place-detail-eyebrow">Resource</p>
            <h1>Resource not found</h1>
            <p>
                This Resource may have moved, closed, or changed its URL.
            </p>
            <a class="resource-link-button" href="/map.php">
                Explore the map
            </a>
        </div>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$resourceId =
    (int) (
        $resource['id']
        ?? 0
    );

$images =
    llama_resource_images(
        db(),
        $resourceId
    );

$heroImage =
    $images[0]
    ?? [];

$services =
    array_values(
        array_filter(
            (array) (
                $resource['services']
                ?? []
            ),
            static fn (mixed $service): bool =>
                is_array($service)
        )
    );

$locationParts =
    array_values(
        array_filter([
            trim(
                (string) (
                    $resource['city']
                    ?? ''
                )
            ),
            trim(
                (string) (
                    $resource['state_region']
                    ?? ''
                )
            ),
        ])
    );

$addressParts =
    array_values(
        array_filter([
            trim(
                (string) (
                    $resource['street_address']
                    ?? ''
                )
            ),
            implode(
                ', ',
                $locationParts
            ),
            trim(
                (string) (
                    $resource['postal_code']
                    ?? ''
                )
            ),
        ])
    );

$typeLabel =
    (string) (
        $resource['resource_type_label']
        ?? llama_resource_type_label(
            $resource['resource_type']
            ?? ''
        )
    );

$statusKey =
    (string) (
        $resource['status']
        ?? 'unknown'
    );

$statusLabel =
    llama_resource_statuses()[$statusKey]
    ?? ucfirst(
        str_replace(
            '_',
            ' ',
            $statusKey
        )
    );

$canonicalUrl =
    'https://llamascout.com/resource.php?slug='
    . rawurlencode(
        (string) (
            $resource['slug']
            ?? $slug
        )
    );

$pageTitle =
    (string) $resource['name']
    . ' | Llama Scout Resource';

$pageDescription =
    trim(
        (string) (
            $resource['notes']
            ?? ''
        )
    );

if ($pageDescription === '') {
    $serviceLabels =
        array_values(
            array_filter(
                array_map(
                    static fn (array $service): string =>
                        trim(
                            (string) (
                                $service['service_type_label']
                                ?? ''
                            )
                        ),
                    $services
                )
            )
        );

    $pageDescription =
        (string) $resource['name']
        . ' on Llama Scout'
        . (
            $serviceLabels
                ? ': '
                    . implode(
                        ', ',
                        $serviceLabels
                    )
                : '.'
        );
}

if (!empty($heroImage['src'])) {
    $pageSocialImage =
        llama_resource_image_url(
            $heroImage['src']
        );

    if (
        $pageSocialImage !== ''
        && !preg_match(
            '/^https?:\/\//i',
            $pageSocialImage
        )
    ) {
        $pageSocialImage =
            'https://llamascout.com'
            . $pageSocialImage;
    }
}

$latitude =
    isset($resource['latitude'])
        ? (float) $resource['latitude']
        : null;

$longitude =
    isset($resource['longitude'])
        ? (float) $resource['longitude']
        : null;

$coordinates = $latitude !== null && $longitude !== null
    ? $latitude . ',' . $longitude
    : '';
$googleDirectionsUrl = $coordinates !== ''
    ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($coordinates)
    : '';
$appleDirectionsUrl = $coordinates !== ''
    ? 'https://maps.apple.com/directions?destination=' . rawurlencode($coordinates) . '&mode=driving'
    : '';
$onxMapUrl = 'https://webmap.onxmaps.com/offroad/';
$phoneRaw = trim((string) ($resource['phone'] ?? ''));
$phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';
$phoneNational = strlen($phoneDigits) === 11 && $phoneDigits[0] === '1'
    ? substr($phoneDigits, 1)
    : $phoneDigits;
$phoneDisplay = strlen($phoneNational) === 10
    ? '(' . substr($phoneNational, 0, 3) . ')' . substr($phoneNational, 3, 3) . '-' . substr($phoneNational, 6)
    : $phoneRaw;
$phoneHref = preg_replace('/[^0-9+]/', '', $phoneRaw) ?? '';

$sourceDateRaw =
    trim(
        (string) (
            $resource['verified_at']
            ?? $resource['source_updated_at']
            ?? ''
        )
    );

$sourceDateLabel = '';

if ($sourceDateRaw !== '') {
    try {
        $sourceDateLabel =
            (new DateTimeImmutable($sourceDateRaw))
                ->format('M j, Y');
    } catch (Throwable) {
        $sourceDateLabel = '';
    }
}

require __DIR__ . '/partials/header.php';
?>

<article class="resource-page">

<header class="place-detail-hero<?= $heroImage ? ' has-image' : ' no-image' ?>">

    <?php if ($heroImage): ?>
        <img
            class="place-detail-hero-image"
            src="<?= htmlspecialchars(
                llama_resource_image_url(
                    $heroImage['src']
                    ?? ''
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            alt="<?= htmlspecialchars(
                (string) (
                    $heroImage['alt_text']
                    ?? $resource['name']
                    ?? 'Resource'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
    <?php endif; ?>

    <div class="place-detail-hero-shade" aria-hidden="true"></div>

    <div class="place-detail-hero-inner">

        <a class="place-detail-back" href="/map.php">
            <i aria-hidden="true"><?= llama_icon('arrow-left') ?></i>
            Explore Map
        </a>

        <div class="place-detail-hero-content">

            <div class="place-detail-title-block">

                <p class="place-detail-eyebrow">
                    Resource · <?= htmlspecialchars(
                        $typeLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <h1><?= htmlspecialchars(
                    (string) $resource['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></h1>

                <?php if ($locationParts): ?>
                    <p class="place-detail-location">
                        <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                        <?= htmlspecialchars(
                            implode(', ', $locationParts),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>
                <?php endif; ?>

                <div class="resource-hero-pills">

                    <span class="resource-status-pill is-<?= htmlspecialchars(
                        $statusKey,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        <?= htmlspecialchars(
                            $statusLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <?php foreach (
                        array_slice($services, 0, 5)
                        as $service
                    ): ?>
                        <span>
                            <?= htmlspecialchars(
                                (string) (
                                    $service['service_type_label']
                                    ?? ''
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    <?php endforeach; ?>

                </div>

            </div>

            <div class="place-detail-actions">
                <a class="place-detail-action-button" href="/resource-contribution.php?resource_id=<?= $resourceId ?>">
                    <i aria-hidden="true"><?= llama_icon('edit') ?></i>
                    Update Resource
                </a>

                <?php if ($coordinates !== ''): ?>
                    <button class="place-detail-action-button" type="button"
                            data-resource-directions-open
                            aria-haspopup="dialog" aria-controls="resource-directions-dialog">
                        <i aria-hidden="true"><?= llama_icon('current-location') ?></i>
                        Directions
                    </button>
                <?php endif; ?>

                <?php if ($phoneRaw !== ''): ?>
                    <a class="place-detail-action-button"
                       href="tel:<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($resource['website_url'])): ?>
                    <a
                        class="place-detail-action-button"
                        href="<?= htmlspecialchars(
                            (string) $resource['website_url'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Website
                    </a>
                <?php endif; ?>

            </div>

        </div>
    </div>
</header>

<div class="place-detail-container resource-detail-body">

<section class="resource-detail-section">
    <div class="resource-section-heading">
        <p class="place-detail-eyebrow">Services</p>
        <h2>What you can get here</h2>
    </div>

    <div class="resource-service-grid">

    <?php foreach ($services as $service): ?>
        <?php
        $serviceStatusKey =
            (string) (
                $service['status']
                ?? 'unknown'
            );

        $serviceStatus =
            llama_resource_statuses()[
                $serviceStatusKey
            ]
            ?? ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $serviceStatusKey
                )
            );

        $feeKey =
            (string) (
                $service['fee_status']
                ?? 'unknown'
            );

        $feeLabel =
            llama_resource_fee_statuses()[
                $feeKey
            ]
            ?? ucfirst($feeKey);
        ?>

        <article class="resource-service-card">

            <header>
                <div>
                    <p>Service</p>
                    <h3><?= htmlspecialchars(
                        (string) (
                            $service['service_type_label']
                            ?? 'Service'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></h3>
                </div>

                <span class="resource-service-status is-<?= htmlspecialchars(
                    $serviceStatusKey,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">
                    <?= htmlspecialchars(
                        $serviceStatus,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </header>

            <dl class="resource-service-facts">

                <div>
                    <dt>Cost</dt>
                    <dd>
                        <?= htmlspecialchars(
                            (string) (
                                $service['fee_description']
                                ?: $feeLabel
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </dd>
                </div>

                <?php if (!empty($service['access_notes'])): ?>
                <div>
                    <dt>Access</dt>
                    <dd><?= nl2br(
                        htmlspecialchars(
                            (string) $service['access_notes'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?></dd>
                </div>
                <?php endif; ?>

                <?php if (!empty($service['notes'])): ?>
                <div>
                    <dt>Notes</dt>
                    <dd><?= nl2br(
                        htmlspecialchars(
                            (string) $service['notes'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?></dd>
                </div>
                <?php endif; ?>

            </dl>

        </article>

    <?php endforeach; ?>

    </div>
</section>

<section class="resource-detail-section resource-info-grid">

    <article class="resource-info-card">
        <p class="place-detail-eyebrow">Location</p>
        <h2>Contact &amp; location</h2>

        <dl class="resource-info-list">

            <?php if ($addressParts): ?>
            <div>
                <dt>Address</dt>
                <dd><?= htmlspecialchars(
                    implode(', ', $addressParts),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (!empty($resource['hours_text'])): ?>
            <div>
                <dt>Hours</dt>
                <dd><?= htmlspecialchars(
                    (string) $resource['hours_text'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></dd>
            </div>
            <?php endif; ?>

            <?php if ($phoneRaw !== ''): ?>
            <div>
                <dt>Phone</dt>
                <dd><?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>

            <?php if ($latitude !== null && $longitude !== null): ?>
            <div>
                <dt>GPS</dt>
                <dd>
                    <?= number_format($latitude, 6, '.', '') ?>,
                    <?= number_format($longitude, 6, '.', '') ?>
                </dd>
            </div>
            <?php endif; ?>

        </dl>
    </article>

    <article class="resource-info-card">
        <p class="place-detail-eyebrow">Details</p>
        <h2>Resource notes</h2>

        <?php if (!empty($resource['notes'])): ?>
            <p class="resource-notes">
                <?= nl2br(
                    htmlspecialchars(
                        (string) $resource['notes'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>
            </p>
        <?php else: ?>
            <p class="resource-notes is-empty">
                No general Resource notes have been added yet.
            </p>
        <?php endif; ?>

        <?php if (
            !empty($resource['source_provider'])
            || $sourceDateLabel !== ''
        ): ?>
            <div class="resource-source">
                <?php if (!empty($resource['source_provider'])): ?>
                    <span>
                        Source:
                        <?= htmlspecialchars(
                            (string) $resource['source_provider'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                <?php endif; ?>

                <?php if ($sourceDateLabel !== ''): ?>
                    <span>
                        Verified / updated
                        <?= htmlspecialchars(
                            $sourceDateLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>

</section>

<?php if ($images): ?>
<section class="place-photo-gallery-section resource-photo-section">

    <div class="place-photo-gallery-heading">
        <div>
            <p class="place-detail-eyebrow">Photos</p>
            <h2>Resource gallery</h2>
        </div>

        <span>
            <?= count($images) ?>
            <?= count($images) === 1 ? 'photo' : 'photos' ?>
        </span>
    </div>

    <div
        class="place-photo-gallery"
        data-place-gallery
    >
        <?php foreach ($images as $index => $image): ?>
            <button
                class="place-photo-thumb<?= !empty($image['is_featured']) ? ' is-featured' : '' ?>"
                type="button"
                data-gallery-open="<?= (int) $index ?>"
                aria-label="Open Resource photo <?= (int) $index + 1 ?>"
            >
                <img
                    src="<?= htmlspecialchars(
                        llama_resource_image_url(
                            $image['src']
                            ?? ''
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="<?= htmlspecialchars(
                        (string) (
                            $image['alt_text']
                            ?? $resource['name']
                            ?? 'Resource'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    loading="lazy"
                >

                <?php if (!empty($image['is_featured'])): ?>
                    <span class="place-photo-featured-label">
                        Hero
                    </span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

</section>

<dialog
    class="place-gallery-lightbox"
    id="place-gallery-lightbox"
    aria-label="Resource photo viewer"
>
    <div class="place-gallery-lightbox-inner">
        <div class="place-gallery-lightbox-top">
            <span id="place-gallery-counter"></span>
            <button
                type="button"
                class="place-gallery-close"
                id="place-gallery-close"
                aria-label="Close photo viewer"
            >
                ×
            </button>
        </div>

        <div class="place-gallery-stage">
            <button
                type="button"
                class="place-gallery-arrow is-previous"
                id="place-gallery-previous"
                aria-label="Previous photo"
            >
                ‹
            </button>

            <img
                id="place-gallery-large-image"
                src=""
                alt=""
            >

            <button
                type="button"
                class="place-gallery-arrow is-next"
                id="place-gallery-next"
                aria-label="Next photo"
            >
                ›
            </button>
        </div>

        <p
            class="place-gallery-caption"
            id="place-gallery-caption"
        ></p>

        <div
            class="place-gallery-lightbox-thumbs"
            id="place-gallery-lightbox-thumbs"
        ></div>
    </div>
</dialog>
<?php endif; ?>

</div>

</article>

<?php if ($coordinates !== ''): ?>
<dialog class="resource-directions-dialog" id="resource-directions-dialog"
        aria-labelledby="resource-directions-title">
    <div class="resource-directions-header">
        <h2 id="resource-directions-title">Open directions in</h2>
        <button type="button" class="resource-directions-close"
                data-resource-directions-close aria-label="Close directions menu">×</button>
    </div>
    <div class="resource-directions-options">
        <a href="<?= htmlspecialchars($googleDirectionsUrl, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
            <i aria-hidden="true"><?= llama_icon('brand-google-maps') ?></i>
            Google Maps
        </a>
        <a href="<?= htmlspecialchars($appleDirectionsUrl, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
            <i aria-hidden="true"><?= llama_icon('brand-apple') ?></i>
            Apple Maps
        </a>
        <a href="<?= htmlspecialchars($onxMapUrl, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
            <i aria-hidden="true"><?= llama_icon('brand-onx') ?></i>
            onX Offroad Maps
        </a>
    </div>
    <p class="resource-directions-note">To add this Resource to onX, copy its coordinates and paste them into onX Search, then save a waypoint.</p>
    <div class="resource-directions-coordinates">
        <output id="resource-directions-coordinates"><?= htmlspecialchars($coordinates, ENT_QUOTES, 'UTF-8') ?></output>
        <button type="button" data-resource-copy-coordinates>Copy coordinates</button>
    </div>
    <p class="resource-directions-copy-status" data-resource-copy-status role="status" aria-live="polite"></p>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('resource-directions-dialog');
    const open = document.querySelector('[data-resource-directions-open]');
    if (!dialog || !open) return;
    const close = dialog.querySelector('[data-resource-directions-close]');
    const copy = dialog.querySelector('[data-resource-copy-coordinates]');
    const status = dialog.querySelector('[data-resource-copy-status]');
    const value = dialog.querySelector('#resource-directions-coordinates');
    open.addEventListener('click', () => dialog.showModal());
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    copy.addEventListener('click', async () => {
        const coordinates = value.textContent.trim();
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(coordinates);
            status.textContent = 'Coordinates copied.';
        } catch (_) {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(value);
            selection.removeAllRanges();
            selection.addRange(range);
            status.textContent = 'Select and copy the highlighted coordinates.';
        }
    });
})();
</script>
<?php endif; ?>

<script src="/js/place-gallery.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

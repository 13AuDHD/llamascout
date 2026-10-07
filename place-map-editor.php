<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/place-map-features.php';
require_once __DIR__ . '/app/place-map.php';

require_verified_email();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

if (
    $userId < 1
    || !llama_contributor_can(
        db(),
        $userId,
        'field_report'
    )
) {
    http_response_code(403);
    exit('Scout access is required.');
}

$slug = trim(
    (string) (
        $_GET['slug']
        ?? ''
    )
);

$place =
    $slug !== ''
        ? place_member_by_slug(
            $slug
        )
        : null;

if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$latitude =
    isset($place['latitude'])
    && is_numeric($place['latitude'])
        ? (float) $place['latitude']
        : null;

$longitude =
    isset($place['longitude'])
    && is_numeric($place['longitude'])
        ? (float) $place['longitude']
        : null;

if (
    $latitude === null
    || $longitude === null
) {
    http_response_code(422);

    exit(
        'This Place needs exact coordinates before mapped areas can be edited.'
    );
}

$pageTitle =
    'Edit Mapped Areas | '
    . (string) $place['name']
    . ' | Llama Scout';

$pageStyles = [
    'site/pages/place-map-editor.css',
];

require __DIR__ . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<section
    class="mapped-area-editor"
    data-mapped-area-editor
    data-place-slug="<?= htmlspecialchars(
        (string) $place['slug'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-place-latitude="<?= htmlspecialchars(
        (string) $latitude,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-place-longitude="<?= htmlspecialchars(
        (string) $longitude,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    data-csrf-token="<?= htmlspecialchars(
        community_csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
    <header class="mapped-area-editor-header">
        <div>
            <p class="eyebrow">
                Mapped Areas
            </p>

            <h1>
                <?= htmlspecialchars(
                    (string) $place['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <p>
                Draw against satellite imagery or collect each boundary point
                from your device GPS. All geometry is stored as WGS 84
                coordinates.
            </p>
        </div>

        <a
            class="mapped-area-editor-back"
            href="/place.php?slug=<?= rawurlencode(
                (string) $place['slug']
            ) ?>"
        >
            Back to Place
        </a>
    </header>

    <div class="mapped-area-editor-layout">
        <aside class="mapped-area-editor-sidebar">

            <section class="mapped-area-editor-card">
                <div class="mapped-area-editor-card-heading">
                    <h2>Area</h2>

                    <button
                        type="button"
                        data-new-feature
                    >
                        New area
                    </button>
                </div>

                <label>
                    <span>Type</span>

                    <select data-feature-type>
                        <?php foreach (
                            llama_place_map_feature_types()
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= htmlspecialchars(
                                    $value,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Label</span>

                    <input
                        type="text"
                        maxlength="120"
                        placeholder="Optional, e.g. Main camping area"
                        data-feature-label
                    >
                </label>

                <div class="mapped-area-editor-draw-actions">
                    <button
                        type="button"
                        data-start-drawing
                    >
                        Draw area
                    </button>

                    <button
                        type="button"
                        data-finish-drawing
                        disabled
                    >
                        Finish
                    </button>

                    <button
                        type="button"
                        data-undo-point
                        disabled
                    >
                        Undo point
                    </button>
                </div>

                <p
                    class="mapped-area-editor-help"
                    data-editor-help
                >
                    Choose Draw area and tap around the outside edge, or use
                    device GPS below to collect the points while in the field.
                </p>

                <div class="mapped-area-editor-save-actions">
                    <button
                        type="button"
                        class="is-primary"
                        data-save-feature
                        disabled
                    >
                        Save area
                    </button>

                    <button
                        type="button"
                        class="is-danger"
                        data-delete-feature
                        disabled
                    >
                        Delete
                    </button>
                </div>

                <p
                    class="mapped-area-editor-status"
                    data-editor-status
                    role="status"
                    aria-live="polite"
                ></p>
            </section>


            <section class="mapped-area-editor-card">
                <div class="mapped-area-editor-card-heading">
                    <h2>Device GPS</h2>

                    <span
                        class="mapped-area-gps-state"
                        data-gps-state
                    >
                        Off
                    </span>
                </div>

                <div class="mapped-area-gps-actions">
                    <button
                        type="button"
                        data-start-gps
                    >
                        Start GPS
                    </button>

                    <button
                        type="button"
                        data-stop-gps
                        disabled
                    >
                        Stop GPS
                    </button>
                </div>

                <div
                    class="mapped-area-gps-reading"
                    data-gps-reading
                    hidden
                >
                    <div>
                        <span>Latitude</span>
                        <strong data-gps-latitude>--</strong>
                    </div>

                    <div>
                        <span>Longitude</span>
                        <strong data-gps-longitude>--</strong>
                    </div>

                    <div>
                        <span>Accuracy</span>
                        <strong data-gps-accuracy>--</strong>
                    </div>
                </div>

                <div class="mapped-area-gps-actions">
                    <button
                        type="button"
                        class="is-primary"
                        data-add-gps-point
                        disabled
                    >
                        Add GPS point
                    </button>

                    <button
                        type="button"
                        data-center-gps
                        disabled
                    >
                        Center on me
                    </button>
                </div>

                <p class="mapped-area-editor-help">
                    Keep GPS running while walking the boundary. At each corner
                    or meaningful bend, stop and choose Add GPS point. The
                    reported accuracy is saved with the point.
                </p>
            </section>


            <section class="mapped-area-editor-card">
                <h2>Existing areas</h2>

                <div
                    class="mapped-area-editor-list"
                    data-feature-list
                >
                    <p>Loading mapped areas...</p>
                </div>
            </section>


            <section class="mapped-area-editor-card">
                <h2>Map style</h2>

                <div
                    class="mapped-area-editor-map-styles"
                    role="group"
                    aria-label="Map style"
                >
                    <button
                        type="button"
                        data-map-style="street"
                    >
                        Street
                    </button>

                    <button
                        type="button"
                        data-map-style="satellite"
                        class="is-active"
                    >
                        Satellite
                    </button>

                    <button
                        type="button"
                        data-map-style="terrain"
                    >
                        Terrain
                    </button>
                </div>
            </section>

        </aside>

        <div class="mapped-area-editor-map-card">
            <div
                id="mapped-area-editor-map"
                class="mapped-area-editor-map"
                aria-label="Mapped area editor"
            ></div>

            <div
                class="mapped-area-editor-point-count"
                data-point-count
                hidden
            >
                0 points
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/place-map-editor.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

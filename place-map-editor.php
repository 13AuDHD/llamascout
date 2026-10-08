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


    <div class="mapped-area-editor-map-card">

        <div
            class="mapped-area-map-toolbar
                   mapped-area-map-toolbar--draw"
            aria-label="Drawing controls"
        >
            <button
                type="button"
                data-start-drawing
            >
                Draw
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
                Undo
            </button>
        </div>


        <div
            class="mapped-area-map-toolbar
                   mapped-area-map-toolbar--style"
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


    <div class="mapped-area-editor-content">

        <section class="mapped-area-editor-card mapped-area-editor-area-card">
            <div class="mapped-area-editor-card-heading">
                <h2>Area details</h2>

                <div class="mapped-area-editor-card-heading-actions">
                    <span
                        class="mapped-area-editor-unsaved"
                        data-unsaved-state
                        hidden
                    >
                        Unsaved
                    </span>

                    <button
                        type="button"
                        data-new-feature
                    >
                        New area
                    </button>
                </div>
            </div>

            <div class="mapped-area-editor-area-fields">
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
            </div>

            <div
                class="mapped-area-editor-stay-fields"
                data-area-details
                hidden
            >
                <label>
                    <span>Area use</span>

                    <select data-area-use>
                        <option value="">Not specified</option>
                    </select>
                </label>

                <label data-overnight-status-field hidden>
                    <span>Overnight vehicle stay</span>

                    <select data-overnight-status>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_overnight_status_options()
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
                    <span>Cost</span>

                    <select data-fee-status>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_fee_status_options()
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

                <p
                    class="mapped-area-editor-area-note"
                    data-area-details-note
                ></p>
            </div>

            <div
                class="mapped-area-editor-site-fields"
                data-site-details
                hidden
            >
                <label>
                    <span>Parent Camping area</span>

                    <select data-site-parent>
                        <option value="">Choose a Camping area</option>
                    </select>
                </label>

                <label>
                    <span>Site pricing class</span>

                    <select data-site-class>
                        <option value="">No pricing class</option>
                    </select>
                </label>

                <label>
                    <span>Site number / name</span>

                    <input
                        type="text"
                        maxlength="60"
                        placeholder="e.g. 12, A14, Pull-through 3"
                        data-site-code
                    >
                </label>

                <label>
                    <span>Site type</span>

                    <select data-site-type>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_camping_site_type_options()
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
                    <span>Parking style</span>

                    <select data-site-parking-style>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_camping_site_parking_style_options()
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
                    <span>Hookups</span>

                    <select data-site-hookups>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_camping_site_hookup_options()
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
                    <span>Accessible site</span>

                    <select data-site-accessible>
                        <option value="">Not specified</option>

                        <?php foreach (
                            llama_place_map_camping_site_accessible_options()
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

                <p class="mapped-area-editor-area-note">
                    Individual campsites belong to a Camping area. Put objective
                    site facts here. Sensory observations can be added later
                    through site reports.
                </p>
            </div>

            <div
                class="mapped-area-editor-summary"
                data-feature-summary
                hidden
            >
                <div>
                    <span>Points</span>
                    <strong data-summary-points>0</strong>
                </div>

                <div>
                    <span>Source</span>
                    <strong data-summary-source>Manual</strong>
                </div>

                <div>
                    <span>GPS accuracy</span>
                    <strong data-summary-accuracy>--</strong>
                </div>

                <div>
                    <span>Updated</span>
                    <strong data-summary-updated>--</strong>
                </div>
            </div>

            <p
                class="mapped-area-editor-help"
                data-editor-help
            >
                Choose Draw on the map and tap around the outside edge, or use
                device GPS below to collect points while in the field.
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


        <section
            class="mapped-area-editor-card mapped-area-editor-site-class-card"
            data-site-classes-section
            hidden
        >
            <div class="mapped-area-editor-card-heading">
                <div>
                    <h2>Site pricing classes</h2>

                    <p class="mapped-area-editor-card-subtitle">
                        Create reusable campground pricing groups such as
                        Standard Back-In or Large Pull-Through.
                    </p>
                </div>

                <button
                    type="button"
                    data-add-site-class
                >
                    Add class
                </button>
            </div>

            <div
                class="mapped-area-editor-site-class-list"
                data-site-class-list
            ></div>

            <p
                class="mapped-area-editor-help"
                data-site-class-empty
            >
                No site pricing classes have been entered for this Camping area yet.
            </p>

            <p class="mapped-area-editor-help">
                Individual campsites can inherit a pricing class, then override
                any rate that is genuinely different for that specific site.
            </p>
        </section>


        <section
            class="mapped-area-editor-card mapped-area-editor-rate-card"
            data-rate-section
            hidden
        >
            <div class="mapped-area-editor-card-heading">
                <div>
                    <h2>Rates</h2>
                    <p class="mapped-area-editor-card-subtitle">
                        General nightly pricing for this Camping area, or site-specific overrides for an individual campsite.
                    </p>
                </div>

                <button
                    type="button"
                    data-add-rate
                >
                    Add rate
                </button>
            </div>

            <div
                class="mapped-area-editor-rate-inheritance"
                data-rate-inheritance
                hidden
            ></div>

            <div
                class="mapped-area-editor-rate-list"
                data-rate-list
            ></div>

            <p
                class="mapped-area-editor-help"
                data-rate-empty
            >
                No rates have been entered for this area yet.
            </p>

            <p class="mapped-area-editor-help">
                Standard, weekday, weekend, holiday, and recurring seasonal
                nightly rates can be stored here. Seasonal dates use MM-DD so
                the same season can apply every year.
            </p>
        </section>

        <section class="mapped-area-editor-card">
            <div class="mapped-area-editor-card-heading">
                <h2>Edit shape</h2>
            </div>

            <div
                class="mapped-area-selected-point"
                data-selected-point
                hidden
            >
                <div>
                    <span>Selected point</span>
                    <strong data-selected-point-number>--</strong>
                </div>

                <div>
                    <span>Latitude</span>
                    <strong data-selected-point-latitude>--</strong>
                </div>

                <div>
                    <span>Longitude</span>
                    <strong data-selected-point-longitude>--</strong>
                </div>

                <div>
                    <span>Source</span>
                    <strong data-selected-point-source>--</strong>
                </div>
            </div>

            <div class="mapped-area-shape-actions">
                <button
                    type="button"
                    data-remove-point
                    disabled
                >
                    Remove point
                </button>

                <button
                    type="button"
                    data-use-gps-point
                    disabled
                >
                    Replace with GPS
                </button>

                <button
                    type="button"
                    data-move-area
                    disabled
                >
                    Move whole area
                </button>

                <button
                    type="button"
                    data-revert-feature
                    disabled
                >
                    Revert changes
                </button>
            </div>

            <p class="mapped-area-editor-help">
                Tap a corner to select it. Drag corners to adjust them.
                Tap a small + between corners to insert another point.
                Move whole area repositions the polygon without changing
                its shape.
            </p>
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


        <section class="mapped-area-editor-card mapped-area-editor-existing">
            <div class="mapped-area-editor-card-heading">
                <h2>Existing areas</h2>

                <button
                    type="button"
                    data-fit-all-areas
                    disabled
                >
                    Fit all
                </button>
            </div>

            <div
                class="mapped-area-editor-list"
                data-feature-list
            >
                <p>Loading mapped areas...</p>
            </div>
        </section>

    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/place-map-editor.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

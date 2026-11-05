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
    'Edit Map | '
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
    data-place-slug="<?= htmlspecialchars((string) $place['slug'], ENT_QUOTES, 'UTF-8') ?>"
    data-place-name="<?= htmlspecialchars((string) $place['name'], ENT_QUOTES, 'UTF-8') ?>"
    data-place-latitude="<?= htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') ?>"
    data-place-longitude="<?= htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') ?>"
    data-csrf-token="<?= htmlspecialchars(community_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
    data-map-mode="place"
>
    <header class="mapped-area-editor-header">
        <div>
            <p class="eyebrow">Edit Map</p>
            <h1>Place layout</h1>
            <p>
                Draw the Place boundary, define Areas, and place Site markers
                from one unified editor.
            </p>
        </div>

        <a
            class="mapped-area-editor-back"
            href="/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>"
        >
            Back to Place
        </a>
    </header>

    <div class="mapped-area-editor-map-card">
        <div
            class="mapped-area-map-toolbar mapped-area-map-toolbar--draw"
            aria-label="Map editing controls"
        >
            <button type="button" data-start-drawing>Draw boundary</button>
            <button type="button" data-finish-drawing disabled>Finish</button>
            <button type="button" data-undo-point disabled>Undo</button>
            <button type="button" data-site-map-pick hidden>Place Site pin</button>
        </div>

        <div
            class="mapped-area-map-toolbar mapped-area-map-toolbar--style"
            role="group"
            aria-label="Map style"
        >
            <button type="button" data-map-style="street">Street</button>
            <button type="button" data-map-style="satellite" class="is-active">Satellite</button>
            <button type="button" data-map-style="terrain">Terrain</button>
        </div>

        <div
            id="mapped-area-editor-map"
            class="mapped-area-editor-map"
            aria-label="Place layout editor"
        ></div>

        <div class="mapped-area-editor-point-count" data-point-count hidden>
            0 points
        </div>

        <button
            type="button"
            class="mapped-area-fit-all"
            data-fit-all-areas
            disabled
        >
            Fit all
        </button>
    </div>

    <section class="mapped-area-editor-card mapped-area-editor-unified-card">
        <div class="mapped-area-editor-selection-header">
            <div>
                <h2>Edit selection</h2>
                <p>
                    Choose what you are editing. Place and Area use boundaries;
                    Sites use one exact GPS point.
                </p>
            </div>

            <div class="mapped-area-editor-mode-tabs" role="group" aria-label="Map object type">
                <button type="button" class="is-active" data-map-mode-button="place">
                    Place
                </button>
                <button type="button" data-map-mode-button="area">
                    Area
                </button>
                <button type="button" data-map-mode-button="site">
                    Site
                </button>
            </div>
        </div>

        <div class="mapped-area-editor-object-panel" data-polygon-editor>
            <input type="hidden" data-feature-type value="place_boundary">

            <div class="mapped-area-editor-object-fields">
                <div class="mapped-area-editor-object-identity">
                    <strong data-object-kind>Place</strong>
                    <p data-object-explanation>
                        The green boundary represents this Place.
                    </p>
                </div>

                <label>
                    <span>Label</span>
                    <input
                        type="text"
                        maxlength="120"
                        data-feature-label
                        value="<?= htmlspecialchars((string) $place['name'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                </label>

                <label data-area-type-field hidden>
                    <span>Area type</span>
                    <select data-area-feature-type>
                        <option value="camping_area">Camping area</option>
                        <option value="parking_area">Parking area</option>
                    </select>
                </label>
            </div>

            <div class="mapped-area-editor-place-boundary-note" data-place-boundary-note>
                <strong>Place boundary</strong>
                <span>
                    Every green boundary saved here belongs directly to
                    <?= htmlspecialchars((string) $place['name'], ENT_QUOTES, 'UTF-8') ?>.
                </span>
            </div>

            <div class="mapped-area-editor-stay-fields" data-area-details hidden>
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
                        <?php foreach (llama_place_map_overnight_status_options() as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <p class="mapped-area-editor-area-note" data-area-details-note></p>
            </div>

            <div class="mapped-area-editor-summary" data-feature-summary hidden>
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
        </div>

        <div class="mapped-area-editor-site-point-panel" data-site-point-panel hidden>
            <div class="mapped-area-editor-object-fields">
                <div class="mapped-area-editor-object-identity">
                    <strong>Site</strong>
                    <p>
                        A Site is one exact location. It does not need its own boundary.
                    </p>
                </div>

                <label>
                    <span>Site</span>
                    <select data-site-point-target>
                        <option value="new">New Site</option>
                    </select>
                </label>

                <label data-site-point-new-name>
                    <span>New Site name</span>
                    <input
                        type="text"
                        maxlength="120"
                        placeholder="e.g. G014, Riverside pullout"
                        data-site-point-name
                    >
                </label>
            </div>

            <div class="mapped-area-editor-site-coordinates">
                <label>
                    <span>Latitude</span>
                    <input type="text" inputmode="decimal" data-site-point-latitude>
                </label>
                <label>
                    <span>Longitude</span>
                    <input type="text" inputmode="decimal" data-site-point-longitude>
                </label>
            </div>

            <p class="mapped-area-editor-help" data-site-point-status>
                Select an existing Site or create a new one, then place its exact point.
            </p>
        </div>

        <div class="mapped-area-editor-tool-grid">
            <section class="mapped-area-editor-tool-panel">
                <div class="mapped-area-editor-tool-heading">
                    <div>
                        <h3>Device GPS</h3>
                        <p>Collect boundary points or set the selected Site location.</p>
                    </div>
                    <span class="mapped-area-gps-state" data-gps-state>Off</span>
                </div>

                <div class="mapped-area-gps-reading" data-gps-reading hidden>
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
                    <button type="button" data-start-gps>Start GPS</button>
                    <button type="button" data-stop-gps disabled>Stop GPS</button>
                    <button type="button" data-add-gps-point disabled>Add GPS point</button>
                    <button type="button" data-center-gps disabled>Center on me</button>
                </div>
            </section>

            <section class="mapped-area-editor-tool-panel" data-shape-tools>
                <div class="mapped-area-editor-tool-heading">
                    <div>
                        <h3>Edit shape</h3>
                        <p>Modify the selected Place boundary or Area.</p>
                    </div>
                </div>

                <div class="mapped-area-selected-point" data-selected-point hidden>
                    <div>
                        <span>Point</span>
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
                    <button type="button" data-remove-point disabled>Remove point</button>
                    <button type="button" data-use-gps-point disabled>Replace with GPS</button>
                    <button type="button" data-move-area disabled>Move shape</button>
                    <button type="button" data-revert-feature disabled>Revert changes</button>
                </div>
            </section>
        </div>

        <div class="mapped-area-editor-records">
            <div class="mapped-area-editor-card-heading">
                <div>
                    <h3>Mapped records</h3>
                    <p class="mapped-area-editor-card-subtitle">
                        Select a Place boundary or Area to edit it. Site markers are selected in Site mode.
                    </p>
                </div>

                <button type="button" data-new-feature>New boundary / Area</button>
            </div>

            <div class="mapped-area-editor-list" data-feature-list>
                <p>Loading map records...</p>
            </div>
        </div>

        <p class="mapped-area-editor-help" data-editor-help>
            Draw the Place boundary on the map, or use Device GPS to collect its points.
        </p>

        <div class="mapped-area-editor-footer">
            <div>
                <span class="mapped-area-editor-unsaved" data-unsaved-state hidden>Unsaved</span>
                <p class="mapped-area-editor-status" data-editor-status role="status" aria-live="polite"></p>
            </div>

            <div class="mapped-area-editor-save-actions">
                <button type="button" class="is-danger" data-delete-feature disabled>Delete</button>
                <button type="button" class="is-primary" data-save-feature disabled>Save changes</button>
                <button type="button" class="is-primary" data-save-site-point hidden>Save Site point</button>
            </div>
        </div>
    </section>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/place-map-editor/feature-state.js"></script>
<script src="/js/place-map-editor/geometry.js"></script>
<script src="/js/place-map-editor/gps.js"></script>
<script src="/js/place-map-editor/map-styles.js"></script>
<script src="/js/place-map-editor.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

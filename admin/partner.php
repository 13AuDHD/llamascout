<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-partners.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db =
    db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$partnerId =
    (int) (
        $_GET['id']
        ?? $_POST['partner_id']
        ?? 0
    );

if ($partnerId < 1) {
    header(
        'Location: /partners.php'
    );

    exit;
}

$notice = '';
$error = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['partner_action']
                        ?? ''
                    )
                );

            if ($action === 'save') {
                admin_partner_save(
                    $db,
                    $actorUserId,
                    $partnerId,
                    $_POST
                );

                $notice =
                    'Partner updated.';

            } elseif ($action === 'attach-place') {
                admin_partner_attach_place(
                    $db,
                    $actorUserId,
                    $partnerId,
                    (int) (
                        $_POST['place_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['relationship_type']
                        ?? 'official'
                    )
                );

                $notice =
                    'Place added to partner.';

            } elseif ($action === 'detach-place') {
                admin_partner_detach_place(
                    $db,
                    $partnerId,
                    (int) (
                        $_POST['place_id']
                        ?? 0
                    ),
                    $actorUserId
                );

                $notice =
                    'Place removed from partner.';

            } elseif ($action === 'toggle-place-branding') {
                admin_partner_set_place_branding(
                    $db,
                    $actorUserId,
                    $partnerId,
                    (int) (
                        $_POST['place_id']
                        ?? 0
                    ),
                    !empty(
                        $_POST['branding_enabled']
                    )
                );

                $notice =
                    'Partner location branding updated.';
            }

        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.partner',
                    [
                        'partner_id' =>
                            $partnerId,
                        'action' =>
                            $action
                            ?? '',
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The partner could not be updated.',
                        $reference
                    );
        }
    }
}

$partner =
    admin_partner_get(
        $db,
        $partnerId
    );

if (!$partner) {
    http_response_code(404);

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
        'Partner Not Found';

    $adminPageEyebrow =
        'Partners';

    $adminActiveNav =
        'partners';

    require __DIR__ . '/_header.php';
    ?>

    <section class="admin-panel">
        <div class="admin-empty-state">
            <h3>Partner not found.</h3>
            <a
                class="admin-button"
                href="/partners.php"
            >
                Back to partners
            </a>
        </div>
    </section>

    <?php
    require __DIR__ . '/_footer.php';

    exit;
}

$categories =
    admin_partner_categories();

$statuses =
    admin_partner_statuses();

$partnerLocations =
    admin_partner_locations(
        $db,
        $partnerId
    );

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
    (string) $partner['name'];

$adminPageEyebrow =
    'Partners';

$adminActiveNav =
    'partners';

require __DIR__ . '/_header.php';

$logoUrl =
    admin_partner_asset_url(
        $siteUrl,
        $partner['logo_path']
        ?? null
    );

$markerUrl =
    admin_partner_marker_url(
        $siteUrl,
        (string) (
            $partner['marker_icon']
            ?? 'brand-partner'
        )
    );

$category =
    (string) (
        $partner['category']
        ?? 'other'
    );

$status =
    (string) (
        $partner['status']
        ?? 'prospect'
    );
?>


<div class="admin-page-back">
    <a href="/partners.php">
        <i aria-hidden="true">
            <?= llama_icon('arrow-left') ?>
        </i>
        All partners
    </a>
</div>


<?php if ($notice !== ''): ?>

<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>

<?php endif; ?>


<section class="admin-partner-hero">

<div class="admin-partner-hero-brand">

<div class="admin-partner-logo-preview">

<?php if ($logoUrl !== ''): ?>

<img
    src="<?= moderation_e($logoUrl) ?>"
    alt="<?= moderation_e(
        (string) $partner['name']
    ) ?> logo"
>

<?php else: ?>

<i aria-hidden="true">
    <?= llama_icon('users') ?>
</i>

<?php endif; ?>

</div>


<div>

<p>
    <?= moderation_e(
        $categories[$category]
        ?? 'Partner'
    ) ?>
</p>

<h2>
    <?= moderation_e(
        (string) $partner['name']
    ) ?>
</h2>

<div class="admin-partner-hero-pills">

<span class="admin-status-pill">
    <?= moderation_e(
        $statuses[$status]
        ?? ucfirst($status)
    ) ?>
</span>

<?php if (
    !empty(
        $partner['branding_permitted']
    )
): ?>

<span class="admin-status-pill">
    Branding permitted
</span>

<?php endif; ?>

<?php if (
    !empty(
        $partner['show_map_markers']
    )
): ?>

<span class="admin-status-pill">
    Branded markers on
</span>

<?php endif; ?>

</div>

</div>

</div>


<div class="admin-partner-marker-preview">

<span>Map marker</span>

<img
    src="<?= moderation_e($markerUrl) ?>"
    alt=""
>

</div>

</section>


<form method="post">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="partner_id"
    value="<?= (int) $partnerId ?>"
>

<input
    type="hidden"
    name="partner_action"
    value="save"
>


<div class="admin-partner-layout">


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Identity</p>
        <h2>Overview</h2>
    </div>
</header>

<div class="admin-user-action-box">

<label>
    <span>Partner name</span>

    <input
        type="text"
        name="name"
        required
        value="<?= moderation_e(
            (string) $partner['name']
        ) ?>"
    >
</label>


<label>
    <span>Slug</span>

    <input
        type="text"
        name="slug"
        required
        value="<?= moderation_e(
            (string) $partner['slug']
        ) ?>"
    >
</label>


<label>
    <span>Category</span>

    <select name="category">

    <?php foreach (
        $categories
        as $value => $label
    ): ?>

        <option
            value="<?= moderation_e($value) ?>"
            <?= $category === $value
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($label) ?>
        </option>

    <?php endforeach; ?>

    </select>
</label>


<label>
    <span>Status</span>

    <select name="status">

    <?php foreach (
        $statuses
        as $value => $label
    ): ?>

        <option
            value="<?= moderation_e($value) ?>"
            <?= $status === $value
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($label) ?>
        </option>

    <?php endforeach; ?>

    </select>
</label>


<label>
    <span>Internal notes</span>

    <textarea
        name="notes"
        rows="5"
        placeholder="Relationship notes, contacts, next steps..."
    ><?= moderation_e(
        (string) (
            $partner['notes']
            ?? ''
        )
    ) ?></textarea>
</label>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Visual identity</p>
        <h2>Branding</h2>
    </div>
</header>

<div class="admin-user-action-box">

<label class="admin-toggle">

<input
    type="checkbox"
    name="branding_permitted"
    value="1"
    <?= !empty(
        $partner['branding_permitted']
    )
        ? 'checked'
        : '' ?>
>

<span class="admin-toggle-track">
    <span class="admin-toggle-knob"></span>
</span>

<span class="admin-toggle-copy">
    <strong>Branding permitted</strong>
    <small>
        Confirms that Llama Scout is permitted to use the partner's
        branding in the enabled placements below.
    </small>
</span>

</label>


<label>
    <span>Logo path</span>

    <input
        type="text"
        name="logo_path"
        value="<?= moderation_e(
            (string) (
                $partner['logo_path']
                ?? ''
            )
        ) ?>"
        placeholder="assets/brands/loves/logo.svg"
    >

    <small>
        Relative paths are resolved from llamascout.com.
    </small>
</label>


<label>
    <span>Map marker icon</span>

    <input
        type="text"
        name="marker_icon"
        value="<?= moderation_e(
            (string) (
                $partner['marker_icon']
                ?? 'brand-partner'
            )
        ) ?>"
        placeholder="brand-loves"
    >

    <small>
        File name only, without .svg, from assets/icons.
    </small>
</label>


<div class="admin-partner-color-grid">

<label>
    <span>Primary color</span>

    <div class="admin-partner-color-field">

    <input
        type="color"
        aria-label="Primary color picker"
        value="<?= moderation_e(
            (string) (
                $partner['primary_color']
                ?: '#2563EB'
            )
        ) ?>"
        data-partner-color-picker="primary"
    >

    <input
        type="text"
        name="primary_color"
        value="<?= moderation_e(
            (string) (
                $partner['primary_color']
                ?? ''
            )
        ) ?>"
        placeholder="#2563EB"
        data-partner-color-value="primary"
    >

    </div>
</label>


<label>
    <span>Accent color</span>

    <div class="admin-partner-color-field">

    <input
        type="color"
        aria-label="Accent color picker"
        value="<?= moderation_e(
            (string) (
                $partner['accent_color']
                ?: '#FFFFFF'
            )
        ) ?>"
        data-partner-color-picker="accent"
    >

    <input
        type="text"
        name="accent_color"
        value="<?= moderation_e(
            (string) (
                $partner['accent_color']
                ?? ''
            )
        ) ?>"
        placeholder="#FFFFFF"
        data-partner-color-value="accent"
    >

    </div>
</label>

</div>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Presentation</p>
        <h2>Placements</h2>
    </div>
</header>

<div class="admin-user-action-box admin-partner-toggle-list">

<label class="admin-toggle">

<input
    type="checkbox"
    name="show_map_markers"
    value="1"
    <?= !empty(
        $partner['show_map_markers']
    )
        ? 'checked'
        : '' ?>
>

<span class="admin-toggle-track">
    <span class="admin-toggle-knob"></span>
</span>

<span class="admin-toggle-copy">
    <strong>Show branded map markers</strong>
    <small>
        Uses this partner's marker for all associated partner locations.
        This is independent from sponsorship status.
    </small>
</span>

</label>


<label class="admin-toggle">

<input
    type="checkbox"
    name="use_branded_cards"
    value="1"
    <?= !empty(
        $partner['use_branded_cards']
    )
        ? 'checked'
        : '' ?>
>

<span class="admin-toggle-track">
    <span class="admin-toggle-knob"></span>
</span>

<span class="admin-toggle-copy">
    <strong>Use branded place cards</strong>
    <small>
        Allows partner colors and logo treatment on associated place cards.
    </small>
</span>

</label>


<label class="admin-toggle">

<input
    type="checkbox"
    name="use_branded_place_pages"
    value="1"
    <?= !empty(
        $partner['use_branded_place_pages']
    )
        ? 'checked'
        : '' ?>
>

<span class="admin-toggle-track">
    <span class="admin-toggle-knob"></span>
</span>

<span class="admin-toggle-copy">
    <strong>Use branded place pages</strong>
    <small>
        Allows partner branding in the hero and partner identity areas
        of associated place pages.
    </small>
</span>

</label>

</div>

</section>


<section class="admin-panel admin-partner-locations-panel">

<header class="admin-panel-header">
    <div>
        <p>Places</p>
        <h2>Partner Locations</h2>
    </div>

    <span>
        <?= number_format(
            count(
                $partnerLocations
            )
        ) ?>
    </span>
</header>


<div class="admin-user-action-box">

<form
    method="post"
    class="admin-partner-place-picker"
    data-partner-place-picker
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="partner_id"
    value="<?= (int) $partnerId ?>"
>

<input
    type="hidden"
    name="partner_action"
    value="attach-place"
>

<input
    type="hidden"
    name="place_id"
    value=""
    data-partner-place-id
>


<label>
    <span>Add existing Place</span>

    <input
        type="search"
        autocomplete="off"
        placeholder="Start typing a Place name, town, or ID..."
        data-partner-place-search
    >
</label>


<div
    class="admin-partner-place-results"
    data-partner-place-results
    hidden
></div>


<label>
    <span>Relationship</span>

    <select name="relationship_type">
        <option value="official">
            Official partner location
        </option>

        <option value="demo">
            Demo partner location
        </option>

        <option value="affiliate">
            Affiliate / participating location
        </option>

        <option value="other">
            Other relationship
        </option>
    </select>
</label>


<div
    class="admin-partner-place-selected"
    data-partner-place-selected
    hidden
>
    <span>Selected Place</span>
    <strong data-partner-place-selected-name></strong>
    <small data-partner-place-selected-meta></small>
</div>


<div class="admin-user-form-actions">

<button
    class="admin-button"
    type="submit"
    data-partner-place-submit
    disabled
>
    Add Place
</button>

</div>

</form>

</div>


<?php if (!$partnerLocations): ?>

<div class="admin-empty-state">

<h3>No partner locations yet.</h3>

<p>
    Search for an existing Llama Scout Place above and attach it
    to this partner.
</p>

</div>

<?php else: ?>

<div class="admin-partner-location-list">

<?php foreach (
    $partnerLocations
    as $location
): ?>

<article class="admin-partner-location">

<div>

<span class="admin-partner-location-type">
    <?= moderation_e(
        ucwords(
            str_replace(
                '-',
                ' ',
                (string) (
                    $location[
                        'relationship_type'
                    ]
                    ?? 'official'
                )
            )
        )
    ) ?>
</span>

<strong>
    <?= moderation_e(
        (string) (
            $location['name']
            ?? 'Unnamed Place'
        )
    ) ?>
</strong>

<small>
    <?= moderation_e(
        implode(
            ' · ',
            array_filter(
                [
                    trim(
                        (string) (
                            $location['city']
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $location['state']
                            ?? ''
                        )
                    ),
                    ucfirst(
                        trim(
                            (string) (
                                $location['place_status']
                                ?? ''
                            )
                        )
                    ),
                    '#'
                    . (int) $location['place_id'],
                ]
            )
        )
    ) ?>
</small>

</div>


<div class="admin-partner-location-actions">

<a
    class="admin-button"
    href="/place.php?id=<?= (int) $location['place_id'] ?>"
>
    Manage Place
</a>


<form method="post">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="partner_id"
    value="<?= (int) $partnerId ?>"
>

<input
    type="hidden"
    name="place_id"
    value="<?= (int) $location['place_id'] ?>"
>

<input
    type="hidden"
    name="partner_action"
    value="toggle-place-branding"
>

<input
    type="hidden"
    name="branding_enabled"
    value="<?= !empty(
        $location['branding_enabled']
    )
        ? '0'
        : '1' ?>"
>

<button
    class="admin-button"
    type="submit"
>
    <?= !empty(
        $location['branding_enabled']
    )
        ? 'Disable branding'
        : 'Enable branding' ?>
</button>

</form>


<form
    method="post"
    onsubmit="return confirm('Remove this Place from the partner?');"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="partner_id"
    value="<?= (int) $partnerId ?>"
>

<input
    type="hidden"
    name="place_id"
    value="<?= (int) $location['place_id'] ?>"
>

<input
    type="hidden"
    name="partner_action"
    value="detach-place"
>

<button
    class="admin-button is-danger"
    type="submit"
>
    Remove
</button>

</form>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Usage rights</p>
        <h2>Trademark & Assets</h2>
    </div>
</header>

<div class="admin-user-action-box">

<label>
    <span>Trademark / asset owner</span>

    <input
        type="text"
        name="trademark_owner"
        value="<?= moderation_e(
            (string) (
                $partner['trademark_owner']
                ?? ''
            )
        ) ?>"
        placeholder="Company legal name"
    >
</label>


<label>
    <span>Branding rights note</span>

    <textarea
        name="branding_rights_note"
        rows="6"
        placeholder="Record permission, agreement language, attribution requirements, approved placements, or restrictions."
    ><?= moderation_e(
        (string) (
            $partner['branding_rights_note']
            ?? ''
        )
    ) ?></textarea>
</label>

</div>

</section>


</div>


<div class="admin-partner-savebar">

<div>
    <strong>Save partner settings</strong>
    <span>
        Changes apply to this partner's configuration.
    </span>
</div>

<button
    class="admin-button"
    type="submit"
>
    Save partner
</button>

</div>


</form>


<script>
(() => {
    'use strict';

    document
        .querySelectorAll(
            '[data-partner-color-picker]'
        )
        .forEach((picker) => {
            const key =
                picker.dataset.partnerColorPicker;

            const input =
                document.querySelector(
                    '[data-partner-color-value="'
                    + key
                    + '"]'
                );

            if (!input) {
                return;
            }

            picker.addEventListener(
                'input',
                () => {
                    input.value =
                        picker.value.toUpperCase();
                }
            );

            input.addEventListener(
                'change',
                () => {
                    const value =
                        String(
                            input.value
                            || ''
                        ).trim();

                    if (
                        /^#[0-9A-Fa-f]{6}$/.test(
                            value
                        )
                    ) {
                        picker.value =
                            value;
                    }
                }
            );
        });
})();
</script>


<script src="https://llamascout.com/js/admin/partner-locations.js"></script>

<?php require __DIR__ . '/_footer.php'; ?>

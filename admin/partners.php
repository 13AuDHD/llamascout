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

            if ($action === 'create') {
                admin_partner_create(
                    $db,
                    $actorUserId,
                    $_POST
                );

                $notice =
                    'Partner created.';
            }

        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.partners',
                    [
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
                        'The partner could not be saved.',
                        $reference
                    );
        }
    }
}

$partners =
    admin_partner_list(
        $db
    );

$counts =
    admin_partner_counts(
        $partners
    );

$categories =
    admin_partner_categories();

$statuses =
    admin_partner_statuses();

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
    'Partners';

$adminPageEyebrow =
    'Commerce';

$adminActiveNav =
    'partners';

require __DIR__ . '/_header.php';
?>


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


<section
    class="admin-commerce-stat-grid"
    aria-label="Partner statistics"
>

<article class="admin-commerce-stat-card">
    <span>Total</span>
    <strong>
        <?= number_format(
            (int) $counts['total']
        ) ?>
    </strong>
</article>

<article class="admin-commerce-stat-card">
    <span>Active</span>
    <strong>
        <?= number_format(
            (int) $counts['active']
        ) ?>
    </strong>
</article>

<article class="admin-commerce-stat-card">
    <span>Prospects</span>
    <strong>
        <?= number_format(
            (int) $counts['prospect']
        ) ?>
    </strong>
</article>

<article class="admin-commerce-stat-card">
    <span>Inactive</span>
    <strong>
        <?= number_format(
            (int) $counts['inactive']
        ) ?>
    </strong>
</article>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>New relationship</p>
        <h2>Create Partner</h2>
    </div>
</header>


<form
    class="admin-user-action-box"
    method="post"
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
    name="partner_action"
    value="create"
>


<div class="admin-integration-summary">

<label>
    <span>Partner name</span>

    <input
        type="text"
        name="name"
        required
        placeholder="Love's Travel Stops"
    >
</label>


<label>
    <span>Slug</span>

    <input
        type="text"
        name="slug"
        placeholder="loves"
    >

    <small>
        Leave blank to generate it from the partner name.
    </small>
</label>


<label>
    <span>Category</span>

    <select name="category">

    <?php foreach (
        $categories
        as $value => $label
    ): ?>

        <option
            value="<?= moderation_e(
                $value
            ) ?>"
        >
            <?= moderation_e(
                $label
            ) ?>
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
            value="<?= moderation_e(
                $value
            ) ?>"
            <?= $value === 'prospect'
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e(
                $label
            ) ?>
        </option>

    <?php endforeach; ?>

    </select>
</label>

</div>


<div class="admin-user-form-actions">

<button
    class="admin-button"
    type="submit"
>
    Create partner
</button>

</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Partner directory</p>
        <h2>Partners</h2>
    </div>

    <span>
        <?= number_format(
            count($partners)
        ) ?>
    </span>
</header>


<?php if (!$partners): ?>

<div class="admin-empty-state">

<h3>No partners yet.</h3>

<p>
    Create the first partner above. Branding, locations, contracts,
    preview access, and sponsored placements will be managed from
    each partner's individual page as those controls are added.
</p>

</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Partner</th>
    <th>Category</th>
    <th>Status</th>
    <th>Map markers</th>
    <th>Branding</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $partners
    as $partner
): ?>

<?php
$status =
    trim(
        (string) (
            $partner['status']
            ?? 'prospect'
        )
    );

$category =
    trim(
        (string) (
            $partner['category']
            ?? 'other'
        )
    );
?>

<tr>

<td data-label="Partner">

<strong>
    <?= moderation_e(
        (string) $partner['name']
    ) ?>
</strong>

<small>
    <?= moderation_e(
        (string) $partner['slug']
    ) ?>
</small>

</td>


<td data-label="Category">
    <?= moderation_e(
        $categories[$category]
        ?? $category
    ) ?>
</td>


<td data-label="Status">

<span class="admin-status-pill">
    <?= moderation_e(
        $statuses[$status]
        ?? ucfirst($status)
    ) ?>
</span>

</td>


<td data-label="Map markers">
    <?= !empty(
        $partner['show_map_markers']
    )
        ? 'On'
        : 'Off' ?>
</td>


<td data-label="Branding">

<?php if (
    !empty(
        $partner['branding_permitted']
    )
): ?>

    Permitted

<?php else: ?>

    Not approved

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>


<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/support.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$status = trim(
    (string) (
        $_GET['status']
        ?? $_POST['status_filter']
        ?? 'open'
    )
);

$requestId = (int) (
    $_GET['id']
    ?? $_POST['request_id']
    ?? 0
);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            llama_support_update(
                $db,
                (int) (
                    $_POST['request_id']
                    ?? 0
                ),
                (string) (
                    $_POST['support_status']
                    ?? 'open'
                ),
                (string) (
                    $_POST['internal_notes']
                    ?? ''
                )
            );

            $notice = 'Support request updated.';

        } catch (Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The support request could not be updated.';
        }
    }
}

$requests = llama_support_requests(
    $db,
    $status
);

$selected = $requestId > 0
    ? llama_support_request(
        $db,
        $requestId
    )
    : null;

$categories = llama_support_categories();

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Support';
$adminPageEyebrow = 'Operations';
$adminActiveNav = 'support';

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


<section class="admin-panel admin-user-filter-panel">

<form method="get">

<label>
    <span>Status</span>
    <select name="status">
        <?php foreach (
            [
                'open' => 'Open',
                'waiting' => 'Waiting',
                'resolved' => 'Resolved',
                'all' => 'All',
            ]
            as $key => $label
        ): ?>
            <option
                value="<?= moderation_e($key) ?>"
                <?= $status === $key
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<button class="admin-button" type="submit">
    Filter
</button>

</form>

</section>


<?php if ($selected): ?>

<section class="admin-panel">

<header class="admin-panel-header">
<div>
    <p>
        Support #<?= (int) $selected['id'] ?>
    </p>

    <h2>
        <?= moderation_e(
            (string) $selected['subject']
        ) ?>
    </h2>
</div>

<span class="admin-status-pill">
    <?= moderation_e(
        ucfirst(
            (string) $selected['status']
        )
    ) ?>
</span>
</header>

<div class="admin-user-action-box">

<p>
    <strong>Name:</strong>
    <?= moderation_e(
        (string) $selected['name']
    ) ?>
</p>

<p>
    <strong>Email:</strong>
    <a
        href="mailto:<?= moderation_e(
            (string) $selected['email']
        ) ?>?subject=<?= rawurlencode(
            'Re: '
            . (string) $selected['subject']
            . ' [Support #'
            . (int) $selected['id']
            . ']'
        ) ?>"
    >
        <?= moderation_e(
            (string) $selected['email']
        ) ?>
    </a>
</p>

<p>
    <strong>Category:</strong>
    <?= moderation_e(
        $categories[
            (string) $selected['category']
        ]
        ?? (string) $selected['category']
    ) ?>
</p>

<?php if (
    !empty($selected['order_number'])
): ?>
<p>
    <strong>Order:</strong>
    <?= moderation_e(
        (string) $selected['order_number']
    ) ?>
</p>
<?php endif; ?>

<?php if (
    !empty($selected['username'])
): ?>
<p>
    <strong>Account:</strong>
    @<?= moderation_e(
        (string) $selected['username']
    ) ?>
</p>
<?php endif; ?>

<p>
    <strong>Received:</strong>
    <?= moderation_e(
        (string) $selected['created_at']
    ) ?>
</p>

</div>

<div class="admin-user-action-box">

<p style="white-space:pre-wrap;"><?= moderation_e(
    (string) $selected['message']
) ?></p>

</div>

<form method="post" class="admin-user-action-box">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="request_id"
    value="<?= (int) $selected['id'] ?>"
>

<input
    type="hidden"
    name="status_filter"
    value="<?= moderation_e($status) ?>"
>

<label>
    <span>Status</span>

    <select name="support_status">
        <?php foreach (
            [
                'open',
                'waiting',
                'resolved',
            ]
            as $option
        ): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $selected['status'] === $option
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    ucfirst($option)
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Internal notes</span>

    <textarea
        name="internal_notes"
        rows="6"
        maxlength="10000"
    ><?= moderation_e(
        (string) (
            $selected['internal_notes']
            ?? ''
        )
    ) ?></textarea>
</label>

<div class="admin-user-form-actions">

<a
    class="admin-button"
    href="mailto:<?= moderation_e(
        (string) $selected['email']
    ) ?>?subject=<?= rawurlencode(
        'Re: '
        . (string) $selected['subject']
        . ' [Support #'
        . (int) $selected['id']
        . ']'
    ) ?>"
>
    Reply by email
</a>

<button class="admin-button" type="submit">
    Save
</button>

</div>

</form>

</section>

<?php endif; ?>


<section class="admin-panel">

<header class="admin-panel-header">
<div>
    <p>Queue</p>

    <h2>
        <?= number_format(
            count($requests)
        ) ?>
        request<?= count($requests) === 1
            ? ''
            : 's' ?>
    </h2>
</div>
</header>

<?php if (!$requests): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-inbox"
        aria-hidden="true"
    ></i>

    <h3>No support requests here.</h3>
</div>

<?php else: ?>

<div class="admin-inbox-list">

<?php foreach ($requests as $request): ?>

<article class="admin-inbox-item">

<span class="admin-inbox-icon">
    <i
        class="fa-solid fa-headset"
        aria-hidden="true"
    ></i>
</span>

<div class="admin-inbox-content">

<span class="admin-inbox-type">
    <?= moderation_e(
        $categories[
            (string) $request['category']
        ]
        ?? 'Support'
    ) ?>
</span>

<strong>
    <?= moderation_e(
        (string) $request['subject']
    ) ?>
</strong>

<p>
    <?= moderation_e(
        (string) $request['name']
    ) ?>
    |
    <?= moderation_e(
        (string) $request['email']
    ) ?>
</p>

<time>
    <?= moderation_e(
        (string) $request['created_at']
    ) ?>
</time>

</div>

<a
    class="admin-button"
    href="/support.php?id=<?= (int) $request['id'] ?>&amp;status=<?= moderation_e($status) ?>"
>
    Open
</a>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>

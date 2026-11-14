<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/support.php';
require_once dirname(__DIR__) . '/app/support-conversations.php';
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
$replyDraft = '';
$conversationReady = llama_support_conversations_ready($db);

if (isset($_GET['reply']) && $_GET['reply'] === 'sent') {
    $notice = 'Reply sent and saved to the ticket.';
} elseif (isset($_GET['reply']) && $_GET['reply'] === 'note') {
    $notice = 'Private note saved to the ticket.';
} elseif (isset($_GET['canned_saved'])) {
    $notice = 'Canned response saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? 'update_ticket'));
        try {
            if ($action === 'reply') {
                if (!$conversationReady) {
                    throw new RuntimeException('Install the Support conversation migration first.');
                }
                $postedId = (int) ($_POST['request_id'] ?? 0);
                $requestId = $postedId;
                $replyDraft = (string) ($_POST['reply_body'] ?? '');
                $internal = (string) ($_POST['reply_visibility'] ?? 'public') === 'internal';
                $sent = llama_support_add_reply(
                    $db,
                    $postedId,
                    (int) ($adminUser['id'] ?? 0),
                    $replyDraft,
                    $internal,
                    (string) ($_POST['submission_key'] ?? ''),
                    (array) ($_FILES['attachments'] ?? []),
                    (int) ($_POST['canned_response_id'] ?? 0)
                );
                if (!$sent) {
                    $replyDraft = '';
                    $error = 'Reply saved, but SMTP delivery failed. Check the Error Log and the failed message below before retrying.';
                } else {
                    $type = $internal ? 'note' : 'sent';
                    header('Location: /support.php?'
                        . http_build_query([
                            'id' => $postedId,
                            'status' => $status,
                            'reply' => $type,
                        ])
                        . '#support-conversation', true, 303);
                    exit;
                }
            } elseif ($action === 'save_canned') {
                if (!$conversationReady) {
                    throw new RuntimeException('Install the Support conversation migration first.');
                }
                $responseId = llama_support_save_canned_response(
                    $db,
                    (int) ($adminUser['id'] ?? 0),
                    (int) ($_POST['canned_id'] ?? 0),
                    (string) ($_POST['canned_title'] ?? ''),
                    (string) ($_POST['canned_category'] ?? ''),
                    (string) ($_POST['canned_body'] ?? ''),
                    !empty($_POST['canned_active'])
                );
                header('Location: /support.php?'
                    . http_build_query([
                        'id' => $requestId,
                        'status' => $status,
                        'canned' => $responseId,
                        'canned_saved' => 1,
                    ])
                    . '#support-canned', true, 303);
                exit;
            } elseif ($action === 'update_ticket') {
                llama_support_update(
                    $db,
                    (int) ($_POST['request_id'] ?? 0),
                    (string) ($_POST['support_status'] ?? 'open'),
                    (string) ($_POST['internal_notes'] ?? '')
                );
                $notice = 'Support ticket updated.';
            } else {
                throw new InvalidArgumentException('Unknown Support action.');
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException
                || $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The Support action could not be completed.';
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

$ticketMessages = $conversationReady && $selected
    ? llama_support_conversation($db, $requestId)
    : [];
$ticketAttachments = $conversationReady && $selected
    ? llama_support_message_attachments($db, $requestId)
    : [];
$cannedResponses = $conversationReady
    ? llama_support_canned_responses($db, true)
    : [];
$activeCannedResponses = array_values(array_filter(
    $cannedResponses,
    static fn(array $response): bool => (int) $response['is_active'] === 1
));
$editingCannedId = (int) ($_GET['canned'] ?? 0);
$editingCanned = null;
foreach ($cannedResponses as $response) {
    if ((int) $response['id'] === $editingCannedId) {
        $editingCanned = $response;
        break;
    }
}


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

$adminUsesSplitCss = true;

$adminPageStyles = [
    'support.css',
];

$adminFeatureStyles = [];

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

<form
    method="get"
    class="admin-support-filter-form"
>

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
<?php
$selectedTicket = trim(
    (string) (
        $selected['ticket_number']
        ?? $selected['id']
    )
);

$selectedPhone =
    trim(
        (string) (
            $selected['phone_number']
            ?? ''
        )
    );

$selectedPreferredContact =
    trim(
        (string) (
            $selected['preferred_contact']
            ?? 'email'
        )
    );

$selectedPreferredLabel =
    llama_support_contact_method_label(
        $selectedPreferredContact
    );

$selectedSupportPinSet =
    !empty(
        $selected['user_id']
    )
        ? llama_support_pin_is_set(
            $db,
            (int) $selected['user_id']
        )
        : null;
?>

<section class="admin-panel admin-support-ticket-panel">

<header class="admin-panel-header">
<div>
    <p>
        Ticket #<?= moderation_e(
            $selectedTicket
        ) ?>
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
    <?= moderation_e((string) $selected['email']) ?>
</p>

<p>
    <strong>Phone:</strong>
    <?php if ($selectedPhone !== ''): ?>
        <a href="tel:<?= moderation_e($selectedPhone) ?>">
            <?= moderation_e(
                llama_support_format_phone(
                    $selectedPhone
                )
            ) ?>
        </a>
    <?php else: ?>
        Not provided
    <?php endif; ?>
</p>

<p>
    <strong>Preferred contact:</strong>
    <?= moderation_e(
        $selectedPreferredLabel
    ) ?>
</p>

<?php if ($selectedSupportPinSet !== null): ?>
<p>
    <strong>Support PIN:</strong>
    <?= $selectedSupportPinSet
        ? 'Configured'
        : 'Not configured' ?>
</p>
<?php endif; ?>

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
    !empty($selected['error_reference'])
): ?>
<p>
    <strong>Error:</strong>
    <a
        href="/errors.php?q=<?= rawurlencode(
            (string) $selected['error_reference']
        ) ?>"
    >
        <?= moderation_e(
            (string) $selected['error_reference']
        ) ?>
    </a>
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
        llama_format_viewer_datetime(
            (string) $selected['created_at']
        )
    ) ?>
</p>

</div>

<section class="admin-user-action-box admin-support-conversation" id="support-conversation">
    <h3>Conversation</h3>
    <div class="admin-support-thread">
        <article class="admin-support-entry is-customer">
            <header><strong><?= moderation_e((string) $selected['name']) ?></strong>
                <span>Original request</span>
                <time><?= moderation_e(llama_format_viewer_datetime((string) $selected['created_at'])) ?></time>
            </header>
            <p class="admin-support-message"><?= moderation_e((string) $selected['message']) ?></p>
        </article>

        <?php foreach ($ticketMessages as $entry): ?>
        <?php
            $isPrivate = (string) $entry['visibility'] === 'internal';
            $isStaff = (string) $entry['author_role'] === 'staff';
            $delivery = (string) ($entry['send_status'] ?? 'none');
        ?>
        <article class="admin-support-entry <?= $isPrivate ? 'is-private' : ($isStaff ? 'is-staff' : 'is-customer') ?>">
            <header>
                <strong><?= moderation_e($isPrivate ? 'Private staff note' : ($isStaff ? 'Llama Scout Support' : (string) ($entry['sender_email'] ?? 'Customer'))) ?></strong>
                <span><?= moderation_e($isPrivate ? 'Internal only' : ucfirst((string) $entry['channel'])) ?></span>
                <?php if ($delivery === 'failed'): ?><span class="admin-support-send-error">Email failed</span><?php endif; ?>
                <?php if ($delivery === 'pending'): ?><span class="admin-support-send-error">Pending delivery</span><?php endif; ?>
                <time><?= moderation_e(llama_format_viewer_datetime((string) $entry['created_at'])) ?></time>
            </header>
            <p class="admin-support-message"><?= moderation_e((string) $entry['body_text']) ?></p>
            <?php foreach (($ticketAttachments[(int) $entry['id']] ?? []) as $attachment): ?>
                <a class="admin-support-attachment" href="/support-attachment.php?id=<?= (int) $attachment['id'] ?>">
                    <?= moderation_e((string) $attachment['original_name']) ?>
                    (<?= number_format((int) $attachment['byte_size'] / 1024) ?> KB)
                </a>
            <?php endforeach; ?>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ($conversationReady): ?>
    <form method="post" enctype="multipart/form-data" class="admin-support-reply-form" data-support-reply>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= (int) $selected['id'] ?>">
        <input type="hidden" name="status_filter" value="<?= moderation_e($status) ?>">
        <input type="hidden" name="submission_key" value="<?= bin2hex(random_bytes(16)) ?>">
        <label for="support-canned-select">Canned response</label>
        <select id="support-canned-select" name="canned_response_id" data-support-canned>
            <option value="0">Choose a response (optional)</option>
            <?php foreach ($activeCannedResponses as $response): ?>
            <option value="<?= (int) $response['id'] ?>"
                data-body="<?= moderation_e((string) $response['body_text']) ?>">
                <?= moderation_e((string) $response['category'] . ' / ' . (string) $response['title']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <label for="support-reply-body">Reply</label>
        <textarea id="support-reply-body" name="reply_body" rows="7" maxlength="10000"
            data-customer-name="<?= moderation_e((string) $selected['name']) ?>"
            data-ticket-number="<?= moderation_e($selectedTicket) ?>"
            data-ticket-subject="<?= moderation_e((string) $selected['subject']) ?>"
            required><?= moderation_e($replyDraft) ?></textarea>
        <label for="support-reply-visibility">Visibility</label>
        <select id="support-reply-visibility" name="reply_visibility" data-support-visibility>
            <option value="public">Public reply (send email)</option>
            <option value="internal">Private staff note (do not email)</option>
        </select>
        <label for="support-reply-attachments">Attachments (up to 3 files, max 10 MB each)</label>
        <input id="support-reply-attachments" type="file" name="attachments[]" multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf,.txt" data-support-attachments>
        <p class="admin-support-hint">Public replies are emailed from support@llamascout.com and saved to this ticket. Private notes never leave Basecamp.</p>
        <button class="admin-button" type="submit">Save and send reply</button>
    </form>
    <?php else: ?>
        <p class="admin-support-hint">Conversation replies and canned responses require the Support SQL migration. Existing ticket status and notes remain available below.</p>
    <?php endif; ?>
</section>

<form method="post" class="admin-user-action-box">
<input type="hidden" name="action" value="update_ticket">

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

<?php if (
    $selectedPhone !== ''
    && $selectedPreferredContact === 'text'
): ?>
<a
    class="admin-button"
    href="sms:<?= moderation_e(
        $selectedPhone
    ) ?>"
>
    Text
</a>
<?php elseif (
    $selectedPhone !== ''
    && $selectedPreferredContact === 'phone'
): ?>
<a
    class="admin-button"
    href="tel:<?= moderation_e(
        $selectedPhone
    ) ?>"
>
    Call
</a>
<?php endif; ?>

<button class="admin-button" type="submit">
    Save
</button>

</div>

</form>

</section>

<?php endif; ?>


<section class="admin-panel admin-support-queue">

<header class="admin-panel-header">
<div>
    <p>Queue</p>

    <h2>
        <?= number_format(
            count($requests)
        ) ?>
        ticket<?= count($requests) === 1
            ? ''
            : 's' ?>
    </h2>
</div>
</header>

<?php if (!$requests): ?>

<div class="admin-empty-state">
    <i aria-hidden="true">
        <?= llama_icon('inbox') ?>
    </i>

    <h3>No support tickets here.</h3>
</div>

<?php else: ?>

<div class="admin-inbox-list">

<?php foreach ($requests as $request): ?>
<?php
$requestTicket = trim(
    (string) (
        $request['ticket_number']
        ?? $request['id']
    )
);

$requestPreferredLabel =
    llama_support_contact_method_label(
        (string) (
            $request['preferred_contact']
            ?? 'email'
        )
    );
?>

<article class="admin-inbox-item">

<span class="admin-inbox-icon">
    <i aria-hidden="true">
        <?= llama_icon('headset') ?>
    </i>
</span>

<div class="admin-inbox-content">

<span class="admin-inbox-type">
    Ticket #<?= moderation_e(
        $requestTicket
    ) ?>
    |
    <?= moderation_e(
        $categories[
            (string) $request['category']
        ]
        ?? 'Support'
    ) ?>
    |
    <?= moderation_e(
        $requestPreferredLabel
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

    <?php if (
        !empty($request['phone_number'])
    ): ?>
        |
        <?= moderation_e(
            llama_support_format_phone(
                (string) $request['phone_number']
            )
        ) ?>
    <?php endif; ?>

    <?php if (
        !empty($request['error_reference'])
    ): ?>
        |
        <?= moderation_e(
            (string) $request['error_reference']
        ) ?>
    <?php endif; ?>
</p>

<time>
    <?= moderation_e(
        llama_format_viewer_datetime(
            (string) $request['created_at']
        )
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


<?php if ($conversationReady): ?>
<section class="admin-panel admin-support-canned-panel" id="support-canned">
    <details <?= $editingCanned !== null ? 'open' : '' ?>>
        <summary>Canned responses (<?= count($activeCannedResponses) ?> active)</summary>
        <div class="admin-support-canned-inner">
            <form method="post" class="admin-support-canned-editor">
                <input type="hidden" name="action" value="save_canned">
                <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                <input type="hidden" name="status_filter" value="<?= moderation_e($status) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">
                <input type="hidden" name="canned_id" value="<?= (int) ($editingCanned['id'] ?? 0) ?>">
                <label>Title
                    <input type="text" name="canned_title" maxlength="150" required
                        value="<?= moderation_e((string) ($editingCanned['title'] ?? '')) ?>">
                </label>
                <label>Category
                    <input type="text" name="canned_category" maxlength="80" required
                        value="<?= moderation_e((string) ($editingCanned['category'] ?? 'General')) ?>">
                </label>
                <label>Response text (use {{customer_name}}, {{ticket_number}}, {{ticket_subject}})
                    <textarea name="canned_body" rows="6" maxlength="10000" required><?= moderation_e((string) ($editingCanned['body_text'] ?? '')) ?></textarea>
                </label>
                <label class="admin-support-inline-checkbox">
                    <input type="checkbox" name="canned_active" value="1" <?= $editingCanned === null || !empty($editingCanned['is_active']) ? 'checked' : '' ?>>
                    Active
                </label>
                <div class="admin-user-form-actions">
                    <button class="admin-button" type="submit">Save response</button>
                    <?php if ($editingCanned !== null): ?>
                    <a class="admin-button" href="/support.php?<?= moderation_e(http_build_query(['status' => $status, 'id' => $requestId])) ?>#support-canned">New response</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($cannedResponses): ?>
            <div class="admin-support-canned-list">
                <?php foreach ($cannedResponses as $response): ?>
                    <a href="/support.php?<?= moderation_e(http_build_query(['status' => $status, 'id' => $requestId, 'canned' => (int) $response['id']])) ?>#support-canned">
                        <strong><?= moderation_e((string) $response['title']) ?></strong>
                        <span><?= moderation_e((string) $response['category']) ?> · <?= (int) $response['use_count'] ?> uses<?= empty($response['is_active']) ? ' · Inactive' : '' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </details>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

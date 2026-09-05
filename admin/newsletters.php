<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/newsletters.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

$issueId =
    (int) (
        $_GET['id']
        ?? $_POST['newsletter_id']
        ?? 0
    );

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
            $action = trim(
                (string) (
                    $_POST[
                        'newsletter_action'
                    ]
                    ?? ''
                )
            );

            if (
                in_array(
                    $action,
                    [
                        'save',
                        'schedule',
                        'send-now',
                    ],
                    true
                )
            ) {
                $issueId =
                    llama_newsletter_save_issue(
                        $db,
                        $actorUserId,
                        $_POST,
                        $issueId
                    );
            }

            if ($action === 'save') {
                $notice =
                    'Newsletter draft saved.';
            } elseif (
                $action === 'schedule'
            ) {
                $localSendAt = trim(
                    (string) (
                        $_POST['send_at']
                        ?? ''
                    )
                );

                if ($localSendAt === '') {
                    throw new InvalidArgumentException(
                        'Choose a send date and time.'
                    );
                }

                $mountain =
                    new DateTimeZone(
                        'America/Denver'
                    );

                $utc =
                    new DateTimeZone('UTC');

                $localDate =
                    new DateTimeImmutable(
                        $localSendAt,
                        $mountain
                    );

                $sendAtUtc =
                    $localDate
                        ->setTimezone($utc)
                        ->format(
                            'Y-m-d H:i:s'
                        );

                llama_newsletter_schedule(
                    $db,
                    $issueId,
                    $sendAtUtc
                );

                $notice =
                    'Newsletter scheduled.';
            } elseif (
                $action === 'send-now'
            ) {
                llama_newsletter_schedule(
                    $db,
                    $issueId,
                    gmdate('Y-m-d H:i:s')
                );

                $issue =
                    llama_newsletter_issue(
                        $db,
                        $issueId
                    );

                if ($issue) {
                    $stats =
                        llama_newsletter_send_batch(
                            $db,
                            $issue,
                            5
                        );

                    $notice =
                        'Newsletter sending started. '
                        . number_format(
                            (int) $stats['sent']
                        )
                        . ' sent in this pass. Remaining recipients will continue through automated maintenance.';
                }
            } elseif (
                $action === 'unschedule'
            ) {
                llama_newsletter_unschedule(
                    $db,
                    $issueId
                );

                $notice =
                    'Newsletter returned to draft.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.newsletters',
                    [
                        'newsletter_id' =>
                            $issueId,
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The newsletter could not be updated.',
                        $reference
                    );
        }
    }
}

$editIssue =
    $issueId > 0
        ? llama_newsletter_issue(
            $db,
            $issueId
        )
        : null;

$issues =
    llama_newsletter_issues(
        $db,
        100
    );

$monthlyAudience =
    llama_newsletter_audience_count(
        $db,
        'monthly'
    );

$memberAudience =
    llama_newsletter_audience_count(
        $db,
        'member_dispatch'
    );

$stats =
    admin_dashboard_stats($db);

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
    'Newsletters';

$adminPageEyebrow =
    'Communications';

$adminActiveNav =
    'newsletters';

require __DIR__ . '/_header.php';

$editStatus =
    strtolower(
        trim(
            (string) (
                $editIssue['status']
                ?? 'draft'
            )
        )
    );

$editLocked =
    in_array(
        $editStatus,
        ['sending', 'sent'],
        true
    );

$sendAtLocal = '';

if (
    $editIssue
    && !empty($editIssue['send_at'])
) {
    try {
        $utc =
            new DateTimeZone('UTC');

        $mountain =
            new DateTimeZone(
                'America/Denver'
            );

        $sendAtLocal =
            (
                new DateTimeImmutable(
                    (string) $editIssue[
                        'send_at'
                    ],
                    $utc
                )
            )
                ->setTimezone($mountain)
                ->format('Y-m-d\TH:i');
    } catch (Throwable) {
        $sendAtLocal = '';
    }
}
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


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Audience</p>
        <h2>Newsletter subscriptions</h2>
    </div>
</header>

<div class="admin-newsletter-audience-grid">

    <article>
        <i
            class="fa-solid fa-newspaper"
            aria-hidden="true"
        ></i>

        <strong>
            <?= number_format(
                $monthlyAudience
            ) ?>
        </strong>

        <span>
            Llama Scout Monthly
        </span>
    </article>

    <article>
        <i
            class="fa-solid fa-compass"
            aria-hidden="true"
        ></i>

        <strong>
            <?= number_format(
                $memberAudience
            ) ?>
        </strong>

        <span>
            Member Dispatch
        </span>
    </article>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>
            <?= $editIssue
                ? 'Edit issue'
                : 'New issue' ?>
        </p>

        <h2>
            <?= $editIssue
                ? moderation_e(
                    (string) $editIssue[
                        'title'
                    ]
                )
                : 'Compose newsletter' ?>
        </h2>
    </div>

    <?php if ($editIssue): ?>
        <a
            class="admin-button"
            href="/newsletters.php"
        >
            New issue
        </a>
    <?php endif; ?>
</header>

<form
    class="admin-newsletter-form"
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
    name="newsletter_id"
    value="<?= (int) (
        $editIssue['id']
        ?? 0
    ) ?>"
>

<div class="admin-newsletter-form-grid">

<label>
    <span>Newsletter</span>

    <select
        name="newsletter_type"
        <?= $editLocked
            ? 'disabled'
            : '' ?>
    >
        <?php foreach (
            llama_newsletter_types()
            as $typeKey => $typeLabel
        ): ?>
            <option
                value="<?= moderation_e(
                    $typeKey
                ) ?>"
                <?= (
                    (string) (
                        $editIssue[
                            'newsletter_type'
                        ]
                        ?? 'monthly'
                    )
                    === $typeKey
                )
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    $typeLabel
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Issue title</span>

    <input
        type="text"
        name="title"
        maxlength="180"
        value="<?= moderation_e(
            (string) (
                $editIssue['title']
                ?? ''
            )
        ) ?>"
        placeholder="September 2026"
        <?= $editLocked
            ? 'readonly'
            : '' ?>
        required
    >
</label>

</div>

<label>
    <span>Email subject</span>

    <input
        type="text"
        name="subject"
        maxlength="180"
        value="<?= moderation_e(
            (string) (
                $editIssue['subject']
                ?? ''
            )
        ) ?>"
        placeholder="This month around Llama Scout"
        <?= $editLocked
            ? 'readonly'
            : '' ?>
        required
    >
</label>

<label>
    <span>Newsletter content</span>

    <textarea
        name="body_text"
        rows="18"
        maxlength="30000"
        placeholder="Write the newsletter here. Separate paragraphs with a blank line."
        <?= $editLocked
            ? 'readonly'
            : '' ?>
        required
    ><?= moderation_e(
        (string) (
            $editIssue['body_text']
            ?? ''
        )
    ) ?></textarea>
</label>

<?php if (!$editLocked): ?>

<label>
    <span>
        Schedule
        <small>
            Mountain Time
        </small>
    </span>

    <input
        type="datetime-local"
        name="send_at"
        value="<?= moderation_e(
            $sendAtLocal
        ) ?>"
    >
</label>

<div class="admin-user-form-actions">

    <button
        class="admin-button"
        type="submit"
        name="newsletter_action"
        value="save"
    >
        Save draft
    </button>

    <button
        class="admin-button"
        type="submit"
        name="newsletter_action"
        value="schedule"
    >
        Schedule
    </button>

    <button
        class="admin-button"
        type="submit"
        name="newsletter_action"
        value="send-now"
    >
        Send now
    </button>

    <?php if (
        $editIssue
        && $editStatus === 'scheduled'
    ): ?>
        <button
            class="admin-button"
            type="submit"
            name="newsletter_action"
            value="unschedule"
        >
            Return to draft
        </button>
    <?php endif; ?>

</div>

<?php else: ?>

<div class="admin-user-notice">
    Sending has started for this issue, so its
    audience and content are locked.
</div>

<?php endif; ?>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Archive</p>
        <h2>Newsletter issues</h2>
    </div>
</header>

<?php if (!$issues): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-envelope-open-text"
        aria-hidden="true"
    ></i>

    <h3>No newsletters yet.</h3>
</div>

<?php else: ?>

<div class="admin-newsletter-table-wrap">

<table class="admin-newsletter-table">

<thead>
<tr>
    <th>Issue</th>
    <th>Newsletter</th>
    <th>Status</th>
    <th>Sent</th>
    <th>Failed</th>
    <th>Schedule</th>
    <th>
        <span class="sr-only">
            Action
        </span>
    </th>
</tr>
</thead>

<tbody>

<?php foreach ($issues as $issue): ?>

<tr>

<td data-label="Issue">
    <strong>
        <?= moderation_e(
            (string) $issue['title']
        ) ?>
    </strong>

    <small>
        <?= moderation_e(
            (string) $issue['subject']
        ) ?>
    </small>
</td>

<td data-label="Newsletter">
    <?= moderation_e(
        llama_newsletter_type_label(
            (string) $issue[
                'newsletter_type'
            ]
        )
    ) ?>
</td>

<td data-label="Status">
    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) $issue['status']
            )
        ) ?>
    </span>
</td>

<td data-label="Sent">
    <?= number_format(
        (int) (
            $issue['sent_delivery_count']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Failed">
    <?= number_format(
        (int) (
            $issue['failed_delivery_count']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Schedule">
    <span class="admin-table-muted">
        <?= moderation_e(
            (string) (
                $issue['send_at']
                ?: $issue['sent_at']
                ?: 'Draft'
            )
        ) ?>
    </span>
</td>

<td>
    <a
        class="admin-button"
        href="/newsletters.php?id=<?= (int) $issue['id'] ?>"
    >
        Manage
    </a>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<?php
require __DIR__ . '/_footer.php';
?>

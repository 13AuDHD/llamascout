<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mail.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Emails';
$adminPageEyebrow = 'Communications';
$adminActiveNav = 'emails';

$notice = '';
$error = '';

$schemaReady =
    llama_email_schema_ready($db);

if ($schemaReady) {
    llama_email_seed_defaults($db);
}

$selectedTemplateKey =
    trim(
        (string) (
            $_GET['template']
            ?? $_POST['template_key']
            ?? 'welcome'
        )
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
            $action =
                trim(
                    (string) (
                        $_POST['email_action']
                        ?? ''
                    )
                );

            $selectedTemplateKey =
                trim(
                    (string) (
                        $_POST['template_key']
                        ?? ''
                    )
                );

            if (!$schemaReady) {
                throw new RuntimeException(
                    'Run the Email Center database migration before saving or testing templates.'
                );
            }

            $defaults =
                llama_email_default_templates();

            if (
                !isset(
                    $defaults[
                        $selectedTemplateKey
                    ]
                )
            ) {
                throw new InvalidArgumentException(
                    'Unknown email template.'
                );
            }

            if ($action === 'save') {
                llama_email_save_template(
                    $db,
                    $actorUserId,
                    $selectedTemplateKey,
                    $_POST
                );

                $notice =
                    'Email template saved.';
            } elseif ($action === 'test') {
                $stored =
                    llama_email_template(
                        $db,
                        $selectedTemplateKey
                    );

                if (!$stored) {
                    throw new RuntimeException(
                        'Email template not found.'
                    );
                }

                $override =
                    array_merge(
                        $stored,
                        [
                            'subject' =>
                                trim(
                                    (string) (
                                        $_POST['subject']
                                        ?? ''
                                    )
                                ),
                            'preheader' =>
                                trim(
                                    (string) (
                                        $_POST['preheader']
                                        ?? ''
                                    )
                                ),
                            'html_body' =>
                                trim(
                                    (string) (
                                        $_POST['html_body']
                                        ?? ''
                                    )
                                ),
                            'text_body' =>
                                trim(
                                    (string) (
                                        $_POST['text_body']
                                        ?? ''
                                    )
                                ),
                            'enabled' =>
                                !empty(
                                    $_POST['is_enabled']
                                )
                                    ? 1
                                    : 0,
                        ]
                    );

                if (
                    $override['subject'] === ''
                    || $override['html_body'] === ''
                    || $override['text_body'] === ''
                ) {
                    throw new InvalidArgumentException(
                        'Subject, HTML body, and plain-text fallback are required before sending a test.'
                    );
                }

                $sent =
                    llama_email_send_template(
                        $db,
                        $selectedTemplateKey,
                        'dev@llamascout.com',
                        llama_email_sample_context(
                            $selectedTemplateKey
                        ),
                        true,
                        null,
                        $override
                    );

                if (!$sent) {
                    throw new RuntimeException(
                        'The test message could not be sent. Check the mail configuration and Error Log.'
                    );
                }

                $notice =
                    'Test email sent to dev@llamascout.com.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.emails',
                    [
                        'template_key' =>
                            $selectedTemplateKey,
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The email template could not be updated.',
                        $reference
                    );
        }
    }
}

$emailTemplates =
    llama_email_templates($db);

$selectedTemplate =
    llama_email_template(
        $db,
        $selectedTemplateKey
    );

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/emails.css"
>

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

<?php if (!$schemaReady): ?>
    <div class="admin-user-notice is-error">
        Email Center is running from built-in defaults only.
        Run <strong>database/email-center.sql</strong> before editing
        templates or sending Admin tests.
    </div>
<?php endif; ?>

<div class="email-center-layout">

    <?php require __DIR__ . '/_emails-list.php'; ?>

    <div class="email-center-editor-column">
        <?php require __DIR__ . '/_email-editor.php'; ?>
    </div>

</div>

<?php require __DIR__ . '/_footer.php'; ?>

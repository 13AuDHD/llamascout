<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mail.php';
require_once dirname(__DIR__) . '/app/timezone.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Email Activity';
$adminPageEyebrow = 'Communications';
$adminActiveNav = 'email-activity';

$schemaReady =
    llama_email_table_exists(
        $db,
        'email_send_log'
    );

$statusFilter =
    strtolower(
        trim(
            (string) (
                $_GET['status']
                ?? ''
            )
        )
    );

if (
    !in_array(
        $statusFilter,
        [
            '',
            'sent',
            'failed',
        ],
        true
    )
) {
    $statusFilter = '';
}

$kindFilter =
    strtolower(
        trim(
            (string) (
                $_GET['kind']
                ?? ''
            )
        )
    );

if (
    !in_array(
        $kindFilter,
        [
            '',
            'live',
            'test',
        ],
        true
    )
) {
    $kindFilter = '';
}

$templateFilter =
    trim(
        (string) (
            $_GET['template']
            ?? ''
        )
    );

$recipientFilter =
    trim(
        (string) (
            $_GET['recipient']
            ?? ''
        )
    );

$limit =
    (int) (
        $_GET['limit']
        ?? 100
    );

if (
    !in_array(
        $limit,
        [
            50,
            100,
            250,
        ],
        true
    )
) {
    $limit = 100;
}

$emailTemplates =
    llama_email_templates($db);

$templateNames = [];

foreach ($emailTemplates as $template) {
    $key =
        (string) (
            $template['template_key']
            ?? ''
        );

    if ($key === '') {
        continue;
    }

    $templateNames[$key] =
        (string) (
            $template['name']
            ?? $key
        );
}

$emailActivityRows = [];
$emailActivityTotals = [
    'total' => 0,
    'sent' => 0,
    'failed' => 0,
    'live' => 0,
    'test' => 0,
    'today' => 0,
];

$error = '';

if ($schemaReady) {
    try {
        $totalsStmt =
            $db->query(
                'SELECT
                    COUNT(*) AS total,
                    SUM(send_status = "sent") AS sent,
                    SUM(send_status = "failed") AS failed,
                    SUM(is_test = 0) AS live_count,
                    SUM(is_test = 1) AS test_count,
                    SUM(
                        sent_at >= UTC_DATE()
                        AND sent_at < DATE_ADD(
                            UTC_DATE(),
                            INTERVAL 1 DAY
                        )
                    ) AS today_count
                 FROM email_send_log'
            );

        $totals =
            $totalsStmt
                ? $totalsStmt->fetch(PDO::FETCH_ASSOC)
                : false;

        if ($totals) {
            $emailActivityTotals = [
                'total' =>
                    (int) (
                        $totals['total']
                        ?? 0
                    ),
                'sent' =>
                    (int) (
                        $totals['sent']
                        ?? 0
                    ),
                'failed' =>
                    (int) (
                        $totals['failed']
                        ?? 0
                    ),
                'live' =>
                    (int) (
                        $totals['live_count']
                        ?? 0
                    ),
                'test' =>
                    (int) (
                        $totals['test_count']
                        ?? 0
                    ),
                'today' =>
                    (int) (
                        $totals['today_count']
                        ?? 0
                    ),
            ];
        }

        $where = [];
        $params = [];

        if ($statusFilter !== '') {
            $where[] =
                'send_status = ?';
            $params[] =
                $statusFilter;
        }

        if ($kindFilter === 'live') {
            $where[] =
                'is_test = 0';
        } elseif ($kindFilter === 'test') {
            $where[] =
                'is_test = 1';
        }

        if ($templateFilter !== '') {
            $where[] =
                'template_key = ?';
            $params[] =
                $templateFilter;
        }

        if ($recipientFilter !== '') {
            $where[] =
                'recipient_email LIKE ?';
            $params[] =
                '%' . $recipientFilter . '%';
        }

        $sql =
            'SELECT
                id,
                template_key,
                recipient_email,
                user_id,
                is_test,
                send_status,
                sent_at
             FROM email_send_log';

        if ($where) {
            $sql .=
                ' WHERE '
                . implode(
                    ' AND ',
                    $where
                );
        }

        $sql .=
            ' ORDER BY id DESC'
            . ' LIMIT '
            . $limit;

        $activityStmt =
            $db->prepare($sql);

        $activityStmt->execute(
            $params
        );

        $emailActivityRows =
            $activityStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

    } catch (Throwable $exception) {
        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.email_activity'
            );

        $error =
            llama_error_message_with_reference(
                'Email activity could not be loaded.',
                $reference
            );
    }
}

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/email-activity.css"
>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<?php if (!$schemaReady): ?>
    <div class="admin-user-notice is-error">
        Email activity is unavailable until the Email Center database
        migration has been installed.
    </div>
<?php else: ?>

    <?php require __DIR__ . '/_email-activity.php'; ?>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

<?php

declare(strict_types=1);

$accountSupportEmail = strtolower(
    trim(
        (string) (
            $user['email']
            ?? ''
        )
    )
);

$accountSupportParams = [
    $userId,
];

$accountSupportWhere =
    'user_id = ?';

if ($accountSupportEmail !== '') {
    $accountSupportWhere .=
        ' OR (
            user_id IS NULL
            AND LOWER(email) = ?
        )';

    $accountSupportParams[] =
        $accountSupportEmail;
}

$accountSupportStmt = $db->prepare(
    'SELECT
        id,
        ticket_number,
        status,
        subject,
        created_at
     FROM support_requests
     WHERE (
        ' . $accountSupportWhere . '
     )
       AND status IN ("open", "waiting")
     ORDER BY created_at DESC, id DESC'
);

$accountSupportStmt->execute(
    $accountSupportParams
);

$accountSupportTickets =
    $accountSupportStmt->fetchAll(
        PDO::FETCH_ASSOC
    )
    ?: [];

$accountSupportCount =
    count($accountSupportTickets);

$accountLatestSupport =
    $accountSupportTickets[0]
    ?? null;

$accountSupportHeadline =
    'No open tickets';

$accountSupportDetail =
    'Contact & Support';

if ($accountLatestSupport) {
    $ticketNumber = trim(
        (string) (
            $accountLatestSupport['ticket_number']
            ?? ''
        )
    );

    $ticketStatus = strtolower(
        trim(
            (string) (
                $accountLatestSupport['status']
                ?? 'open'
            )
        )
    );

    $ticketStatusLabel = match (
        $ticketStatus
    ) {
        'waiting' => 'Waiting',
        default => 'Open',
    };

    $accountSupportHeadline =
        $ticketNumber !== ''
            ? 'Ticket #' . $ticketNumber
            : 'Support ticket';

    $accountSupportDetail =
        $ticketStatusLabel;

    if ($accountSupportCount > 1) {
        $accountSupportDetail .=
            ' Â· '
            . number_format(
                $accountSupportCount
            )
            . ' active tickets';
    }
}
?>

<a
    class="account-glance-card account-glance-link account-glance-support"
    href="<?= htmlspecialchars(
        $siteUrl . '/contact.php',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
    <span class="account-glance-icon">
        <i
            class="fa-solid fa-headset"
            aria-hidden="true"
        ></i>
    </span>

    <div>
        <strong>
            <?= htmlspecialchars(
                $accountSupportHeadline,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <span>
            <?= htmlspecialchars(
                $accountSupportDetail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>
    </div>

    <i
        class="fa-solid fa-chevron-right"
        aria-hidden="true"
    ></i>
</a>

<?php
require __DIR__
    . '/_email-preferences-dashboard-card.php';
?>

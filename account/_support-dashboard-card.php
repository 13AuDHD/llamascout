<?php

declare(strict_types=1);

$accountSupportEmail =
    strtolower(
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

$accountSupportStmt =
    $db->prepare(
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
    count(
        $accountSupportTickets
    );

$accountSupportDetail =
    $accountSupportCount === 0
        ? 'No open tickets. Contact Llama Scout or review support.'
        : number_format(
            $accountSupportCount
        )
        . ' active support ticket'
        . (
            $accountSupportCount === 1
                ? ''
                : 's'
        )
        . '.';
?>

<a
    class="account-action-card account-settings-support"
    href="<?= htmlspecialchars(
        $siteUrl . '/contact.php',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
    <i
        class="fa-solid fa-headset"
        aria-hidden="true"
    ></i>

    <span>
        <strong>
            Contact & support
        </strong>

        <small>
            <?= htmlspecialchars(
                $accountSupportDetail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </span>
</a>

<?php
require __DIR__
    . '/_email-preferences-dashboard-card.php';
?>

<?php

declare(strict_types=1);

$accountEmailPreferenceStmt =
    $db->prepare(
        'SELECT
            newsletter_email_enabled,
            member_dispatch_email_enabled,
            marketing_email_enabled
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

$accountEmailPreferenceStmt->execute([
    $userId,
]);

$accountEmailPreferenceState =
    $accountEmailPreferenceStmt->fetch(
        PDO::FETCH_ASSOC
    )
    ?: [];

$accountOptionalEmailCount =
    (int) !empty(
        $accountEmailPreferenceState[
            'newsletter_email_enabled'
        ]
    )
    + (int) !empty(
        $accountEmailPreferenceState[
            'member_dispatch_email_enabled'
        ]
    )
    + (int) !empty(
        $accountEmailPreferenceState[
            'marketing_email_enabled'
        ]
    );

$accountEmailPreferenceHeadline =
    $accountOptionalEmailCount === 0
        ? 'Essential email only'
        : 'Manage email preferences';

$accountEmailPreferenceDetail =
    $accountOptionalEmailCount === 0
        ? 'Optional email is off'
        : number_format(
            $accountOptionalEmailCount
        )
        . ' optional email '
        . (
            $accountOptionalEmailCount === 1
                ? 'subscription'
                : 'subscriptions'
        );
?>

<a
    class="account-glance-card account-glance-link account-glance-email"
    href="/email-preferences.php"
>
    <span class="account-glance-icon">
        <i
            class="fa-solid fa-envelope"
            aria-hidden="true"
        ></i>
    </span>

    <div>
        <strong>
            <?= htmlspecialchars(
                $accountEmailPreferenceHeadline,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <span>
            <?= htmlspecialchars(
                $accountEmailPreferenceDetail,
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

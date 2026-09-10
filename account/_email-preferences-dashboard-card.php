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

$accountEmailPreferenceDetail =
    $accountOptionalEmailCount === 0
        ? 'Optional email is off. Essential account email remains enabled while your email is verified.'
        : number_format(
            $accountOptionalEmailCount
        )
        . ' optional email '
        . (
            $accountOptionalEmailCount === 1
                ? 'subscription is'
                : 'subscriptions are'
        )
        . ' enabled.';
?>

<a
    class="account-action-card account-settings-email"
    href="/email-preferences.php"
>
    <i
        class="fa-solid fa-envelope"
        aria-hidden="true"
    ></i>

    <span>
        <strong>
            Email preferences
        </strong>

        <small>
            <?= htmlspecialchars(
                $accountEmailPreferenceDetail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </span>
</a>

<?php

declare(strict_types=1);

if (
    !isset(
        $db,
        $user,
        $userId,
        $actorUserId
    )
    || !is_array($user)
) {
    return;
}

if (
    !empty(
        $user['anonymized_at']
    )
) {
    return;
}

$verificationNotice =
    (string) (
        $_SESSION[
            'admin_user_verification_notice'
        ]
        ?? ''
    );

$verificationWarning =
    (string) (
        $_SESSION[
            'admin_user_verification_warning'
        ]
        ?? ''
    );

$verificationError =
    (string) (
        $_SESSION[
            'admin_user_verification_error'
        ]
        ?? ''
    );

unset(
    $_SESSION[
        'admin_user_verification_notice'
    ],
    $_SESSION[
        'admin_user_verification_warning'
    ],
    $_SESSION[
        'admin_user_verification_error'
    ]
);

$isVerified =
    !empty(
        $user['email_verified_at']
    );
?>

<section
    class="admin-panel admin-user-email-verification-panel"
    data-user-email-verification-panel
>

    <header class="admin-panel-header">
        <div>
            <p>Email Verification</p>

            <h2>
                Verification Status
            </h2>
        </div>

        <span
            class="admin-user-email-verification-status <?= $isVerified ? 'is-verified' : 'is-unverified' ?>"
        >
            <i
                class="fa-solid <?= $isVerified ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"
                aria-hidden="true"
            ></i>

            <?= $isVerified
                ? 'Verified'
                : 'Not verified' ?>
        </span>
    </header>


    <?php if (
        $verificationNotice !== ''
    ): ?>
        <div class="admin-user-email-verification-message is-success">
            <?= moderation_e(
                $verificationNotice
            ) ?>
        </div>
    <?php endif; ?>


    <?php if (
        $verificationWarning !== ''
    ): ?>
        <div class="admin-user-email-verification-message">
            <?= moderation_e(
                $verificationWarning
            ) ?>
        </div>
    <?php endif; ?>


    <?php if (
        $verificationError !== ''
    ): ?>
        <div class="admin-user-email-verification-message is-error">
            <?= moderation_e(
                $verificationError
            ) ?>
        </div>
    <?php endif; ?>


    <div class="admin-user-email-verification-body">

        <div>
            <span>Email address</span>

            <strong>
                <?= moderation_e(
                    (string) (
                        $user['email']
                        ?? ''
                    )
                ) ?>
            </strong>
        </div>

        <p>
            Use this when you need the account to prove ownership of its current email address again.
        </p>


        <?php if (
            $userId
            !== $actorUserId
        ): ?>

            <form
                method="post"
                action="/user-email-verification-action.php"
                class="admin-user-email-verification-form"
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
                    name="user_id"
                    value="<?= (int) $userId ?>"
                >

                <button
                    class="admin-button <?= $isVerified ? 'is-secondary' : '' ?>"
                    type="submit"
                >
                    <i
                        class="fa-solid fa-envelope-circle-check"
                        aria-hidden="true"
                    ></i>

                    <?= $isVerified
                        ? 'Unverify + Send Verification'
                        : 'Send Fresh Verification Email' ?>
                </button>

            </form>

        <?php else: ?>

            <small>
                This control is unavailable for your own Admin account.
            </small>

        <?php endif; ?>

    </div>

</section>

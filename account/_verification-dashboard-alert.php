<?php

declare(strict_types=1);

$dashboardVerificationStmt =
    $db->prepare(
        'SELECT
            email,
            email_verified_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

$dashboardVerificationStmt->execute([
    $userId,
]);

$dashboardVerification =
    $dashboardVerificationStmt->fetch(
        PDO::FETCH_ASSOC
    )
    ?: [];

$dashboardEmailVerified =
    !empty(
        $dashboardVerification[
            'email_verified_at'
        ]
    );

$dashboardEmail =
    trim(
        (string) (
            $dashboardVerification['email']
            ?? ''
        )
    );
?>

<?php if (!$dashboardEmailVerified): ?>

    <section
        class="account-email-verification-banner"
        role="alert"
    >
        <span class="account-email-verification-banner-icon">
            <i
                class="fa-solid fa-envelope-circle-check"
                aria-hidden="true"
            ></i>
        </span>

        <div class="account-email-verification-banner-copy">
            <p class="account-eyebrow">
                Action required
            </p>

            <h2>
                Verify your email address
            </h2>

            <p>
                Email delivery and normal Llama Scout features are paused
                until
                <strong>
                    <?= htmlspecialchars(
                        $dashboardEmail,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
                is verified again.
            </p>

            <div class="account-email-verification-banner-actions">
                <a
                    class="place-save-button"
                    href="/account-information.php#email-address"
                >
                    <i
                        class="fa-solid fa-pen"
                        aria-hidden="true"
                    ></i>

                    Correct Email Address
                </a>

                <a
                    class="place-save-button"
                    href="/resend-verification.php"
                >
                    <i
                        class="fa-solid fa-paper-plane"
                        aria-hidden="true"
                    ></i>

                    Resend Verification
                </a>
            </div>
        </div>
    </section>

<?php endif; ?>

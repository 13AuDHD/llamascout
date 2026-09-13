<?php

declare(strict_types=1);
?>

<section
    class="account-section"
    aria-labelledby="account-settings-heading"
>
    <div class="account-section-heading">
        <div>
            <p class="account-eyebrow">
                Account & settings
            </p>

            <h2 id="account-settings-heading">
                Manage your account
            </h2>
        </div>
    </div>

    <div class="account-action-grid account-settings-grid">

        <a
            class="account-action-card"
            href="/account-information.php"
        >
            <i
                class="fa-solid fa-address-card"
                aria-hidden="true"
            ></i>

            <span>
                <strong>Account information</strong>

                <small>
                    Public identity, private contact details, address,
                    time zone, email, and Support PIN.
                </small>
            </span>
        </a>

        <a
            class="account-action-card"
            href="/billing.php"
        >
            <i
                class="fa-solid fa-credit-card"
                aria-hidden="true"
            ></i>

            <span>
                <strong>
                    Billing & membership
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $membershipLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>.
                    View access, membership plans, and billing options.
                </small>
            </span>
        </a>

        <?php
        require __DIR__
            . '/_orders-dashboard-card.php';
        ?>

        <a
            class="account-action-card"
            href="/security.php"
        >
            <i
                class="fa-solid fa-shield-halved"
                aria-hidden="true"
            ></i>

            <span>
                <strong>Password &amp; security</strong>

                <small>
                    Change your password, manage multi-factor
                    authentication, recovery codes, and security.
                </small>
            </span>
        </a>

        <?php if (
            llama_passkey_account_is_owner(
                db(),
                (int) ($user['id'] ?? 0)
            )
        ): ?>
            <a
                class="account-action-card"
                href="/passkeys.php"
            >
                <i
                    class="fa-solid fa-fingerprint"
                    aria-hidden="true"
                ></i>

                <span>
                    <strong>Passkeys</strong>

                    <small>
                        Add and manage passkeys for faster Owner sign-in
                        on Apple and Android devices.
                    </small>
                </span>
            </a>
        <?php endif; ?>

        <a
            class="account-action-card account-action-card-danger"
            href="/delete-account.php"
        >
            <i
                class="fa-solid fa-user-slash"
                aria-hidden="true"
            ></i>

            <span>
                <strong>
                    Delete or anonymize account
                </strong>

                <small>
                    Permanently close your account.
                    Published contribution history may remain anonymously.
                </small>
            </span>
        </a>

    </div>
</section>

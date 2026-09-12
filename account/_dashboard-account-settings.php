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
                <strong>Edit account information</strong>

                <small>
                    Display name, username, phone number,
                    email address, and verification.
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
                    Change your password, set up multi-factor
                    authentication, and manage recovery codes.
                </small>
            </span>
        </a>


        <a
            class="account-action-card"
            href="/support-pin.php"
        >
            <i
                class="fa-solid fa-phone-volume"
                aria-hidden="true"
            ></i>

            <span>
                <strong>Support PIN</strong>

                <small>
                    Set up phone support verification and a one-time
                    emergency MFA reset.
                </small>
            </span>
        </a>


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

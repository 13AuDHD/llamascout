<?php

declare(strict_types=1);

$accountEmailPreferenceStmt =
    $db->prepare(
        'SELECT
            email,
            email_verified_at,
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

$accountInformationEmail =
    trim(
        (string) (
            $accountEmailPreferenceState['email']
            ?? ''
        )
    );

$accountInformationVerified =
    !empty(
        $accountEmailPreferenceState[
            'email_verified_at'
        ]
    );
?>


<?php if (!$accountInformationVerified): ?>

    <div
        class="account-email-verification-alert"
        role="alert"
        style="
            grid-column:1 / -1;
            padding:16px;
            border:1px solid var(--border);
            border-radius:12px;
            background:var(--surface);
        "
    >
        <div
            style="
                display:grid;
                grid-template-columns:38px minmax(0,1fr);
                gap:12px;
                align-items:start;
            "
        >
            <span
                class="account-glance-icon"
                style="margin:0;"
            >
                <i
                    class="fa-solid fa-envelope-circle-check"
                    aria-hidden="true"
                ></i>
            </span>

            <div>
                <strong
                    style="
                        display:block;
                        margin-bottom:5px;
                    "
                >
                    Verify your email address
                </strong>

                <span
                    style="
                        display:block;
                        color:var(--text-muted);
                        font-size:.72rem;
                        line-height:1.5;
                    "
                >
                    Email delivery and normal Llama Scout features are paused
                    until <?= htmlspecialchars(
                        $accountInformationEmail,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?> is verified again.
                </span>

                <div
                    style="
                        display:flex;
                        flex-wrap:wrap;
                        gap:8px;
                        margin-top:11px;
                    "
                >
                    <a
                        class="place-save-button"
                        href="/account-information.php#email-address"
                        style="width:fit-content;"
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
                        style="width:fit-content;"
                    >
                        <i
                            class="fa-solid fa-paper-plane"
                            aria-hidden="true"
                        ></i>

                        Resend Verification
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>


<a
    class="account-glance-card account-glance-link account-glance-account-info"
    href="/account-information.php"
>
    <span class="account-glance-icon">
        <i
            class="fa-solid fa-address-card"
            aria-hidden="true"
        ></i>
    </span>

    <div>
        <strong>
            Account information
        </strong>

        <span>
            <?= htmlspecialchars(
                $accountInformationEmail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            ·
            <?= $accountInformationVerified
                ? 'Verified'
                : 'Verification required' ?>
        </span>
    </div>

    <i
        class="fa-solid fa-chevron-right"
        aria-hidden="true"
    ></i>
</a>


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

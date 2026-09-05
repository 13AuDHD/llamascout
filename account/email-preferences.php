<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$db = db();
$currentUser = current_user();

$token = strtolower(
    trim(
        (string) (
            $_GET['token']
            ?? $_POST['token']
            ?? ''
        )
    )
);

$notice = '';
$error = '';
$userRow = null;
$isSignedInPreferenceOwner = false;

if (
    is_array($currentUser)
    && (int) ($currentUser['id'] ?? 0) > 0
) {
    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            marketing_email_enabled,
            marketing_unsubscribed_at,
            newsletter_email_enabled,
            member_dispatch_email_enabled
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        (int) $currentUser['id'],
    ]);

    $userRow =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: null;

    $isSignedInPreferenceOwner =
        $userRow !== null;
} elseif (
    preg_match(
        '/^[a-f0-9]{64}$/',
        $token
    )
) {
    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            marketing_email_enabled,
            marketing_unsubscribed_at,
            newsletter_email_enabled,
            member_dispatch_email_enabled
         FROM users
         WHERE marketing_unsubscribe_token = ?
         LIMIT 1'
    );

    $stmt->execute([
        $token,
    ]);

    $userRow =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: null;
}

if (!$userRow) {
    http_response_code(404);

    $error =
        'This email preference link is not valid.';
}

$preferenceUserId =
    $userRow
        ? (int) $userRow['id']
        : 0;

$hasMemberDispatchAccess =
    $preferenceUserId > 0
    && user_has_member_access(
        $preferenceUserId
    );

if (
    $isSignedInPreferenceOwner
    && empty(
        $_SESSION[
            'email_preferences_csrf'
        ]
    )
) {
    $_SESSION[
        'email_preferences_csrf'
    ] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken =
    $isSignedInPreferenceOwner
        ? (string) (
            $_SESSION[
                'email_preferences_csrf'
            ]
            ?? ''
        )
        : '';

if (
    $userRow
    && ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
) {
    if ($isSignedInPreferenceOwner) {
        $submittedCsrf =
            (string) (
                $_POST['csrf_token']
                ?? ''
            );

        if (
            $csrfToken === ''
            || $submittedCsrf === ''
            || !hash_equals(
                $csrfToken,
                $submittedCsrf
            )
        ) {
            $error =
                'Your session token expired. Reload the page and try again.';
        }
    }

    if ($error === '') {
        $newsletterEnabled =
            isset(
                $_POST[
                    'newsletter_email_enabled'
                ]
            )
                ? 1
                : 0;

        $marketingEnabled =
            isset(
                $_POST[
                    'marketing_email_enabled'
                ]
            )
                ? 1
                : 0;

        $memberDispatchEnabled =
            !empty(
                $userRow[
                    'member_dispatch_email_enabled'
                ]
            )
                ? 1
                : 0;

        if ($hasMemberDispatchAccess) {
            $memberDispatchEnabled =
                isset(
                    $_POST[
                        'member_dispatch_email_enabled'
                    ]
                )
                    ? 1
                    : 0;
        }

        $stmt = $db->prepare(
            'UPDATE users
             SET
                newsletter_email_enabled = ?,
                member_dispatch_email_enabled = ?,
                marketing_email_enabled = ?,
                marketing_unsubscribed_at = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $newsletterEnabled,
            $memberDispatchEnabled,
            $marketingEnabled,
            $marketingEnabled
                ? null
                : gmdate(
                    'Y-m-d H:i:s'
                ),
            $preferenceUserId,
        ]);

        $userRow[
            'newsletter_email_enabled'
        ] = $newsletterEnabled;

        $userRow[
            'member_dispatch_email_enabled'
        ] = $memberDispatchEnabled;

        $userRow[
            'marketing_email_enabled'
        ] = $marketingEnabled;

        $userRow[
            'marketing_unsubscribed_at'
        ] = $marketingEnabled
            ? null
            : gmdate(
                'Y-m-d H:i:s'
            );

        $notice =
            'Your email preferences have been saved.';
    }
}

$pageTitle =
    'Email Preferences | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageDescription = '';

require dirname(__DIR__)
    . '/partials/header.php';
?>

<section class="email-preferences-page">

<div class="email-preferences-container">

<header class="email-preferences-header">
    <p class="account-eyebrow">
        Your inbox
    </p>

    <h1>Email preferences</h1>

    <p>
        Choose which optional Llama Scout emails
        you want to receive.
    </p>
</header>

<?php if ($notice !== ''): ?>
    <div
        class="email-preferences-notice"
        role="status"
    >
        <?= htmlspecialchars(
            $notice,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div
        class="email-preferences-notice is-error"
        role="alert"
    >
        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
<?php else: ?>

<form
    class="email-preferences-form"
    method="post"
>

    <?php if ($token !== ''): ?>
        <input
            type="hidden"
            name="token"
            value="<?= htmlspecialchars(
                $token,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
    <?php endif; ?>

    <?php if ($isSignedInPreferenceOwner): ?>
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
    <?php endif; ?>


    <article class="email-preference-card is-required">

        <div class="email-preference-copy">
            <div class="email-preference-heading">
                <span class="email-preference-icon">
                    <i
                        class="fa-solid fa-shield-halved"
                        aria-hidden="true"
                    ></i>
                </span>

                <div>
                    <h2>
                        Account &amp; service messages
                    </h2>

                    <span class="email-preference-required">
                        Required
                    </span>
                </div>
            </div>

            <p>
                Security alerts, password resets,
                account notices, membership and billing
                messages, receipts, Shop order updates,
                shipping and refund notices, support
                updates, and other messages needed to
                operate your account.
            </p>
        </div>

        <label
            class="email-preference-switch is-locked"
            aria-label="Account and service messages are always enabled"
        >
            <input
                type="checkbox"
                checked
                disabled
            >

            <span aria-hidden="true"></span>
        </label>

    </article>


    <article class="email-preference-card">

        <div class="email-preference-copy">
            <div class="email-preference-heading">
                <span class="email-preference-icon">
                    <i
                        class="fa-solid fa-newspaper"
                        aria-hidden="true"
                    ></i>
                </span>

                <div>
                    <h2>
                        Llama Scout Monthly
                    </h2>

                    <span>
                        Monthly
                    </span>
                </div>
            </div>

            <p>
                A monthly roundup of new Places,
                community activity, Field Guide
                highlights, site updates, and other
                things worth knowing around Llama Scout.
            </p>
        </div>

        <label
            class="email-preference-switch"
            aria-label="Llama Scout Monthly"
        >
            <input
                type="checkbox"
                name="newsletter_email_enabled"
                value="1"
                <?= !empty(
                    $userRow[
                        'newsletter_email_enabled'
                    ]
                )
                    ? 'checked'
                    : '' ?>
            >

            <span aria-hidden="true"></span>
        </label>

    </article>


    <article
        class="email-preference-card <?= !$hasMemberDispatchAccess ? 'is-locked' : '' ?>"
    >

        <div class="email-preference-copy">
            <div class="email-preference-heading">
                <span class="email-preference-icon">
                    <i
                        class="fa-solid fa-compass"
                        aria-hidden="true"
                    ></i>
                </span>

                <div>
                    <h2>
                        Member Dispatch
                    </h2>

                    <span>
                        Members
                    </span>
                </div>
            </div>

            <p>
                A member-only monthly dispatch with
                featured Places, seasonal planning
                information, sensory and access
                highlights, new Field Guides, and
                useful discoveries from around
                Llama Scout.
            </p>

            <?php if (!$hasMemberDispatchAccess): ?>
                <small>
                    Available with Llama Scout membership.
                    Your preference will become available
                    when your account has member access.
                </small>
            <?php endif; ?>
        </div>

        <label
            class="email-preference-switch <?= !$hasMemberDispatchAccess ? 'is-locked' : '' ?>"
            aria-label="Member Dispatch"
        >
            <input
                type="checkbox"
                name="member_dispatch_email_enabled"
                value="1"
                <?= !empty(
                    $userRow[
                        'member_dispatch_email_enabled'
                    ]
                )
                    ? 'checked'
                    : '' ?>
                <?= !$hasMemberDispatchAccess
                    ? 'disabled'
                    : '' ?>
            >

            <span aria-hidden="true"></span>
        </label>

    </article>


    <article class="email-preference-card">

        <div class="email-preference-copy">
            <div class="email-preference-heading">
                <span class="email-preference-icon">
                    <i
                        class="fa-solid fa-tags"
                        aria-hidden="true"
                    ></i>
                </span>

                <div>
                    <h2>
                        Offers &amp; promotions
                    </h2>

                    <span>
                        Optional
                    </span>
                </div>
            </div>

            <p>
                Membership offers, promotion codes,
                sales, Shop promotions, and other
                occasional Llama Scout offers.
            </p>
        </div>

        <label
            class="email-preference-switch"
            aria-label="Offers and promotions"
        >
            <input
                type="checkbox"
                name="marketing_email_enabled"
                value="1"
                <?= !empty(
                    $userRow[
                        'marketing_email_enabled'
                    ]
                )
                    ? 'checked'
                    : '' ?>
            >

            <span aria-hidden="true"></span>
        </label>

    </article>


    <div class="email-preferences-actions">

        <button
            class="public-home-button"
            type="submit"
        >
            Save email preferences
        </button>

        <?php if ($isSignedInPreferenceOwner): ?>
            <a
                href="/"
                class="email-preferences-back"
            >
                Back to account
            </a>
        <?php endif; ?>

    </div>

</form>

<?php endif; ?>

</div>

</section>

<?php
require dirname(__DIR__)
    . '/partials/footer.php';
?>

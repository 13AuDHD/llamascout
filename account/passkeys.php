<?php

declare(strict_types=1);

require_once
    dirname(__DIR__)
    . '/app/bootstrap.php';

require_once
    dirname(__DIR__)
    . '/app/account-security.php';

require_login();
require_verified_email();

$db =
    db();

$user =
    current_user();

$userId =
    (int) (
        $user['id']
        ?? 0
    );

if (
    $userId < 1
    ||
    !llama_passkey_account_is_owner(
        $db,
        $userId
    )
) {
    http_response_code(403);
    exit(
        'Owner access is required.'
    );
}

$libraryReady =
    llama_passkey_library_ready();

$mfaEnabled =
    llama_mfa_is_enabled(
        $userId,
        $db
    );

$credentials =
    $libraryReady
        ? llama_passkey_credentials(
            $db,
            $userId
        )
        : [];

$pageTitle =
    'Passkeys | Llama Scout';

$pageStyles = [
    'account/pages/passkeys.css',
];

require
    dirname(__DIR__)
    . '/partials/header.php';
?>

<section class="passkeys-page">

    <header class="passkeys-header">
        <p class="eyebrow">
            Account security
        </p>

        <h1>Passkeys</h1>

        <p>
            Add separate passkeys for the devices you use.
            A passkey will eventually let this Owner account sign in
            without entering a password or authenticator code.
        </p>
    </header>


    <div
        id="passkey-message"
        class="passkeys-message"
        hidden
        role="status"
    ></div>


    <section class="passkeys-card">

        <div class="passkeys-card-heading">
            <i
                class="fa-solid fa-fingerprint"
                aria-hidden="true"
            ></i>

            <div>
                <h2>Your passkeys</h2>

                <p>
                    Each device or password manager can have its own
                    credential for this same account.
                </p>
            </div>
        </div>


        <?php if (!$libraryReady): ?>

            <div class="passkeys-warning">
                The server-side passkey engine must be installed once
                before a passkey can be created.
            </div>

            <a
                class="passkeys-button"
                href="https://admin.llamascout.com/passkey-library-install.php"
            >
                Install from Basecamp
            </a>

        <?php elseif (!$mfaEnabled): ?>

            <div class="passkeys-warning">
                TOTP must remain enabled as your fallback before a passkey
                can be added.
            </div>

            <a
                class="passkeys-button"
                href="/security.php"
            >
                Set up MFA
            </a>

        <?php else: ?>

            <?php if (!$credentials): ?>

                <div class="passkeys-empty">
                    <strong>No passkeys yet.</strong>
                    <span>
                        Add your first one below. You can come back on
                        Android or an Apple device and add another.
                    </span>
                </div>

            <?php else: ?>

                <div class="passkeys-list">

                    <?php foreach ($credentials as $credential): ?>
                        <article class="passkeys-list-item">

                            <div>
                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $credential['label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <span>
                                    Added
                                    <?= htmlspecialchars(
                                        llama_format_viewer_datetime(
                                            (string) $credential['created_at']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span>
                                    Last used:
                                    <?= !empty($credential['last_used_at'])
                                        ? htmlspecialchars(
                                            llama_format_viewer_datetime(
                                                (string) $credential['last_used_at']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : 'Never' ?>
                                </span>
                            </div>

                            <?php if (!empty($credential['backup_state'])): ?>
                                <span class="passkeys-sync-pill">
                                    Synced
                                </span>
                            <?php endif; ?>

                        </article>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <div class="passkeys-add">

                <h3>Add a passkey</h3>

                <p>
                    Enter a name for this credential and a fresh TOTP code.
                    Your device will then ask you to create the passkey.
                </p>

                <form
                    id="passkey-add-form"
                    class="passkeys-form"
                    autocomplete="off"
                >
                    <input
                        id="passkey-csrf"
                        type="hidden"
                        value="<?= htmlspecialchars(
                            llama_account_security_csrf_token(),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <label>
                        <span>Passkey name</span>

                        <input
                            id="passkey-label"
                            type="text"
                            maxlength="60"
                            placeholder="Apple devices, Android, Pixel..."
                            required
                        >
                    </label>

                    <label>
                        <span>Fresh authenticator code</span>

                        <input
                            id="passkey-totp"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                        >
                    </label>

                    <button
                        id="passkey-add-button"
                        type="submit"
                    >
                        Add Passkey
                    </button>
                </form>

            </div>

        <?php endif; ?>

    </section>


    <section class="passkeys-card is-note">

        <div class="passkeys-card-heading">
            <i
                class="fa-solid fa-shield-halved"
                aria-hidden="true"
            ></i>

            <div>
                <h2>Fallback stays available</h2>

                <p>
                    Adding a passkey does not remove your password, TOTP,
                    recovery codes, or Support PIN. Those remain available
                    if your devices are lost.
                </p>
            </div>
        </div>

    </section>


    <p class="passkeys-back">
        <a href="/">
            <i
                class="fa-solid fa-arrow-left"
                aria-hidden="true"
            ></i>
            Return to My Account
        </a>
    </p>

</section>


<?php if ($libraryReady && $mfaEnabled): ?>
<script>
(() => {
    'use strict';

    const form = document.getElementById('passkey-add-form');
    const button = document.getElementById('passkey-add-button');
    const message = document.getElementById('passkey-message');

    if (!form || !button || !message) {
        return;
    }

    const showMessage = (text, isError = false) => {
        message.hidden = false;
        message.textContent = text;
        message.classList.toggle('is-error', isError);
        message.classList.toggle('is-success', !isError);
    };

    const base64urlToBytes = (value) => {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);

        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }

        return bytes;
    };

    const bytesToBase64url = (value) => {
        const bytes = new Uint8Array(value);
        let binary = '';

        for (const byte of bytes) {
            binary += String.fromCharCode(byte);
        }

        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/g, '');
    };

    const parseCreationOptions = (json) => {
        if (
            typeof PublicKeyCredential !== 'undefined'
            && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
        ) {
            return PublicKeyCredential.parseCreationOptionsFromJSON(json);
        }

        const copy = structuredClone(json);

        copy.challenge = base64urlToBytes(copy.challenge);
        copy.user.id = base64urlToBytes(copy.user.id);

        if (Array.isArray(copy.excludeCredentials)) {
            copy.excludeCredentials = copy.excludeCredentials.map((item) => ({
                ...item,
                id: base64urlToBytes(item.id),
            }));
        }

        return copy;
    };

    const credentialToJson = (credential) => {
        if (typeof credential.toJSON === 'function') {
            return credential.toJSON();
        }

        const response = credential.response;

        return {
            id: credential.id,
            rawId: bytesToBase64url(credential.rawId),
            type: credential.type,
            authenticatorAttachment: credential.authenticatorAttachment ?? null,
            clientExtensionResults: credential.getClientExtensionResults(),
            response: {
                clientDataJSON: bytesToBase64url(response.clientDataJSON),
                attestationObject: bytesToBase64url(response.attestationObject),
                transports: typeof response.getTransports === 'function'
                    ? response.getTransports()
                    : [],
            },
        };
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (
            !window.PublicKeyCredential
            || !navigator.credentials
        ) {
            showMessage(
                'This browser does not support passkeys.',
                true
            );
            return;
        }

        const label = document.getElementById('passkey-label').value.trim();
        const totp = document.getElementById('passkey-totp').value.trim();
        const csrf = document.getElementById('passkey-csrf').value;

        button.disabled = true;
        button.textContent = 'Preparing...';
        message.hidden = true;

        try {
            const optionsResponse = await fetch(
                '/passkey-register-options.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        label,
                        totp_code: totp,
                    }),
                }
            );

            const optionsText = await optionsResponse.text();
            let optionsData;

            try {
                optionsData = JSON.parse(optionsText);
            } catch {
                throw new Error('The server returned an invalid passkey response.');
            }

            if (!optionsResponse.ok) {
                throw new Error(
                    optionsData.message
                    || 'Passkey registration could not begin.'
                );
            }

            button.textContent = 'Waiting for device...';

            const credential = await navigator.credentials.create({
                publicKey: parseCreationOptions(optionsData),
            });

            if (!credential) {
                throw new Error('Passkey creation was cancelled.');
            }

            button.textContent = 'Saving...';

            const verifyResponse = await fetch(
                '/passkey-register-verify.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(
                        credentialToJson(credential)
                    ),
                }
            );

            const verifyData = await verifyResponse.json();

            if (!verifyResponse.ok || !verifyData.ok) {
                throw new Error(
                    verifyData.message
                    || 'The passkey could not be saved.'
                );
            }

            showMessage(
                'Passkey added. Reloading...'
            );

            window.setTimeout(() => {
                window.location.reload();
            }, 500);

        } catch (error) {
            let text = 'Passkey registration did not complete.';

            if (
                error
                && typeof error.message === 'string'
                && error.message !== ''
            ) {
                text = error.message;
            }

            if (
                error
                && error.name === 'NotAllowedError'
            ) {
                text = 'Passkey creation was cancelled or timed out.';
            }

            showMessage(
                text,
                true
            );

            button.disabled = false;
            button.textContent = 'Add Passkey';
        }
    });
})();
</script>
<?php endif; ?>

<?php
require
    dirname(__DIR__)
    . '/partials/footer.php';

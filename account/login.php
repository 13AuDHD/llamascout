<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   LOGIN
   account/login.php

   Password flow:
   - Turnstile
   - Password
   - MFA when required/enabled

   Passkey flow:
   - WebAuthn user verification
   - Successful passkey = strong authentication
   - No separate TOTP challenge after a valid passkey
   ========================================================= */


require_once
    dirname(__DIR__)
    . '/app/auth.php';

require_once
    dirname(__DIR__)
    . '/app/mfa.php';

require_once
    dirname(__DIR__)
    . '/app/passkeys.php';

require_once
    dirname(__DIR__)
    . '/app/passkey-library.php';


start_llama_session();


$returnUrl =
    llama_safe_return_url(
        $_POST['return']
        ??
        $_GET['return']
        ??
        null
    );


$destination =
    $returnUrl
    ?:
    'https://account.llamascout.com/';


/* =========================================================
   EXISTING AUTHENTICATED SESSION
   ========================================================= */


$existingUser =
    current_user();


if ($existingUser) {
    $existingUserId =
        (int) $existingUser['id'];

    if (
        (
            llama_mfa_role_requires_mfa(
                $existingUserId
            )
            ||
            llama_mfa_is_enabled(
                $existingUserId
            )
        )
        &&
        !llama_mfa_session_is_verified(
            $existingUserId
        )
    ) {
        llama_mfa_begin_login_challenge(
            $existingUserId,
            false,
            $returnUrl
        );

        if (
            llama_mfa_is_enabled(
                $existingUserId
            )
        ) {
            header(
                'Location: /mfa-challenge.php'
            );
        } else {
            header(
                'Location: /mfa-setup.php'
            );
        }

        exit;
    }

    header(
        'Location: '
        . $destination
    );

    exit;
}


/* =========================================================
   TURNSTILE
   ========================================================= */


$config =
    llama_config();


$turnstileConfig =
    $config['turnstile']
    ?? [];


$turnstileSiteKey =
    trim(
        (string) (
            $turnstileConfig['site_key']
            ?? ''
        )
    );


$turnstileSecretKey =
    trim(
        (string) (
            $turnstileConfig['secret_key']
            ?? ''
        )
    );


function verify_turnstile(
    string $secretKey,
    string $token
): bool {
    if (
        $secretKey === ''
        ||
        $token === ''
    ) {
        return false;
    }

    $curl =
        curl_init(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        );

    if ($curl === false) {
        return false;
    }

    $fields = [
        'secret' =>
            $secretKey,
        'response' =>
            $token,
    ];

    $remoteIp =
        trim(
            (string) (
                $_SERVER['REMOTE_ADDR']
                ?? ''
            )
        );

    if ($remoteIp !== '') {
        $fields['remoteip'] =
            $remoteIp;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_POST =>
                true,
            CURLOPT_POSTFIELDS =>
                http_build_query(
                    $fields
                ),
            CURLOPT_RETURNTRANSFER =>
                true,
            CURLOPT_CONNECTTIMEOUT =>
                5,
            CURLOPT_TIMEOUT =>
                10,
            CURLOPT_HTTPHEADER =>
                [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
        ]
    );

    $response =
        curl_exec(
            $curl
        );

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

    curl_close(
        $curl
    );

    if (
        !is_string($response)
        ||
        $status !== 200
    ) {
        return false;
    }

    $result =
        json_decode(
            $response,
            true
        );

    return
        is_array($result)
        &&
        !empty(
            $result['success']
        );
}


/* =========================================================
   USER LOOKUP FOR PASSWORD + MFA ROUTING
   ========================================================= */


function login_find_user(
    string $login
): ?array {
    $login =
        strtolower(
            trim(
                $login
            )
        );

    if ($login === '') {
        return null;
    }

    $stmt =
        db()->prepare(
            '
            SELECT
                id,
                email,
                username,
                display_name,
                password_hash,
                status

            FROM users

            WHERE LOWER(email) = ?
               OR LOWER(username) = ?

            LIMIT 1
            '
        );

    $stmt->execute([
        $login,
        $login,
    ]);

    $user =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        is_array($user)
            ? $user
            : null;
}


/* =========================================================
   PASSWORD POST
   ========================================================= */


$error = '';
$login = '';
$remember = true;


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    $login =
        trim(
            (string) (
                $_POST['login']
                ?? ''
            )
        );

    $password =
        (string) (
            $_POST['password']
            ?? ''
        );

    $remember =
        isset(
            $_POST['remember']
        );

    $turnstileToken =
        trim(
            (string) (
                $_POST['cf-turnstile-response']
                ?? ''
            )
        );

    if (
        $turnstileSiteKey === ''
        ||
        $turnstileSecretKey === ''
    ) {
        error_log(
            'Llama Scout login Turnstile configuration is missing.'
        );

        $error =
            'Security verification is temporarily unavailable.';

    } elseif ($turnstileToken === '') {
        $error =
            'Security verification was not ready. Please try again.';

    } elseif (
        !verify_turnstile(
            $turnstileSecretKey,
            $turnstileToken
        )
    ) {
        error_log(
            'Llama Scout login blocked by Turnstile.'
        );

        $error =
            'Security verification failed. Please try again.';

    } elseif (
        $login === ''
        ||
        $password === ''
    ) {
        $error =
            'Enter your email or username and password.';

    } else {
        llama_mfa_clear_session_state();

        $candidate =
            login_find_user(
                $login
            );

        if (
            !$candidate
            ||
            !password_verify(
                $password,
                (string) (
                    $candidate['password_hash']
                    ?? ''
                )
            )
        ) {
            $error =
                'The email, username, or password is incorrect.';

        } else {
            $candidateStatus =
                (string) (
                    $candidate['status']
                    ?? ''
                );

            if (
                $candidateStatus ===
                'suspended'
            ) {
                $error =
                    'This account has been suspended. Please contact Llama Scout if you believe this is an error.';

            } elseif (
                $candidateStatus ===
                'disabled'
            ) {
                $error =
                    'This account is currently disabled. Please contact Llama Scout for assistance.';

            } else {
                $candidateUserId =
                    (int) $candidate['id'];

                if (
                    llama_mfa_role_requires_mfa(
                        $candidateUserId
                    )
                    ||
                    llama_mfa_is_enabled(
                        $candidateUserId
                    )
                ) {
                    llama_mfa_begin_login_challenge(
                        $candidateUserId,
                        $remember,
                        $returnUrl
                    );

                    llama_mfa_invalidate_remember_tokens(
                        $candidateUserId
                    );

                    if (
                        llama_mfa_is_enabled(
                            $candidateUserId
                        )
                    ) {
                        header(
                            'Location: /mfa-challenge.php'
                        );
                    } else {
                        header(
                            'Location: /mfa-setup.php'
                        );
                    }

                    exit;
                }

                $loginResult =
                    attempt_login_result(
                        $login,
                        $password,
                        $remember
                    );

                if (
                    $loginResult ===
                    'success'
                ) {
                    header(
                        'Location: '
                        . $destination
                    );

                    exit;
                }

                $error =
                    match ($loginResult) {
                        'suspended' =>
                            'This account has been suspended. Please contact Llama Scout if you believe this is an error.',
                        'disabled' =>
                            'This account is currently disabled. Please contact Llama Scout for assistance.',
                        default =>
                            'The email, username, or password is incorrect.',
                    };
            }
        }
    }
}


/* =========================================================
   OUTPUT
   ========================================================= */


function e(
    string $value
): string {
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$passkeyLoginAvailable =
    llama_passkey_library_ready();

?>
<!doctype html>

<html lang="en">

<head>

  <meta charset="utf-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1"
  >

  <title>
    Log In | Llama Scout
  </title>

  <meta
    name="description"
    content="Log in to your Llama Scout account."
  >

  <link
    rel="stylesheet"
    href="https://llamascout.com/css/site.css"
  >

  <link
    rel="stylesheet"
    href="https://llamascout.com/css/account/features/auth.css"
  >

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
  >

  <script
    src="https://llamascout.com/js/accessibility.js"
  ></script>


  <?php if (
      $turnstileSiteKey !== ''
  ): ?>

    <script>
      function llamaLoginButton() {
        return document.getElementById(
          'login-submit'
        );
      }

      function llamaTurnstileReady() {
        const button =
          llamaLoginButton();

        if (button) {
          button.disabled =
            false;

          button.removeAttribute(
            'aria-disabled'
          );
        }
      }

      function llamaTurnstileWaiting() {
        const button =
          llamaLoginButton();

        if (button) {
          button.disabled =
            true;

          button.setAttribute(
            'aria-disabled',
            'true'
          );
        }
      }

      function llamaTurnstileError() {
        llamaTurnstileWaiting();
      }
    </script>

    <script
      src="https://challenges.cloudflare.com/turnstile/v0/api.js"
      defer
    ></script>

  <?php endif; ?>

</head>


<body class="account-auth-body">


<main class="account-auth">


  <a href="https://llamascout.com">

    <img
      src="https://llamascout.com/images/logo.png"
      alt="Llama Scout"
      class="account-auth-logo"
    >

  </a>


  <section class="account-auth-card">


    <h1>
      Welcome back
    </h1>


    <p class="account-auth-intro">
      Log in to access your Llama Scout account,
      saved places, membership, and Scout activity.
    </p>


    <?php if ($error !== ''): ?>

      <div
        class="account-error"
        role="alert"
      >
        <?= e($error) ?>
      </div>

    <?php endif; ?>


    <?php if ($passkeyLoginAvailable): ?>

      <div class="account-passkey-login">

        <button
          id="passkey-login-button"
          type="button"
          class="account-passkey-button"
        >
          <i
            class="fa-solid fa-fingerprint"
            aria-hidden="true"
          ></i>

          Sign in with a passkey
        </button>

        <div
          id="passkey-login-message"
          class="account-passkey-message"
          role="status"
          hidden
        ></div>

      </div>


      <div class="account-auth-divider">
        <span>or use your password</span>
      </div>

    <?php endif; ?>


    <form method="post">


      <?php if (
          $returnUrl !== null
      ): ?>

        <input
          type="hidden"
          name="return"
          value="<?= e($returnUrl) ?>"
        >

      <?php endif; ?>


      <?php if (
          $turnstileSiteKey !== ''
      ): ?>

        <div class="account-login-security">

          <div
            class="cf-turnstile"
            data-sitekey="<?= e($turnstileSiteKey) ?>"
            data-theme="auto"
            data-callback="llamaTurnstileReady"
            data-expired-callback="llamaTurnstileWaiting"
            data-timeout-callback="llamaTurnstileWaiting"
            data-error-callback="llamaTurnstileError"
          ></div>

        </div>

      <?php endif; ?>


      <div class="account-field">

        <label for="login">
          Email or username
        </label>

        <input
          id="login"
          name="login"
          type="text"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          value="<?= e($login) ?>"
          required
        >

      </div>


      <div class="account-field">

        <label for="password">
          Password
        </label>

        <input
          id="password"
          name="password"
          type="password"
          autocomplete="current-password"
          required
        >

      </div>


      <label
        class="account-remember"
        for="remember"
      >

        <input
          id="remember"
          name="remember"
          type="checkbox"
          value="1"
          <?= $remember
              ? 'checked'
              : ''
          ?>
        >

        <span>
          Remember me for 30 days
        </span>

      </label>


      <a
        class="account-forgot"
        href="forgot-password.php"
      >
        Forgot your password?
      </a>


      <button
        id="login-submit"
        type="submit"
        class="account-submit"
        <?php if (
            $turnstileSiteKey !== ''
        ): ?>
          disabled
          aria-disabled="true"
        <?php endif; ?>
      >
        Log In
      </button>


    </form>


    <p class="account-auth-footer">

      New to Llama Scout?

      <a href="register.php">
        Create an account
      </a>

    </p>


  </section>


</main>


<?php if ($passkeyLoginAvailable): ?>

<script>
(() => {
    'use strict';

    const button =
        document.getElementById(
            'passkey-login-button'
        );

    const message =
        document.getElementById(
            'passkey-login-message'
        );

    if (!button || !message) {
        return;
    }

    const returnUrl =
        <?= json_encode(
            $returnUrl ?? '',
            JSON_UNESCAPED_SLASHES
        ) ?>;

    const showMessage = (
        text,
        isError = false
    ) => {
        message.hidden =
            false;

        message.textContent =
            text;

        message.classList.toggle(
            'is-error',
            isError
        );

        message.classList.toggle(
            'is-success',
            !isError
        );
    };

    const base64urlToBytes = (
        value
    ) => {
        const padding =
            '='.repeat(
                (4 - (value.length % 4)) % 4
            );

        const base64 =
            (value + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

        const binary =
            atob(
                base64
            );

        const bytes =
            new Uint8Array(
                binary.length
            );

        for (
            let index = 0;
            index < binary.length;
            index += 1
        ) {
            bytes[index] =
                binary.charCodeAt(
                    index
                );
        }

        return bytes;
    };

    const bytesToBase64url = (
        value
    ) => {
        const bytes =
            new Uint8Array(
                value
            );

        let binary =
            '';

        for (const byte of bytes) {
            binary +=
                String.fromCharCode(
                    byte
                );
        }

        return btoa(
            binary
        )
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/g, '');
    };

    const parseRequestOptions = (
        json
    ) => {
        if (
            typeof PublicKeyCredential !==
                'undefined'
            &&
            typeof PublicKeyCredential
                .parseRequestOptionsFromJSON ===
                'function'
        ) {
            return PublicKeyCredential
                .parseRequestOptionsFromJSON(
                    json
                );
        }

        const copy =
            structuredClone(
                json
            );

        copy.challenge =
            base64urlToBytes(
                copy.challenge
            );

        if (
            Array.isArray(
                copy.allowCredentials
            )
        ) {
            copy.allowCredentials =
                copy.allowCredentials.map(
                    (item) => ({
                        ...item,
                        id:
                            base64urlToBytes(
                                item.id
                            ),
                    })
                );
        }

        return copy;
    };

    const credentialToJson = (
        credential
    ) => {
        if (
            typeof credential.toJSON ===
            'function'
        ) {
            return credential.toJSON();
        }

        const response =
            credential.response;

        return {
            id:
                credential.id,
            rawId:
                bytesToBase64url(
                    credential.rawId
                ),
            type:
                credential.type,
            authenticatorAttachment:
                credential
                    .authenticatorAttachment
                ?? null,
            clientExtensionResults:
                credential
                    .getClientExtensionResults(),
            response: {
                clientDataJSON:
                    bytesToBase64url(
                        response.clientDataJSON
                    ),
                authenticatorData:
                    bytesToBase64url(
                        response.authenticatorData
                    ),
                signature:
                    bytesToBase64url(
                        response.signature
                    ),
                userHandle:
                    response.userHandle
                        ? bytesToBase64url(
                            response.userHandle
                        )
                        : null,
            },
        };
    };

    button.addEventListener(
        'click',
        async () => {
            if (
                !window.PublicKeyCredential
                ||
                !navigator.credentials
            ) {
                showMessage(
                    'This browser does not support passkeys.',
                    true
                );

                return;
            }

            button.disabled =
                true;

            button.textContent =
                'Preparing passkey...';

            message.hidden =
                true;

            try {
                const optionsResponse =
                    await fetch(
                        '/passkey-login-options.php',
                        {
                            method:
                                'POST',
                            credentials:
                                'same-origin',
                            headers: {
                                'Content-Type':
                                    'application/json',
                            },
                            body:
                                '{}',
                        }
                    );

                const optionsData =
                    await optionsResponse.json();

                if (!optionsResponse.ok) {
                    throw new Error(
                        optionsData.message
                        ||
                        'Passkey sign-in could not begin.'
                    );
                }

                button.textContent =
                    'Waiting for passkey...';

                const credential =
                    await navigator.credentials.get({
                        publicKey:
                            parseRequestOptions(
                                optionsData
                            ),
                    });

                if (!credential) {
                    throw new Error(
                        'Passkey sign-in was cancelled.'
                    );
                }

                button.textContent =
                    'Signing in...';

                let verifyUrl =
                    '/passkey-login-verify.php';

                if (returnUrl) {
                    verifyUrl +=
                        '?return='
                        +
                        encodeURIComponent(
                            returnUrl
                        );
                }

                const verifyResponse =
                    await fetch(
                        verifyUrl,
                        {
                            method:
                                'POST',
                            credentials:
                                'same-origin',
                            headers: {
                                'Content-Type':
                                    'application/json',
                            },
                            body:
                                JSON.stringify(
                                    credentialToJson(
                                        credential
                                    )
                                ),
                        }
                    );

                const verifyData =
                    await verifyResponse.json();

                if (
                    !verifyResponse.ok
                    ||
                    !verifyData.ok
                ) {
                    throw new Error(
                        verifyData.message
                        ||
                        'Passkey sign-in failed.'
                    );
                }

                showMessage(
                    'Passkey verified. Signing in...'
                );

                window.location.assign(
                    verifyData.destination
                    ||
                    'https://account.llamascout.com/'
                );

            } catch (error) {
                let text =
                    'Passkey sign-in did not complete.';

                if (
                    error
                    &&
                    typeof error.message ===
                        'string'
                    &&
                    error.message !== ''
                ) {
                    text =
                        error.message;
                }

                if (
                    error
                    &&
                    error.name ===
                        'NotAllowedError'
                ) {
                    text =
                        'Passkey sign-in was cancelled or timed out.';
                }

                showMessage(
                    text,
                    true
                );

                button.disabled =
                    false;

                button.innerHTML =
                    '<i class="fa-solid fa-fingerprint" aria-hidden="true"></i> Sign in with a passkey';
            }
        }
    );
})();
</script>

<?php endif; ?>


</body>

</html>

<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/support.php';

$db = db();
$user = current_user();

$userId =
    (int) (
        $user['id']
        ?? 0
    );

$pageTitle = 'Contact & Support | Llama Scout';
$pageDescription = 'Contact Llama Scout for account, membership, Shop order, place information, accessibility, privacy, technical problems, or general support.';
$canonicalUrl = 'https://llamascout.com/contact.php';

$config = llama_config();

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


function llama_support_public_verify_turnstile(
    string $secretKey,
    string $token
): bool {
    if (
        $secretKey === ''
        || $token === ''
    ) {
        return false;
    }

    $curl = curl_init(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify'
    );

    if ($curl === false) {
        return false;
    }

    $fields = [
        'secret' => $secretKey,
        'response' => $token,
    ];

    $remoteIp = trim(
        (string) (
            $_SERVER['REMOTE_ADDR']
            ?? ''
        )
    );

    if ($remoteIp !== '') {
        $fields['remoteip'] = $remoteIp;
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]
    );

    $response = curl_exec($curl);

    $status = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if (
        !is_string($response)
        || $status !== 200
    ) {
        return false;
    }

    $result = json_decode(
        $response,
        true
    );

    return is_array($result)
        && !empty($result['success']);
}


$errorReference =
    llama_support_normalize_error_reference(
        (string) (
            $_POST['error_reference']
            ?? $_GET['error']
            ?? ''
        )
    );

$name = trim(
    (string) (
        $_POST['name']
        ?? $user['display_name']
        ?? $user['username']
        ?? ''
    )
);

$email = trim(
    (string) (
        $_POST['email']
        ?? $user['email']
        ?? ''
    )
);

$accountPhone =
    $userId > 0
        ? llama_support_phone_number(
            $db,
            $userId
        )
        : null;

$phoneNumber =
    llama_support_format_phone(
        (string) (
            $_POST['phone_number']
            ?? $accountPhone
            ?? ''
        )
    );

$preferredContact =
    trim(
        (string) (
            $_POST['preferred_contact']
            ?? 'email'
        )
    );

if (
    !isset(
        llama_support_contact_methods()[
            $preferredContact
        ]
    )
) {
    $preferredContact = 'email';
}

$supportPinSet =
    $userId > 0
        ? llama_support_pin_is_set(
            $db,
            $userId
        )
        : false;

$defaultCategory =
    $errorReference !== null
        ? 'technical'
        : 'general';

$category = trim(
    (string) (
        $_POST['category']
        ?? $defaultCategory
    )
);

$defaultSubject =
    $errorReference !== null
        ? 'Report site error ' . $errorReference
        : '';

$subject = trim(
    (string) (
        $_POST['subject']
        ?? $defaultSubject
    )
);

$orderNumber = trim(
    (string) (
        $_POST['order_number']
        ?? ''
    )
);

$message = trim(
    (string) (
        $_POST['message']
        ?? ''
    )
);

$error = '';
$success = '';
$requestId = 0;
$ticketNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !llama_support_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload the page and try again.';
    } else {
        $honeypot = trim(
            (string) (
                $_POST['website']
                ?? ''
            )
        );

        $turnstileToken = trim(
            (string) (
                $_POST['cf-turnstile-response']
                ?? ''
            )
        );

        if ($honeypot !== '') {
            $error =
                'Your support request could not be submitted.';

        } elseif (
            $turnstileSiteKey === ''
            || $turnstileSecretKey === ''
        ) {
            error_log(
                'Llama Scout support Turnstile configuration is missing.'
            );

            $error =
                'Security verification is temporarily unavailable.';

        } elseif ($turnstileToken === '') {
            $error =
                'Security verification was not ready. Please try again.';

        } elseif (
            !llama_support_public_verify_turnstile(
                $turnstileSecretKey,
                $turnstileToken
            )
        ) {
            error_log(
                'Llama Scout support form blocked by Turnstile.'
            );

            $error =
                'Security verification failed. Please try again.';

        } else {
            try {
                $requestId =
                    llama_support_create(
                        $db,
                        $_POST,
                        $user
                    );

                $ticketNumber =
                    llama_support_ticket_number(
                        $db,
                        $requestId
                    );

                $success =
                    'Your support ticket was sent. Ticket #'
                    . $ticketNumber
                    . '.';

                $subject = '';
                $orderNumber = '';
                $message = '';
                $errorReference = null;
                $category = 'general';
                $preferredContact = 'email';
                $phoneNumber =
                    llama_support_format_phone(
                        $accountPhone
                    );

            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();

            } catch (Throwable $exception) {
                $reference =
                    function_exists(
                        'llama_log_caught_exception'
                    )
                        ? llama_log_caught_exception(
                            $exception,
                            'support.public_submit'
                        )
                        : null;

                $error = $reference
                    ? 'Your request could not be submitted. Error reference '
                        . $reference
                        . '.'
                    : 'Your request could not be submitted.';
            }
        }
    }
}

$categories = llama_support_categories();
$contactMethods =
    llama_support_contact_methods();

require __DIR__ . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $siteUrl . '/css/support.css',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/contact-support.js',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    defer
></script>

<?php if ($turnstileSiteKey !== ''): ?>
<script
    src="https://challenges.cloudflare.com/turnstile/v0/api.js"
    async
    defer
></script>
<?php endif; ?>

<div class="legal-page">

<section class="legal-hero">
<div class="legal-container">

    <p class="eyebrow">Help</p>

    <div class="support-kb-entry">

        <div class="support-kb-entry-copy">

            <p class="support-kb-kicker">
                Got a question?
            </p>

            <h2>
                Search our Knowledge Base first.
            </h2>

            <p>
                It is the quickest way to find answers about
                accounts, memberships, Places, Scouts, orders,
                privacy, weather, and more. Apparently the llama
                has been taking very thorough notes.
            </p>

        </div>

        <a
            class="support-kb-button"
            href="/help.php"
        >
            <i aria-hidden="true">
                <?= llama_icon('search') ?>
            </i>

            <span>Search Knowledge Base</span>
        </a>

    </div>

    <h1>Contact &amp; Support</h1>

    <p class="legal-lede">
        Still need help? Send us a message about your account,
        membership, an order, a Place, accessibility, privacy,
        a technical problem, or Llama Scout in general.
    </p>

</div>
</section>


<section class="legal-content">
<div class="legal-container">

<section class="legal-section">

<?php if ($success !== ''): ?>
    <div class="support-message is-success">
        <strong>Ticket created</strong>
        <p><?= htmlspecialchars(
            $success,
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="support-message is-error">
        <strong>Could not send message</strong>
        <p><?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
    </div>
<?php endif; ?>

<?php if ($errorReference !== null): ?>
    <div class="support-message">
        <strong>
            Reporting <?= htmlspecialchars(
                $errorReference,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
        <p>
            This ticket will be linked to the Llama Scout
            error reference above. Describe what you were
            doing when the error appeared and anything you
            noticed immediately before it happened.
        </p>
    </div>
<?php endif; ?>

<form method="post" class="support-form">

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        llama_support_csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<div class="support-honeypot" aria-hidden="true">
    <label for="support-website">
        Website
    </label>
    <input
        id="support-website"
        type="text"
        name="website"
        tabindex="-1"
        autocomplete="off"
    >
</div>

<?php if ($errorReference !== null): ?>
<input
    type="hidden"
    name="error_reference"
    value="<?= htmlspecialchars(
        $errorReference,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>
<?php endif; ?>

<div class="support-form-grid">

<label>
    <span>Name</span>
    <input
        type="text"
        name="name"
        maxlength="150"
        autocomplete="name"
        required
        value="<?= htmlspecialchars(
            $name,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
</label>

<label>
    <span>Email</span>
    <input
        type="email"
        name="email"
        maxlength="255"
        autocomplete="email"
        required
        value="<?= htmlspecialchars(
            $email,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
</label>

<label>
    <span>Phone number</span>
    <input
        type="tel"
        name="phone_number"
        maxlength="32"
        autocomplete="tel"
        inputmode="tel"
        placeholder="(970) 555-1212"
        value="<?= htmlspecialchars(
            $phoneNumber,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        data-support-phone
        aria-describedby="support-phone-help"
    >
    <small id="support-phone-help" data-support-phone-help>
        Optional for email replies. Required if you prefer a text or phone call.
    </small>
</label>

<label>
    <span>Preferred Contact</span>
    <select
        name="preferred_contact"
        required
        data-support-preferred-contact
    >
        <?php foreach (
            $contactMethods
            as $key => $label
        ): ?>
            <option
                value="<?= htmlspecialchars(
                    $key,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                <?= $preferredContact === $key
                    ? 'selected'
                    : '' ?>
            >
                <?= htmlspecialchars(
                    match ($key) {
                        'email' => 'Email (Fastest)',
                        'phone' => 'Phone Call (Slowest)',
                        default => $label,
                    },
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <small>
        Choosing Text or Phone Call gives Llama Scout permission
        to contact you about this support request at the number above.
    </small>
</label>

<div class="support-pin-note">
    <i aria-hidden="true">
        <?= llama_icon('shield') ?>
    </i>

    <div>
        <?php if ($userId > 0): ?>
            <strong>
                Support PIN:
                <?= $supportPinSet
                    ? 'Configured'
                    : 'Not configured' ?>
            </strong>

            <?php if ($supportPinSet): ?>
                <p>
                    If phone verification is needed, Support may ask for
                    your private 8-digit Support PIN during the call.
                    Never enter your Support PIN in this form.
                    <a
                        href="https://account.llamascout.com/account-information.php#support-pin"
                    >Manage Support PIN</a>.
                </p>
            <?php else: ?>
                <p>
                    A Support PIN lets Support verify your identity during
                    a phone call. Never enter a Support PIN in this form.
                    <a
                        href="https://account.llamascout.com/account-information.php#support-pin"
                    >Create a Support PIN in Account Information</a>.
                </p>
            <?php endif; ?>
        <?php else: ?>
            <strong>Support PIN</strong>
            <p>
                Signed-in members can create a private 8-digit Support PIN
                for identity verification during phone support. Never enter
                a Support PIN in this form.
            </p>
        <?php endif; ?>
    </div>
</div>

<label>
    <span>What do you need help with?</span>
    <select name="category" required>
        <?php foreach (
            $categories
            as $key => $label
        ): ?>
            <option
                value="<?= htmlspecialchars(
                    $key,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                <?= $category === $key
                    ? 'selected'
                    : '' ?>
            >
                <?= htmlspecialchars(
                    $label,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Order number, if applicable</span>
    <input
        type="text"
        name="order_number"
        maxlength="100"
        placeholder="Example: LS-20260905-ABC123"
        value="<?= htmlspecialchars(
            $orderNumber,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
</label>

</div>

<label>
    <span>Subject</span>
    <input
        type="text"
        name="subject"
        maxlength="180"
        required
        value="<?= htmlspecialchars(
            $subject,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
</label>

<label>
    <span>Message</span>
    <textarea
        name="message"
        rows="8"
        maxlength="10000"
        required
    ><?= htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    ) ?></textarea>
</label>

<p class="support-form-note">
    Do not send passwords, MFA codes, Support PINs, complete payment-card
    numbers, or other secret login credentials.
</p>

<div class="support-form-actions">

    <div class="support-turnstile">
        <?php if ($turnstileSiteKey !== ''): ?>
            <div
                class="cf-turnstile"
                data-sitekey="<?= htmlspecialchars(
                    $turnstileSiteKey,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                data-theme="auto"
            ></div>
        <?php else: ?>
            <span class="support-security-unavailable">
                Security verification unavailable
            </span>
        <?php endif; ?>
    </div>

    <button type="submit" class="button">
        Create support ticket
    </button>

</div>

</form>

</section>


<section class="legal-section">

<h2>Order help</h2>

<p>
    If your question involves Shop merchandise, include the
    Llama Scout order number. For a damaged, defective, or
    incorrect item, keep photos of the item and packaging in
    case they are needed to resolve the request.
</p>

<p>
    Review the
    <a href="/returns.php">Returns &amp; Refunds Policy</a>
    for merchandise return and refund information.
</p>

</section>


<section class="legal-section">

<h2>Account security</h2>

<p>
    Llama Scout will never ask you to send your password,
    MFA authentication code, recovery code, Support PIN,
    complete payment card number, or card security code
    through this form.
</p>

</section>

</div>
</section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/support.php';

$db = db();
$user = current_user();

$pageTitle = 'Contact & Support | Llama Scout';
$pageDescription = 'Contact Llama Scout for account, membership, Shop order, place information, accessibility, privacy, or general support.';
$canonicalUrl = 'https://llamascout.com/contact.php';

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

$category = trim(
    (string) (
        $_POST['category']
        ?? 'general'
    )
);

$subject = trim(
    (string) (
        $_POST['subject']
        ?? ''
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
        try {
            $requestId =
                llama_support_create(
                    $db,
                    $_POST,
                    $user
                );

            $success =
                'Your support request was sent. Reference #'
                . $requestId
                . '.';

            $subject = '';
            $orderNumber = '';
            $message = '';

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
                ? 'Your request could not be submitted. Reference '
                    . $reference
                    . '.'
                : 'Your request could not be submitted.';
        }
    }
}

$categories = llama_support_categories();

require __DIR__ . '/partials/header.php';
?>

<div class="legal-page">

<section class="legal-hero">
<div class="legal-container">

    <p class="eyebrow">Help</p>

    <h1>Contact &amp; Support</h1>

    <p class="legal-lede">
        Questions about your account, membership, an order,
        a place listing, accessibility, privacy, or Llama Scout
        in general can be sent here.
    </p>

</div>
</section>


<section class="legal-content">
<div class="legal-container">

<section class="legal-section">

<?php if ($success !== ''): ?>
    <div class="support-message is-success">
        <strong>Message received</strong>
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
    Do not send passwords, MFA codes, complete payment-card
    numbers, or other secret login credentials.
</p>

<button type="submit" class="button">
    Send support request
</button>

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
    MFA authentication code, recovery code, complete payment
    card number, or card security code through this form.
</p>

</section>

</div>
</section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

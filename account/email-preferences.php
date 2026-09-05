<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$db = db();
$token = strtolower(trim((string) ($_GET['token'] ?? $_POST['token'] ?? '')));
$notice = '';
$error = '';
$userRow = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            marketing_email_enabled,
            marketing_unsubscribed_at
         FROM users
         WHERE marketing_unsubscribe_token = ?
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$userRow) {
    http_response_code(404);
    $error = 'This email preference link is not valid.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $enabled = isset($_POST['marketing_email_enabled']) ? 1 : 0;

    $stmt = $db->prepare(
        'UPDATE users
         SET
            marketing_email_enabled = ?,
            marketing_unsubscribed_at = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $enabled,
        $enabled ? null : gmdate('Y-m-d H:i:s'),
        (int) $userRow['id'],
    ]);

    $userRow['marketing_email_enabled'] = $enabled;
    $userRow['marketing_unsubscribed_at'] = $enabled
        ? null
        : gmdate('Y-m-d H:i:s');

    $notice = $enabled
        ? 'Promotional email is enabled.'
        : 'You have been unsubscribed from promotional email.';
}

$pageTitle = 'Email Preferences | Llama Scout';
$pageRobots = 'noindex,nofollow';
$pageDescription = '';

require dirname(__DIR__) . '/partials/header.php';
?>

<section class="legal-page">
<div class="legal-container">

<header class="legal-header">
    <p class="eyebrow">Llama Scout</p>
    <h1>Email preferences</h1>
</header>

<?php if ($notice !== ''): ?>
    <div class="legal-callout">
        <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="legal-callout">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php else: ?>

<section class="legal-section">
    <h2>Promotional email</h2>

    <p>
        Control membership offers, sales, and other promotional
        email from Llama Scout. Account, security, purchase, and
        other service-related messages are not affected.
    </p>

    <form method="post">
        <input
            type="hidden"
            name="token"
            value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label style="display:flex;align-items:flex-start;gap:10px;margin:22px 0;">
            <input
                type="checkbox"
                name="marketing_email_enabled"
                value="1"
                <?= !empty($userRow['marketing_email_enabled']) ? 'checked' : '' ?>
            >
            <span>Send me Llama Scout promotional email.</span>
        </label>

        <button class="public-home-button" type="submit">
            Save email preference
        </button>
    </form>
</section>

<?php endif; ?>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

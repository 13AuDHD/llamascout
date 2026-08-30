<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (auth_check()) {
    header('Location: /');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT
                    id,
                    email,
                    password_hash,
                    status,
                    email_verified_at
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'The email address or password is incorrect.';
            } elseif ($user['status'] !== 'active') {
                $error = 'This account is not currently active.';
            } elseif ($user['email_verified_at'] === null) {
                $error = 'Please verify your email address before signing in.';
            } else {
                auth_login((int) $user['id']);

                $update = db()->prepare(
                    'UPDATE users
                     SET last_login_at = NOW()
                     WHERE id = ?'
                );

                $update->execute([(int) $user['id']]);

                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Unable to sign in right now.';
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | Llama Scout</title>
</head>
<body>

<main>
    <h1>Sign in</h1>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <div>
            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="email"
                required
            >
        </div>

        <div>
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">Sign in</button>
    </form>
</main>

</body>
</html>

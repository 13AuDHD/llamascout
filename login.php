<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (auth_check()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Enter your email address or username and password.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT
                    id,
                    email,
                    username,
                    password_hash,
                    status,
                    email_verified_at
                 FROM users
                 WHERE email = :identifier
                    OR username = :identifier
                 LIMIT 1'
            );

            $stmt->execute([
                'identifier' => $identifier,
            ]);

            $user = $stmt->fetch();

            if (
                !$user ||
                !password_verify($password, (string) $user['password_hash'])
            ) {
                $error = 'The email address, username, or password is incorrect.';
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

                header('Location: /index.php');
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
            <label for="identifier">Email address or username</label>

            <input
                type="text"
                id="identifier"
                name="identifier"
                value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="username"
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

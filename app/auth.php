<?php

declare(strict_types=1);

function auth_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            timezone,
            status,
            email_verified_at,
            last_login_at,
            membership_status,
            membership_interval,
            membership_started_at,
            membership_ends_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([(int) $userId]);

    $row = $stmt->fetch();

    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }

    if ($row['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $row;

    return $user;
}


function auth_check(): bool
{
    return auth_user() !== null;
}


function auth_id(): ?int
{
    $user = auth_user();

    return $user ? (int) $user['id'] : null;
}


function auth_login(int $userId): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
}


function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}


function auth_require_login(): void
{
    if (auth_check()) {
        return;
    }

    header('Location: /login.php');
    exit;
}


function auth_roles(?int $userId = null): array
{
    $userId ??= auth_id();

    if (!$userId) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT r.slug
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.id'
    );

    $stmt->execute([$userId]);

    return array_column($stmt->fetchAll(), 'slug');
}


function auth_has_role(string $role): bool
{
    return in_array($role, auth_roles(), true);
}

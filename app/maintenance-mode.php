<?php

declare(strict_types=1);

function llama_site_setting(
    PDO $db,
    string $key,
    ?string $default = null
): ?string {
    $stmt = $db->prepare(
        'SELECT setting_value
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false
        ? $default
        : (string) $value;
}

function llama_site_setting_bool(
    PDO $db,
    string $key,
    bool $default = false
): bool {
    $value = llama_site_setting(
        $db,
        $key,
        $default ? '1' : '0'
    );

    return in_array(
        strtolower(trim((string) $value)),
        ['1', 'true', 'yes', 'on'],
        true
    );
}

function llama_set_site_setting(
    PDO $db,
    string $key,
    ?string $value
): void {
    $stmt = $db->prepare(
        'INSERT INTO site_settings (
            setting_key,
            setting_value
         ) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)'
    );

    $stmt->execute([
        $key,
        $value,
    ]);
}

function llama_maintenance_state(
    PDO $db
): array {
    return [
        'enabled' => llama_site_setting_bool(
            $db,
            'maintenance_enabled',
            false
        ),

        'message' => llama_site_setting(
            $db,
            'maintenance_message',
            'Llama Scout is getting a few upgrades.'
        ),

        'return_at' => llama_site_setting(
            $db,
            'maintenance_return_at',
            ''
        ),

        'public_enabled' => llama_site_setting_bool(
            $db,
            'maintenance_public_enabled',
            true
        ),

        'account_enabled' => llama_site_setting_bool(
            $db,
            'maintenance_account_enabled',
            false
        ),

        'api_enabled' => llama_site_setting_bool(
            $db,
            'maintenance_api_enabled',
            false
        ),

        'started_at' => llama_site_setting(
            $db,
            'maintenance_started_at',
            ''
        ),

        'started_by' => (int) llama_site_setting(
            $db,
            'maintenance_started_by',
            '0'
        ),
    ];
}

function llama_maintenance_request_scope(): string
{
    $host = strtolower(
        trim(
            explode(
                ':',
                (string) ($_SERVER['HTTP_HOST'] ?? '')
            )[0]
        )
    );

    if ($host === 'admin.llamascout.com') {
        return 'admin';
    }

    if ($host === 'account.llamascout.com') {
        return 'account';
    }

    if ($host === 'api.llamascout.com') {
        return 'api';
    }

    return 'public';
}

function llama_maintenance_admin_bypass(): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    $userId = (int) ($user['id'] ?? 0);

    if ($userId < 1) {
        return false;
    }

    return
        user_has_role('owner', $userId)
        || user_has_role('admin', $userId);
}

function llama_maintenance_should_block(
    PDO $db
): bool {
    $state = llama_maintenance_state($db);

    if (!$state['enabled']) {
        return false;
    }

    $scope = llama_maintenance_request_scope();

    if ($scope === 'admin') {
        return false;
    }

    if (llama_maintenance_admin_bypass()) {
        return false;
    }

    return match ($scope) {
        'account' => (bool) $state['account_enabled'],
        'api' => (bool) $state['api_enabled'],
        default => (bool) $state['public_enabled'],
    };
}

function llama_render_maintenance(
    PDO $db
): never {
    $state = llama_maintenance_state($db);

    http_response_code(503);

    if (!headers_sent()) {
        header('Retry-After: 900');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: text/html; charset=UTF-8');
    }

    $message = htmlspecialchars(
        trim((string) $state['message']) !== ''
            ? (string) $state['message']
            : 'Llama Scout is getting a few upgrades.',
        ENT_QUOTES,
        'UTF-8'
    );

    $returnAt = trim(
        (string) $state['return_at']
    );

    $returnMarkup = '';

    if ($returnAt !== '') {
        $returnMarkup =
            '<p class="maintenance-return">Expected back: ' .
            htmlspecialchars(
                $returnAt,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '</p>';
    }

    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Maintenance | Llama Scout</title>
<style>
:root{color-scheme:dark}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:28px;background:#171717;color:#f4f4f4;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.maintenance{width:min(620px,100%);text-align:center}
.maintenance img{display:block;width:min(260px,70vw);height:auto;margin:0 auto 30px}
.maintenance-card{padding:30px;border:1px solid #3c3c3c;border-radius:18px;background:#222}
h1{margin:0 0 12px;font-size:clamp(2rem,7vw,4rem);line-height:1}
p{margin:0;color:#c8c8c8;line-height:1.65}
.maintenance-return{margin-top:18px;font-size:.9rem;color:#aaa}
.llama-note{margin-top:22px;font-size:.82rem;color:#8f8f8f}
</style>
</head>
<body>
<main class="maintenance">
<img src="https://llamascout.com/images/logo.png" alt="Llama Scout">
<div class="maintenance-card">
<h1>We are brushing the llama.</h1>
<p>' . $message . '</p>
' . $returnMarkup . '
<p class="llama-note">The llama did not eat the server. Probably.</p>
</div>
</main>
</body>
</html>';

    exit;
}

function llama_enforce_maintenance(
    PDO $db
): void {
    if (llama_maintenance_should_block($db)) {
        llama_render_maintenance($db);
    }
}

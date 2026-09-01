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
    PDO $db,
    bool $preview = false
): never {
    $state = llama_maintenance_state($db);

    if (!$preview) {
        http_response_code(503);
    }

    if (!headers_sent()) {
        if (!$preview) {
            header('Retry-After: 900');
        }

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        header(
            'Content-Type: text/html; charset=UTF-8'
        );
    }

    $message =
        trim((string) $state['message']);

    if ($message === '') {
        $message =
            'The llama is under the hood.';
    }

    $returnAt =
        trim((string) $state['return_at']);

    $messageEscaped =
        htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        );

    $returnAtEscaped =
        htmlspecialchars(
            $returnAt,
            ENT_QUOTES,
            'UTF-8'
        );

    echo '<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<meta
    name="robots"
    content="noindex,nofollow,noarchive"
>

<title>
    Llama Scout is getting a tune-up
</title>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/maintenance.css"
>

</head>

<body class="maintenance-body">

<main class="maintenance-page">

<section class="maintenance-card">

<div class="maintenance-illustration">

<img
    src="https://llamascout.com/images/maintenance-llama.png"
    alt="The Llama Scout mascot working under the hood of an off-road vehicle"
>

</div>

<p class="maintenance-eyebrow">
    Tiny detour
</p>

<h1>
    The llama is under the hood.
</h1>

<p class="maintenance-message">' .
    $messageEscaped .
'</p>

<p class="maintenance-copy">
    We are loosening things, tightening things,
    repairing things, and pretending we know
    where that extra screw came from.
</p>';

    if ($returnAt !== '') {
        echo '
<div
    class="maintenance-countdown"
    data-maintenance-countdown
    data-return-at="' . $returnAtEscaped . '"
>

<p class="maintenance-countdown-label">
    We should be back in
</p>

<div class="maintenance-countdown-grid">

<div class="maintenance-countdown-unit">
<strong data-countdown-days>00</strong>
<span>days</span>
</div>

<div class="maintenance-countdown-unit">
<strong data-countdown-hours>00</strong>
<span>hours</span>
</div>

<div class="maintenance-countdown-unit">
<strong data-countdown-minutes>00</strong>
<span>minutes</span>
</div>

<div class="maintenance-countdown-unit">
<strong data-countdown-seconds>00</strong>
<span>seconds</span>
</div>

</div>

<p
    class="maintenance-countdown-finished"
    data-countdown-finished
    hidden
>
    Any minute now. The llama says they are almost done.
</p>

</div>';
    } else {
        echo '
<p
    class="maintenance-no-time"
    data-maintenance-no-time
>
    We will be back as soon as the llama stops touching things.
</p>';
    }

    echo '
<p class="maintenance-footnote">
    No llamas were harmed during this maintenance.
    Their dignity is another matter.
</p>

</section>

</main>

<script src="https://llamascout.com/js/maintenance.js"></script>

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

<?php

declare(strict_types=1);

/*
 * Llama Scout safety/error landing page.
 *
 * Deliberately standalone: do not bootstrap the application here. If the
 * database, auth layer, or application bootstrap is what failed, this page
 * still needs to render successfully.
 */

$reason = strtolower(trim((string) ($_GET['reason'] ?? '')));

$pages = [
    'permission' => [
        'status' => 403,
        'eyebrow' => 'Access restricted',
        'title' => 'That trail is not open to you.',
        'message' => 'You do not have permission to access that part of Llama Scout. If you think you should have access, sign in with the correct account or contact us with what you were trying to open.',
        'icon' => '🔒',
    ],
    '401' => [
        'status' => 401,
        'eyebrow' => 'Sign-in required',
        'title' => 'You need to sign in first.',
        'message' => 'This part of Llama Scout requires an authenticated account.',
        'icon' => '🔑',
    ],
    '403' => [
        'status' => 403,
        'eyebrow' => 'Access restricted',
        'title' => 'This area is off limits.',
        'message' => 'The server understood the request, but access to this resource is not allowed.',
        'icon' => '⛔',
    ],
    '404' => [
        'status' => 404,
        'eyebrow' => '404 · Not found',
        'title' => 'Looks like this trail disappeared.',
        'message' => 'The page you were looking for does not exist, was moved, or the address is incorrect.',
        'icon' => '🧭',
    ],
    '410' => [
        'status' => 410,
        'eyebrow' => '410 · Gone',
        'title' => 'This one has been taken off the map.',
        'message' => 'This resource used to exist, but it has been intentionally removed.',
        'icon' => '🗺️',
    ],
    '429' => [
        'status' => 429,
        'eyebrow' => '429 · Slow down',
        'title' => 'Too many requests hit the trail at once.',
        'message' => 'Please wait a little bit and try again. This protects Llama Scout from automated abuse and accidental request loops.',
        'icon' => '⏱️',
    ],
    '500' => [
        'status' => 500,
        'eyebrow' => '500 · Server error',
        'title' => 'Something broke under the hood.',
        'message' => 'Llama Scout could not complete this request. If you received an LS error reference, include it when reporting the problem.',
        'icon' => '🛠️',
    ],
    '503' => [
        'status' => 503,
        'eyebrow' => '503 · Temporarily unavailable',
        'title' => 'Llama Scout is temporarily unavailable.',
        'message' => 'The site is unavailable for the moment. Try again shortly.',
        'icon' => '🚧',
    ],
    '400' => [
        'status' => 400,
        'eyebrow' => '400 · Bad request',
        'title' => 'That request went off trail.',
        'message' => 'The server could not understand the request that was sent.',
        'icon' => '🧭',
    ],
];

if (!isset($pages[$reason])) {
    $redirectStatus = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);
    $derived = (string) $redirectStatus;
    $reason = isset($pages[$derived]) ? $derived : '404';
}

$page = $pages[$reason];
$status = (int) $page['status'];

http_response_code($status);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$requested = (string) ($_SERVER['HTTP_X_ORIGINAL_URI'] ?? $_SERVER['REQUEST_URI'] ?? '');
if (str_contains($requested, '/safety.php')) {
    $requested = '';
}

function safety_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= safety_e((string) $page['eyebrow']) ?> · Llama Scout</title>
    <link rel="icon" href="/images/logo.png">
    <style>
        :root {
            color-scheme: dark;
            --bg: #111312;
            --panel: #1b1e1c;
            --panel-2: #222623;
            --line: rgba(255,255,255,.12);
            --text: #f4f5f4;
            --muted: #a9b0ab;
            --accent: #e7a85d;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            background:
                radial-gradient(circle at top, rgba(231,168,93,.08), transparent 34rem),
                var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell { width: min(720px, 100%); }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
        }
        .brand img {
            width: min(220px, 58vw);
            max-height: 84px;
            object-fit: contain;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 24px;
            background: linear-gradient(180deg, var(--panel-2), var(--panel));
            padding: clamp(28px, 6vw, 52px);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
            text-align: center;
        }
        .icon { font-size: 38px; line-height: 1; margin-bottom: 18px; }
        .eyebrow {
            margin: 0 0 9px;
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            font-size: clamp(32px, 7vw, 52px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .message {
            max-width: 570px;
            margin: 20px auto 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.6;
        }
        .requested {
            margin: 22px auto 0;
            padding: 12px 14px;
            max-width: 100%;
            overflow-wrap: anywhere;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(0,0,0,.17);
            color: var(--muted);
            font-size: 13px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .actions {
            margin-top: 30px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: transparent;
            color: var(--text);
            font-weight: 750;
            text-decoration: none;
        }
        .button.primary {
            background: var(--text);
            border-color: var(--text);
            color: #101210;
        }
        .footer {
            margin: 18px 0 0;
            color: #767d78;
            text-align: center;
            font-size: 13px;
        }
        @media (prefers-color-scheme: light) {
            :root {
                color-scheme: light;
                --bg: #f3f4f1;
                --panel: #ffffff;
                --panel-2: #ffffff;
                --line: rgba(18,24,20,.13);
                --text: #151915;
                --muted: #626a64;
                --accent: #9a5b18;
            }
            .card { box-shadow: 0 24px 70px rgba(25,32,27,.09); }
            .requested { background: rgba(0,0,0,.025); }
            .button.primary { color: #fff; background: #151915; border-color: #151915; }
        }
    </style>
</head>
<body>
<main class="shell">
    <a class="brand" href="https://llamascout.com/" aria-label="Llama Scout home">
        <img src="https://llamascout.com/images/logo.png" alt="Llama Scout">
    </a>

    <section class="card">
        <div class="icon" aria-hidden="true"><?= safety_e((string) $page['icon']) ?></div>
        <p class="eyebrow"><?= safety_e((string) $page['eyebrow']) ?></p>
        <h1><?= safety_e((string) $page['title']) ?></h1>
        <p class="message"><?= safety_e((string) $page['message']) ?></p>

        <?php if ($requested !== ''): ?>
            <div class="requested"><?= safety_e($requested) ?></div>
        <?php endif; ?>

        <div class="actions">
            <a class="button primary" href="https://llamascout.com/">Go home</a>
            <a class="button" href="https://llamascout.com/map.php">Open the map</a>
            <?php if ($reason === 'permission' || $reason === '401' || $reason === '403'): ?>
                <a class="button" href="https://account.llamascout.com/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </section>

    <p class="footer">Know the place before you go.</p>
</main>
</body>
</html>

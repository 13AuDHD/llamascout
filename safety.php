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
        'message' => 'You do not have permission to access that part of Llama Scout. If you think you should have access, sign in with the correct account or report what you were trying to open.',
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
    '405' => [
        'status' => 405,
        'eyebrow' => '405 · Method not allowed',
        'title' => 'That action cannot be used here.',
        'message' => 'The page exists, but it does not accept the type of request that was sent.',
        'icon' => '🚫',
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

$requested =
    (string) (
        $_SERVER['HTTP_X_ORIGINAL_URI']
        ?? $_SERVER['REQUEST_URI']
        ?? ''
    );

if (str_contains($requested, '/safety.php')) {
    $requested = '';
}

/*
 * Never carry a query string into a support-report link. Erroring URLs can
 * contain reset tokens, checkout state, or other values that do not belong in
 * a support ticket URL.
 */
$requestedPath = '';

if ($requested !== '') {
    $parsedPath =
        parse_url(
            $requested,
            PHP_URL_PATH
        );

    if (is_string($parsedPath)) {
        $requestedPath =
            substr(
                $parsedPath,
                0,
                1000
            );
    }
}

$reportQuery = [
    'reason' => $reason,
];

if ($requestedPath !== '') {
    $reportQuery['path'] =
        $requestedPath;
}

$reportUrl =
    'https://llamascout.com/contact.php?'
    . http_build_query(
        $reportQuery,
        '',
        '&',
        PHP_QUERY_RFC3986
    );

function safety_e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="dark light">

    <title>
        <?= safety_e((string) $page['eyebrow']) ?> · Llama Scout
    </title>

    <link
        rel="icon"
        href="https://llamascout.com/images/logo.png"
    >

    <link
        rel="stylesheet"
        href="https://llamascout.com/css/safety.css"
    >
</head>

<body>

<main class="safety-shell">

    <a
        class="safety-brand"
        href="https://llamascout.com/"
        aria-label="Llama Scout home"
    >
        <img
            src="https://llamascout.com/images/logo.png"
            alt="Llama Scout"
        >
    </a>

    <section class="safety-card">

        <div
            class="safety-icon"
            aria-hidden="true"
        >
            <?= safety_e((string) $page['icon']) ?>
        </div>

        <p class="safety-eyebrow">
            <?= safety_e((string) $page['eyebrow']) ?>
        </p>

        <h1>
            <?= safety_e((string) $page['title']) ?>
        </h1>

        <p class="safety-message">
            <?= safety_e((string) $page['message']) ?>
        </p>

        <?php if ($requestedPath !== ''): ?>
            <div class="safety-requested">
                <?= safety_e($requestedPath) ?>
            </div>
        <?php endif; ?>

        <div class="safety-actions">

            <button
                class="safety-button safety-button-primary"
                type="button"
                data-safety-back
            >
                Go back
            </button>

            <a
                class="safety-button"
                href="<?= safety_e($reportUrl) ?>"
            >
                Report
            </a>

            <a
                class="safety-button"
                href="https://llamascout.com/"
            >
                Go home
            </a>

            <?php if (
                $reason === 'permission'
                || $reason === '401'
                || $reason === '403'
            ): ?>
                <a
                    class="safety-button"
                    href="https://account.llamascout.com/login.php"
                >
                    Sign in
                </a>
            <?php endif; ?>

        </div>

    </section>

    <p class="safety-footer">
        Know the place before you go.
    </p>

</main>

<script
    src="https://llamascout.com/js/safety.js"
    defer
></script>

</body>
</html>

<?php

declare(strict_types=1);

/*
 * Llama Scout Knowledge Base launch refresh.
 *
 * Temporary one-time installer:
 *   /admin/kb-launch-refresh.php
 *
 * After it reports success, delete this file and /admin/kb-assets/.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-knowledge-base.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);
$today = gmdate('Y-m-d');

function kb_launch_e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function kb_launch_ensure_category(
    PDO $db,
    string $slug,
    string $name,
    string $description,
    string $iconName,
    int $sortOrder
): int {
    $statement = $db->prepare(
        'SELECT id
         FROM kb_categories
         WHERE slug = ?
         LIMIT 1'
    );
    $statement->execute([$slug]);

    $existingId = (int) $statement->fetchColumn();

    if ($existingId > 0) {
        return $existingId;
    }

    return kb_admin_save_category(
        $db,
        [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon_name' => $iconName,
            'status' => 'active',
            'sort_order' => $sortOrder,
        ]
    );
}

function kb_launch_existing_article_id(
    PDO $db,
    string $slug
): int {
    $statement = $db->prepare(
        'SELECT id
         FROM kb_articles
         WHERE slug = ?
         LIMIT 1'
    );
    $statement->execute([$slug]);

    return (int) $statement->fetchColumn();
}

function kb_launch_save_article(
    PDO $db,
    int $actorUserId,
    int $categoryId,
    array $article,
    string $today
): int {
    $slug = (string) $article['slug'];

    return kb_admin_save_article(
        $db,
        $actorUserId,
        [
            'id' => kb_launch_existing_article_id($db, $slug),
            'category_id' => $categoryId,
            'title' => (string) $article['title'],
            'slug' => $slug,
            'summary' => (string) $article['summary'],
            'content' => (string) $article['content'],
            'seo_description' => (string) $article['seo_description'],
            'status' => 'published',
            'is_featured' => !empty($article['is_featured']) ? 1 : 0,
            'sort_order' => (int) ($article['sort_order'] ?? 0),
            'last_reviewed_at' => $today,
            'keywords' => $article['keywords'] ?? [],
        ]
    );
}

function kb_launch_accessibility_image_id(
    PDO $db,
    int $articleId,
    string $sourcePath
): int {
    $statement = $db->prepare(
        'SELECT id
         FROM kb_article_images
         WHERE article_id = ?
           AND status = "active"
           AND alt_text = ?
         ORDER BY id ASC
         LIMIT 1'
    );

    $altText =
        'Accessibility settings button showing the Llama Scout accessibility icon.';

    $statement->execute([
        $articleId,
        $altText,
    ]);

    $existingId = (int) $statement->fetchColumn();

    if ($existingId > 0) {
        return $existingId;
    }

    if (!is_file($sourcePath)) {
        throw new RuntimeException(
            'The accessibility helper image is missing from /admin/kb-assets/.'
        );
    }

    $dimensions = @getimagesize($sourcePath);

    if (!is_array($dimensions)) {
        throw new RuntimeException(
            'The accessibility helper image could not be read.'
        );
    }

    $width = (int) ($dimensions[0] ?? 0);
    $height = (int) ($dimensions[1] ?? 0);

    $targetDirectory =
        dirname(__DIR__)
        . '/uploads/knowledge-base/'
        . $articleId;

    if (
        !is_dir($targetDirectory)
        && !mkdir($targetDirectory, 0755, true)
        && !is_dir($targetDirectory)
    ) {
        throw new RuntimeException(
            'The Knowledge Base image folder could not be created.'
        );
    }

    $filename =
        'accessibility-menu-button-'
        . gmdate('Ymd-His')
        . '.png';

    $targetPath =
        $targetDirectory
        . '/'
        . $filename;

    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException(
            'The accessibility helper image could not be copied.'
        );
    }

    @chmod($targetPath, 0644);

    $publicPath =
        '/uploads/knowledge-base/'
        . $articleId
        . '/'
        . $filename;

    try {
        $sortStatement = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 10
             FROM kb_article_images
             WHERE article_id = ?
               AND status = "active"'
        );
        $sortStatement->execute([$articleId]);
        $sortOrder = (int) $sortStatement->fetchColumn();

        $insert = $db->prepare(
            'INSERT INTO kb_article_images
                (
                    article_id,
                    image_path,
                    mime_type,
                    file_size_bytes,
                    width_px,
                    height_px,
                    alt_text,
                    caption,
                    status,
                    sort_order,
                    archived_at,
                    created_at,
                    updated_at
                )
             VALUES
                (
                    ?, ?, "image/png", ?, ?, ?, ?, ?,
                    "active", ?, NULL,
                    UTC_TIMESTAMP(), UTC_TIMESTAMP()
                )'
        );

        $insert->execute([
            $articleId,
            $publicPath,
            (int) filesize($targetPath),
            $width,
            $height,
            $altText,
            'Look for this accessibility button to open the Accessibility settings menu.',
            $sortOrder,
        ]);

        return (int) $db->lastInsertId();

    } catch (Throwable $exception) {
        @unlink($targetPath);
        throw $exception;
    }
}

$error = '';
$results = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session token expired. Reload the page and try again.';
    } else {
        try {
            if (!kb_admin_schema_ready($db)) {
                throw new RuntimeException(
                    'The Knowledge Base database tables are not installed.'
                );
            }

            $categories = [
                'accessibility' => kb_launch_ensure_category(
                    $db,
                    'accessibility',
                    'Accessibility',
                    'Display, motion, focus, text size, and accessibility tools.',
                    'accessible',
                    70
                ),
                'account' => kb_launch_ensure_category(
                    $db,
                    'account',
                    'Account',
                    'Manage your Llama Scout account, identity, contact details, and account lifecycle.',
                    'users',
                    20
                ),
                'security' => kb_launch_ensure_category(
                    $db,
                    'security',
                    'Security',
                    'Passwords, multi-factor authentication, recovery, and account security.',
                    'shield',
                    30
                ),
                'map' => kb_launch_ensure_category(
                    $db,
                    'map',
                    'Map',
                    'Search, filters, map layers, location precision, and map tools.',
                    'map',
                    40
                ),
            ];

            /*
             * Accessibility article is saved once to obtain its article ID,
             * then its managed KB image is inserted and the final image token
             * is saved into the article body.
             */
            $accessibilityBase = [
                'slug' => 'using-the-accessibility-menu',
                'title' => 'Using the Accessibility menu',
                'summary' =>
                    'Change appearance, text size, motion, and focus indicators from the Accessibility menu.',
                'seo_description' =>
                    'How to use Llama Scout accessibility settings including text size, reduced motion, theme, and always-visible focus indicators.',
                'sort_order' => 10,
                'is_featured' => 1,
                'keywords' => [
                    'accessibility',
                    'accessibility menu',
                    'focus ring',
                    'focus indicator',
                    'keyboard focus',
                    'text size',
                    'large text',
                    'reduce motion',
                    'dark mode',
                    'light mode',
                ],
                'content' => '
<h2>Open the Accessibility menu</h2>
<p>Llama Scout keeps its display and motion controls together in the Accessibility menu.</p>
<p>On a larger screen, select the Accessibility button in the site header. On a smaller screen, open the site menu and select the Accessibility button there.</p>
<p>[ACCESSIBILITY_MENU_IMAGE]</p>

<h2>What you can change</h2>
<h3>Appearance</h3>
<ul>
<li><strong>Device setting</strong> follows your device or browser light/dark preference.</li>
<li><strong>Light</strong> keeps Llama Scout in light mode.</li>
<li><strong>Dark</strong> keeps Llama Scout in dark mode.</li>
</ul>

<h3>Text size</h3>
<p>Choose <strong>Normal</strong>, <strong>Large</strong>, or <strong>Extra large</strong>. This changes Llama Scout text without requiring you to change the text size for the rest of your device.</p>

<h3>Reduce motion</h3>
<p>Turn on Reduce motion if animation or movement is distracting or uncomfortable. Llama Scout also respects your device reduced-motion preference where supported.</p>

<h3>Always show focus indicators</h3>
<p>Turn on <strong>Always show focus indicators</strong> to keep the visible focus ring around the active link, button, or form control. This makes it easier to see where you are when navigating with a keyboard, switch control, or other non-pointer input.</p>
<aside class="kb-note"><strong>Focus rings do not change what is selected.</strong> They make the current focus position easier to see.</aside>

<h3>Reset accessibility settings</h3>
<p>Use the reset option in the Accessibility menu to return the menu settings to their defaults.</p>

<h2>Settings are saved on this device</h2>
<p>These accessibility preferences are stored in your browser. If you use Llama Scout on another browser or device, you may need to set them again.</p>
',
            ];

            $accessibilityId = kb_launch_save_article(
                $db,
                $actorUserId,
                $categories['accessibility'],
                $accessibilityBase,
                $today
            );

            $imageId = kb_launch_accessibility_image_id(
                $db,
                $accessibilityId,
                __DIR__ . '/kb-assets/accessibility-menu-button.png'
            );

            $accessibilityBase['content'] =
                str_replace(
                    '[ACCESSIBILITY_MENU_IMAGE]',
                    '[kb-image id="' . $imageId . '"]',
                    (string) $accessibilityBase['content']
                );

            kb_launch_save_article(
                $db,
                $actorUserId,
                $categories['accessibility'],
                $accessibilityBase,
                $today
            );

            $results[] = 'Accessibility menu article updated.';

            $articles = [
                [
                    'category_id' => $categories['account'],
                    'slug' => 'delete-or-anonymize-your-account',
                    'title' => 'How to delete or anonymize your account',
                    'summary' =>
                        'What account deletion means, why published contributions are anonymized, and the exact steps to permanently close your account.',
                    'seo_description' =>
                        'Step-by-step instructions for permanently deleting and anonymizing a Llama Scout account.',
                    'sort_order' => 60,
                    'is_featured' => 1,
                    'keywords' => [
                        'delete account',
                        'close account',
                        'anonymize account',
                        'deleted user',
                        'privacy',
                        'remove account',
                        'remove profile',
                    ],
                    'content' => '
<h2>What Llama Scout means by delete and anonymize</h2>
<p>Deleting your account permanently removes your ability to sign in and removes or anonymizes personal account information. Published Places and approved community contributions can remain because they are part of the historical record of how a Place was added or updated.</p>
<p>Instead of leaving those published records attached to your former identity, Llama Scout changes the account to an anonymous identity such as <code>user_0013</code> and the display name <strong>Deleted User</strong>.</p>

<h2>What is removed or changed</h2>
<ul>
<li>Your sign-in email is replaced with an invalid Llama Scout deletion address and your password is replaced, so the account cannot be used to sign in again.</li>
<li>Your username and display name are replaced with an anonymous username and <strong>Deleted User</strong>.</li>
<li>Your public profile is turned off. Profile photos, bio, location, social links, camping preferences, and other profile details are removed.</li>
<li>Saved Places, remember-me tokens, password-reset records, and email-verification records are removed.</li>
<li>Badges, badge credential evidence, points, Scout status, Master Scout status, and other account recognition are forfeited.</li>
<li>Draft or non-approved Place submissions and updates may be removed.</li>
<li>Membership grants and account access end.</li>
</ul>

<h2>What may remain</h2>
<p>Published Places and approved contributions may remain under the anonymous account so Llama Scout can preserve contribution provenance and Place history without displaying your former account identity.</p>
<p>Orders, payments, refunds, disputes, security logs, moderation history, accounting records, and similar records may also be retained when needed for legal, financial, security, fraud-prevention, dispute-resolution, or service-integrity purposes.</p>

<aside class="kb-note"><strong>Account deletion cannot be undone.</strong> The anonymized account cannot be restored or transferred to a new account.</aside>

<h2>Before deleting your account</h2>
<ul>
<li>If you have an active paid Stripe subscription, cancel the membership first. Self-service deletion is blocked while an active Stripe subscription is still linked to the account.</li>
<li>Owner and Admin accounts cannot use self-service deletion until privileged access has been transferred or removed.</li>
</ul>

<h2>Delete your account step by step</h2>
<ol>
<li>Sign in to Llama Scout.</li>
<li>Open <strong>My Account</strong>.</li>
<li>Scroll to <strong>Account &amp; settings</strong>.</li>
<li>Select <strong>Delete or anonymize account</strong>.</li>
<li>Read the <strong>What deletion means</strong> section.</li>
<li>Type your current username exactly as shown.</li>
<li>Enter your current password.</li>
<li>Check every deletion acknowledgement.</li>
<li>Select <strong>Permanently Delete My Account</strong>.</li>
<li>Confirm the final browser warning.</li>
</ol>

<p>When the process completes, you are signed out immediately. The confirmation page shows the anonymous username now attached to any historical Places or approved contributions that remain.</p>
',
                ],
                [
                    'category_id' => $categories['account'],
                    'slug' => 'update-your-account-information',
                    'title' => 'How to update your account information',
                    'summary' =>
                        'Update your public identity, private contact details, address, time zone, email address, and Support PIN.',
                    'seo_description' =>
                        'Step-by-step instructions for changing Llama Scout account information and sign-in email.',
                    'sort_order' => 20,
                    'is_featured' => 0,
                    'keywords' => [
                        'account information',
                        'change email',
                        'email address',
                        'phone number',
                        'address',
                        'time zone',
                        'support pin',
                        'display name',
                        'username',
                    ],
                    'content' => '
<h2>Open Account information</h2>
<ol>
<li>Sign in and open <strong>My Account</strong>.</li>
<li>Scroll to <strong>Account &amp; settings</strong>.</li>
<li>Select <strong>Account information</strong>.</li>
</ol>

<h2>Update private details</h2>
<p>Use the private-details section to update the contact and address information shown on the page, including your time zone. Dates and times across Llama Scout use the saved time zone while you are signed in.</p>
<ol>
<li>Change the fields you want to update.</li>
<li>Review the information for accuracy.</li>
<li>Select <strong>Save private details</strong>.</li>
</ol>

<h2>Change your sign-in email</h2>
<ol>
<li>Find the <strong>Email address</strong> section.</li>
<li>Enter the new email address.</li>
<li>Enter your current password.</li>
<li>Select <strong>Send verification to new email</strong>.</li>
<li>Open the verification message sent to the new address and complete verification.</li>
</ol>
<p>Your existing verified sign-in email continues to work until the new address is verified. If a change is already waiting for verification, you can resend the verification message or cancel the pending change.</p>

<h2>Support PIN</h2>
<p>The Support PIN is an 8-digit private PIN used for phone-support verification. The page will tell you what must be set up first. A verified email, multi-factor authentication, and a saved phone number are required before a Support PIN can be created.</p>
',
                ],
                [
                    'category_id' => $categories['security'],
                    'slug' => 'password-and-security',
                    'title' => 'Password, MFA, and account security',
                    'summary' =>
                        'Change your password, set up multi-factor authentication, and manage recovery codes.',
                    'seo_description' =>
                        'How to change a Llama Scout password, enable MFA, and manage recovery codes.',
                    'sort_order' => 10,
                    'is_featured' => 0,
                    'keywords' => [
                        'password',
                        'change password',
                        'security',
                        'mfa',
                        'multi-factor authentication',
                        'authenticator',
                        'recovery codes',
                        'totp',
                    ],
                    'content' => '
<h2>Open Password &amp; Security</h2>
<ol>
<li>Sign in and open <strong>My Account</strong>.</li>
<li>Scroll to <strong>Account &amp; settings</strong>.</li>
<li>Select <strong>Password &amp; security</strong>.</li>
</ol>

<h2>Change your password</h2>
<p>The Password section uses a secure email reset link.</p>
<ol>
<li>Select <strong>Change Password</strong>.</li>
<li>Follow the password-reset instructions sent to your email.</li>
<li>Return to Llama Scout and sign in with the new password.</li>
</ol>

<h2>Enable multi-factor authentication</h2>
<p>MFA adds an authenticator-app code as a second sign-in step. It is required for Owner and Admin accounts and optional for ordinary member accounts.</p>
<ol>
<li>Open the Multi-factor authentication section.</li>
<li>Scan the QR code with your authenticator app. If you cannot scan it, use the manual setup key shown on the page.</li>
<li>Enter the current 6-digit authentication code.</li>
<li>Finish enabling MFA.</li>
<li>Save the recovery codes shown immediately after setup.</li>
</ol>

<aside class="kb-note"><strong>Recovery codes are one-time codes.</strong> The exact set shown after creation cannot be displayed again. If you replace them, save the new set.</aside>

<h2>Recovery codes and disabling MFA</h2>
<p>When MFA is enabled, the security page shows the remaining recovery-code count and controls for replacing recovery codes. Disabling MFA requires your current password and a current authenticator code.</p>
',
                ],
                [
                    'category_id' => $categories['map'],
                    'slug' => 'using-the-llama-scout-map',
                    'title' => 'Using the Llama Scout map',
                    'summary' =>
                        'Search and filter Places, understand public versus exact locations, use map layers, and know which tools appear at each zoom level.',
                    'seo_description' =>
                        'Complete guide to Llama Scout map search, filters, location precision, map styles, land, weather, cell coverage, and zoom levels.',
                    'sort_order' => 10,
                    'is_featured' => 1,
                    'keywords' => [
                        'map',
                        'map help',
                        'zoom',
                        'map layers',
                        'satellite',
                        'terrain',
                        'topo',
                        'land boundaries',
                        'weather radar',
                        'cell coverage',
                        'exact coordinates',
                        'approximate coordinates',
                        'filters',
                    ],
                    'content' => '
<h2>What the map shows</h2>
<p>The map is the main place to browse published Llama Scout Places. You can search by Place name or general area, narrow the list with filters, select a result, and use the map controls to inspect the surrounding area.</p>

<h2>Search and filters</h2>
<p>The search box accepts Place names, towns, and counties. The Filters button opens additional filters for:</p>
<ul>
<li>State</li>
<li>County</li>
<li>Nearest town</li>
<li>Place type</li>
<li>Land manager</li>
<li>Land type</li>
<li>Maximum elevation</li>
<li>Amenity</li>
</ul>
<p>Amenity filters currently include toilets, potable water, trash, fire rings, picnic tables, bear boxes, showers, electricity, and dump stations.</p>
<p>Select <strong>Clear</strong> to remove active filters. Select <strong>Fit map</strong> above the results list to fit the map around the current matching Places.</p>

<h2>Public locations and exact locations</h2>
<p>Public map pins use approximate Place locations. Complete Access members receive exact Place locations. If you originally contributed a Place, your account can see the exact pin for that contributed Place even when other Places remain approximate.</p>

<h2>Zoom levels</h2>
<ul>
<li><strong>Public map:</strong> zoom levels up to 11.</li>
<li><strong>Complete Access:</strong> zoom levels up to 20.</li>
<li><strong>Contributor exact access:</strong> the map can use the detailed zoom range for a Place you contributed, while Places you do not have exact access to remain approximate.</li>
</ul>
<p>The <strong>Show my location</strong> control zooms public users to level 11. When exact-location zoom is available, it normally centers around level 15 so you can see the surrounding area without immediately zooming all the way in.</p>

<h2>Complete Access map tools</h2>
<h3>Map styles</h3>
<p>Use the <strong>Map</strong> tool to choose Auto, Street, Terrain, Topo, Dark, or Satellite. Auto follows the current Llama Scout appearance when supported.</p>

<h3>Land</h3>
<p>The Land tool can show U.S. Forest Service, Bureau of Land Management, and Tribal land boundaries. Land overlays begin loading at zoom level 7 and become more detailed as you zoom in.</p>

<h3>Weather</h3>
<p>The Weather tool can show NOAA radar, cloud imagery, lightning strike density, and active watches, warnings, and advisories. Weather layers refresh periodically while enabled.</p>

<h3>Cell coverage</h3>
<p>The Cell tool uses FCC National Broadband Map data. Choose AT&amp;T, T-Mobile, or Verizon, then select 4G LTE or 5G and either <strong>In vehicle</strong> or <strong>Outdoors</strong>.</p>
<p>Cell coverage begins displaying at <strong>zoom level 11</strong>. If coverage is enabled while you are farther out, zoom in until level 11 or closer.</p>

<aside class="kb-note"><strong>Map layers are planning tools, not guarantees.</strong> Coverage, weather, boundaries, roads, and Place conditions can change. Use the current Place report and appropriate official sources before relying on a map layer for safety-critical decisions.</aside>
',
                ],
            ];

            foreach ($articles as $article) {
                kb_launch_save_article(
                    $db,
                    $actorUserId,
                    (int) $article['category_id'],
                    $article,
                    $today
                );

                $results[] =
                    (string) $article['title']
                    . ' updated.';
            }

        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Knowledge Base Launch Refresh</title>
    <style>
        body {
            margin: 0;
            padding: 32px 18px;
            background: #141414;
            color: #f4f4f4;
            font: 16px/1.55 system-ui, sans-serif;
        }
        main {
            max-width: 760px;
            margin: 0 auto;
        }
        section {
            padding: 22px;
            border: 1px solid #444;
            border-radius: 16px;
            background: #222;
        }
        h1 { margin-top: 0; }
        button {
            min-height: 44px;
            padding: 10px 16px;
            border: 1px solid #666;
            border-radius: 10px;
            background: #111;
            color: #fff;
            font: inherit;
            font-weight: 800;
        }
        .ok { color: #6ed38b; }
        .error { color: #ff7d7d; }
        code { color: #ddd; }
    </style>
</head>
<body>
<main>
    <section>
        <h1>Knowledge Base launch refresh</h1>

        <?php if ($error !== ''): ?>
            <p class="error"><?= kb_launch_e($error) ?></p>
        <?php endif; ?>

        <?php if ($results): ?>
            <h2 class="ok">Refresh complete</h2>
            <ul>
                <?php foreach ($results as $result): ?>
                    <li><?= kb_launch_e($result) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>
                Open <a href="/knowledge-base.php" style="color:#8fd7a5;">Basecamp Knowledge Base</a>
                to review the published articles.
            </p>
            <p>
                Delete <code>/admin/kb-launch-refresh.php</code> and
                <code>/admin/kb-assets/</code> after you have checked the articles.
            </p>
        <?php else: ?>
            <p>
                This installs or updates five launch-ready help articles and
                adds the Accessibility menu image to managed Knowledge Base storage.
            </p>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= kb_launch_e(moderation_csrf_token()) ?>"
                >
                <button type="submit">
                    Install Knowledge Base refresh
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

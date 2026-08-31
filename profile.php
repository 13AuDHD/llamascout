<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$db = db();
$currentUser = current_user();
$username = strtolower(trim((string) ($_GET['user'] ?? '')));
$profile = llama_public_profile_by_username($db, $username);

if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    echo '<section class="account-empty-state"><i class="fa-solid fa-user-slash" aria-hidden="true"></i><h1>Profile not found</h1><p>This Community Profile does not exist or is no longer available.</p><a class="place-save-button" href="/community.php">Browse Community</a></section>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$isOwner = $currentUser && (int) ($currentUser['id'] ?? 0) === (int) $profile['id'];
$isPublic = !empty($profile['is_public']);
$isSignedIn = is_array($currentUser);

if (!$isPublic && !$isOwner && !$isSignedIn) {
    http_response_code(404);
    $pageTitle = 'Profile Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    echo '<section class="account-empty-state"><i class="fa-solid fa-user-lock" aria-hidden="true"></i><h1>Members-only profile</h1><p>Sign in to view this Llama Scout member profile.</p></section>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

function public_profile_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
$accountUrl = rtrim((string) ($config['app']['account_url'] ?? 'https://account.llamascout.com'), '/');
$displayName = trim((string) ($profile['display_name'] ?? '')) ?: (string) $profile['username'];
$pageTitle = $displayName . ' | Llama Scout Community';

$socials = array_filter([
    ['fa-solid fa-globe', 'Website', $profile['website_url'] ?? null],
    ['fa-brands fa-instagram', 'Instagram', $profile['instagram_url'] ?? null],
    ['fa-brands fa-facebook', 'Facebook', $profile['facebook_url'] ?? null],
    ['fa-solid fa-cloud', 'Bluesky', $profile['bluesky_url'] ?? null],
    ['fa-brands fa-youtube', 'YouTube', $profile['youtube_url'] ?? null],
    ['fa-brands fa-tiktok', 'TikTok', $profile['tiktok_url'] ?? null],
    ['fa-solid fa-link', 'Link', $profile['other_social_url'] ?? null],
], static fn (array $item): bool => is_string($item[2]) && trim($item[2]) !== '');

require __DIR__ . '/partials/header.php';
?>

<article class="public-community-profile">
    <header class="public-community-profile-hero">
        <img
            class="public-community-profile-avatar"
            src="<?= public_profile_e(llama_profile_image_url((string) $profile['primary_image'], $siteUrl)) ?>"
            alt="<?= public_profile_e($displayName) ?> profile picture"
        >

        <div class="public-community-profile-heading">
            <p class="account-eyebrow">Llama Scout Community</p>
            <h1><?= public_profile_e($displayName) ?></h1>
            <p class="public-community-profile-handle">@<?= public_profile_e($profile['username']) ?></p>

            <?php if (!empty($profile['location'])): ?>
                <p class="public-community-profile-location">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <?= public_profile_e($profile['location']) ?>
                </p>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <a class="place-save-button" href="<?= public_profile_e($accountUrl . '/profile.php') ?>">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    Edit profile
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($profile['badges'])): ?>
        <section class="public-community-profile-section">
            <div class="community-profile-section-heading">
                <div>
                    <p class="account-eyebrow">Recognition</p>
                    <h2>Badges</h2>
                </div>
            </div>
            <div class="community-badge-grid">
                <?php foreach ($profile['badges'] as $badge): ?>
                    <article class="community-badge-card">
                        <span class="community-badge-icon">
                            <?php if (!empty($badge['image_src'])): ?>
                                <img src="<?= public_profile_e(llama_profile_image_url((string) $badge['image_src'], $siteUrl)) ?>" alt="">
                            <?php else: ?>
                                <i class="fa-solid <?= public_profile_e($badge['icon'] ?: 'fa-award') ?>" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>
                        <div>
                            <strong><?= public_profile_e($badge['name']) ?></strong>
                            <?php if (!empty($badge['description'])): ?>
                                <p><?= public_profile_e($badge['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($profile['bio'])): ?>
        <section class="public-community-profile-section">
            <h2>About</h2>
            <p class="public-community-profile-bio"><?= nl2br(public_profile_e($profile['bio'])) ?></p>
        </section>
    <?php endif; ?>

    <?php
    $facts = array_filter([
        ['fa-solid fa-people-group', 'Squad / club', $profile['squad'] ?? null],
        ['fa-solid fa-campground', 'Camping style', $profile['camping_style'] ?? null],
        ['fa-solid fa-mountain-sun', 'Favorite kind of place', $profile['favorite_places'] ?? null],
        ['fa-solid fa-music', 'Camping soundtrack', $profile['favorite_camping_music'] ?? null],
    ], static fn (array $item): bool => is_string($item[2]) && trim($item[2]) !== '');
    ?>
    <?php if ($facts): ?>
        <section class="public-community-profile-section">
            <h2>Camp profile</h2>
            <div class="public-community-profile-facts">
                <?php foreach ($facts as [$icon, $label, $value]): ?>
                    <div class="public-community-profile-fact">
                        <i class="<?= public_profile_e($icon) ?>" aria-hidden="true"></i>
                        <span><?= public_profile_e($label) ?></span>
                        <strong><?= public_profile_e($value) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($profile['images'])): ?>
        <section class="public-community-profile-section">
            <div class="community-profile-section-heading">
                <div>
                    <p class="account-eyebrow">Gallery</p>
                    <h2>Photos</h2>
                </div>
                <span class="account-section-count"><?= count($profile['images']) ?>/5</span>
            </div>
            <div class="public-community-profile-gallery">
                <?php foreach ($profile['images'] as $image): ?>
                    <img
                        src="<?= public_profile_e(llama_profile_image_url((string) $image['image_src'], $siteUrl)) ?>"
                        alt="<?= public_profile_e($image['alt_text'] ?: $displayName . ' profile photo') ?>"
                        loading="lazy"
                    >
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($socials): ?>
        <section class="public-community-profile-section">
            <h2>Links</h2>
            <div class="public-community-profile-links">
                <?php foreach ($socials as [$icon, $label, $url]): ?>
                    <a href="<?= public_profile_e($url) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="<?= public_profile_e($icon) ?>" aria-hidden="true"></i>
                        <?= public_profile_e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$db = db();
$profiles = llama_public_profiles($db, 100);
$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');

function community_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Community | Llama Scout';
require __DIR__ . '/partials/header.php';
?>

<section class="community-directory">
    <header class="community-directory-header">
        <p class="account-eyebrow">The herd</p>
        <h1>Llama Scout Community</h1>
        <p>Meet the people contributing places, observations, photos, and experience to Llama Scout.</p>
    </header>

    <?php if (!$profiles): ?>
        <div class="account-empty-state">
            <i class="fa-solid fa-people-group" aria-hidden="true"></i>
            <h2>No public profiles yet</h2>
            <p>Public Community Profiles will appear here as members choose to share them.</p>
        </div>
    <?php else: ?>
        <div class="community-directory-grid">
            <?php foreach ($profiles as $profile): ?>
                <a class="community-directory-card" href="/profile.php?user=<?= rawurlencode((string) $profile['username']) ?>">
                    <img
                        src="<?= community_e(llama_profile_image_url((string) $profile['primary_image'], $siteUrl)) ?>"
                        alt=""
                        loading="lazy"
                    >
                    <div>
                        <h2><?= community_e($profile['display_name'] ?: $profile['username']) ?></h2>
                        <p class="community-directory-handle">@<?= community_e($profile['username']) ?></p>
                        <?php if (!empty($profile['location'])): ?>
                            <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= community_e($profile['location']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($profile['bio'])): ?>
                            <p class="community-directory-bio"><?= community_e(mb_strimwidth((string) $profile['bio'], 0, 150, '…')) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>

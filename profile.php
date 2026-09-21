<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/icons.php';

$db = db();
$currentUser = current_user();
$username = strtolower(trim((string) ($_GET['user'] ?? '')));
$profile = llama_public_profile_by_username($db, $username);

function public_profile_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function public_profile_local_icon_name(
    mixed $value,
    string $fallback = 'award'
): string {
    $name = strtolower(trim((string) $value));

    if (
        $name === ''
        || !preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $name
        )
        || !is_file(
            __DIR__
            . '/assets/icons/'
            . $name
            . '.svg'
        )
    ) {
        return $fallback;
    }

    return $name;
}

if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    echo '<section class="account-empty-state"><i aria-hidden="true">' . llama_icon('user-off') . '</i><h1>Profile not found</h1><p>That Llama Scout profile does not exist or is no longer available.</p><a class="place-save-button" href="/map.php">Explore the map</a></section>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$isOwner = $currentUser && (int) ($currentUser['id'] ?? 0) === (int) $profile['id'];
$isPublic = !empty($profile['is_public']);
$isSignedIn = is_array($currentUser);
$showFullProfile = $isPublic || $isOwner;

if (!$isPublic && !$isOwner && !$isSignedIn) {
    http_response_code(404);
    $pageTitle = 'Profile Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    echo '<section class="account-empty-state"><i aria-hidden="true">' . llama_icon('user-shield') . '</i><h1>This profile is not public</h1><p>Sign in to view this member\'s basic Llama Scout profile.</p></section>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$publicScoutStatus = '';
$publicScoutRoles = [];

try {
    $scoutStmt = $db->prepare(
        'SELECT
            sp.status,
            GROUP_CONCAT(
                DISTINCT r.slug
                ORDER BY r.id
                SEPARATOR ","
            ) AS role_slugs
         FROM scout_profiles sp
         LEFT JOIN user_roles ur
            ON ur.user_id = sp.user_id
         LEFT JOIN roles r
            ON r.id = ur.role_id
         WHERE sp.user_id = ?
         GROUP BY sp.id
         ORDER BY sp.id DESC
         LIMIT 1'
    );

    $scoutStmt->execute([
        (int) $profile['id'],
    ]);

    $scoutIdentity =
        $scoutStmt->fetch(PDO::FETCH_ASSOC);

    if ($scoutIdentity) {
        $publicScoutStatus =
            strtolower(
                trim(
                    (string) (
                        $scoutIdentity['status']
                        ?? ''
                    )
                )
            );

        $publicScoutRoles =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            strtolower(
                                (string) (
                                    $scoutIdentity['role_slugs']
                                    ?? ''
                                )
                            )
                        )
                    )
                )
            );
    }
} catch (Throwable $exception) {
    if (function_exists('llama_log_caught_exception')) {
        llama_log_caught_exception(
            $exception,
            'profile.scout_identity',
            [
                'user_id' =>
                    (int) $profile['id'],
            ]
        );
    }
}

$isActiveScout =
    $publicScoutStatus === 'active';

$isMasterScout =
    $isActiveScout
    && (
        in_array(
            'master-scout',
            $publicScoutRoles,
            true
        )
        || in_array(
            'master_scout',
            $publicScoutRoles,
            true
        )
    );

$publicScoutLabel =
    $isMasterScout
        ? 'Master Scout'
        : (
            $isActiveScout
                ? 'Llama Scout'
                : ''
        );

$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
$accountUrl = rtrim((string) ($config['app']['account_url'] ?? 'https://account.llamascout.com'), '/');
$displayName = trim((string) ($profile['display_name'] ?? '')) ?: (string) $profile['username'];
$pageTitle = $displayName . ' | Llama Scout';
$canonicalUrl = llama_profile_url((string) $profile['username'], $siteUrl);

$joinedAt = '';

if (!empty($profile['joined_at'])) {
    $joinedAt = llama_format_viewer_date(
        (string) $profile['joined_at'],
        'F Y'
    );
}

$stats = is_array($profile['stats'] ?? null) ? $profile['stats'] : [];
$primaryImageId = (int) ($profile['primary_image_id'] ?? 0);
$galleryImages = array_values(array_filter(
    is_array($profile['images'] ?? null) ? $profile['images'] : [],
    static fn (array $image): bool => (int) ($image['id'] ?? 0) !== $primaryImageId
));

$socials = [];
if ($showFullProfile) {
    $website = trim((string) ($profile['website_url'] ?? ''));
    if ($website !== '') {
        $socials[] = ['world', 'Website', $website, parse_url($website, PHP_URL_HOST) ?: 'Website'];
    }

    foreach ([
        ['instagram', 'brand-instagram', 'Instagram', 'instagram_url'],
        ['facebook', 'brand-facebook', 'Facebook', 'facebook_url'],
        ['bluesky', 'brand-bluesky', 'Bluesky', 'bluesky_url'],
        ['youtube', 'brand-youtube', 'YouTube', 'youtube_url'],
        ['tiktok', 'brand-tiktok', 'TikTok', 'tiktok_url'],
    ] as [$network, $icon, $label, $field]) {
        $handle = trim((string) ($profile[$field] ?? ''));
        $url = llama_profile_social_url($network, $handle);
        if ($url !== null) {
            $socials[] = [$icon, $label, $url, llama_profile_social_display($handle)];
        }
    }

    $other = trim((string) ($profile['other_social_url'] ?? ''));
    if ($other !== '') {
        $socials[] = ['link', 'Other', $other, parse_url($other, PHP_URL_HOST) ?: 'Link'];
    }
}

$pageRobots =
    $isPublic
        ? 'index,follow'
        : 'noindex,nofollow';

$pageDescription =
    $isPublic
        ? $displayName .
            ' on Llama Scout.'
        : '';

$canonicalUrl =
    $isPublic
        ? $siteUrl .
            '/' .
            rawurlencode(
                (string) $profile['username']
            )
        : '';

$pageSocialImage =
    $isPublic
        ? llama_profile_image_url(
            (string) $profile['primary_image'],
            $siteUrl
        )
        : '';

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
            <p class="account-eyebrow">Llama Scout member</p>

            <div class="public-community-profile-name-row">
                <h1><?= public_profile_e($displayName) ?></h1>

                <?php if ($publicScoutLabel !== ''): ?>
                    <span
                        class="public-community-profile-scout-badge <?= $isMasterScout ? 'is-master' : '' ?>"
                    >
                        <i aria-hidden="true">
                            <?= llama_icon('binoculars') ?>
                        </i>
                        <?= public_profile_e(
                            $publicScoutLabel
                        ) ?>
                    </span>
                <?php endif; ?>
            </div>

            <p class="public-community-profile-handle">@<?= public_profile_e($profile['username']) ?></p>

            <?php if ($joinedAt !== ''): ?>
                <p class="public-community-profile-location">
                    <i aria-hidden="true"><?= llama_icon('calendar') ?></i>
                    Joined <?= public_profile_e($joinedAt) ?>
                </p>
            <?php endif; ?>

            <?php if ($showFullProfile && !empty($profile['location'])): ?>
                <p class="public-community-profile-location">
                    <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                    <?= public_profile_e($profile['location']) ?>
                </p>
            <?php endif; ?>

            <div class="public-community-profile-actions">

                <?php if ($isOwner): ?>
                    <a
                        class="place-save-button"
                        href="<?= public_profile_e($accountUrl . '/profile.php') ?>"
                    >
                        <i aria-hidden="true">
                            <?= llama_icon('edit') ?>
                        </i>
                        Edit profile
                    </a>
                <?php endif; ?>

                <?php if ($isPublic): ?>
                    <button
                        class="place-save-button llama-share-button"
                        type="button"
                        data-share
                        data-share-title="<?= public_profile_e($displayName . ' | Llama Scout') ?>"
                        data-share-text="<?= public_profile_e('Check out ' . $displayName . ' on Llama Scout.') ?>"
                        data-share-url="<?= public_profile_e($canonicalUrl) ?>"
                    >
                        <i aria-hidden="true">
                            <?= llama_icon('share') ?>
                        </i>
                        <span data-share-label>Share</span>
                    </button>
                <?php endif; ?>

            </div>
        </div>
    </header>

    <section class="public-community-profile-section">
        <div class="community-profile-section-heading">
            <div>
                <p class="account-eyebrow">Activity</p>
                <h2>Llama Scout stats</h2>
            </div>
        </div>

        <div class="public-community-profile-facts profile-stat-grid">
            <div class="public-community-profile-fact">
                <i aria-hidden="true"><?= llama_icon('star') ?></i>
                <span>Points earned</span>
                <strong><?= number_format((int) ($stats['points'] ?? 0)) ?></strong>
            </div>
            <div class="public-community-profile-fact">
                <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                <span>New Places</span>
                <strong><?= number_format((int) ($stats['places_submitted'] ?? 0)) ?></strong>
            </div>
            <div class="public-community-profile-fact">
                <i aria-hidden="true"><?= llama_icon('edit') ?></i>
                <span>Updates</span>
                <strong><?= number_format((int) ($stats['places_improved'] ?? 0)) ?></strong>
            </div>
            <div class="public-community-profile-fact">
                <i aria-hidden="true"><?= llama_icon('check') ?></i>
                <span>Contributions</span>
                <strong><?= number_format((int) ($stats['approved_contributions'] ?? 0)) ?></strong>
            </div>
        </div>
    </section>

    <section class="public-community-profile-section">
        <div class="community-profile-section-heading">
            <div>
                <p class="account-eyebrow">Recognition</p>
                <h2>Badges</h2>
            </div>
            <span class="account-section-count"><?= count($profile['badges'] ?? []) ?></span>
        </div>

        <?php if (!empty($profile['badges'])): ?>
            <div class="community-badge-grid">
<?php foreach ($profile['badges'] as $badge): ?>
    <?php
    $badgeSlug =
        (string) ($badge['slug'] ?? '');

    $badgeName =
        (string) ($badge['name'] ?? 'Badge');

    $badgeImage =
        $badge['resolved_image_src'] ?? null;
    ?>

    <a
        class="profile-earned-badge"
        href="/badges/<?= rawurlencode($badgeSlug) ?>"
        aria-label="View <?= public_profile_e($badgeName) ?> badge"
        title="<?= public_profile_e($badgeName) ?>"
    >
        <?php if ($badgeImage): ?>

            <img
                src="<?= public_profile_e(
                    llama_profile_image_url(
                        (string) $badgeImage,
                        $siteUrl
                    )
                ) ?>"
                alt="<?= public_profile_e($badgeName) ?>"
                loading="lazy"
            >

        <?php else: ?>

            <span class="profile-earned-badge-fallback">
                <i aria-hidden="true">
                    <?= llama_icon(
                        public_profile_local_icon_name(
                            $badge['icon'] ?? 'award',
                            'award'
                        )
                    ) ?>
                </i>
            </span>

        <?php endif; ?>
    </a>

<?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="public-community-profile-bio">No badges earned yet.</p>
        <?php endif; ?>
    </section>

    <?php if (!$showFullProfile): ?>
        <section class="public-community-profile-section profile-private-note">
            <i aria-hidden="true"><?= llama_icon('lock') ?></i>
            <div>
                <strong>This member has not made a public profile.</strong>
                <p>Members can still see the basic account information, badges, and contribution activity shown above.</p>
            </div>
        </section>
    <?php else: ?>

        <?php if (!empty($profile['bio'])): ?>
            <section class="public-community-profile-section">
                <h2>About</h2>
                <p class="public-community-profile-bio"><?= nl2br(public_profile_e($profile['bio'])) ?></p>
            </section>
        <?php endif; ?>

        <?php
        $facts = array_filter([
            ['users-group', 'Squad / club', $profile['squad'] ?? null],
            ['tent', 'Camping style', $profile['camping_style'] ?? null],
            ['mountain', 'Favorite kind of place', $profile['favorite_places'] ?? null],
            ['music', 'Camping soundtrack', $profile['favorite_camping_music'] ?? null],
        ], static fn (array $item): bool => is_string($item[2]) && trim($item[2]) !== '');
        ?>
        <?php if ($facts): ?>
            <section class="public-community-profile-section">
                <h2>About their adventures</h2>
                <div class="public-community-profile-facts">
                    <?php foreach ($facts as [$icon, $label, $value]): ?>
                        <div class="public-community-profile-fact">
                            <i aria-hidden="true"><?= llama_icon($icon) ?></i>
                            <span><?= public_profile_e($label) ?></span>
                            <strong><?= public_profile_e($value) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($galleryImages): ?>
            <section class="public-community-profile-section">
                <div class="community-profile-section-heading">
                    <div>
                        <p class="account-eyebrow">Photos</p>
                        <h2>More from <?= public_profile_e($displayName) ?></h2>
                    </div>
                </div>
                <div class="public-community-profile-gallery">
                    <?php foreach ($galleryImages as $image): ?>
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
                <h2>Elsewhere</h2>
                <div class="public-community-profile-links">
                    <?php foreach ($socials as [$icon, $label, $url, $text]): ?>
                        <a href="<?= public_profile_e($url) ?>" target="_blank" rel="noopener noreferrer">
                            <i aria-hidden="true"><?= llama_icon($icon) ?></i>
                            <span>
                                <strong><?= public_profile_e($label) ?></strong>
                                <small><?= public_profile_e($text) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/partials/footer.php'; ?>

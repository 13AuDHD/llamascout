<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';

const LLAMA_DEFAULT_PROFILE_IMAGE = '/images/default-profile.png';

function llama_ensure_community_profile(PDO $db, int $userId): void
{
    $stmt = $db->prepare(
        'INSERT INTO community_profiles (user_id) VALUES (?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
    );
    $stmt->execute([$userId]);
}

function llama_community_profile(PDO $db, int $userId): array
{
    llama_ensure_community_profile($db, $userId);

    $stmt = $db->prepare(
        'SELECT
            user_id, is_public, bio, location, squad,
            website_url, instagram_url, facebook_url, bluesky_url,
            youtube_url, tiktok_url, other_social_url,
            camping_style, favorite_places, favorite_camping_music,
            primary_image_id, created_at, updated_at
         FROM community_profiles
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function llama_community_profile_images(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT id, user_id, image_src, alt_text, sort_order, uploaded_at
         FROM community_profile_images
         WHERE user_id = ?
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_primary_profile_image(PDO $db, int $userId): string
{
    $stmt = $db->prepare(
        'SELECT i.image_src
         FROM community_profiles p
         INNER JOIN community_profile_images i
           ON i.id = p.primary_image_id
          AND i.user_id = p.user_id
         WHERE p.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $src = $stmt->fetchColumn();

    return is_string($src) && trim($src) !== ''
        ? trim($src)
        : LLAMA_DEFAULT_PROFILE_IMAGE;
}

function llama_user_badges(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT
            ub.id AS user_badge_id,
            ub.awarded_at,
            ub.review_status,
            ub.evidence_url,
            ub.note,
            bd.id AS badge_id,
            bd.slug,
            bd.name,
            bd.description,
            bd.category,
            bd.source_organization,
            bd.icon,
            bd.image_src,
            bd.sort_order
         FROM user_badges ub
         INNER JOIN badge_definitions bd ON bd.id = ub.badge_id
         WHERE ub.user_id = ?
           AND ub.review_status = \'earned\'
           AND bd.is_active = 1
         ORDER BY bd.sort_order ASC, ub.awarded_at ASC, bd.name ASC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_profile_stats(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT
            COALESCE(SUM(points_awarded), 0) AS points,
            COUNT(*) AS approved_contributions,
            COUNT(DISTINCT CASE
                WHEN contribution_type = \'new_place\' THEN place_id
                ELSE NULL
            END) AS places_submitted,
            COUNT(DISTINCT CASE
                WHEN contribution_type <> \'new_place\' THEN place_id
                ELSE NULL
            END) AS places_improved
         FROM place_contributions
         WHERE user_id = ?
           AND status = \'approved\''
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'points' => llama_points_total($db, $userId),
        'approved_contributions' => (int) ($row['approved_contributions'] ?? 0),
        'places_submitted' => (int) ($row['places_submitted'] ?? 0),
        'places_improved' => (int) ($row['places_improved'] ?? 0),
    ];
}

function llama_profile_image_url(string $src, ?string $siteUrl = null): string
{
    $src = trim($src);
    if ($src === '') {
        $src = LLAMA_DEFAULT_PROFILE_IMAGE;
    }

    if (preg_match('~^https?://~i', $src)) {
        return $src;
    }

    if ($siteUrl === null) {
        $config = llama_config();
        $siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
    }

    return rtrim($siteUrl, '/') . '/' . ltrim($src, '/');
}

function llama_profile_url(string $username, ?string $siteUrl = null): string
{
    $username = trim($username);

    if ($siteUrl === null) {
        $config = llama_config();
        $siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
    }

    return rtrim($siteUrl, '/') . '/' . rawurlencode($username);
}

function llama_profile_social_handle(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $value)) {
        $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
        if ($path !== '') {
            $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
            $value = (string) end($parts);
        }
    }

    return ltrim(trim($value), '@');
}

function llama_profile_social_url(string $network, string $handle): ?string
{
    $handle = llama_profile_social_handle($handle);
    if ($handle === '') {
        return null;
    }

    $encoded = rawurlencode($handle);

    return match ($network) {
        'instagram' => 'https://www.instagram.com/' . $encoded . '/',
        'facebook' => 'https://www.facebook.com/' . $encoded,
        'bluesky' => 'https://bsky.app/profile/' . $encoded,
        'youtube' => 'https://www.youtube.com/@' . $encoded,
        'tiktok' => 'https://www.tiktok.com/@' . $encoded,
        default => null,
    };
}

function llama_profile_social_display(string $handle): string
{
    $handle = llama_profile_social_handle($handle);
    return $handle === '' ? '' : '@' . $handle;
}

function llama_public_profile_by_username(PDO $db, string $username): ?array
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT
            u.id, u.username, u.display_name, u.created_at AS joined_at,
            cp.is_public, cp.bio, cp.location, cp.squad,
            cp.website_url, cp.instagram_url, cp.facebook_url,
            cp.bluesky_url, cp.youtube_url, cp.tiktok_url,
            cp.other_social_url, cp.camping_style,
            cp.favorite_places, cp.favorite_camping_music,
            cp.primary_image_id, cp.updated_at
         FROM users u
         LEFT JOIN community_profiles cp ON cp.user_id = u.id
         WHERE LOWER(u.username) = ?
           AND u.status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        return null;
    }

    $userId = (int) $profile['id'];
    $profile['images'] = llama_community_profile_images($db, $userId);
    $profile['badges'] = llama_user_badges($db, $userId);
    $profile['stats'] = llama_profile_stats($db, $userId);
    $profile['primary_image'] = llama_primary_profile_image($db, $userId);

    return $profile;
}

function llama_profile_save(PDO $db, int $userId, array $input): void
{
    llama_ensure_community_profile($db, $userId);

    $fields = [
        'is_public' => !empty($input['is_public']) ? 1 : 0,
        'bio' => trim((string) ($input['bio'] ?? '')),
        'location' => trim((string) ($input['location'] ?? '')),
        'squad' => trim((string) ($input['squad'] ?? '')),
        'website_url' => trim((string) ($input['website_url'] ?? '')),
        'instagram_url' => llama_profile_social_handle((string) ($input['instagram_url'] ?? '')),
        'facebook_url' => llama_profile_social_handle((string) ($input['facebook_url'] ?? '')),
        'bluesky_url' => llama_profile_social_handle((string) ($input['bluesky_url'] ?? '')),
        'youtube_url' => llama_profile_social_handle((string) ($input['youtube_url'] ?? '')),
        'tiktok_url' => llama_profile_social_handle((string) ($input['tiktok_url'] ?? '')),
        'other_social_url' => trim((string) ($input['other_social_url'] ?? '')),
        'camping_style' => trim((string) ($input['camping_style'] ?? '')),
        'favorite_places' => trim((string) ($input['favorite_places'] ?? '')),
        'favorite_camping_music' => trim((string) ($input['favorite_camping_music'] ?? '')),
    ];

    $limits = [
        'bio' => 1000,
        'location' => 150,
        'squad' => 150,
        'camping_style' => 255,
        'favorite_places' => 255,
        'favorite_camping_music' => 255,
        'instagram_url' => 150,
        'facebook_url' => 150,
        'bluesky_url' => 150,
        'youtube_url' => 150,
        'tiktok_url' => 150,
    ];

    foreach ($limits as $field => $max) {
        if (mb_strlen($fields[$field]) > $max) {
            throw new InvalidArgumentException('One of your profile answers is too long.');
        }
    }

    foreach (['website_url', 'other_social_url'] as $field) {
        if ($fields[$field] !== '' && !filter_var($fields[$field], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Website and other links must be complete URLs, including https://.');
        }
    }

    $stmt = $db->prepare(
        'UPDATE community_profiles SET
            is_public = ?, bio = ?, location = ?, squad = ?,
            website_url = ?, instagram_url = ?, facebook_url = ?, bluesky_url = ?,
            youtube_url = ?, tiktok_url = ?, other_social_url = ?,
            camping_style = ?, favorite_places = ?, favorite_camping_music = ?
         WHERE user_id = ?'
    );

    $stmt->execute([
        $fields['is_public'],
        $fields['bio'] !== '' ? $fields['bio'] : null,
        $fields['location'] !== '' ? $fields['location'] : null,
        $fields['squad'] !== '' ? $fields['squad'] : null,
        $fields['website_url'] !== '' ? $fields['website_url'] : null,
        $fields['instagram_url'] !== '' ? $fields['instagram_url'] : null,
        $fields['facebook_url'] !== '' ? $fields['facebook_url'] : null,
        $fields['bluesky_url'] !== '' ? $fields['bluesky_url'] : null,
        $fields['youtube_url'] !== '' ? $fields['youtube_url'] : null,
        $fields['tiktok_url'] !== '' ? $fields['tiktok_url'] : null,
        $fields['other_social_url'] !== '' ? $fields['other_social_url'] : null,
        $fields['camping_style'] !== '' ? $fields['camping_style'] : null,
        $fields['favorite_places'] !== '' ? $fields['favorite_places'] : null,
        $fields['favorite_camping_music'] !== '' ? $fields['favorite_camping_music'] : null,
        $userId,
    ]);
}

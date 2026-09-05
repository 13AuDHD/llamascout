<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
}

$db = db();

function sitemap_xml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

function sitemap_date(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

$urls = [];

$addUrl = static function (
    string $loc,
    ?string $lastmod = null,
    ?string $changefreq = null,
    ?string $priority = null
) use (&$urls): void {
    $urls[] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
};


/* =========================================================
   STATIC PUBLIC PAGES
   ========================================================= */

$staticPages = [
    ['https://llamascout.com/', null, 'daily', '1.0'],
    ['https://llamascout.com/map.php', null, 'daily', '1.0'],
    ['https://llamascout.com/field-guides', null, 'weekly', '0.8'],
    ['https://llamascout.com/membership', null, 'weekly', '0.8'],
    ['https://llamascout.com/shop.php', null, 'weekly', '0.8'],
    ['https://llamascout.com/about.php', null, 'monthly', '0.6'],
    ['https://llamascout.com/contact.php', null, 'monthly', '0.4'],
    ['https://llamascout.com/privacy.php', null, 'yearly', '0.3'],
    ['https://llamascout.com/privacy-choices.php', null, 'yearly', '0.3'],
    ['https://llamascout.com/terms.php', null, 'yearly', '0.3'],
    ['https://llamascout.com/returns.php', null, 'yearly', '0.3'],
    ['https://llamascout.com/accessibility.php', null, 'yearly', '0.3'],
    ['https://llamascout.com/disclaimer.php', null, 'yearly', '0.3'],
];

foreach ($staticPages as [$loc, $lastmod, $changefreq, $priority]) {
    $addUrl(
        $loc,
        $lastmod,
        $changefreq,
        $priority
    );
}


/* =========================================================
   PUBLISHED PLACES
   ========================================================= */

try {
    $stmt = $db->query(
        'SELECT
            slug,
            updated_at
         FROM places
         WHERE status IN ("active", "featured")
           AND slug IS NOT NULL
           AND slug <> ""
         ORDER BY id ASC'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));

        if ($slug === '') {
            continue;
        }

        $addUrl(
            'https://llamascout.com/place.php?slug='
                . rawurlencode($slug),
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.9'
        );
    }
} catch (Throwable $exception) {
    if (function_exists('llama_log_caught_exception')) {
        llama_log_caught_exception(
            $exception,
            'sitemap.places'
        );
    }
}


/* =========================================================
   FIELD GUIDES
   ========================================================= */

$guideFile =
    __DIR__ . '/data/field-guides.json';

if (is_file($guideFile)) {
    $decoded = json_decode(
        (string) file_get_contents($guideFile),
        true
    );

    if (is_array($decoded)) {
        foreach ($decoded as $guide) {
            if (!is_array($guide)) {
                continue;
            }

            $slug = trim(
                (string) ($guide['slug'] ?? '')
            );

            if ($slug === '') {
                continue;
            }

            $updated =
                sitemap_date(
                    (string) (
                        $guide['updated']
                        ?? $guide['published']
                        ?? ''
                    )
                );

            $addUrl(
                'https://llamascout.com/field-guides/'
                    . rawurlencode($slug),
                $updated,
                'monthly',
                '0.8'
            );
        }
    }
}


/* =========================================================
   PUBLIC MEMBER PROFILES
   ========================================================= */

try {
    $stmt = $db->query(
        'SELECT
            u.username,
            cp.updated_at
         FROM users u
         INNER JOIN community_profiles cp
            ON cp.user_id = u.id
         WHERE u.status = "active"
           AND cp.is_public = 1
           AND u.username IS NOT NULL
           AND u.username <> ""
         ORDER BY u.id ASC'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $username = trim(
            (string) ($row['username'] ?? '')
        );

        if ($username === '') {
            continue;
        }

        $addUrl(
            'https://llamascout.com/'
                . rawurlencode($username),
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'monthly',
            '0.5'
        );
    }
} catch (Throwable $exception) {
    if (function_exists('llama_log_caught_exception')) {
        llama_log_caught_exception(
            $exception,
            'sitemap.public_profiles'
        );
    }
}


/* =========================================================
   OUTPUT
   ========================================================= */

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?= sitemap_xml((string) $url['loc']) ?></loc>
<?php if (!empty($url['lastmod'])): ?>
        <lastmod><?= sitemap_xml((string) $url['lastmod']) ?></lastmod>
<?php endif; ?>
<?php if (!empty($url['changefreq'])): ?>
        <changefreq><?= sitemap_xml((string) $url['changefreq']) ?></changefreq>
<?php endif; ?>
<?php if (!empty($url['priority'])): ?>
        <priority><?= sitemap_xml((string) $url['priority']) ?></priority>
<?php endif; ?>
    </url>
<?php endforeach; ?>
</urlset>

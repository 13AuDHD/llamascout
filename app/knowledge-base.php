<?php

declare(strict_types=1);

/**
 * Public Knowledge Base helpers.
 *
 * KB-04 intentionally reads only published articles and public-facing
 * category data. Search analytics and feedback collection arrive later.
 */

function llama_kb_e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function llama_kb_schema_ready(PDO $db): bool
{
    try {
        $statement = $db->query(
            "SHOW TABLES LIKE 'kb_articles'"
        );

        return $statement !== false
            && (bool) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return false;
    }
}

function llama_kb_public_categories(PDO $db): array
{
    if (!llama_kb_schema_ready($db)) {
        return [];
    }

    $statement = $db->query(
        'SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon_name,
            c.status,
            c.sort_order,
            COUNT(
                CASE
                    WHEN a.status = "published"
                    THEN 1
                    ELSE NULL
                END
            ) AS published_count
         FROM kb_categories c
         LEFT JOIN kb_articles a
            ON a.category_id = c.id
         WHERE c.status IN ("active", "coming_soon")
         GROUP BY
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon_name,
            c.status,
            c.sort_order
         ORDER BY
            c.sort_order ASC,
            c.name ASC'
    );

    return $statement
        ? $statement->fetchAll(PDO::FETCH_ASSOC)
        : [];
}

function llama_kb_public_category_by_slug(
    PDO $db,
    string $slug
): ?array {
    $slug = trim($slug);

    if ($slug === '' || !llama_kb_schema_ready($db)) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon_name,
            c.status,
            c.sort_order,
            COUNT(
                CASE
                    WHEN a.status = "published"
                    THEN 1
                    ELSE NULL
                END
            ) AS published_count
         FROM kb_categories c
         LEFT JOIN kb_articles a
            ON a.category_id = c.id
         WHERE c.slug = ?
           AND c.status IN ("active", "coming_soon")
         GROUP BY
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon_name,
            c.status,
            c.sort_order
         LIMIT 1'
    );

    $statement->execute([$slug]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function llama_kb_public_articles_for_category(
    PDO $db,
    int $categoryId
): array {
    if ($categoryId <= 0 || !llama_kb_schema_ready($db)) {
        return [];
    }

    $statement = $db->prepare(
        'SELECT
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.is_featured,
            a.sort_order,
            a.updated_at,
            a.last_reviewed_at,
            c.name AS category_name,
            c.slug AS category_slug
         FROM kb_articles a
         INNER JOIN kb_categories c
            ON c.id = a.category_id
         WHERE a.category_id = ?
           AND a.status = "published"
           AND c.status = "active"
         ORDER BY
            a.is_featured DESC,
            a.sort_order ASC,
            a.title ASC'
    );

    $statement->execute([$categoryId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function llama_kb_public_featured_articles(
    PDO $db,
    int $limit = 6
): array {
    if (!llama_kb_schema_ready($db)) {
        return [];
    }

    $limit = max(1, min(12, $limit));

    $statement = $db->prepare(
        'SELECT
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.is_featured,
            a.updated_at,
            c.name AS category_name,
            c.slug AS category_slug
         FROM kb_articles a
         INNER JOIN kb_categories c
            ON c.id = a.category_id
         WHERE a.status = "published"
           AND a.is_featured = 1
           AND c.status = "active"
         ORDER BY
            a.sort_order ASC,
            a.updated_at DESC,
            a.title ASC
         LIMIT ' . $limit
    );

    $statement->execute();

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function llama_kb_public_recent_articles(
    PDO $db,
    int $limit = 6
): array {
    if (!llama_kb_schema_ready($db)) {
        return [];
    }

    $limit = max(1, min(12, $limit));

    $statement = $db->prepare(
        'SELECT
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.is_featured,
            a.updated_at,
            c.name AS category_name,
            c.slug AS category_slug
         FROM kb_articles a
         INNER JOIN kb_categories c
            ON c.id = a.category_id
         WHERE a.status = "published"
           AND c.status = "active"
         ORDER BY
            a.updated_at DESC,
            a.title ASC
         LIMIT ' . $limit
    );

    $statement->execute();

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function llama_kb_public_article_by_slug(
    PDO $db,
    string $slug
): ?array {
    $slug = trim($slug);

    if ($slug === '' || !llama_kb_schema_ready($db)) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT
            a.*,
            c.name AS category_name,
            c.slug AS category_slug,
            c.description AS category_description
         FROM kb_articles a
         INNER JOIN kb_categories c
            ON c.id = a.category_id
         WHERE a.slug = ?
           AND a.status = "published"
           AND c.status = "active"
         LIMIT 1'
    );

    $statement->execute([$slug]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function llama_kb_public_related_articles(
    PDO $db,
    int $articleId,
    int $categoryId,
    int $limit = 5
): array {
    if (
        $articleId <= 0
        || $categoryId <= 0
        || !llama_kb_schema_ready($db)
    ) {
        return [];
    }

    $limit = max(1, min(8, $limit));

    $statement = $db->prepare(
        'SELECT
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.is_featured
         FROM kb_articles a
         WHERE a.category_id = ?
           AND a.id <> ?
           AND a.status = "published"
         ORDER BY
            a.is_featured DESC,
            a.sort_order ASC,
            a.title ASC
         LIMIT ' . $limit
    );

    $statement->execute([
        $categoryId,
        $articleId,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function llama_kb_public_article_images(
    PDO $db,
    int $articleId
): array {
    if ($articleId <= 0 || !llama_kb_schema_ready($db)) {
        return [];
    }

    try {
        $statement = $db->prepare(
            'SELECT
                id,
                image_path,
                alt_text,
                caption,
                sort_order
             FROM kb_article_images
             WHERE article_id = ?
               AND status = "active"
             ORDER BY sort_order ASC, id ASC'
        );

        $statement->execute([$articleId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        /*
         * KB-03 may not yet be installed. In that case the public article
         * can still render its text safely without screenshots.
         */
        return [];
    }

    $indexed = [];

    foreach ($rows as $row) {
        $imageId = (int) ($row['id'] ?? 0);

        if ($imageId > 0) {
            $indexed[$imageId] = $row;
        }
    }

    return $indexed;
}

function llama_kb_public_search(
    PDO $db,
    string $query,
    int $limit = 12
): array {
    $query = trim(
        preg_replace('/\s+/u', ' ', $query) ?? $query
    );

    if (
        $query === ''
        || mb_strlen($query) < 2
        || !llama_kb_schema_ready($db)
    ) {
        return [];
    }

    $limit = max(1, min(30, $limit));
    $like = '%' . $query . '%';
    $prefix = $query . '%';

    $statement = $db->prepare(
        'SELECT
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.updated_at,
            c.name AS category_name,
            c.slug AS category_slug,
            MAX(
                CASE
                    WHEN LOWER(a.title) = LOWER(?) THEN 120
                    WHEN LOWER(a.title) LIKE LOWER(?) THEN 100
                    WHEN LOWER(a.title) LIKE LOWER(?) THEN 80
                    WHEN LOWER(a.summary) LIKE LOWER(?) THEN 55
                    WHEN LOWER(k.keyword) = LOWER(?) THEN 95
                    WHEN LOWER(k.keyword) LIKE LOWER(?) THEN 70
                    WHEN LOWER(a.content) LIKE LOWER(?) THEN 30
                    ELSE 0
                END
            ) AS relevance
         FROM kb_articles a
         INNER JOIN kb_categories c
            ON c.id = a.category_id
         LEFT JOIN kb_article_keywords k
            ON k.article_id = a.id
         WHERE a.status = "published"
           AND c.status = "active"
           AND (
                LOWER(a.title) LIKE LOWER(?)
                OR LOWER(a.summary) LIKE LOWER(?)
                OR LOWER(a.content) LIKE LOWER(?)
                OR LOWER(k.keyword) LIKE LOWER(?)
           )
         GROUP BY
            a.id,
            a.title,
            a.slug,
            a.summary,
            a.updated_at,
            c.name,
            c.slug
         ORDER BY
            relevance DESC,
            a.is_featured DESC,
            a.title ASC
         LIMIT ' . $limit
    );

    $statement->execute([
        $query,
        $prefix,
        $like,
        $like,
        $query,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function llama_kb_public_plain_text(
    string $html
): string {
    $html = preg_replace(
        '/\[kb-image\s+id=["\']?\d+["\']?\s*\]/i',
        ' ',
        $html
    ) ?? $html;

    $text = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            strip_tags($html)
        ) ?? ''
    );

    return $text;
}

function llama_kb_public_sanitize_html(
    string $html
): string {
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return nl2br(
            llama_kb_e(
                strip_tags($html)
            )
        );
    }

    $allowedTags = [
        'p',
        'h2',
        'h3',
        'h4',
        'ul',
        'ol',
        'li',
        'strong',
        'em',
        'a',
        'code',
        'pre',
        'blockquote',
        'aside',
        'br',
        'hr',
    ];

    $allowedAttributes = [
        'a' => ['href', 'title'],
        'aside' => ['class'],
        'code' => ['class'],
        'pre' => ['class'],
    ];

    $document = new DOMDocument('1.0', 'UTF-8');
    $previousErrors = libxml_use_internal_errors(true);

    $wrapped =
        '<!doctype html><html><body><div id="kb-root">'
        . $html
        . '</div></body></html>';

    $document->loadHTML(
        mb_convert_encoding(
            $wrapped,
            'HTML-ENTITIES',
            'UTF-8'
        ),
        LIBXML_HTML_NOIMPLIED
        | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);

    $root = $document->getElementById('kb-root');

    if (!$root) {
        return '';
    }

    $walker = function (DOMNode $node) use (
        &$walker,
        $allowedTags,
        $allowedAttributes
    ): void {
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (!in_array($tag, $allowedTags, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore(
                            $child->firstChild,
                            $child
                        );
                    }

                    $node->removeChild($child);
                    continue;
                }

                $allowedForTag = $allowedAttributes[$tag] ?? [];

                if ($child->hasAttributes()) {
                    $remove = [];

                    foreach ($child->attributes as $attribute) {
                        $name = strtolower($attribute->name);

                        if (!in_array($name, $allowedForTag, true)) {
                            $remove[] = $attribute->name;
                            continue;
                        }

                        if ($tag === 'a' && $name === 'href') {
                            $href = trim($attribute->value);

                            if (
                                !preg_match(
                                    '#^(https?://|mailto:|tel:|/|#)#i',
                                    $href
                                )
                            ) {
                                $remove[] = $attribute->name;
                            }
                        }

                        if ($tag === 'aside' && $name === 'class') {
                            $className = trim($attribute->value);

                            if ($className !== 'kb-note') {
                                $remove[] = $attribute->name;
                            }
                        }
                    }

                    foreach ($remove as $attributeName) {
                        $child->removeAttribute($attributeName);
                    }
                }
            }

            $walker($child);
        }
    };

    $walker($root);

    $result = '';

    foreach ($root->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }

    return $result;
}

function llama_kb_public_render_article_content(
    PDO $db,
    array $article,
    string $siteUrl
): string {
    $articleId = (int) ($article['id'] ?? 0);
    $content = (string) ($article['content'] ?? '');
    $images = llama_kb_public_article_images(
        $db,
        $articleId
    );

    $tokens = [];

    $content = preg_replace_callback(
        '/\[kb-image\s+id=["\']?(\d+)["\']?\s*\]/i',
        function (array $matches) use (
            &$tokens,
            $images,
            $siteUrl
        ): string {
            $imageId = (int) ($matches[1] ?? 0);

            if (
                $imageId <= 0
                || !isset($images[$imageId])
            ) {
                return '';
            }

            $image = $images[$imageId];
            $imagePath = '/' . ltrim(
                (string) ($image['image_path'] ?? ''),
                '/'
            );

            $src =
                rtrim($siteUrl, '/')
                . $imagePath;

            $alt = llama_kb_e(
                (string) ($image['alt_text'] ?? '')
            );

            $caption = trim(
                (string) ($image['caption'] ?? '')
            );

            $figure =
                '<figure class="kb-article-image">'
                . '<img src="'
                . llama_kb_e($src)
                . '" alt="'
                . $alt
                . '" loading="lazy" decoding="async">';

            if ($caption !== '') {
                $figure .=
                    '<figcaption>'
                    . llama_kb_e($caption)
                    . '</figcaption>';
            }

            $figure .= '</figure>';

            $token =
                'KBIMAGETOKEN'
                . count($tokens)
                . 'PLACEHOLDER';

            $tokens[$token] = $figure;

            return $token;
        },
        $content
    ) ?? $content;

    $content = llama_kb_public_sanitize_html($content);

    foreach ($tokens as $token => $figure) {
        $content = str_replace(
            $token,
            $figure,
            $content
        );
    }

    return $content;
}

function llama_kb_public_category_icon(
    string $slug
): string {
    return match ($slug) {
        'account' => 'users',
        'accessibility' => 'accessible',
        'badges-points' => 'award',
        'map' => 'map',
        'membership' => 'tag',
        'places' => 'map-pin',
        'privacy' => 'shield',
        'scouts-contributions' => 'binoculars',
        'security' => 'shield',
        'shopping-orders' => 'package',
        'weather' => 'sun',
        'api' => 'article',
        default => 'article',
    };
}

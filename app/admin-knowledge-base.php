<?php

declare(strict_types=1);

/**
 * Basecamp Knowledge Base administration helpers.
 *
 * KB-01 owns the persistent data model.
 * KB-02 owns category/article administration and revision history.
 */

function kb_admin_required_tables(): array
{
    return [
        'kb_categories',
        'kb_articles',
        'kb_article_keywords',
        'kb_article_images',
        'kb_article_revisions',
        'kb_article_feedback',
        'kb_search_log',
    ];
}

function kb_admin_table_exists(PDO $db, string $table): bool
{
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        return false;
    }

    try {
        $statement = $db->query(
            'SHOW TABLES LIKE ' . $db->quote($table)
        );

        return $statement !== false
            && $statement->fetchColumn() !== false;
    } catch (Throwable) {
        return false;
    }
}

function kb_admin_schema_ready(PDO $db): bool
{
    foreach (kb_admin_required_tables() as $table) {
        if (!kb_admin_table_exists($db, $table)) {
            return false;
        }
    }

    return true;
}

function kb_admin_slugify(string $value): string
{
    $value = trim(mb_strtolower($value));

    if ($value === '') {
        return '';
    }

    if (function_exists('transliterator_transliterate')) {
        $transliterated = transliterator_transliterate(
            'Any-Latin; Latin-ASCII',
            $value
        );

        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }
    }

    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');

    return substr($value, 0, 240);
}

function kb_admin_normalize_keyword(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return mb_substr($value, 0, 160);
}

function kb_admin_parse_keywords(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $parts = preg_split(
            '/[\r\n,]+/',
            (string) $value
        ) ?: [];
    }

    $keywords = [];
    $seen = [];

    foreach ($parts as $part) {
        $keyword = trim((string) $part);

        if ($keyword === '') {
            continue;
        }

        $keyword = mb_substr($keyword, 0, 160);
        $normalized = kb_admin_normalize_keyword($keyword);

        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;
        $keywords[] = $keyword;
    }

    return $keywords;
}

function kb_admin_unique_article_slug(
    PDO $db,
    string $requestedSlug,
    string $title,
    int $excludeId = 0
): string {
    $base = kb_admin_slugify(
        $requestedSlug !== ''
            ? $requestedSlug
            : $title
    );

    if ($base === '') {
        $base = 'article';
    }

    $slug = $base;
    $suffix = 2;

    while (true) {
        $sql =
            'SELECT id
             FROM kb_articles
             WHERE slug = ?';

        $params = [$slug];

        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $db->prepare($sql);
        $statement->execute($params);

        if ($statement->fetchColumn() === false) {
            return $slug;
        }

        $tail = '-' . $suffix;
        $slug = substr(
            $base,
            0,
            max(1, 240 - strlen($tail))
        ) . $tail;

        $suffix++;
    }
}

function kb_admin_unique_category_slug(
    PDO $db,
    string $requestedSlug,
    string $name,
    int $excludeId = 0
): string {
    $base = kb_admin_slugify(
        $requestedSlug !== ''
            ? $requestedSlug
            : $name
    );

    if ($base === '') {
        $base = 'topic';
    }

    $slug = substr($base, 0, 140);
    $suffix = 2;

    while (true) {
        $sql =
            'SELECT id
             FROM kb_categories
             WHERE slug = ?';

        $params = [$slug];

        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $db->prepare($sql);
        $statement->execute($params);

        if ($statement->fetchColumn() === false) {
            return $slug;
        }

        $tail = '-' . $suffix;
        $slug = substr(
            $base,
            0,
            max(1, 140 - strlen($tail))
        ) . $tail;

        $suffix++;
    }
}

function kb_admin_categories(
    PDO $db,
    bool $includeHidden = true
): array {
    $sql =
        'SELECT
            c.*,
            COUNT(a.id) AS article_count,
            SUM(a.status = "published") AS published_count,
            SUM(a.status = "draft") AS draft_count
         FROM kb_categories c
         LEFT JOIN kb_articles a
            ON a.category_id = c.id';

    if (!$includeHidden) {
        $sql .=
            " WHERE c.status IN ('active','coming_soon')";
    }

    $sql .=
        ' GROUP BY c.id
          ORDER BY c.sort_order ASC, c.name ASC';

    $statement = $db->query($sql);

    return $statement
        ? $statement->fetchAll(PDO::FETCH_ASSOC)
        : [];
}

function kb_admin_category(PDO $db, int $categoryId): ?array
{
    if ($categoryId <= 0) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT *
         FROM kb_categories
         WHERE id = ?
         LIMIT 1'
    );
    $statement->execute([$categoryId]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function kb_admin_save_category(
    PDO $db,
    array $input
): int {
    $id = max(0, (int) ($input['id'] ?? 0));
    $name = trim((string) ($input['name'] ?? ''));
    $requestedSlug = trim((string) ($input['slug'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $iconName = trim((string) ($input['icon_name'] ?? ''));
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    $sortOrder = (int) ($input['sort_order'] ?? 0);

    if ($name === '') {
        throw new InvalidArgumentException('Topic name is required.');
    }

    if (
        !in_array(
            $status,
            ['active', 'coming_soon', 'hidden'],
            true
        )
    ) {
        throw new InvalidArgumentException('Invalid topic status.');
    }

    if (
        $iconName !== ''
        && preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $iconName
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Icon names may contain lowercase letters, numbers, and hyphens.'
        );
    }

    $slug = kb_admin_unique_category_slug(
        $db,
        $requestedSlug,
        $name,
        $id
    );

    if ($id > 0 && !kb_admin_category($db, $id)) {
        throw new RuntimeException('Knowledge Base topic not found.');
    }

    if ($id > 0) {
        $statement = $db->prepare(
            'UPDATE kb_categories
             SET
                name = ?,
                slug = ?,
                description = ?,
                icon_name = ?,
                status = ?,
                sort_order = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );

        $statement->execute([
            mb_substr($name, 0, 120),
            $slug,
            mb_substr($description, 0, 500),
            mb_substr($iconName, 0, 80),
            $status,
            $sortOrder,
            $id,
        ]);

        return $id;
    }

    $statement = $db->prepare(
        'INSERT INTO kb_categories
            (
                name,
                slug,
                description,
                icon_name,
                status,
                sort_order,
                created_at,
                updated_at
            )
         VALUES
            (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $statement->execute([
        mb_substr($name, 0, 120),
        $slug,
        mb_substr($description, 0, 500),
        mb_substr($iconName, 0, 80),
        $status,
        $sortOrder,
    ]);

    return (int) $db->lastInsertId();
}

function kb_admin_stats(PDO $db): array
{
    $stats = [
        'articles' => 0,
        'published' => 0,
        'drafts' => 0,
        'archived' => 0,
        'featured' => 0,
        'topics' => 0,
        'needs_review' => 0,
        'searches' => 0,
        'zero_result_searches' => 0,
        'negative_feedback' => 0,
    ];

    $articleRow = $db->query(
        'SELECT
            COUNT(*) AS articles,
            SUM(status = "published") AS published,
            SUM(status = "draft") AS drafts,
            SUM(status = "archived") AS archived,
            SUM(is_featured = 1) AS featured,
            SUM(
                status = "published"
                AND (
                    last_reviewed_at IS NULL
                    OR last_reviewed_at
                        < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)
                )
            ) AS needs_review
         FROM kb_articles'
    )->fetch(PDO::FETCH_ASSOC);

    if (is_array($articleRow)) {
        foreach (
            [
                'articles',
                'published',
                'drafts',
                'archived',
                'featured',
                'needs_review',
            ] as $key
        ) {
            $stats[$key] = (int) ($articleRow[$key] ?? 0);
        }
    }

    $stats['topics'] = (int) $db
        ->query('SELECT COUNT(*) FROM kb_categories')
        ->fetchColumn();

    $searchRow = $db->query(
        'SELECT
            COUNT(*) AS searches,
            SUM(result_count = 0) AS zero_result_searches
         FROM kb_search_log'
    )->fetch(PDO::FETCH_ASSOC);

    if (is_array($searchRow)) {
        $stats['searches'] = (int) ($searchRow['searches'] ?? 0);
        $stats['zero_result_searches'] =
            (int) ($searchRow['zero_result_searches'] ?? 0);
    }

    $stats['negative_feedback'] = (int) $db
        ->query(
            'SELECT COUNT(*)
             FROM kb_article_feedback
             WHERE helpful = 0'
        )
        ->fetchColumn();

    return $stats;
}

function kb_admin_articles(
    PDO $db,
    array $filters = []
): array {
    $search = trim((string) ($filters['q'] ?? ''));
    $status = trim((string) ($filters['status'] ?? ''));
    $categoryId = max(
        0,
        (int) ($filters['category_id'] ?? 0)
    );

    $where = [];
    $params = [];

    if ($status !== '') {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }

    if ($categoryId > 0) {
        $where[] = 'a.category_id = ?';
        $params[] = $categoryId;
    }

    if ($search !== '') {
        $where[] =
            '(
                a.title LIKE ?
                OR a.slug LIKE ?
                OR a.summary LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM kb_article_keywords k
                    WHERE k.article_id = a.id
                      AND k.keyword LIKE ?
                )
            )';

        $needle = '%' . $search . '%';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle
        );
    }

    $sql =
        'SELECT
            a.*,
            c.name AS category_name,
            c.slug AS category_slug,
            c.status AS category_status,
            (
                SELECT COUNT(*)
                FROM kb_article_revisions r
                WHERE r.article_id = a.id
            ) AS revision_count,
            (
                SELECT COUNT(*)
                FROM kb_article_feedback f
                WHERE f.article_id = a.id
                  AND f.helpful = 0
            ) AS negative_feedback_count
         FROM kb_articles a
         LEFT JOIN kb_categories c
            ON c.id = a.category_id';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .=
        ' ORDER BY
            CASE a.status
                WHEN "draft" THEN 1
                WHEN "published" THEN 2
                WHEN "archived" THEN 3
                ELSE 4
            END,
            a.is_featured DESC,
            COALESCE(c.sort_order, 999999) ASC,
            a.sort_order ASC,
            a.title ASC';

    $statement = $db->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function kb_admin_article(
    PDO $db,
    int $articleId
): ?array {
    if ($articleId <= 0) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT
            a.*,
            c.name AS category_name,
            c.slug AS category_slug
         FROM kb_articles a
         LEFT JOIN kb_categories c
            ON c.id = a.category_id
         WHERE a.id = ?
         LIMIT 1'
    );

    $statement->execute([$articleId]);
    $article = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($article)) {
        return null;
    }

    $article['keywords'] =
        kb_admin_article_keywords(
            $db,
            $articleId
        );

    return $article;
}

function kb_admin_article_keywords(
    PDO $db,
    int $articleId
): array {
    $statement = $db->prepare(
        'SELECT keyword
         FROM kb_article_keywords
         WHERE article_id = ?
         ORDER BY keyword ASC'
    );

    $statement->execute([$articleId]);

    return array_values(
        array_map(
            static fn(array $row): string =>
                (string) ($row['keyword'] ?? ''),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        )
    );
}

function kb_admin_sync_keywords(
    PDO $db,
    int $articleId,
    array $keywords
): void {
    $db->prepare(
        'DELETE FROM kb_article_keywords
         WHERE article_id = ?'
    )->execute([$articleId]);

    if (!$keywords) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO kb_article_keywords
            (
                article_id,
                keyword,
                normalized_keyword,
                created_at
            )
         VALUES
            (?, ?, ?, UTC_TIMESTAMP())'
    );

    foreach ($keywords as $keyword) {
        $keyword = trim((string) $keyword);
        $normalized = kb_admin_normalize_keyword($keyword);

        if ($keyword === '' || $normalized === '') {
            continue;
        }

        $insert->execute([
            $articleId,
            mb_substr($keyword, 0, 160),
            $normalized,
        ]);
    }
}

function kb_admin_revision_snapshot(
    PDO $db,
    int $articleId,
    int $actorUserId
): int {
    $article = kb_admin_article($db, $articleId);

    if (!$article) {
        throw new RuntimeException(
            'Cannot create a revision for a missing article.'
        );
    }

    $statement = $db->prepare(
        'SELECT COALESCE(MAX(revision_number), 0) + 1
         FROM kb_article_revisions
         WHERE article_id = ?'
    );
    $statement->execute([$articleId]);
    $revisionNumber = (int) $statement->fetchColumn();

    $snapshot = [
        'category_id' => $article['category_id'] ?? null,
        'title' => (string) ($article['title'] ?? ''),
        'slug' => (string) ($article['slug'] ?? ''),
        'summary' => (string) ($article['summary'] ?? ''),
        'content' => (string) ($article['content'] ?? ''),
        'seo_description' =>
            (string) ($article['seo_description'] ?? ''),
        'status' => (string) ($article['status'] ?? 'draft'),
        'is_featured' =>
            !empty($article['is_featured']) ? 1 : 0,
        'sort_order' =>
            (int) ($article['sort_order'] ?? 0),
        'last_reviewed_at' =>
            $article['last_reviewed_at'] ?? null,
        'published_at' =>
            $article['published_at'] ?? null,
        'keywords' =>
            is_array($article['keywords'] ?? null)
                ? $article['keywords']
                : [],
    ];

    $encoded = json_encode(
        $snapshot,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    $insert = $db->prepare(
        'INSERT INTO kb_article_revisions
            (
                article_id,
                revision_number,
                snapshot_json,
                edited_by,
                created_at
            )
         VALUES
            (?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $insert->execute([
        $articleId,
        $revisionNumber,
        $encoded,
        $actorUserId > 0 ? $actorUserId : null,
    ]);

    return $revisionNumber;
}

function kb_admin_normalize_review_date(
    mixed $value
): ?string {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        new DateTimeZone('UTC')
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || (
            is_array($errors)
            && (
                ($errors['warning_count'] ?? 0) > 0
                || ($errors['error_count'] ?? 0) > 0
            )
        )
    ) {
        throw new InvalidArgumentException(
            'Last reviewed must be a valid date.'
        );
    }

    return $date->format('Y-m-d 00:00:00');
}

function kb_admin_save_article(
    PDO $db,
    int $actorUserId,
    array $input
): int {
    $id = max(0, (int) ($input['id'] ?? 0));
    $title = trim((string) ($input['title'] ?? ''));
    $requestedSlug = trim((string) ($input['slug'] ?? ''));
    $summary = trim((string) ($input['summary'] ?? ''));
    $content = trim((string) ($input['content'] ?? ''));
    $seoDescription =
        trim((string) ($input['seo_description'] ?? ''));
    $status =
        strtolower(trim((string) ($input['status'] ?? 'draft')));
    $categoryId =
        max(0, (int) ($input['category_id'] ?? 0));
    $isFeatured =
        !empty($input['is_featured']) ? 1 : 0;
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $lastReviewedAt =
        kb_admin_normalize_review_date(
            $input['last_reviewed_at']
            ?? null
        );
    $keywords =
        kb_admin_parse_keywords(
            $input['keywords']
            ?? []
        );

    if ($title === '') {
        throw new InvalidArgumentException('Article title is required.');
    }

    if ($categoryId <= 0 || !kb_admin_category($db, $categoryId)) {
        throw new InvalidArgumentException(
            'Choose a Knowledge Base topic.'
        );
    }

    if (
        !in_array(
            $status,
            ['draft', 'published', 'archived'],
            true
        )
    ) {
        throw new InvalidArgumentException('Invalid article status.');
    }

    if ($status === 'published' && $content === '') {
        throw new InvalidArgumentException(
            'Published articles need an article body.'
        );
    }

    $slug = kb_admin_unique_article_slug(
        $db,
        $requestedSlug,
        $title,
        $id
    );

    $db->beginTransaction();

    try {
        if ($id > 0) {
            $existing = kb_admin_article($db, $id);

            if (!$existing) {
                throw new RuntimeException(
                    'Knowledge Base article not found.'
                );
            }

            kb_admin_revision_snapshot(
                $db,
                $id,
                $actorUserId
            );

            $publishedAt = $existing['published_at'] ?? null;

            if (
                $status === 'published'
                && empty($publishedAt)
            ) {
                $publishedAt = gmdate('Y-m-d H:i:s');
            } elseif ($status !== 'published') {
                $publishedAt = null;
            }

            $statement = $db->prepare(
                'UPDATE kb_articles
                 SET
                    category_id = ?,
                    title = ?,
                    slug = ?,
                    summary = ?,
                    content = ?,
                    seo_description = ?,
                    status = ?,
                    is_featured = ?,
                    sort_order = ?,
                    last_reviewed_at = ?,
                    published_at = ?,
                    updated_by = ?,
                    updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );

            $statement->execute([
                $categoryId,
                mb_substr($title, 0, 220),
                $slug,
                mb_substr($summary, 0, 700),
                $content,
                mb_substr($seoDescription, 0, 320),
                $status,
                $isFeatured,
                $sortOrder,
                $lastReviewedAt,
                $publishedAt,
                $actorUserId > 0 ? $actorUserId : null,
                $id,
            ]);
        } else {
            $publishedAt =
                $status === 'published'
                    ? gmdate('Y-m-d H:i:s')
                    : null;

            $statement = $db->prepare(
                'INSERT INTO kb_articles
                    (
                        category_id,
                        title,
                        slug,
                        summary,
                        content,
                        seo_description,
                        status,
                        is_featured,
                        sort_order,
                        last_reviewed_at,
                        published_at,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                    )'
            );

            $statement->execute([
                $categoryId,
                mb_substr($title, 0, 220),
                $slug,
                mb_substr($summary, 0, 700),
                $content,
                mb_substr($seoDescription, 0, 320),
                $status,
                $isFeatured,
                $sortOrder,
                $lastReviewedAt,
                $publishedAt,
                $actorUserId > 0 ? $actorUserId : null,
                $actorUserId > 0 ? $actorUserId : null,
            ]);

            $id = (int) $db->lastInsertId();
        }

        kb_admin_sync_keywords(
            $db,
            $id,
            $keywords
        );

        $db->commit();

        return $id;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function kb_admin_set_article_status(
    PDO $db,
    int $actorUserId,
    int $articleId,
    string $status
): void {
    if (
        !in_array(
            $status,
            ['draft', 'published', 'archived'],
            true
        )
    ) {
        throw new InvalidArgumentException('Invalid article status.');
    }

    $article = kb_admin_article($db, $articleId);

    if (!$article) {
        throw new RuntimeException('Knowledge Base article not found.');
    }

    if (
        $status === 'published'
        && trim((string) ($article['content'] ?? '')) === ''
    ) {
        throw new RuntimeException(
            'Add article content before publishing.'
        );
    }

    $db->beginTransaction();

    try {
        kb_admin_revision_snapshot(
            $db,
            $articleId,
            $actorUserId
        );

        $publishedSql =
            $status === 'published'
                ? 'COALESCE(published_at, UTC_TIMESTAMP())'
                : 'NULL';

        $statement = $db->prepare(
            'UPDATE kb_articles
             SET
                status = ?,
                published_at = ' . $publishedSql . ',
                updated_by = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );

        $statement->execute([
            $status,
            $actorUserId > 0 ? $actorUserId : null,
            $articleId,
        ]);

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function kb_admin_duplicate_article(
    PDO $db,
    int $actorUserId,
    int $articleId
): int {
    $source = kb_admin_article($db, $articleId);

    if (!$source) {
        throw new RuntimeException('Knowledge Base article not found.');
    }

    $copyTitle =
        'Copy of ' . (string) ($source['title'] ?? 'Article');

    $copySlug = kb_admin_unique_article_slug(
        $db,
        '',
        $copyTitle
    );

    $db->beginTransaction();

    try {
        $statement = $db->prepare(
            'INSERT INTO kb_articles
                (
                    category_id,
                    title,
                    slug,
                    summary,
                    content,
                    seo_description,
                    status,
                    is_featured,
                    sort_order,
                    last_reviewed_at,
                    published_at,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                )
             VALUES
                (
                    ?, ?, ?, ?, ?, ?, "draft", 0, ?,
                    NULL, NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                )'
        );

        $statement->execute([
            $source['category_id'] ?? null,
            mb_substr($copyTitle, 0, 220),
            $copySlug,
            (string) ($source['summary'] ?? ''),
            (string) ($source['content'] ?? ''),
            (string) ($source['seo_description'] ?? ''),
            (int) ($source['sort_order'] ?? 0),
            $actorUserId > 0 ? $actorUserId : null,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $newId = (int) $db->lastInsertId();

        kb_admin_sync_keywords(
            $db,
            $newId,
            is_array($source['keywords'] ?? null)
                ? $source['keywords']
                : []
        );

        $db->commit();

        return $newId;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function kb_admin_revisions(
    PDO $db,
    int $articleId
): array {
    $statement = $db->prepare(
        'SELECT
            id,
            article_id,
            revision_number,
            snapshot_json,
            edited_by,
            created_at
         FROM kb_article_revisions
         WHERE article_id = ?
         ORDER BY revision_number DESC'
    );

    $statement->execute([$articleId]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        try {
            $snapshot = json_decode(
                (string) ($row['snapshot_json'] ?? ''),
                true,
                64,
                JSON_THROW_ON_ERROR
            );

            $row['snapshot'] =
                is_array($snapshot)
                    ? $snapshot
                    : [];
        } catch (Throwable) {
            $row['snapshot'] = [];
        }
    }
    unset($row);

    return $rows;
}

function kb_admin_restore_revision(
    PDO $db,
    int $actorUserId,
    int $articleId,
    int $revisionId
): void {
    $statement = $db->prepare(
        'SELECT *
         FROM kb_article_revisions
         WHERE id = ?
           AND article_id = ?
         LIMIT 1'
    );

    $statement->execute([
        $revisionId,
        $articleId,
    ]);

    $revision = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($revision)) {
        throw new RuntimeException('Knowledge Base revision not found.');
    }

    $snapshot = json_decode(
        (string) ($revision['snapshot_json'] ?? ''),
        true,
        64,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($snapshot)) {
        throw new RuntimeException(
            'The selected revision could not be read.'
        );
    }

    $categoryId =
        max(0, (int) ($snapshot['category_id'] ?? 0));

    if ($categoryId <= 0 || !kb_admin_category($db, $categoryId)) {
        throw new RuntimeException(
            'The revision points to a topic that no longer exists.'
        );
    }

    $title = trim((string) ($snapshot['title'] ?? ''));

    if ($title === '') {
        throw new RuntimeException(
            'The selected revision does not have a valid title.'
        );
    }

    $slug = kb_admin_unique_article_slug(
        $db,
        (string) ($snapshot['slug'] ?? ''),
        $title,
        $articleId
    );

    $keywords = kb_admin_parse_keywords(
        $snapshot['keywords'] ?? []
    );

    $db->beginTransaction();

    try {
        /*
         * Save the current state before replacing it so Restore itself
         * is reversible.
         */
        kb_admin_revision_snapshot(
            $db,
            $articleId,
            $actorUserId
        );

        /*
         * Restores intentionally return to Draft. An older revision
         * should never silently replace live help content.
         */
        $update = $db->prepare(
            'UPDATE kb_articles
             SET
                category_id = ?,
                title = ?,
                slug = ?,
                summary = ?,
                content = ?,
                seo_description = ?,
                status = "draft",
                is_featured = ?,
                sort_order = ?,
                last_reviewed_at = NULL,
                published_at = NULL,
                updated_by = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );

        $update->execute([
            $categoryId,
            mb_substr($title, 0, 220),
            $slug,
            mb_substr(
                (string) ($snapshot['summary'] ?? ''),
                0,
                700
            ),
            (string) ($snapshot['content'] ?? ''),
            mb_substr(
                (string) ($snapshot['seo_description'] ?? ''),
                0,
                320
            ),
            !empty($snapshot['is_featured']) ? 1 : 0,
            (int) ($snapshot['sort_order'] ?? 0),
            $actorUserId > 0 ? $actorUserId : null,
            $articleId,
        ]);

        kb_admin_sync_keywords(
            $db,
            $articleId,
            $keywords
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

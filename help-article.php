<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/knowledge-base.php';
require_once __DIR__ . '/app/icons.php';

$db = db();

$slug = trim((string) ($_GET['slug'] ?? ''));
$article =
    llama_kb_public_article_by_slug(
        $db,
        $slug
    );

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Help Article Not Found | Llama Scout';
    $pageDescription = 'The requested Llama Scout Knowledge Base article could not be found.';
    $canonicalUrl = 'https://llamascout.com/help-article.php';
    $pageStyles = ['knowledge-base.css'];

    require __DIR__ . '/partials/header.php';
    ?>
    <main class="kb-public">
        <section class="kb-state-page">
            <div class="kb-container kb-state-card">
                <p class="kb-eyebrow">Knowledge Base</p>
                <h1>That article is not in the pasture.</h1>
                <p>
                    It may have moved, been renamed, or not be published.
                </p>
                <a class="kb-button" href="/help.php">
                    Search the Knowledge Base
                </a>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$pageTitle =
    (string) ($article['title'] ?? 'Help')
    . ' | Llama Scout Knowledge Base';

$pageDescription =
    trim((string) ($article['seo_description'] ?? ''));

if ($pageDescription === '') {
    $pageDescription =
        trim((string) ($article['summary'] ?? ''));
}

if ($pageDescription === '') {
    $pageDescription =
        mb_substr(
            llama_kb_public_plain_text(
                (string) ($article['content'] ?? '')
            ),
            0,
            300
        );
}

$canonicalUrl =
    'https://llamascout.com/help-article.php?slug='
    . rawurlencode(
        (string) ($article['slug'] ?? '')
    );

$pageStyles = ['knowledge-base.css'];

$relatedArticles =
    llama_kb_public_related_articles(
        $db,
        (int) ($article['id'] ?? 0),
        (int) ($article['category_id'] ?? 0),
        5
    );

$renderedContent =
    llama_kb_public_render_article_content(
        $db,
        $article,
        $siteUrl ?? 'https://llamascout.com'
    );

require __DIR__ . '/partials/header.php';

/*
 * Header establishes the canonical public $siteUrl value. Re-rendering
 * after the header keeps screenshot URLs consistent with the active site.
 */
$renderedContent =
    llama_kb_public_render_article_content(
        $db,
        $article,
        $siteUrl
    );
?>

<main class="kb-public">
    <section class="kb-article-hero">
        <div class="kb-container">
            <nav class="kb-breadcrumbs" aria-label="Breadcrumb">
                <a href="/help.php">Knowledge Base</a>
                <span aria-hidden="true">/</span>
                <a
                    href="/help-topic.php?slug=<?= rawurlencode(
                        (string) ($article['category_slug'] ?? '')
                    ) ?>"
                >
                    <?= llama_kb_e($article['category_name'] ?? '') ?>
                </a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">
                    <?= llama_kb_e($article['title'] ?? '') ?>
                </span>
            </nav>

            <p class="kb-eyebrow">
                <?= llama_kb_e($article['category_name'] ?? '') ?>
            </p>

            <h1><?= llama_kb_e($article['title'] ?? '') ?></h1>

            <?php if (trim((string) ($article['summary'] ?? '')) !== ''): ?>
                <p class="kb-article-lede">
                    <?= llama_kb_e($article['summary'] ?? '') ?>
                </p>
            <?php endif; ?>

            <div class="kb-article-meta">
                <?php if (!empty($article['last_reviewed_at'])): ?>
                    <span>
                        Last reviewed
                        <?= llama_kb_e(
                            date(
                                'F j, Y',
                                strtotime(
                                    (string) $article['last_reviewed_at']
                                )
                            )
                        ) ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($article['updated_at'])): ?>
                    <span>
                        Updated
                        <?= llama_kb_e(
                            date(
                                'F j, Y',
                                strtotime(
                                    (string) $article['updated_at']
                                )
                            )
                        ) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="kb-section">
        <div class="kb-container kb-article-layout">
            <article class="kb-article-body">
                <?= $renderedContent ?>

                <div class="kb-article-end">
                    <span aria-hidden="true">🦙</span>
                    <p>
                        End of article. The llama has no additional footnotes
                        hidden under the hay.
                    </p>
                </div>
            </article>

            <aside class="kb-article-sidebar">
                <div class="kb-sidebar-card">
                    <p class="kb-eyebrow">Need Another Answer?</p>
                    <a class="kb-button is-secondary" href="/help.php">
                        Search Knowledge Base
                    </a>
                    <a class="kb-text-link" href="/contact.php">
                        Contact Support
                    </a>
                </div>

                <?php if ($relatedArticles): ?>
                    <div class="kb-sidebar-card">
                        <h2>Related articles</h2>

                        <div class="kb-related-list">
                            <?php foreach ($relatedArticles as $related): ?>
                                <a
                                    href="/help-article.php?slug=<?= rawurlencode(
                                        (string) ($related['slug'] ?? '')
                                    ) ?>"
                                >
                                    <?= llama_kb_e($related['title'] ?? '') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/knowledge-base.php';
require_once __DIR__ . '/app/icons.php';

$db = db();

$slug = trim((string) ($_GET['slug'] ?? ''));
$category =
    llama_kb_public_category_by_slug(
        $db,
        $slug
    );

if (
    !$category
    || (string) ($category['status'] ?? '') !== 'active'
) {
    http_response_code(404);
    $pageTitle = 'Knowledge Base Topic Not Found | Llama Scout';
    $pageDescription = 'The requested Llama Scout Knowledge Base topic could not be found.';
    $canonicalUrl = 'https://llamascout.com/help-topic.php';
    $pageStyles = ['knowledge-base.css'];

    require __DIR__ . '/partials/header.php';
    ?>
    <main class="kb-public">
        <section class="kb-state-page">
            <div class="kb-container kb-state-card">
                <p class="kb-eyebrow">Knowledge Base</p>
                <h1>That topic wandered off.</h1>
                <p>
                    It may have been renamed, moved, or not published yet.
                </p>
                <a class="kb-button" href="/help.php">
                    Back to Knowledge Base
                </a>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$articles =
    llama_kb_public_articles_for_category(
        $db,
        (int) ($category['id'] ?? 0)
    );

$pageTitle =
    (string) ($category['name'] ?? 'Help')
    . ' Help | Llama Scout';

$pageDescription =
    trim((string) ($category['description'] ?? ''));

if ($pageDescription === '') {
    $pageDescription =
        'Llama Scout Knowledge Base articles about '
        . (string) ($category['name'] ?? 'this topic')
        . '.';
}

$canonicalUrl =
    'https://llamascout.com/help-topic.php?slug='
    . rawurlencode(
        (string) ($category['slug'] ?? '')
    );

$pageStyles = ['knowledge-base.css'];

require __DIR__ . '/partials/header.php';
?>

<main class="kb-public">
    <section class="kb-topic-hero">
        <div class="kb-container">
            <nav class="kb-breadcrumbs" aria-label="Breadcrumb">
                <a href="/help.php">Knowledge Base</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">
                    <?= llama_kb_e($category['name'] ?? '') ?>
                </span>
            </nav>

            <div class="kb-topic-hero-heading">
                <div class="kb-topic-icon is-large" aria-hidden="true">
                    <?= llama_icon(
                        llama_kb_public_category_icon(
                            (string) ($category['slug'] ?? '')
                        )
                    ) ?>
                </div>

                <div>
                    <p class="kb-eyebrow">Topic</p>
                    <h1><?= llama_kb_e($category['name'] ?? '') ?></h1>
                    <p>
                        <?= llama_kb_e($category['description'] ?? '') ?>
                    </p>
                </div>
            </div>

            <div class="kb-search-shell is-compact" data-kb-search-shell>
                <label class="kb-search-label" for="kb-search">
                    Search all Knowledge Base articles
                </label>

                <div class="kb-search-box">
                    <i aria-hidden="true"><?= llama_icon('search') ?></i>
                    <input
                        id="kb-search"
                        type="search"
                        inputmode="search"
                        autocomplete="off"
                        placeholder="Search the Knowledge Base"
                        aria-controls="kb-search-results"
                        aria-expanded="false"
                        data-kb-search
                    >
                    <button
                        type="button"
                        class="kb-search-clear"
                        aria-label="Clear Knowledge Base search"
                        data-kb-search-clear
                        hidden
                    >
                        <?= llama_icon('x') ?>
                    </button>
                </div>

                <div
                    class="kb-search-results"
                    id="kb-search-results"
                    role="region"
                    aria-live="polite"
                    aria-label="Knowledge Base search results"
                    data-kb-search-results
                    hidden
                ></div>
            </div>
        </div>
    </section>

    <section class="kb-section">
        <div class="kb-container kb-topic-layout">
            <div class="kb-section-heading">
                <div>
                    <p class="kb-eyebrow">Articles</p>
                    <h2>
                        <?= count($articles) ?>
                        <?= count($articles) === 1 ? 'answer' : 'answers' ?>
                        in this topic
                    </h2>
                </div>
            </div>

            <?php if (!$articles): ?>
                <div class="kb-empty-public">
                    <strong>The topic exists. The articles are still grazing.</strong>
                    <p>
                        Nothing has been published here yet.
                    </p>
                </div>
            <?php else: ?>
                <div class="kb-topic-article-list">
                    <?php foreach ($articles as $article): ?>
                        <a
                            class="kb-topic-article-row"
                            href="/help-article.php?slug=<?= rawurlencode(
                                (string) ($article['slug'] ?? '')
                            ) ?>"
                        >
                            <div>
                                <div class="kb-topic-article-title">
                                    <h3><?= llama_kb_e($article['title'] ?? '') ?></h3>

                                    <?php if (!empty($article['is_featured'])): ?>
                                        <span>Featured</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (trim((string) ($article['summary'] ?? '')) !== ''): ?>
                                    <p>
                                        <?= llama_kb_e($article['summary'] ?? '') ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <i aria-hidden="true">
                                <?= llama_icon('arrow-right') ?>
                            </i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script
    src="<?= llama_kb_e($siteUrl . '/js/knowledge-base.js') ?>"
    defer
></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

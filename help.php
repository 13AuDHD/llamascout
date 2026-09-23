<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/knowledge-base.php';
require_once __DIR__ . '/app/icons.php';

$db = db();

$pageTitle = 'Knowledge Base | Llama Scout';
$pageDescription =
    'Search the Llama Scout Knowledge Base for help with your account, map, Places, security, shopping, membership, Scouts, weather, and more.';
$canonicalUrl = 'https://llamascout.com/help.php';

$pageStyles = ['knowledge-base.css'];

$categories = llama_kb_public_categories($db);
$featuredArticles =
    llama_kb_public_featured_articles(
        $db,
        6
    );

if (!$featuredArticles) {
    $featuredArticles =
        llama_kb_public_recent_articles(
            $db,
            6
        );
}

$publishedTotal = 0;

foreach ($categories as $category) {
    $publishedTotal +=
        (int) ($category['published_count'] ?? 0);
}

require __DIR__ . '/partials/header.php';
?>

<main class="kb-public">
    <section class="kb-hero">
        <div class="kb-container">
            <p class="kb-eyebrow">Knowledge Base</p>
            <h1>What can this llama help you find?</h1>
            <p class="kb-hero-copy">
                Search the customer-facing encyclopedia of Llama Scout.
                Features, settings, Places, security, shopping, and the
                occasional thing a llama had to write down so nobody forgot.
            </p>

            <div class="kb-search-shell" data-kb-search-shell>
                <label class="kb-search-label" for="kb-search">
                    Search the Knowledge Base
                </label>

                <div class="kb-search-box">
                    <i aria-hidden="true"><?= llama_icon('search') ?></i>
                    <input
                        id="kb-search"
                        type="search"
                        inputmode="search"
                        autocomplete="off"
                        placeholder="Try “phone”, “password”, “map”, or “refund”"
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

            <?php if ($publishedTotal === 0): ?>
                <p class="kb-launch-note">
                    The shelves are built. Articles will appear here as
                    Basecamp publishes them.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="kb-section">
        <div class="kb-container">
            <div class="kb-section-heading">
                <div>
                    <p class="kb-eyebrow">Browse</p>
                    <h2>Topics</h2>
                </div>
                <p>
                    Start broad, then let the llama narrow things down.
                </p>
            </div>

            <div class="kb-topic-grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $isComingSoon =
                        (string) ($category['status'] ?? '') === 'coming_soon';

                    $topicUrl =
                        '/help-topic.php?slug='
                        . rawurlencode(
                            (string) ($category['slug'] ?? '')
                        );
                    ?>
                    <?php if ($isComingSoon): ?>
                        <article class="kb-topic-card is-coming-soon">
                    <?php else: ?>
                        <a
                            class="kb-topic-card"
                            href="<?= llama_kb_e($topicUrl) ?>"
                        >
                    <?php endif; ?>

                        <div class="kb-topic-icon" aria-hidden="true">
                            <?= llama_icon(
                                llama_kb_public_category_icon(
                                    (string) ($category['slug'] ?? '')
                                )
                            ) ?>
                        </div>

                        <div class="kb-topic-copy">
                            <div class="kb-topic-title-row">
                                <h3><?= llama_kb_e($category['name'] ?? '') ?></h3>

                                <?php if ($isComingSoon): ?>
                                    <span class="kb-coming-soon">
                                        Coming Soon
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p>
                                <?= llama_kb_e($category['description'] ?? '') ?>
                            </p>

                            <?php if (!$isComingSoon): ?>
                                <span class="kb-topic-count">
                                    <?= (int) ($category['published_count'] ?? 0) ?>
                                    <?= (int) ($category['published_count'] ?? 0) === 1 ? 'article' : 'articles' ?>
                                </span>
                            <?php endif; ?>
                        </div>

                    <?php if ($isComingSoon): ?>
                        </article>
                    <?php else: ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($featuredArticles): ?>
        <section class="kb-section kb-section-soft">
            <div class="kb-container">
                <div class="kb-section-heading">
                    <div>
                        <p class="kb-eyebrow">Start Here</p>
                        <h2>Useful articles</h2>
                    </div>
                    <p>
                        A few things our support llamas think are worth
                        keeping within hoof's reach.
                    </p>
                </div>

                <div class="kb-article-card-grid">
                    <?php foreach ($featuredArticles as $article): ?>
                        <a
                            class="kb-article-card"
                            href="/help-article.php?slug=<?= rawurlencode(
                                (string) ($article['slug'] ?? '')
                            ) ?>"
                        >
                            <span class="kb-article-topic">
                                <?= llama_kb_e($article['category_name'] ?? '') ?>
                            </span>
                            <h3><?= llama_kb_e($article['title'] ?? '') ?></h3>

                            <?php if (trim((string) ($article['summary'] ?? '')) !== ''): ?>
                                <p>
                                    <?= llama_kb_e($article['summary'] ?? '') ?>
                                </p>
                            <?php endif; ?>

                            <span class="kb-read-link">
                                Read article
                                <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="kb-support-bridge">
        <div class="kb-container">
            <div class="kb-support-card">
                <div>
                    <p class="kb-eyebrow">Still Stuck?</p>
                    <h2>Ask a human-shaped support llama.</h2>
                    <p>
                        If the Knowledge Base does not answer it, send Support
                        the question and tell us what you were trying to do.
                    </p>
                </div>

                <a class="kb-button" href="/contact.php">
                    Contact Support
                </a>
            </div>
        </div>
    </section>
</main>

<script
    src="<?= llama_kb_e($siteUrl . '/js/knowledge-base.js') ?>"
    defer
></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-knowledge-base.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);
$config = llama_config();
$adminBaseUrl = rtrim(
    (string) (
        $config['app']['admin_url']
        ?? 'https://admin.llamascout.com'
    ),
    '/'
);

$notice = '';
$error = '';
$schemaReady = kb_admin_schema_ready($db);

$updatedStatus = trim((string) ($_GET['updated'] ?? ''));

if ($updatedStatus === 'published') {
    $notice = 'Article published.';
} elseif ($updatedStatus === 'draft') {
    $notice = 'Article moved to Draft.';
} elseif ($updatedStatus === 'archived') {
    $notice = 'Article archived.';
}

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $articleId = max(0, (int) ($_POST['article_id'] ?? 0));

        try {
            if ($articleId <= 0) {
                throw new InvalidArgumentException('Choose an article.');
            }

            if ($action === 'publish') {
                kb_admin_set_article_status(
                    $db,
                    $actorUserId,
                    $articleId,
                    'published'
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base.php?updated=published'
                );
                exit;
            } elseif ($action === 'draft') {
                kb_admin_set_article_status(
                    $db,
                    $actorUserId,
                    $articleId,
                    'draft'
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base.php?updated=draft'
                );
                exit;
            } elseif ($action === 'archive') {
                kb_admin_set_article_status(
                    $db,
                    $actorUserId,
                    $articleId,
                    'archived'
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base.php?updated=archived'
                );
                exit;
            } elseif ($action === 'duplicate') {
                $newId = kb_admin_duplicate_article(
                    $db,
                    $actorUserId,
                    $articleId
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base-article.php?id='
                    . $newId
                    . '&copied=1'
                );
                exit;
            } else {
                throw new InvalidArgumentException(
                    'Unknown Knowledge Base action.'
                );
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));

$stats = [];
$categories = [];
$articles = [];

if ($schemaReady) {
    try {
        $stats = kb_admin_stats($db);
        $categories = kb_admin_categories($db);
        $articles = kb_admin_articles(
            $db,
            [
                'q' => $q,
                'status' => $status,
                'category_id' => $categoryId,
            ]
        );
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$adminPageTitle = 'Knowledge Base';
$adminPageEyebrow = 'Communications';
$adminActiveNav = 'knowledge-base';

$adminPageActions =
    '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base-categories.php')
    . '">'
    . moderation_e('Topics')
    . '</a>'
    . '<a class="admin-button is-primary" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base-article.php')
    . '">'
    . moderation_e('New article')
    . '</a>';

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base.css') ?>"
>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= moderation_e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<?php if (!$schemaReady): ?>
    <section class="admin-panel kb-install-panel">
        <header class="admin-panel-header">
            <div>
                <p>KB-01</p>
                <h2>The Knowledge Base tables are not installed yet.</h2>
            </div>
        </header>

        <p>
            Run <strong>INSTALL-KB01-02.sql</strong> once against the
            Llama Scout database, then reload this page. The installer
            creates the seven Knowledge Base tables and the starting
            topic list.
        </p>
    </section>

    <?php require __DIR__ . '/_footer.php'; ?>
    <?php exit; ?>
<?php endif; ?>

<section class="kb-stat-grid" aria-label="Knowledge Base status">
    <?php
    $statCards = [
        ['Articles', (int) ($stats['articles'] ?? 0)],
        ['Published', (int) ($stats['published'] ?? 0)],
        ['Drafts', (int) ($stats['drafts'] ?? 0)],
        ['Topics', (int) ($stats['topics'] ?? 0)],
        ['Needs review', (int) ($stats['needs_review'] ?? 0)],
        ['Search gaps', (int) ($stats['zero_result_searches'] ?? 0)],
    ];
    ?>

    <?php foreach ($statCards as [$label, $value]): ?>
        <article class="kb-stat-card">
            <span><?= moderation_e($label) ?></span>
            <strong><?= number_format((int) $value) ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Library</p>
            <h2>Articles</h2>
        </div>

        <span class="kb-result-count">
            <?= number_format(count($articles)) ?>
            result<?= count($articles) === 1 ? '' : 's' ?>
        </span>
    </header>

    <form method="get" class="kb-filter-grid">
        <label>
            <span>Search</span>
            <input
                type="search"
                name="q"
                value="<?= moderation_e($q) ?>"
                placeholder="Title, slug, summary, keyword..."
            >
        </label>

        <label>
            <span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'published', 'archived'] as $option): ?>
                    <option
                        value="<?= moderation_e($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= moderation_e(ucfirst($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Topic</span>
            <select name="category_id">
                <option value="0">
                    All Topics (<?= number_format((int) ($stats['articles'] ?? 0)) ?>)
                </option>
        
                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>
                    >
                        <?= moderation_e((string) $category['name']) ?>
                        (<?= number_format((int) ($category['article_count'] ?? 0)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="kb-filter-actions">
            <button class="admin-button is-primary" type="submit">
                Filter
            </button>

            <a
                class="admin-button"
                href="<?= moderation_e($adminBaseUrl . '/knowledge-base.php') ?>"
            >
                Clear
            </a>
        </div>
    </form>

    <?php if (!$articles): ?>
        <div class="kb-empty">
            <strong>No articles found.</strong>
            <span>
                The llamas checked behind the hay bale too.
                Try another filter or create the first article.
            </span>
        </div>
    <?php else: ?>
        <div class="kb-article-list">
            <?php foreach ($articles as $article): ?>
                <?php
                $articleId = (int) ($article['id'] ?? 0);
                $articleStatus = (string) ($article['status'] ?? 'draft');
                $lastReviewed = trim(
                    (string) ($article['last_reviewed_at'] ?? '')
                );
                $needsReview =
                    $articleStatus === 'published'
                    && (
                        $lastReviewed === ''
                        || strtotime($lastReviewed) < strtotime('-180 days')
                    );
                ?>

                <article class="kb-article-row">
                    <div class="kb-article-main">
                        <div class="kb-article-heading">
                            <a
                                href="<?= moderation_e(
                                    $adminBaseUrl
                                    . '/knowledge-base-article.php?id='
                                    . $articleId
                                ) ?>"
                            >
                                <?= moderation_e((string) $article['title']) ?>
                            </a>

                            <?php if (!empty($article['is_featured'])): ?>
                                <span class="kb-pill is-featured">Featured</span>
                            <?php endif; ?>

                            <span class="kb-pill is-<?= moderation_e($articleStatus) ?>">
                                <?= moderation_e(ucfirst($articleStatus)) ?>
                            </span>

                            <?php if ($needsReview): ?>
                                <span class="kb-pill is-review">Review due</span>
                            <?php endif; ?>
                        </div>

                        <p>
                            <?= moderation_e(
                                (string) (
                                    $article['summary']
                                    ?: 'No short summary yet.'
                                )
                            ) ?>
                        </p>

                        <div class="kb-article-meta">
                            <span>
                                <?= moderation_e(
                                    (string) (
                                        $article['category_name']
                                        ?: 'No topic'
                                    )
                                ) ?>
                            </span>
                            <span>/<?= moderation_e((string) $article['slug']) ?></span>
                            <span>
                                <?= (int) ($article['revision_count'] ?? 0) ?>
                                revision<?= (int) ($article['revision_count'] ?? 0) === 1 ? '' : 's' ?>
                            </span>
                            <span>
                                Updated
                                <?= moderation_e(
                                    (string) ($article['updated_at'] ?? '')
                                ) ?>
                            </span>
                        </div>
                    </div>

                    <div class="kb-row-actions">
                        <a
                            class="admin-button"
                            href="<?= moderation_e(
                                $adminBaseUrl
                                . '/knowledge-base-article.php?id='
                                . $articleId
                            ) ?>"
                        >
                            Edit
                        </a>

                        <?php if ((int) ($article['revision_count'] ?? 0) > 0): ?>
                            <a
                                class="admin-button"
                                href="<?= moderation_e(
                                    $adminBaseUrl
                                    . '/knowledge-base-revisions.php?id='
                                    . $articleId
                                ) ?>"
                            >
                                History
                            </a>
                        <?php endif; ?>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= moderation_e(moderation_csrf_token()) ?>"
                            >
                            <input
                                type="hidden"
                                name="article_id"
                                value="<?= $articleId ?>"
                            >

                            <?php if ($articleStatus === 'published'): ?>
                                <button
                                    class="admin-button"
                                    type="submit"
                                    name="action"
                                    value="draft"
                                >
                                    Unpublish
                                </button>
                            <?php elseif ($articleStatus !== 'archived'): ?>
                                <button
                                    class="admin-button"
                                    type="submit"
                                    name="action"
                                    value="publish"
                                >
                                    Publish
                                </button>
                            <?php endif; ?>

                            <button
                                class="admin-button"
                                type="submit"
                                name="action"
                                value="duplicate"
                            >
                                Duplicate
                            </button>

                            <?php if ($articleStatus !== 'archived'): ?>
                                <button
                                    class="admin-button"
                                    type="submit"
                                    name="action"
                                    value="archive"
                                >
                                    Archive
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

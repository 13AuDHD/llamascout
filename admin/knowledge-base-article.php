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

$schemaReady = kb_admin_schema_ready($db);
$articleId = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$error = '';

if (!$schemaReady) {
    header('Location: ' . $adminBaseUrl . '/knowledge-base.php');
    exit;
}

$categories = kb_admin_categories($db);

$blankBody = <<<'HTML'
<h2>What it does</h2>
<p></p>

<h2>Why it exists</h2>
<p></p>

<h2>Where to find it</h2>
<p></p>

<h2>How to use it</h2>
<ol>
    <li></li>
</ol>

<h2>What happens next</h2>
<p></p>

<h2>Troubleshooting</h2>
<p></p>
HTML;

$article = [
    'id' => 0,
    'category_id' => '',
    'title' => '',
    'slug' => '',
    'summary' => '',
    'content' => $blankBody,
    'seo_description' => '',
    'status' => 'draft',
    'is_featured' => 0,
    'sort_order' => 0,
    'last_reviewed_at' => null,
    'keywords' => [],
];

if ($articleId > 0) {
    $existing = kb_admin_article($db, $articleId);

    if (!$existing) {
        http_response_code(404);
        $error = 'Knowledge Base article not found.';
    } else {
        $article = $existing;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $savedId = kb_admin_save_article(
                $db,
                $actorUserId,
                $_POST
            );

            header(
                'Location: '
                . $adminBaseUrl
                . '/knowledge-base-article.php?id='
                . $savedId
                . '&saved=1'
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            $article = array_merge(
                $article,
                [
                    'id' => $articleId,
                    'category_id' =>
                        (int) ($_POST['category_id'] ?? 0),
                    'title' =>
                        (string) ($_POST['title'] ?? ''),
                    'slug' =>
                        (string) ($_POST['slug'] ?? ''),
                    'summary' =>
                        (string) ($_POST['summary'] ?? ''),
                    'content' =>
                        (string) ($_POST['content'] ?? ''),
                    'seo_description' =>
                        (string) ($_POST['seo_description'] ?? ''),
                    'status' =>
                        (string) ($_POST['status'] ?? 'draft'),
                    'is_featured' =>
                        !empty($_POST['is_featured']) ? 1 : 0,
                    'sort_order' =>
                        (int) ($_POST['sort_order'] ?? 0),
                    'last_reviewed_at' =>
                        (string) ($_POST['last_reviewed_at'] ?? ''),
                    'keywords' =>
                        kb_admin_parse_keywords(
                            $_POST['keywords']
                            ?? ''
                        ),
                ]
            );
        }
    }
}

$adminPageTitle =
    $articleId > 0
        ? 'Edit Knowledge Base Article'
        : 'New Knowledge Base Article';

$adminPageEyebrow = 'Knowledge Base';
$adminActiveNav = 'knowledge-base';

$adminPageActions =
    '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base.php')
    . '">All articles</a>'
    . '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base-categories.php')
    . '">Topics</a>';

if ($articleId > 0) {
    $adminPageActions .=
        '<a class="admin-button" href="'
        . moderation_e(
            $adminBaseUrl
            . '/knowledge-base-revisions.php?id='
            . $articleId
        )
        . '">Revision history</a>';
}

require __DIR__ . '/_header.php';

$reviewDate = '';
if (!empty($article['last_reviewed_at'])) {
    $timestamp = strtotime((string) $article['last_reviewed_at']);

    if ($timestamp !== false) {
        $reviewDate = gmdate('Y-m-d', $timestamp);
    }
}

$keywordText = implode(
    ', ',
    is_array($article['keywords'] ?? null)
        ? $article['keywords']
        : []
);
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base.css') ?>"
>

<?php if (isset($_GET['saved'])): ?>
    <div class="admin-user-notice is-success">
        Article saved. The llama did not eat your homework.
    </div>
<?php endif; ?>

<?php if (isset($_GET['copied'])): ?>
    <div class="admin-user-notice is-success">
        Copy created as a Draft.
    </div>
<?php endif; ?>

<?php if (isset($_GET['restored'])): ?>
    <div class="admin-user-notice is-success">
        Revision restored as a Draft. Review it before publishing.
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="kb-editor">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >
    <input
        type="hidden"
        name="id"
        value="<?= (int) ($article['id'] ?? $articleId) ?>"
    >

    <div class="kb-editor-main">
        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Article</p>
                    <h2>What the llama knows</h2>
                </div>
            </header>

            <div class="kb-form-grid">
                <label class="kb-field is-wide">
                    <span>Title</span>
                    <input
                        type="text"
                        name="title"
                        maxlength="220"
                        required
                        value="<?= moderation_e((string) ($article['title'] ?? '')) ?>"
                        placeholder="Changing my password"
                    >
                </label>

                <label class="kb-field">
                    <span>Topic</span>
                    <select name="category_id" required>
                        <option value="">Choose a topic</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (int) ($article['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>
                            >
                                <?= moderation_e((string) $category['name']) ?>
                                <?= (string) $category['status'] === 'coming_soon' ? ' (Coming Soon)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="kb-field">
                    <span>Slug</span>
                    <input
                        type="text"
                        name="slug"
                        maxlength="240"
                        value="<?= moderation_e((string) ($article['slug'] ?? '')) ?>"
                        placeholder="changing-my-password"
                    >
                    <small>
                        Leave blank and Basecamp makes one from the title.
                    </small>
                </label>

                <label class="kb-field is-wide">
                    <span>Short summary</span>
                    <textarea
                        name="summary"
                        rows="3"
                        maxlength="700"
                        placeholder="The short answer shown in search results."
                    ><?= moderation_e((string) ($article['summary'] ?? '')) ?></textarea>
                </label>

                <label class="kb-field is-wide">
                    <span>Search keywords and synonyms</span>
                    <textarea
                        name="keywords"
                        rows="3"
                        placeholder="phone, mobile number, telephone, SMS"
                    ><?= moderation_e($keywordText) ?></textarea>
                    <small>
                        Separate words or phrases with commas or new lines.
                        These are searchable even when they are not in the title.
                    </small>
                </label>

                <label class="kb-field is-wide">
                    <span>Article body</span>
                    <textarea
                        class="kb-body-editor"
                        name="content"
                        rows="26"
                        spellcheck="true"
                    ><?= moderation_e((string) ($article['content'] ?? '')) ?></textarea>
                    <small>
                        Trusted Basecamp HTML for now. KB-03 will add the
                        screenshot uploader and friendlier article editor.
                        Keep the section headings so articles stay predictable.
                    </small>
                </label>

                <label class="kb-field is-wide">
                    <span>SEO description</span>
                    <textarea
                        name="seo_description"
                        rows="2"
                        maxlength="320"
                        placeholder="Optional. Used later on the public article page."
                    ><?= moderation_e((string) ($article['seo_description'] ?? '')) ?></textarea>
                </label>
            </div>
        </section>
    </div>

    <aside class="kb-editor-side">
        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Publishing</p>
                    <h2>Status</h2>
                </div>
            </header>

            <div class="kb-side-fields">
                <label class="kb-field">
                    <span>Status</span>
                    <select name="status">
                        <?php foreach (['draft', 'published', 'archived'] as $option): ?>
                            <option
                                value="<?= moderation_e($option) ?>"
                                <?= (string) ($article['status'] ?? 'draft') === $option ? 'selected' : '' ?>
                            >
                                <?= moderation_e(ucfirst($option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="kb-checkbox">
                    <input
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        <?= !empty($article['is_featured']) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Featured article</strong>
                        <small>
                            Eligible for prominent placement on the public
                            Knowledge Base later.
                        </small>
                    </span>
                </label>

                <label class="kb-field">
                    <span>Sort order</span>
                    <input
                        type="number"
                        name="sort_order"
                        step="1"
                        value="<?= (int) ($article['sort_order'] ?? 0) ?>"
                    >
                </label>

                <label class="kb-field">
                    <span>Last reviewed</span>
                    <input
                        type="date"
                        name="last_reviewed_at"
                        value="<?= moderation_e($reviewDate) ?>"
                    >
                    <small>
                        Published articles become review-due after 180 days.
                    </small>
                </label>

                <button
                    class="admin-button is-primary kb-save-button"
                    type="submit"
                >
                    Save article
                </button>
            </div>
        </section>

        <?php if ($articleId > 0): ?>
            <section class="admin-panel kb-meta-panel">
                <header class="admin-panel-header">
                    <div>
                        <p>Record</p>
                        <h2>Article details</h2>
                    </div>
                </header>

                <dl>
                    <div>
                        <dt>Article ID</dt>
                        <dd>#<?= $articleId ?></dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd>
                            <?= moderation_e(
                                (string) ($article['created_at'] ?? 'Unknown')
                            ) ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Updated</dt>
                        <dd>
                            <?= moderation_e(
                                (string) ($article['updated_at'] ?? 'Unknown')
                            ) ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Published</dt>
                        <dd>
                            <?= moderation_e(
                                (string) (
                                    $article['published_at']
                                    ?: 'Not published'
                                )
                            ) ?>
                        </dd>
                    </div>
                </dl>
            </section>
        <?php endif; ?>
    </aside>
</form>

<?php require __DIR__ . '/_footer.php'; ?>

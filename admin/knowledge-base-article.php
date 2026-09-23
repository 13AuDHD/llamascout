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
        $action = trim((string) ($_POST['action'] ?? 'save-article'));

        try {
            if ($action === 'upload-image') {
                if ($articleId <= 0) {
                    throw new RuntimeException(
                        'Save the article before adding screenshots.'
                    );
                }

                kb_admin_upload_article_image(
                    $db,
                    $articleId,
                    (array) ($_FILES['screenshot'] ?? []),
                    $_POST
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base-article.php?id='
                    . $articleId
                    . '&image_uploaded=1#kb-screenshots'
                );
                exit;
            }

            if ($action === 'update-image') {
                $imageId = max(0, (int) ($_POST['image_id'] ?? 0));

                kb_admin_update_article_image(
                    $db,
                    $articleId,
                    $imageId,
                    $_POST
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base-article.php?id='
                    . $articleId
                    . '&image_saved=1#kb-screenshots'
                );
                exit;
            }

            if ($action === 'archive-image' || $action === 'restore-image') {
                $imageId = max(0, (int) ($_POST['image_id'] ?? 0));
                $status =
                    $action === 'archive-image'
                        ? 'archived'
                        : 'active';

                kb_admin_set_article_image_status(
                    $db,
                    $articleId,
                    $imageId,
                    $status
                );

                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base-article.php?id='
                    . $articleId
                    . '&image_status='
                    . rawurlencode($status)
                    . '#kb-screenshots'
                );
                exit;
            }

            if ($action !== 'save-article') {
                throw new InvalidArgumentException(
                    'Unknown Knowledge Base action.'
                );
            }

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

            if ($action === 'save-article') {
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

$articleImages =
    $articleId > 0
        ? kb_admin_article_images($db, $articleId, true)
        : [];

$imagePreviewData = [];

foreach ($articleImages as $image) {
    $imagePreviewData[] = [
        'id' => (int) ($image['id'] ?? 0),
        'path' => (string) ($image['image_path'] ?? ''),
        'alt' => (string) ($image['alt_text'] ?? ''),
        'caption' => (string) ($image['caption'] ?? ''),
        'status' => (string) ($image['status'] ?? 'active'),
    ];
}
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

<?php if (isset($_GET['image_uploaded'])): ?>
    <div class="admin-user-notice is-success">
        Screenshot uploaded. Tiny visual aid acquired.
    </div>
<?php endif; ?>

<?php if (isset($_GET['image_saved'])): ?>
    <div class="admin-user-notice is-success">
        Screenshot details saved.
    </div>
<?php endif; ?>

<?php if (isset($_GET['image_status'])): ?>
    <div class="admin-user-notice is-success">
        Screenshot <?= $_GET['image_status'] === 'archived' ? 'archived' : 'restored' ?>.
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="kb-editor" id="kb-article-form">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >
    <input type="hidden" name="action" value="save-article">
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

                <div class="kb-field is-wide kb-body-field">
                    <div class="kb-editor-label-row">
                        <span>Article body</span>
                        <div class="kb-editor-tabs" role="tablist" aria-label="Article editor view">
                            <button
                                class="kb-editor-tab is-active"
                                type="button"
                                role="tab"
                                aria-selected="true"
                                data-kb-editor-tab="write"
                            >
                                Write
                            </button>
                            <button
                                class="kb-editor-tab"
                                type="button"
                                role="tab"
                                aria-selected="false"
                                data-kb-editor-tab="preview"
                            >
                                Preview
                            </button>
                        </div>
                    </div>

                    <div class="kb-editor-toolbar" aria-label="Article formatting">
                        <button type="button" data-kb-wrap="<strong>|</strong>">Bold</button>
                        <button type="button" data-kb-wrap="<h2>|</h2>">Heading</button>
                        <button type="button" data-kb-wrap="<h3>|</h3>">Subheading</button>
                        <button type="button" data-kb-wrap="<p>|</p>">Paragraph</button>
                        <button type="button" data-kb-wrap="<ul>\n    <li>|</li>\n</ul>">Bullets</button>
                        <button type="button" data-kb-wrap="<ol>\n    <li>|</li>\n</ol>">Steps</button>
                        <button type="button" data-kb-link>Link</button>
                        <button type="button" data-kb-note>Helpful note</button>
                        <button type="button" data-kb-template>Article template</button>
                    </div>

                    <div data-kb-editor-panel="write">
                        <textarea
                            class="kb-body-editor"
                            id="kb-article-content"
                            name="content"
                            rows="26"
                            spellcheck="true"
                        ><?= moderation_e((string) ($article['content'] ?? '')) ?></textarea>
                    </div>

                    <div
                        class="kb-preview-panel"
                        data-kb-editor-panel="preview"
                        hidden
                    >
                        <div class="kb-preview-empty" data-kb-preview-empty>
                            Nothing to preview yet. The llama is staring at a blank page.
                        </div>
                        <article class="kb-article-preview" data-kb-preview></article>
                    </div>

                    <small>
                        The toolbar writes clean article HTML for you. Screenshots are
                        inserted as <code>[kb-image id="123"]</code> markers so captions
                        and alt text stay editable in one place.
                    </small>
                </div>

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
                        <dd><?= moderation_e((string) ($article['created_at'] ?? 'Unknown')) ?></dd>
                    </div>
                    <div>
                        <dt>Updated</dt>
                        <dd><?= moderation_e((string) ($article['updated_at'] ?? 'Unknown')) ?></dd>
                    </div>
                    <div>
                        <dt>Published</dt>
                        <dd><?= moderation_e((string) (($article['published_at'] ?? null) ?: 'Not published')) ?></dd>
                    </div>
                    <div>
                        <dt>Screenshots</dt>
                        <dd><?= count($articleImages) ?></dd>
                    </div>
                </dl>
            </section>
        <?php endif; ?>
    </aside>
</form>

<section class="admin-panel kb-screenshot-panel" id="kb-screenshots">
    <header class="admin-panel-header">
        <div>
            <p>KB-03</p>
            <h2>Screenshots</h2>
        </div>
    </header>

    <?php if ($articleId <= 0): ?>
        <div class="kb-empty">
            <strong>Save the article first.</strong>
            <span>
                Once Basecamp has an article ID, screenshots can move into their
                permanent little pasture.
            </span>
        </div>
    <?php else: ?>
        <div class="kb-screenshot-layout">
            <form
                method="post"
                enctype="multipart/form-data"
                class="kb-screenshot-upload"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(moderation_csrf_token()) ?>"
                >
                <input type="hidden" name="action" value="upload-image">
                <input type="hidden" name="id" value="<?= $articleId ?>">

                <div class="kb-screenshot-upload-copy">
                    <strong>Add a screenshot</strong>
                    <span>
                        JPG, PNG, or WebP. Maximum 8 MB and 8000 × 8000 pixels.
                    </span>
                </div>

                <label class="kb-field">
                    <span>Image</span>
                    <input
                        type="file"
                        name="screenshot"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >
                </label>

                <label class="kb-field">
                    <span>Alt text</span>
                    <input
                        type="text"
                        name="alt_text"
                        maxlength="300"
                        required
                        placeholder="Describe what the screenshot shows"
                    >
                </label>

                <label class="kb-field">
                    <span>Caption</span>
                    <input
                        type="text"
                        name="caption"
                        maxlength="500"
                        placeholder="Optional explanation below the image"
                    >
                </label>

                <button class="admin-button is-primary" type="submit">
                    Upload screenshot
                </button>
            </form>

            <?php if (!$articleImages): ?>
                <div class="kb-empty">
                    <strong>No screenshots yet.</strong>
                    <span>
                        Some things are easier to explain when a llama can point at them.
                    </span>
                </div>
            <?php else: ?>
                <div class="kb-screenshot-list">
                    <?php foreach ($articleImages as $image): ?>
                        <?php
                        $imageId = (int) ($image['id'] ?? 0);
                        $imageStatus = (string) ($image['status'] ?? 'active');
                        $imagePath = (string) ($image['image_path'] ?? '');
                        $imageUrl = $siteUrl . $imagePath;
                        $shortcode = '[kb-image id="' . $imageId . '"]';
                        ?>
                        <article class="kb-screenshot-card <?= $imageStatus === 'archived' ? 'is-archived' : '' ?>">
                            <div class="kb-screenshot-preview">
                                <img
                                    src="<?= moderation_e($imageUrl) ?>"
                                    alt="<?= moderation_e((string) ($image['alt_text'] ?? '')) ?>"
                                    loading="lazy"
                                >
                                <?php if ($imageStatus === 'archived'): ?>
                                    <span class="kb-screenshot-status">Archived</span>
                                <?php endif; ?>
                            </div>

                            <div class="kb-screenshot-card-body">
                                <form method="post" class="kb-screenshot-details">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                                    >
                                    <input type="hidden" name="action" value="update-image">
                                    <input type="hidden" name="id" value="<?= $articleId ?>">
                                    <input type="hidden" name="image_id" value="<?= $imageId ?>">

                                    <label class="kb-field">
                                        <span>Alt text</span>
                                        <input
                                            type="text"
                                            name="alt_text"
                                            maxlength="300"
                                            required
                                            value="<?= moderation_e((string) ($image['alt_text'] ?? '')) ?>"
                                        >
                                    </label>

                                    <label class="kb-field">
                                        <span>Caption</span>
                                        <textarea
                                            name="caption"
                                            rows="2"
                                            maxlength="500"
                                        ><?= moderation_e((string) ($image['caption'] ?? '')) ?></textarea>
                                    </label>

                                    <label class="kb-field kb-screenshot-order">
                                        <span>Order</span>
                                        <input
                                            type="number"
                                            name="sort_order"
                                            step="1"
                                            value="<?= (int) ($image['sort_order'] ?? 0) ?>"
                                        >
                                    </label>

                                    <div class="kb-screenshot-actions">
                                        <button class="admin-button" type="submit">
                                            Save details
                                        </button>

                                        <button
                                            class="admin-button"
                                            type="button"
                                            data-kb-insert-image="<?= $imageId ?>"
                                            <?= $imageStatus === 'archived' ? 'disabled' : '' ?>
                                        >
                                            Insert in article
                                        </button>
                                    </div>
                                </form>

                                <div class="kb-screenshot-meta">
                                    <code><?= moderation_e($shortcode) ?></code>
                                    <span>
                                        <?= (int) ($image['width_px'] ?? 0) ?>
                                        ×
                                        <?= (int) ($image['height_px'] ?? 0) ?>
                                    </span>
                                </div>

                                <form method="post" class="kb-screenshot-status-form">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= moderation_e(moderation_csrf_token()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="<?= $imageStatus === 'archived' ? 'restore-image' : 'archive-image' ?>"
                                    >
                                    <input type="hidden" name="id" value="<?= $articleId ?>">
                                    <input type="hidden" name="image_id" value="<?= $imageId ?>">

                                    <button class="admin-button" type="submit">
                                        <?= $imageStatus === 'archived' ? 'Restore screenshot' : 'Archive screenshot' ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script
    id="kb-image-data"
    type="application/json"
><?= json_encode(
    $imagePreviewData,
    JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
) ?></script>

<script src="<?= moderation_e($siteUrl . '/js/admin-knowledge-base-article.js') ?>"></script>

<?php require __DIR__ . '/_footer.php'; ?>

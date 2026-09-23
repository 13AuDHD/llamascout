<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-knowledge-base.php';

$adminUser = moderation_require_admin();
$db = db();

$config = llama_config();
$adminBaseUrl = rtrim(
    (string) (
        $config['app']['admin_url']
        ?? 'https://admin.llamascout.com'
    ),
    '/'
);

$schemaReady = kb_admin_schema_ready($db);
$notice = '';
$error = '';

if (!$schemaReady) {
    header('Location: ' . $adminBaseUrl . '/knowledge-base.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $savedId = kb_admin_save_category(
                $db,
                $_POST
            );

            $notice =
                !empty($_POST['id'])
                    ? 'Topic updated.'
                    : 'Topic created.';

            if (isset($_POST['save_and_reload'])) {
                header(
                    'Location: '
                    . $adminBaseUrl
                    . '/knowledge-base-categories.php?saved='
                    . $savedId
                );
                exit;
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$categoryRows = kb_admin_categories($db);

$adminPageTitle = 'Knowledge Base Topics';
$adminPageEyebrow = 'Knowledge Base';
$adminActiveNav = 'knowledge-base';

$adminPageActions =
    '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base.php')
    . '">All articles</a>'
    . '<a class="admin-button is-primary" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base-article.php')
    . '">New article</a>';

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base.css') ?>"
>

<?php if ($notice !== '' || isset($_GET['saved'])): ?>
    <div class="admin-user-notice is-success">
        <?= moderation_e($notice !== '' ? $notice : 'Topic saved.') ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>New topic</p>
            <h2>Add another shelf to the encyclopedia</h2>
        </div>
    </header>

    <form method="post" class="kb-topic-form">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >

        <label class="kb-field">
            <span>Name</span>
            <input
                type="text"
                name="name"
                maxlength="120"
                required
                placeholder="Navigation"
            >
        </label>

        <label class="kb-field">
            <span>Slug</span>
            <input
                type="text"
                name="slug"
                maxlength="140"
                placeholder="navigation"
            >
        </label>

        <label class="kb-field">
            <span>Status</span>
            <select name="status">
                <option value="active">Active</option>
                <option value="coming_soon">Coming Soon</option>
                <option value="hidden">Hidden</option>
            </select>
        </label>

        <label class="kb-field">
            <span>Sort order</span>
            <input type="number" name="sort_order" value="0" step="1">
        </label>

        <label class="kb-field">
            <span>Icon name</span>
            <input
                type="text"
                name="icon_name"
                maxlength="80"
                placeholder="map"
            >
            <small>
                Optional local SVG name without .svg.
            </small>
        </label>

        <label class="kb-field is-wide">
            <span>Description</span>
            <textarea
                name="description"
                rows="3"
                maxlength="500"
                placeholder="What kind of help lives here?"
            ></textarea>
        </label>

        <div class="kb-form-actions is-wide">
            <button
                class="admin-button is-primary"
                type="submit"
                name="save_and_reload"
                value="1"
            >
                Create topic
            </button>
        </div>
    </form>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Topics</p>
            <h2><?= number_format(count($categoryRows)) ?> Knowledge Base topics</h2>
        </div>
    </header>

    <div class="kb-topic-list">
        <?php foreach ($categoryRows as $category): ?>
            <form method="post" class="kb-topic-card">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(moderation_csrf_token()) ?>"
                >
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $category['id'] ?>"
                >

                <div class="kb-topic-card-heading">
                    <div>
                        <strong><?= moderation_e((string) $category['name']) ?></strong>
                        <span>
                            <?= (int) ($category['article_count'] ?? 0) ?>
                            article<?= (int) ($category['article_count'] ?? 0) === 1 ? '' : 's' ?>
                            ·
                            <?= (int) ($category['published_count'] ?? 0) ?>
                            published
                        </span>
                    </div>

                    <span class="kb-pill is-<?= moderation_e((string) $category['status']) ?>">
                        <?= moderation_e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) $category['status']
                                )
                            )
                        ) ?>
                    </span>
                </div>

                <div class="kb-form-grid">
                    <label class="kb-field">
                        <span>Name</span>
                        <input
                            type="text"
                            name="name"
                            maxlength="120"
                            required
                            value="<?= moderation_e((string) $category['name']) ?>"
                        >
                    </label>

                    <label class="kb-field">
                        <span>Slug</span>
                        <input
                            type="text"
                            name="slug"
                            maxlength="140"
                            value="<?= moderation_e((string) $category['slug']) ?>"
                        >
                    </label>

                    <label class="kb-field">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach (['active', 'coming_soon', 'hidden'] as $option): ?>
                                <option
                                    value="<?= moderation_e($option) ?>"
                                    <?= (string) $category['status'] === $option ? 'selected' : '' ?>
                                >
                                    <?= moderation_e(
                                        ucwords(str_replace('_', ' ', $option))
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="kb-field">
                        <span>Sort order</span>
                        <input
                            type="number"
                            name="sort_order"
                            step="1"
                            value="<?= (int) $category['sort_order'] ?>"
                        >
                    </label>

                    <label class="kb-field">
                        <span>Icon name</span>
                        <input
                            type="text"
                            name="icon_name"
                            maxlength="80"
                            value="<?= moderation_e((string) $category['icon_name']) ?>"
                        >
                    </label>

                    <label class="kb-field is-wide">
                        <span>Description</span>
                        <textarea
                            name="description"
                            rows="2"
                            maxlength="500"
                        ><?= moderation_e((string) $category['description']) ?></textarea>
                    </label>
                </div>

                <div class="kb-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        name="save_and_reload"
                        value="1"
                    >
                        Save topic
                    </button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

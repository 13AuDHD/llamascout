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

if (!$schemaReady) {
    header('Location: ' . $adminBaseUrl . '/knowledge-base.php');
    exit;
}

$articleId = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$article = kb_admin_article($db, $articleId);

if (!$article) {
    http_response_code(404);
    $adminPageTitle = 'Revision History';
    $adminPageEyebrow = 'Knowledge Base';
    $adminActiveNav = 'knowledge-base';
    require __DIR__ . '/_header.php';
    ?>
    <link
        rel="stylesheet"
        href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base.css') ?>"
    >
    <div class="admin-user-notice is-error">
        Knowledge Base article not found.
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $revisionId = max(
                0,
                (int) ($_POST['revision_id'] ?? 0)
            );

            kb_admin_restore_revision(
                $db,
                $actorUserId,
                $articleId,
                $revisionId
            );

            header(
                'Location: '
                . $adminBaseUrl
                . '/knowledge-base-article.php?id='
                . $articleId
                . '&restored=1'
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$revisionRows = kb_admin_revisions($db, $articleId);

$adminPageTitle = 'Revision History';
$adminPageEyebrow = 'Knowledge Base';
$adminActiveNav = 'knowledge-base';

$adminPageActions =
    '<a class="admin-button" href="'
    . moderation_e(
        $adminBaseUrl
        . '/knowledge-base-article.php?id='
        . $articleId
    )
    . '">Back to article</a>'
    . '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base.php')
    . '">All articles</a>';

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base.css') ?>"
>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p><?= moderation_e((string) $article['title']) ?></p>
            <h2><?= number_format(count($revisionRows)) ?> saved revisions</h2>
        </div>
    </header>

    <div class="kb-revision-note">
        Restoring a revision always returns the article to
        <strong>Draft</strong>. Basecamp also saves the current version
        before restoring, so a restore can itself be undone.
    </div>

    <?php if (!$revisionRows): ?>
        <div class="kb-empty">
            <strong>No revisions yet.</strong>
            <span>
                Revisions appear after an existing article is changed,
                published, unpublished, archived, or restored.
            </span>
        </div>
    <?php else: ?>
        <div class="kb-revision-list">
            <?php foreach ($revisionRows as $revision): ?>
                <?php
                $snapshot =
                    is_array($revision['snapshot'] ?? null)
                        ? $revision['snapshot']
                        : [];
                ?>
                <article class="kb-revision-row">
                    <div>
                        <strong>
                            Revision
                            #<?= (int) $revision['revision_number'] ?>
                        </strong>

                        <span>
                            Saved
                            <?= moderation_e((string) $revision['created_at']) ?>
                            <?php if (!empty($revision['edited_by'])): ?>
                                by user #<?= (int) $revision['edited_by'] ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="kb-revision-snapshot">
                        <span>
                            <?= moderation_e(
                                (string) (
                                    $snapshot['title']
                                    ?? 'Untitled article'
                                )
                            ) ?>
                        </span>
                        <span class="kb-pill is-<?= moderation_e(
                            (string) ($snapshot['status'] ?? 'draft')
                        ) ?>">
                            <?= moderation_e(
                                ucfirst(
                                    (string) (
                                        $snapshot['status']
                                        ?? 'draft'
                                    )
                                )
                            ) ?>
                        </span>
                    </div>

                    <form method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= moderation_e(moderation_csrf_token()) ?>"
                        >
                        <input
                            type="hidden"
                            name="id"
                            value="<?= $articleId ?>"
                        >
                        <input
                            type="hidden"
                            name="revision_id"
                            value="<?= (int) $revision['id'] ?>"
                        >

                        <button
                            class="admin-button"
                            type="submit"
                        >
                            Restore as Draft
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

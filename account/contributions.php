<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$items = community_submissions_for_user($userId);
$submitted = (string) ($_GET['submitted'] ?? '');
$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
$pageTitle = 'My Contributions | Llama Scout';
require dirname(__DIR__) . '/partials/header.php';
?>

<section class="account-page contribution-history-page">
    <header class="account-page-header">
        <div>
            <p class="account-eyebrow">Community</p>
            <h1>My contributions</h1>
        </div>
        <a class="contribution-submit" href="<?= htmlspecialchars($siteUrl . '/add-place.php', ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add a place</a>
    </header>

    <?php if ($submitted !== ''): ?>
        <div class="contribution-message is-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Submitted for review.</div>
    <?php endif; ?>

    <?php if (!$items): ?>
        <div class="account-empty-state">
            <i class="fa-solid fa-route" aria-hidden="true"></i>
            <h2>No contributions yet</h2>
            <p>Add a new place or suggest a correction when something has changed.</p>
        </div>
    <?php else: ?>
        <div class="contribution-history-list">
            <?php foreach ($items as $item): ?>
                <?php
                $status = (string) ($item['status'] ?? 'pending');
                $statusLabel = ucwords(str_replace('-', ' ', $status));
                ?>
                <article class="contribution-history-card">
                    <div class="contribution-history-main">
                        <p class="account-eyebrow"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <h2><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="contribution-history-date">Submitted <?= htmlspecialchars(date('M j, Y', strtotime((string) $item['submitted_at'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($item['review_notes'])): ?><p class="contribution-review-note"><?= nl2br(htmlspecialchars((string) $item['review_notes'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                    </div>
                    <div class="contribution-history-side">
                        <span class="contribution-status status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($item['slug'])): ?><a href="<?= htmlspecialchars($siteUrl . '/place.php?slug=' . rawurlencode((string) $item['slug']), ENT_QUOTES, 'UTF-8') ?>">View place</a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

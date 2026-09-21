<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/master-scout-moderation.php';

require_verified_email();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$moderator = llama_master_moderator_context($db, $userId);

if (!$moderator) {
    http_response_code(403);
    $pageTitle = 'Moderation unavailable | Llama Scout';
    require dirname(__DIR__) . '/partials/header.php';
    ?>
    <section class="master-moderation-page">
        <div class="master-moderation-shell">
            <div class="master-moderation-empty">
                <i aria-hidden="true"><?= llama_icon('lock') ?></i>
                <h1>Moderation unavailable</h1>
                <p>Active Master Scout status is required to use the community moderation queue.</p>
                <a class="master-moderation-button" href="/scout.php">Return to Scout Basecamp</a>
            </div>
        </div>
    </section>
    <?php
    require dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$newPlaces = llama_master_moderation_new_place_queue($db, $userId);
$updates = llama_master_moderation_update_queue($db, $userId);

$notice = trim((string) ($_GET['notice'] ?? ''));
$noticeText = match ($notice) {
    'approved' => 'Contribution approved.',
    'changes' => 'Changes requested from the contributor.',
    'rejected' => 'Contribution marked not approved.',
    default => '',
};

$pageTitle = 'Master Scout Moderation | Llama Scout';
require dirname(__DIR__) . '/partials/header.php';
?>

<link rel="stylesheet" href="https://llamascout.com/css/account/features/master-moderation.css">

<section class="master-moderation-page">
<div class="master-moderation-shell">

<header class="master-moderation-hero">
    <div>
        <p class="eyebrow">Master Scout</p>
        <h1>Community Moderation</h1>
        <p>
            Review new Places and structured updates submitted by other contributors.
            Master Scouts can approve, request changes, or mark a contribution not approved.
        </p>
    </div>

    <a class="master-moderation-button is-secondary" href="/scout.php">
        <?= llama_icon('arrow-left') ?>
        Scout Basecamp
    </a>
</header>

<div class="master-moderation-boundary">
    <i aria-hidden="true"><?= llama_icon('shield-check') ?></i>
    <div>
        <strong>Moderator boundaries</strong>
        <span>
            Your own contributions are excluded. This workspace does not provide Basecamp access,
            submission editing, deletion, Featured publishing, user administration, or site settings.
        </span>
    </div>
</div>

<?php if ($noticeText !== ''): ?>
    <div class="master-moderation-notice is-success">
        <?= htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="master-moderation-stats" aria-label="Moderation queue summary">
    <div>
        <span>New Places</span>
        <strong><?= number_format(count($newPlaces)) ?></strong>
    </div>
    <div>
        <span>Place Updates</span>
        <strong><?= number_format(count($updates)) ?></strong>
    </div>
    <div>
        <span>Total Waiting</span>
        <strong><?= number_format(count($newPlaces) + count($updates)) ?></strong>
    </div>
</section>

<section class="master-moderation-panel">
    <header>
        <div>
            <p class="eyebrow">Queue</p>
            <h2>New Places</h2>
        </div>
        <span><?= number_format(count($newPlaces)) ?></span>
    </header>

    <?php if (!$newPlaces): ?>
        <div class="master-moderation-empty is-compact">
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
            <p>No new Place submissions from other contributors are waiting for review.</p>
        </div>
    <?php else: ?>
        <div class="master-moderation-list">
            <?php foreach ($newPlaces as $item): ?>
                <?php
                $level = llama_contribution_level_from_role(
                    (string) ($item['role_at_submission'] ?? 'user')
                );
                ?>
                <article>
                    <div>
                        <span class="master-moderation-type">New Place</span>
                        <h3><?= htmlspecialchars((string) ($item['place_name'] ?? 'Unnamed Place'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p>
                            <?= htmlspecialchars(
                                (string) (($item['display_name'] ?? '') ?: ($item['username'] ?? 'Contributor')),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            · <?= htmlspecialchars(llama_contribution_level_short_label($level), ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars(
                                llama_format_viewer_datetime((string) ($item['submitted_at'] ?? '')),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>
                    <a class="master-moderation-button" href="/master-review.php?type=new-place&id=<?= (int) $item['id'] ?>">
                        Review
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="master-moderation-panel">
    <header>
        <div>
            <p class="eyebrow">Queue</p>
            <h2>Place Updates</h2>
        </div>
        <span><?= number_format(count($updates)) ?></span>
    </header>

    <?php if (!$updates): ?>
        <div class="master-moderation-empty is-compact">
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
            <p>No Place updates from other contributors are waiting for review.</p>
        </div>
    <?php else: ?>
        <div class="master-moderation-list">
            <?php foreach ($updates as $item): ?>
                <?php
                $level = llama_contribution_level_from_role(
                    (string) ($item['role_at_submission'] ?? 'user')
                );
                $changes = moderation_decode_json($item['proposed_changes'] ?? '');
                ?>
                <article>
                    <div>
                        <span class="master-moderation-type">Place Update</span>
                        <h3><?= htmlspecialchars((string) ($item['place_name'] ?? 'Place'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p>
                            <?= htmlspecialchars(
                                (string) (($item['display_name'] ?? '') ?: ($item['username'] ?? 'Contributor')),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            · <?= htmlspecialchars(llama_contribution_level_short_label($level), ENT_QUOTES, 'UTF-8') ?>
                            · <?= number_format(count($changes)) ?> changed field<?= count($changes) === 1 ? '' : 's' ?>
                        </p>
                    </div>
                    <a class="master-moderation-button" href="/master-review.php?type=update&id=<?= (int) $item['id'] ?>">
                        Review
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

</div>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

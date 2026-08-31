<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function badge_e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$slug = strtolower(
    trim((string) ($_GET['slug'] ?? ''))
);

if (
    $slug === ''
    || !preg_match('/^[a-z0-9-]+$/', $slug)
) {
    http_response_code(404);
    $pageTitle = 'Badge Not Found | Llama Scout';
    $pageRobots = 'noindex,nofollow';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="badge-detail-page">
        <div class="badge-detail-container">
            <h1>Badge not found.</h1>
            <p><a href="/">Return to Llama Scout</a></p>
        </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$db = db();

$stmt = $db->prepare(
    'SELECT
        id,
        slug,
        name,
        description,
        category,
        source_organization,
        icon,
        image_src,
        award_type,
        threshold_value
     FROM badge_definitions
     WHERE slug = ?
       AND is_active = 1
     LIMIT 1'
);

$stmt->execute([$slug]);
$badge = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$badge) {
    http_response_code(404);
    $pageTitle = 'Badge Not Found | Llama Scout';
    $pageRobots = 'noindex,nofollow';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="badge-detail-page">
        <div class="badge-detail-container">
            <h1>Badge not found.</h1>
            <p><a href="/">Return to Llama Scout</a></p>
        </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$badgeImage = llama_badge_image_url(
    (string) $badge['slug'],
    (string) ($badge['image_src'] ?? '')
);

$earnedStmt = $db->prepare(
    'SELECT COUNT(DISTINCT user_id)
     FROM user_badges
     WHERE badge_id = ?
       AND review_status = ?'
);
$earnedStmt->execute([
    (int) $badge['id'],
    'earned',
]);
$earnedCount = (int) $earnedStmt->fetchColumn();

$totalMembers = 0;

try {
    $totalStmt = $db->query(
        'SELECT COUNT(*)
         FROM community_profiles'
    );
    $totalMembers =
        (int) $totalStmt->fetchColumn();
} catch (Throwable $exception) {
    error_log(
        'Llama Scout badge member-count error: ' .
        $exception->getMessage()
    );
}

$earnedPercent =
    $totalMembers > 0
        ? ($earnedCount / $totalMembers) * 100
        : 0;

if ($earnedCount === 0) {
    $rarity = 'Not Yet Earned';
} elseif ($earnedPercent <= 1) {
    $rarity = 'Legendary';
} elseif ($earnedPercent <= 5) {
    $rarity = 'Very Rare';
} elseif ($earnedPercent <= 15) {
    $rarity = 'Rare';
} elseif ($earnedPercent <= 40) {
    $rarity = 'Uncommon';
} else {
    $rarity = 'Common';
}

$howToEarn = match ($slug) {
    'first-contribution' =>
        'Make your first approved contribution to Llama Scout.',

    'first-place' =>
        'Add your first approved Place to Llama Scout.',

    'first-llama-scout' =>
        'Complete your first approved Llama Scout field visit.',

    'five-places-scouted' =>
        'Llama Scout 5 different Places.',

    'ten-places-scouted' =>
        'Llama Scout 10 different Places.',

    'twenty-five-places-scouted' =>
        'Llama Scout 25 different Places.',

    'fifty-places-scouted' =>
        'Llama Scout 50 different Places.',

    'helpful-editor' =>
        'Submit an approved update or correction that improves an existing Place.',

    'master-scout' =>
        'Earn Master Scout status.',

    'founding-member' =>
        'Be one of the early members who helped Llama Scout get its hooves under it.',

    default => match ((string) ($badge['award_type'] ?? '')) {
        'credential' =>
            'Awarded for an applicable training or stewardship credential.',

        'automatic' =>
            'Earned automatically when the badge requirements are met.',

        default =>
            'Awarded by Llama Scout for meeting the badge requirements.',
    },
};

$pageTitle =
    (string) $badge['name'] .
    ' Badge | Llama Scout';

$pageDescription =
    trim((string) ($badge['description'] ?? '')) !== ''
        ? (string) $badge['description']
        : 'Llama Scout badge details.';

$canonicalUrl =
    'https://llamascout.com/badges/' .
    rawurlencode($slug);

require __DIR__ . '/partials/header.php';
?>

<section class="badge-detail-page">

    <div class="badge-detail-container">

        <a class="badge-detail-back" href="javascript:history.back()">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back
        </a>

        <article class="badge-detail">

            <div class="badge-detail-art">

                <?php if ($badgeImage): ?>
                    <img
                        src="<?= badge_e(
                            llama_profile_image_url(
                                $badgeImage,
                                'https://llamascout.com'
                            )
                        ) ?>"
                        alt="<?= badge_e($badge['name']) ?>"
                    >
                <?php else: ?>
                    <div class="badge-detail-fallback">
                        <i
                            class="fa-solid <?= badge_e(
                                $badge['icon'] ?: 'fa-award'
                            ) ?>"
                            aria-hidden="true"
                        ></i>
                    </div>
                <?php endif; ?>

            </div>


            <div class="badge-detail-content">

                <p class="badge-detail-category">
                    <?= badge_e(
                        ucfirst(
                            (string) ($badge['category'] ?? 'community')
                        )
                    ) ?>
                </p>

                <h1><?= badge_e($badge['name']) ?></h1>

                <?php if (!empty($badge['description'])): ?>
                    <p class="badge-detail-description">
                        <?= badge_e($badge['description']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($badge['source_organization'])): ?>
                    <p class="badge-detail-issued">
                        Issued by
                        <strong>
                            <?= badge_e($badge['source_organization']) ?>
                        </strong>
                    </p>
                <?php endif; ?>


                <div class="badge-detail-stats">

                    <div>
                        <strong><?= number_format($earnedCount) ?></strong>
                        <span>Members Earned</span>
                    </div>

                    <div>
                        <strong>
                            <?= number_format($earnedPercent, 1) ?>%
                        </strong>
                        <span>Of Members</span>
                    </div>

                    <div>
                        <strong><?= badge_e($rarity) ?></strong>
                        <span>Rarity</span>
                    </div>

                </div>


                <section class="badge-detail-how">
                    <p class="badge-detail-label">How to earn</p>
                    <h2><?= badge_e($howToEarn) ?></h2>
                </section>

            </div>

        </article>

    </div>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function guide_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$guideFile = __DIR__ . '/data/field-guides.json';
$guides = [];

if (is_file($guideFile)) {
    $decoded = json_decode((string) file_get_contents($guideFile), true);
    $guides = is_array($decoded) ? $decoded : [];
}

$guide = null;

foreach ($guides as $candidate) {
    if ((string) ($candidate['slug'] ?? '') === $slug) {
        $guide = $candidate;
        break;
    }
}

if (!$guide) {
    http_response_code(404);
    $pageTitle = 'Field Guide Not Found | Llama Scout';
    $pageRobots = 'noindex,nofollow';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="field-guide-not-found">
        <div class="public-home-container">
            <p class="public-home-eyebrow">Off the trail</p>
            <h1>That Field Guide could not be found.</h1>
            <p><a href="/field-guides">Return to the Field Guides</a></p>
        </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$pageTitle = ($guide['title'] ?? 'Field Guide') . ' | Llama Scout';
$pageDescription = (string) ($guide['excerpt'] ?? '');
$canonicalUrl = 'https://llamascout.com/field-guides/' . rawurlencode((string) $guide['slug']);

require __DIR__ . '/partials/header.php';
?>

<article class="field-guide-article">

    <header class="field-guide-article-hero">
        <div class="field-guide-article-container">

            <a class="field-guide-back" href="/field-guides">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Field Guides
            </a>

            <p class="public-home-eyebrow"><?= guide_e($guide['category'] ?? 'Field Guide') ?></p>

            <h1><?= guide_e($guide['title'] ?? '') ?></h1>

            <p class="field-guide-article-lede">
                <?= guide_e($guide['excerpt'] ?? '') ?>
            </p>

            <div class="field-guide-article-meta">
                <?php if (!empty($guide['published'])): ?>
                    <span>
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <?= guide_e(date('M j, Y', strtotime((string) $guide['published']))) ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($guide['readTime'])): ?>
                    <span>
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <?= guide_e($guide['readTime']) ?>
                    </span>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <?php if (!empty($guide['image'])): ?>
        <div class="field-guide-article-image-wrap">
            <img
                src="/<?= guide_e(ltrim((string) $guide['image'], '/')) ?>"
                alt="<?= guide_e($guide['imageAlt'] ?? $guide['title']) ?>"
            >
        </div>
    <?php endif; ?>

    <div class="field-guide-article-container field-guide-article-body">

        <?php foreach (($guide['sections'] ?? []) as $section): ?>
            <section>
                <?php if (!empty($section['heading'])): ?>
                    <h2><?= guide_e($section['heading']) ?></h2>
                <?php endif; ?>

                <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
                    <p><?= guide_e($paragraph) ?></p>
                <?php endforeach; ?>

                <?php if (!empty($section['bullets']) && is_array($section['bullets'])): ?>
                    <ul>
                        <?php foreach ($section['bullets'] as $bullet): ?>
                            <li><?= guide_e($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($section['tip'])): ?>
                    <aside class="field-guide-tip">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        <p><?= guide_e($section['tip']) ?></p>
                    </aside>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <footer class="field-guide-article-footer">
            <div>
                <p class="public-home-eyebrow">Keep exploring</p>
                <h2>Put the guide to work.</h2>
                <p>Browse Llama Scout places and compare what matters before you head out.</p>
            </div>

            <div class="public-home-actions">
                <a class="public-home-button is-primary" href="/map.php">Explore the Map</a>
                <a class="public-home-button" href="/field-guides">More Field Guides</a>
            </div>
        </footer>

    </div>

</article>

<?php require __DIR__ . '/partials/footer.php'; ?>

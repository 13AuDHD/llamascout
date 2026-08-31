<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Field Guides | Llama Scout';
$pageDescription = 'Practical Llama Scout field guides for dispersed camping, sensory planning, forest roads, connectivity, privacy, and outdoor travel.';
$canonicalUrl = 'https://llamascout.com/field-guides';

function guides_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$guideFile = __DIR__ . '/data/field-guides.json';
$guides = [];

if (is_file($guideFile)) {
    $decoded = json_decode((string) file_get_contents($guideFile), true);
    $guides = is_array($decoded) ? $decoded : [];
}

$categories = [];

foreach ($guides as $guide) {
    $category = trim((string) ($guide['category'] ?? ''));

    if ($category !== '') {
        $categories[$category] = true;
    }
}

$categories = array_keys($categories);
sort($categories);

require __DIR__ . '/partials/header.php';
?>

<section class="field-guides-page">

    <header class="field-guides-hero">
        <div class="public-home-container">
            <p class="public-home-eyebrow">The Llama Scout Field Guide</p>
            <h1>Useful information before you lose cell service.</h1>
            <p>
                Practical guides for quieter camping, dispersed travel,
                road access, sensory planning, connectivity, and getting
                outside with fewer surprises.
            </p>
        </div>
    </header>

    <section class="field-guides-content">
        <div class="public-home-container">

            <div class="field-guides-controls">
                <label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="field-guide-search"
                        placeholder="Search the Field Guides"
                        autocomplete="off"
                    >
                </label>

                <label>
                    <span class="visually-hidden">Category</span>
                    <select id="field-guide-category">
                        <option value="">All topics</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= guides_e($category) ?>">
                                <?= guides_e($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <p class="field-guides-status" id="field-guides-status" aria-live="polite"></p>

            <div class="field-guides-grid" id="field-guides-grid">
                <?php foreach ($guides as $guide): ?>
                    <article
                        class="field-guide-card"
                        data-field-guide-card
                        data-title="<?= guides_e(strtolower((string) ($guide['title'] ?? ''))) ?>"
                        data-category="<?= guides_e((string) ($guide['category'] ?? '')) ?>"
                        data-search="<?= guides_e(strtolower(implode(' ', array_filter([
                            $guide['title'] ?? '',
                            $guide['category'] ?? '',
                            $guide['excerpt'] ?? '',
                            implode(' ', is_array($guide['keywords'] ?? null) ? $guide['keywords'] : []),
                        ])))) ?>"
                    >
                        <?php if (!empty($guide['image'])): ?>
                            <a
                                class="field-guide-card-image"
                                href="/field-guides/<?= rawurlencode((string) $guide['slug']) ?>"
                            >
                                <img
                                    src="/<?= guides_e(ltrim((string) $guide['image'], '/')) ?>"
                                    alt="<?= guides_e($guide['imageAlt'] ?? $guide['title']) ?>"
                                    loading="lazy"
                                >
                            </a>
                        <?php endif; ?>

                        <div class="field-guide-card-body">
                            <div class="field-guide-card-meta">
                                <span><?= guides_e($guide['category'] ?? 'Field Guide') ?></span>
                                <span><?= guides_e($guide['readTime'] ?? '') ?></span>
                            </div>

                            <h2>
                                <a href="/field-guides/<?= rawurlencode((string) $guide['slug']) ?>">
                                    <?= guides_e($guide['title'] ?? '') ?>
                                </a>
                            </h2>

                            <p><?= guides_e($guide['excerpt'] ?? '') ?></p>

                            <a
                                class="field-guide-read"
                                href="/field-guides/<?= rawurlencode((string) $guide['slug']) ?>"
                            >
                                Read guide
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="field-guides-empty" id="field-guides-empty" hidden>
                No Field Guides match that search.
            </div>

        </div>
    </section>

</section>

<script src="/js/field-guides.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>

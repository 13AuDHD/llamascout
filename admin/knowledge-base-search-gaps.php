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

if (!$schemaReady) {
    header('Location: ' . $adminBaseUrl . '/knowledge-base.php');
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$period = trim((string) ($_GET['period'] ?? 'all'));
$page = max(1, (int) ($_GET['page'] ?? 1));

$allowedPeriods = [
    'all' => 0,
    '30' => 30,
    '90' => 90,
    '365' => 365,
];

if (!array_key_exists($period, $allowedPeriods)) {
    $period = 'all';
}

$days = (int) $allowedPeriods[$period];

$pageSize = 100;
$offset = ($page - 1) * $pageSize;

$error = '';
$summary = [
    'searches' => 0,
    'unique_gaps' => 0,
    'latest_search_at' => null,
];
$gaps = [];

try {
    $where = [
        'result_count = 0',
        "normalized_query <> ''",
    ];

    $params = [];

    if ($q !== '') {
        $where[] = 'normalized_query LIKE ?';
        $params[] = '%' . mb_strtolower($q) . '%';
    }

    if ($days > 0) {
        /*
         * $days comes only from the whitelist above, so it is safe
         * to place directly into the INTERVAL expression.
         */
        $where[] =
            'created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL '
            . $days
            . ' DAY)';
    }

    $whereSql = implode(' AND ', $where);

    $summaryStatement = $db->prepare(
        'SELECT
            COUNT(*) AS searches,
            COUNT(DISTINCT normalized_query) AS unique_gaps,
            MAX(created_at) AS latest_search_at
         FROM kb_search_log
         WHERE ' . $whereSql
    );

    $summaryStatement->execute($params);

    $summaryRow =
        $summaryStatement->fetch(PDO::FETCH_ASSOC);

    if (is_array($summaryRow)) {
        $summary = [
            'searches' =>
                (int) ($summaryRow['searches'] ?? 0),
            'unique_gaps' =>
                (int) ($summaryRow['unique_gaps'] ?? 0),
            'latest_search_at' =>
                $summaryRow['latest_search_at'] ?? null,
        ];
    }

    $listStatement = $db->prepare(
        'SELECT
            normalized_query,
            COUNT(*) AS search_count,
            MIN(created_at) AS first_searched_at,
            MAX(created_at) AS last_searched_at
         FROM kb_search_log
         WHERE ' . $whereSql . '
         GROUP BY normalized_query
         ORDER BY
            search_count DESC,
            last_searched_at DESC,
            normalized_query ASC
         LIMIT ' . $pageSize . '
         OFFSET ' . $offset
    );

    $listStatement->execute($params);

    $gaps =
        $listStatement->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$totalUnique = (int) ($summary['unique_gaps'] ?? 0);
$totalPages = max(
    1,
    (int) ceil($totalUnique / $pageSize)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

function kb_search_gap_url(
    string $adminBaseUrl,
    array $values
): string {
    $query = http_build_query(
        array_filter(
            $values,
            static fn(mixed $value): bool =>
                $value !== ''
                && $value !== null
                && $value !== 'all'
                && $value !== 1
        )
    );

    return $adminBaseUrl
        . '/knowledge-base-search-gaps.php'
        . ($query !== '' ? '?' . $query : '');
}

function kb_search_gap_date(mixed $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return 'Never';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return gmdate('M j, Y g:i A', $timestamp) . ' UTC';
}

$adminPageTitle = 'Search Gaps';
$adminPageEyebrow = 'Knowledge Base';
$adminActiveNav = 'knowledge-base';

$adminPageActions =
    '<a class="admin-button" href="'
    . moderation_e($adminBaseUrl . '/knowledge-base.php')
    . '">'
    . moderation_e('Back to Knowledge Base')
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

<link
    rel="stylesheet"
    href="<?= moderation_e($siteUrl . '/css/admin/pages/knowledge-base-search-gaps.css') ?>"
>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<section class="kb-gap-summary" aria-label="Search gap summary">
    <article class="kb-stat-card">
        <span>Zero-result searches</span>
        <strong>
            <?= number_format((int) ($summary['searches'] ?? 0)) ?>
        </strong>
    </article>

    <article class="kb-stat-card">
        <span>Unique search gaps</span>
        <strong>
            <?= number_format((int) ($summary['unique_gaps'] ?? 0)) ?>
        </strong>
    </article>

    <article class="kb-stat-card">
        <span>Latest gap</span>
        <strong class="kb-gap-date-stat">
            <?= moderation_e(
                kb_search_gap_date(
                    $summary['latest_search_at'] ?? null
                )
            ) ?>
        </strong>
    </article>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Search analytics</p>
            <h2>What people could not find</h2>
        </div>

        <span class="kb-result-count">
            <?= number_format($totalUnique) ?>
            unique gap<?= $totalUnique === 1 ? '' : 's' ?>
        </span>
    </header>

    <p class="kb-gap-intro">
        These are Knowledge Base searches that returned zero results.
        Repeated searches are grouped together, so you can see which
        missing answers are being looked for most often.
    </p>

    <form method="get" class="kb-gap-filters">
        <label>
            <span>Search gaps</span>
            <input
                type="search"
                name="q"
                value="<?= moderation_e($q) ?>"
                placeholder="Search the missing searches..."
            >
        </label>

        <label>
            <span>Time period</span>
            <select name="period">
                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>
                    All time
                </option>
                <option value="30" <?= $period === '30' ? 'selected' : '' ?>>
                    Last 30 days
                </option>
                <option value="90" <?= $period === '90' ? 'selected' : '' ?>>
                    Last 90 days
                </option>
                <option value="365" <?= $period === '365' ? 'selected' : '' ?>>
                    Last year
                </option>
            </select>
        </label>

        <div class="kb-filter-actions">
            <button
                class="admin-button is-primary"
                type="submit"
            >
                Filter
            </button>

            <a
                class="admin-button"
                href="<?= moderation_e(
                    $adminBaseUrl
                    . '/knowledge-base-search-gaps.php'
                ) ?>"
            >
                Clear
            </a>
        </div>
    </form>

    <?php if (!$gaps): ?>
        <div class="kb-empty">
            <strong>No search gaps found.</strong>
            <span>
                Every recorded Knowledge Base search in this view
                returned at least one result.
            </span>
        </div>
    <?php else: ?>
        <div class="kb-gap-list">
            <?php foreach ($gaps as $gap): ?>
                <?php
                $queryText = trim(
                    (string) ($gap['normalized_query'] ?? '')
                );

                $searchCount =
                    (int) ($gap['search_count'] ?? 0);

                $articleSearchUrl =
                    $adminBaseUrl
                    . '/knowledge-base.php?q='
                    . rawurlencode($queryText);
                ?>

                <article class="kb-gap-row">
                    <div class="kb-gap-main">
                        <div class="kb-gap-heading">
                            <strong>
                                <?= moderation_e($queryText) ?>
                            </strong>

                            <span class="kb-gap-count">
                                <?= number_format($searchCount) ?>
                                search<?= $searchCount === 1 ? '' : 'es' ?>
                            </span>
                        </div>

                        <div class="kb-gap-meta">
                            <span>
                                First seen
                                <?= moderation_e(
                                    kb_search_gap_date(
                                        $gap['first_searched_at']
                                        ?? null
                                    )
                                ) ?>
                            </span>

                            <span>
                                Last seen
                                <?= moderation_e(
                                    kb_search_gap_date(
                                        $gap['last_searched_at']
                                        ?? null
                                    )
                                ) ?>
                            </span>
                        </div>
                    </div>

                    <div class="kb-gap-actions">
                        <a
                            class="admin-button"
                            href="<?= moderation_e($articleSearchUrl) ?>"
                        >
                            Search articles
                        </a>

                        <a
                            class="admin-button"
                            href="<?= moderation_e(
                                $adminBaseUrl
                                . '/knowledge-base-article.php'
                            ) ?>"
                        >
                            New article
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav
                class="kb-gap-pagination"
                aria-label="Search gap pages"
            >
                <?php if ($page > 1): ?>
                    <a
                        class="admin-button"
                        href="<?= moderation_e(
                            kb_search_gap_url(
                                $adminBaseUrl,
                                [
                                    'q' => $q,
                                    'period' => $period,
                                    'page' => $page - 1,
                                ]
                            )
                        ) ?>"
                    >
                        Previous
                    </a>
                <?php endif; ?>

                <span>
                    Page
                    <?= number_format($page) ?>
                    of
                    <?= number_format($totalPages) ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a
                        class="admin-button"
                        href="<?= moderation_e(
                            kb_search_gap_url(
                                $adminBaseUrl,
                                [
                                    'q' => $q,
                                    'period' => $period,
                                    'page' => $page + 1,
                                ]
                            )
                        ) ?>"
                    >
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

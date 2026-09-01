<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-system.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$result =
    admin_system_audit_search(
        $db,
        $_GET,
        50
    );

$rows =
    $result['rows'];

$filters =
    $result['filters'];

$actorOptions =
    admin_system_audit_actor_options(
        $db
    );

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Audit Log';

$adminPageEyebrow =
    'System';

$adminActiveNav =
    'audit';

function audit_console_query(
    array $changes = []
): string {
    $query =
        array_merge(
            $_GET,
            $changes
        );

    foreach ($query as $key => $value) {
        if (
            $value === ''
            || $value === null
            || $value === 0
            || $value === '0'
        ) {
            unset(
                $query[$key]
            );
        }
    }

    return
        http_build_query(
            $query
        );
}

function audit_console_icon(
    string $category
): string {
    return match ($category) {
        'users' =>
            'fa-user-gear',
        'places' =>
            'fa-location-dot',
        'scouts' =>
            'fa-binoculars',
        'points' =>
            'fa-star',
        'shop' =>
            'fa-store',
        'reports' =>
            'fa-flag',
        'system' =>
            'fa-gears',
        'badges' =>
            'fa-award',
        'policy' =>
            'fa-file-shield',
        default =>
            'fa-clipboard-list',
    };
}

function audit_console_label(
    string $category
): string {
    return match ($category) {
        'users' => 'Users',
        'places' => 'Places',
        'scouts' => 'Scouts',
        'points' => 'Points',
        'shop' => 'Shop',
        'reports' => 'Reports',
        'system' => 'System',
        'badges' => 'Badges',
        'policy' => 'Policy',
        default => 'Other',
    };
}

require __DIR__ .
    '/_header.php';
?>

<section class="admin-panel admin-audit-console">

    <header class="admin-panel-header">
        <div>
            <p>Administrative History</p>
            <h2>
                <?= number_format(
                    (int) $result['total']
                ) ?>
                matching actions
            </h2>
        </div>

        <span>
            Page
            <?= number_format(
                (int) $result['page']
            ) ?>
            of
            <?= number_format(
                (int) $result['pages']
            ) ?>
        </span>
    </header>


    <form
        class="admin-audit-filters"
        method="get"
    >

        <label class="admin-audit-search">
            <span>Search</span>

            <input
                type="search"
                name="q"
                value="<?= moderation_e(
                    (string) $filters['q']
                ) ?>"
                placeholder="Action, summary, user, IP, or audit ID"
            >
        </label>


        <label>
            <span>Category</span>

            <select name="category">
                <option value="">All categories</option>

                <?php foreach (
                    [
                        'users' => 'Users',
                        'places' => 'Places & moderation',
                        'scouts' => 'Scouts',
                        'points' => 'Points',
                        'shop' => 'Shop & orders',
                        'reports' => 'Reports',
                        'system' => 'System',
                        'badges' => 'Badges',
                        'policy' => 'Policy',
                        'other' => 'Other',
                    ]
                    as
                    $value => $label
                ): ?>
                    <option
                        value="<?= moderation_e($value) ?>"
                        <?= $filters['category'] === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>


        <label>
            <span>Actor</span>

            <select name="actor_id">
                <option value="">Any administrator</option>

                <?php foreach ($actorOptions as $actor): ?>
                    <option
                        value="<?= (int) $actor['id'] ?>"
                        <?= (int) $filters['actor_id'] === (int) $actor['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= moderation_e(
                            (string) $actor['name']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>


        <label>
            <span>Target user ID</span>

            <input
                type="number"
                name="target_id"
                min="1"
                value="<?= $filters['target_id'] > 0
                    ? (int) $filters['target_id']
                    : '' ?>"
                placeholder="Any"
            >
        </label>


        <label>
            <span>From</span>

            <input
                type="date"
                name="date_from"
                value="<?= moderation_e(
                    (string) $filters['date_from']
                ) ?>"
            >
        </label>


        <label>
            <span>Through</span>

            <input
                type="date"
                name="date_to"
                value="<?= moderation_e(
                    (string) $filters['date_to']
                ) ?>"
            >
        </label>


        <div class="admin-audit-filter-actions">
            <button
                class="admin-button"
                type="submit"
            >
                <i
                    class="fa-solid fa-filter"
                    aria-hidden="true"
                ></i>
                Filter
            </button>

            <a
                class="admin-button is-muted"
                href="/audit.php"
            >
                Clear
            </a>
        </div>

    </form>


    <?php if (!$rows): ?>

        <div class="admin-empty-state">
            <p>No administrative actions match these filters.</p>
        </div>

    <?php else: ?>

        <div class="admin-audit-list">

            <?php foreach ($rows as $row): ?>

                <?php
                $metadata =
                    admin_system_audit_metadata(
                        $row['metadata']
                        ?? null
                    );

                $metadataRows =
                    admin_system_flatten_metadata(
                        $metadata
                    );

                $category =
                    (string) (
                        $row['category']
                        ?? 'other'
                    );
                ?>

                <article class="admin-audit-row">

                    <span class="admin-audit-icon">
                        <i
                            class="fa-solid <?= moderation_e(
                                audit_console_icon(
                                    $category
                                )
                            ) ?>"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <div class="admin-audit-main">

                        <div class="admin-audit-title-row">
                            <strong>
                                <?= moderation_e(
                                    (string) $row['summary']
                                ) ?>
                            </strong>

                            <span class="admin-audit-category">
                                <?= moderation_e(
                                    audit_console_label(
                                        $category
                                    )
                                ) ?>
                            </span>
                        </div>

                        <span>
                            <?= moderation_e(
                                (string) $row['actor_name']
                            ) ?>

                            <?php if (!empty($row['target_name'])): ?>
                                ·
                                <?= moderation_e(
                                    (string) $row['target_name']
                                ) ?>
                            <?php endif; ?>

                            ·
                            <?= moderation_e(
                                (string) $row['created_at']
                            ) ?>
                        </span>

                        <small>
                            #<?= (int) $row['id'] ?>
                            ·
                            <?= moderation_e(
                                (string) $row['action']
                            ) ?>

                            <?php if (!empty($row['ip_address'])): ?>
                                · IP
                                <?= moderation_e(
                                    (string) $row['ip_address']
                                ) ?>
                            <?php endif; ?>
                        </small>


                        <div class="admin-audit-links">

                            <?php if ((int) ($row['target_user_id'] ?? 0) > 0): ?>
                                <a
                                    href="/user.php?id=<?= (int) $row['target_user_id'] ?>"
                                >
                                    User
                                </a>
                            <?php endif; ?>

                            <?php if ((int) ($row['actor_user_id'] ?? 0) > 0): ?>
                                <a
                                    href="/user.php?id=<?= (int) $row['actor_user_id'] ?>"
                                >
                                    Actor
                                </a>
                            <?php endif; ?>

                        </div>


                        <?php if ($metadataRows): ?>

                            <details class="admin-audit-details">
                                <summary>
                                    View stored details
                                    <span>
                                        <?= number_format(
                                            count($metadataRows)
                                        ) ?>
                                    </span>
                                </summary>

                                <dl class="admin-audit-metadata">

                                    <?php foreach ($metadataRows as $metadataRow): ?>

                                        <div>
                                            <dt>
                                                <?= moderation_e(
                                                    ucwords(
                                                        str_replace(
                                                            [
                                                                '.',
                                                                '_',
                                                            ],
                                                            ' ',
                                                            (string) $metadataRow['key']
                                                        )
                                                    )
                                                ) ?>
                                            </dt>

                                            <dd>
                                                <?= moderation_e(
                                                    (string) $metadataRow['value']
                                                ) ?>
                                            </dd>
                                        </div>

                                    <?php endforeach; ?>

                                </dl>
                            </details>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>


        <?php if ((int) $result['pages'] > 1): ?>

            <nav
                class="admin-audit-pagination"
                aria-label="Audit log pages"
            >

                <?php if ((int) $result['page'] > 1): ?>
                    <a
                        class="admin-button is-muted"
                        href="/audit.php?<?= moderation_e(
                            audit_console_query([
                                'page' =>
                                    (int) $result['page'] - 1,
                            ])
                        ) ?>"
                    >
                        <i
                            class="fa-solid fa-chevron-left"
                            aria-hidden="true"
                        ></i>
                        Previous
                    </a>
                <?php endif; ?>

                <span>
                    <?= number_format(
                        (int) $result['page']
                    ) ?>
                    /
                    <?= number_format(
                        (int) $result['pages']
                    ) ?>
                </span>

                <?php if ((int) $result['page'] < (int) $result['pages']): ?>
                    <a
                        class="admin-button is-muted"
                        href="/audit.php?<?= moderation_e(
                            audit_console_query([
                                'page' =>
                                    (int) $result['page'] + 1,
                            ])
                        ) ?>"
                    >
                        Next
                        <i
                            class="fa-solid fa-chevron-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                <?php endif; ?>

            </nav>

        <?php endif; ?>

    <?php endif; ?>

</section>

<?php
require __DIR__ .
    '/_footer.php';
?>

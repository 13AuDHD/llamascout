<section
    class="admin-panel admin-maintenance-console"
    id="automated-maintenance"
>

<header class="admin-panel-header">
    <div>
        <p>Automation</p>
        <h2>Maintenance Console</h2>
    </div>

    <?php if (!$actorIsOwner): ?>
        <span>
            Owner access required to run
        </span>
    <?php endif; ?>
</header>

<div class="admin-maintenance-console-intro">
    <div>
        <strong>
            Automatic maintenance remains enabled.
        </strong>

        <p>
            These workers normally run during ordinary authenticated
            activity because this hosting plan does not provide cron.
            This console is the manual fallback when a worker becomes
            overdue or stale.
        </p>
    </div>

    <div class="admin-maintenance-console-legend">
        <span class="is-good">
            <i aria-hidden="true"></i>
            Healthy
        </span>

        <span class="is-attention">
            <i aria-hidden="true"></i>
            Overdue
        </span>

        <span class="is-down">
            <i aria-hidden="true"></i>
            Stale / no run
        </span>
    </div>
</div>

<div class="admin-maintenance-console-grid">

<?php foreach (
    $maintenanceWorkers
    as $worker
): ?>

<article
    class="admin-maintenance-worker is-<?= moderation_e(
        (string) $worker['status']
    ) ?>"
>
    <span
        class="admin-maintenance-worker-light"
        aria-hidden="true"
    ></span>

    <div class="admin-maintenance-worker-heading">
        <span class="admin-maintenance-worker-icon">
            <i aria-hidden="true">
                <?= llama_icon(
                    (string) $worker['icon']
                ) ?>
            </i>
        </span>

        <div>
            <span class="admin-maintenance-worker-status">
                <?= moderation_e(
                    (string) $worker[
                        'status_label'
                    ]
                ) ?>
            </span>

            <h3>
                <?= moderation_e(
                    (string) $worker['label']
                ) ?>
            </h3>
        </div>
    </div>

    <p class="admin-maintenance-worker-description">
        <?= moderation_e(
            (string) $worker[
                'description'
            ]
        ) ?>
    </p>

    <dl class="admin-maintenance-worker-meta">
        <div>
            <dt>Last run</dt>
            <dd>
                <strong>
                    <?= moderation_e(
                        (string) $worker[
                            'age_label'
                        ]
                    ) ?>
                </strong>

                <?php if (
                    !empty(
                        $worker['last_run']
                    )
                ): ?>
                    <small>
                        <?= moderation_e(
                            admin_system_run_time_label(
                                (string) $worker[
                                    'last_run'
                                ]
                            )
                        ) ?>
                    </small>
                <?php endif; ?>
            </dd>
        </div>

        <div>
            <dt>Worker cadence</dt>
            <dd>
                <?= moderation_e(
                    (string) $worker[
                        'cadence_label'
                    ]
                ) ?>
            </dd>
        </div>
    </dl>

    <div class="admin-maintenance-worker-actions">

        <?php if ($actorIsOwner): ?>

        <form
            method="post"
            action="/system.php#automated-maintenance"
            onsubmit="return confirm('Run <?= moderation_e(
                (string) $worker['label']
            ) ?> now? This uses the live production worker and may send due emails or change live account, Scout, Shop, or promotion state.');"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="run_maintenance_worker"
            >

            <input
                type="hidden"
                name="worker"
                value="<?= moderation_e(
                    (string) $worker['id']
                ) ?>"
            >

            <button
                class="admin-button <?= $worker['status'] === 'down'
                    ? 'is-danger'
                    : (
                        $worker['status'] === 'attention'
                            ? ''
                            : 'is-muted'
                    ) ?>"
                type="submit"
            >
                <i aria-hidden="true">
                    <?= llama_icon('refresh') ?>
                </i>

                Run now
            </button>
        </form>

        <?php else: ?>

        <span class="admin-maintenance-owner-note">
            Owner can run manually
        </span>

        <?php endif; ?>

    </div>
</article>

<?php endforeach; ?>

</div>


<div class="admin-maintenance-event-driven">
    <div>
        <strong>Error log cleanup</strong>

        <span>
            Event-driven housekeeping, not health-scored.
            It runs when application errors are recorded.
        </span>
    </div>

    <span>
        <?= moderation_e(
            $lastErrorCleanup
                ? admin_system_run_time_label(
                    $lastErrorCleanup
                )
                : 'No cleanup recorded yet'
        ) ?>
    </span>
</div>

<div class="admin-maintenance-console-note">
    <i aria-hidden="true">
        <?= llama_icon('shield') ?>
    </i>

    <p>
        A manual run bypasses only the time throttle. Existing database
        locks, row transactions, duplicate-send checks, and worker safety
        rules still apply. Every manual run is written to the Admin audit
        log.
    </p>
</div>

</section>

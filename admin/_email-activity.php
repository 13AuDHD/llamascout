<section class="email-activity-stat-grid">

    <div class="email-activity-stat">
        <span>
            <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
            Today
        </span>
        <strong>
            <?= number_format($emailActivityTotals['today']) ?>
        </strong>
    </div>

    <div class="email-activity-stat">
        <span>
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            Live
        </span>
        <strong>
            <?= number_format($emailActivityTotals['live']) ?>
        </strong>
    </div>

    <div class="email-activity-stat">
        <span>
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            Sent
        </span>
        <strong>
            <?= number_format($emailActivityTotals['sent']) ?>
        </strong>
    </div>

    <div class="email-activity-stat<?= $emailActivityTotals['failed'] > 0 ? ' is-warning' : '' ?>">
        <span>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            Failed
        </span>
        <strong>
            <?= number_format($emailActivityTotals['failed']) ?>
        </strong>
    </div>

    <div class="email-activity-stat">
        <span>
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            Tests
        </span>
        <strong>
            <?= number_format($emailActivityTotals['test']) ?>
        </strong>
    </div>

</section>


<section class="admin-panel email-activity-panel">

    <header class="admin-panel-header">
        <div>
            <p>Delivery Log</p>
            <h2>Email Activity</h2>
        </div>

        <span>
            <?= number_format($emailActivityTotals['total']) ?>
            total
        </span>
    </header>


    <form
        class="email-activity-filters"
        method="get"
        action="/email-activity.php"
    >

        <label>
            <span>Status</span>

            <select name="status">
                <option value="">All statuses</option>

                <option
                    value="sent"
                    <?= $statusFilter === 'sent' ? 'selected' : '' ?>
                >
                    Sent
                </option>

                <option
                    value="failed"
                    <?= $statusFilter === 'failed' ? 'selected' : '' ?>
                >
                    Failed
                </option>
            </select>
        </label>


        <label>
            <span>Type</span>

            <select name="kind">
                <option value="">Live + tests</option>

                <option
                    value="live"
                    <?= $kindFilter === 'live' ? 'selected' : '' ?>
                >
                    Live only
                </option>

                <option
                    value="test"
                    <?= $kindFilter === 'test' ? 'selected' : '' ?>
                >
                    Tests only
                </option>
            </select>
        </label>


        <label>
            <span>Template</span>

            <select name="template">
                <option value="">All templates</option>

                <?php foreach (
                    $templateNames
                    as
                    $templateKey => $templateName
                ): ?>
                    <option
                        value="<?= moderation_e($templateKey) ?>"
                        <?= $templateFilter === $templateKey ? 'selected' : '' ?>
                    >
                        <?= moderation_e($templateName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>


        <label class="email-activity-recipient-filter">
            <span>Recipient</span>

            <input
                type="search"
                name="recipient"
                value="<?= moderation_e($recipientFilter) ?>"
                placeholder="Email address"
            >
        </label>


        <label>
            <span>Show</span>

            <select name="limit">
                <?php foreach ([50, 100, 250] as $option): ?>
                    <option
                        value="<?= $option ?>"
                        <?= $limit === $option ? 'selected' : '' ?>
                    >
                        <?= $option ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>


        <div class="email-activity-filter-actions">
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
                class="admin-button is-secondary"
                href="/email-activity.php"
            >
                Clear
            </a>
        </div>

    </form>


    <?php if (!$emailActivityRows): ?>

        <div class="admin-empty-state email-activity-empty">
            <i
                class="fa-regular fa-paper-plane"
                aria-hidden="true"
            ></i>

            <h3>No email activity found.</h3>

            <p>
                New Email Center sends will appear here automatically.
            </p>
        </div>

    <?php else: ?>

        <div class="email-activity-table-wrap">

            <table class="email-activity-table">

                <thead>
                    <tr>
                        <th>When</th>
                        <th>Template</th>
                        <th>Recipient</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach (
                        $emailActivityRows
                        as
                        $row
                    ): ?>
                        <?php
                        $templateKey =
                            (string) (
                                $row['template_key']
                                ?? ''
                            );

                        $templateName =
                            $templateNames[$templateKey]
                            ?? ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $templateKey
                                )
                            );

                        $status =
                            strtolower(
                                (string) (
                                    $row['send_status']
                                    ?? ''
                                )
                            );

                        $isTest =
                            !empty(
                                $row['is_test']
                            );
                        ?>

                        <tr>

                            <td
                                data-label="When"
                                class="email-activity-when"
                            >
                                <?= moderation_e(
                                    llama_format_viewer_datetime(
                                        (string) $row['sent_at']
                                    )
                                ) ?>
                            </td>

                            <td data-label="Template">
                                <a
                                    class="email-activity-template-link"
                                    href="/emails.php?template=<?= rawurlencode($templateKey) ?>"
                                >
                                    <?= moderation_e($templateName) ?>
                                </a>

                                <small>
                                    <?= moderation_e($templateKey) ?>
                                </small>
                            </td>

                            <td
                                data-label="Recipient"
                                class="email-activity-recipient"
                            >
                                <?= moderation_e(
                                    (string) $row['recipient_email']
                                ) ?>

                                <?php if (
                                    (int) (
                                        $row['user_id']
                                        ?? 0
                                    ) > 0
                                ): ?>
                                    <a
                                        href="/user.php?id=<?= (int) $row['user_id'] ?>"
                                    >
                                        User #<?= (int) $row['user_id'] ?>
                                    </a>
                                <?php endif; ?>
                            </td>

                            <td data-label="Type">
                                <span
                                    class="email-activity-pill<?= $isTest ? ' is-test' : ' is-live' ?>"
                                >
                                    <?= $isTest ? 'Test' : 'Live' ?>
                                </span>
                            </td>

                            <td data-label="Status">
                                <span
                                    class="email-activity-pill<?= $status === 'sent' ? ' is-sent' : ' is-failed' ?>"
                                >
                                    <?= $status === 'sent' ? 'Sent' : 'Failed' ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

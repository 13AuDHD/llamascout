<?php

declare(strict_types=1);
?>

<section
    class="admin-place-admin-section"
    id="history"
>
    <header>
        <p>Record</p>
        <h2>History + Provenance</h2>
    </header>

    <?php if ($provenance): ?>
        <div class="admin-place-history-group">
            <h3>Origin</h3>

            <article class="admin-place-history-card">
                <strong>
                    <?= $e(
                        $provenance['origin_type']
                        ?? 'Unknown'
                    ) ?>
                </strong>

                <?php if (!empty($provenance['contributor_name'])): ?>
                    <span>
                        Original contributor:
                        <?= $e(
                            $provenance['contributor_name']
                        ) ?>
                    </span>
                <?php endif; ?>

                <span class="admin-place-muted">
                    Established
                    <?= $e(
                        $provenance['established_at']
                        ?? ''
                    ) ?>
                </span>
            </article>
        </div>
    <?php endif; ?>


    <div class="admin-place-history-group">
        <h3>Status timeline</h3>

        <?php if ($statusHistory): ?>
            <div class="admin-place-history-list">
                <?php foreach ($statusHistory as $entry): ?>
                    <article class="admin-place-history-card">
                        <header>
                            <strong>
                                <?= $e(
                                    ($entry['old_status'] ?? 'New')
                                    . ' -> '
                                    . ($entry['new_status'] ?? '')
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $entry['changed_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span>
                            By
                            <?= $e(
                                $entry['changed_by_name']
                                ?? 'System'
                            ) ?>
                        </span>

                        <?php if (!empty($entry['reason'])): ?>
                            <div>
                                <?= nl2br(
                                    $e(
                                        $entry['reason']
                                    )
                                ) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No status history.
            </div>
        <?php endif; ?>
    </div>


    <div class="admin-place-history-group">
        <h3>
            Contributions
            (<?= count($contributions) ?>)
        </h3>

        <?php if ($contributions): ?>
            <div class="admin-place-history-list">
                <?php foreach ($contributions as $contribution): ?>
                    <article class="admin-place-history-card">
                        <header>
                            <strong>
                                <?= $e(
                                    $contribution['contribution_type']
                                    ?? 'Contribution'
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $contribution['approved_at']
                                    ?? $contribution['created_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span>
                            <?= $e(
                                $contribution['contributor_name']
                                ?? 'Former Llama Scout Member'
                            ) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No contribution history.
            </div>
        <?php endif; ?>
    </div>


    <div class="admin-place-history-group">
        <h3>
            Update submissions
            (<?= count($updateHistory) ?>)
        </h3>

        <?php if ($updateHistory): ?>
            <div class="admin-place-history-list">
                <?php foreach ($updateHistory as $update): ?>
                    <article class="admin-place-history-card">
                        <header>
                            <strong>
                                <?= $e(
                                    ucfirst(
                                        (string) (
                                            $update['status']
                                            ?? 'pending'
                                        )
                                    )
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $update['submitted_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span>
                            <?= $e(
                                $update['contributor_name']
                                ?? 'Former Llama Scout Member'
                            ) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No Place Update history.
            </div>
        <?php endif; ?>
    </div>


    <div class="admin-place-history-group">
        <h3>
            Reports
            (<?= count($reportHistory) ?>)
        </h3>

        <?php if ($reportHistory): ?>
            <div class="admin-place-history-list">
                <?php foreach ($reportHistory as $report): ?>
                    <article class="admin-place-history-card">
                        <header>
                            <strong>
                                <?= $e(
                                    ucfirst(
                                        (string) (
                                            $report['status']
                                            ?? 'open'
                                        )
                                    )
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $report['created_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span>
                            <?= $e(
                                $report['reporter_name']
                                ?? 'Former Llama Scout Member'
                            ) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No reports.
            </div>
        <?php endif; ?>
    </div>


    <?php if ($placeAuditHistory): ?>
        <div class="admin-place-history-group">
            <h3>
                Admin audit
                (<?= count($placeAuditHistory) ?>)
            </h3>

            <div class="admin-place-history-list">
                <?php foreach ($placeAuditHistory as $audit): ?>
                    <article class="admin-place-history-card">
                        <header>
                            <strong>
                                <?= $e(
                                    $audit['action']
                                    ?? 'Admin action'
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $audit['created_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span>
                            <?= $e(
                                $audit['actor_name']
                                ?? 'System'
                            ) ?>
                        </span>

                        <?php if (!empty($audit['summary'])): ?>
                            <div>
                                <?= $e(
                                    $audit['summary']
                                ) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

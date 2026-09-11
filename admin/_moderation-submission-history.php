<?php

declare(strict_types=1);

$submissionHistory =
    llama_place_submission_history(
        $db,
        $submissionId
    );

if (
    !$submissionHistory
    && (string) ($item['status'] ?? '')
        === 'needs-changes'
    && trim(
        (string) (
            $item['review_notes']
            ?? ''
        )
    ) !== ''
) {
    $submissionHistory[] = [
        'type' => 'changes-requested',
        'by' => 'moderator',
        'review_notes' =>
            (string) $item['review_notes'],
        'at' =>
            $item['reviewed_at']
            ?? null,
        'legacy' => true,
    ];
}

if (!$submissionHistory) {
    return;
}

$historyE =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$historyTime =
    static function (
        mixed $value
    ): string {
        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return '';
        }

        return function_exists(
            'llama_format_viewer_datetime'
        )
            ? llama_format_viewer_datetime(
                $value
            )
            : $value;
    };
?>

<section
    class="admin-moderation-detail admin-submission-history"
    aria-labelledby="submission-history-heading"
>
    <header class="admin-moderation-section-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i
                    class="fa-solid fa-clock-rotate-left"
                    aria-hidden="true"
                ></i>
                Revision History
            </p>

            <h2 id="submission-history-heading">
                What changed
            </h2>

            <p>
                Moderation requests and contributor changes stay attached to this submission.
            </p>
        </div>
    </header>

    <div class="admin-submission-history-timeline">

        <?php foreach ($submissionHistory as $event): ?>
            <?php
            $type =
                (string) (
                    $event['type']
                    ?? 'event'
                );

            $title =
                match ($type) {
                    'submitted' =>
                        'Place Report submitted',
                    'changes-requested' =>
                        'Changes requested',
                    'resubmitted' =>
                        'Contributor resubmitted',
                    'approved' =>
                        'Approved and published',
                    'rejected' =>
                        'Not approved',
                    default =>
                        ucwords(
                            str_replace(
                                '-',
                                ' ',
                                $type
                            )
                        ),
                };

            $icon =
                match ($type) {
                    'submitted' =>
                        'fa-paper-plane',
                    'changes-requested' =>
                        'fa-rotate-left',
                    'resubmitted' =>
                        'fa-arrows-rotate',
                    'approved' =>
                        'fa-circle-check',
                    'rejected' =>
                        'fa-circle-xmark',
                    default =>
                        'fa-circle',
                };
            ?>

            <article class="admin-submission-history-event">
                <div class="admin-submission-history-icon">
                    <i
                        class="fa-solid <?= $historyE($icon) ?>"
                        aria-hidden="true"
                    ></i>
                </div>

                <div class="admin-submission-history-body">
                    <header>
                        <strong>
                            <?= $historyE($title) ?>
                        </strong>

                        <?php if (!empty($event['at'])): ?>
                            <time>
                                <?= $historyE(
                                    $historyTime(
                                        $event['at']
                                    )
                                ) ?>
                            </time>
                        <?php endif; ?>
                    </header>

                    <?php if (!empty($event['review_notes'])): ?>
                        <div class="admin-submission-history-note">
                            <span>Moderator note</span>

                            <p>
                                <?= nl2br(
                                    $historyE(
                                        $event['review_notes']
                                    )
                                ) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $type === 'resubmitted'
                    ): ?>
                        <?php
                        $changes =
                            is_array(
                                $event['changes']
                                ?? null
                            )
                                ? $event['changes']
                                : [];

                        $photoDiff =
                            is_array(
                                $event['photos']
                                ?? null
                            )
                                ? $event['photos']
                                : [];
                        ?>

                        <?php if ($changes): ?>
                            <div class="admin-submission-history-diff">
                                <span>
                                    Changed on this resubmission
                                </span>

                                <div class="admin-submission-history-change-list">

                                    <?php foreach ($changes as $change): ?>
                                        <?php
                                        $fieldKey =
                                            (string) (
                                                $change['field']
                                                ?? ''
                                            );

                                        $field =
                                            llama_place_report_fields()[
                                                $fieldKey
                                            ]
                                            ?? null;

                                        $label =
                                            $field
                                                ? rtrim(
                                                    (string) $field['label'],
                                                    '*'
                                                )
                                                : $fieldKey;

                                        $before =
                                            llama_place_submission_display_value(
                                                $fieldKey,
                                                (string) (
                                                    $change['before_state']
                                                    ?? 'unanswered'
                                                ),
                                                $change['before_value']
                                                ?? null
                                            );

                                        $after =
                                            llama_place_submission_display_value(
                                                $fieldKey,
                                                (string) (
                                                    $change['after_state']
                                                    ?? 'unanswered'
                                                ),
                                                $change['after_value']
                                                ?? null
                                            );
                                        ?>

                                        <div class="admin-submission-history-change">
                                            <strong>
                                                <?= $historyE($label) ?>
                                            </strong>

                                            <div>
                                                <span>
                                                    <?= $historyE($before) ?>
                                                </span>

                                                <i
                                                    class="fa-solid fa-arrow-right"
                                                    aria-hidden="true"
                                                ></i>

                                                <span>
                                                    <?= $historyE($after) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                </div>
                            </div>
                        <?php else: ?>
                            <p class="admin-submission-history-meta">
                                No Place Report answers changed.
                            </p>
                        <?php endif; ?>

                        <?php
                        $photoBefore =
                            (int) (
                                $photoDiff['before_count']
                                ?? 0
                            );

                        $photoAfter =
                            (int) (
                                $photoDiff['after_count']
                                ?? 0
                            );

                        $photoAdded =
                            count(
                                is_array(
                                    $photoDiff['added']
                                    ?? null
                                )
                                    ? $photoDiff['added']
                                    : []
                            );

                        $photoRemoved =
                            count(
                                is_array(
                                    $photoDiff['removed']
                                    ?? null
                                )
                                    ? $photoDiff['removed']
                                    : []
                            );
                        ?>

                        <?php if (
                            $photoBefore !== $photoAfter
                            || $photoAdded > 0
                            || $photoRemoved > 0
                        ): ?>
                            <p class="admin-submission-history-meta">
                                Photos:
                                <?= $photoBefore ?>
                                →
                                <?= $photoAfter ?>

                                <?php if ($photoAdded > 0): ?>
                                    · <?= $photoAdded ?> added
                                <?php endif; ?>

                                <?php if ($photoRemoved > 0): ?>
                                    · <?= $photoRemoved ?> removed
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if (
                        $type === 'submitted'
                        && isset($event['answered'])
                    ): ?>
                        <p class="admin-submission-history-meta">
                            <?= (int) $event['answered'] ?>
                            questions answered
                            ·
                            <?= (int) (
                                $event['photo_count']
                                ?? 0
                            ) ?>
                            photos
                        </p>
                    <?php endif; ?>

                </div>
            </article>

        <?php endforeach; ?>

    </div>
</section>

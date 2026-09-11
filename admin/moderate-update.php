<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/place-update.php';

$adminUser =
    moderation_require_admin();

$db = db();

$csrfToken =
    moderation_csrf_token();

require_once __DIR__
    . '/_dashboard.php';

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],

    'updates' =>
        $stats['updates'],

    'reports' =>
        $stats['reports'],

    'orders' =>
        $stats['orders'],

    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Review Place Update';

$adminPageEyebrow =
    'Moderation';

$adminActiveNav =
    'updates';


$updateId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );


$item =
    moderation_update(
        $db,
        $updateId
    );

$error = '';


/*
 * =========================================================
 * NOT FOUND
 * =========================================================
 */

if (!$item) {
    http_response_code(404);

    require __DIR__
        . '/_header.php';

    echo '
        <div class="admin-moderation-notice">
            Update not found.
        </div>
    ';

    require __DIR__
        . '/_footer.php';

    exit;
}


/*
 * =========================================================
 * MODERATION ACTION
 * =========================================================
 */

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
    $action = '';

    try {

        if (
            !moderation_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }


        $action =
            (string) (
                $_POST['action']
                ?? ''
            );

        $notes =
            trim(
                (string) (
                    $_POST['review_notes']
                    ?? ''
                )
            );


        $points =
            llama_points_policy_required(
                $db,
                'approved_place_update'
            );


        $db->beginTransaction();


        /*
         * =================================================
         * APPROVE
         * =================================================
         *
         * Approval now uses the shared Place Report schema
         * through app/place-update.php.
         *
         * Do not route approval back through the older
         * moderation_approve_update() field-definition list.
         */

        if ($action === 'approve') {

            $contributionId =
                llama_place_update_approve(
                    $db,
                    $updateId,
                    (int) $adminUser['id'],
                    $notes
                );


            llama_place_update_record_review(
                $db,
                $updateId,
                (int) $adminUser['id'],
                'approved',
                $notes,
                $item
            );


            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                'place.update_approved',
                'Approved Place update #'
                    . $updateId
                    . '.',
                [
                    'update_id' =>
                        $updateId,

                    'place_id' =>
                        (int) $item['place_id'],

                    'contribution_id' =>
                        $contributionId,

                    'changed_fields' =>
                        array_keys(
                            is_array(
                                $item['proposed']
                                ?? null
                            )
                                ? $item['proposed']
                                : []
                        ),

                    'points_awarded' =>
                        $points,
                ]
            );


            $db->commit();


            header(
                'Location: /updates.php?approved=1'
            );

            exit;
        }


        /*
         * =================================================
         * REQUEST CHANGES / REJECT
         * =================================================
         */

        if (
            in_array(
                $action,
                [
                    'needs-changes',
                    'rejected',
                ],
                true
            )
        ) {

            if ($notes === '') {
                throw new InvalidArgumentException(
                    $action === 'needs-changes'
                        ? 'Add clear review notes explaining what the contributor needs to change.'
                        : 'Add review notes explaining why the update was not approved.'
                );
            }


            llama_place_update_record_review(
                $db,
                $updateId,
                (int) $adminUser['id'],
                $action === 'needs-changes'
                    ? 'changes-requested'
                    : 'rejected',
                $notes,
                $item
            );


            moderation_set_update_status(
                $db,
                $updateId,
                (int) $adminUser['id'],
                $action,
                $notes
            );


            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                $action === 'needs-changes'
                    ? 'place.update_changes_requested'
                    : 'place.update_rejected',
                $action === 'needs-changes'
                    ? 'Requested changes to Place update #'
                        . $updateId
                        . '.'
                    : 'Rejected Place update #'
                        . $updateId
                        . '.',
                [
                    'update_id' =>
                        $updateId,

                    'place_id' =>
                        (int) $item['place_id'],

                    'review_notes' =>
                        $notes,
                ]
            );


            $db->commit();


            header(
                'Location: /updates.php?updated=1'
            );

            exit;
        }


        throw new InvalidArgumentException(
            'Choose a moderation action.'
        );

    } catch (Throwable $exception) {

        if (
            $db->inTransaction()
        ) {
            $db->rollBack();
        }


        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.moderate_update',
                [
                    'update_id' =>
                        $updateId,

                    'action' =>
                        $action,
                ],
                [
                    InvalidArgumentException::class,
                    RuntimeException::class,
                ]
            );


        $error =
            $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The moderation action could not be completed.',
                    $reference
                );
    }


    /*
     * Reload after unsuccessful POST so the page represents
     * the current database state.
     */
    $item =
        moderation_update(
            $db,
            $updateId
        );
}


/*
 * =========================================================
 * PAGE DATA
 * =========================================================
 */

require __DIR__
    . '/_header.php';


$proposed =
    is_array(
        $item['proposed']
        ?? null
    )
        ? $item['proposed']
        : [];


$original =
    is_array(
        $item['original']
        ?? null
    )
        ? $item['original']
        : [];


$photos =
    is_array(
        $item['photo_list']
        ?? null
    )
        ? $item['photo_list']
        : [];


$updatePointValue =
    llama_points_policy_required(
        $db,
        'approved_place_update'
    );


/*
 * Definitions now come from the shared Place Report schema.
 */
$definitions =
    llama_place_update_definitions();


$groupedChanges = [];


foreach (
    $proposed
    as $path => $newValue
) {

    $definition =
        $definitions[$path]
        ?? [
            'label' =>
                ucwords(
                    str_replace(
                        [
                            '.',
                            '_',
                        ],
                        ' ',
                        $path
                    )
                ),

            'section' =>
                'other',

            'group' =>
                'Other',
        ];


    $section =
        (string) (
            $definition['section']
            ?? 'other'
        );


    $groupedChanges[$section]['meta'] =
        llama_place_report_sections()[$section]
        ?? [
            'label' =>
                (string) (
                    $definition['group']
                    ?? 'Other'
                ),

            'icon' =>
                'fa-pen-to-square',
        ];


    $groupedChanges[$section]['changes'][$path] = [
        'definition' =>
            $definition,

        'old' =>
            $original[$path]
            ?? null,

        'new' =>
            $newValue,
    ];
}


/*
 * =========================================================
 * REVISION HISTORY
 * =========================================================
 */

$historyRow =
    llama_place_update_fetch_row(
        $db,
        $updateId
    );

$history =
    $historyRow
        ? llama_place_update_history(
            $historyRow
        )
        : [];


/*
 * =========================================================
 * HELPERS
 * =========================================================
 */

$e =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );


$formatTime =
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


        return
            function_exists(
                'llama_format_viewer_datetime'
            )
                ? llama_format_viewer_datetime(
                    $value
                )
                : $value;
    };
?>


<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/moderate-update.css"
>


<?php if ($error !== ''): ?>

    <div class="admin-moderation-notice">
        <?= moderation_e(
            $error
        ) ?>
    </div>

<?php endif; ?>


<section class="admin-update-summary">

    <div>

        <p class="admin-update-eyebrow">
            Place update
        </p>


        <h2>
            <?= moderation_e(
                $item['place_name']
            ) ?>
        </h2>


        <p>

            Submitted by

            <strong>
                <?= moderation_e(
                    $item['display_name']
                    ?: $item['username']
                ) ?>
            </strong>

            on

            <?= moderation_e(
                llama_format_viewer_datetime(
                    (string) $item['submitted_at']
                )
            ) ?>.

        </p>

    </div>


    <a
        class="admin-button"
        href="https://llamascout.com/place.php?slug=<?= rawurlencode(
            (string) $item['place_slug']
        ) ?>"
        target="_blank"
        rel="noopener"
    >

        <i
            class="fa-solid fa-arrow-up-right-from-square"
            aria-hidden="true"
        ></i>

        Open live Place

    </a>

</section>


<?php if (
    !empty(
        $item['contributor_notes']
    )
): ?>

    <section class="admin-update-note">

        <span>
            Contributor notes
        </span>

        <p>
            <?= nl2br(
                moderation_e(
                    $item['contributor_notes']
                )
            ) ?>
        </p>

    </section>

<?php endif; ?>


<section class="admin-update-changes">

    <header>

        <div>

            <p class="admin-update-eyebrow">
                Current submission
            </p>

            <h2>
                What changed
            </h2>

        </div>


        <span class="admin-update-change-count">

            <?= count(
                $proposed
            ) ?>

            field<?= count(
                $proposed
            ) === 1
                ? ''
                : 's' ?>

        </span>

    </header>


    <?php if (!$proposed): ?>

        <div class="admin-update-empty">
            No field changes are currently proposed.
        </div>

    <?php endif; ?>


    <?php foreach (
        $groupedChanges
        as $group
    ): ?>

        <?php

        $meta =
            $group['meta'];

        $changes =
            $group['changes'];

        ?>


        <section class="admin-update-change-group">

            <h3>

                <i
                    class="fa-solid <?= $e(
                        $meta['icon']
                        ?? 'fa-pen-to-square'
                    ) ?>"
                    aria-hidden="true"
                ></i>

                <?= $e(
                    $meta['label']
                    ?? 'Other'
                ) ?>

            </h3>


            <div class="admin-update-change-grid">


                <?php foreach (
                    $changes
                    as $path => $change
                ): ?>


                    <?php

                    $definition =
                        $change['definition'];

                    $label =
                        (string) (
                            $definition['label']
                            ?? $path
                        );


                    $oldDisplay =
                        llama_place_update_display_value(
                            $path,
                            $change['old']
                        );


                    $newDisplay =
                        llama_place_update_display_value(
                            $path,
                            $change['new'],
                            $change['new'] === null
                        );

                    ?>


                    <article class="admin-update-change-card">

                        <h4>
                            <?= $e(
                                $label
                            ) ?>
                        </h4>


                        <div class="admin-update-before-after">


                            <div class="is-before">

                                <span>
                                    Current when submitted
                                </span>

                                <strong>
                                    <?= $e(
                                        $oldDisplay
                                    ) ?>
                                </strong>

                            </div>


                            <i
                                class="fa-solid fa-arrow-right"
                                aria-hidden="true"
                            ></i>


                            <div class="is-after">

                                <span>
                                    Proposed
                                </span>

                                <strong>
                                    <?= $e(
                                        $newDisplay
                                    ) ?>
                                </strong>

                            </div>


                        </div>

                    </article>


                <?php endforeach; ?>


            </div>

        </section>


    <?php endforeach; ?>


</section>


<?php if ($history): ?>


    <section class="admin-update-history">

        <header>

            <div>

                <p class="admin-update-eyebrow">
                    Review history
                </p>

                <h2>
                    What happened to this update
                </h2>

            </div>

        </header>


        <div class="admin-update-timeline">


            <?php foreach (
                $history
                as $event
            ): ?>


                <?php

                $type =
                    (string) (
                        $event['type']
                        ?? 'event'
                    );


                $title =
                    match ($type) {

                        'submitted' =>
                            'Update submitted',

                        'changes-requested' =>
                            'Moderator requested changes',

                        'resubmitted' =>
                            'Contributor resubmitted',

                        'approved' =>
                            'Update approved',

                        'rejected' =>
                            'Update not approved',

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


                <article class="admin-update-timeline-event">


                    <div class="admin-update-timeline-icon">

                        <i
                            class="fa-solid <?= $e(
                                $icon
                            ) ?>"
                            aria-hidden="true"
                        ></i>

                    </div>


                    <div>


                        <header>

                            <strong>
                                <?= $e(
                                    $title
                                ) ?>
                            </strong>


                            <?php if (
                                !empty(
                                    $event['at']
                                )
                            ): ?>

                                <time>
                                    <?= $e(
                                        $formatTime(
                                            $event['at']
                                        )
                                    ) ?>
                                </time>

                            <?php endif; ?>

                        </header>


                        <?php if (
                            !empty(
                                $event['review_notes']
                            )
                        ): ?>

                            <div class="admin-update-history-note">

                                <span>
                                    Moderator note
                                </span>

                                <p>
                                    <?= nl2br(
                                        $e(
                                            $event['review_notes']
                                        )
                                    ) ?>
                                </p>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $type === 'resubmitted'
                            && !empty(
                                $event['proposal_changes']
                            )
                        ): ?>


                            <div class="admin-update-history-diff">

                                <span>
                                    Changed on resubmission
                                </span>


                                <ul>


                                    <?php foreach (
                                        (array) $event['proposal_changes']
                                        as $path => $difference
                                    ): ?>


                                        <?php

                                        $definition =
                                            $definitions[$path]
                                            ?? [];


                                        $label =
                                            (string) (
                                                $definition['label']
                                                ?? $path
                                            );


                                        $beforeText =
                                            !empty(
                                                $difference['before_present']
                                            )
                                                ? llama_place_update_display_value(
                                                    $path,
                                                    $difference['before'],
                                                    $difference['before'] === null
                                                )
                                                : 'Not previously proposed';


                                        $afterText =
                                            !empty(
                                                $difference['after_present']
                                            )
                                                ? llama_place_update_display_value(
                                                    $path,
                                                    $difference['after'],
                                                    $difference['after'] === null
                                                )
                                                : 'Removed from proposal';

                                        ?>


                                        <li>

                                            <strong>
                                                <?= $e(
                                                    $label
                                                ) ?>
                                            </strong>

                                            <span>

                                                <?= $e(
                                                    $beforeText
                                                ) ?>

                                                →

                                                <?= $e(
                                                    $afterText
                                                ) ?>

                                            </span>

                                        </li>


                                    <?php endforeach; ?>


                                </ul>

                            </div>


                        <?php endif; ?>


                        <?php if (
                            isset(
                                $event['photo_count_before']
                            )
                            || isset(
                                $event['photo_count_after']
                            )
                        ): ?>


                            <p class="admin-update-history-meta">

                                Photos:

                                <?= (int) (
                                    $event['photo_count_before']
                                    ?? 0
                                ) ?>

                                →

                                <?= (int) (
                                    $event['photo_count_after']
                                    ?? 0
                                ) ?>

                            </p>


                        <?php elseif (
                            isset(
                                $event['photo_count']
                            )
                        ): ?>


                            <p class="admin-update-history-meta">

                                Photos:

                                <?= (int) $event['photo_count'] ?>

                            </p>


                        <?php endif; ?>


                    </div>

                </article>


            <?php endforeach; ?>


        </div>

    </section>


<?php endif; ?>


<?php if ($photos): ?>


    <section class="admin-update-photos">

        <h2>
            Submitted Photos
        </h2>


        <div class="admin-moderation-photo-grid">


            <?php foreach (
                $photos
                as $photo
            ): ?>


                <?php

                $src =
                    moderation_photo_path(
                        $photo
                    );


                $photoUrl =
                    $src !== ''
                        ? (
                            preg_match(
                                '#^https?://#i',
                                $src
                            )
                                ? $src
                                : 'https://llamascout.com/'
                                    . ltrim(
                                        $src,
                                        '/'
                                    )
                        )
                        : '';

                ?>


                <?php if (
                    $photoUrl !== ''
                ): ?>


                    <img
                        src="<?= moderation_e(
                            $photoUrl
                        ) ?>"
                        alt="<?= moderation_e(
                            $photo['alt']
                            ?? ''
                        ) ?>"
                    >


                <?php endif; ?>


            <?php endforeach; ?>


        </div>

    </section>


<?php endif; ?>


<?php
require __DIR__
    . '/_moderation-update-points.php';
?>


<section class="admin-update-decision">

    <h2>
        Decision
    </h2>


    <form
        method="post"
        class="admin-moderation-form"
    >


        <input
            type="hidden"
            name="id"
            value="<?= $updateId ?>"
        >


        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(
                $csrfToken
            ) ?>"
        >


        <label>

            Review notes

            <textarea
                name="review_notes"
                rows="5"
                placeholder="Required when requesting changes or not approving. Be specific so the contributor knows exactly what to fix."
            ></textarea>

        </label>


        <div class="admin-moderation-actions">


            <button
                class="admin-moderation-button is-primary"
                type="submit"
                name="action"
                value="approve"
            >
                Approve Update
            </button>


            <button
                class="admin-moderation-button is-warning"
                type="submit"
                name="action"
                value="needs-changes"
            >
                Request Changes
            </button>


            <button
                class="admin-moderation-button is-danger"
                type="submit"
                name="action"
                value="rejected"
            >
                Not Approved
            </button>


        </div>


    </form>

</section>


<?php
require __DIR__
    . '/_footer.php';
?>

<?php

declare(strict_types=1);

// Photo ordering, captions, hero and deletion now live inside the shared
// Scout Report photo gallery. This partial retains internal Place Notes only.
?>

<section
    class="admin-place-admin-section"
    id="notes"
>
    <header>
        <p>Internal</p>
        <h2>Place Notes</h2>
    </header>

    <?php if ($placeNotes): ?>
        <div class="admin-place-note-list">
            <?php foreach ($placeNotes as $note): ?>
                <article class="admin-place-note-card">
                    <header>
                        <strong>
                            <?= $e(
                                $note['author_name']
                                ?? 'System'
                            ) ?>
                        </strong>

                        <span class="admin-place-muted">
                            <?= $e(
                                $note['created_at']
                                ?? ''
                            ) ?>
                        </span>
                    </header>

                    <div>
                        <?= nl2br(
                            $e(
                                $note['note']
                                ?? ''
                            )
                        ) ?>
                    </div>

                    <form method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $e(
                                moderation_csrf_token()
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="place_id"
                            value="<?= $placeId ?>"
                        >

                        <input
                            type="hidden"
                            name="place_admin_action"
                            value="delete-note"
                        >

                        <input
                            type="hidden"
                            name="note_id"
                            value="<?= (int) $note['id'] ?>"
                        >

                        <button
                            class="admin-button is-danger"
                            type="submit"
                        >
                            Delete note
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        class="admin-place-note-form"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(
                moderation_csrf_token()
            ) ?>"
        >

        <input
            type="hidden"
            name="place_id"
            value="<?= $placeId ?>"
        >

        <input
            type="hidden"
            name="place_admin_action"
            value="add-note"
        >

        <label>
            <span>Add internal note</span>

            <textarea
                name="note"
                rows="4"
                maxlength="2000"
            ></textarea>
        </label>

        <button
            class="admin-button"
            type="submit"
        >
            Add note
        </button>
    </form>
</section>

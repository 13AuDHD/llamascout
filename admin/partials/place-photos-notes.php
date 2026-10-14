<?php

declare(strict_types=1);
?>

<section
    class="admin-place-admin-section"
    id="photos"
>
    <header>
        <p>Media</p>
        <h2>Photo management</h2>
    </header>

    <?php if ($images): ?>
        <div class="admin-place-photo-grid">
            <?php foreach ($images as $image): ?>
                <?php
                $imageUrl =
                    admin_place_photo_url(
                        $image
                    );
                ?>

                <article class="admin-place-photo-card">
                    <?php if ($imageUrl !== ''): ?>
                        <img
                            src="<?= $e($imageUrl) ?>"
                            alt="<?= $e(
                                $image['alt_text']
                                ?? ''
                            ) ?>"
                        >
                    <?php endif; ?>

                    <div class="admin-place-photo-card-body">
                        <?php if (
                            (int) (
                                $image['is_featured']
                                ?? 0
                            ) === 1
                        ): ?>
                            <strong>
                                Featured image
                            </strong>
                        <?php endif; ?>

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
                                value="save-image-meta"
                            >

                            <input
                                type="hidden"
                                name="image_id"
                                value="<?= (int) $image['id'] ?>"
                            >

                            <label>
                                <span>Alt text / caption</span>

                                <input
                                    type="text"
                                    name="alt_text"
                                    value="<?= $e(
                                        $image['alt_text']
                                        ?? ''
                                    ) ?>"
                                >
                            </label>

                            <label>
                                <span>Sort order</span>

                                <input
                                    type="number"
                                    name="sort_order"
                                    min="0"
                                    max="999"
                                    value="<?= (int) (
                                        $image['sort_order']
                                        ?? 0
                                    ) ?>"
                                >
                            </label>

                            <button
                                class="admin-button is-muted"
                                type="submit"
                            >
                                Save photo details
                            </button>
                        </form>

                        <div class="admin-place-photo-actions">
                            <?php if (
                                (int) (
                                    $image['is_featured']
                                    ?? 0
                                ) !== 1
                            ): ?>
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
                                        value="featured-image"
                                    >

                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= (int) $image['id'] ?>"
                                    >

                                    <button
                                        class="admin-button is-muted"
                                        type="submit"
                                    >
                                        Make featured
                                    </button>
                                </form>
                            <?php endif; ?>

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
                                    value="delete-image"
                                >

                                <input
                                    type="hidden"
                                    name="image_id"
                                    value="<?= (int) $image['id'] ?>"
                                >

                                <button
                                    class="admin-button is-danger"
                                    type="submit"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="admin-place-empty">
            No photos are attached to this Place.
        </div>
    <?php endif; ?>

    <p class="admin-place-muted">
        New photos can be added from the Photos section inside the shared Place Report above.
    </p>
</section>


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

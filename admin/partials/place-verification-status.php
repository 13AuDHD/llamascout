<?php

declare(strict_types=1);
?>

<section
    class="admin-place-admin-section"
    id="verification"
>
    <header>
        <p>Verification</p>
        <h2>Verification History</h2>
    </header>

    <div class="admin-place-verification-intro">
        <p>
            Verifications document how current Place information was confirmed.
            A Llama Scout field visit establishes the permanent Llama Scouted provenance.
        </p>
    </div>

    <?php if ($verifications): ?>
        <div class="admin-place-verification-list">
            <?php foreach ($verifications as $verification): ?>
                <?php
                $verificationType =
                    (string) (
                        $verification['verification_type']
                        ?? ''
                    );

                $verificationLabel =
                    llama_place_verification_type_label(
                        $verificationType
                    );
                ?>

                <article class="admin-place-verification-card">
                    <div class="admin-place-verification-card-main">
                        <header>
                            <strong>
                                <?= $e($verificationLabel) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $verification['verified_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <div class="admin-place-verification-card-meta">
                            <span>
                                By
                                <?= $e(
                                    $verification['verifier_name']
                                    ?? 'System'
                                ) ?>
                            </span>

                            <?php if (!empty($verification['visited_at'])): ?>
                                <span>
                                    Visited
                                    <?= $e(
                                        $verification['visited_at']
                                    ) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($verification['source'])): ?>
                                <span>
                                    Source:
                                    <?= $e(
                                        $verification['source']
                                    ) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($verification['public_data_verified'])): ?>
                                <span>
                                    Public data verified
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($verification['notes'])): ?>
                            <div class="admin-place-verification-card-notes">
                                <?= nl2br(
                                    $e(
                                        $verification['notes']
                                    )
                                ) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (
                            !isset(
                                llama_place_verification_types()[
                                    $verificationType
                                ]
                            )
                        ): ?>
                            <span class="admin-place-muted">
                                Legacy verification type:
                                <?= $e($verificationType) ?>
                            </span>
                        <?php endif; ?>
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
                            value="delete-verification"
                        >

                        <input
                            type="hidden"
                            name="verification_id"
                            value="<?= (int) $verification['id'] ?>"
                        >

                        <button
                            class="admin-button admin-place-verification-delete"
                            type="submit"
                            data-delete-verification
                        >
                            <i aria-hidden="true">
                                <?= llama_icon('trash') ?>
                            </i>
                            Delete
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="admin-place-empty">
            No verification history yet.
        </div>
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
            value="add-verification"
        >

        <div class="admin-place-verification-grid">
            <label>
                <span>Verification type</span>

                <select
                    name="verification_type"
                    required
                >
                    <option value="">
                        Select...
                    </option>

                    <?php foreach (
                        llama_place_verification_types()
                        as $verificationType => $verificationMeta
                    ): ?>
                        <option
                            value="<?= $e($verificationType) ?>"
                        >
                            <?= $e(
                                $verificationMeta['label']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small class="admin-place-verification-help">
                    Field visit means a Llama Scout personally visited the Place.
                </small>
            </label>

            <label>
                <span>Date visited</span>

                <input
                    type="date"
                    name="visited_at"
                >

                <small class="admin-place-verification-help">
                    Required for a Llama Scout field visit.
                </small>
            </label>

            <label>
                <span>Source</span>

                <input
                    type="text"
                    name="source"
                    placeholder="USFS, BLM, county website..."
                >

                <small class="admin-place-verification-help">
                    Required for Official source verified. Field visits automatically use Llama Scouted.
                </small>
            </label>

            <label>
                <span>Public data verified</span>

                <span>
                    <input
                        type="checkbox"
                        name="public_data_verified"
                        value="1"
                    >
                    Yes
                </span>
            </label>

            <label class="admin-place-verification-wide">
                <span>Notes</span>

                <textarea
                    name="notes"
                    rows="3"
                    placeholder="What was checked, confirmed, or observed?"
                ></textarea>
            </label>
        </div>

        <button
            class="admin-button"
            type="submit"
        >
            <i aria-hidden="true">
                <?= llama_icon('circle-check') ?>
            </i>
            Add verification
        </button>
    </form>
</section>


<section
    class="admin-place-admin-section"
    id="status"
>
    <header>
        <p>Publishing</p>
        <h2>Status</h2>
    </header>

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
            value="change-status"
        >

        <div class="admin-place-status-grid">
            <label>
                <span>Status</span>

                <select name="status">
                    <?php foreach (
                        [
                            'draft',
                            'active',
                            'featured',
                            'unlisted',
                            'removed',
                            'archived',
                        ]
                        as $status
                    ): ?>
                        <option
                            value="<?= $e($status) ?>"
                            <?= (string) $place['status'] === $status
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $e(
                                ucwords(
                                    str_replace(
                                        '-',
                                        ' ',
                                        $status
                                    )
                                )
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-place-status-wide">
                <span>
                    Reason for status change
                </span>

                <textarea
                    name="status_reason"
                    rows="3"
                ></textarea>
            </label>
        </div>

        <button
            class="admin-button"
            type="submit"
        >
            Update status
        </button>
    </form>
</section>

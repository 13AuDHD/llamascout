<?php

declare(strict_types=1);
?>

<div class="admin-moderation-detail">
    <h2>Decision</h2>

    <form
        method="post"
        class="admin-moderation-form admin-submission-decision-form"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $submissionId ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e($csrfToken) ?>"
        >

        <div class="admin-submission-publish-row">
            <label>
                Publish status

                <select name="publish_status">
                    <option value="active">Active</option>
                    <option value="featured">Featured</option>
                </select>
            </label>

            <button
                class="admin-moderation-button is-primary admin-submission-approve"
                type="submit"
                name="action"
                value="approve"
            >
                <i
                    class="fa-solid fa-circle-check"
                    aria-hidden="true"
                ></i>
                Approve and Publish
            </button>
        </div>

        <label>
            Review notes

            <textarea
                name="review_notes"
                rows="5"
                placeholder="Required when not approving. Also useful for documenting anything you corrected or verified."
            ></textarea>
        </label>

        <div class="admin-moderation-actions admin-submission-secondary-actions">
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

            <button
                class="admin-moderation-button admin-submission-delete"
                type="submit"
                name="action"
                value="delete"
                data-delete-submission
            >
                <i
                    class="fa-solid fa-trash-can"
                    aria-hidden="true"
                ></i>
                Delete Submission
            </button>
        </div>
    </form>
</div>

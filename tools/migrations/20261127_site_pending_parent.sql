-- Run against MariaDB 10.11+ after backing up the database.
-- A Site may reference a pending Place submission until the Place is published.
ALTER TABLE place_site_submissions
    MODIFY COLUMN place_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS parent_submission_id BIGINT UNSIGNED NULL AFTER place_id,
    ADD INDEX IF NOT EXISTS idx_site_submissions_pending_parent (parent_submission_id, status);

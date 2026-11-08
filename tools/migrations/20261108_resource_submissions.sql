CREATE TABLE IF NOT EXISTS resource_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submitted_by BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    target_resource_id BIGINT UNSIGNED NULL,
    base_snapshot CHAR(64) NULL,
    pending_key CHAR(64) NULL,
    form_data JSON NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    resource_id BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resource_submission_pending (pending_key),
    KEY idx_resource_submission_queue (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

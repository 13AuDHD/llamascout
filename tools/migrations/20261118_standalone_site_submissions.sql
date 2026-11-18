-- Standalone Site contributions. Run once against a backed-up MariaDB 10.11+
-- database in phpMyAdmin before enabling the separate Add Site flow.
-- Does not alter Places, existing Sites, scoped answers or Scout Report history.
CREATE TABLE IF NOT EXISTS place_site_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    role_at_submission VARCHAR(48) NOT NULL DEFAULT 'user',
    site_name VARCHAR(120) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    submission_data LONGTEXT NOT NULL,
    published_site_id BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_site_submissions_queue (status, created_at, id),
    KEY idx_site_submissions_parent (place_id, status),
    KEY idx_site_submissions_author (submitted_by, created_at),
    KEY idx_site_submissions_published (published_site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

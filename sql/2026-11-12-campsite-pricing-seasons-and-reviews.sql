-- Llama Scout: annual and date-specific campsite operating seasons.
-- Apply once using the database manager after reviewing a backup.
-- Existing place_seasons records remain recurring, with no absolute dates.
-- MariaDB 10.11+.
ALTER TABLE place_seasons
    ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN starts_on DATE NULL,
    ADD COLUMN ends_on DATE NULL;

-- Review scheduling belongs to the pricing workflow and is not an operating closure.
-- One row per Place or Site; a NULL site_id is the Place-level pricing review.
-- This can later be extended to a rate group without scheduling 300 separate reminders.
CREATE TABLE IF NOT EXISTS place_pricing_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NULL,
    next_review_on DATE NULL,
    availability_release_on DATETIME NULL,
    last_checked_on DATE NULL,
    source_url VARCHAR(2048) NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pricing_review_next (next_review_on, place_id),
    INDEX idx_pricing_release_next (availability_release_on, place_id),
    INDEX idx_pricing_review_place (place_id, site_id),
    UNIQUE KEY uniq_pricing_review_site (place_id, site_id)
);

-- V3 Site visibility: public is an explicit reviewed opt-in.
-- Existing Sites remain member-only until individually approved for public access.
-- Never bulk-publish imported, primitive, dispersed, or curated Sites.
CREATE TABLE IF NOT EXISTS place_site_visibility (
    campsite_id BIGINT UNSIGNED NOT NULL,
    place_id BIGINT UNSIGNED NOT NULL,
    visibility ENUM('members', 'public') NOT NULL DEFAULT 'members',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (campsite_id),
    KEY idx_place_site_visibility_place (place_id, visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

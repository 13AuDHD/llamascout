-- M4-002: normal Site operating status by named season.
-- Run once in phpMyAdmin AFTER M4-001.
-- This does NOT modify or replace verified Place/Site closures.
-- It only records the normal seasonal pattern for a particular Site.
-- Missing row means: use normal Site behavior.

CREATE TABLE IF NOT EXISTS place_season_site_status (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    place_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NOT NULL,
    target_source VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    operating_status VARCHAR(16) NOT NULL,
    source_url VARCHAR(2048) NULL,
    approved_by BIGINT UNSIGNED NOT NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_season_site_status (
        season_id, target_source, target_id
    ),
    KEY ix_season_site_status_target (
        place_id, target_source, target_id, season_id
    ),
    CONSTRAINT fk_season_site_status_season
        FOREIGN KEY (place_id, season_id) REFERENCES place_seasons(place_id, id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SHOW TABLES LIKE 'place_season_site_status';

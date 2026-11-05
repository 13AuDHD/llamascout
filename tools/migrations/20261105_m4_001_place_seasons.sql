-- M4-001: Place seasons and future seasonal canonical-answer overrides.
-- Apply ONCE through phpMyAdmin. Safe for existing Places, Sites, Scout Reports,
-- pricing, and verified closure records: no existing table is modified.
--
-- Recurring MM-DD ranges are inclusive and may cross December/January.
-- A NULL start/end pair means a manually selectable season with no automatic date.
-- The season service enforces one active default per Place, non-overlapping
-- date ranges, and unique names under a Place row lock.
--
-- Compatible with shared-host phpMyAdmin: no INFORMATION_SCHEMA queries,
-- generated columns, or CHECK syntax required. CREATE IF NOT EXISTS makes
-- a retry safe even if the first statement succeeded on an earlier attempt.

CREATE TABLE IF NOT EXISTS place_seasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    place_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_mmdd CHAR(5) NULL,
    end_mmdd CHAR(5) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    source_url VARCHAR(2048) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_place_seasons_place_id (place_id, id),
    KEY ix_place_seasons_place (place_id, is_active, sort_order, id),
    KEY ix_place_seasons_default (place_id, is_default, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reserved for M4-002. Season answers use ORIGINAL canonical question keys,
-- values, and states, scoped to the same Place/Area/Site identities.
-- They do not overwrite place_report_current_answers or immutable Scout Reports.
-- Only approved Admin/Owner records will publish through this table initially.
CREATE TABLE IF NOT EXISTS place_season_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    place_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NOT NULL,
    target_scope VARCHAR(16) NOT NULL,
    target_source VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(191) NOT NULL,
    answer_state VARCHAR(16) NOT NULL,
    value_json JSON NOT NULL,
    source_url VARCHAR(2048) NULL,
    source_note TEXT NULL,
    approved_by BIGINT UNSIGNED NOT NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_place_season_answer (
        season_id, target_scope, target_source, target_id, question_key
    ),
    KEY ix_place_season_answer_target (
        place_id, target_scope, target_source, target_id, season_id
    ),
    -- Composite FK guarantees the selected season belongs to the target Place.
    CONSTRAINT fk_place_season_answer_season
        FOREIGN KEY (place_id, season_id) REFERENCES place_seasons(place_id, id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Installation verification (each statement returns the matching table):
-- These only inspect the currently selected database.
SHOW TABLES LIKE 'place_seasons';
SHOW TABLES LIKE 'place_season_answers';

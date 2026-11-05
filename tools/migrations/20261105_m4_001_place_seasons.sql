-- M4-001: Place seasons and future seasonal canonical-answer overrides.
-- Apply ONCE through phpMyAdmin. Safe for existing Places, Sites, Scout Reports,
-- pricing, and verified closure records: no existing table is modified.
--
-- Recurring MM-DD ranges are inclusive and may cross December/January.
-- A NULL start/end pair means a manually selectable season with no automatic date.
-- Only one ACTIVE season can be the default for a Place.
-- The application also rejects overlapping active dated seasons and name collisions.

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

    -- NULL values may repeat; an active default Place ID may not.
    active_default_place_id BIGINT UNSIGNED
        AS (CASE WHEN is_default = 1 AND is_active = 1
                 THEN place_id ELSE NULL END) PERSISTENT,

    PRIMARY KEY (id),
    KEY ix_place_seasons_place (place_id, is_active, sort_order, id),
    UNIQUE KEY uq_place_seasons_active_default (active_default_place_id),
    CONSTRAINT chk_place_seasons_date_pair CHECK (
        (start_mmdd IS NULL AND end_mmdd IS NULL) OR
        (start_mmdd IS NOT NULL AND end_mmdd IS NOT NULL)
    ),
    CONSTRAINT chk_place_seasons_default CHECK (
        is_default IN (0, 1) AND is_active IN (0, 1)
    )
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
    CONSTRAINT fk_place_season_answer_season
        FOREIGN KEY (season_id) REFERENCES place_seasons(id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_place_season_answer_scope
        CHECK (target_scope IN ('place', 'area', 'site')),
    CONSTRAINT chk_place_season_answer_state
        CHECK (answer_state IN ('answered', 'unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Installation verification (should return two rows):
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('place_seasons', 'place_season_answers')
ORDER BY TABLE_NAME;

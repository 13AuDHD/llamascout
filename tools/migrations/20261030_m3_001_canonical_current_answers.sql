-- V3 Milestone 3: shared current-answer storage for all reporting targets.
-- Apply through phpMyAdmin before enabling readers/writers of this table.
-- No existing Place columns, scoped answers, or immutable reports are deleted.
-- This table holds only current values; scout_report_answers remains immutable history.
CREATE TABLE IF NOT EXISTS place_report_current_answers (
    place_id BIGINT UNSIGNED NOT NULL,
    target_scope VARCHAR(16) NOT NULL,
    target_source VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(191) NOT NULL,
    answer_state VARCHAR(16) NOT NULL,
    value_json JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (place_id, target_scope, target_source, target_id, question_key),
    KEY ix_place_report_current_answers_target (place_id, target_scope, target_source, target_id),
    CONSTRAINT chk_place_report_current_answers_scope
        CHECK (target_scope IN ('place', 'area', 'site')),
    CONSTRAINT chk_place_report_current_answers_state
        CHECK (answer_state IN ('answered', 'unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviewed migration; apply before deploying the M2-006 PHP changes.
CREATE TABLE IF NOT EXISTS scout_report_schemas (
    schema_version CHAR(64) NOT NULL,
    definition_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scout_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    logical_report_key VARCHAR(191) NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    prior_revision_id BIGINT UNSIGNED NULL,
    amends_report_id BIGINT UNSIGNED NULL,
    submission_bundle_key VARCHAR(191) NOT NULL,
    place_id BIGINT UNSIGNED NOT NULL,
    target_scope VARCHAR(16) NOT NULL,
    target_source VARCHAR(64) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    target_label VARCHAR(255) NULL,
    contributor_id BIGINT UNSIGNED NOT NULL,
    role_at_submission VARCHAR(32) NOT NULL,
    visit_date DATE NULL,
    submitted_at DATETIME NOT NULL,
    schema_version CHAR(64) NOT NULL,
    source_channel VARCHAR(32) NOT NULL,
    client_submission_key VARCHAR(191) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scout_reports_logical_revision
        (logical_report_key, revision_number),
    UNIQUE KEY uq_scout_reports_contributor_client
        (contributor_id, client_submission_key),
    KEY ix_scout_reports_place_target
        (place_id, target_scope, target_source, target_id),
    KEY ix_scout_reports_submitted_at
        (submitted_at),
    KEY ix_scout_reports_schema
        (schema_version),
    CONSTRAINT chk_scout_reports_target_scope
        CHECK (target_scope IN ('place', 'area', 'site')),
    CONSTRAINT chk_scout_reports_revision
        CHECK (revision_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scout_report_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(191) NOT NULL,
    answer_state VARCHAR(16) NOT NULL,
    value_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scout_report_answers_report_question
        (report_id, question_key),
    KEY ix_scout_report_answers_question
        (question_key),
    CONSTRAINT chk_scout_report_answers_state
        CHECK (answer_state IN ('answered', 'unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scout_report_moderation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    reason TEXT NULL,
    metadata_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_scout_report_moderation_events_report_created
        (report_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scout_report_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    photo_path VARCHAR(1024) NOT NULL,
    photo_path_sha256 CHAR(64) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    photo_snapshot_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scout_report_photos_report_path
        (report_id, photo_path_sha256),
    KEY ix_scout_report_photos_report_order
        (report_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP TRIGGER IF EXISTS scout_report_schemas_bu_immutable$$
CREATE TRIGGER scout_report_schemas_bu_immutable
BEFORE UPDATE ON scout_report_schemas
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_schemas_bd_immutable$$
CREATE TRIGGER scout_report_schemas_bd_immutable
BEFORE DELETE ON scout_report_schemas
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_reports_bu_immutable$$
CREATE TRIGGER scout_reports_bu_immutable
BEFORE UPDATE ON scout_reports
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_reports_bd_immutable$$
CREATE TRIGGER scout_reports_bd_immutable
BEFORE DELETE ON scout_reports
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_answers_bu_immutable$$
CREATE TRIGGER scout_report_answers_bu_immutable
BEFORE UPDATE ON scout_report_answers
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_answers_bd_immutable$$
CREATE TRIGGER scout_report_answers_bd_immutable
BEFORE DELETE ON scout_report_answers
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_moderation_events_bu_immutable$$
CREATE TRIGGER scout_report_moderation_events_bu_immutable
BEFORE UPDATE ON scout_report_moderation_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_moderation_events_bd_immutable$$
CREATE TRIGGER scout_report_moderation_events_bd_immutable
BEFORE DELETE ON scout_report_moderation_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_photos_bu_immutable$$
CREATE TRIGGER scout_report_photos_bu_immutable
BEFORE UPDATE ON scout_report_photos
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DROP TRIGGER IF EXISTS scout_report_photos_bd_immutable$$
CREATE TRIGGER scout_report_photos_bd_immutable
BEFORE DELETE ON scout_report_photos
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scout Report history is append-only';
END$$

DELIMITER ;

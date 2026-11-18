-- RESOURCE SCOUT REPORT LEDGER, SCHEMA-ONLY CANDIDATE.
-- MariaDB 10.11. Review and test before manually applying in production.
-- Do not treat table creation as authorization to publish Resource reports.
-- These are new sidecar tables. Existing Place/Site/Area Scout Report tables,
-- Resource records, linked Facility parent IDs and historical Experience stay unchanged.
-- One approved Resource submission may eventually create ONE immutable report.
-- The ordinary Resource moderation writer is NOT connected in this PR.

CREATE TABLE IF NOT EXISTS resource_scout_report_schemas (
    schema_version CHAR(64) NOT NULL PRIMARY KEY,
    definition_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_scout_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    resource_id BIGINT UNSIGNED NOT NULL,
    target_kind VARCHAR(16) NOT NULL,
    parent_place_id BIGINT UNSIGNED NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    logical_report_key VARCHAR(191) NOT NULL,
    revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    prior_report_id BIGINT UNSIGNED NULL,
    contributor_id BIGINT UNSIGNED NOT NULL,
    role_at_submission VARCHAR(32) NOT NULL DEFAULT 'user',
    submitted_at DATETIME NOT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at DATETIME NOT NULL,
    visit_date DATE NULL,
    schema_version CHAR(64) NOT NULL,
    client_submission_key VARCHAR(191) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resource_report_submission (submission_id),
    UNIQUE KEY uq_resource_report_revision (logical_report_key, revision_number),
    UNIQUE KEY uq_resource_report_client (contributor_id, client_submission_key),
    KEY ix_resource_report_target (resource_id, target_kind, id),
    KEY ix_resource_report_schema (schema_version),
    CONSTRAINT fk_resource_report_record
        FOREIGN KEY (resource_id) REFERENCES resources (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_resource_report_submission
        FOREIGN KEY (submission_id) REFERENCES resource_submissions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_resource_report_schema
        FOREIGN KEY (schema_version)
        REFERENCES resource_scout_report_schemas (schema_version)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_resource_report_kind
        CHECK (
            (target_kind = 'resource' AND parent_place_id IS NULL)
            OR
            (target_kind = 'facility' AND parent_place_id IS NOT NULL AND parent_place_id > 0)
        ),
    CONSTRAINT chk_resource_report_revision CHECK (revision_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_scout_report_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    service_type VARCHAR(64) NOT NULL,
    group_key VARCHAR(64) NOT NULL,
    question_key VARCHAR(191) NOT NULL,
    canonical_question_key VARCHAR(191) NULL,
    answer_state VARCHAR(16) NOT NULL,
    value_json JSON NOT NULL,
    legacy_value_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resource_answer_occurrence (
        report_id, service_type, group_key, question_key
    ),
    KEY ix_resource_answer_question (question_key),
    CONSTRAINT fk_resource_answer_report
        FOREIGN KEY (report_id) REFERENCES resource_scout_reports (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_resource_answer_state
        CHECK (answer_state IN ('answered', 'unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_scout_report_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    reason TEXT NULL,
    metadata_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_resource_event_report (report_id, created_at),
    CONSTRAINT fk_resource_event_report
        FOREIGN KEY (report_id) REFERENCES resource_scout_reports (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_scout_report_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    photo_path VARCHAR(1024) NOT NULL,
    photo_path_sha256 CHAR(64) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    photo_snapshot_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resource_report_photo (report_id, photo_path_sha256),
    KEY ix_resource_photo_order (report_id, sort_order),
    CONSTRAINT fk_resource_photo_report
        FOREIGN KEY (report_id) REFERENCES resource_scout_reports (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Future current-answer projection. Existing resources.experience_json stays
-- authoritative until the reviewed moderation writer and cutover are complete.
-- A cleared answer can be represented as an explicit 'unanswered' tombstone.
-- Current answers are mutable by design; immutable report evidence above is not.
CREATE TABLE IF NOT EXISTS resource_scout_current_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    resource_id BIGINT UNSIGNED NOT NULL,
    target_kind VARCHAR(16) NOT NULL,
    service_type VARCHAR(64) NOT NULL,
    group_key VARCHAR(64) NOT NULL,
    question_key VARCHAR(191) NOT NULL,
    answer_state VARCHAR(16) NOT NULL,
    value_json JSON NOT NULL,
    source_report_id BIGINT UNSIGNED NOT NULL,
    row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resource_current_occurrence (
        resource_id, service_type, group_key, question_key
    ),
    KEY ix_resource_current_report (source_report_id),
    CONSTRAINT fk_resource_current_record
        FOREIGN KEY (resource_id) REFERENCES resources (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_resource_current_report
        FOREIGN KEY (source_report_id) REFERENCES resource_scout_reports (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_resource_current_kind
        CHECK (target_kind IN ('resource', 'facility')),
    CONSTRAINT chk_resource_current_state
        CHECK (answer_state IN ('answered', 'unknown', 'unanswered')),
    CONSTRAINT chk_resource_current_version CHECK (row_version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All immutable tables are protected from UPDATE and DELETE.
-- One-statement triggers need no DELIMITER directive, so this SQL also
-- works in phpMyAdmin and in PDO-backed disposable MariaDB tests.
CREATE TRIGGER IF NOT EXISTS rs_schema_immutable_u
    BEFORE UPDATE ON resource_scout_report_schemas
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_schema_immutable_d
    BEFORE DELETE ON resource_scout_report_schemas
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_report_immutable_u
    BEFORE UPDATE ON resource_scout_reports
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_report_immutable_d
    BEFORE DELETE ON resource_scout_reports
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_answer_immutable_u
    BEFORE UPDATE ON resource_scout_report_answers
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_answer_immutable_d
    BEFORE DELETE ON resource_scout_report_answers
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_event_immutable_u
    BEFORE UPDATE ON resource_scout_report_events
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_event_immutable_d
    BEFORE DELETE ON resource_scout_report_events
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_photo_immutable_u
    BEFORE UPDATE ON resource_scout_report_photos
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

CREATE TRIGGER IF NOT EXISTS rs_photo_immutable_d
    BEFORE DELETE ON resource_scout_report_photos
    FOR EACH ROW SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Resource report history is append-only';

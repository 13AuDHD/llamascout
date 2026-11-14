-- Run once in phpMyAdmin before using permanent Site deletion on a Site
-- that has approved Scout Report history.
--
-- Existing history remains append-only for ordinary workflows.
-- Only a deliberately scoped Site deletion transaction can remove the four
-- history record types. UPDATE operations remain permanently forbidden.
--
-- IMPORTANT: This is a destructive administrative capability. Back up the
-- database before running the migration.

DELIMITER $$

DROP TRIGGER IF EXISTS scout_reports_bd_immutable$$
CREATE TRIGGER scout_reports_bd_immutable
BEFORE DELETE ON scout_reports
FOR EACH ROW
BEGIN
    IF COALESCE(@llama_site_delete_report_id, 0) <> OLD.id
       OR COALESCE(@llama_site_delete_place_id, 0) <> OLD.place_id
       OR COALESCE(@llama_site_delete_site_id, 0) <> OLD.target_id
       OR OLD.target_scope <> 'site'
       OR OLD.target_source <> 'campsite_record'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Scout Report history is append-only';
    END IF;
END$$

DROP TRIGGER IF EXISTS scout_report_answers_bd_immutable$$
CREATE TRIGGER scout_report_answers_bd_immutable
BEFORE DELETE ON scout_report_answers
FOR EACH ROW
BEGIN
    IF COALESCE(@llama_site_delete_report_id, 0) <> OLD.report_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Scout Report history is append-only';
    END IF;
END$$

DROP TRIGGER IF EXISTS scout_report_photos_bd_immutable$$
CREATE TRIGGER scout_report_photos_bd_immutable
BEFORE DELETE ON scout_report_photos
FOR EACH ROW
BEGIN
    IF COALESCE(@llama_site_delete_report_id, 0) <> OLD.report_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Scout Report history is append-only';
    END IF;
END$$

DROP TRIGGER IF EXISTS scout_report_moderation_events_bd_immutable$$
CREATE TRIGGER scout_report_moderation_events_bd_immutable
BEFORE DELETE ON scout_report_moderation_events
FOR EACH ROW
BEGIN
    IF COALESCE(@llama_site_delete_report_id, 0) <> OLD.report_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Scout Report history is append-only';
    END IF;
END$$

DELIMITER ;

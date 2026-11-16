-- Existing Resource schema has UNIQUE(name), which prevents two different
-- Places from having facilities both named "Restroom", "Laundry", etc.
-- This changes ONLY the name index. It does not touch names, slugs, photos,
-- coordinates, Place links or historical mapped Area records.
--
-- Run once after the original Resource migrations and before relying on
-- repeated Facility names at different Places.
-- Application validation still prevents duplicates inside a single Place
-- and among standalone Resources.

ALTER TABLE resources
    DROP INDEX uq_resources_name,
    ADD INDEX idx_resources_name (name);

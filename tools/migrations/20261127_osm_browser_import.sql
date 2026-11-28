-- Primary Llama Scout database. Existing regional snapshots and jobs remain intact.
ALTER TABLE osm_resource_import_jobs
    ADD COLUMN IF NOT EXISTS import_mode ENUM('local','browser') NOT NULL DEFAULT 'local',
    ADD COLUMN IF NOT EXISTS source_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS source_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS source_row_offset INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS source_validator VARCHAR(255) NULL;

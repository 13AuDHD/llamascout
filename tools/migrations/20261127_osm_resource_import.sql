-- Primary Llama Scout database. Existing Colorado/Oregon snapshots remain intact.
CREATE TABLE IF NOT EXISTS osm_resource_import_jobs (
    region VARCHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
    batch_id CHAR(32) CHARACTER SET ascii NOT NULL,
    status ENUM('queued','importing','complete','error','cancelled') NOT NULL DEFAULT 'queued',
    snapshot_date DATE NULL,
    expected_rows INT UNSIGNED NOT NULL DEFAULT 0,
    received_rows INT UNSIGNED NOT NULL DEFAULT 0,
    next_chunk INT UNSIGNED NOT NULL DEFAULT 0,
    message VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_osm_jobs_batch (batch_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS osm_resource_import_stage LIKE osm_nearest_services;

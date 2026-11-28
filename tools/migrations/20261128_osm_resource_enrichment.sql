-- Independent resumable state scan and ODbL source facts. No existing rows are changed.
CREATE TABLE IF NOT EXISTS osm_resource_enrichment_jobs (
    region VARCHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
    source_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
    source_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    source_validator VARCHAR(255) NOT NULL DEFAULT '',
    snapshot_date DATE NULL,
    status ENUM('scanning','complete') NOT NULL DEFAULT 'scanning',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS osm_resource_enrichment_facts (
    osm_type ENUM('node','way','relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    region VARCHAR(64) CHARACTER SET ascii NOT NULL,
    snapshot_date DATE NOT NULL,
    tags JSON NOT NULL,
    applied_fields JSON NOT NULL,
    review_reason VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (osm_type, osm_id),
    KEY idx_osm_enrichment_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Source identity and immutable imported baseline, separate from Scout observations.
CREATE TABLE IF NOT EXISTS osm_resource_publications (
    osm_type ENUM('node','way','relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    region VARCHAR(64) CHARACTER SET ascii NOT NULL,
    source_snapshot JSON NOT NULL,
    disposition ENUM('published','review') NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (osm_type, osm_id),
    UNIQUE KEY uq_osm_publication_resource (resource_id),
    KEY idx_osm_publication_region (region,disposition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

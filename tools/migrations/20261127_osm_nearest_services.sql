-- Independent ODbL reference layer. No Scout records are altered.
CREATE TABLE IF NOT EXISTS osm_nearest_services (
    region VARCHAR(64) CHARACTER SET ascii NOT NULL,
    osm_type ENUM('node','way','relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    service_type VARCHAR(32) CHARACTER SET ascii NOT NULL,
    name VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(11,7) NOT NULL,
    snapshot_date DATE NOT NULL,
    PRIMARY KEY (region, osm_type, osm_id, service_type),
    KEY idx_osm_nearest_lat_lon (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

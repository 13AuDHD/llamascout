CREATE TABLE IF NOT EXISTS resource_photo_discovery (
    resource_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    location_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    status ENUM('none','review','attached','skipped') NOT NULL,
    candidates JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_resource_photo_discovery_status (status,resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

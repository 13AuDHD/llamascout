CREATE TABLE IF NOT EXISTS resource_kartaview_discovery (
    resource_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    location_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    status ENUM('attached','none','unusable') NOT NULL,
    photo JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_kartaview_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kartaview_requests_time (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

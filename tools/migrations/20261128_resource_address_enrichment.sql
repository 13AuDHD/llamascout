CREATE TABLE IF NOT EXISTS resource_address_enrichment (
    resource_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    status ENUM('filled','partial','review','none') NOT NULL,
    candidate JSON NOT NULL,
    applied_fields JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_resource_address_status (status,resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_address_daily_usage (
    usage_date DATE NOT NULL PRIMARY KEY,
    requests INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE resources
    ADD COLUMN hours_mode VARCHAR(16) NULL AFTER hours_text,
    ADD COLUMN fixed_open_hour TINYINT UNSIGNED NULL AFTER hours_mode,
    ADD COLUMN fixed_close_hour TINYINT UNSIGNED NULL AFTER fixed_open_hour;

UPDATE resources
SET hours_mode = '24_hours'
WHERE LOWER(TRIM(COALESCE(hours_text, ''))) = '24 hours'
  AND (hours_mode IS NULL OR hours_mode = '');

CREATE TABLE IF NOT EXISTS resource_weekly_hours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    open_hour TINYINT UNSIGNED NOT NULL,
    close_hour TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resource_weekly_hours_day (
        resource_id,
        day_of_week
    ),
    KEY idx_resource_weekly_hours_resource (
        resource_id,
        day_of_week
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE resources
    ADD COLUMN slug VARCHAR(190) NULL AFTER name;

UPDATE resources
SET slug =
    TRIM(
        BOTH '-'
        FROM LOWER(
            REGEXP_REPLACE(
                name,
                '[^A-Za-z0-9]+',
                '-'
            )
        )
    )
WHERE slug IS NULL
   OR slug = '';

ALTER TABLE resources
    MODIFY slug VARCHAR(190) NOT NULL,
    ADD UNIQUE KEY uq_resources_name (name),
    ADD UNIQUE KEY uq_resources_slug (slug);

CREATE TABLE IF NOT EXISTS resource_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id BIGINT UNSIGNED NOT NULL,
    src VARCHAR(500) NOT NULL,
    alt_text VARCHAR(300) NULL,
    caption VARCHAR(500) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    source_provider VARCHAR(160) NULL,
    source_url TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resource_images_owner (
        resource_id,
        is_featured,
        sort_order,
        id
    ),
    KEY idx_resource_images_uploaded_by (uploaded_by)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

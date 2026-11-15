-- Required once, after a database backup, before saving an unmapped Area.
-- Existing geometry values are preserved. Site/Place geometry remains mandatory
-- in PHP validation. Only intentional unmapped Area identities may store NULL.
--
-- MariaDB error #1252: geometry is currently in a SPATIAL index, whose parts
-- must be NOT NULL. A nullable geometry cannot keep that SPATIAL index.
-- Discover its live name, drop only SPATIAL indexes on this geometry column,
-- and make the column nullable in ONE ALTER TABLE. No index name is guessed.
-- Existing place_id/feature_type indexes are preserved.
SET @ls_area_spatial_drops = (
    SELECT GROUP_CONCAT(
        DISTINCT CONCAT('DROP INDEX `', REPLACE(INDEX_NAME, '`', '``'), '`')
        ORDER BY INDEX_NAME SEPARATOR ', '
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'place_map_features'
      AND COLUMN_NAME = 'geometry'
      AND INDEX_TYPE = 'SPATIAL'
);
SET @ls_area_alter = CONCAT(
    'ALTER TABLE `place_map_features` ',
    IF(@ls_area_spatial_drops IS NULL, '',
       CONCAT(@ls_area_spatial_drops, ', ')),
    'MODIFY COLUMN `geometry` GEOMETRY NULL'
);
PREPARE ls_area_schema_stmt FROM @ls_area_alter;
EXECUTE ls_area_schema_stmt;
DEALLOCATE PREPARE ls_area_schema_stmt;

-- Unmapped Area identities use geometry_type = 'unmapped', never fake 0,0 GPS.
-- The existing geometry_type VARCHAR(16) accepts the new label.
-- Sanity checks (inspect results in phpMyAdmin):
SELECT COUNT(*) AS mapped_existing_features
FROM place_map_features WHERE geometry IS NOT NULL;
SELECT COUNT(*) AS unmapped_area_records
FROM place_map_features
WHERE geometry IS NULL
  AND feature_type IN ('camping_area','parking_area','facility_area');
-- This query should always return zero:
SELECT id, place_id, feature_type
FROM place_map_features
WHERE geometry IS NULL
  AND feature_type NOT IN ('camping_area','parking_area','facility_area');

CREATE TABLE IF NOT EXISTS place_area_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    place_id BIGINT NOT NULL,
    submitted_by BIGINT NOT NULL,
    area_name VARCHAR(120) NOT NULL,
    feature_type VARCHAR(32) NOT NULL,
    facility_kind VARCHAR(48) NULL,
    answers_json LONGTEXT NOT NULL,
    status ENUM('pending','approved','rejected','needs-changes') NOT NULL DEFAULT 'pending',
    published_feature_id BIGINT NULL,
    reviewed_by BIGINT NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_place_area_submissions_status (status, created_at),
    INDEX idx_place_area_submissions_place (place_id, status),
    INDEX idx_place_area_submissions_user (submitted_by, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

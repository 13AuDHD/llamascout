-- Required once, after a database backup, before saving an unmapped Area.
-- Existing features and their geometries remain unchanged.
-- Site and Place geometries are still required by the PHP validators.
-- NULL geometry is permitted ONLY for intentional unmapped Area identities.
ALTER TABLE place_map_features
  MODIFY COLUMN geometry GEOMETRY NULL;

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

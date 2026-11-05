-- Llama Scout v3: legacy campground perimeter classification
-- This is a ONE-TIME, MANUAL migration. It does not run when code is deployed.
--
-- Purpose: an old map editor saved campground-wide outer boundaries as
-- camping_area. In v3 these are Places (green), while genuine Areas are
-- purple and Sites are orange.
--
-- Safety guards:
--   * Only campground/camping Place types.
--   * One and only one active camping_area polygon for the Place.
--   * No active place_boundary already present.
--   * The polygon label matches the Place name (or "<Place name> boundary").
--   * No Site records refer to it as a parent Area or a mapped Site.
--   * No Area-specific pricing or Site-class records refer to it.
--   * Polygon coordinates, IDs, and all other attributes are preserved.
-- Rows not matching these guards are deliberately skipped for review.
--
-- Run in phpMyAdmin AFTER reviewing the eligible rows from the SELECT below.
-- A persistent, ID-keyed audit backup is written before the UPDATE.

-- PREVIEW: eligible legacy perimeter records.
SELECT
    f.id AS map_feature_id,
    p.id AS place_id,
    p.name AS place_name,
    p.type AS place_type,
    f.label AS current_label,
    f.feature_type AS current_type
FROM place_map_features AS f
INNER JOIN places AS p
    ON p.id = f.place_id
WHERE f.is_active = 1
  AND f.feature_type = 'camping_area'
  AND p.type IN (
      'developed-campground',
      'rv-park-resort',
      'dispersed-camping',
      'camping-area'
  )
  AND (
      LOWER(TRIM(f.label)) = LOWER(TRIM(p.name))
      OR LOWER(TRIM(f.label)) = LOWER(CONCAT(TRIM(p.name), ' boundary'))
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_features AS b
      WHERE b.place_id = f.place_id
        AND b.is_active = 1
        AND b.feature_type = 'place_boundary'
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_features AS a
      WHERE a.place_id = f.place_id
        AND a.id <> f.id
        AND a.is_active = 1
        AND a.feature_type = 'camping_area'
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_camping_sites AS s
      WHERE s.camping_area_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_campsites AS s
      WHERE s.map_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_camping_site_classes AS c
      WHERE c.camping_area_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_feature_rates AS r
      WHERE r.feature_id = f.id
  )
ORDER BY p.name, f.id;

-- APPLY: backup IDs/types first; the audit table allows reversing the change.
CREATE TABLE IF NOT EXISTS migration_20261104_place_boundary_types (
    feature_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    place_id BIGINT UNSIGNED NOT NULL,
    old_feature_type VARCHAR(64) NOT NULL,
    saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO migration_20261104_place_boundary_types
    (feature_id, place_id, old_feature_type)
SELECT
    f.id, f.place_id, f.feature_type
FROM place_map_features AS f
INNER JOIN places AS p ON p.id = f.place_id
WHERE f.is_active = 1
  AND f.feature_type = 'camping_area'
  AND p.type IN (
      'developed-campground',
      'rv-park-resort',
      'dispersed-camping',
      'camping-area'
  )
  AND (
      LOWER(TRIM(f.label)) = LOWER(TRIM(p.name))
      OR LOWER(TRIM(f.label)) = LOWER(CONCAT(TRIM(p.name), ' boundary'))
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_features AS b
      WHERE b.place_id = f.place_id
        AND b.is_active = 1
        AND b.feature_type = 'place_boundary'
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_features AS a
      WHERE a.place_id = f.place_id
        AND a.id <> f.id
        AND a.is_active = 1
        AND a.feature_type = 'camping_area'
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_camping_sites AS s
      WHERE s.camping_area_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_campsites AS s
      WHERE s.map_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_camping_site_classes AS c
      WHERE c.camping_area_feature_id = f.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM place_map_feature_rates AS r
      WHERE r.feature_id = f.id
  );

-- Reclassify only backed-up features that are still active camping_areas.
UPDATE place_map_features AS f
INNER JOIN migration_20261104_place_boundary_types AS audit
    ON audit.feature_id = f.id
   AND audit.place_id = f.place_id
SET f.feature_type = 'place_boundary'
WHERE f.is_active = 1
  AND f.feature_type = 'camping_area';

-- Verify migrated records.
SELECT p.name, f.id, f.feature_type
FROM migration_20261104_place_boundary_types AS audit
INNER JOIN place_map_features AS f ON f.id = audit.feature_id
INNER JOIN places AS p ON p.id = f.place_id
ORDER BY p.name, f.id;

-- MANUAL MIGRATION: Run in phpMyAdmin after backing up place_map_features.
-- This widens stored geometries from Polygon to Geometry while preserving rows.
-- Check SHOW CREATE TABLE place_map_features first on the live host.
ALTER TABLE place_map_features
  MODIFY COLUMN geometry GEOMETRY NOT NULL,
  MODIFY COLUMN geometry_type VARCHAR(16) NOT NULL;
SELECT geometry_type, ST_GeometryType(geometry) AS actual_type, COUNT(*) AS rows_count
FROM place_map_features
GROUP BY geometry_type, ST_GeometryType(geometry);

ALTER TABLE resources
    ADD COLUMN marker_service_type VARCHAR(64) NULL AFTER resource_type;

UPDATE resources AS r
SET r.marker_service_type = (
    SELECT rs.service_type
    FROM resource_services AS rs
    WHERE rs.resource_id = r.id
      AND rs.is_active = 1
    ORDER BY
        rs.sort_order ASC,
        rs.id ASC
    LIMIT 1
)
WHERE r.marker_service_type IS NULL
   OR r.marker_service_type = '';

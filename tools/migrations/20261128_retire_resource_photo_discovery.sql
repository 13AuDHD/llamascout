-- Retire automated Resource photo discovery only.
-- Run after deploying the removal PR. Does not delete Scout uploads,
-- manually sourced images, Resource records, or address enrichment data.
-- The historical discovery migrations must already have been applied.
START TRANSACTION;

DELETE i FROM resource_images i
JOIN resource_kartaview_discovery k ON k.resource_id=i.resource_id
WHERE i.source_provider='KartaView' AND i.uploaded_by IS NULL
  AND k.status='attached'
  AND i.src=JSON_UNQUOTE(JSON_EXTRACT(k.photo,'$.src'));

DELETE i FROM resource_images i
JOIN resource_photo_discovery d ON d.resource_id=i.resource_id
WHERE i.source_provider='Wikimedia Commons' AND i.uploaded_by IS NULL
  AND d.status='attached'
  AND EXISTS (
    SELECT 1 FROM JSON_TABLE(d.candidates,'$[*]'
        COLUMNS (src VARCHAR(500) PATH '$.src')) AS candidate
    WHERE candidate.src=i.src
  );

COMMIT;

-- Only after the imported image rows above have been removed:
DROP TABLE IF EXISTS resource_kartaview_requests;
DROP TABLE IF EXISTS resource_kartaview_discovery;
DROP TABLE IF EXISTS resource_photo_discovery;

-- Apply once before accepting new Facility submissions.
-- Existing Resources remain standalone. No historical Area data is changed.
ALTER TABLE resources
    ADD COLUMN parent_place_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_resource_parent_place (parent_place_id, is_active);

-- If this migration was already applied, do not run it again.

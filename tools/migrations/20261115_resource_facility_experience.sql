-- Shared, moderated Experience answers for Resources and Place-owned Facilities.
-- Does not modify existing Resource, Facility or Area observations.
ALTER TABLE resources
    ADD COLUMN experience_json JSON NULL AFTER notes;

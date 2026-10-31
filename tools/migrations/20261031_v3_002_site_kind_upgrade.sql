-- Apply only to databases that ran the first V3 Site visibility
-- migration before site_kind was added to its table definition.
ALTER TABLE place_site_visibility
    ADD COLUMN site_kind
        ENUM('unreviewed', 'developed', 'dispersed')
        NOT NULL DEFAULT 'unreviewed'
        AFTER place_id;

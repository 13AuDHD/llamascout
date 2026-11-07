-- Restore the published Place projection for the approved Tornado Risk rating.
-- Existing answers and report history are preserved. NULL is no rating.
ALTER TABLE `place_rules`
    ADD COLUMN IF NOT EXISTS `tornado_risk` TINYINT UNSIGNED NULL DEFAULT NULL;

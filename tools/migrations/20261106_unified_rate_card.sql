-- Additive prerequisite only. Review before execution. Existing rows/history stay intact.
-- Run once before deploying the editor; safe when the column already exists.
SET @llama_pricing_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'place_rules'
             AND COLUMN_NAME = 'rate_cards_json'),
    'SELECT 1',
    'ALTER TABLE place_rules ADD COLUMN rate_cards_json LONGTEXT NULL'
);
PREPARE llama_pricing_statement FROM @llama_pricing_ddl;
EXECUTE llama_pricing_statement;
DEALLOCATE PREPARE llama_pricing_statement;
-- Rollback: revert application commit. Keep this nullable column and its values.

-- M3-002: Non-destructive migration of existing Area and Site current answers.
-- Run after M3-001. Re-running this script is safe.
-- Canonical rows always win: INSERT IGNORE skips already migrated/updated keys.
-- This copies current scoped answers, NOT immutable historical reports.
-- The canonical PHP reader still verifies registered question scopes.
INSERT IGNORE INTO place_report_current_answers
    (place_id, target_scope, target_source, target_id,
     question_key, answer_state, value_json)
SELECT
    legacy.place_id,
    legacy.scope_type,
    legacy.scope_source,
    legacy.scope_id,
    legacy.field_key,
    CASE
        WHEN JSON_UNQUOTE(legacy.value_json) = '__LLAMA_UNKNOWN__'
            THEN 'unknown'
        ELSE 'answered'
    END,
    CASE
        WHEN JSON_UNQUOTE(legacy.value_json) = '__LLAMA_UNKNOWN__'
            THEN JSON_EXTRACT('null', '$')
        ELSE legacy.value_json
    END
FROM place_report_scoped_answers AS legacy
INNER JOIN places AS p ON p.id = legacy.place_id
WHERE legacy.scope_type IN ('area', 'site')
  AND legacy.scope_id > 0
  AND legacy.field_key <> ''
  AND JSON_VALID(legacy.value_json)
  AND (
      JSON_UNQUOTE(legacy.value_json) IS NULL
      OR JSON_UNQUOTE(legacy.value_json) <> '__LLAMA_UNANSWERED__'
  );

-- Coverage report: new V3 rows alongside legacy scoped rows.
SELECT
    (SELECT COUNT(*) FROM place_report_scoped_answers
     WHERE scope_type IN ('area', 'site')) AS legacy_scoped_rows,
    (SELECT COUNT(*) FROM place_report_current_answers
     WHERE target_scope IN ('area', 'site')) AS canonical_scoped_rows,
    (SELECT COUNT(*) FROM place_report_current_answers
     WHERE target_scope IN ('area', 'site')
       AND answer_state = 'unknown') AS explicit_unknown_rows;

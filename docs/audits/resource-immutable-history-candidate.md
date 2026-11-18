# Resource and Resource-backed Facility immutable report contract (candidate v1)

This is a **design and test contract**, not an approved Scout Report migration or an active history writer. It is a continuation of issue #179 and the field-by-field audit in PR #190.

## Existing Service-only questions

The original Resource Experience-only question definitions have been moved **unchanged** from `app/resource-experience.php` into:

- `app/resource-report-service-questions.php`: `llama_resource_service_question_definitions()`, which is now consumed by the existing Add, Update, Admin, moderation and public Experience manifest.
- `llama_resource_service_question_candidates()`: the same keys, labels, types and options, explicitly marked `candidate_not_approved` for possible registration in the central Scout Report question registry.

This does not add any unapproved questions to `llama_place_report_fields()` or expand Place, Site or Area ownership. The separate Resource Experience catalog remains authoritative for those existing Resource forms until a field-by-field approval is made. Reused approved Area questions continue to be referenced by their existing keys, never redefined.

## Versioned dry-run history payload

`app/resource-report-history-candidate.php` provides:

- `llama_resource_history_candidate_schema()`: a deterministic, SHA-256 versioned snapshot of candidate Service definitions and **all** composite Experience occurrences, with `write_approved = false`.
- `llama_resource_history_candidate($resource, $reviewEvidence)`: a pure read-only preview of the proposed immutable report, containing verified Resource or Resource-backed Facility identity, separate `service/group/question` answer keys, precise answered/unknown states, original raw values, content SHA-256, and explicitly supplied moderation provenance.

For example, `toilet/men/facility_cleanliness`, `toilet/women/facility_cleanliness`, and `toilet/all_gender/facility_cleanliness` are three distinct answers. A `facility_cleanliness` answer in Laundry is a fourth. The same isolation applies to repeated `service_access_notes` in different Services.

The preview preserves explicit No, zero, multiselects and Unknown, and omits Unanswered. Historical Experience that no longer maps to the selected Services is reported separately in `unmapped` and blocks automatic publication. The preview does **not** update or insert any SQL rows and cannot be used as an approved `scout_report_schemas.schema_version`.

## Why the current immutable writer cannot be reused unchanged

The reviewed `tools/migrations/20261030_m2_006_scout_report_history.sql` schema contains these constraints:

| Constraint | Current behavior | Resource / Facility requirement |
|---|---|---|
| `scout_reports.place_id` | `NOT NULL` | A standalone Resource must work without a Place ID |
| `scout_reports.target_scope` | CHECK allows only `place`, `area`, `site` | Explicit Resource and Resource-backed Facility scopes |
| `llama_scout_report_record_approved()` | Rejects any target outside Place/Area/Site | New target verification for `resources.id`, distinct from mapped Facility Area |
| `llama_scout_report_normalize_answers()` | Validates bare approved Place/Area/Site question IDs | Service/group/question composite answer IDs and Resource-only candidate definitions |
| `llama_scout_report_schema_version()` | Hashes `llama_place_report_fields()` | A versioned snapshot of the exact Resource Experience contract used for this report |
| `place_report_current_answers` | Validates Place/Area/Site with a parent Place | An authorized Resource/Facility target-aware current answer reader/writer |
| `scout_report_answers` | One `question_key` per report | Collision-free Service/group/question identity, with declared underlying canonical question ID where applicable |

Do **not** fabricate a parent Place for standalone Resources or convert linked Facilities into mapped Areas to satisfy existing constraints.

## Write design to review before any migration

1. **Approve question ownership:** Review candidate Service-only keys without changing their existing labels, types or options implicitly. Decide whether they should be centrally registered as Resource/Facility-scoped questions or maintained as a versioned subordinate manifest.
2. **Choose an append-only schema:** Decide whether to extend the current tables with nullable parent Place and supported Resource/Facility scopes, or create a separately namespaced immutable Resource report ledger with the same immutability guarantees. Review keys, indexes, foreign keys, and existing trigger compatibility.
3. **Preserve answer identity:** Keep `service/group/question` composite instances distinct; the bare canonical question key alone is not enough for restrooms and repeated Service notes.
4. **Verify target and authorization:** Resource target must resolve to a real `resources.id`. The target kind is determined by its **saved** `parent_place_id`; contributor input cannot pick another existing Facility or switch to a mapped Area.
5. **Capture only real provenance:** Moderation supplies verified `resource_submissions.id`, `submitted_by`, `created_at` and `reviewed_by`. There is no verified Resource visit date in the current submission workflow, so leave it null. Do not backfill from `resources.created_at` or administrative timestamps.
6. **Publish atomically:** A future approved Resource submission must save its Resource row, selected Service rows, Experience JSON, photo evidence and **exactly one** immutable report in one moderation transaction with deterministic idempotency. Failed publication must roll back or clean up copied photos as existing moderation does.
7. **Preserve history and reads:** Legacy `resources.experience_json` stays authoritative until an explicitly reviewed current-answer migration. Existing Resource/Facility pages and Resource IDs must continue to work; no forced backfill or dual write is authorized by this PR.

## Verification

Run `php tools/tests/resource-report-history-candidate.php` to check unchanged Resource-only definitions, stable schema hashes, separate restroom groups, explicit No/zero/Unknown, isolated Resource/Facility target identities, and blocked writes for unsupported schemas or missing provenance.

**No SQL needs to be installed for this candidate contract.** The status remains `preview_only`, and the resulting history envelope is not submitted to the existing immutable writer.

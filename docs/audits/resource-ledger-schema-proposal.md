# Resource and linked-Facility immutable Scout Report ledger: schema proposal

Tracking issue: #179. This is the next proposed step after PR #191's versioned, preview-only Resource history contract.

## Important

**Review-only migration, not yet approved for production installation.**
The schema is implemented and tested on a disposable MariaDB 10.11 instance, but the existing Resource moderation process does **not** write to it. The owner approved the 26 existing Resource-only questions on 2026-11-18 without changes. Do not apply the migration in production until the publishing transaction and cutover are separately reviewed and tested.

The existing `scout_reports` table requires `place_id NOT NULL`, supports only Place/Area/Site scopes and cannot store multiple `service/group/question` occurrences under the same bare question ID. To protect historical Place/Site/Area reports, this proposal uses **separate sidecar tables**. It does not weaken or migrate the original immutable ledger.

## Schema in this PR

Migration: `tools/migrations/20261118_resource_scout_report_ledger.sql`

| New table | Purpose | Immutable? |
|---|---|---|
| `resource_scout_report_schemas` | Versioned Resource/Facility question schema JSON and SHA-256 key | Yes |
| `resource_scout_reports` | One approved submission's Resource or linked Facility target, moderation provenance, content digest and revision identity | Yes |
| `resource_scout_report_answers` | Independently records `service_type / group_key / question_key` instances; optional reference to a reused Area question | Yes |
| `resource_scout_report_events` | Moderation audit evidence | Yes |
| `resource_scout_report_photos` | Approved photo snapshot evidence | Yes |
| `resource_scout_current_answers` | Future mutable typed current answers, with source report and row version | No |

All five history tables reject UPDATE and DELETE with MariaDB triggers. Current-answer rows intentionally remain editable because they are a projection of approved immutable evidence. No triggers, tables or constraints on the old `scout_reports` tables are changed.

The proposed report table requires a real `resources.id`, real `resource_submissions.id`, a versioned schema, contributor and moderator IDs, explicit submission/review timestamps, and an idempotent submission ID. The parent Place snapshot is NULL for standalone Resources and positive only for Resource-backed Facilities. A mapped Facility is **not** a valid Resource report kind.

The unique `(report_id, service_type, group_key, question_key)` answer index keeps independent men's/women's/all-gender restroom responses distinct, even when they share `facility_cleanliness`. Each current-answer row is unique by `(resource_id, service_type, group_key, question_key)`. Answer states preserve explicit Yes/No and zero as normal answered JSON, Unknown as a distinct state, and future explicit Unanswered tombstones in the current projection. Unknown/Unanswered are never inferred from absence in the immutable record.

## Still required before activation

1. **Resolved:** The owner explicitly reaffirmed all 26 Resource-only questions for Resource and linked-Facility reporting on 2026-11-18. They remain unchanged and scoped to those targets. This does not activate the database or approve publication.
2. Implement a verified writer inside the existing `llama_resource_review()` transaction, *after* the target Resource and Service/Experience updates are validated. It must record one report and relevant photo evidence before committing, or roll back completely.
3. Validate the stored target `resources.parent_place_id` and submitted Service groups, not a client-chosen kind. Preserve historical target kind snapshots if a Resource is later relinked.
4. Establish revision chaining and compare-and-swap conflict detection for moderator-approved updates. The immutable ledger is append-only; mutable current answers must never overwrite history.
5. Continue serving existing Resource/Facility pages from `resources.experience_json` until the new current-answer path is verified and a deliberate cutover is approved.
6. Review Resource deletion policy once the ledger contains actual reports: foreign keys intentionally prevent deleting a Resource that has immutable historical evidence.

## Database testing

GitHub workflow `resource-report-ledger-integration.yml` runs `tools/tests/resource-report-ledger-mariadb.php` against a disposable database named exactly `llama_resource_ledger_test`. It uses real existing Resource and submission CREATE TABLE migrations and applies the proposed schema twice to test idempotency. It tests Resource/Facility parent-kind checks, report/submission uniqueness, target foreign keys, repeated restroom answers, historical Unknown, triggers, current-answer tombstones and cross-target isolation.

**No live database changes or writes are made by this PR.** The eventual production installation should use a database backup, explicit approval and a separate deployment step.

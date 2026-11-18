# Resource and linked-Facility current-answer synchronization

Issue #179. This milestone follows merged PRs #192 through #195 (the separate Resource ledger, approved existing question definitions, guarded writer, and conditional moderation hook).

## Scope

When, and only when, `LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1`, an approved Resource or Resource-backed Facility contribution now updates **two destinations atomically within the same existing moderation transaction**:

1. Append the immutable report, its Service/group/question answers, moderation event, and reviewed photo evidence.
2. Synchronize `resource_scout_current_answers` from the **actual persisted immutable report answers**.

The sync is implemented in `app/resource-report-current-answers.php`, called by `llama_resource_ledger_record_approved()` after inserting immutable evidence. Both writes succeed together or the transaction rolls back. This is not a second report and does not write to Place, Site, mapped Area/Facility or another Resource.

### Answer rules

| Approved evidence | Current answer behavior |
|---|---|
| Newly answered question | Insert with `row_version = 1`, target kind, source report and exact JSON value |
| Changed answer | Update only this Resource/Service/group/question row, increment `row_version`, refer to the new immutable report |
| Unchanged answer | Keep previous state, value, source report ID and row version, even when a newer report is approved |
| Explicit No or numeric zero | Remains `answered`, retains literal JSON value, never silently cleared |
| Explicit Unknown | State `unknown` with JSON `null` |
| Previously answered question now missing | Set state `unanswered` with JSON `null`, preserve the question's identity, set source to the clearing report and increment version |
| Previously cleared and still missing | Keep existing Unanswered tombstone and original clearing-report evidence |
| Resource changes into/out of linked-Facility identity | Use only the saved Resource's verified target kind/parent; update that Resource's projection, never map it to a legacy Area |

Rows are keyed by existing `(resource_id, service_type, group_key, question_key)`. A question answered separately in men's, women's and all-gender restrooms remains three distinct rows. No new SQL table or column is introduced.

Each sync verifies the immutable report target and the saved Resource's parent identity, locks only that Resource's current rows, and refuses to process a current row whose source report belongs to a different Resource. Changed-row updates use their existing `row_version` as a compare-and-swap guard.

## Release safety

**Still disabled by default.** Only the existing Resource history flag enables the writer and this derived sync together. It is not a second independent feature flag, so deployed code cannot silently produce current answers without the immutable report.

**Production SQL from PR #192 remains unverified as installed. Do not turn on the history flag yet.** This milestone requires no additional SQL and no backfill. The new current-answer table already exists in the PR #192 schema, but the live database has not been inspected.

**No public read cutover.** The Resource/linked-Facility public pages and contribution/update forms continue reading the existing `resources.experience_json` source. The current-answer table is a derived, tested projection of newly approved immutable records only. Legacy records are not overwritten or guessed from outdated data.

## Verification

The real disposable MariaDB 10.11 integration suite now covers unchanged/changed row versions and original source-report provenance; explicit Unknown, No and zero; clearing a question and removing a Service; tombstones; Resource vs linked-Facility isolation; and a deliberately corrupted cross-target source report that forces the immutable and current writes to roll back together.

The earlier real-moderation regression continues to test flag-off legacy approval, enabled Resource/Facility approval, photo evidence, and failed-approval rollback. There is no production database mutation in these tests.

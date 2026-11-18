# Resource/linked-Facility verified current-answer read bridge

Issue #179. Follows merged PR #196 (transactional current-answer synchronization).

## Purpose

Public Resource/Facility pages and the Admin Resource editor can *prepare* to read `resource_scout_current_answers` without removing compatibility for pre-ledger Resource records.

The new `app/resource-report-current-read.php` provides `llama_resource_report_read(PDO $db, array $resource)`. Both `resource.php` and `admin_resource_get()` use it. The returned projection has the same Service/group/question answer structure, names, choice labels and independent restroom groups used by the original Experience system.

## Two independent opt-in gates

Actual current-answer presentation requires **both** server settings:

- `LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1` (history and current-answer writes operating).
- `LLAMA_RESOURCE_CURRENT_READS_ENABLED=1` (separate read cutover permission).

Both default to OFF and **neither is set in this PR**. Merging the PR does not change public or Admin answers. The original `resources.experience_json` remains authoritative until the database deployment and staging review are approved.

## How the read bridge protects existing data

The bridge falls back to `resources.experience_json` when any of these is true:

- Either switch is off, or the new sidecar tables or immutable database triggers are absent.
- The Resource has no approved immutable history (normal for pre-migration records).
- The most recent historical target does not match the saved Resource ID and linked Facility parent identity.
- The Resource has been edited since its last approved report.
- A current row cites another Resource's immutable evidence, contains invalid state/JSON or belongs to an inactive or unrecognized Service.
- The new current-answer projection is incomplete or differs from the saved legacy Experience answers, including explicit No, zero and Unknown.
- Legacy JSON contains unmapped older observations that need reconciliation.

When all checks pass, the bridge reads the existing current-answer rows with preserved `source_report_id` and `row_version`, omits explicit Unanswered tombstones from visible observations, and renders through the **same canonical Resource Experience manifest and public cards** as before. This is a conservative equal-values cutover, not a historical backfill or data migration. Unchanged current answers may refer to an older approved report because their original source evidence is preserved.

For safety, the Admin editor only uses current Experience values after this same validation. The normal contributor update and moderation write paths still use the existing Resource storage model.

## Verification status

The **disposable MariaDB 10.11 test** checks new Resource and linked-Facility approvals, legacy-only Resources with no immutable report, both read switches, exact target, No, source metadata, old-record and missing-schema fallback, stale legacy changes, cross-target source corruption and missing current rows. It also asserts that public and Admin entry points share one guarded read contract.

This is database inspection logic, not confirmation of your production database. No production connection was available to this PR. **PR #192's SQL installation remains unverified.**

## SQL

**No new SQL is required for this PR.** It uses only the tables and triggers already defined by PR #192. Do not run that earlier migration or enable these switches merely because this PR passes CI or merges. Production backup, manual schema verification, and staging approval tests are the next deployment gate.

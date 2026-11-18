# Resource/linked-Facility moderation history hook

Issue #179, continuation of merged PRs #192 (isolated Resource/Facility ledger), #193 (approval of 26 established Resource-only Experience questions) and #194 (transaction-only, fail-closed immutable writer).

## Integration

`llama_resource_review()` now invokes `llama_resource_ledger_record_approved()` **only on approval and only when `llama_resource_ledger_writer_enabled()` is true**, after its existing Resource/Service/Experience save, existing published gallery reordering, and reviewed submission-photo publisher. The history call remains **inside the original moderator-owned database transaction** and runs before the submission is marked approved and before commit.

The existing photo publisher returns the actual newly copied Resource gallery paths. The hook passes these paths in order with the reviewed photo list to the history writer; no contributor-provided permanent paths are trusted. On failure the existing moderator catch block rolls back the Resource record, Services, gallery rows and immutable history, and deletes only newly copied gallery files. Contributor submission images remain untouched so moderation can be retried.

When no photos are approved, the history writer receives empty arrays. Existing published photo reordering does not imply new evidence or manufacture new photo snapshots.

## Release safety

**DO NOT enable in production yet.** Merging this PR does not activate the hook. It remains disabled unless the server process has `LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1`. No such setting is added by this PR. PR #192 SQL installation has not been verified in production.

If the flag is enabled without the ledger installed, the writer errors inside the transaction and the **entire approval is rolled back**. This is intentional: we cannot publish a Resource without the promised immutable evidence in enabled mode. Leave the flag unset until database inspection, backup and staging verification are complete.

No SQL or database migration is included in this PR. The six sidecar tables from PR #192 are the unchanged prerequisites. The legacy `resources.experience_json` remains authoritative for public display and Add/Update.

## Regression tests

`tools/tests/resource-report-ledger-mariadb.php` exercises the **real** `llama_resource_review()` function, not a fake approval sequence. The disposable MariaDB test verifies:

- Feature flag absent: legacy approval succeeds without writing history
- Feature flag set: real Resource approval writes exactly one immutable report with the recorded answers
- Linked Facility approval saves a separate target with the existing parent Place
- Approved photo publisher supplies actual gallery paths to a correctly hashed immutable photo snapshot
- A conflicting immutable submission causes moderator approval to roll back its new Resource and leave the submission pending
- Original `scout_reports` history and Resource current-answer projection remain unchanged

The previous direct-writer tests still verify missing transaction, rollback, deterministic repeat submission, revision chaining, target mismatch and expired Service rejection.

## Next step

After deployment prerequisites are confirmed, perform staging approval/revision tests, decide when to enable the server-side flag, and separately implement the versioned `resource_scout_current_answers` projection. **Do not treat a merged PR or passing disposable tests as proof production SQL has been installed.**

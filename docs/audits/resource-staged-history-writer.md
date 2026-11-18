# Resource and linked-Facility immutable history writer (staged)

Issue #179. This stage follows the owner approval of the original 26 existing Resource-only Experience questions in PR #193.

## What this stage implements

`app/resource-report-history-writer.php` provides `llama_resource_ledger_record_approved()`, a **transaction-only, fail-closed history writer** for the six sidecar tables introduced by PR #192.

It re-reads the exact saved `resources.id`, `resource_services`, and `resources.experience_json` instead of accepting client-submitted answer or target identities. It locks the Resource and pending submission, verifies the contributor and selected update target, requires a real parent Place for a linked Facility, and rejects unknown/inactive Service answers. It does not convert a linked Facility into a mapped Area.

The writer inserts a versioned Resource schema snapshot, one immutable report with deterministic submission idempotency, ordered Service/group/question answer instances, a moderation event with Resource/Service fact snapshots, and SHA-256 evidence for reviewed permanent photos. Subsequent approved submissions to the same Resource form a revision chain without rewriting earlier answers.

The Resource submission's actual `submitted_by` and `created_at` supply contributor and submission time. Moderator identity comes from the authenticated review call. `reviewed_at` comes from the database clock. A Resource visit date and role at submission are **not** inferred; historical role is marked `not_recorded` and visit date remains NULL.

No change is made to the old Place/Site/Area ledger or to the new `resource_scout_current_answers` table.

## Why this is not yet live

Two independent gates remain:

1. **No production call path.** `llama_resource_review()` does not invoke this writer in this PR. The current Resource moderation and public pages continue as before.
2. **Explicit runtime opt-in.** The writer refuses to run unless `LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1` *and* a moderation-owned DB transaction is active. The default is disabled. The disposable MariaDB CI test sets the opt-in only in its own PHP process.

The Resource ledger SQL from PR #192 is still not verified installed in production. **Do not install or enable it based only on a merged PR.** There is **no new SQL migration in this PR**.

## Verification

`.github/workflows/resource-report-ledger-integration.yml` exercises the writer against a disposable MariaDB 10.11 database, checking:

- Disabled-by-default and transaction-only refusal
- An approved new standalone Resource and a linked Facility with real parent Place
- Review target mismatches, provenance and no fabricated visit date
- Repeat submission idempotency
- Immutable Service/group answers and raw No/zero/multiselect handling
- Approved photo file hashing and immutable evidence
- Full rollback of report writes inside a failed moderation transaction
- New revisions linked to their prior immutable report
- No writes to existing Place/Site/Area report tables or new Resource current-answer projection

## Still required before activation

- Verify PR #192's schema has been manually installed and backed up, or stage a separate reviewed deployment.
- Wire exactly one invocation after the existing Resource/Facility save and photo publisher inside `llama_resource_review()`, without creating a second transaction.
- Confirm the schema/current answer and identity rules in a real staging Resource/Facility approval and correction.
- Prove that approval failure rolls back Resource record, Service facts, photo DB rows and immutable ledger together, cleaning only newly copied files.
- Make a separate deliberate decision for current-answer cutover. `resources.experience_json` remains authoritative and no history backfill is attempted.

# Resource/linked-Facility production ledger readiness

Issue #179. The site owner confirmed on 2026-11-18 that the full PR #192 SQL migration was manually executed. **That establishes owner-reported installation, not independent verification of the live server schema.**

## How to verify without running SQL

After this PR is merged and deployed, sign into Llama Scout Admin, open **Resources**, and select **Check Resource / Facility report-history readiness**. The protected page is `/resource-ledger-readiness.php` on the Admin host.

It checks the **database connected to the running website**. No external connection, database password or phpMyAdmin access is needed for the check.

The read-only `app/resource-ledger-readiness.php` inspects:

- All six expected Resource/Facility sidecar tables and their named required columns.
- All ten immutable `BEFORE UPDATE` and `BEFORE DELETE` triggers, including their actual target tables and events.
- Eight foreign keys connecting reports, submissions, Resource records, schemas, answer evidence and current answers.
- Six target/answer-state/version CHECK constraints and six unique report/answer/photo/current-answer indexes.
- Counts of saved standalone Resources, linked Facilities, immutable reports, current-answer rows, and **older approved submissions with no ledger history**.
- Current answer rows citing the wrong Resource and current target kinds inconsistent with the saved Resource/Facility relationship.
- Current server-side history-write and read switches (display only).

The inspector does not write any rows, run migrations, touch history, enable either flag, or expose DB credentials in the Admin UI. Missing or inaccessible tables and triggers result in an explicit warning, not an assumption that the PR merge installed SQL.

## How to interpret it

- **Schema verified, staging approval still required:** Expected columns, constraints, triggers and relationships exist, and the checked cross-target anomalies are zero. This does **not** prove full data parity, backups, successful end-to-end approval or authorization to enable the feature.
- **Missing or changed database structure:** One or more migration prerequisites were not found or are incompatible. Leave both flags OFF.
- **Existing history/current answers require review:** The database structure exists, but the inspector found cross-target evidence or Resource/Facility identity inconsistencies. Leave both flags OFF and investigate; never automatically repair history.
- **Unable to inspect database safely:** Current Admin DB credentials cannot inspect the relevant structures; no proof of installation.
- **Approved submissions without history:** Normal for Resource/Facility approvals completed before this immutable writer was enabled. These must **not** be automatically backfilled from the current mutable Resource record.

## Release gates that remain

1. Confirm a recent, restorable database backup outside this tool.
2. View the new Admin readiness page and confirm the schema and integrity checks pass on the live server.
3. Execute a controlled staging **Resource** and **Resource-backed Facility** Add, Update, moderation, and photo/rollback regression with the same deployed code and schema.
4. Only then explicitly decide on enabling `LLAMA_RESOURCE_HISTORY_WRITES_ENABLED=1`. It is disabled by default and this PR does not enable it.
5. Keep `LLAMA_RESOURCE_CURRENT_READS_ENABLED` disabled until after approved-history parity has been verified. A separate read gate cannot be assumed safe simply because a history table exists.
6. Complete the full Place, Site, mapped Facility and Resource/linked-Facility cross-target regression before closing Issue #179.

## SQL

**No additional SQL in this PR.** The production migration was owner-reported installed, so do not rerun it. The new Admin page performs all its checks read-only.

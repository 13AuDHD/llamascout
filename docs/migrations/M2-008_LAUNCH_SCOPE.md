# M2-008: Legacy history is not a V3 launch blocker

Decision (2026-10-30): Do not backfill historical Add Place / Update Place submissions as a prerequisite for the V3 launch. The owner will manually review the existing 16 Places after the new system is usable.

## Keep
- Preserve all legacy tables, submissions, photos, and existing Places without deletion or destructive rewriting.
- Keep the M2-006 append-only tables and new V3 report-history implementation. New approvals must create trustworthy immutable history.
- Validate new report creation, Place/Site scope, approval/rejection, current answer projection, rollback, visibility, and read paths.
- Continue with NPS, USFS, BLM, and RIDB ingestion once V3 reporting passes launch-critical tests.

## Out of launch scope
- Backfilling old approved submissions into `scout_reports`.
- Matching post-submission Site rows to historical reports.
- Reconciling old photo submission references.
- Mapping every legacy question as a blocker.

## Code style
Prefer camelCase in new JS/application-facing identifiers where practical. Keep the established canonical question keys and MariaDB column names until a separately justified compatibility change.

## Merge gate
Do not use legacy backfill completion as a gate. Require verified tests of new Place and Site submissions, moderation and immutable write behavior, published reads, permissions and dispersed-Site restrictions, and documented rollback.

Historical M2-008 audit scripts and inventories are retained for reference only.

# M2-006 Approved Update Report History Migration

This migration creates the append-only Scout Report tables required before the
M2-006 application code is deployed.

It does not migrate legacy submissions, delete current data, or replace the
existing Place, Area, Site, moderation, contribution, or photo tables.

This first migration deliberately does not add cascading foreign keys to live
Place or user tables. Historical reports must survive account or Place archival,
and the repository does not contain authoritative DDL for the parent ID column
types. Application approval validates those identities before insertion. Any
later foreign-key migration requires a live-schema and retention review.

## Migration file

`tools/migrations/20261030_m2_006_scout_report_history.sql`

## Before applying

1. Confirm the target is the intended Llama Scout database.
2. Take a database backup using the hosting provider's normal backup process.
3. Check whether any of these tables already exist:
   - `scout_report_schemas`
   - `scout_reports`
   - `scout_report_answers`
   - `scout_report_moderation_events`
   - `scout_report_photos`
4. If any table already exists, compare its columns, unique indexes, engine, and
   immutable triggers with `app/place-report/diagnostics.php` before applying.
5. Do not deploy the M2-006 PHP changes until the migration and diagnostics pass.

## Apply and verify

1. Apply the SQL migration through the normal reviewed database deployment path.
2. Run the existing Place Report schema diagnostic.
3. Confirm all five tables use InnoDB.
4. Confirm the required unique indexes exist.
5. Confirm each table has both UPDATE and DELETE append-only triggers.
6. Confirm the trigger message contains `Scout Report history is append-only`.
7. Approve one staging Entire Place report and one staging Site report.
8. Confirm each approval creates one immutable report, its exact submitted
   answers, one approval event, and ordered photo snapshots where applicable.
9. Confirm an approval failure rolls back both the current projection and all
   immutable rows.

## Compatibility

Existing public and administrative reads continue using the current published
Place and scoped-answer tables. M2-006 writes those tables only after the
immutable report snapshot succeeds in the same approval transaction.

The immutable rows are therefore the approved evidence. The existing tables
remain the compatibility projection used by current interfaces.

## Recovery

If the application deployment must be reverted, revert the M2-006 PHP commit but
leave the append-only tables and any successfully approved report rows intact.
They are historical evidence and must not be deleted as part of code rollback.

If the migration fails before application deployment, restore from the verified
backup only when the database administrator determines the failed DDL left an
unsafe partial state. Otherwise, correct the migration issue and rerun the
read-only diagnostics. Do not drop populated Scout Report tables to retry.

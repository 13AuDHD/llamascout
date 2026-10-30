# Llama Scout V3 Progress

This file records completed and reviewable V3 implementation-plan tasks. Work proceeds one task at a time from `docs/IMPLEMENTATION_PLAN.md`.

## V3-001: Repository and Infrastructure Inventory

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `463462c9e64143908d0e77895393b2372a12e7aa`

**Pull request:** [#8 — V3-001: Repository and Infrastructure Inventory](https://github.com/13AuDHD/llamascout/pull/8)

**Merged into `v3-development`:** `059bc8df49d0cafde21fcfcbe3a71eb1b61813f4`

### Result

Created `docs/audits/01-repository-inventory.md`, a read-only inventory of the V2 systems that V3 must preserve and reuse. It documents:

- Public, account, admin, API, bootstrap, and shared PHP structure.
- Primary, reference, RIDB, and cell database connection architecture.
- Authentication, memberships, role hierarchy, Scout, Master Scout, Owner, and Admin functions.
- Administration, moderation, canonical Scout Report definitions, and reporting interfaces.
- Maps, spatial features, campsite/site identity, overlays, and proposal tools.
- CSS organization, local Manrope fonts, SVG icons, logos, and partner branding.
- Photo staging/processing, published media, profiles, campsites, and shop images.
- RIDB, NPS, USFS, PAD-US, FCC cell coverage, and reconciliation tooling.
- Shop catalog, cart, checkout, Stripe events, fulfillment, shipping, refunds, and returns.
- Error logging, security controls, maintenance jobs, documentation, tests, and deployment configuration.

### Verification

- Fetched `origin/v3-development` immediately before creating the task branch and confirmed local and remote both resolved to `463462c9e64143908d0e77895393b2372a12e7aa`.
- Confirmed important inventory paths with repository searches and targeted reads; no path was inferred from a planned architecture alone.
- Kept specifications separate from implementation evidence. Unverified runtime, production-data, provider, host, and detailed schema questions are marked **Needs investigation**.
- Did not access production databases, private configuration values, provider accounts, or the deployed application.
- Did not run migrations or modify application behavior.
- Verified the intended documentation-only scope before delivery.

### Limitations

This task establishes system boundaries and representative files, not detailed correctness. It does not certify live schemas, provider completeness, role edge cases, hosting configuration, runtime image support, or end-to-end workflows. No general automated test framework was found in the reviewed branch; only targeted administrative diagnostics were identified.

### Next planned task

**V3-002: Reporting Definition Audit.** See the task record below.

## V3-002: Reporting Definition Audit

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `b35be100e45590ef5167ef1b2eee12dba29dd7a4`

**Pull request:** [#9 — V3-002: Reporting Definition Audit](https://github.com/13AuDHD/llamascout/pull/9)

**Merged into `v3-development`:** `ef7896ef0643259ad51a6207c6f0de77014a115f`

### Result

Created `docs/audits/02-reporting-definitions.md`, a static audit of the existing Scout Report definition system. The audit covers:

- Canonical registry assembly and field-family files.
- Stable keys, labels, types, choices, validation, help, and display metadata.
- Approved groups, form layout groups, flow groups, and final ordering.
- Place, Area, and Site scope, inheritance, targets, and imported Site baselines.
- Generic answer states and question-specific state-like values.
- Applicability profiles, dependency operators, and frontend rule mirrors.
- Published Place mappings, scoped answers, compatibility mirrors, and specialized writers.
- Intentional adapters, legacy compatibility, suspected duplication, suspected overrides, and unresolved modules.

### Confirmed findings

- `llama_place_report_fields()` is the canonical final contract, assembled through registration and ordered mutation passes rather than defined in one static array.
- Approved-question membership/order, active form layout, legacy sections, and semantic scope are separate registries with different responsibilities.
- Generic answer states are `answered`, `unanswered`, and `unknown`; explicit No is an answered value.
- Scope supports Place, Area, Site, Place/Area inheritance, and nearest-recorded Place/Area/Site inheritance. Resource scope is not implemented in the reviewed target services.
- Published Place persistence is derived from canonical storage paths, while Area/Site overrides use canonical field keys in `place_report_scoped_answers`.

### Important risks

- The registry does not expose a general Public/Community classification or general field status/version metadata required by the specification.
- Site applicability logic exists in an unwired module while later metadata passes also replace applicability.
- Supplemental Admin save bridges repeat field lists/validation and may bypass canonical Unknown-state updates.
- JavaScript repeats taxonomy lists, hazard keys, and applicability evaluation that can drift from PHP.
- Legacy, approved, additional, derived, hidden, and compatibility-only fields coexist and require consistent filtering.

### Verification

- Inspected the required definition, applicability, condition, permission, metadata, scope, persistence, target, and scoped-answer services.
- Searched report-related PHP and JavaScript for independent field lists, labels, options, conditional logic, ordering, visibility, legacy aliases, and supplemental persistence.
- Labeled possible duplication and overrides as suspected unless the separate behavior was directly confirmed.
- Did not access production data, run migrations, or modify application files.
- Verified the documentation-only file scope before delivery.

### Limitations

PHP CLI is not installed in the Codex environment, so the repository's read-only runtime contract diagnostic could not be executed. The audit is based on static repository evidence. It does not certify live schemas, current route reachability, deployed interface behavior, or question-by-question agreement with the approved Scout Report.

### Next planned task

**V3-003: Reporting Interface Audit.** See the task record below.

## V3-003: Reporting Interface Audit

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Base commit:** `99d4752a0e2903d53db827a8ee31076ccdbfbaed`

**Pull request:** [#10 — V3-003: Reporting Interface Audit](https://github.com/13AuDHD/llamascout/pull/10)

**Merged into `v3-development`:** `b068871dcbf5f38f89e72ada657841af47b002e9`

### Result

Created `docs/audits/03-reporting-interfaces.md`, a static end-to-end audit of the Scout Report interfaces. It covers:

- Add Place submission, moderation, Place publication, and first-Site creation.
- Existing Area/Site target selection, scoped reporting, inheritance, and imported Site baselines.
- Entire-Place and scoped Update This Place parsing, moderation, concurrency checks, and partial updates.
- Admin Place, Area, Site, bulk Site, photo, and supplemental save paths.
- Admin and Master Scout moderation plus problem-report handling.
- Published report access, approved grouping, unanswered fields, Site selection, imported values, and attribution.
- Historical snapshot reconstruction, comparison, and public timeline relationships.

### Confirmed findings

- Major editable interfaces consume the finalized canonical registry, but target selection, scoped payloads, read-only display, history, and supplemental Admin persistence remain separate adapters.
- Omitted keys in moderated Place and scoped updates leave unrelated published answers unchanged. Approval checks the submission-time value and answer state before writing.
- Active Admin operating and shared-amenity bridges run after the canonical save, write duplicate columns directly, collapse Unknown and Unanswered to SQL null, and do not update canonical published answer state.
- Public reconciliation rendering uses approved groups and `hide_public`, keeps unanswered questions visible, and currently bypasses canonical applicability filtering.
- Contributor reporting can update an existing Area or Site but cannot create another Site for an existing Place; later Site creation is an Admin/import/identity workflow.
- Historical report reconstruction applies Place-level storage paths only; scoped Area/Site updates can appear as versions without reconstructing their scoped answers.

### Important risks

- One Admin Save action can succeed canonically and then fail in a separate supplemental request.
- Published target selection does not expose Area or map-feature-only Site reports through the reviewed public Scout Report path.
- Public Site cards flatten inherited, imported, and direct values without per-answer attribution or structured answer state.
- Historical reports use current definition metadata and a separate curated comparison registry, creating future drift risk.
- Legacy campsite editing and an unreferenced rules supplemental bridge remain parallel behavior surfaces.

### Verification

- Inspected Add Place, Update This Place, Admin Place, Admin Area/Site, moderation, Master Scout moderation, public rendering, Site API/browser, history, and comparison entry points and services.
- Traced target identity and answer-state handling through frontend payloads, backend verification, moderation, publication, scoped storage, and read-only display.
- Confirmed important cited paths exist and labeled runtime/data-dependent conclusions as suspected rather than confirmed.
- Did not access production data, run migrations, modify application files, or begin implementation work.
- Verified the documentation-only file scope before delivery.

### Limitations

The audit is based on static repository evidence. It does not certify deployed route reachability, browser execution order, live schema state, historical data quality, or the frequency of identified risks. The complete approved-question reconciliation remains separate work.

### Next planned task

**V3-004: Database and Historical Records Audit.** See the task record below.

## V3-004: Database and Historical Records Audit

**Status:** Ready for review

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `b068871dcbf5f38f89e72ada657841af47b002e9`

### Result

Created `docs/audits/04-data-and-history.md`, a static audit of Scout Report storage and historical records. It covers:

- Published Place tables and canonical storage mappings.
- Area/Site target identity, scoped overrides, inheritance, clear behavior, and imported Site baselines.
- New Place and update submissions, moderation records, contribution provenance, and public history.
- Answer-state persistence, including confirmed Admin supplemental-save inconsistency surfaces.
- Scoped-history reconstruction limits and Site identity normalization risks.
- Imported provenance, Place/Site/problem evidence relationships, scoped-photo retention, schema diagnostics, and known integrity behavior.

### Confirmed findings

- Published Place state, mutable scoped overrides, imported baselines, submission snapshots, contribution provenance, public history, and comparison versions are separate data systems.
- Place Unknown state uses `places.place_report_answer_state`; scoped Unknown is an explicit token, while absence of a scoped row means Unanswered and deletion resumes inheritance.
- Scoped update JSON preserves its target and changed answers, but the current scoped table has no immutable revision link and Place comparison does not reconstruct Area/Site snapshots.
- Active Admin operating/shared-amenity supplemental saves can null fact columns without updating canonical answer-state metadata.
- Approved scoped updates do not promote evidence photos before their source update folder is removed.
- The repository has no checked-in Scout Report DDL. The `scout_report_*` append-only model appears only as a diagnostic contract, with no active repository persistence path found.

### Important risks

- Site identity normalization can leave pending/historical scoped payloads referring to an earlier map-feature identity.
- Imported baseline refreshes are not historical snapshots, so prior effective Site state cannot be replayed completely.
- Current historical views use current registry metadata and Place-level deltas rather than immutable, versioned report answers.
- Most live primary keys, foreign keys, unique constraints, indexes, and cascade rules cannot be certified from repository evidence alone.

### Verification

- Inspected canonical Place persistence, scoped answers, targets, Site identity, imported baselines, submission/update history, moderation, contributions, comparison, public timeline, media, import, and diagnostics paths.
- Kept repository-confirmed behavior separate from application assumptions, diagnostic requirements, and data-dependent risks.
- Did not connect to a database, run schema diagnostics, execute schema creation, or modify application/schema files.
- Verified cited paths and the documentation-only change scope before delivery.

### Limitations

This static audit does not certify live schema, stored data quality, deployment migrations, trigger/index presence, source-database contents, or the frequency of identified risks. No production or external database was accessed.

### Next planned task

**V3-005: Permissions and Moderation Audit.** It has not been started.

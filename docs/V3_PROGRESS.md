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

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `b068871dcbf5f38f89e72ada657841af47b002e9`

**Merged into `v3-development`:** `d616f2d33067ec01e9d95e17771bd8b12263508b`

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

**V3-005: Permissions and Moderation Audit.** See the task record below.

## V3-005: Permissions and Moderation Audit

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `45c3789957111a7db0ac108316519a3fd406f1a2`

### Result

Created `docs/audits/05-permissions-and-moderation.md`, a static audit of authentication, roles, Complete Access, earned Place access, contributor capabilities, Scout/Master Scout authority, Admin/Owner authority, self-review protections, and publication workflows.

### Confirmed findings

- Owner inherits Admin; Master Scout inherits Scout; legacy Master Scout slug compatibility remains active.
- Paid membership is intentionally narrower than Complete Access. Owner/Admin, active Scouts, valid memberships, and complimentary grants can receive Complete Access through separate paths.
- Place-specific earned access currently recognizes original/new-Place contributors only.
- The approved V3 rule that the first legitimate new Site contributor inside an existing Place earns Place access is not currently implemented.
- Member updates require both contribution eligibility and Complete Access to the selected Place.
- Master Scout moderation requires an active Master Scout profile and explicitly blocks self-review.
- Master Scout approval reuses the shared publication engine but publishes new Places as Active rather than Featured.
- Admin moderation uses Admin-role authorization, CSRF checks, transactions, locking, and audit logging; no equivalent contributor-ID self-review prohibition was found in the reviewed Admin path.
- The current canonical report registry does not yet provide the V3 Public/Community field-level authorization classification.
- Resource permissions are not represented in the current Place/Area/Site reporting target model.

### Important risks

- Contribution role labeling and active Scout entitlement are separate concepts, so expired Scout status may require explicit role-policy review.
- A future caller that checks only generic `submit_update` capability could omit the required Place Complete Access gate.
- Owner-only boundaries could be lost if future code treats Owner simply as Admin everywhere.
- Earned access for new Sites needs durable criteria to avoid rewarding duplicate or trivial submissions.
- Public/Community enforcement must be shared by backend services so website, API, and mobile clients cannot diverge.

### Verification

- Inspected authentication role inheritance, membership/Complete Access, contribution capability, Place-specific access, Add Place, updates, Admin moderation, Master Scout moderation, and membership grant code.
- Distinguished contribution capabilities from content-access permissions and moderation authority.
- Did not access production data, change role assignments, alter authorization behavior, or modify application files.

### Next planned task

**V3-006: External Data Import Audit.** See the task record below.

## V3-006: External Data Import Audit

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `539d8212248cc57347609b10a2fcb56ff1ead190`

### Result

Created `docs/audits/06-external-data.md`, a static audit of RIDB, NPS, USFS, BLM, PAD-US, FCC, source databases, normalization, matching, conflict handling, sync tracking, and photo import behavior.

### Confirmed findings

- RIDB is the most complete production integration, with a dedicated source database, mirror, normalization, Place import, individual Site import, Site facts/features, media, and sync logging.
- RIDB repeated imports match by RIDB external ID, but there is no generalized cross-provider duplicate detector before creating a Place.
- NPS has a dedicated source database, raw source hashes, resumable import tracking, and human-confirmed RIDB matching before canonical fill.
- NPS fills only empty canonical fields and preserves conflicting non-empty values for review.
- USFS currently functions as explicit reviewed campground enrichment rather than a full nationwide source mirror/Site importer.
- BLM is actively used for land-management map overlays, but no canonical BLM recreation/primitive-Site ingestion pipeline was found.
- PAD-US has working reference-source synchronization, issue tracking, classification, and managed-land lookup.
- FCC cell coverage has a mature separate ingestion pipeline with planning, downloads, retries, queues, progress, and dataset tracking.
- Source identity, conflict policy, and synchronization are currently provider-specific rather than governed by one shared source-registry/source-authority service.
- RIDB Site media preserves provider IDs, URLs, credits, and source timestamps; NPS Place image provenance is less complete.

### Important risks

- RIDB production refresh can overwrite selected canonical Place fields while NPS and USFS use more conservative policies.
- A manually created or differently sourced campground can duplicate an RIDB Place if the RIDB source link is absent.
- Upstream disappearance behavior differs by provider and must not become automatic canonical deletion.
- Imported provider facts and community observations need a shared field-level source authority model before more providers are automated.
- BLM/USFS expansion must preserve the distinction between land ownership/reference geography and verified legal camping.

### Verification

- Inspected source database connections, RIDB API/mirror/normalization/import/canonical/media paths, NPS raw/import/matching/fill paths, USFS enrichment, PAD-US sync/review, FCC sync workers, BLM land overlays, and official-source reconciliation.
- Distinguished working, incomplete, and missing components.
- Did not access source credentials, production databases, provider accounts, or alter importer behavior.

### Next planned task

**V3-007: Approved Scout Report Reconciliation.** See the task record below.

## V3-007: Approved Scout Report Reconciliation

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `fa6a3b8eba5c514cf34567e32a2da9117963fe4c`

### Result

Created `docs/audits/07-scout-report-reconciliation.md`, a complete trace of the approved Scout Report PDF against the current canonical reporting registry.

### Confirmed findings

- The approved PDF contains 266 visible questionnaire entries when imported official information and questionnaire-only moderator notes are included.
- The current approved-question registry contains 263 slots, and the count difference can be fully explained by identified structural mismatches.
- Official Address, Phone Number, Email Address, and Official Links are currently outside the approved canonical question registry.
- Seasonal Access represents two approved date questions as four separate month/day questions.
- Site Overhead Clearance and Parking Clearance are two approved questions but currently share `overhead_clearance_feet`.
- `flat_walking_surface` is currently marked approved but is not in the approved PDF.
- Notes for Moderator correctly exists as workflow metadata rather than a public approved card.
- Toilet type, Camp Host, permission-style rules, Seasonal Access dates, parking distance, and several legacy answer vocabularies differ from the approved answer specification.
- Most approved questions already map cleanly to stable canonical field keys.
- Existing historical/legacy values must be preserved while approved metadata is corrected.

### Milestone 0 conclusion

The existing backend, moderation, storage, scope, import, access, and shared-form infrastructure is reusable. The primary reporting foundation needs targeted reconciliation rather than a ground-up backend replacement.

The recommended first Milestone 1 implementation is a bounded canonical-definition task that makes approved question identity, labels, groups, order, scope, answer types, options, current keys, and compatibility status explicit and machine-verifiable without simultaneously rewriting persistence.

### Verification

- Compared all eight pages of the approved Scout Report against `llama_place_report_approved_question_groups()` and relevant final field metadata.
- Traced every approved question to an implementation key, a documented non-registry presentation path, workflow metadata, or an explicit missing/distinct-key gap.
- Reviewed known dependencies, hidden/compatibility behavior, answer-option differences, and historical preservation requirements.
- Did not modify application code, database schema, production data, or the approved specification.

### Next

**Milestone 0 Completion Gate.** Review audit findings and approve a small initial Milestone 1 implementation task before changing application behavior.


## M1-001: Extract Approved Question Registry

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `8a194c45c2d5f9f0daf6f83a387d8e649f2f6740`

### Objective

Begin Milestone 1 without changing persistence or rendered questionnaire behavior.

### Changes

- Added `app/place-report/approved-questions.php` as the dedicated owner of the current approved Scout Report group/order inventory.
- Removed the duplicate inventory definition from `app/place-report/flow-layout.php`.
- Updated `flow-layout.php` to consume the dedicated approved-question module.
- Added machine-readable reconciliation metadata for the known differences identified in V3-007.
- Added static registry diagnostics and a summary function.

### Behavior preservation

The active approved projection remains:

- 24 groups
- 263 current approved question slots

No question keys, ordering, persistence paths, scope ownership, applicability, or rendered field behavior were intentionally changed.

### Known reconciliation metadata now represented

- official Basic Information outside the current registry
- Seasonal Access dates split across month/day keys
- Site Overhead Clearance and Parking Clearance sharing one current key
- `flat_walking_surface` as an extra current approved slot
- questionnaire-only moderator notes
- toilet answer-option mismatch
- Camp Host answer-option mismatch
- parking-to-Site distance type mismatch
- legacy permission-answer vocabulary differences

### Verification

- Confirmed `flow-layout.php` requires the new registry module.
- Confirmed `flow-layout.php` no longer defines the approved-question inventory itself.
- Confirmed the extracted inventory still contains 24 groups and 263 question slots.
- Confirmed no database schema, persistence mapping, or production data changed.

### Next

After review and merge, the next bounded Milestone 1 task should begin correcting the approved-definition mismatches one category at a time, starting with identity-only issues that do not require destructive storage changes.


## M1-002: Canonical Two-Page Report Surfaces

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `ccd4dfa1fb870e61b4d9c364ec9059459a0a16dd`

### Objective

Lock the approved two-page Scout Report design into canonical metadata before deeper definition reconciliation.

### Design rule

The full Scout Report remains split into two comprehensible surfaces:

- Place details
- Site details

The report must not be flattened into one long Place-plus-Site page.

Place uses the established green Place accent role.

Site uses the established orange Site accent role.

The registry stores semantic accent roles only. CSS owns actual color values.

### Changes

- Added `llama_place_report_approved_pages()` to define the canonical Place and Site report surfaces.
- Added `llama_place_report_approved_group_page_lookup()` for shared group-to-page lookup.
- Assigned the nine approved Place groups to the Place page.
- Assigned the fifteen approved Site groups to the Site page.
- Added integrity checks for missing, duplicate, or unknown page/group assignments.
- Added final field metadata:
  - `approved_report_page`
  - `approved_report_page_label`
  - `approved_accent_role`
- Added the two-page presentation rule to `docs/SCOUT_REPORT_SPEC.md`.

### Behavior preservation

This task does not change persistence, question ownership, answer values, applicability, database schema, or current rendered page behavior.

It establishes metadata that later interfaces can consume while preserving one canonical data foundation.

### Verification

- Confirmed exactly two approved report pages.
- Confirmed all 24 approved groups are assigned exactly once.
- Confirmed Place has 9 groups.
- Confirmed Site has 15 groups.
- Confirmed the existing approved question inventory remains 263 current slots.
- Confirmed every approved field can inherit its page and accent role from its approved group.
- No production data or deployment configuration changed.

### Next

After review and merge, proceed to the first definition-reconciliation change that corrects an approved mismatch while preserving historical storage.


## M1-003: Keep Flat Walking Surface as an Approved V3 Question

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `404b105432c4e37f0af807a89122ed5e16cc0f28`

### Decision

Flat Walking Surface remains an active approved V3 Site Accessibility question.

The older approved Scout Report PDF does not contain this question, but the V3 specification explicitly allows deliberate V3 decisions to supersede the older PDF.

### Canonical identity

- Key: `flat_walking_surface`
- Page: Site details
- Group: Accessibility
- Status: Active
- Existing storage: Preserved
- Historical answers: Preserved

### Changes

- Reclassified the V3-007 reconciliation item from an unexplained extra question to an explicit approved V3 override.
- Recorded the decision in `docs/SCOUT_REPORT_SPEC.md`.
- Did not remove, rename, reorder, or change the existing field.

### Verification

- The approved question inventory remains 263 current slots.
- Flat Walking Surface remains in the Site Accessibility group.
- No persistence, rendering, answer options, database schema, or historical data changed.

### Next

Continue definition reconciliation with another confirmed mismatch that still requires correction.


## M1-004: Canonical Official Basic Information

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `9485829ff92a50492ade609fedcf0512209131ac`

### Objective

Bring the four approved official Basic Information items under the canonical Scout Report definition layer without copying them into community-editable answer storage.

### Canonical source fields

Added four read-only approved source-field definitions:

- `official_address`
- `official_phone`
- `official_email`
- `official_links`

All four are:

- Page: Place details
- Group: Basic
- Accent role: Place
- Visibility: Public
- Editing: Read-only
- Source bundle: existing facility public/source data

### Existing data preserved

The values continue to come from the existing `llama_place_facility_public_data()` path backed by official facility/source records.

No database fields, provider IDs, provenance records, or community answer storage were changed.

### Presentation cleanup

The existing `partials/place/facility-info.php` presentation now consumes the canonical source-field definitions for labels and source keys instead of independently hardcoding those definitions.

### Reconciliation status

The four V3-007 items are no longer classified as unexplained fields outside the registry. They are now explicitly classified as canonical source fields.

### Verification

- Confirmed exactly four canonical official Basic source fields.
- Confirmed each points to the Basic group on the Place page.
- Confirmed each remains read-only.
- Confirmed each has a source key.
- Confirmed the existing 263 community/current approved question slots are unchanged.
- Confirmed no persistence, schema, provider provenance, or production data changed.

### Next

Continue definition reconciliation with a mismatch that affects approved question identity or answer semantics while preserving historical compatibility.


## M1-005: Seasonal Access Compound Date Cards

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `ab428a86f6b69e5b5f269118445b74979a9862b4`

### Objective

Keep the existing four month/day storage keys while presenting Seasonal Access as two comprehensible questions and cards.

### Canonical compound questions

**Season Begins**

- Presentation key: `season_begins`
- Month: `approach_closure_month`
- Day: `approach_closure_day`

**Season Ends**

- Presentation key: `season_ends`
- Month: `approach_reopening_month`
- Day: `approach_reopening_day`

### Editable form behavior

Each compound question renders as one card containing two controls:

- Month
- Day

The actual submitted control names remain the existing four component keys.

### Published Scout Report behavior

The two components render as one answer card.

Examples:

- Season Begins: `April 30`
- Season Ends: `September 15`

Partial historical values remain readable instead of being discarded.

### Existing data preserved

No database migration was performed.

The existing four component keys remain authoritative for:

- persistence
- validation
- update handling
- historical compatibility
- completion calculations

### Definition layer

Added reusable compound-question metadata and a component lookup so future compound questions can use the same pattern without duplicating presentation logic.

Added integrity checks for:

- unknown compound groups/pages
- duplicate component assignment
- missing component roles
- component/group drift

### Corrective repair

During self-review, M1-005 found that the M1-004 official Basic source-field definitions had the wrong metadata shape on `v3-development`. The existing facility-info renderer expects canonical `label`, `group`, `page`, and `source_key` metadata.

This branch repairs those four source-field definitions and restores their reconciliation status to `canonical_source_field`.

### Verification

- Confirmed two canonical compound questions.
- Confirmed each contains exactly the expected Month and Day component keys.
- Confirmed existing month/day select options and Seasonal Road Closure applicability remain unchanged.
- Confirmed editable controls still submit the four existing keys.
- Confirmed published values use existing select labels, producing values such as `April 30`.
- Confirmed the current 263 stored/current approved component projection remains unchanged.
- No database schema or production data changed.

### Next

Continue canonical definition reconciliation with the next confirmed mismatch.


## M1-006: Separate Site and Parking Clearance

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `fd523aec448356b4be6fa4a5553dd23d09027caa`

### Objective

Resolve the approved Scout Report conflict where Site Overhead Clearance and Parking Clearance were represented by one shared key.

### Historical preservation decision

The existing `overhead_clearance_feet` field remains Site Overhead Clearance.

This preserves its existing label, help meaning, storage path, and historical answers.

It is not repurposed as Parking Clearance.

### New canonical field

Added:

`parking_clearance_feet`

Storage:

`details.parking_clearance_feet`

The new field uses the same feet-based answer options and Unknown support as the existing Site clearance field.

### Approved grouping

**Site Info**

- `overhead_clearance_feet` = Site Overhead Clearance

**Parking and Maneuvering**

- `parking_clearance_feet` = Parking Clearance

Both are Site-scoped.

### Presentation meaning

Site Overhead Clearance describes overhead obstacles across the usable Site area.

Parking Clearance describes overhead clearance over the primary parking or driveway position.

### Projection count

The stored/current approved projection increases from 263 to 264 field slots because two approved questions now have two distinct persisted identities.

The Seasonal Access compound presentation still combines four stored component fields into two visible cards.

### Verification

- Confirmed `overhead_clearance_feet` remains on its existing storage path.
- Confirmed `parking_clearance_feet` uses a new scoped JSON detail path.
- Confirmed both fields are Site-scoped.
- Confirmed Site Overhead Clearance belongs to Site Info.
- Confirmed Parking Clearance belongs to Parking and Maneuvering.
- Confirmed no approved key is shared between the two clearance questions.
- Confirmed the approved stored projection is now 264 field slots.
- No database schema migration or historical data rewrite was performed.

### Next

Continue canonical definition reconciliation with the next confirmed answer-type or answer-option mismatch.


## M1-007: Camp Host Answer Vocabulary

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `107b876de94932ee3706b33563546cc4c9230702`

### Objective

Align Camp Host with the approved Yes/No answer model while preserving historical Seasonal answers.

### Active answer vocabulary

New/current Camp Host answers are:

- Yes
- No
- Unknown
- Unanswered

Unknown and Unanswered continue to use the canonical answer-state system.

### Historical compatibility

The historical value:

`seasonal`

remains registered as a compatibility-only answer.

It is:

- displayed correctly on old reports
- retained if an existing record is edited without changing Camp Host
- not offered as a new answer on a fresh report

### Shared compatibility support

Added generic `legacy_values` support for select-style fields:

- the parser accepts registered historical values
- the read-only renderer displays their human label
- the shared Yes/No control exposes a legacy value only when it is the existing current value

This prevents future answer-vocabulary cleanup from requiring one-off UI hacks.

### Toilet type deferral

The Toilets mismatch was reviewed before this task.

The approved answer vocabulary requires:

- Pit
- Flush
- Portable
- No
- Unknown

However, the existing Place answer publishes through the SQL-backed `place_amenities.toilets` column and the repository does not contain a main reporting schema migration for changing that storage shape.

The richer Toilet answer was therefore intentionally not merged in this task.

It remains unresolved until a safe storage migration or dual-storage design is approved.

### Verification

- Confirmed Camp Host new options are only Yes and No.
- Confirmed Unknown remains available through existing canonical answer state.
- Confirmed existing Seasonal values remain readable.
- Confirmed an existing Seasonal value can remain selected during an edit.
- Confirmed the approved stored projection remains 264 field slots.
- No database schema or production data changed.

### Next

Continue with the next mismatch that can be corrected without an undefined storage migration.


## M1-008: Toilets Typed Storage Migration

**Status:** Completed

**Date:** 2026-10-29

**Base branch:** `v3-development`

**Merged into `v3-development`:** `f69fc5a07529d0b517c86c2b5d4d553fa5398b19`

### Objective

Resolve the Toilets answer-model mismatch using a safe dual-storage migration.

### Canonical question

Key:

`amenity_toilets`

Active answers:

- Pit toilet
- Flush toilet
- Portable toilet
- No toilets
- Unknown
- Unanswered

Unknown and Unanswered continue to use the canonical answer-state system.

### Canonical storage

The canonical question now stores its richer answer in:

`place_amenities.toilet_type`

Canonical stored values:

- `pit`
- `flush`
- `portable`
- `no`

Historical compatibility value:

- `present_unknown_type`

### Legacy compatibility

The existing SQL column:

`place_amenities.toilets`

remains a compatibility presence mirror.

New canonical answers mirror as:

- pit -> true
- flush -> true
- portable -> true
- present_unknown_type -> true
- no -> false
- Unknown/Unanswered -> null

This allows older code that only understands toilet presence to continue working while V3 uses the richer answer.

### One-time SQL migration required

Before code using this field is deployed against a database, add the nullable `toilet_type` column and backfill existing boolean records.

Existing Yes becomes `present_unknown_type`.

Existing No becomes `no`.

Unknown/null remains null.

### RIDB compatibility

The RIDB canonical importer continues to populate the legacy presence column and now also populates the richer type when source evidence is specific enough.

Recognized source distinctions include:

- Flush toilet -> `flush`
- Pit or vault toilet -> `pit`
- Portable toilet -> `portable`
- Generic known toilet presence -> `present_unknown_type`

The importer does not guess a specific type when the source only proves that a toilet exists.

### Accessibility dependency

Accessible Toilet is applicable when the canonical toilet answer indicates a toilet is present:

- pit
- flush
- portable
- present_unknown_type

It is not applicable when the answer is No.

### Verification

- Canonical key remains `amenity_toilets`.
- Approved group remains Place Amenities.
- Approved stored-question projection remains 264.
- Canonical storage points to `amenities.toilet_type`.
- Legacy presence mirror points to `amenities.toilets`.
- Existing historical Yes/No can be backfilled without guessing type.
- RIDB type enrichment preserves specificity when known.
- No existing column is renamed or removed.

### Next

After review, merge the application changes and run the one-time SQL migration before this V3 code is deployed against the database.


## M1-009: Keep Parking-to-Site Distance Buckets

**Status:** Ready for review

**Date:** 2026-10-30

**Base branch:** `v3-development`

### Decision

The existing Walking Distance from Vehicle buckets remain the approved V3 answer model.

Canonical key:

`walking_distance_from_vehicle`

### Approved V3 answers

- At / beside vehicle
- Under 50 ft
- 50-100 ft
- 100-250 ft
- 250-500 ft
- 500+ ft / short hike
- Unknown
- Unanswered

### Rationale

The older approved Scout Report PDF describes the answer as a distance in feet.

V3 intentionally keeps the existing buckets because they provide enough practical accessibility and Site-fit information without requiring a false level of measurement precision.

### Preservation

No field type, storage path, answer values, historical data, scope, rendering, or database schema changes.

The reconciliation item is reclassified as an explicit V3 override rather than an unresolved mismatch.

### Verification

- Canonical key remains `walking_distance_from_vehicle`.
- Existing bucket values remain unchanged.
- Site Accessibility grouping remains unchanged.
- Site scope remains unchanged.
- Approved stored-question projection remains 264.

### Next

Continue with the next unresolved canonical answer-vocabulary mismatch.

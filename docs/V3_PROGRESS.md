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

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `15736ffa55cba3f67936e383534c956f8111d0a1`

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


## M1-010: Field-Specific Rule Permission Vocabularies

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `5c9f5548faea4f1fb3030f34d06e99b3e540d206`

### Objective

Resolve the remaining Rules and Restrictions answer-vocabulary mismatch without discarding historical Conditional values.

### Yes / No only

The following approved V3 questions now offer only:

- Yes
- No
- Unknown
- Unanswered

Fields:

- `overnight_camping_allowed`
- `dispersed_camping_allowed`

Historical Permit Required value `2` and Conditional value `3` remain registered as compatibility-only values when already stored.

### Yes / Permit required / No

The following approved V3 questions now offer:

- Yes
- Permit required
- No
- Unknown
- Unanswered

Fields:

- `campfire_allowed`
- `collecting_firewood`
- `place_charcoal_grills_allowed`
- `place_propane_fire_pits_allowed`
- `drone_use_legal`
- `target_shooting_allowed`

Historical Conditional value `3` remains readable when already stored but is no longer offered as a new answer.

### Alcohol

`place_alcohol_permitted` now offers:

- Yes
- No Glass
- No
- Unknown
- Unanswered

Historical Conditional value `3` remains compatibility-only.

### Permit and condition details

Charcoal grills and propane fire pits now participate in the same Fire and Activity permit-details and permit-URL applicability as the other permit-capable fire/activity rules.

Legacy Conditional answers continue to expose their detail fields so historical context is not hidden.

### Compatibility

The generic `legacy_values` support added in earlier Milestone 1 work is reused here.

No historical value is rewritten.

No database schema or SQL migration is required.

### Verification

- Confirmed Overnight Stay and Dispersed Camping no longer offer Permit or Conditional as new answers.
- Confirmed Campfires, Collecting Firewood, Charcoal Grills, Propane Fire Pits, Drone Use, and Target Shooting offer Permit required.
- Confirmed Conditional remains available only as a legacy value for approved fields.
- Confirmed Alcohol keeps No Glass as an active answer and Conditional as legacy only.
- Confirmed permit/condition detail applicability includes charcoal and propane rules.
- Confirmed approved stored-question projection remains 264.

### Next

Continue canonical definition reconciliation and review whether any unresolved reconciliation items remain before closing Milestone 1 definition cleanup.


## M1-011: Restore With Permit for Overnight and Dispersed Camping

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `c328be1a9f277c0ce402a34f23e2044c8c0f01ad`

### Correction

M1-010 incorrectly made Overnight Stay and Dispersed Camping active Yes/No-only questions.

The approved V3 behavior is:

- Yes
- With Permit
- No
- Unknown
- Unanswered

Fields:

- `overnight_camping_allowed`
- `dispersed_camping_allowed`

### Storage

Existing numeric permission storage is preserved.

- `0` = No
- `1` = Yes
- `2` = With Permit
- `3` = Conditional, legacy-only

No stored values are rewritten.

### Existing dependencies

The existing camping permit/conditions details and permit URL logic already activates for value `2`, so no additional persistence or applicability migration is required.

Historical Conditional value `3` remains readable and can continue exposing its existing condition details.

### Verification

- Confirmed Overnight Stay offers With Permit as active value `2`.
- Confirmed Dispersed Camping offers With Permit as active value `2`.
- Confirmed Conditional remains legacy-only.
- Confirmed no database schema or SQL migration is required.
- Approved stored-question projection remains 264.

### Next

Continue Milestone 1 reconciliation from the corrected rule vocabulary.


## M1-012: Canonical Question Visibility

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `c5737a85b18df69899d411f22fb19418078ba640`

### Objective

Establish one canonical Public / Community visibility classification for every approved Scout Report question.

### Public baseline

The initial V3 Public classification follows the existing public Place experience.

Public Place identity and orientation questions:

- `name`
- `type`
- `elevation_feet`
- `city`
- `county`
- `state`
- `land_type`
- `land_manager`
- `region`

All approved shared Place Amenities questions are Public.

Canonical official source fields remain Public:

- `official_address`
- `official_phone`
- `official_email`
- `official_links`

### Community baseline

Every other approved Scout Report question is Community unless a later approved V3 decision explicitly changes it.

This includes exact coordinates, road / route, description, detailed Place access information, all Site questions, connectivity, sensory information, hazards, rules, recommendations, Scout Notes, and visit-history report metadata.

### Legacy group names

Old group keys containing `_public` do not control V3 visibility.

Canonical visibility metadata is authoritative.

### Registry metadata

Added:

`llama_place_report_approved_question_visibility()`

Finalized approved fields now expose:

`approved_visibility`

Allowed values:

- `public`
- `community`

### Integrity checks

Registry diagnostics now verify that every approved question has a valid canonical visibility classification.

### Behavior preservation

This task adds canonical metadata only.

It does not yet change public/member authorization or rendered Place behavior.

Interface enforcement will be migrated separately so access behavior changes only when the shared authorization path is ready.

### Verification

- Confirmed every approved question receives Public or Community visibility.
- Confirmed shared Place Amenities are Public.
- Confirmed exact Latitude and Longitude remain Community.
- Confirmed Site questions remain Community.
- Confirmed official source fields remain Public.
- Confirmed legacy `_public` group names do not determine visibility.
- Approved stored-question projection remains 264.
- No database schema or production data changed.

### Next

Migrate one reporting interface or shared authorization path to consume canonical visibility without changing dispersed-Site discoverability rules.


## M1-013: Locked Preview Canonical Visibility

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `6748605150ff8c46e2cd27ec3d5fe8f13ceba958`

### Objective

Migrate the locked public Scout Report preview to consume canonical Public / Community visibility instead of legacy section exclusions.

### Shared authorization helper

Added:

`llama_place_report_visibility_allows()`

and:

`llama_place_report_field_visibility_allows()`

Rules:

- Public is allowed at Public access.
- Community is allowed only with Community access.
- Missing or invalid visibility never grants access.

These helpers evaluate question-level visibility only.

They do not grant Place/Site access and do not override restricted dispersed-Site discoverability.

### Locked preview migration

The locked public preview now derives its question inventory from:

`llama_place_report_approved_question_groups()`

For a public viewer, it includes only fields that:

- are not Public
- are Community
- belong to the approved questionnaire

It no longer uses legacy section names as its access-control rule.

### Presentation preservation

- Public questions are not shown as Members only.
- Community questions remain locked.
- Description remains a separate locked About this place panel.
- Seasonal compound questions appear as Season Begins and Season Ends rather than four month/day cards.
- Approved question/group order is preserved.

### Access behavior

No Community answer values are exposed publicly.

Complete Access behavior is unchanged.

Dispersed-Site discoverability restrictions are unchanged.

No database schema or SQL migration is required.

### Verification

- Confirmed locked preview uses canonical approved question groups.
- Confirmed it calls the shared visibility authorization helper.
- Confirmed Public fields are excluded from locked cards.
- Confirmed Community fields remain represented as locked cards.
- Confirmed invalid or missing visibility does not grant access.
- Confirmed compound seasonal components deduplicate to two locked question labels.
- Approved stored-question projection remains 264.

### Next

Migrate the next reporting interface or read path to consume canonical visibility, then evaluate the remaining Milestone 1 completion gate.


## M1-014: Canonical Published Report Page Ownership

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `ec6aa47f49bc0fbd3eaef606b093f7f86fa9fda7`

### Objective

Remove the published Scout Report's duplicate hardcoded Place-group list and make canonical two-page metadata authoritative for Place/Site ownership and group order.

### Canonical group sequence

Added:

`llama_place_report_approved_groups_in_page_order()`

This derives the report sequence from:

`llama_place_report_approved_pages()`

while retaining group labels and question order from:

`llama_place_report_approved_question_groups()`

### Published renderer migration

The read-only Scout Report now:

- builds its group sequence from canonical page definitions
- gets each group's Place/Site ownership from `llama_place_report_approved_group_page_lookup()`
- no longer maintains a local nine-group Place list
- preserves existing Place/Area and Site/Parking visual scope classes
- exposes semantic page ownership through `data-report-page="place"` or `data-report-page="site"`

### Behavior preservation

This task does not yet split the existing Place experience into separate visual Place and Site pages.

It establishes canonical semantic ownership first so the later UI migration can separate them without rebuilding group logic again.

No question storage, answers, access control, Site switching, database schema, or SQL changed.

### Verification

- Confirmed all approved groups are returned in canonical page order.
- Confirmed all nine Place groups derive from the Place page registry.
- Confirmed all fifteen Site groups derive from the Site page registry.
- Confirmed the old local `$publicPlaceGroups` list is removed.
- Confirmed report scope styling uses canonical page ownership.
- Approved stored-question projection remains 264.

### Next

Review remaining Milestone 1 definition consumers and determine whether the canonical-definition completion gate is satisfied before beginning Milestone 2 answer/history work.


## M1-015: Milestone 1 Completion Gate

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `0a50c649478f26274af71ab0f6b9fb4fd6f5298b`

### Objective

Evaluate and enforce the Milestone 1 completion gate instead of continuing definition cleanup indefinitely.

### Completion audit

Added:

`docs/audits/08-milestone-1-completion.md`

Gate result:

**PASS, contingent on merge of M1-015.**

### Reconciliation result

The canonical reconciliation registry contains no unresolved mismatch, conflict, missing-definition, or shared-key status.

All tracked differences are now:

- resolved
- explicitly compatibility-preserved
- workflow-only
- or intentional V3 overrides

### Interface result

Reviewed the primary reporting interfaces:

- Add Place
- Update This Place
- Admin Place editing
- Moderation
- Published Scout Report
- Locked public Scout Report preview

All retrieve question definitions from the shared Place Report field/approved registries rather than maintaining a competing approved question contract.

### Diagnostic enforcement

Expanded canonical contract diagnostics so future drift fails visibly.

The diagnostics now enforce:

- approved inventory integrity
- finalized question group/order
- page ownership
- accent role
- Public / Community visibility
- reconciliation completion
- exact visibility inventory coverage
- storage identity
- report ownership
- answer-type support
- Unknown support
- applicability dependencies
- storage adapters

### Milestone 1 completion

After this PR is merged:

**Milestone 1: Canonical Reporting Definitions is complete.**

Remaining reporting concerns involving answer precedence, persistence, immutable reports, provenance, partial updates, and history belong to Milestone 2.

### Next

Begin Milestone 2 with a bounded audit of the canonical current-answer read path before changing answer persistence behavior.


## M2-001: Current-Answer Read Path Audit

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `42f3a7a00068580f48fb685bd71c85cf5ed7b612`

### Objective

Trace what the application currently treats as the current answer for Place, Area, map-only Site, and canonical campsite Site targets before changing persistence or history behavior.

### Audit

Added:

`docs/audits/09-current-answer-read-path.md`

### Current Place model

Current Place values come from mutable published fact tables through:

`llama_place_report_current_values()`

Unknown state is read separately through:

`llama_place_report_published_answer_state()`

Historical contributions and submissions do not select current Place answers.

### Scoped model

Current scoped answers use deterministic nearest-recorded precedence.

Area:

1. direct Area answer
2. eligible Place answer

Map-only Site:

1. direct Site answer
2. eligible parent Area answer
3. eligible Place answer

Canonical campsite Site:

1. direct Site Scout/Admin override
2. imported campsite baseline
3. eligible parent Area answer
4. eligible Place answer

### State behavior to preserve

The resolver deliberately treats these as recorded answers that stop inheritance:

- explicit No / false
- numeric zero
- explicit null
- Unknown
- explicitly recorded empty list

Only an absent field inherits.

Clearing a scoped override deletes the row and resumes inheritance.

### Confirmed read-model gaps

- no single canonical answer envelope exists
- Place current values do not expose field-level origin
- canonical campsite provenance collapses imported baseline and direct Site override into the same Site/campsite_record provenance identity
- imported source refresh can change current Site baseline without a historical baseline snapshot
- mutable current state is not derived from immutable report history
- Place and scoped Unknown state use different storage representations

### Append-only report contract

The diagnostic `scout_report_*` tables are not currently part of current-answer selection and still have no active repository-backed read/write integration.

### Behavior

This task is audit-only.

No storage, precedence, answer values, interface behavior, database schema, or SQL changed.

### Next

**M2-002: Canonical Current Answer Read Model**

Introduce one shared current-answer envelope over the existing compatibility precedence before changing persistence or historical storage.


## M2-002: Canonical Current Answer Read Model

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `505a65c6e55e743bd39ee00ad2c00fbe903df457`

### Objective

Introduce one canonical read-only answer envelope over the existing current Place, Area, Site, and campsite baseline precedence.

### New service

Added:

`app/place-report/current-answers.php`

Shared entry point:

`llama_place_report_current_answer_model()`

The target is verified against the selected Place before current answers are returned.

### Answer envelope

Each relevant field now has a common read shape:

- `field_key`
- `state`
- `value`
- `recorded`
- `inherited`
- `direct`
- `baseline`
- `imported`
- `target`
- `origin`

### State semantics

The read model distinguishes:

- Answered
- Unknown
- Unanswered

An explicit recorded null remains Answered with a null value.

Unknown and Unanswered also use a null value, but their state remains distinct.

No truthiness-based state inference is used.

### Origin semantics

Current origin types:

- `published_place`
- `scoped_override`
- `campsite_baseline`

A campsite baseline is separately marked as externally imported only when its source provider is not Llama Scout.

### Campsite baseline preservation

The target model now retains:

- `baseline`: campsite-record baseline values
- `overrides`: direct scoped answers
- `direct`: baseline plus overrides

Field-level provenance now distinguishes a baseline winner from a direct Scout/Admin scoped override.

Source provider and external ID are retained for baseline provenance when available.

### Precedence preservation

No current-answer winner changed.

The model wraps the existing precedence documented in M2-001.

### Diagnostics

Added side-effect-free contract checks covering:

- Unanswered
- Unknown
- explicit No / false
- inherited Area answer
- campsite baseline
- external imported baseline identity

The standard Place Report contract diagnostic now includes these checks.

### Behavior

This task does not migrate any public/admin/member interface to the new envelope yet.

It does not change persistence, approval behavior, database schema, or historical storage.

No SQL is required.

### Next

Migrate one current-answer consumer to the canonical envelope while preserving its rendered and editing behavior.


## M2-003: Site Detail Uses Canonical Current Answers

**Status:** Completed

**Date:** 2026-10-30

**Base branch:** `v3-development`

**Merged into `v3-development`:** `8cf56ef9d78a55c97f367ed9732fe068e0f93da8`

### Objective

Migrate one active current-answer consumer onto the canonical answer envelope without changing its response or frontend behavior.

### Consumer

Migrated:

`api/place-campsite-detail.php?mode=report`

This endpoint is used by Site switching in the published Scout Report.

### Previous read path

The endpoint previously rebuilt current Site state locally by:

1. reading published Place answer state
2. converting Place data to scoped values
3. locating the Site reporting target
4. resolving Place / Area / Site precedence
5. composing the campsite baseline
6. generating display maps and warnings

That duplicated the precedence now owned by the canonical current-answer model.

### New read path

The endpoint now calls:

`llama_place_report_current_answer_model()`

for the canonical campsite target.

It projects the returned envelopes through:

`llama_place_report_current_answer_values()`

### Compatibility projections

Added reusable envelope-to-flat-map projections:

- `effective`
- `inherited`
- `local`
- `scoped_override`
- `baseline`

Unknown answers convert back to the existing Unknown token.

Unanswered answers are omitted.

### Response preservation

The existing JSON contract is unchanged:

- `report_inherited_values`
- `report_override_values`
- `report_override_keys`
- `report_warnings`

For compatibility, the historical name `report_override_values` still contains all Site-local values, including campsite baseline values.

No JavaScript change is required.

### Warning preservation

Warnings continue to use the same effective current answers.

The source of those answers is now the canonical envelope instead of endpoint-local precedence code.

### Published Place compatibility

During self-review, the canonical Place reader was corrected to run:

`llama_place_report_derive_compatibility()`

before converting published Place data into canonical scoped values.

This preserves the same compatibility-mirror behavior previously used by:

`llama_place_report_data_from_published_place()`

and prevents the first consumer migration from silently dropping legacy mirror-derived answers.

### Diagnostics

Extended the current-answer synthetic contract checks to verify:

- effective projection preserves Unknown and omits Unanswered
- inherited projection returns only inherited winners
- local projection excludes inherited answers
- baseline projection returns only campsite baseline winners

### Behavior

No current-answer precedence changed.

No Site switching behavior changed.

No persistence, moderation, schema, or SQL changed.

### Next

Migrate the next active current-answer consumer, preferably the Update This Place scoped target read path, onto the canonical envelope while preserving its editing-layer semantics.


## M2-004: Update This Place Reference-Only Report Model

**Status:** Ready for review

**Date:** 2026-10-30

**Base branch:** `v3-development`

### Objective

Correct Update This Place so current answers are reference context for a new Scout report rather than editable form values.

### Product rule

Update This Place submits a new report.

It does not directly edit the current Place, Area, or Site record.

Current answers may inform the Scout and moderation conflict checks, but they are not copied into the Scout's draft.

### Entire Place reports

Normal new Entire Place reports now start with blank controls.

Current published answers are displayed separately as:

`Current answer: ...`

The current reference values come from:

`llama_place_report_current_answer_model()`

A Needs Changes revision preloads only the contributor's own previously submitted answers.

A failed POST restores only that submitted draft.

### Touched-field submission

The shared form now supports:

`mode = update-report`

Update-report forms emit a partial-report marker and touched-question markers.

The browser marks a Place question touched only when the Scout interacts with that question while the Entire Place target is selected.

The backend independently enforces the same rule.

Untouched questions are ignored even if the shared form emitted hidden presence or Unanswered controls.

### Unanswered preservation

For partial reports:

- Unanswered is omitted
- untouched is omitted
- explicit No remains an answer
- zero remains an answer
- Unknown remains an answer
- intentional empty-list remains an answer

This prevents a one-question report from erasing unrelated current answers.

### Area and Site reports

The scoped context endpoint now uses the canonical current-answer envelope.

Existing answers are shown as reference notes only.

Current answers no longer populate Area/Site report controls.

Draft controls are populated only by the Scout's new answers or a Needs Changes revision.

The existing response shape remains compatible with the target-switching JavaScript.

### Scoped submission snapshots

Scoped report submission now snapshots:

- current direct scoped override state
- current effective answer state

through the canonical current-answer model.

These are moderation conflict/reference snapshots, not proposed answers.

Approval conflict checking reads the same canonical current-answer model before applying approved answers.

### Compatibility fix

Scoped target composition now applies:

`llama_place_report_derive_compatibility()`

to published Place data before inheritance resolution.

The current-answer service now explicitly loads the scoped value adapter it depends on.

### UI wording

The Update This Place page now explains:

- submit a new Scout report
- current answers are reference only
- answer only what was observed
- blank questions are not submitted
- approval can update the current summary while the report remains in history

Scoped status text now says Reporting rather than Updating.

### Verification

- Confirmed normal Entire Place controls start blank.
- Confirmed field defaults do not preload in update-report mode.
- Confirmed current Place answers render separately as references.
- Confirmed Needs Changes can preload only the contributor's report draft.
- Confirmed failed POST restores only touched draft answers.
- Confirmed untouched Place fields are ignored server-side.
- Confirmed Unanswered partial-report fields cannot clear current answers.
- Confirmed explicit No, Unknown, zero, and empty-list answers remain recordable.
- Confirmed Site/Area current answers no longer populate report controls.
- Confirmed scoped current-answer context and moderation conflict checks use the canonical envelope.
- Confirmed Place touched tracking cannot be triggered by Site/Area controls.
- No database schema or SQL migration is required.

### Next

Audit and unify answer validation across Place and scoped report submission so the same state rules are enforced before moving deeper into persistence/history work.


## M2-005: Unified Report Answer Validation

**Status:** Ready for review

**Date:** 2026-10-30

**Base branch:** `v3-development`

### Objective

Enforce the same Answered / Unknown / Unanswered contract for Entire Place and
scoped Area/Site report submissions before moving into immutable report
persistence and history.

### Audit findings

Both submission paths already used the canonical field parser for type-specific
validation, but they applied different report-state behavior around it.

Entire Place reports omitted Unanswered observations. Area/Site reports rejected
an Unanswered entry as an error.

Entire Place reports silently removed an Answered observation when it matched
the current Place answer. Area/Site reports rejected the same observation as
already recorded.

Those differences treated current-summary comparison as answer validation even
though Update This Place is a new report.

### Shared submission-answer contract

Added:

`llama_place_report_submission_answer()`

The envelope exposes:

- `state`
- canonical `value`
- whether the answer is `recorded`
- the existing scoped transport representation

The contract preserves:

- No / false as Answered
- numeric zero as Answered
- Unknown as recorded Unknown when permitted
- an intentional empty list as Answered
- blank or Unanswered as not recorded

All existing type, option, range, date, URL, length, and Unknown-permission
validation remains owned by the canonical parser.

### Entire Place reports

Partial Place reports now use the shared submission-answer envelope.

Unanswered remains omitted.

An intentionally submitted Answered or Unknown observation remains in the
moderated report payload even when it confirms the current answer.

The existing non-report editing path retains its previous change-only behavior.

### Area and Site reports

Scoped submissions now use the same envelope.

Unanswered entries are omitted instead of becoming a target-specific validation
error. A payload containing no recorded answers is still rejected.

Valid Answered or Unknown observations are no longer rejected merely because
the current scoped answer has the same value.

The scoped browser adapter now queues an intentionally supplied answer based on
its answer state rather than comparing it with the current reference value.

Duplicate submitted question keys remain invalid, including when an earlier
duplicate entry was Unanswered.

### Diagnostics

The standard Place Report contract diagnostic now checks the shared submission
contract for:

- Unanswered
- explicit No
- numeric zero
- Unknown
- intentional empty list

### Scope

No database schema, SQL migration, current-answer precedence, permissions,
Admin clearing behavior, moderation authorization, or frontend layout changed.

This task does not implement immutable Scout Report persistence or migrate
historical records.

### Verification

- `git diff --check` passes.
- `node --check js/place-update-report-target.js` passes.
- Reviewed both active submission paths against the shared envelope.
- PHP CLI is not installed in the Codex environment, so PHP lint and the runtime
  contract diagnostic could not be executed here.

### Next

Connect approved report submissions to append-only Scout Report persistence while
keeping current summaries as derived projections and preserving existing
moderation behavior.


## M2-006: Approved Update Report History

**Status:** Ready for review

**Date:** 2026-10-30

**Base branch:** `v3-development`

### Objective

Persist approved Update This Place reports as append-only historical evidence
before applying their answers to the existing current-summary projection.

This task covers Entire Place, Area, and Site targets submitted through Update
This Place.

### Immutable history service

Added:

`app/place-report/immutable-history.php`

The service:

- requires an active moderation transaction
- snapshots the canonical question registry and addresses it by SHA-256
- validates each report answer through the shared M2-005 submission contract
- omits Unanswered because it never enters the approved report payload
- preserves Answered, explicit No, numeric zero, Unknown, and empty lists
- stores explicit Place / Area / Site target identity
- stores contributor, role, visit date, and submission time
- creates a deterministic content hash
- records the approval moderation event
- prevents a reused client submission key from identifying different content

### Approval ordering

For Entire Place and scoped Area/Site updates, approval now performs this order
inside the existing transaction:

1. validate the pending submission and conflict snapshots
2. copy submitted photos into permanent Place storage
3. create the immutable report, answers, ordered photo snapshots, and approval event
4. update only the submitted questions in the existing current projection
5. create the existing contribution, points, moderation history, and Admin audit records
6. commit through the existing controller

If a later step fails, the database transaction rolls back. The existing copied
photo rollback journal removes permanent files created during the failed approval.

### Scoped photo preservation

Scoped approval previously returned before the normal photo attachment path.
The controller then deleted the source update folder after commit, which could
leave scoped evidence metadata pointing at removed files.

Scoped approval now uses the same transaction-safe permanent photo copy path as
Entire Place approval before immutable photo snapshots are written.

The immutable photo record reuses the permanent Place photo file. It does not
create a duplicate report-only copy.

Each photo snapshot preserves:

- report order
- permanent path
- SHA-256 of the permanent path
- SHA-256 of the file contents
- the original submitted photo metadata

### Database migration

Added:

`tools/migrations/20261030_m2_006_scout_report_history.sql`

The migration creates:

- `scout_report_schemas`
- `scout_reports`
- `scout_report_answers`
- `scout_report_moderation_events`
- `scout_report_photos`

It also creates the unique indexes expected by the existing diagnostic and
UPDATE / DELETE guard triggers for every append-only table.

Migration, backup, compatibility, verification, and recovery instructions are
documented in:

`docs/migrations/M2-006_APPROVED_UPDATE_REPORT_HISTORY.md`

The migration must be reviewed and applied before the PHP changes are deployed.
No production database change was performed by Codex.

### Current projection compatibility

Existing public and Admin readers continue using the current published Place and
scoped-answer tables.

M2-006 writes those compatibility tables only after the immutable report insert
succeeds in the same transaction.

No current-answer precedence, permission, moderation authorization, points rule,
or public presentation changed.

### Scope boundaries

This task does not:

- create immutable history for Add Place and its first Site
- backfill legacy submissions or update history
- make current-answer reads query the immutable ledger
- implement corrections or amendments to already approved reports
- change public history or comparison presentation
- delete or migrate existing current tables

### Verification

- `git diff --check` passes.
- Inspected the Admin and Master Scout approval callers to confirm they retain
  the existing outer transaction and copied-photo rollback handling.
- Confirmed immutable history is inserted before current Place or scoped writes.
- Confirmed Place and scoped paths use the same writer and answer validator.
- PHP CLI and MariaDB clients are not installed in the Codex environment, so PHP
  lint, SQL execution, schema diagnostics, and live approval tests could not be
  run here.

### Next

Apply and verify the reviewed migration in staging, then exercise one Entire
Place approval and one Site approval before merging or deploying the application
changes. After that, the next code task is immutable history for Add Place and
its first Site bundle.


## M2-007: Approved Add Place Report Bundle

**Status:** Ready for review

**Date:** 2026-10-30

**Base branch:** `v3-development`

### Objective

Persist every approved community Add Place submission as an immutable Place and
first-Site report bundle before publishing its answers into the existing current
projection.

### Submission answer ownership

Added `llama_place_report_submission_answers()` as the shared Add Place split.

It walks the canonical registry once and:

- omits derived and Unanswered questions
- preserves Answered, explicit No, numeric zero, Unknown, and empty lists
- assigns a question to Place when Place is an allowed scope
- otherwise assigns an eligible Site question to the first Site
- supplies the same Place keys to current publication and the same answer maps to
  immutable history

This keeps immutable report ownership aligned with the existing Add Place
publication rule instead of reconstructing it separately.

### Required first Site

Add Place approval now creates one canonical first Site for every approved
community Place, even when all optional Site questions are Unanswered.

The Site identity is created separately from answer publication. Its label is
`Site {identifier}` when `site_number` is answered and `Reported Site` otherwise.
No Area, map geometry, or additional Site is inferred.

### Immutable report bundle

Added `llama_scout_report_record_approved_new_place_bundle()`.

One approved Add Place submission now creates:

1. a Place report with Place answers and ordered permanent photo evidence
2. a first-Site report with Site answers

The reports share `place-submission:{submission_id}` as their bundle key. They
retain separate logical keys, client submission keys, content hashes, target
scope/source/id/label, answers, and approval events.

Both reports preserve:

- contributor and role at submission
- visit date and original submission time
- exact canonical schema version
- approving moderator and review notes
- original Add Place submission ID

An empty first-Site answer set is allowed only for this required Add Place bundle.
Its immutable Site report still records that the approved submission created the
canonical first Site.

### Approval ordering

Inside the existing Admin or Master Scout transaction, approval now:

1. validates and locks the pending Add Place submission
2. creates the Place identity and required first-Site identity
3. copies submitted photos into permanent Place storage
4. records the immutable Place and Site reports
5. publishes submitted Place and Site answers to current tables
6. records provenance, status history, contribution credit, points, badges,
   moderation status, and the existing audit event
7. commits through the existing caller

If any database step fails, the transaction rolls back. The existing copied-photo
journal removes permanent files created during the failed approval. Source files
remain until the caller commits successfully.

### Photo ownership

Add Place photos remain ordered Place evidence. The Place report references the
permanent files and records their content hashes. The Site report does not create
duplicate photo rows or duplicate physical files.

### Database compatibility

M2-007 uses the append-only tables and guards introduced by the reviewed M2-006
migration. It adds no database migration.

The M2-006 migration must therefore be applied before either M2-006 or M2-007 PHP
is deployed.

### Scope boundaries

This task does not:

- backfill previously approved Add Place submissions
- guess or create missing legacy first Sites
- migrate current-answer reads to the immutable ledger
- add the later Add Site to Existing Place member workflow
- change minimum submission requirements, permissions, points, moderation, or
  public presentation

### Verification

- `git diff --check` passes.
- Confirmed Admin and Master Scout approval still call the same transaction-owned
  `moderation_approve_new_place()` service.
- Confirmed the immutable bundle is written before current Place or Site answers.
- Confirmed permanent photo paths are sliced from the existing rollback journal
  and the source submission folder is still removed only after commit.
- Confirmed Update This Place retains its public immutable writer wrapper.
- PHP CLI and MariaDB clients are not installed in the Codex environment, so PHP
  lint, database execution, schema diagnostics, and live approval tests could not
  be run here.

### Next

After review and merge, exercise one Admin approval and one Master Scout approval
in staging with the M2-006 migration installed. Include one submission with Site
answers and one with all Site questions Unanswered. Then begin M2-008 as a
non-destructive legacy-history backfill design and dry-run audit before any
historical rows are inserted.

# V3-002 Reporting Definition Audit

**Status:** Ready for review

**Audit date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `b35be100e45590ef5167ef1b2eee12dba29dd7a4`

## Purpose and boundaries

This audit documents the existing Scout Report definition architecture: question identity, labels, types, choices, grouping, scope, applicability, answer states, validation, persistence references, presentation metadata, and parallel definitions. It is a static review of repository code. It does not change behavior, inspect a production database, audit every interface end to end, or perform the V3-007 question-by-question reconciliation.

The classifications used below are:

- **Canonical:** part of the final shared field contract or a shared registry that composes it.
- **Intentional adapter/presentation logic:** translates the shared contract for a workflow without claiming a new question identity.
- **Legacy compatibility:** retains historical keys, values, paths, or interfaces.
- **Suspected duplication:** repeats canonical information and could drift, but this audit does not establish that it currently produces different results.
- **Suspected override:** independently changes or persists behavior that is also represented canonically.
- **Needs investigation:** intent or active runtime use cannot be established safely from this definition audit alone.

## 1. Definition architecture overview

`app/place-report.php` is the public loader and describes the reporting package as the single source of truth. The actual source of truth is a composed result, not one static file:

1. `app/place-report/core.php` defines transport tokens and configurable form requirements.
2. `app/place-report/definitions.php` defines sections and shared choice catalogues.
3. The `fields-*.php` families register base fields through the `$add` closure in `app/place-report/fields.php`.
4. Applicability profiles and dependency passes add or replace `applicable_if` rules.
5. Flow modules change labels, types, options, help, grouping, order, visibility flags, derived status, and other metadata.
6. `app/place-report/flow-scopes.php` assigns Place/Area/Site ownership last.
7. `app/place-report/flow-layout.php` supplies approved-question identity and ordering metadata.
8. `app/place-report/fields.php` converts remaining checkbox definitions to tri-state questions and attaches legacy compatibility mirrors.
9. `app/place-report/answers.php`, `app/place-report/runtime.php`, and `app/place-report/persistence.php` consume the finalized definitions for parsing, completion, display, and published Place persistence.
10. `app/place-report/scoped-answers.php`, `app/place-report/report-targets.php`, and `app/place-report/target-model.php` apply the same field keys to Area/Site targets and imported Site baselines.

The final `llama_place_report_fields()` result is therefore canonical. A field's first registration is not necessarily its final definition.

The task list referred to `conditions.php`, `permissions.php`, and `metadata.php`. Those exact files do not exist. Their implemented equivalents are `app/place-report/flow-conditions.php`, `app/place-report/flow-permissions.php`, and `app/place-report/flow-metadata.php`.

## 2. Canonical registry files

### Registry and shared catalogues

| File | Classification | Responsibility |
| --- | --- | --- |
| `app/place-report/fields.php` | Canonical | Builds and caches the final field map, sets defaults, runs mutation passes, assigns approved-question metadata, converts legacy checkboxes, and defines published-storage projections. |
| `app/place-report/definitions.php` | Canonical | Legacy section catalogue, state list, distance options, land managers, land types, Place types, surfaces, and icon mapping. |
| `app/place-report/flow-layout.php` | Canonical | Approved questionnaire groups/order, active form layout groups/assignments, fallbacks, final registry reorder, and grouping projection. |
| `app/place-report/flow-scopes.php` | Canonical | Explicit semantic ownership inventory and inheritance policy for Place, Area, and Site. |
| `app/place-report/flow-metadata.php` | Canonical | Smaller flow groups and relative order for related questions. |
| `app/place-report/applicability.php` and `app/place-report/applicability/*.php` | Canonical, except the unwired Site module noted below | Place-type profiles and general applicability conditions. |
| `app/place-report/answers.php` | Canonical | Shared answer parser, equality rules, form adapters, writable fields by scope, and legacy path input adapter. |
| `app/place-report/runtime.php` | Canonical | Applicability evaluator, answer-state interpretation, completion, validation, display formatting, and publication helpers. |
| `app/place-report/persistence.php` | Canonical | Published Place reads/writes and Site publication from a new Place submission. |

`llama_place_report_approved_question_groups()` in `app/place-report/flow-layout.php` is a second canonical inventory layered over the field registry. Static inspection found 24 approved groups with 263 unique question slots and no key assigned to two approved groups. Fields not in that inventory remain registered and are marked `approved_question = false`; shared grouping can put them in `additional_questions` or a compatibility area rather than silently deleting them.

### Field registration families

| Family | Main registration files | Definition content |
| --- | --- | --- |
| Basic identity/location | `app/place-report/fields-basic-location.php` | Name, type, description, coordinates, address/location, land type, and manager. |
| Site, vehicle, road, base amenities | `app/place-report/fields-site.php` | Site identity/dimensions/fit, road facts, shared legacy amenity keys, and Site amenity facts. |
| Connectivity, sensory, safety, experience | `app/place-report/fields-experience.php`, `app/place-report/fields-rules-services.php` | Ratings, observations, permissions/rules, nearby services, recommendations, and notes. |
| Summaries and guidance | `app/place-report/fields-summaries.php` | Access/sensory/moderator summaries and a large help-text overlay for existing fields. |
| Official/source context | `app/place-report/fields-source-context.php` | Official accessibility, facility map, and activities. |
| Road detail | `app/place-report/fields-road-conditions.php`, `fields-road-access-additions.php`, `fields-road-seasonal.php`, `fields-road-restrictions.php` | Road condition, selected hazards, dimensional/access limits, seasons, gates, permits, and URLs. |
| Seasons | `app/place-report/fields-seasons.php` | Recurring season dates and ordering changes. |
| Permission flows | `app/place-report/flow-permissions.php`, `app/place-report/fields-place-overnight.php` | Permission value semantics, permit/conditional details, overnight modes, and permit links. |
| Shared facilities | `app/place-report/fields-place-shared-amenities.php` | Place/Area facilities, Site equipment separation, labels, types, and accessibility follow-ups. |
| Rules and operating status | `app/place-report/fields-place-rules.php`, `app/place-report/fields-place-operating.php`, `app/place-report/flow-conditions.php` | Rule questions, generator chains, operating hours, gates, and closure observations. |

## 3. Field-definition structure

Every call to the registry `$add` closure establishes these base properties:

| Logical property | Current implementation |
| --- | --- |
| Stable key | The associative registry key and the field's `key`; Area/Site JSON rows and historical answer records use this identity. |
| Label | Initially passed to `$add`; may be replaced by flow, rule, operating, Site, or cleanup metadata passes. |
| Description/help | Usually `help`, with additional `placeholder`, `summary`, `search_placeholder`, `low`, `high`, and `rows` presentation metadata. |
| Input type | `type`; final supported types checked by `app/place-report/diagnostics.php` are `tri`, `checkbox`, `rating`, `permission`, `number`, `select`, `multiselect`, `text`, `textarea`, `date`, and `url`. Final checkbox registrations are converted to `tri` by `fields.php`. |
| Choices | `options` on select/multiselect fields, implicit `0/1` for tri-state Yes/No, implicit `1`–`5` for ratings, or shared catalogues from `definitions.php` and flow modules. |
| Initial group | `section`, supplemented or replaced for display by `display_section`, `subsection`, `layout_section`, `layout_group`, `flow_group`, and approved-question group metadata. |
| Completion/scoring | `counts_toward_completion`, `completion_group`, `min_characters`, and `points_categories`. Completion and Scout points are separate systems. |
| Applicability | `applicable_if`, built from field-local rules and later passes. |
| Persistence | `storage`, later translated to a published Place table/column or used as a nested submission/history path. |
| Scope | Added later as `report_scopes`, `report_scope_group`, `scope_inheritance`, and `scope_definition_explicit`. |
| Display status | `derived`, `hide_public`, `approved_question`, compatibility layout, and related flags. |

Definitions are intentionally mutable during assembly. Examples include:

- `app/place-report/flow-permissions.php` changes several existing rule fields to select questions with `No`, `Yes`, `Permit required`, and `Conditional` options.
- `app/place-report/flow-access-options.php` changes high-clearance and 4WD fields from Yes/No to `No`, `Recommended`, and `Required` while preserving keys and storage.
- `app/place-report/flow-site-selection.php`, `flow-site-parking.php`, `flow-site-equipment.php`, `flow-site-accessibility.php`, `flow-site-experience.php`, and `flow-site-notes.php` replace labels/help and sometimes applicability after base registration.
- `app/place-report/flow-form-cleanup.php` makes compatibility questions derived/hidden or removes them from completion while keeping their historical identity.

The final field contract does not expose a general per-field `visibility` value such as Public/Community, or general `status` and `version` properties described by `docs/SCOUT_REPORT_SPEC.md`. `hide_public` exists for private moderator notes and compatibility fields, and access is also enforced outside the field registry. This is a confirmed metadata gap for later reconciliation, not a change made here.

## 4. Group and ordering architecture

There are four overlapping grouping systems:

1. `llama_place_report_sections()` in `definitions.php` is the older broad section catalogue used by scoring, locked previews, and some adapters.
2. `llama_place_report_approved_question_groups()` in `flow-layout.php` is the approved questionnaire membership and order used by public/read-only grouping and final canonical order.
3. `llama_place_report_layout_groups()` plus `llama_place_report_layout_assignments()` define the editable `getting_there`, `place`, and `site` form hierarchy.
4. `llama_place_report_flow_metadata()` adds small semantic flows such as camping, booking, arrival, generator, and Site hookups.

`llama_place_report_reorder_layout_fields()` sorts explicit layout assignments by approved-question rank, appends unassigned fields in their existing relative order, and retains compatibility/hidden fields at the end. Consequently, approved order, form layout, and legacy section order are related but not identical concepts.

Individual consumers can still regroup:

- `partials/place-report/form.php` renders `layout_section`/`layout_group` and moves unmapped or compatibility fields into a `legacy_review` presentation group.
- `partials/place-report/read-only.php` projects the approved groups and filters `hide_public` fields.
- `admin/moderate-update.php` groups changes by approved-question group.
- `account/update-place.php` serializes the canonical layout metadata for client-side scoped target rendering and has its own `legacy_review` fallback.

These are **intentional presentation adapters**, but they make the approved inventory, active form layout, and interface fallbacks all preservation-critical. `flow-layout.php` also contains `check_in_required` twice in the `place:reservations_pricing` assignment. The set-based reorder prevents a second rendered field, but the repeated key is confirmed list drift to reconcile later.

## 5. Scope and ownership architecture

`app/place-report/flow-scopes.php` is the declared exclusive owner of semantic scope. It assigns every final field to one of four groups:

| Scope group | Writable scopes | Inheritance behavior |
| --- | --- | --- |
| `place` | Place | Place only. |
| `place_area` | Place, Area | A Site may inherit the nearest recorded value but cannot write a Site override. |
| `site` | Site | No Place/Area inheritance. |
| `all` | Place, Area, Site | Nearest recorded value wins. |

The explicit inventory in `llama_place_report_scope_overrides()` is applied after presentation metadata. A conservative section-based fallback exists for future unclassified fields, and `llama_place_report_unclassified_scope_fields()` exposes fields that rely on it.

`app/place-report/report-targets.php` enumerates a Place, mapped Areas, mapped Sites, and canonical campsite records while deduplicating a mapped Site associated with a canonical campsite. It verifies incoming targets against a fresh Place-scoped read. `app/place-report/scoped-answers.php` filters stored rows against the current allowed scope and resolves Place → parent Area → Site precedence. Explicit No, Unknown, zero, null, and empty lists stop inheritance; only absence inherits. `app/place-report/target-model.php` overlays imported campsite baseline facts beneath direct Site overrides.

The current registry has no `resource` scope or Resource target in these services, although the specification includes Resource ownership. This is a confirmed unresolved ownership capability for later reconciliation. Special workflow metadata also exists outside ordinary scope, including submission-only visit/reviewer context, derived compatibility answers, closure evidence, and imported Site baseline facts.

## 6. Answer choices and answer states

### Choices and types

- Shared choice catalogues live in `app/place-report/definitions.php`.
- Many select and multiselect choices are field-local in the relevant `fields-*.php` file.
- Permission choices are normalized by `app/place-report/flow-permissions.php`.
- Vehicle-access choices are normalized by `app/place-report/flow-access-options.php`.
- Rating questions use an implicit integer range of 1–5 with field-specific `low` and `high` labels.
- Tri-state questions accept explicit `0`/No and `1`/Yes, with Unknown allowed only when `allow_unknown` is true.

### Generic states actually implemented

`app/place-report/answers.php` and `runtime.php` expose three generic states:

| State | Representation and meaning |
| --- | --- |
| `answered` | A valid value. Explicit No/false/zero is answered. An explicit empty multiselect is also answered and stops scoped inheritance. |
| `unknown` | Transport token `__LLAMA_UNKNOWN__`; for published Place facts the value is nullable and the field key is retained in `places.place_report_answer_state`; scoped answers store the token in JSON. |
| `unanswered` | Missing, null, empty string, or transport token `__LLAMA_UNANSWERED__`; omission from a partial update is normally left unchanged. |

“Not applicable” is calculated by `applicable_if`; it is not a stored generic answer state. “Not tested” is not generic either: for example, connectivity exposes `none` / “None Tested” as a question-specific multiselect choice. Some select fields also contain an option whose literal value is `unknown`; that is an answered question-specific option, distinct from the generic Unknown state.

There is no generic persisted state for “intentionally withdrawn.” `llama_scoped_report_clear_override()` deletes a scoped row so inheritance resumes. Published Place clearing and legacy `__NULL__` handling use null/unanswered semantics. V3 must preserve these distinctions until intentional clearing and historical interpretation are audited.

## 7. Applicability, dependencies, validation, and permissions

### Applicability and dependency model

Rules are arrays in `applicable_if`. The server evaluator in `app/place-report/runtime.php` supports:

- Leaf operators: `equals`, `not_equals`, `in`, `not_in`, `truthy`, `falsy`, and `answered`.
- Nested `any` and `all` groups.
- Recursive parent applicability, preventing a stored answer on an inapplicable parent from activating children.

Sources of conditions include field-local definitions, Place-type profiles in `app/place-report/applicability/`, overnight profiles, road hazard selection, permission expansion, generator chains, operating hours, gate schedules, pets/leash logic, tent-pad dimensions, hookups, Site WiFi, and facility accessibility.

Examples confirmed in the final assembly path include:

- `overnight_camping_allowed` enables overnight modes, reservation/membership/check-in questions, nighttime observations, and related experience questions.
- `site_wifi_rating` depends on shared `amenity_wifi = Yes`.
- `tent_pad = Yes` enables tent-pad dimensions.
- `place_open_24_hours = No` enables opening/closing times.
- Generator prohibition, quiet hours, and run-time limits form a multi-level chain.
- Place `type` profiles include or exclude road, camping, recreation, facility, and parking-specific questions.

`app/place-report/applicability/site.php` defines a separate Site applicability pass, but no repository caller or loader reference was found. Meanwhile, later Site “metadata” modules such as `flow-site-selection.php`, `flow-site-equipment.php`, and `flow-site-parking.php` replace or clear `applicable_if` after the main applicability pass. This is **Needs investigation** and a **suspected override risk**: the final registry remains canonical, but behavior ownership is distributed beyond the named applicability orchestrator.

### Validation

`app/place-report/answers.php` validates allowed answer states, list options, ratings, permission values, finite numbers and field bounds, integer steps, select choices, dates, recurring month/day values, HTTP(S) URLs, and maximum text length. `runtime.php` adds cross-field and submission-minimum checks. Scoped writes call the same parser through `llama_place_report_scoped_value()`.

Frontend code mirrors some rules for immediate display/completion. `partials/place-report/form.php` serializes field metadata and `js/place-report-section-completion.js` reimplements the applicability operators for the browser. This is **intentional adapter/presentation logic**; server parsing remains authoritative, but the duplicated evaluator must remain synchronized.

### Permission-related field behavior

`flow-permissions.php` defines the meaning of permission answers; it does not authorize users. `scoped-answers.php`, `report-targets.php`, and `target-model.php` explicitly leave authentication/authorization to callers. Admin and community workflows enforce their own role, ownership, moderation, and CSRF checks.

The registry does not contain a general role/edit-permission matrix or Public/Community field classification. `partials/place/member-content.php` applies membership access around report content, and `hide_public` suppresses selected fields in the read-only renderer. A later permissions/interface audit must establish whether every consumer enforces the intended access policy consistently.

## 8. Persistence references

`llama_place_report_storage_definition()` in `app/place-report/fields.php` maps published Place paths as follows:

- Direct columns in `places` for a fixed identity/location/summary list.
- Daytime and nighttime sensory paths to `place_sensory` with a `period` discriminator.
- Prefix mappings to `place_details`, `place_amenities`, `place_connectivity`, `place_sensory_details`, `place_rules`, and `place_experience`.

`llama_place_report_published_definitions()` derives a storage-indexed projection and preserves the first registered definition if legacy keys share a path. `app/place-report/persistence.php` publishes only supplied registered Place keys inside a transaction, so omitted fields do not clear existing values. Explicit published Unknown state is maintained separately through `places.place_report_answer_state`.

Area/Site answers use `place_report_scoped_answers` through `app/place-report/scoped-answers.php`, keyed by Place, scope type, source, target ID, and canonical field key, with `value_json` holding the explicit value. Site source identity distinguishes map features from canonical campsite records. Imported campsite columns are translated into canonical keys by `app/place-report/campsite-baseline.php`, below direct scoped overrides.

Compatibility mirrors in `fields.php` write selected current answers to older storage paths. Mapped rate precedence is described separately by `app/place-report/flow-pricing.php`; detailed Area/Site pricing is not reduced to the Place-level `fee` field.

`app/place-report/fields-place-pricing.php` defines a `place_rate_cards_json` field, but no loader or caller was found for its registration function. It is **Needs investigation**, not part of the confirmed final registry path.

This audit records mapping references only. Table definitions, constraints, and historical relationships remain V3-004 work.

## 9. Duplicate or parallel definition locations

| Location | Classification | Confirmed behavior and risk |
| --- | --- | --- |
| `partials/place-report/form.php`, `partials/place-report/read-only.php` | Intentional adapter/presentation logic | Consume canonical fields but independently choose form/read-only group projection, legacy fallback, and `hide_public` filtering. |
| `account/update-place.php`, `app/place-update.php`, `app/community-contributions.php` | Intentional adapter plus legacy compatibility | Current update services derive definitions from the registry; legacy path inputs and `__NULL__` handling remain. |
| `app/admin-place-report.php`, `app/admin-place-sites.php`, `app/admin-place-areas.php` | Intentional adapter | Use canonical scopes, fields, parser, and scoped store for the shared Admin Place Report. |
| `js/place-report-section-completion.js` | Intentional adapter/presentation logic | Reimplements the PHP applicability operators for live completion using registry metadata serialized by the form. Drift is possible if one evaluator changes alone. |
| `js/place-report-form.js` | Suspected duplication | Repeats land-manager taxonomy lists and state/land/Place-type manager filtering that overlap `definitions.php`. The browser filtering purpose is valid, but the duplicated catalogue can drift. |
| `js/place-report-road-hazards.js` | Intentional adapter with suspected duplication | Repeats the road hazard key list and hiding behavior represented by `fields-road-conditions.php`; it also hydrates legacy severity answers. |
| `js/place-report-operating.js` + `admin/save-operating-report.php` | Suspected override | Repeat field lists, state tokens, choices, validation, and direct persistence for operating/closure fields after the shared Admin report save. |
| `js/place-report-place-rules.js` + `admin/save-place-rules-supplemental.php` | Suspected override | Repeat rule field lists and validation, then write `place_rules` directly. |
| `js/place-report-shared-amenities.js` + `admin/save-shared-amenities.php` | Suspected override | Repeat amenity field lists/options and write `place_amenities` directly. |
| `admin/place-campsites.php`, `js/admin/campsite-amenities.js` | Legacy compatibility / suspected duplication | A separate campsite editor contains hardcoded labels and options, including blank “Unknown” controls and legacy string values, outside the scoped-answer contract. |
| `app/ridb-normalization.php`, `app/place-report/campsite-baseline.php` | Intentional source adapter | RIDB has its own source-field catalogue; baseline code maps imported campsite facts into canonical report keys. These are not automatically duplicate Scout questions. |
| `scout-report-demo.php` | Needs investigation / presentation logic | A standalone demo hardcodes example labels and amenity presentation. It does not define the saved registry, but its visible wording can drift. |
| `partials/place-report/update-field.php` | Legacy compatibility, apparently unreferenced | Uses `__NULL__` while labeling it Unknown; the legacy adapter converts that token to unanswered. No PHP include/reference to this partial was found. Active use must be confirmed before calling it a live state-collapse defect. |

The three supplemental Admin endpoints normalize both canonical Unknown and Unanswered tokens to SQL null and do not call the canonical published-answer-state updater. That is a confirmed separate path with a **suspected answer-state override risk**. Whether current Admin sequencing causes observable loss requires V3-003 interface tracing; this audit does not execute or fix it.

## 10. Legacy compatibility mechanisms

- `fields.php` preserves original storage and marks converted checkbox questions with `legacy_checkbox` before changing their final type to `tri`.
- `llama_place_report_field_by_storage()` preserves the first definition for a shared legacy storage path.
- Five fields have `compatibility_mirror` mappings to older paths, with identity or recommendation transforms.
- `flow-site-selection.php` and `flow-form-cleanup.php` keep older suitability, driveway, and derived recommendation fields registered while moving them to compatibility layout, disabling completion/points, or hiding them publicly.
- `answers.php` adapts path-based `change_fields` / `field_value` submissions to canonical keys.
- `review.php` keeps unsupported pending storage paths visible to moderators instead of silently dropping them.
- `js/place-report-road-hazards.js` infers the new hazard selector from older severity answers for display.
- `campsite-baseline.php` translates imported/legacy campsite columns without replacing explicit scoped overrides.

These mechanisms are preservation-critical until historical data and every active interface are audited.

## 11. Suspected drift and risks

1. **Definition ownership is distributed.** Base field files do not show the final label, type, choices, applicability, grouping, scope, or display status. Any future tooling must consume the finalized registry rather than parse one registration file.
2. **Visibility metadata is incomplete.** The final registry lacks the specification's general Public/Community classification and relies on `hide_public` plus external access gates.
3. **Resource ownership is absent.** Current scope and target services support Place, Area, and Site only.
4. **Site applicability has competing locations.** `app/place-report/applicability/site.php` is unwired, while later flow modules mutate conditions after the applicability orchestrator.
5. **Supplemental Admin saves may bypass canonical state/persistence behavior.** They repeat validation and collapse both generic tokens to null.
6. **Frontend taxonomy and condition mirrors can drift.** Manager catalogues, hazard keys, and applicability evaluation exist in JavaScript as well as PHP.
7. **Generic answer states do not cover the full specification vocabulary.** Not applicable and not tested are calculated or question-specific; intentional withdrawal is not a first-class generic state.
8. **Legacy and approved inventories coexist.** A registered field may be active, additional, derived, hidden, or compatibility-only. Consumers that filter by only one flag can disagree.
9. **Unwired pricing definition.** `fields-place-pricing.php` is present but not assembled into the registry.
10. **Ordering list drift exists.** `check_in_required` is duplicated in one layout assignment even though the final set-based reorder prevents duplication in output.

These findings are risks or discrepancies, not proof that production data is corrupt or that an interface is currently failing.

## 12. Questions requiring later audits

- Which registered fields are active in each Add Place, Add Site, update, moderation, Admin, public, history, and comparison surface? This belongs to V3-003.
- Do the supplemental Admin save bridges overwrite canonical Unknown state in current request sequencing, or are they compatibility fallbacks for columns not yet handled elsewhere?
- Is `app/place-report/applicability/site.php` intentionally retired, or should it participate in final assembly?
- Is `fields-place-pricing.php` intentionally dormant while normalized mapped rates are used elsewhere?
- Which legacy templates and the standalone demo remain reachable in deployed routing?
- How do the current published paths, historical reports, scoped rows, and schema snapshots relate at the database level? This belongs to V3-004.
- Which roles may edit each scope and which questions are Public versus Community? Permission enforcement requires the dedicated permissions/moderation audit.
- Which imported mappings intentionally differ from community question choices? External source normalization belongs to V3-006.
- Which keys, labels, choices, ownership, groups, and order differ from the approved Scout Report? Full reconciliation belongs to V3-007.

## 13. Preservation recommendations for V3

1. Treat the finalized `llama_place_report_fields()` result—not base registration calls—as the current contract.
2. Preserve stable field keys, storage paths, explicit answer state, compatibility mirrors, and scoped inheritance until data/history review establishes migration rules.
3. Keep semantic scope in one shared service and prevent layout changes from implicitly changing ownership.
4. Preserve the approved-question inventory separately from compatibility fields, while making all consumers use the same projection helpers.
5. Centralize shared answer choices, taxonomies, applicability evaluation, and published-state updates before removing any bridge.
6. Retain server-side parsing as authoritative; browser logic should consume serialized metadata and provide presentation feedback only.
7. Add explicit Public/Community, lifecycle status, and version behavior only through an approved implementation task with historical compatibility.
8. Preserve imported baseline provenance and direct scoped overrides as separate layers.
9. Reconcile supplemental/direct writers before changing or removing them; separation alone is not evidence that they are obsolete.
10. Use the existing read-only diagnostics in `app/place-report/diagnostics.php` as a starting point for future controlled verification, without querying production during audit tasks.

## Verification and limitations

- Required specifications and V3-001 findings were read from `v3-development` at `b35be100e45590ef5167ef1b2eee12dba29dd7a4`.
- All cited paths were inspected in the repository; path existence is verified again before delivery.
- Repository-wide searches covered report-related PHP and JavaScript definitions, hardcoded field lists, options, visibility, applicability, supplemental saves, compatibility keys, and scoped persistence.
- PHP CLI is not installed in this Codex environment, so `llama_place_report_contract_issues()` could not be executed. Findings are based on static code inspection and targeted scripts that did not load application configuration or data.
- No production database, private configuration, deployed page, external provider, or approved Scout Report PDF was accessed.
- No question inventory was reconciled line by line against the approved report; that remains V3-007.
- No application behavior, schema, specification, or earlier audit was changed.

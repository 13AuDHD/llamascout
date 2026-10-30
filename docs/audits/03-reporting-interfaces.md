# V3-003 Reporting Interface Audit

**Status:** Ready for review

**Audit date:** 2026-10-30

**Base branch:** `v3-development`

**Base commit:** `99d4752a0e2903d53db827a8ee31076ccdbfbaed`

## Purpose and boundaries

This audit traces the major Scout Report interfaces from rendering through parsing, moderation, publication, and historical display. It is a static repository review. It does not change application behavior, inspect production data, certify the deployed schema, or perform the V3-007 question-by-question reconciliation.

Findings are labeled **Confirmed** when the repository establishes the behavior. **Suspected** means the code exposes a plausible risk whose runtime effect depends on data, browser sequencing, schema state, or deployment details that were not exercised.

## 1. Interface architecture overview

There is one finalized PHP field contract, `llama_place_report_fields()`, but there is not one form or one persistence path for every workflow.

| Interface | Registry/rendering path | Save or publication path |
| --- | --- | --- |
| Add Place | `add-place.php` includes `partials/place-report/form.php` | `app/place-report/runtime.php` builds submission JSON; approval publishes through `app/moderation.php` and `app/place-report/persistence.php`. |
| Update entire Place | `account/update-place.php` includes the shared form | `app/place-update.php` stores changed published paths for moderation and applies only approved paths. |
| Update Area/Site | `account/update-place.php` clones shared field markup using canonical metadata in `js/place-update-report-target.js` | `app/place-report/scoped-update-review.php` stores a moderated `__scoped_report__` payload and writes only approved scoped keys. |
| Admin Place/Area/Site | `admin/place.php` includes the shared form; Admin JavaScript changes the active target | `app/admin-place-report.php`, `app/admin-place-sites.php`, `app/admin-place-areas.php`, and `app/admin-place-actions.php` save in one transaction, followed by active supplemental browser requests. |
| New Place moderation | `partials/place-report/read-only.php` in moderation mode | `moderation_approve_new_place()` calls canonical new-submission publication. |
| Update moderation | `app/place-report/review.php` projects canonical labels and values into Admin and Master Scout review cards | `llama_place_update_approve()` dispatches Place or scoped approval. |
| Published report | `partials/place/scout-report/index.php` and `partials/place-report/read-only.php` | Read-only Place data plus a separate Site-detail API and client adapter. |
| History/comparison | Historical submission/update JSON plus current canonical definitions | `app/compare-reports.php` reconstructs Place snapshots; `place-history.php` reuses the read-only renderer and `compare.php` uses comparison rows. |

The shared form owns the normal labels, controls, options, help, and layout metadata. Each interface still chooses its own target selection, filtering, data overlay, submission envelope, and presentation. Admin supplemental endpoints and the public Site browser are parallel adapters with material behavior of their own.

## 2. Add Place

### Entry, access, and form

`add-place.php` requires a verified email and `submit_place` contributor permission. It supports drafts and needs-changes resubmission, then includes `partials/place-report/form.php` with contributor mode. The partial loads the final field registry, canonical layout sections/groups, applicability metadata, answer-state controls, photo controls, and the common completion scripts. `js/place-report-form.js` supplies browser presentation and taxonomy behavior; `js/place-report-section-completion.js` evaluates the serialized rules for live completion.

The form exposes the complete canonical inventory in reconciliation mode. Server-side minimum submission requirements remain narrower: `llama_place_report_validate_new_place_minimum()` in `app/place-report/runtime.php` requires name, type, visit date, description, exact coordinates, and one current photo. Other questions may remain unanswered. Scout and Master Scout submissions also require their personal visit date.

### Parsing and moderation envelope

`llama_place_report_submit_new_place()` calls `llama_place_report_build_data()`, which uses the canonical parser and retains generic Unknown state in submission data. The pending record is stored as `place_submissions.submission_data`; staged photos are committed to the submission's private review path. Resubmission overlays a rebuilt report on the existing submission, preserving retained photos and resetting moderation state.

### Place and first Site creation

**Confirmed:** approval creates the Place and calls `llama_place_report_publish_submission()` through `app/moderation.php`. Place-writable keys are published by canonical storage path. If the submission contains any answered Site-only fields, `app/place-report/persistence.php` creates one active `place_campsites` record. Its label comes from `site_number` when supplied, otherwise `Reported Site`; the Site answers are written to `place_report_scoped_answers` using source `campsite_record`.

Add Place therefore creates a Place and at most one first Site from one report. It does not create Area geometry, Site geometry, or multiple Site records.

### Interface-specific behavior and risks

- **Intentional adapter:** draft and needs-changes data are translated back to form values by the shared answer adapters.
- **Confirmed:** canonical applicability affects completion, but reconciliation mode exposes the full question inventory for review.
- **Confirmed:** new Place publication separates Place keys from Site-only keys. Area-scoped creation is not part of this workflow.
- **Suspected risk:** because one submitted Site answer is enough to create a Site record, incomplete Site identity can produce the generic `Reported Site` record. This audit does not assess production frequency.

## 3. Add Site and Site reporting

### Existing Site update workflow

The member-facing workflow in `account/update-place.php` can report only against targets returned by `llama_report_targets()` in `app/place-report/report-targets.php`. The target selector lists the Place, mapped Areas, mapped Sites, and canonical campsite records. `llama_report_verified_target()` rechecks ownership before context is loaded or a submission is accepted.

`js/place-update-report-target.js` uses canonical field metadata serialized by PHP and clones control markup from the shared form. It filters by `report_scopes`, removes ordinary field names from scoped clones, and submits only changed answers through `scoped_answers_json`. The context endpoint calls `llama_place_report_target_model()` so the browser can distinguish direct values from inherited Place/Area or imported Site values.

### Creating another Site

**Confirmed:** no dedicated member-facing “add another Site” creation path was found. The update target selector accepts existing targets and the backend rejects an unverified target. Add Place can create the first Site during approval. Admin can create a later canonical Site from an exact GPS point through `admin/partials/place-map.php` and the `save-site-location` action in `app/admin-place-actions.php`. Importers and Site identity normalization can also establish Site records, but those are not member Add Site forms.

Scouts can propose a `camping_site` boundary through `map-contribute.php`, but `app/place-map-proposals.php` requires that proposal to name an existing active `place_campsites` record. Admin map moderation may approve and associate the geometry; it cannot use the proposal to invent the Site identity. This is an existing-Site mapping workflow, not Add Site.

After Admin creates a Site, the canonical Admin report selector can edit its scoped answers. An existing mapped Site can also be normalized to a canonical campsite identity by `app/place-site-identity.php` when Admin selects it.

### Imported baseline and scoped storage

`app/place-report/target-model.php` combines Place values, parent Area values, imported campsite baseline facts, and direct scoped overrides. Direct overrides win. `app/place-report/campsite-baseline.php` translates imported campsite columns into canonical field keys without turning the imported row into a second question registry.

**Confirmed:** a cleared scoped override deletes its row and restores inheritance. Explicit No, zero, empty multiselect, and the Unknown token are recorded values and stop inheritance.

## 4. Update This Place

### Access and target selection

`account/update-place.php` requires verified email, `submit_update` permission, and Complete Access for the selected Place because it pre-fills the full published report. Contributor-origin access is accepted through `user_has_place_complete_access()` even without global paid membership.

The target is transported as three fields: `report_target_scope`, `report_target_source`, and `report_target_id`. A needs-changes scoped submission locks the selector to its original target. The browser's target key is the same `scope:source:id` identity used by the server.

### Entire Place updates

The Place form uses `partials/place-report/form.php`. `app/place-update.php` derives its definitions from the canonical published definitions, expands presence markers, ignores derived and non-Place fields, parses the supplied fields, and stores only actual value or answer-state changes. Proposed and original values are keyed by published storage path; revision history separately retains proposed and original Unknown field keys.

**Confirmed partial-update behavior:** omitted fields are skipped by `llama_place_update_build_changes()`. On approval, `llama_place_update_approve()` validates only proposed paths against the original snapshot, writes only those paths through `llama_place_report_apply_published_field()`, and updates answer state only for those paths. Unrelated published answers are untouched.

Approval also rejects stale proposals when either the current value or its Unknown state no longer matches the submission-time original.

### Area and Site updates

The scoped browser adapter submits between one and 50 changed answers. `llama_scoped_update_build_payload()` verifies target ownership, field scope, value syntax, direct recorded value, and effective inherited/imported value. It stores the proposal under `__scoped_report__` in the normal update moderation table.

**Confirmed partial-update behavior:** only keys present in the scoped payload are written on approval. Before writing, `llama_scoped_update_approve()` verifies both the original direct answer and the original effective inherited/imported answer. It then calls `llama_scoped_report_save()` for each approved key. Other targets and other answers are untouched.

### Applicability and presentation

The entire Place form receives canonical applicability metadata. The scoped adapter deliberately exposes all eligible scoped questions in reconciliation mode and does not apply the dependency system to hide them. This is a **confirmed interface-specific presentation override**. Server parsing and scope checks remain canonical, but a scoped contributor can see questions the ordinary form would normally hide as inapplicable.

The adapter can render only supported canonical control types and depends on a matching control template existing in the shared Place form. It reports missing templates rather than inventing replacement markup.

## 5. Admin editing

### Shared report and direct save

`admin/place.php` requires Admin moderation access and includes the same shared form. `js/admin/place-report-save.js` sends a background `save-report` request. `app/admin-place-actions.php` wraps Place, selected Area, selected Site, bulk Site answers, photos, and audit records in one transaction.

`admin_place_save_shared_report()` in `app/admin-place-report.php` loads canonical published data, removes selected-Site fields from the Place payload, intersects input with Place-writable canonical fields, builds data with the common parser, and publishes only changed keys. This is a canonical direct-publish path; it does not pass through contribution moderation.

### Site and Area modules

`app/admin-place-sites.php` discovers editable Site questions from final registry scope and layout metadata, overlays imported baseline and direct scoped answers, and saves a JSON change set. `app/admin-place-areas.php` similarly discovers Area-writable fields and saves only submitted keys. Empty or Unanswered values clear an override; Unknown and explicit No remain explicit. `js/admin/place-report-area.js` and `js/admin/place-report-save.js` are presentation/payload adapters rather than independent question registries.

Admin can choose the Place, one Area, or one canonical Site. The Site selector replaces the rendered `site_number` input. Admin also has an explicit bulk Site operation: it applies only fields touched after enabling bulk mode and requires confirmation, with fill-empty versus overwrite behavior selected by the Admin.

### Admin applicability override

`js/admin/place-report-save.js` adds “Show normally inapplicable questions.” It changes visibility and editability without changing completion applicability. This is a confirmed, intentional Admin presentation override.

### Supplemental saves

The main background save dispatches `llama:admin-place-report-saved` only after the canonical request succeeds. Two active scripts listen to that event:

1. `js/place-report-operating.js`, loaded dynamically by `js/place-report-road-hazards.js`, sends changed operating/closure fields to `admin/save-operating-report.php`.
2. `js/place-report-shared-amenities.js`, also loaded dynamically by `js/place-report-road-hazards.js`, sends changed shared-facility fields to `admin/save-shared-amenities.php`.

Both endpoints write the same published tables represented by canonical storage. They are separate HTTP requests after the main transaction, repeat field lists and validation, and translate blank, Unknown, and Unanswered to SQL `NULL`. They do not call `llama_place_report_apply_published_answer_state()`.

**Confirmed overwrite path:** when a watched value is dirty, these bridges run alongside and after the canonical save and can rewrite the just-saved columns. **Confirmed answer-state mismatch risk:** an explicit Unknown can remain in `places.place_report_answer_state` while the supplemental endpoint independently writes `NULL`; conversely, the bridge cannot itself distinguish Unknown from Unanswered. Whether a particular production record is inconsistent requires data inspection outside this task.

`js/place-report-place-rules.js` and `admin/save-place-rules-supplemental.php` implement a third parallel rules save. No loader, script tag, or other reference to `js/place-report-place-rules.js` was found. It is therefore **confirmed present but not confirmed active** in the reviewed repository. The endpoint remains directly routable to Admin users and still bypasses the canonical answer-state updater if called.

`admin/place-campsites.php` and `js/admin/campsite-amenities.js` are a separate legacy campsite editor with hardcoded labels/options and older value conventions. They are not the shared Admin Place Report target workflow and remain a compatibility/drift surface.

## 6. Moderation

### New Place moderation

`admin/moderate-submission.php` loads submission JSON, canonical completion/point calculations, and `partials/place-report/read-only.php` in `moderation` mode. The read-only renderer uses the current canonical definitions, canonical answer-state/display helpers, and canonical layout grouping. It leaves unanswered questions visible for reconciliation.

Approval runs inside a transaction through `moderation_approve_new_place()` in `app/moderation.php`. It revalidates the minimum report, creates the Place, publishes canonical Place fields, creates the optional first Site, moves photos, records the contribution, awards points, and updates moderation status. Request-changes and rejection preserve review history without publishing data.

### Update moderation

`admin/moderate-update.php` calls `llama_place_report_review_changes()` from `app/place-report/review.php`. That adapter resolves canonical definitions, labels, approved groups, target labels, and Unknown-state display. Scoped updates are displayed as target-labeled canonical field changes. Approval calls the same `llama_place_update_approve()` path described above.

**Confirmed:** update moderation preserves explicit Unknown separately from a blank/unanswered original. The review UI labels before/after values through canonical display helpers. Stale Place and scoped proposals fail approval instead of overwriting newer data.

### Problem-report moderation

`admin/moderate-report.php` verifies and displays the same `scope/source/id` target identity, but a problem report is not a Scout Report answer proposal. Moderation changes the problem report's status and resolution notes and links the Admin to the target-aware Place editor. It does not automatically transform the report into canonical answers or publish report data.

## 7. Master Scout moderation

`account/master-moderation.php` exposes the queue only to an active Master Scout. `account/master-review.php` rejects self-moderation and supports new Place and update reviews.

New Place review reuses `partials/place-report/read-only.php` in moderation mode. Update review reuses `app/place-report/review.php`, including scoped target labels and canonical before/after formatting. `app/master-scout-moderation.php` delegates approvals to `moderation_approve_new_place()` or `llama_place_update_approve()`, so Admin and Master Scout approvals share publication, concurrency checks, answer-state handling, points, and contribution records. Master Scout review cannot choose Featured status; approved new Places publish as Active.

## 8. Published Scout Report

### Access and base data

`partials/place/member-content.php` renders the full report only when `$hasMemberAccess` is true. That includes paid/global Complete Access and contributor-origin Place access. Other viewers receive a locked preview built from broad legacy sections and field labels, not published answers.

`partials/place/scout-report/index.php` loads published Place data through `llama_place_report_data_from_published_place()` and `llama_place_report_published_answer_state()`, then includes the shared read-only renderer. Failure to read the answer-state migration falls back to no explicit Unknown fields to avoid a fatal page; this is a **confirmed compatibility fallback** that can visually collapse Unknown to unanswered when the answer-state store is unavailable.

### Grouping, filtering, and answer display

In public mode, `partials/place-report/read-only.php` uses `llama_place_report_approved_question_groups()` for group membership and sequence, canonical labels/options/display formatting, and excludes `hide_public` fields. It adds official RIDB contact/link data as presentation-only cards outside the Scout Report registry.

**Confirmed presentation override:** reconciliation mode currently assigns all group fields to the applicable/reported collection without calling the canonical applicability evaluator, despite preparing applicability input. It keeps unanswered questions visible as `Not provided`. As a result, public visibility follows approved-group membership and `hide_public`, not current question applicability. The calculated missing counters are also effectively zero because `answeredFields` is assigned the whole field set before counting.

### Place, Area, and Site selection

The server-rendered Place report deliberately resets non-Place scoped fields to unanswered. `js/place-campsites.js` later replaces Site-capable card values when a canonical campsite is selected. `api/place-campsite-detail.php` requires Complete Access, verifies a `campsite_record` target, resolves Place → Area → Site answers, overlays imported campsite baseline facts, and returns display-formatted inherited and override values.

The browser merges inherited values, legacy campsite facts, and direct overrides, inserts the displayed Site name as `site_number`, and updates the existing canonical cards. It independently determines whether a returned value is Unknown by comparing display text to `unknown`.

**Confirmed scope asymmetry:** the published selector/API path is based on canonical `place_campsites` records. The read-only report does not expose a comparable public Area selector, and the API lookup does not select a map-feature-only Site target. Those targets may participate in contributor/Admin reporting without a matching published Scout Report selection path until identity is normalized.

### Imported data and attribution

Imported campsite facts appear through the target model/baseline overlay. The API returns effective inherited and direct display values but does not return per-field provenance or source attribution for the Scout Report cards. The Site detail object retains some source fields for other campsite presentation, yet the report-card updater does not label whether a value came from Place, Area, imported baseline, or direct Site reporting. This is a **confirmed presentation limitation**, not evidence that source data is lost from its underlying imported tables.

## 9. History and comparison

### Historical report reconstruction

`app/compare-reports.php` starts with the first approved `place_submissions.submission_data` snapshot and applies approved `place_update_submissions.proposed_changes` in approval order. It maps published storage paths back to current canonical field keys to update Unknown state. `place-history.php` then renders a selected snapshot through the current `partials/place-report/read-only.php` in `historical-report` mode.

This preserves stable storage-path identity for Place-level updates, but labels, grouping, options, and `hide_public` behavior come from the current registry, not a versioned definition snapshot. Old values that are no longer current options rely on display/compatibility handling.

### Comparison interface

`compare.php` requires Complete Access and uses `partials/compare/report-table.php`. The comparison rows come from the separate `llama_compare_sections()` presentation registry rather than iterating the complete Scout Report registry. It therefore presents a curated comparison and can independently differ in row labels, grouping, or coverage.

### Scoped history limitation

**Confirmed inconsistency:** approved scoped updates are stored in `proposed_changes` as `__scoped_report__`. `llama_compare_report_versions()` treats every proposed key like a Place storage path and does not reconstruct `place_report_scoped_answers` by target. Such an update can appear in the report-version picker and public timeline, but its Area/Site values are not rendered as scoped historical state in the historical report or comparison table. The `changed_paths` marker also contains `__scoped_report__`, which does not match ordinary comparison row storage paths.

`app/place-history-timeline.php` is an activity timeline rather than a field snapshot system. It combines approved contributions, origin data, problem reports, verifications, and legacy field visits. Its contribution links can point to the report versions above, so the scoped reconstruction limitation carries into linked history views.

## 10. Place / Area / Site target flow

The common target identity is:

```text
scope (place|area|site) + source (place|map_feature|campsite_record) + integer id
```

The main flows are:

```text
Contributor update selector
  -> hidden scope/source/id
  -> llama_report_verified_target()
  -> moderated __scoped_report__ payload
  -> approval re-verifies target and original/effective values
  -> place_report_scoped_answers
```

```text
Admin target selector
  -> selected_area_id or selected_campsite_id
  -> target verification / Site identity normalization
  -> canonical scoped parser
  -> place_report_scoped_answers in the Admin save transaction
```

```text
Published Site selector
  -> canonical campsite id only
  -> Complete Access API
  -> target model plus imported baseline
  -> client replacement of Site-capable report cards
```

**Confirmed:** backend contributor and Admin saves verify Place ownership. **Confirmed:** public selection supports a narrower target set than reporting. **Suspected risk:** user-visible identity can change when a mapped Site is normalized to a canonical campsite record; merge code exists in `app/place-site-identity.php`, but historical references across every old record were not audited here.

## 11. Answer-state flow

| State/action | Add Place / Place update | Scoped contributor/Admin | Published/read-only |
| --- | --- | --- | --- |
| Explicit Yes/No | Parsed as answered; No remains `0` | Stored as explicit scoped value | Displayed as Yes/No. |
| Unknown | Token retained in submission/history; Place value nullable plus key in published answer state | Token stored as scoped JSON value | Canonical PHP displays Unknown; public Site JavaScript infers it from display text. |
| Unanswered/omitted | Omitted update key leaves published value untouched | Omitted key leaves override untouched | Shown as `Not provided` in reconciliation mode. |
| Clear scoped override | Not applicable to Place inheritance | Empty/Unanswered deletes direct row and restores inheritance | Effective inherited value reappears. |
| Not applicable | Calculated from rules, not persisted | Scoped contributor UI currently exposes eligible questions without dependency hiding | Public reconciliation view currently does not filter it. |

The canonical moderated paths preserve Unknown separately from omission. The active Admin supplemental endpoints are the confirmed exception: they collapse Unknown and Unanswered to SQL `NULL` and do not update the answer-state list. The public Site adapter also works with display strings rather than structured state, so it can distinguish only the current English `Unknown` representation.

## 12. Supplemental save paths

| Path | Active call evidence | Runs with canonical save | Canonical-state effect |
| --- | --- | --- | --- |
| `admin/save-operating-report.php` | Dynamically loaded by `js/place-report-road-hazards.js`; dirty listener in `js/place-report-operating.js` | After successful main Admin save | Direct SQL; Unknown and Unanswered become null; answer-state list is not updated. |
| `admin/save-shared-amenities.php` | Dynamically loaded by `js/place-report-road-hazards.js`; dirty listener in `js/place-report-shared-amenities.js` | After successful main Admin save | Direct SQL; Unknown and Unanswered become null; answer-state list is not updated. |
| `admin/save-place-rules-supplemental.php` | Endpoint and listener exist, but no loader/reference for `js/place-report-place-rules.js` was found | Not confirmed active | Direct SQL with the same state-collapse behavior if invoked. |
| Legacy campsite editor | Separate Admin route and JavaScript | Separate workflow | Older hardcoded values do not use the current target form contract. |

The two active requests are not part of the main Admin transaction. A supplemental failure occurs after the canonical request reports success and is surfaced only by its own status/error handling. This can leave canonical and supplemental writes partially successful relative to one button click.

## 13. Interface-specific overrides

- `partials/place-report/form.php` uses active layout sections/groups and a `legacy_review` fallback.
- `account/update-place.php` and `js/place-update-report-target.js` expose all scoped eligible questions, clone shared controls, and submit a JSON change set.
- `js/admin/place-report-save.js` replaces `site_number` with the stored Site selector, enables an Admin applicability override, and owns explicit bulk Site changes.
- `partials/place-report/read-only.php` uses approved groups for public mode, current layout groups for moderation/history, and keeps unanswered questions visible.
- `partials/place/member-content.php` builds the locked preview from older broad section metadata.
- `js/place-campsites.js` overlays public Site values in the browser and supplies Site identity from the campsite catalogue.
- `partials/compare/report-table.php` uses a separate curated comparison-row registry.
- Official RIDB contacts/links and several legacy campsite summaries are derived presentation cards, not canonical Scout Report questions.

These adapters are not defects merely because they are separate. They become drift risks where they redefine applicability, state semantics, labels/options, or persistence rather than only presenting the shared contract.

## 14. Confirmed inconsistencies

1. **Admin supplemental state handling differs from canonical handling.** Active operating and shared-amenity requests collapse Unknown and Unanswered and run after canonical publication.
2. **Public applicability is bypassed in reconciliation mode.** Approved-group fields remain visible regardless of the prepared canonical applicability input, and missing counts do not represent actual answered fields.
3. **Published target coverage is narrower than edit target coverage.** Area and map-feature-only Site answers have no equivalent public selector in the reviewed report path.
4. **Historical reconstruction is Place-only.** Scoped approved updates are listed as versions but their target answers are not reconstructed or rendered.
5. **Public Site state/provenance is flattened for display.** The client receives formatted inherited/direct maps and does not display per-answer source or structured state.
6. **There is no member Add Site creation workflow for an existing Place.** Contributor Site reporting requires a pre-existing verified target; later Site creation is currently an Admin/import/identity workflow.
7. **The rules supplemental bridge is orphaned from normal loading.** Its endpoint and JavaScript exist, but no active repository reference loads that JavaScript.

## 15. Suspected risks

- Current reconciliation overrides may mask real applicability drift because the same questions are visible even when dependencies would normally hide them.
- A successful main Admin response followed by a failed supplemental request can leave one Save action only partly reflected across the duplicate paths.
- Current registry labels and grouping can reinterpret old historical reports because definition metadata is not versioned with each snapshot.
- Canonical Site identity normalization may make old map-feature identifiers difficult to follow across history unless all references are reconciled.
- Legacy campsite editing can diverge from current scoped values when both interfaces remain in operational use.
- A generic first Site created from sparse Add Place answers may later require identity cleanup before it matches a mapped/imported Site.

## 16. Preservation requirements for V3

1. Keep stable canonical field keys and published storage mappings while interfaces are consolidated.
2. Preserve the distinction among omission, Unanswered, Unknown, explicit No, and clearing a scoped override.
3. Preserve `scope/source/id` as a verified target identity, including the distinction between map features and canonical campsite records until migration is explicit.
4. Preserve partial-update concurrency checks against both direct and inherited/imported values.
5. Preserve contributor Complete Access, Admin authorization, Master Scout anti-self-review, CSRF, and moderation gates.
6. Preserve imported Site baselines beneath direct Area/Site answers and expose provenance if presentation is redesigned.
7. Preserve current contribution, review, photo, points, and audit records when replacing compatibility bridges.
8. Treat Add Place, existing-target reporting, Site creation, public selection, and historical reconstruction as distinct capabilities that need an intentional unified contract.
9. Remove a supplemental path only after its fields are proven to persist through the canonical transaction with equivalent validation and answer-state behavior.
10. Version or snapshot enough definition metadata to render historical reports without silently applying incompatible future labels, choices, scope, or grouping.

## 17. Questions for later audits

- Which live schema versions and historical rows contain mismatches between nullable values and `places.place_report_answer_state`? This belongs to V3-004: Database and Historical Records Audit.
- How many scoped contributions currently appear as historical versions without reconstructable Area/Site state?
- Are map-feature-only Sites and Area answers intended to be publicly selectable, or must every public Site first become a canonical campsite record?
- Is the separate legacy campsite editor still used operationally, and which values does it own after the canonical scoped editor saves?
- Should Add Site become a moderated contributor workflow, and what identity/location evidence is required before a new target exists?
- Should reconciliation mode remain enabled in production after the approved question inventory is reconciled?
- Which current labels, choices, and ordering differ from the approved Scout Report? That full comparison remains V3-007 work.
- Should per-answer inherited/imported/direct provenance and source attribution be visible in the public Scout Report?

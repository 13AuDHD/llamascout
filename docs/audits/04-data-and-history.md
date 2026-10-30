# V3-004: Database and Historical Records Audit

**Audit date:** 2026-10-29

**Repository base:** `v3-development` at `b068871dcbf5f38f89e72ada657841af47b002e9`

**Method:** Static repository inspection only. No database connection, schema creation, migration, or production-data access was performed.

## 1. Data architecture overview

The implemented Scout Report system does not have one report table. It combines several stores with different lifecycles:

| Concept | Current repository-backed store | Lifecycle |
|---|---|---|
| Current published Place state | `places` plus the `place_*` detail tables | Mutable canonical state |
| Current Area/Site overrides | `place_report_scoped_answers` | Mutable per-target overrides |
| Imported Site baseline | `place_campsites`, campsite fact/feature tables, and source records | Refreshable source-backed baseline |
| New Place submission | `place_submissions` JSON plus moderation columns | Submitted snapshot, later reviewed |
| Proposed Place/Area/Site update | `place_update_submissions` JSON plus moderation columns | Proposed change set, later reviewed |
| Approved contribution provenance | `place_contributions` | Durable activity/provenance row |
| Public history | A projection assembled by `app/place-history-timeline.php` | Presentation over several stores |
| Comparison versions | Reconstructed by `app/compare-reports.php` | Place-level derived snapshots |
| Problem report evidence | `place_reports` and `place_report_images` | Separate problem-moderation workflow |

These stores must remain distinct. A submitted report is not the current published state; a contribution row is not an answer snapshot; an imported baseline is not a Scout override; and the public timeline is not an immutable report ledger.

`app/place-report/diagnostics.php` additionally describes an append-only `scout_report_*` contract. Repository searches found no current reader, writer, or DDL for those tables outside that diagnostic. Their names and expected constraints are therefore a **diagnostic requirement / needs investigation**, not evidence that this architecture is deployed or active.

## 2. Place storage

`app/place-report/fields.php` (`llama_place_report_storage_definition()`) converts canonical storage paths into published tables and columns. `app/place-report/persistence.php` reads the resulting definitions, and `app/place-report/runtime.php` applies individual published fields.

| Storage family | Table | Repository-confirmed use |
|---|---|---|
| Place identity, summary, location, management | `places` | Direct columns including name, type, description, coordinates, elevation, address/region, land manager/type, and summaries |
| General details and access | `place_details` | `details.*` paths |
| Shared Place amenities | `place_amenities` | `amenities.*` paths |
| Connectivity | `place_connectivity` | `connectivity.*` paths |
| Day/night observations | `place_sensory` | `sensory.daytime.*` and `sensory.nighttime.*`, distinguished by `period` |
| Other sensory detail | `place_sensory_details` | `sensory.details.*` paths |
| Rules and operating information | `place_rules` | `rules.*` paths |
| Experience observations/recommendations | `place_experience` | `experience.*` paths |

The read/write code addresses `places` by `id`, each non-sensory child table by `place_id`, and `place_sensory` by `place_id` plus `period`. This confirms the application's one-row-per-Place assumption for the detail, amenity, connectivity, sensory-detail, rule, and experience tables, and its repeated daytime/nighttime model for `place_sensory`. The repository has no checked-in DDL proving unique keys for those assumptions.

Canonical paths can share or mirror storage:

- `road_overall_difficulty` uses the `details.road_difficulty` identity mapping.
- `best_months` uses `rules.recommended_travel_season`.
- three experience observations mirror recommendation columns through compatibility transforms.
- latitude/longitude publication also maintains rounded public coordinates.
- multiselect values are encoded as JSON for legacy text/JSON columns.

`llama_place_report_published_definitions()` keeps the first definition for a duplicate storage path. This protects one physical column from being independently treated as two published facts, but it also makes registry ordering relevant to compatibility mappings.

## 3. Area storage

Areas are active `place_map_features` rows whose feature type is an Area type accepted by `app/place-report/report-targets.php`. Their geometry and identity remain in the map-feature system. Scout Report answers for an Area are rows in `place_report_scoped_answers` with:

- `place_id`
- `scope_type = area`
- `scope_source = map_feature`
- `scope_id = place_map_features.id`
- canonical `field_key`
- JSON `value_json`

An Area can also be the parent inherited by a Site. `place_campsite_area_links` connects a canonical campsite to an Area, while mapped Site detail can carry `camping_area_feature_id`. The reporting target service normalizes these into `parent_area_id` for answer resolution.

No separate Area answer-state column or append-only Area report table was found.

## 4. Site storage

Two Site identities are supported by `app/place-report/report-targets.php`:

1. A canonical `place_campsites` row, represented as source `campsite_record`.
2. A historical/map-only `place_map_features` camping Site, represented as source `map_feature`.

Canonical Site identity and structured/imported facts live in `place_campsites` and the related campsite fact, feature, image, and relationship tables used by `app/place-campsites.php`, `app/place-campsite-media.php`, and the import services. Site geometry remains in `place_map_features`; `place_campsites.map_feature_id` associates identity with geometry.

Direct Scout/Admin answers are not written back into imported fact columns. They use `place_report_scoped_answers` with the selected Site source and ID. `app/place-report/campsite-baseline.php` translates imported campsite data into canonical answer keys, and `app/place-report/target-model.php` overlays direct scoped answers on that baseline.

## 5. Target identity model

The reporting identity is a triple: `scope`, `source`, and `id`, always constrained to a Place.

| Target | Source | ID |
|---|---|---|
| Place | `place` | Place ID |
| Area | `map_feature` | Area feature ID |
| Canonical Site | `campsite_record` | `place_campsites.id` |
| Map-only Site | `map_feature` | Site feature ID |

`app/place-site-identity.php` converts a map-only Site into a canonical campsite when a persistence workflow needs a campsite owner. It tries an existing exact association, the internal external ID `mapped-site:{featureId}`, or an unambiguous exact Site code before creating a record. It then calls `llama_scoped_report_merge_site_identity()`.

That merge copies a map-feature override to the canonical campsite when no canonical value exists, removes an equal duplicate, and aborts on a conflicting value. Once successful, it deletes the old map-feature scoped rows and associates the map geometry with the canonical campsite.

**Historical-reference risk:** pending scoped update payloads retain the source and ID captured at submission. The identity merge rewrites current scoped rows, but no code was found that rewrites pending or historical `__scoped_report__` payloads. A later approval can therefore refer to the earlier identity. Whether this has affected stored records requires data inspection and remains **needs investigation**.

## 6. Scoped answer model

`app/place-report/scoped-answers.php` owns scoped persistence and resolution.

- `llama_scoped_report_save()` validates Place ownership, target scope, and canonical field scope, JSON-encodes the value, and uses `INSERT ... ON DUPLICATE KEY UPDATE`.
- `llama_scoped_report_clear_override()` deletes the row. Clear means “resume inheritance,” not “store blank.”
- resolution composes Place values, optional parent-Area values, and selected-Site values in that order; the nearest recorded answer wins.
- a canonical campsite's imported baseline is composed before its direct Site overrides, so a direct override wins over imported data.

The upsert requires a unique identity across `(place_id, scope_type, scope_source, scope_id, field_key)`. `app/place-report/diagnostics.php` explicitly checks for that unique index. The checked-in repository does not define it, so its live presence is unverified.

## 7. Answer-state storage

The current implementation distinguishes states by store:

| State | Published Place | Scoped Area/Site |
|---|---|---|
| Answered | Physical fact column contains value | Scoped row contains JSON value |
| Explicit No | Answered false/No value | Scoped row contains false/No value |
| Unknown | Fact may be null and canonical key appears in `places.place_report_answer_state` JSON | Scoped row contains the literal canonical Unknown token |
| Unanswered | No value and key absent from Unknown list | No scoped row |
| Cleared override | Not a distinct Place operation | Scoped row is deleted; inheritance resumes |

`app/place-report/runtime.php` updates the published Unknown-key array only for submitted paths and preserves unrelated keys. The value and state metadata are separate writes within the publication transaction, so all canonical writers must keep them aligned.

Two confirmed inconsistency surfaces remain from V3-003:

- `admin/save-operating-report.php` and `admin/save-shared-amenities.php` perform active supplemental direct writes after the canonical Admin save.
- those paths collapse Unknown and Unanswered into SQL null without updating `places.place_report_answer_state`.

They can therefore leave a null physical value paired with stale or missing state metadata. The unreferenced rules supplemental bridge is a similar direct persistence surface, but active invocation was not confirmed.

Scoped JSON can also store explicit `null`; the scoped service treats any existing row as recorded. Canonical contributor parsing generally avoids turning omission into such a row, while Admin clear explicitly deletes. Direct callers must preserve that distinction.

## 8. Submission history

New Place reports use `place_submissions`.

- `submission_data` contains the full nested canonical submission, including `_answer_state`, visit information, contributor notes, and submitted photo records.
- `user_id` and `role_at_submission` preserve contributor identity and authority at submission.
- `submitted_at` is distinct from the visit date inside the JSON.
- moderation records `status`, `reviewed_at`, `reviewed_by`, and `review_notes`.
- approval links `place_id` but retains the submission JSON.

Resubmission replaces the current `submission_data` and submission timestamp. `app/place-submission-history.php` uses `revision_history` to retain change-request snapshots, field state/value differences, photo additions/removals, resubmission events, and terminal review events. `admin/moderate-submission.php` invokes the resubmission capture when the moderation view detects the relevant pending state.

This is a review history around a mutable current submission row, not a normalized immutable answer ledger.

## 9. Update history

Place, Area, and Site updates use `place_update_submissions`.

For Place updates:

- `proposed_changes` and `original_values` are keyed by published storage path.
- `visited_at`, `user_id`, `role_at_submission`, contributor notes, and photo JSON are stored separately.
- moderation stores status, reviewer, notes, review time, contribution ID, and awarded points.
- `revision_history` records proposed/original maps, Unknown-key arrays, photo counts, visit date, notes, resubmissions, and review events.

Approval in `app/moderation.php` and `admin/moderate-update.php` compares both the original value and original Unknown state with current published state before writing. That concurrency guard prevents silent approval over a changed fact.

At the top level, a proposed null is not self-describing. The corresponding revision-history Unknown arrays are required to distinguish Unknown from another null-like state. Consumers that read only `proposed_changes` can lose that distinction.

## 10. Moderation records

Moderation evidence is distributed rather than centralized:

- submission/update rows retain current status, review actor, review time, and notes;
- each submission type stores JSON revision/review events;
- `place_contributions` records the approved activity, approver, dates, role, fields, points, and linkage;
- Admin moderation paths also call `admin_users_audit()`;
- new Place approval writes `place_provenance` and an initial `place_status_history` event.

No current implementation writes the diagnostic-only `scout_report_moderation_events` table. Moderation history therefore has to be reconstructed from the workflow-specific stores above.

## 11. Contribution history

`app/place-contributions.php` explicitly describes `place_contributions` as permanent provenance that complements, rather than replaces, submissions, the Scout activity ledger, and current Place state.

Approved contribution rows preserve the Place, contributor, contribution type/status, role at the time, visit/submission/approval times, moderator, awarded points, changed-field list, and notes. New Place contributions can link through `submission_id`; approved updates link back from `place_update_submissions.contribution_id`.

Scoped changed fields use strings in the form `scope:source:id:key`. This preserves target identity and affected keys but not answer values. The richer generic contribution service supports a scoring snapshot; the moderation helper reviewed in this audit does not populate one.

`account/delete-account.php` deletes unpublished new-Place submissions and non-approved updates. Approved submission/update and contribution records are deliberately outside those deletion queries, supporting historical retention, although the later user-anonymization behavior and live foreign keys require a dedicated privacy/data review.

## 12. Historical reconstruction

`app/compare-reports.php` constructs Place-level versions by starting with the first approved Place submission and applying approved update `proposed_changes` in order. `place-history.php` and `compare.php` present those derived versions. This yields useful Place snapshots but has limits:

- definitions, labels, and group metadata come from the current registry, not a definition snapshot captured with the historical report;
- updates are storage-path deltas rather than complete reports;
- the reconstruction is Place-level and does not materialize scoped target state;
- contribution rows alone cannot recreate answers;
- source files/photos may have been promoted or removed after moderation even when their JSON references remain historical evidence.

`app/place-history-timeline.php` separately builds a public activity stream from contributions, check-ins, resolved problems, verification activity, and legacy provenance. It is an event presentation and does not reconstruct report answers.

## 13. Scoped-history gap

`app/place-report/scoped-update-review.php` stores a scoped update inside `proposed_changes` as:

```text
__scoped_report__: scope, source, id, target_label, answers
```

Its `original_values` entry records, for each changed key, whether a direct answer existed, its direct value, and the prior effective value after inheritance/import composition. The target and the proposed Unknown token are therefore preserved for the changed keys.

Approval mutates `place_report_scoped_answers`; that current table has only the latest override and `updated_at`, with no contribution/report foreign key or prior-value ledger. `app/compare-reports.php` applies approved changes as Place storage paths and does not interpret `__scoped_report__`. The result is the V3-003 finding: a scoped update can create a displayed version event without a reconstructed Area/Site snapshot.

The stored payload can explain a particular proposed change, but complete historical scoped reconstruction would also need:

- ordered approved scoped events;
- the complete starting scoped state, not only changed keys;
- time-appropriate imported baseline snapshots;
- Area/Site identity remap history;
- a versioned question-definition snapshot;
- explicit handling of later clear/delete operations.

No current store supplies that complete set.

## 14. Imported provenance

The clearest implemented Site baseline is RIDB:

- `place_external_sources` maps provider/external IDs to a Place and stores source URL/timestamps where supplied.
- `place_facility_facts` and `place_facility_features` retain provider identifiers and source details.
- `place_campsites` retains `source_provider` and `source_external_id` for canonical Site identity.
- campsite fact/feature/image rows retain source identifiers and, depending on the path, source URL, credit, and source update timestamp.
- `app/ridb-import.php` refreshes source-backed rows and can deactivate records omitted from a later source response.

`app/place-report/campsite-baseline.php` translates those facts into canonical field values at read time. `app/place-report/target-model.php` then replaces baseline values with direct Scout/Admin overrides. The scoped answer row itself has no field-level import attribution; detailed provenance remains available only by following the Site back to source tables.

Imported Place fields can also be written into canonical `places` columns while `places.source_type` and `place_external_sources` retain origin context. This is different from the read-time Site baseline model. Other external systems identified in the repository inventory were not exhaustively audited here; that belongs to **V3-006: External Data Import Audit**.

## 15. Photo and evidence relationships

| Evidence | Storage relationship | Historical behavior |
|---|---|---|
| New Place submission photos | Photo metadata/paths inside `place_submissions.submission_data` | Approval copies files to `/uploads/places/{placeId}` and inserts `place_images`; source files are removed after the outer commit |
| Place update photos | JSON in `place_update_submissions.photos` | Place approval copies files into the Place gallery and inserts `place_images` |
| Area/Site update photos | JSON in `place_update_submissions.photos` | Scoped approval returns through `llama_scoped_update_approve()` without attaching the files to a Place/Site owner; the moderation controller removes the update folder after commit |
| Published Place gallery | `place_images` by `place_id`, with featured/order/uploader metadata | Current published media, not an immutable report-photo snapshot |
| Canonical Site gallery | `place_campsite_images` by Place and campsite | Supports uploaded and imported source metadata; ownership checks enforce the Place/Site pair |
| Area media | No Area-owned photo persistence was found | Admin code explicitly avoids pretending Area photos have an owner |
| Problem report evidence | `place_report_images` by `report_id` | Separate from Scout Report submission/update photos |
| Imported Site images | `place_campsite_images` source columns | Provider/external ID, URL, credit, and source timestamps can be retained |

Moderation uses copy-first, transaction-aware Place photo attachment in `app/moderation.php`: database failure removes new permanent copies, and submission/update source folders are deleted only after commit. The scoped approval branch in `app/place-update.php` returns before that attachment step, while `admin/moderate-update.php` still removes the source update folder after commit. Thus an approved scoped update can retain photo JSON/history counts whose files are no longer available. The current code does not create an immutable byte or metadata snapshot per historical Scout Report. The diagnostic-only `scout_report_photos` contract would provide hashes and snapshots, but no active writer was found.

## 16. Runtime schema creation

Repository searches found **no reporting-related `CREATE TABLE IF NOT EXISTS` or `ALTER TABLE` path** for the Scout Report stores reviewed here.

| Path | Classification | Finding |
|---|---|---|
| `app/place-contributions.php::llama_ensure_place_contributions_table()` | Active runtime requirement / compatibility validator | Despite its name, it only checks `information_schema` for the table and `scoring_snapshot`; it throws and performs no DDL |
| `app/place-report/diagnostics.php` | Read-only deployment preflight | Uses `SHOW TABLE STATUS`, columns, indexes, and triggers; performs no migration |
| `scout_report_schemas`, `scout_reports`, `scout_report_answers`, `scout_report_moderation_events`, `scout_report_photos` | Needs investigation | Required by the diagnostic, but no repository DDL or active persistence path was found |
| Scout rank/maintenance ensure helpers | Outside Scout Report data scope | Some other project subsystems have schema helpers; they are not report/history storage and were not executed |

The current repository therefore depends on deployment-managed schema that is not checked in here. This audit does not establish the live schema version.

## 17. Known integrity constraints

Repository-confirmed integrity behavior includes:

- transactions and `FOR UPDATE` locking around moderation, contribution, submission-history, identity, and scoped operations;
- explicit Place ownership checks for Area/Site targets and Site images;
- scoped upsert identity that requires the five-column unique key described above;
- conflict detection before merging map-feature Site answers into canonical campsite answers;
- approval-time comparison of original Place values and answer state;
- all-or-nothing copying/attachment of moderated Place photos;
- application validation that a mapped Site cannot be ambiguously attached to multiple active canonical Sites.

`app/place-report/diagnostics.php` expects InnoDB tables, a unique scoped-answer identity, append-only UPDATE/DELETE guard triggers on five `scout_report_*` tables, and unique indexes for report revisions, client submission keys, report answers, and report-photo hashes. These are **expected diagnostic constraints**, not confirmed live constraints.

No checked-in schema definition was found from which to confirm primary keys, foreign keys, cascade rules, or most unique/index definitions for current tables. `ON DUPLICATE KEY` usages reveal required application assumptions, but they do not prove which live index provides the conflict target.

## 18. Confirmed inconsistencies

1. Active Admin supplemental saves can update fact columns without updating published Unknown-state metadata.
2. Current scoped overrides are mutable and have no link to the submission/contribution that produced them.
3. Place comparison/history reconstruction does not apply scoped update payloads to Area/Site snapshots.
4. Public history and contribution history identify events but do not preserve full answer sets.
5. Historical rendering depends on current registry metadata rather than a captured schema definition.
6. Place and Site imported data use different composition patterns: canonical Place columns can be refreshed, while Site report values can be composed from a source baseline plus overrides.
7. Scoped update approval does not promote submitted evidence photos before the post-commit update folder is removed.

## 19. Suspected risks

- A pending map-feature Site update may retain an obsolete target identity after Site normalization. This follows from static code paths but was not verified against stored data.
- One-row-per-Place detail-table and one-row-per-Place/period sensory assumptions may not be protected by live unique constraints; repository DDL is absent.
- Consumers that ignore revision-history Unknown arrays may interpret proposed null values incorrectly.
- Historical JSON can retain a photo path after source-file cleanup without guaranteeing the same file remains available.
- Source refresh/deactivation can change the imported baseline used to display a current Site without preserving the earlier baseline needed for historical replay.
- Approved records retained during account deletion may depend on live constraint/anonymization behavior not established by this audit.

## 20. Preservation requirements

V3 should preserve these behaviors while replacing or extending storage:

1. Keep Place, Area, and Site target identity explicit and Place-owned.
2. Preserve Answered, explicit No, Unknown, Unanswered, inherited, imported, direct override, and cleared-override as distinct states.
3. Keep partial updates from changing unrelated current answers.
4. Preserve contributor role, visit time, submission time, approval time, reviewer, source attribution, and evidence relationships independently.
5. Retain imported baselines separately from Scout observations and record which baseline was used for a historical report.
6. Preserve identity-remap history when a map-only Site becomes a canonical campsite.
7. Version question definitions with historical answer snapshots instead of relying on current labels and groups.
8. Link approved scoped changes to immutable report/contribution records before mutating current overrides.
9. Keep moderation and photo publication transactional, including rollback cleanup.
10. Move schema ownership to explicit deployment migrations and verifiable constraints rather than runtime assumptions.

## 21. Questions for later work

- Which current/diagnostic tables and constraints actually exist in each deployed environment?
- Are any pending or approved scoped payloads tied to map-feature Site identities that have since been normalized?
- Do duplicate one-to-one child rows or duplicate scoped identities exist in stored data?
- Which source tables and timestamps are authoritative for each non-RIDB importer?
- How should historical imported baselines and Site identity remaps be snapshotted?
- What retention and anonymization rules should apply to contributor identity and evidence after account deletion?
- Are the diagnostic-only append-only report tables an undeployed design, a separately managed migration, or an incomplete integration?
- Which current photo records require immutable hashes or metadata snapshots for defensible historical evidence?

These questions require later dedicated audits or implementation work. No schema or application change was made in V3-004.

# V3-006: External Data Import Audit

**Audit date:** 2026-10-29

**Repository base:** `v3-development` after merged V3-005

**Method:** Static repository inspection only. No production database, external provider account, live API credential, or deployed import job was accessed.

## 1. External data architecture overview

Llama Scout already has substantial external-data infrastructure, but it is not one unified importer.

Current source handling falls into several patterns:

1. **Dedicated source cache/database**
   - RIDB uses `ridb_db()`.
   - NPS uses a separate `nps_db()`.
   - FCC coverage uses `cell_db()`.
   - PAD-US uses the reference database.

2. **Direct enrichment against the main database**
   - RIDB production import creates or updates canonical Places and Sites.
   - NPS can fill empty canonical Place fields after human-confirmed source matching.
   - USFS can save reviewed enrichment records for an existing Place.

3. **Reference-only / overlay behavior**
   - PAD-US provides managed-land/reference geography and classification.
   - BLM/USFS/NPS/BIA/FWS/local land boundaries are also queried directly by the public map overlay.

4. **Cross-source reconciliation**
   - RIDB and NPS have explicit matching and reconciliation tools.
   - `app/official-source-reconciliation.php` compares selected facts without mutating canonical data.

These patterns are useful, but V3 should converge them around a common source/provenance contract instead of replacing working provider-specific code.

## 2. Database connection architecture

`app/database.php` exposes:

- `db()` for the main application database.
- `reference_db()` for reference data.
- `ridb_db()` for RIDB source/cache data.
- `cell_db()` for FCC cell-coverage data.

`app/nps.php` also defines `nps_db()` using a separate `nps_database` configuration block.

This confirms that source isolation already exists for several providers.

The repository does not define a universal source-database abstraction across all providers.

## 3. RIDB / Recreation.gov status

RIDB is the most complete external source integration in the repository.

### Confirmed working components

`app/ridb.php` provides:

- API configuration and request handling.
- pagination helpers.
- facility lookup/search.
- facility campsites.
- facility addresses.
- media.
- links.
- activities.
- campsite detail.
- campsite attributes.
- sync-run logging.
- source-cache writes.

`app/ridb-mirror.php` provides a broader lossless mirror layer with:

- raw records.
- source endpoint identity.
- source JSON.
- mirror synchronization state.
- resumable feed batches.
- feed-level completion tracking.

`app/ridb-schema.php` inspects raw source schemas and campsite attribute coverage.

`app/ridb-normalization.php` maps provider attributes into canonical concepts and records whether treatment is direct, normalized, or source-attributed.

`app/ridb-import.php` performs production import into the main database.

### Canonical Place import behavior

`llama_ridb_import_find_place()` identifies an existing Place strictly through:

`place_external_sources(source_provider = 'ridb', source_external_id = facilityId)`

If no source link exists, the importer creates a new Place.

This is stable and safe for repeated RIDB imports, but it is not a general cross-provider duplicate detector.

A campground already created manually or through another provider can therefore require reconciliation before RIDB import if it lacks the RIDB source link.

### Place creation/update

RIDB production import can create a developed campground directly as Active and records:

- source type.
- exact and rounded coordinates.
- name.
- description.
- city/state.
- status history.

Repeated import updates the linked Place.

Important: the update path can directly replace some canonical Place values such as name, description, coordinates, and type when the source provides them.

This is stronger behavior than the later NPS fill policy and should be explicitly reviewed under V3 source-priority rules.

### External source identity

`llama_ridb_import_link_source()` records:

- source provider.
- source type.
- source external ID.
- source URL.
- source update timestamp.
- sync timestamp.

This is a strong provenance foundation.

### Site import

RIDB imports individual campsites, not just Place-level data.

For each Site it can:

- upsert canonical campsite identity.
- store Site facts.
- store Site features.
- retain source provider/external ID.
- deactivate previously imported RIDB Sites before reactivating those still returned.

This satisfies an important V3 requirement: official Site-level data can pre-populate Sites before community reporting.

### Site normalization

`app/ridb-normalization.php` includes mappings for many Site-level facts including:

- tent pad presence and dimensions.
- parking style.
- campsite measurements.
- accessibility.
- utilities and hookups.
- amenity-style attributes.
- source-attributed data.

The normalization system is broad but source-specific.

### RIDB canonical Place enrichment

`app/ridb-canonical.php` can promote selected source evidence into canonical Place tables.

Its current write behavior is conservative for many fields:

- fill empty values.
- allow a positive amenity to replace an existing zero in selected cases.
- otherwise avoid replacing non-empty canonical values.

This differs from the direct Place update behavior in `app/ridb-import.php`, so V3 must clarify which RIDB path owns canonical source refresh.

### RIDB Site media

`app/ridb-complete-import.php` imports Site media into `place_campsite_images`.

It preserves:

- source provider.
- source external ID.
- source URL.
- source credit.
- source updated time.

It deletes/replaces only RIDB-owned Site media for the Site, leaving Scout uploads and other providers untouched.

This provider-isolated media behavior should be preserved.

## 4. NPS status

NPS is substantially implemented as a source cache and reconciliation system.

### Dedicated NPS database

`app/nps.php` explicitly states that the NPS source cache is isolated from the main database.

Supported source resources include:

- parks.
- campgrounds.
- alerts/closures.
- fees/passes.
- parking lots.
- road events.

The configured API host is restricted to the official NPS developer API host.

### Raw source storage

NPS source records are stored in `nps_raw_records` with:

- resource type.
- source record ID.
- park code.
- record name.
- source URL.
- source updated time.
- raw JSON.
- source hash.
- fetched time.

This is close to the V3 raw-source design.

### Import tracking

NPS has an import-progress system with:

- per-resource status.
- cursor/offset.
- total record count.
- last page hash.
- response metadata.
- last error.
- start/update/completion timestamps.

It supports:

- start.
- refresh.
- pause.
- resume/next page.
- locking against concurrent advancement.
- page-level transactional source storage.

This is more mature than the initial V3 planning assumptions suggested.

### Runtime schema creation

Unlike the Scout Report persistence path, NPS does contain a runtime `CREATE TABLE IF NOT EXISTS` for `nps_import_progress`.

This should later move under explicit schema/migration ownership, but it is an existing active behavior and should not be removed casually.

### NPS/RIDB matching

NPS does not independently attach a campground to an arbitrary Place by fuzzy name/coordinates.

`app/nps-place-fill.php` requires:

- an NPS campground source record.
- a RIDB facility ID.
- a current human-confirmed NPS/RIDB match.
- the original NPS source hash to still match the reviewed source record.
- an already-published RIDB-backed Llama Scout Place.

This is strong identity protection.

It explicitly rejects using only names, coordinates, or shared reservation URLs as identity.

### NPS canonical fill behavior

NPS fill is conservative.

`llama_nps_fill_preview()` classifies candidate fields into:

- fill.
- conflict.
- same.
- unavailable.

`llama_nps_fill_apply()` writes only empty canonical fields.

It does not overwrite an existing non-empty conflicting fact.

This aligns well with the V3 rule that external conflicts should be reconciled instead of newest-source-wins.

### NPS media

NPS images are deduplicated by exact URL against existing Place images.

New NPS images can be inserted into the Place gallery.

Current NPS image insertion does not appear to store the same rich per-image source-provider/source-credit fields used by RIDB Site media.

That is a provenance gap to address later.

## 5. NPS/RIDB source reconciliation

`app/official-source-reconciliation.php` is read-only assessment logic.

It compares selected NPS and RIDB facts including:

- Site totals.
- nightly fee candidates.
- reservable Site counts.
- geographic distance.
- RV policy.

It preserves source provider, source ID, source path, and source value in the evidence structure.

It deliberately treats ambiguous free-text RIDB evidence as evidence rather than structured truth.

It also recognizes invalid or unsafe source combinations, for example a reservable count larger than the total Site count.

This is a good basis for V3 reconciliation principles.

## 6. USFS status

USFS exists as a reviewed enrichment integration, not as a full source mirror/import pipeline.

`app/usfs-enrichment.php`:

- queries an official USFS ArcGIS recreation layer.
- searches campground candidates.
- requires explicit Place selection.
- verifies Site ID and normalized campground name.
- requires the remote record to be a campground.
- stores the selected source record and raw JSON in `place_usfs_enrichment`.

The module explicitly says:

- source records are retained verbatim.
- matching is explicit and reviewed.
- it does not publish fees automatically.
- it does not mark campgrounds closed automatically.

Fee information is treated as a candidate requiring human review.

### Current limitations

No repository evidence was found of a nationwide USFS source cache equivalent to RIDB/NPS.

No USFS individual campsite/subsite importer comparable to RIDB Site import was confirmed.

Current USFS functionality is therefore best classified as:

**working enrichment/review integration, incomplete as a V3 broad-coverage source importer.**

## 7. BLM status

No dedicated BLM source importer, raw source database, normalization adapter, or reconciliation workflow was found in the reviewed repository.

BLM data is actively used in `js/map-land-overlays.js`.

The map queries BLM Surface Management Agency ArcGIS services for boundaries and uses the same BLM source to show multiple management categories such as:

- BLM.
- USFS.
- NPS.
- FWS.
- local land.

This is a client-side/reference-map integration.

It is **not** a canonical data-ingestion pipeline.

### V3 classification

BLM currently has:

- working land-overlay/reference behavior.
- missing canonical recreation-point / primitive-Site ingestion.
- missing provider-specific source cache.
- missing BLM Place/Site normalization.
- missing BLM reconciliation workflow.

This is one of the largest missing source integrations relative to the V3 plan.

## 8. PAD-US status

PAD-US has a relatively mature reference-source synchronization system.

`app/pad-us-sync.php` uses a provider entry in `reference_sources`.

The system includes:

- source registration.
- state-by-state imports.
- source counts.
- batch import.
- sync-run records.
- issue tracking.
- organization normalization.
- domain/classification mappings.
- manager-type interpretation.

`app/pad-us-review.php` provides review/classification tooling.

`app/pad-us-location.php` provides point-based land lookup and canonical management interpretation.

PAD-US is appropriately treated as reference geography/land-management context rather than proof of legal camping.

That distinction should remain explicit in V3.

## 9. FCC Broadband Data Collection status

FCC cell coverage has one of the most operationally mature ingestion systems in the repository.

The current V2 worker architecture includes:

- provider/state planning.
- catalog discovery.
- published dataset date tracking.
- downloads.
- verification.
- unpacking.
- batched imports.
- retry limits.
- worker heartbeats.
- queue stages.
- run/job/slot status.
- current dataset ledger.
- cleanup.
- resume behavior.
- admin status reporting.

It uses a separate cell database.

This is primarily a reference/coverage system, not a Scout Report observation importer.

V3 should preserve the distinction between:

- predicted/public FCC coverage.
- firsthand community connectivity observations.

FCC data should not overwrite human-tested Site reception observations.

## 10. Source tracking models

The repository currently contains several independent source-tracking patterns:

### Main database

`place_external_sources` links canonical Places to external provider IDs.

RIDB and NPS use this.

Other source tables retain more detailed provider-specific evidence.

### RIDB database

RIDB maintains:

- raw/mirrored records.
- normalized cache tables.
- sync runs.
- mirror sync state.

### NPS database

NPS maintains:

- raw source records.
- source hashes.
- import progress.
- NPS/RIDB review events.

### Reference database

PAD-US uses:

- reference sources.
- reference sync runs.
- reference sync issues.
- source classifications/organization mappings.

### Cell database

FCC uses its own operational ingestion tables.

### V3 implication

There is not yet one shared `source_registry` contract across all providers.

The existing provider-specific systems should be adapted behind a shared source abstraction rather than flattened into one physical database.

## 11. Duplicate detection and source identity

### RIDB

Repeated RIDB records are safely recognized through RIDB external ID.

Cross-provider duplicate detection is limited because production Place lookup uses the RIDB source link, not fuzzy identity.

### NPS

NPS uses explicit human-confirmed RIDB pairing with source-hash validation.

This is the strongest cross-provider identity workflow currently present.

### USFS

USFS relies on explicit Admin selection plus normalized campground-name validation.

### Site identity

Canonical Site identity already supports provider IDs and later reconciliation with map features.

### Confirmed V3 gap

There is no generalized cross-provider Place identity/crosswalk service that can safely determine:

"RIDB facility X, NPS campground Y, USFS recreation site Z, and a manually created Place all represent the same Place."

Provider-specific matching logic currently solves this in different ways.

## 12. Conflict resolution

The repository contains several conflict strategies:

- RIDB direct production refresh can overwrite selected Place fields.
- RIDB canonical enrichment often fills empty facts conservatively.
- NPS fill never overwrites a non-empty conflicting canonical field.
- NPS/RIDB reconciliation exposes conflicts for review.
- USFS stores candidate enrichment instead of auto-publishing ambiguous values.
- PAD-US classification uses explicit review/mapping tools.

There is therefore no single global conflict policy yet.

This is a central V3 implementation requirement.

A future shared policy should distinguish:

- authoritative official identity/facts.
- source-attributed candidate facts.
- community observations.
- current canonical values.
- unresolved conflicts.

## 13. Import tracking and resumability

### Strong implementations

NPS:
- persisted per-resource cursor.
- page hashes.
- status.
- errors.
- locking.
- pause/resume.

FCC:
- run/job/slot model.
- retries.
- worker queues.
- progress and logs.

PAD-US:
- sync runs.
- cursor/row counts.
- issue tracking.

RIDB:
- sync-run logging.
- mirror feed state.
- raw mirror.
- production import logging.

The V3 source architecture should reuse these concepts rather than replace them with a simplistic one-shot importer.

## 14. Photo import behavior

### RIDB Site media

Strong provenance support:

- provider.
- external ID.
- source URL.
- credit.
- source updated time.

Provider-owned imported media can be refreshed without deleting community images.

### NPS Place images

NPS currently:

- validates HTTPS URLs.
- deduplicates exact URLs.
- inserts external image URLs into the Place gallery.

The reviewed insertion path does not preserve the same rich provider/credit metadata as RIDB Site media.

### V3 requirement

Imported media should consistently retain:

- provider.
- external media ID where available.
- source URL.
- credit/license information.
- source update timestamp.
- import/sync timestamp.

Exact hash or URL deduplication can be supplemented later with perceptual duplicate detection, but should not be required for initial V3 migration.

## 15. Missing-upstream behavior

The source integrations do not uniformly interpret an upstream disappearance.

RIDB Site refresh deactivates provider-owned Sites not returned in the refreshed set.

NPS refresh explicitly says it does not delete sources or reviews.

FCC and PAD-US maintain their own dataset/run state.

V3 must formalize the rule:

**upstream missing does not automatically mean delete the canonical Llama Scout record.**

Provider-owned source rows can become inactive/stale while canonical/community history remains preserved.

## 16. Source priority is not yet centralized

Different providers currently have different write powers.

Examples:

- RIDB production import can update canonical Place coordinates/name/description.
- NPS only fills empty canonical values.
- USFS stores enrichment candidates.
- RIDB canonical enrichment fills selected empty/current-positive fields.
- PAD-US is reference context.

This is functional but implicit.

V3 needs field-aware source priority and publication rules so a provider's code path does not independently determine authority.

## 17. Security and credential handling

RIDB, NPS, and FCC use protected configuration rather than repository credentials.

NPS restricts the configured API host to the official NPS endpoint.

No secrets were found in the audited implementation files.

This pattern should be preserved.

## 18. Working, incomplete, and missing components

| Component | Status |
|---|---|
| RIDB API/cache | Working and substantial |
| RIDB lossless mirror | Working |
| RIDB normalization | Working, source-specific |
| RIDB Place import | Working |
| RIDB individual Site import | Working |
| RIDB Site media import | Working |
| RIDB cross-provider duplicate prevention | Incomplete |
| NPS raw source cache | Working |
| NPS import progress/resume | Working |
| NPS/RIDB human matching | Working |
| NPS conservative Place fill | Working |
| NPS image provenance | Incomplete |
| USFS campground enrichment | Working |
| USFS nationwide source mirror | Missing/incomplete |
| USFS individual Site/subsite import | Not confirmed |
| BLM land overlay | Working |
| BLM recreation/primitive Site importer | Missing |
| PAD-US reference sync/review | Working |
| FCC coverage sync | Working and mature |
| Shared provider source registry contract | Incomplete |
| Shared cross-provider identity service | Missing |
| Shared field-level source priority | Missing |
| Shared import conflict/reconciliation queue | Partial/provider-specific |
| Uniform imported media provenance | Incomplete |
| State/commercial campground sources | Not implemented in reviewed code |

## 19. Confirmed inconsistencies and gaps

1. RIDB Place matching depends on an existing RIDB source link and does not perform generalized duplicate detection.
2. RIDB production import and RIDB canonical enrichment use different overwrite/fill behavior.
3. NPS image imports do not preserve the same rich image provenance as RIDB Site media.
4. USFS is enrichment-oriented rather than a complete broad source cache/import system.
5. BLM has map-overlay integration but no canonical recreation/Site import pipeline.
6. Source tracking is split among multiple provider-specific models without one shared source registry contract.
7. Conflict/source-priority behavior is provider-specific.
8. Cross-provider identity is solved specially for NPS/RIDB rather than through a reusable canonical crosswalk service.
9. Imported source data and community answers are not yet governed by one shared field-authority model.

## 20. Preservation requirements for V3

1. Preserve dedicated source/raw storage where already working.
2. Preserve original provider IDs and raw source records.
3. Preserve RIDB Site-level import and Site media provenance.
4. Preserve NPS source hashing and human-confirmed identity review.
5. Preserve NPS conservative conflict behavior.
6. Preserve USFS explicit-review behavior until stronger identity rules exist.
7. Preserve PAD-US as reference geography rather than proof of camping legality.
8. Preserve FCC predicted coverage separately from firsthand Scout connectivity.
9. Preserve provider-owned media separately from community uploads.
10. Preserve sync state, retries, checkpoints, and resumability.
11. Do not delete canonical records solely because they disappear upstream.
12. Build shared cross-provider identity/crosswalk services around existing provider adapters.
13. Centralize field-level source authority and reconciliation policy.
14. Centralize provenance presentation so published imported facts can identify source/date.
15. Keep all credentials outside source control.

## 21. Questions for later implementation

- Should RIDB production import continue to overwrite name/description/coordinates, or should those become reconciled official facts?
- What fields should have provider-specific authority precedence?
- Which NPS resources should publish automatically versus remain evidence-only?
- Should NPS photos be copied/hashed or remain external URLs?
- Which USFS official service should become the V3 source of campground/Site records?
- Which BLM recreation-point/primitive-campsite datasets should be imported first?
- How should manually created Places be matched to official provider records before automatic creation?
- What cross-provider confidence threshold is sufficient for automatic linking?
- Which source disappearance states should mark records stale, inactive, or review-required?
- How should state-agency and commercial campground sources plug into the shared source registry?
- Which external datasets permit publication behind membership restrictions?
- Which imported facts belong to Public versus Community visibility?

No importer behavior was changed in V3-006.

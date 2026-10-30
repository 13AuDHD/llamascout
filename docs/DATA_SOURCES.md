Llama Scout V3

External Data Sources, Importing and Reconciliation

Version: 3.0
Status: Architecture defined; implementation pending audit
Document: docs/DATA_SOURCES.md

Related specifications:

* docs/PROJECT_SCOPE.md
* docs/ARCHITECTURE.md
* docs/SCOUT_REPORT_SPEC.md
* AGENTS.md

⸻

1. Purpose

Llama Scout V3 aims to build a comprehensive geographic database of campgrounds, individual campsites, RV parks, overnight parking locations, public-land camping opportunities, and essential travel resources.

Official and licensed external datasets provide the foundation.

Community observations supplement this information with details that cannot reliably be obtained from published databases.

Llama Scout should import as much useful, legitimate information as practical, including individual Site attributes.

Members should not be required to recreate campgrounds, Sites, or facts that already exist in reliable external data.

The primary objective is to let members document their actual experiences, not repeatedly enter information that government agencies and other providers have already published.

All external information must pass through controlled importing, normalization, reconciliation, and publication processes.

⸻

2. Fundamental Architecture

The external data system must preserve three distinct responsibilities.

2.1 External Source Storage

Retains information obtained from each provider.

Each provider’s original records, identifiers, timestamps, and relevant metadata must remain available.

External source records must not be treated as published Llama Scout information merely because they have been downloaded.

2.2 Normalization and Reconciliation

Transforms external information into canonical Llama Scout definitions.

This process identifies existing Places, Sites, and Resources; compares records; detects conflicts; and determines what may be published.

Normalization must not destroy original source information.

2.3 Published Llama Scout Data

Contains the authoritative information served to the website, map, members, and future API consumers.

Only approved, normalized information enters this published system.

The existing separation between reference databases and Llama Scout’s published information must be retained.

Logical data flow

EXTERNAL DATA PROVIDERS
         |
         v
SOURCE-SPECIFIC IMPORTERS
         |
         v
RAW SOURCE STORAGE
         |
         v
CHANGE DETECTION
         |
         v
NORMALIZATION / FIELD MAPPING
         |
         v
MATCHING / DUPLICATE DETECTION
         |
         v
RECONCILIATION / CONFLICT REVIEW
         |
         v
APPROVED PUBLICATION
         |
         v
CANONICAL LLAMA SCOUT DATA
         |
         v
SHARED PHP SERVICES
         |
         +-- Website and Map
         +-- Future API
         +-- Mobile Applications
         +-- AI Integrations

These are logical components, not mandatory new database tables or PHP files.

Codex must inspect the existing implementation before proposing changes.

⸻

3. Approved and Candidate Data Providers

The following providers are part of Llama Scout’s intended data coverage strategy.

A provider’s inclusion here does not automatically authorize importing or redistributing every available field.

Each integration must pass technical, data-quality, and licensing review.

3.1 Recreation Information Database (RIDB)

Organization: Recreation.gov / Recreation Information Database
Priority: Existing integration; audit and complete
Official documentation: https://ridb.recreation.gov/docs
Data downloads: https://ridb.recreation.gov/download

RIDB provides structured information about federal recreation areas, facilities, campsites, and associated attributes.

Relevant information may include:

* Recreation area identifiers
* Facility identifiers
* Individual campsite identifiers
* Facility names and descriptions
* Campsite names and numbers
* Campsite types and capacities
* Permitted equipment
* Site attributes and measurements
* Addresses and coordinates
* Activities and amenities
* Contact and reservation information
* Photos and media references

Import available individual campsite information, not merely facility-level records.

Preserve parent facility relationships and stable external identifiers.

RIDB provides downloadable recreation data in addition to its API.

Codex must compare the existing importer against available API endpoints and bulk data before recommending changes.

Do not replace functioning RIDB import infrastructure without demonstrating a specific deficiency.

3.2 National Park Service (NPS)

Organization: National Park Service
Priority: High
Status: API configuration exists; importer functionality requires verification
Official documentation: https://www.nps.gov/subjects/developer/api-documentation.htm

The NPS API provides information about national parks and associated resources.

Relevant data may include:

* Parks
* Campgrounds
* Official descriptions
* Contact information
* Addresses and coordinates
* Amenities
* Campsite counts and categories
* Operating information
* Fees
* Reservation information
* Accessibility information
* Closures and alerts
* Other relevant visitor facilities

Campground information should supplement existing RIDB records where applicable.

NPS records must not automatically create duplicate campground entries.

NPS alerts and closures should be evaluated through the appropriate existing administrative workflow.

The presence of an NPS API key in server configuration does not establish that an importer has been implemented or tested.

Codex must inspect the existing connection and related code.

3.3 United States Forest Service (USFS)

Organization: USDA Forest Service
Priority: High
Status: Existing integration requires audit; additional datasets proposed

Relevant services include:

Recreation Opportunities

https://apps.fs.usda.gov/arcx/rest/services/EDW/EDW_RecreationOpportunities_01/MapServer

Recreation Sites and Subsites

https://apps.fs.usda.gov/arcx/rest/services/EDW/EDW_RecInfraRecreationSites_02/MapServer

These services provide information about recreation opportunities, facilities, recreation sites, subsites, associated activities, services, and contacts.

Relevant information may include campground locations, recreation classifications, descriptions, available facilities, and Site-related information.

USFS terminology must not automatically be assumed equivalent to Llama Scout terminology.

A USFS “Subsite” must be evaluated before mapping it to a Llama Scout Site.

Source relationships and identifiers must be preserved.

USFS data may overlap with RIDB and other Forest Service publications.

The importer must recognize these overlaps.

3.4 Bureau of Land Management (BLM)

Organization: Bureau of Land Management
Priority: Very high
Status: Proposed expanded integration

BLM provides geographic information relevant to campgrounds, primitive campsites, public-land recreation, and land management.

Relevant datasets include:

* Campgrounds
* Developed campsites
* Primitive campsites
* Parking locations
* Potable water locations
* Recreation points
* Recreation-area polygons
* Surface management boundaries

Official recreation points:

https://gis.blm.gov/natl/rest/services/recreation/BLM_Natl_Recs_pts/FeatureServer

BLM recreation polygons and land-management datasets must be investigated separately.

BLM’s public GIS services are undergoing endpoint migrations.

Codex must verify the current service addresses, available layers, query formats, and metadata before implementing a connector.

Do not hardcode deprecated service URLs into permanent importer logic.

Primitive Campsites

BLM primitive campsite records are particularly valuable to Llama Scout’s dispersed camping collection.

These records may be imported into source storage and normalized into appropriate Places or Sites.

However, their presence in an official dataset does not automatically establish that overnight camping is presently legal or that the location is accessible.

Applicable restrictions must be considered.

Imported primitive and dispersed campsite locations remain subject to Llama Scout’s dispersed camping membership restrictions.

3.5 USGS and Protected Land Data

Organization: U.S. Geological Survey
Priority: High for map coverage

Relevant datasets include PAD-US and other appropriate public-land reference information.

These datasets may provide:

* Protected-area boundaries
* Land-management organizations
* Public-access classifications
* Geographic classifications
* Related administrative boundaries

These records primarily serve as geographic reference layers.

A public-land boundary is not proof that dispersed camping is permitted everywhere inside it.

Do not automatically create camping Sites from general land-management polygons.

3.6 State Park and Recreation Agencies

Priority: High; incremental expansion

State-level sources may provide information about:

* State park campgrounds
* Individual campsites
* Park facilities
* Fees
* Restrictions
* Seasonal access
* Reservation providers
* Geographic boundaries

Each state may expose different APIs, downloadable datasets, or GIS services.

Every state integration requires a documented mapping and source policy.

Do not create a universal importer that guesses how unrelated state datasets should be interpreted.

3.7 Overture Maps

Priority: High for commercial campground discovery
Documentation: https://docs.overturemaps.org/guides/places/

Overture Places may help identify:

* Commercial campgrounds
* RV parks
* Camping resorts
* Private recreation facilities
* Other relevant travel services

Relevant attributes may include names, categories, coordinates, addresses, websites, and contact information where available.

Overture records must be validated because its Places dataset can contain duplicates, incomplete attributes, and incorrect classifications.

Its stable identifiers and published update information should be preserved.

Only use properly licensed data and applicable attribution.

3.8 OpenStreetMap

Priority: High for gap filling and Resources
License: Open Database License (ODbL)
Licensing: https://www.openstreetmap.org/copyright

OpenStreetMap may provide:

* Campgrounds
* Caravan and RV sites
* Camping pitches
* Overnight parking information
* Potable water locations
* Dump stations
* Public toilets
* Other useful travel facilities

OSM data requires attribution and careful handling of derivative-database obligations.

Do not mix ODbL-derived data into Llama Scout’s proprietary canonical database without reviewing the applicable licensing requirements.

Source-derived records, attribution, and licensing obligations must remain identifiable.

OSM’s public map services must not be treated as an unrestricted bulk-download or production tile service.

⸻

4. Source Registry

Every integration should be represented in a centralized administrative source registry.

The registry must identify:

* Provider name
* Provider identifier
* Official documentation
* Source endpoint or dataset location
* Source type
* Authentication requirement
* Import status
* Last attempted synchronization
* Last successful synchronization
* Current source version where available
* Last import result
* Applicable license and attribution
* Associated normalization mapping
* Relevant error status

Do not store plaintext credentials in this registry.

Secrets must remain in the existing protected configuration system.

The source registry should provide administrators with a clear understanding of which integrations are functional, incomplete, outdated, or experiencing errors.

Existing source-management functionality must be reused wherever possible.

⸻

5. Source-Specific Storage

5.1 Preserve Original Records

Each imported record must retain its original source representation or a recoverable equivalent.

Relevant information includes:

* Provider identifier
* Original source record identifier
* Parent record identifier
* Original attributes
* Original geometry
* Original source timestamps
* Import timestamp
* Source dataset version where available
* Import batch identifier
* Relevant provenance and license information

The original source must remain distinguishable from normalized data.

5.2 Preserve Existing Databases

Llama Scout V2 already contains reference database functionality.

Codex must identify all existing source-specific databases, tables, and import services before proposing changes.

Do not create redundant copies of source databases without an architectural justification.

Where the existing design is sufficient, extend it.

5.3 Import Batch Identity

Each synchronization should be associated with an identifiable import operation.

A batch should record:

* Source
* Start and completion times
* Execution status
* Number of records retrieved
* Number of records processed
* Number unchanged
* Number added
* Number changed
* Number flagged for review
* Number failed
* Relevant warnings or errors

Exact storage is an implementation decision.

⸻

6. Import Retrieval Rules

6.1 Controlled Retrieval

Importers must respect source API limits and published usage policies.

Use pagination, batching, appropriate retries, and resumable processing where practical.

Avoid requesting unnecessarily large datasets in a single operation.

Large nationwide imports must be capable of incremental execution without depending on one long-running web request.

6.2 Safe Failure Handling

A failed retrieval must not be interpreted as an empty authoritative dataset.

Network errors, API failures, invalid responses, expired credentials, and incomplete pagination must be detected.

Partially retrieved datasets must not trigger mass deletion or archival of previously imported records.

6.3 Source Availability

Source services may change their URLs, schemas, identifiers, and authentication requirements.

Importers should maintain service configuration separately from canonical reporting logic.

An unavailable source must not prevent existing published Llama Scout Places and Sites from functioning.

6.4 Credentials

Credentials must never be committed to GitHub.

Use existing protected configuration and server-side request handling.

API keys must not appear in client-side JavaScript, public API responses, import reports, or error logs.

⸻

7. Change Detection

A subsequent import must be capable of recognizing the same source record.

7.1 Record Identity

Use stable provider identifiers where available.

The combination of source identifier and original record identifier should uniquely identify a source record within its appropriate dataset and record type.

Do not depend solely on record names or GPS proximity.

Provider-specific identity rules must be documented.

7.2 Change Categories

Each synchronization should identify records as:

Status	Meaning
New	Not previously imported
Unchanged	No relevant source change
Updated	Source values changed
Removed upstream	Previously imported but no longer supplied
Potential duplicate	May correspond to another record
Conflict	Differs from protected published information
Invalid	Cannot safely normalize or interpret
Needs review	Requires administrative decision

A record absent from an incomplete import must not be marked removed upstream.

7.3 Field-Level Differences

Where practical, record which fields changed.

Example:

Target Tree Campground

* Phone number: unchanged
* Campground description: changed
* Number of campsites: changed
* Reservation URL: unchanged

Changes should be reviewable without comparing raw database records manually.

7.4 Refresh Strategy

Different sources may have different update schedules.

Do not assume every provider supports real-time updates.

Each connector should record its actual source freshness and synchronization behavior.

Time-sensitive information such as closures and fees should be distinguishable from relatively static information such as Site identifiers or geographic boundaries.

⸻

8. Canonical Normalization

8.1 Centralized Field Mapping

Each source field must map to an approved canonical definition.

Examples:

Source information	Canonical destination
Facility name	Place name
Facility contact	Place contact
Managing organization	Place land manager
Campsite number	Site identifier/name
Campsite maximum vehicles	Site capacity field
Site driveway length	Appropriate Site measurement
Drinking water service	Applicable amenity or Resource
Official camping fee	Applicable Place or Site fee

These are conceptual examples.

Codex must verify the exact canonical field identifiers and appropriate ownership before implementing mappings.

8.2 No Duplicate Questions

Importers must not create their own independent Scout Report field definitions.

If a source contains a meaningful field not currently represented in Llama Scout, flag it for registry review.

Do not silently create a new canonical question or overload an unrelated existing question.

8.3 Units and Data Types

Normalize supported units, formats, and answer values.

For example:

* Length measurements
* Site dimensions
* Dates and times
* Geographic coordinates
* Monetary amounts
* Boolean and enumerated answers

Preserve the original value and unit where necessary for traceability.

Do not convert ambiguous values by guessing.

8.4 Missing Values

An absent source field is not equivalent to No.

Examples:

* No listed potable water information is not proof water is unavailable.
* No listed RV length is not proof RVs are prohibited.
* Missing Site coordinates do not justify inventing coordinates.
* No published ground condition is not proof the Site is level.

Missing values must remain unknown unless another approved source supplies the information.

8.5 Geographic Coordinates

Preserve original geometry and coordinate reference information.

Normalize coordinates into the format required by Llama Scout’s map services.

Any coordinate transformation must be accurate and repeatable.

Coordinates supplied for a campground entrance, facility center, individual campsite, or parcel centroid must not be treated as interchangeable.

Each coordinate must retain its meaning.

8.6 Imported Photos

Import photos only when permitted by the provider’s terms and applicable rights.

Preserve source attribution, original identifiers, and relevant licensing metadata.

Reuse existing photo deduplication and storage where possible.

Do not duplicate identical image files simply because multiple providers reference them.

Do not assume that publicly accessible photos are automatically licensed for redistribution.

⸻

9. Place and Site Matching

9.1 Match Before Creating

Every normalized external Place or Site must be checked against existing Llama Scout records before publication.

A matching existing record should be updated through reconciliation rather than duplicated.

9.2 Matching Evidence

Matching may consider:

* Existing source identifiers
* Known cross-provider identifiers
* Parent facility relationships
* Place names and aliases
* Geographic coordinates
* Addresses
* Managing organizations
* Site numbers
* Site types
* Related source records

No single criterion is universally sufficient.

9.3 Existing Crosswalks

Maintain a relationship between external source identifiers and canonical Llama Scout records.

For example, one campground may have separate identifiers in RIDB, NPS, and USFS.

Those identifiers should reference the same canonical Place when confirmed to describe the same real-world location.

The source records remain separate.

9.4 Ambiguous Matches

If a match cannot be determined confidently, flag it for review.

Do not force an automatic match solely because two records have similar names.

Do not create duplicates simply because two records have different source identifiers.

Existing reconciliation tools must remain the primary mechanism for resolving ambiguity.

⸻

10. Reconciliation and Conflict Resolution

10.1 Reuse Existing Functionality

Llama Scout already contains conflict resolution and import reconciliation concepts.

Codex must inspect the existing implementation before creating new systems.

10.2 Reconciliation Interface

The administrative interface should allow authorized users to review:

* Source record
* Existing canonical record
* Proposed field changes
* Current published value
* Incoming value
* Original source and date
* Identified differences
* Potential duplicate relationships
* Relevant history

The presentation should make the decision understandable without requiring manual database inspection.

10.3 Field-Level Decisions

Reconciliation should support appropriate actions such as:

* Accept incoming information
* Retain the existing value
* Fill a currently missing value
* Mark a source conflict for further review
* Reject invalid source information
* Associate the source record with an existing Place or Site
* Skip a record when identity cannot be established

A conflict in one field should not necessarily prevent unaffected valid fields from being processed.

10.4 No Silent Overwrites

An external source must not silently replace protected approved Place information.

If a source differs from the current published value, use existing reconciliation rules to determine whether the change can be accepted automatically or requires review.

Do not introduce new automatic conflict resolution behavior without explicit approval.

10.5 Preserve Rejected Source Values

A rejected incoming value may still be useful later.

Preserve the source record and the reconciliation decision.

Future updates may provide additional evidence or resolve the conflict.

10.6 Administrative Review

Existing Master Scout, Admin, and Owner permissions must be respected.

Owner-only tools remain protected.

Do not expand reconciliation privileges merely because a new import interface is introduced.

⸻

11. Publication into Llama Scout

11.1 Controlled Publication

Only appropriately normalized and approved information may become canonical published data.

Publication must preserve:

* Canonical record identity
* Appropriate entity ownership
* Source provenance
* Publication and update history
* Existing community observations
* Membership visibility rules

11.2 Place Information

Imported Place facts should populate the applicable Place-level fields.

They must not be duplicated across every Site within the Place.

11.3 Site Information

Import actual individual Site records wherever reliable data exists.

Examples include:

* Numbered campsites
* Site capacity
* Equipment permissions
* Tent pad dimensions
* Parking dimensions
* Individual Site amenities
* Official Site restrictions

An official Site may exist without a community Scout Report.

Do not fabricate community observations to make an imported Site appear complete.

11.4 Community Observations

Imported official information and community observations must remain distinguishable.

Official facts may supply measurements, restrictions, and documented facilities.

Community reports may supply firsthand connectivity, sensory, environmental, and experience information.

All answers retain appropriate provenance.

11.5 Resource Publication

A source may identify a water fill station, dump station, or other Resource.

That information should be normalized into the appropriate Resource model without requiring a fabricated camping Place or Site.

Resources follow the established publication and moderation rules.

⸻

12. Visibility and Membership Rules

12.1 Public Source Information

Ordinary publicly listed campgrounds and their designated public information remain available without paid membership.

Examples include publicly published campground names, addresses, official contacts, permitted public amenities, and applicable public campground details.

Source terms and V3 classifications determine which fields may be republished.

12.2 Restricted Dispersed Camping

All dispersed campsite records remain membership-restricted within Llama Scout.

This includes imported primitive or dispersed Sites.

The fact that an individual coordinate exists in a public GIS dataset does not override Llama Scout’s established publication classification.

Authorized access includes:

* Paid members
* Complimentary full members
* Free members with earned access to the applicable Place
* Authorized administrative users

These restrictions apply to all map, search, page, and API outputs.

Do not expose restricted coordinates through alternative endpoints, exports, logs, or publicly indexed pages.

12.3 Public-Land Overlays

General public-land boundaries may be displayed independently of restricted dispersed Sites.

Displaying a BLM or USFS boundary must not imply camping is legal everywhere inside it.

12.4 External Licensing

Llama Scout’s membership classification controls access within its own application.

It does not eliminate obligations imposed by external data licenses.

Before using a dataset in a proprietary or membership-restricted product, verify that its terms permit the intended storage, transformation, redistribution, and access model.

If a source’s licensing requirements conflict with the intended publication model, do not publish that data until the issue is resolved.

⸻

13. Source Attribution and Answer Display

Every imported published answer must identify its original provider.

The standard Scout Report card uses three rows:

Row 1: Question and icon
Row 2: Current answer
Row 3: Date and source

Example:

Site Length
35 feet
October 29, 2026 · Recreation.gov

The date must represent the appropriate source update or verification date when known.

If only the synchronization date is available, it must be identified as synchronization rather than verification.

Do not fabricate an observation date.

Do not assume every answer within an imported Place originated from the same source.

Source provenance is associated with individual facts, not merely the Place.

⸻

14. Official Changes and Safety Information

Some imported information may be time-sensitive.

Examples:

* Closures
* Fire restrictions
* Seasonal access changes
* Reservation changes
* Facility availability
* Operating hours
* Public safety alerts

These changes must follow the established source and administrative workflows.

An official closure alert may warrant immediate administrative attention.

However, importing an alert should not automatically overwrite unrelated Place information or bypass the existing authorization rules.

The system should preserve:

* Alert source
* Affected location
* Effective dates where provided
* Relevant restrictions
* Review and resolution status

Existing closure management and freshness systems must be reused.

⸻

15. Imported Geometry and Map Layers

External geographic information may represent:

* Individual points
* Campground locations
* Recreation boundaries
* Land-management parcels
* Parking polygons
* Camping polygons

These must not automatically map to the same Llama Scout entity type.

A land-management boundary is reference geography.

A mapped campground polygon may be a candidate for an Area or associated Place geometry.

An individual campsite point may be a candidate Site.

A Resource point may be a standalone service location.

The appropriate relationship depends on the actual source meaning.

Do not automatically publish an arbitrary parcel as a verified camping Area.

Existing Llama Scout geometry editing remains authoritative for administrative corrections.

⸻

16. Source Monitoring and Administration

The administrative import system should provide visibility into the state of each provider.

Relevant information includes:

* Source name
* Integration health
* Last successful synchronization
* Last failed synchronization
* Number of staged records
* New records awaiting review
* Changed records
* Potential duplicates
* Unresolved conflicts
* Records ready for publication
* Publication history

Existing admin styling, layouts, cards, permissions, and error logging should be preserved.

Avoid creating unrelated dashboards when existing modules can be extended.

⸻

17. Data Quality and Coverage

Llama Scout should track both the quantity and quality of imported information.

Useful metrics include:

* Total unique Places
* Total unique Sites
* Total public Resources
* Imported Places without Sites
* Imported Sites without community observations
* Sites with approved community reports
* Sites with recent observations
* Records with unresolved conflicts
* Duplicate candidates
* Records missing essential coordinates
* Source coverage by region
* Source synchronization health

The goal is broad coverage with traceable information.

A large number of markers is useful only when those markers represent meaningful, correctly classified locations.

⸻

18. Importer Verification Scenarios

Every new or modified importer should eventually pass tests covering:

A. Initial import: New source records are stored and normalized without corrupting existing data.

B. Repeated import: Unchanged records are recognized and are not duplicated.

C. Changed field: A changed source value is detected and handled according to reconciliation rules.

D. Missing field: A blank source field does not erase a valid existing answer.

E. Source overlap: RIDB and NPS records describing the same campground can be associated with one canonical Place.

F. Individual Site: A source campsite is correctly associated with its parent Place.

G. Conflicting value: A protected published fact is not silently overwritten.

H. Source removal: An upstream missing record does not automatically delete a published Place.

I. Partial failure: An interrupted import does not corrupt or remove previously stored records.

J. Coordinate handling: Source coordinates retain their correct meaning and reference system.

K. Attribution: Published facts retain the correct provider and date information.

L. Membership restriction: Imported dispersed Sites do not become publicly discoverable.

M. Media handling: Imported photos retain appropriate attribution and are not unnecessarily duplicated.

N. Licensing: Data that lacks suitable redistribution rights is not improperly published.

O. Recovery: An unsuccessful publication operation can be identified and safely recovered without losing source records.

⸻

19. Required Initial Audit

Before implementing new import functionality, Codex must produce an inventory of existing systems.

The audit must identify:

1. Existing source databases and tables.
2. RIDB importer implementation and coverage.
3. NPS configuration and any existing importer code.
4. Existing USFS and BLM integration code.
5. Existing external record identifiers.
6. Existing import history and synchronization tracking.
7. Existing normalization and field mapping.
8. Existing duplicate detection.
9. Existing conflict resolution interfaces.
10. Existing publication rules.
11. Existing source attribution storage.
12. Existing photo import and deduplication.
13. Existing permissions and admin controls.
14. Existing source-related error logging.
15. Existing backup and recovery mechanisms.

The audit must distinguish between:

* Fully implemented functionality
* Partially implemented functionality
* Configured but unused integrations
* Missing functionality
* Legacy or obsolete components

Do not infer a working importer merely because its API key or configuration entry exists.

⸻

20. Implementation Priorities

The proposed order for later implementation is:

Stage 1: Audit and stabilize the existing import and reconciliation infrastructure.

Stage 2: Complete the current RIDB integration, including individual campsite attributes.

Stage 3: Integrate NPS campground and relevant visitor information using existing configured access.

Stage 4: Expand USFS recreation-site and subsite coverage.

Stage 5: Expand BLM recreation points, primitive campsite records, and applicable recreation polygons.

Stage 6: Introduce public-land boundary data for reference mapping.

Stage 7: Add approved commercial campground sources such as Overture Places.

Stage 8: Evaluate OSM and additional state recreation sources according to their licensing and compatibility requirements.

These stages are planning priorities, not authorization to implement all sources at once.

Each provider requires its own reviewed implementation task.

⸻

21. Unresolved Technical Decisions

The initial audit must determine:

* Which physical databases currently store source records.
* How source identifiers are associated with published Places.
* Whether existing imports already support individual Site records.
* Which normalization logic is reusable.
* How import batches are tracked.
* How the current conflict-resolution interface operates.
* Whether current source mappings preserve field-level provenance.
* How existing photos and media are deduplicated.
* How publication changes are audited and recovered.
* Which source APIs are currently operational.
* What source licenses permit within the V3 business model.
* How geographic data is stored and indexed.
* Whether additional database indexes or staging structures are necessary.

Do not invent table names, API credentials, or migration behavior before inspecting the existing repository.

⸻

22. Definition of Success

The V3 import architecture is successful when:

* Every external record retains its source identity.
* Repeated imports recognize existing records.
* Source changes are detected and reviewable.
* Original source information remains recoverable.
* Normalization uses canonical Llama Scout definitions.
* Campgrounds and individual Sites are correctly associated.
* Overlapping providers do not create duplicate Places.
* Conflicts are resolved through the existing administrative system.
* Official information does not erase community observations.
* Source attribution remains accurate.
* Published information obeys membership restrictions.
* Existing V2 importer functionality remains intact.
* New data providers can be added without creating competing reporting systems.

Llama Scout does not need to collect every fact itself. It needs to know where each fact came from, what it means, which location it describes, and whether it can be trusted and published.

⸻

23. Implementation Boundary

This document does not authorize Codex to build or modify any importer immediately.

The first task is to audit the existing integration, storage, normalization, and reconciliation systems.

Subsequent tasks must be small, source-specific, and independently verifiable.

Do not replace existing production databases, import infrastructure, or published records without an approved migration.

Import broadly. Preserve sources. Reconcile carefully. Publish consistently.

Know the place before you go.

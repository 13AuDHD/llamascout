Llama Scout V3

System Architecture Specification

Version: 3.0
Status: Approved architectural direction; existing implementation pending audit
Architecture: Shared PHP backend with MariaDB
Development branch: v3-development

Governing documents:

* docs/PROJECT_SCOPE.md
* AGENTS.md
* Approved Scout Report PDF
* Existing V2 specifications and verified implementation

⸻

1. Architecture Objective

Llama Scout V3 must establish one authoritative system for storing, retrieving, validating, publishing, and displaying information about outdoor locations.

The architecture must support multiple interfaces without maintaining separate implementations of the same reporting rules.

The existing PHP application and MariaDB databases remain the foundation.

V3 will progressively consolidate the existing reporting logic into shared backend services.

These services will eventually support the website, API, mobile applications, and AI integrations.

The immediate objective is not to replace existing working code.

The objective is to eliminate duplicated question definitions, inconsistent answer handling, fragmented reporting logic, and divergent interpretations of the same underlying information.

⸻

2. System Architecture

2.1 Logical Structure

                 LLAMA SCOUT V3
                USER INTERFACES
                       |
        +--------------+--------------+
        |              |              |
     PHP Website    Mobile Apps    AI / External
      (Existing)      (Future)       (Future)
        |              |              |
        |              +------+-------+
        |                     |
        |                 API / MCP
        |                     |
        +----------+----------+
                   |
             SHARED PHP
           BACKEND SERVICES
                   |
       +-----------+-----------+
       |           |           |
    Reporting   Permissions  Publishing
       |           |           |
       +-----------+-----------+
                   |
            CANONICAL DATA
                MariaDB
                   |
          +--------+--------+
          |                 |
      Published         Historical
       Records          Records

All interfaces must use the same authoritative business rules.

The API must not maintain an independent set of Scout Report questions or answers.

The existing PHP pages may call shared services directly.

External integrations may access those services through authenticated API endpoints.

2.2 Separation of Responsibilities

The architecture separates five responsibilities.

Data definitions

Define what questions exist, which entity owns them, what answers are valid, and how they are classified.

Data persistence

Stores Places, Sites, Areas, Resources, approved answers, source information, and historical observations.

Business rules

Controls validation, moderation, publishing, access, membership entitlements, and protected editing.

Presentation

Determines how information is displayed through cards, forms, maps, comparisons, and future application interfaces.

Integration

Handles communication with official data providers and future external applications.

These responsibilities must not be duplicated independently throughout the application.

⸻

3. Canonical Geographic Model

Llama Scout uses four geographic object types.

3.1 Place

A Place is the parent geographic entity.

It represents a defined location or property that may contain one or more individual Sites.

Examples:

* Target Tree Campground
* Durfield BLM Parcel
* A developed campground
* An RV park
* A private campground
* A commercial location permitting overnight parking
* A designated dispersed camping location

A Place stores its own Place-level information.

Examples include:

* Name and type
* Managing agency
* Official address and contacts
* Shared property information
* Place-wide access rules
* General road access
* Official restrictions
* Applicable closure information
* Shared amenities and facilities

Question ownership must follow the approved Scout Report specification.

A Place must not contain duplicated Site-level answers simply to support a particular interface.

Place creation

Community-created Places are submitted together with their first Site.

Imported Places may exist without any community-created Site.

Imported records may also contain official individual Sites.

A Place can contain multiple Sites and optional Areas.

3.2 Site

A Site belongs to exactly one Place.

A Site represents an individual location someone can camp, park, or otherwise stay.

Examples:

* Rocky Camp within Durfield BLM Parcel
* Site 14 at Target Tree Campground
* An individual RV parking space
* A designated dispersed campsite

A Site has one geographic point representing its actual location.

Site-specific information may include:

* Campsite dimensions
* Parking dimensions
* Tent pad dimensions
* Ground surface
* Vehicle accommodation
* Individual utilities and amenities
* Connectivity
* Sensory conditions
* Environmental observations
* Safety observations
* Historical visits and reports

Officially supplied Site information should be retained and used wherever reliable.

Community reports supplement that information with observations collected during actual visits.

A Site may have zero or many community reports.

A Site must never be duplicated solely because another visitor submits a report.

3.3 Area

An Area is an optional geographic subdivision of a Place.

Areas use polygon geometry.

Examples:

* Camping areas
* Parking areas
* Separate sections of a dispersed camping location
* Facility areas

A Place may have multiple Areas.

Sites may be associated with an Area, but an Area is not required for a Site to exist.

A Site should not be forced into an Area when its relationship is unknown.

Areas are not a mandatory level in the Place/Site hierarchy.

Area permissions

Area creation, editing, and geometry management are restricted to authorized Master Scouts, Admins, and Owners according to existing permissions.

Ordinary community members do not submit Area reports during the initial implementation.

Area-level attributes, photos, and future observations must remain architecturally possible.

An Area must not become a second copy of Place or Site data.

3.4 Resource

A Resource represents a useful service or facility rather than an individual camping location.

Examples:

* Water fill station
* RV dump station
* Propane service
* Public showers
* Other essential overlanding services

Resources may exist independently or be associated with an existing Place.

A Resource can represent multiple services at one location.

Resources use a simplified collection of questions rather than the entire Scout Report.

Relevant attributes may include:

* Resource name
* Service types
* Coordinates
* Fees
* Hours of operation
* Access requirements
* Photos
* Last verification date
* Contributor or source

Resources are public information.

Members submit Resources through the existing Add Place entry point using a Resource-specific form.

Resources follow normal moderation procedures.

Initially, Resource information may be displayed through a map card without requiring a dedicated public detail page.

3.5 Geographic Relationship Rules

PLACE
 |
 +-- SITE
 |
 +-- SITE
 |
 +-- SITE
 |
 +-- AREA (optional polygon)
 |     |
 |     +-- Associated Sites
 |
 +-- AREA (optional polygon)
RESOURCE
 |
 +-- Independent location
       OR
 +-- Associated with an existing Place

These are logical relationships, not proposed database table definitions.

The repository audit must determine how they map to the existing schema.

Existing geometry, identifiers, and relationships must be preserved during migration.

⸻

4. Canonical Question Registry

4.1 Single Definition Per Question

Every Scout Report question must have one authoritative definition.

All reporting interfaces must consume these definitions.

The canonical registry should support:

Property	Purpose
Stable identifier	Permanently identifies a question
Label	Displayed question name
Entity ownership	Place, Site, Area, or Resource
Group	Scout Report section
Display order	Position within its group
Answer type	Text, number, selection, etc.
Answer options	Permitted values
Validation	Accepted answer constraints
Visibility	Public or Community
Applicability	When the question should appear
Status	Active, retired, or deprecated
Version information	Tracks approved definition changes

Existing canonical question identifiers should be reused wherever possible.

Do not generate replacement identifiers simply because the registry is reorganized.

4.2 Question Ownership

Each question must belong to its appropriate entity.

Examples:

Place: Land manager, official contact information, campground-wide access rules.

Site: Cellular reception, nighttime noise, Site dimensions, individual Site conditions.

Area: Attributes specific to an administrative polygon.

Resource: Fees, service availability, operating hours.

A question must not be duplicated across entity types merely to make it appear in multiple views.

If an answer must appear elsewhere, the interface should retrieve the original authoritative information.

4.3 Question Evolution

The registry must support adding, revising, reordering, and retiring questions.

Changes must not destroy historical answers.

Stable identifiers and version-aware interpretation should be used when question definitions change.

Historical answers must retain enough context to be interpreted correctly.

The existing approved Scout Report PDF is authoritative for question organization until a new approved specification supersedes it.

⸻

5. Scout Reports and Observations

5.1 Reports Are Historical Contributions

A Scout Report represents information submitted by a contributor.

Each report may contain answers to many questions or only a few.

Reports retain:

* Contributor identity
* Target Place or Site
* Visit date when applicable
* Submission timestamp
* Moderation status
* Approval information
* Submitted answers
* Associated photos and applicable evidence

Approved report history must be preserved.

Historical information must not be destructively overwritten by subsequent reports.

5.2 Partial Reports

Partial reports are valid.

A member may submit a report containing only cellular reception information.

Once approved, that answer can become the newest published information for the applicable question.

Other previously approved answers remain unchanged.

Missing information is not a replacement answer.

The system must distinguish between:

* Not answered
* Explicitly answered No
* Unknown
* Not applicable
* Not tested
* Other defined answer states

Actual supported answer states must be reconciled with the approved question registry.

5.3 Current Answer Selection

The published Scout Report displays the latest applicable approved answer for each question.

Current answers are assembled from approved information across multiple reports.

Example:

Rocky Camp
Report A:
  Verizon: 5/5
  Night noise: Quiet
  Site length: 35 ft
Report B:
  Verizon: 3/5
Report C:
  Night noise: Moderate
Current Summary:
  Verizon: 3/5       (Report B)
  Night noise: Moderate (Report C)
  Site length: 35 ft (Report A)

Current answers must reference their original records.

The system should use actual observation dates where applicable rather than treating submission time as the visit date.

Precise tie-breaking rules for overlapping observations must be established during the reporting audit.

Do not create a disconnected second source of authoritative answers.

A cached or materialized current view is permissible only when it can be reliably rebuilt from canonical records.

5.4 Conflicting Observations

Different contributors may report different conditions.

The system must preserve those reports without automatically deciding which contributor is correct.

Example:

Seven reports indicate Verizon reception of 5/5.

One newer approved report indicates 1/5.

The newest applicable approved observation becomes the current displayed result.

Earlier observations remain available through history and comparison.

Conflicting observations alone are not grounds for silently rejecting valid submissions.

5.5 Historical Comparison

Preserve the existing report comparison functionality.

Users with appropriate access should be able to compare:

* Different reports for one Site
* Conditions across different visits
* Different Sites
* Relevant information across Places

Comparison tools must use canonical information.

Do not maintain independent comparison-specific copies of question definitions.

⸻

6. Official Facts and Community Observations

6.1 Official Information

Official information may originate from:

* NPS
* RIDB
* USFS
* BLM
* State recreation agencies
* Other approved data providers

Official information can populate both Place and Site attributes.

Import as much accurate and reusable information as the source provides.

Do not force contributors to repeat already available official facts.

6.2 Community Information

Community reporting focuses primarily on information gathered through firsthand experience.

Examples:

* Cellular reception
* Starlink performance
* Nighttime conditions
* Sensory observations
* Actual Site accessibility
* Unexpected hazards
* Environmental conditions
* Current Site experiences

Community observations retain contributor attribution and visit dates.

6.3 Source Provenance

Every imported answer must retain its source.

Source metadata should distinguish:

* Original provider
* Original record identifier
* Original update or verification date
* Import or synchronization date
* Applicable source attribution
* Relevant license information

Do not display the import date as though it were the date the original information was verified.

6.4 Source Conflicts

Conflicts between official information and community observations must respect question ownership and existing moderation rules.

Authoritative Place facts cannot be automatically replaced by ordinary member submissions.

Members report Place-level problems through the existing administrative workflow.

Approved Site observations contribute to Site history.

Imported changes must pass through existing normalization and reconciliation processes.

Do not invent a universal source-priority algorithm before auditing the current conflict-resolution system.

⸻

7. Published Data and Import Architecture

7.1 Data Separation

Llama Scout maintains a distinction between externally sourced data and its own published canonical information.

The existing source-specific databases and staging systems must be preserved.

The intended logical structure is:

EXTERNAL PROVIDERS
  |
  +-- NPS
  +-- RIDB
  +-- USFS
  +-- BLM
  +-- State Agencies
  +-- Other Sources
  |
SOURCE STORAGE
  |
CHANGE DETECTION
  |
NORMALIZATION
  |
CONFLICT RESOLUTION
  |
APPROVAL / PUBLICATION
  |
LLAMA SCOUT CANONICAL DATA
  |
SHARED PHP SERVICES
  |
WEBSITE / API / MOBILE / AI

The audit must identify the existing physical databases, tables, importers, and reconciliation interfaces.

Do not create new parallel databases without demonstrating why existing infrastructure cannot meet the requirement.

7.2 Import Stages

Each provider’s import process should support:

1. Retrieving source records
2. Preserving source identifiers
3. Recording import activity
4. Detecting new and changed records
5. Recognizing missing upstream records
6. Matching existing Places and Sites
7. Normalizing source fields
8. Identifying conflicts
9. Reviewing unresolved differences
10. Publishing approved changes

Existing reconciliation functionality must be reused.

7.3 Change Detection

Subsequent imports must recognize previously imported records.

Where possible, matching should use stable external source identifiers.

Changes should be visible to administrators before conflicting published information is replaced.

The system must distinguish:

* New record
* Changed record
* Unchanged record
* Potential duplicate
* Conflict requiring review
* Record no longer supplied by the source

An upstream deletion must not automatically delete a published Llama Scout record.

7.4 Duplicate Prevention

New imported records must be checked against existing published and staged records.

The existing moderation and nearby-map workflows must continue helping identify duplicate Places and Sites.

Potential duplicates must be flagged rather than automatically merged.

The goal is to prevent duplicate publication.

The system must still be capable of detecting and resolving duplicate or ambiguous source records.

7.5 Public-Land Boundaries

Public-land polygons are geographic reference data.

A BLM or USFS boundary does not, by itself, establish that overnight camping is permitted.

The system must distinguish:

* Land ownership or management
* Camping regulations
* Officially designated recreation areas
* Recognized Llama Scout Places
* Documented individual Sites

Do not automatically create approved camping Sites throughout an entire land-management parcel.

⸻

8. Authorization Architecture

8.1 Two Independent Permission Questions

Authorization must determine:

Can the user access this information?

Can the user perform this operation?

Data visibility and operational permissions are separate concepts.

Membership access does not grant moderation authority.

Scout or administrative authority must not be inferred solely from membership status.

8.2 Role System

Preserve the existing hierarchy:

Owner
  |
Admin
  |
Master Scout
  |
Scout
  |
Ordinary Member

Owner remains above Admin.

Owner-only tools must retain their existing restrictions.

Specific operation permissions must follow existing implementation rules rather than assuming every higher role automatically inherits every lower-role action.

Scout selection, qualifications, reputation, and complimentary membership benefits remain unchanged.

8.3 Question-Level Visibility

Each question is classified as:

* Public
* Community

These classifications must be enforced consistently.

Visibility cannot be implemented only by hiding frontend components.

The backend must determine whether the requested information can be returned.

8.4 Location-Level Visibility

Place and Site visibility must also be supported.

All dispersed camping Site information is restricted according to established membership rules.

This includes imported primitive and dispersed campsite records.

Authorized users are:

* Paid members
* Members with complimentary full membership
* Free members with earned access to the applicable Place
* Authorized administrative users

Ordinary free members without earned access cannot view restricted dispersed Sites.

Unauthenticated visitors cannot discover them.

This restriction applies to all supported interfaces.

8.5 Earned Place Access

A qualifying approved contribution may grant a free member continuing access to an entire Place.

Qualifying contributions include:

* Creating an approved new Place with its first legitimate Site
* Creating the first legitimate new Site within an existing Place

The entitlement applies to existing and future Sites within that Place.

Approval must be recorded before the entitlement becomes active.

One-question updates do not automatically qualify.

The rules for substantial first reports on previously imported Sites require an explicit final decision before implementation.

8.6 Consistent Enforcement

Access restrictions must apply to:

* Map markers
* Coordinates
* Search results
* Filters and sorting
* Public detail pages
* Photo access where restricted
* Historical reporting
* API responses
* AI integration responses
* Future mobile synchronization
* Public sitemaps and indexing

A restricted Site must not be indirectly revealed through search matches, counts, exports, or alternate endpoints.

The same central authorization rules must govern all interfaces.

⸻

9. Moderation and Publishing

9.1 Existing System Is Authoritative

Preserve the existing moderation system.

Do not introduce a new independent moderation engine.

Every contribution follows the existing approval process.

Existing Admin and Owner self-approval permissions remain intact.

Scout and Master Scout self-approval restrictions must remain enforced.

9.2 Submission Lifecycle

The logical submission lifecycle includes:

Draft
  |
Submitted
  |
Moderation
  |
 +-- Return for correction
 |
 +-- Reject
 |
 +-- Approve
       |
    Published

Exact existing status values and transitions must be verified during the repository audit.

Do not replace them with new values unnecessarily.

9.3 Published Place Editing

Once a Place is published, ordinary members cannot directly edit authoritative Place information.

Changes are proposed through Report a Problem.

Authorized administrative users investigate and resolve those issues.

Place modifications must preserve existing administrative controls and audit information.

9.4 Site Reporting

Members may submit additional observations about existing Sites.

Approved observations become part of the Site’s history.

Each approval updates only the applicable canonical current answers.

Other approved information remains available.

9.5 Administrative Corrections

Administrative corrections should preserve appropriate change history and attribution.

Do not silently rewrite historical community observations to correct a current published fact.

Any necessary migration of historical moderation records requires separate review.

⸻

10. Presentation Architecture

10.1 Presentation Is Not Data Ownership

A question may be presented differently in different interfaces.

For example:

* Add Place displays an input.
* Moderation displays submitted and existing values.
* Admin editing displays authorized controls.
* A Place page displays an informational card.
* A comparison tool displays historical values.
* An API returns structured information.

All interfaces must use the same canonical question identity and answer interpretation.

10.2 Standard Scout Report Cards

Preserve the existing Llama Scout card design.

The preferred structure is:

Row 1: Question label and icon
Row 2: Current answer
Row 3: Date and contributor/source

Examples:

October 29, 2026 · Member A

October 29, 2026 · National Park Service

Imported source dates must be correctly distinguished from synchronization timestamps.

The interface may provide historical comparisons without duplicating the answer data.

10.3 Map Presentation

The existing map remains the core geographic presentation system.

V3 will eventually make the map the homepage.

The map must support the existing marker types, optional Areas, public Resources, and membership-restricted Sites.

Featured status remains an attribute rather than a separate Site type.

The existing design system, CSS, fonts, branding, and responsive behavior must be retained.

10.4 Resource Cards

Resources use simplified map cards.

A Resource card may display:

* Name
* Photo
* Service types
* Fees
* Hours
* Location
* Access restrictions
* Verification date
* Source or contributor

A full standalone Resource page is not required for the initial implementation.

⸻

11. API and External Interface Architecture

11.1 Shared Services First

Before exposing new API endpoints, reporting and authorization logic must be centralized into reusable backend services.

The API must reuse those services.

It must not independently query raw tables in ways that bypass canonical reporting or permission rules.

11.2 Initial API Capabilities

Potential read-only capabilities include:

* Searching visible Places and Sites
* Retrieving authorized Place details
* Retrieving authorized Site summaries
* Retrieving Scout Report information
* Accessing permitted report history
* Discovering public Resources
* Retrieving applicable map information

Exact endpoints, response schemas, and authentication mechanisms are future design tasks.

11.3 Future Authenticated Actions

Future interfaces may support:

* Saving favorite Places
* Preparing Scout Reports
* Submitting contributions
* Managing accounts
* Retrieving member-specific collections

Write operations must use the same validation, moderation, and authorization services as the website.

11.4 AI Integrations

ChatGPT, Gemini, and other supported AI integrations must access Llama Scout through authorized interfaces.

They must not receive direct database access.

Initial integrations should be read-only.

Restricted data must only be returned when the requesting user has the appropriate entitlement.

11.5 Mobile Applications

Future mobile applications may cache permitted data and prepare reports offline.

The architecture should support synchronization without creating competing authoritative data.

Offline drafts remain unpublished until submitted and approved.

Cached member-only data requires appropriate local protection and synchronization controls.

⸻

12. Future Booking Architecture

The future booking platform may operate through:

bookings.llamascout.com

Booking services should reuse existing Place and Site identities.

Do not duplicate campground records solely because they become bookable.

Booking-specific information may include:

* Host identity and verification
* Availability
* Rates
* Reservation restrictions
* Payments
* Cancellations
* Host payouts

Host operational permissions must remain separate from community reporting permissions.

A property owner must not automatically acquire permission to alter independent Scout Reports or administrative Place facts.

The booking platform is outside the initial V3 reporting rebuild.

⸻

13. Existing Infrastructure Preservation

The following must be audited and reused wherever possible:

* PHP configuration and connection handling
* Existing MariaDB schemas
* Authentication and session handling
* Passkeys and other existing login methods
* Role and membership permissions
* Existing canonical reporting components
* Existing answer storage
* Existing moderation and publishing logic
* Import staging databases
* Source reconciliation interfaces
* Existing photo storage
* Current map and map editor
* Existing card rendering
* Existing JavaScript and CSS
* Shared templates and components
* Error logging and administrative tools

This specification does not establish new physical table names or mandate new file structures.

Those decisions depend on verified repository findings.

⸻

14. Architectural Invariants

The following rules must remain true throughout V3 development.

1. A Site belongs to one Place.
2. A Place may have many Sites.
3. Areas are optional.
4. Resources do not require camping Sites.
5. Questions have one canonical definition.
6. Question ownership is explicit.
7. Historical reports are preserved.
8. Missing answers do not erase existing answers.
9. Current answers derive from approved canonical data.
10. Source provenance is retained.
11. Official imports cannot silently overwrite protected information.
12. Existing moderation controls remain authoritative.
13. Owner-only permissions remain protected.
14. Dispersed Site restrictions are enforced server-side.
15. The existing website and future API share business rules.
16. Presentation components do not become independent sources of truth.
17. New importers must use source reconciliation.
18. Working V2 functionality must not be discarded without explicit approval.

⸻

15. Implementation Boundaries

This document defines the intended architecture.

It does not authorize Codex to:

* Rewrite the entire application
* Replace the existing database
* Create a separate reporting system
* Remove working V2 features
* Migrate production data without approval
* Redesign memberships or Scout roles
* Replace existing authentication
* Deploy a new API
* Build mobile applications
* Implement bookings
* Change production configuration

Implementation must follow separately approved tasks.

Each task must identify what is being modified and how the change will be verified.

⸻

16. Required Repository Audit

Before implementing architectural changes, Codex must identify the current implementation of:

1. Place, Site, and Area persistence
2. Existing database relationships
3. Question definitions and registries
4. Answer storage and retrieval
5. Report submission and revision
6. Moderation and publishing
7. Current-answer selection
8. Historical report comparison
9. Membership and earned-access checks
10. Scout, Master Scout, Admin, and Owner permissions
11. Import source databases and connectors
12. Data normalization and conflict resolution
13. Frontend question rendering
14. Place page composition
15. Existing map and geometry management

The audit must identify duplicate logic and inconsistent implementations.

It must distinguish existing working services from missing capabilities.

No schema changes or new reporting architecture should be implemented until the relevant findings have been reviewed.

⸻

17. Architectural Success Criteria

V3’s reporting architecture is successful when:

* Every reporting interface obtains questions from the same authoritative definitions.
* Place and Site answers remain correctly separated.
* Official records and community observations retain appropriate ownership and provenance.
* Partial reports update only the questions actually answered.
* Historical reports remain available.
* Existing comparison tools function correctly.
* Permission checks behave consistently across every interface.
* Imported information passes through the established reconciliation process.
* New questions can be introduced without independently modifying every reporting page.
* The website can evolve toward a map-first experience without losing existing functionality.
* Future API consumers can access the same shared business rules.

A single source of truth does not require a single interface or a single physical database table.

It requires one authoritative definition of each fact, consistent ownership, controlled updates, reliable history, and shared rules governing how that information is accessed.

That is the foundation of Llama Scout V3.

⸻

Architecture principle:

One data foundation. Multiple interfaces. Consistent information. Preserved history.

Know the place before you go.

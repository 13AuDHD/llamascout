Llama Scout V3

Project Scope and Product Vision

Project: Llama Scout
Version: 3.0
Status: Architecture defined; implementation pending
Existing technology: PHP, MariaDB, JavaScript, HTML, CSS
Website: https://llamascout.com
Tagline: Know the place before you go.

⸻

1. Executive Summary

Llama Scout is a community-powered outdoor recreation information platform designed to help travelers understand locations before visiting them.

The platform collects structured information about campgrounds, dispersed camping locations, individual campsites, overnight parking, and essential travel resources.

Unlike traditional campground review platforms, Llama Scout emphasizes factual observations, measurable conditions, historical reporting, and practical information rather than subjective reviews.

The goal is to help users determine whether a specific location meets their individual needs, equipment limitations, accessibility requirements, and environmental preferences.

Version 3 represents a significant architectural evolution of the existing Llama Scout V2 application.

V3 is not a complete rewrite.

The existing website, PHP backend, databases, user accounts, membership system, administration, moderation, map, styling, branding, photos, and other functioning systems must be preserved and reused.

The primary architectural objective is to establish one canonical reporting foundation that supports all existing interfaces and can eventually serve mobile applications, AI integrations, and third-party services.

V3 also introduces a map-first product experience, expanded geographic coverage, community-contributed Resources, and a revised approach to displaying and controlling access to information.

All development must be incremental, backward-compatible where possible, and independently verifiable.

⸻

2. Core Product Philosophy

2.1 Information, Not Reviews

Llama Scout is not intended to become a conventional campground review website.

Different travelers have different vehicles, equipment, experience levels, physical capabilities, and preferences.

A location that is unsuitable for one traveler may be ideal for another.

A rocky road requiring high ground clearance should not receive a poor overall rating simply because a visitor arrived with an unsuitable vehicle.

Instead, Llama Scout should document the actual conditions.

Examples include:

* Road surface, grade, and accessibility
* Vehicle clearance and size limitations
* Campsite and parking dimensions
* Cellular connectivity
* Environmental and sensory conditions
* Nighttime noise and artificial lighting
* Amenities and available services
* Safety hazards
* Camping restrictions and requirements
* Actual observations from previous visits

Llama Scout provides the information necessary for users to make their own decisions.

Existing ratings and historical data must be preserved during migration. Generic overall star ratings should not become the primary basis of discovery or recommendations.

2.2 One Source of Truth

Every question, answer, observation, and published fact must have a clearly defined authoritative source within the Llama Scout system.

Question definitions must not be independently duplicated across multiple interfaces.

The database remains the authoritative storage system, with shared backend services responsible for applying canonical definitions and business rules.

Different interfaces may display information differently, but they must retrieve and interpret the same underlying information.

Changing a question definition should not require manually editing multiple unrelated PHP pages.

2.3 Preserve What Works

Existing functionality must be treated as an asset.

Working V2 systems should not be rewritten merely because a newer architectural approach is available.

New functionality should extend the existing application wherever practical.

The objective is to correct architectural problems without sacrificing previous development.

2.4 Community Knowledge

Official data establishes a useful foundation.

Community contributions provide detailed, real-world information that official databases frequently lack.

Llama Scout should use both.

The platform should not force members to repeat information already available from reliable official sources.

Members should instead focus on documenting individual Sites and conditions they personally experienced.

⸻

3. Existing V2 Systems to Preserve

Unless an approved V3 requirement explicitly changes a particular behavior, preserve all existing systems.

This includes:

* Existing PHP application and backend services
* Existing MariaDB databases and historical records
* Website branding, logos, icons, and typography
* Manrope fonts and existing CSS design system
* Responsive desktop, tablet, and mobile layouts
* Navigation, headers, footers, and shared components
* Existing interactive maps and marker systems
* Map layers, polygons, and map editing tools
* User accounts and authentication
* Existing membership subscriptions and permissions
* Scout, Master Scout, Admin, and Owner roles
* Scout qualification and appointment systems
* Existing moderation and approval workflows
* Administrative dashboard and modules
* Existing Site and Place reporting functionality
* Historical Scout Reports and comparison tools
* Freshness indicators and last-visit information
* Photo storage, galleries, and photo ownership
* External data importers and reconciliation systems
* Existing source databases
* Closure reporting and administrative resolution
* Featured and affiliate status systems
* Existing shop and sales functionality
* Security, error logging, and maintenance systems
* Existing external API integrations and configurations

This list is not exhaustive.

Codex must inspect the repository to identify additional functionality before any migration or replacement.

No existing feature may be removed merely because it is not explicitly mentioned in this document.

⸻

4. Platform Architecture

4.1 Shared Backend Architecture

Llama Scout V3 will retain its existing PHP and MariaDB foundation.

The architecture will progressively centralize data access, validation, authorization, and business rules into shared backend services.

These services will support:

1. The existing PHP website
2. Future API endpoints
3. Android applications
4. iOS applications
5. ChatGPT integrations
6. Google Gemini integrations
7. Authorized third-party applications

The website is one interface to Llama Scout data, not the sole owner of its business logic.

The API must not become a separate implementation of the reporting system.

Initially, the existing PHP pages may continue using shared backend services directly.

A complete frontend rewrite or immediate migration of every website operation to REST endpoints is not required.

4.2 Future API

The planned API must support controlled access to canonical Llama Scout data.

It should eventually provide:

* Place and Site discovery
* Geographic searches
* Authorized Scout Report retrieval
* Historical observation access
* Search filtering and comparison
* Resource discovery
* Member authentication
* Account-specific entitlements
* Mobile application support
* External AI integrations

Initial external integrations should be read-only.

Write operations may be introduced later after authentication, authorization, validation, and moderation behavior have been established.

The API must enforce permissions server-side.

4.3 Future Mobile Applications

Llama Scout will eventually support Android and iOS applications.

The existing website remains the primary application during initial V3 development.

Future mobile applications should support offline caching, offline report preparation, GPS, photography, synchronization, and push notifications.

These capabilities must be considered during architectural design but are not immediate implementation requirements.

⸻

5. Geographic Data Model

Llama Scout V3 defines four primary geographic concepts.

5.1 Place

A Place is the primary location record.

Examples:

* Developed campground
* National park campground
* Dispersed camping location
* BLM camping parcel
* National forest camping location
* RV park
* Private campground
* Commercial overnight parking location
* Travel center permitting overnight stays

A Place contains information that applies to the location as a whole.

Examples include management agency, official contact information, address, campground-wide rules, shared facilities, and general access information.

Questions must be assigned to the correct ownership level according to the approved Scout Report specification.

A Place can contain one or many Sites.

A Place can also contain multiple optional mapped Areas.

5.2 Site

A Site is an individual location within a Place.

Examples:

* Numbered campground campsite
* Dispersed campsite
* RV parking space
* Overnight vehicle parking location
* Designated camping spot

Every Site belongs to exactly one Place.

A Site has one geographic marker representing its location.

Site-level information includes its applicable dimensions, facilities, connectivity, sensory observations, experience data, and other Site-specific attributes.

Site information must not be duplicated at the Place level merely for convenience.

A Site may have multiple historical Scout Reports from different visitors.

5.3 Area

An Area is an optional geographic subdivision within a Place.

Areas are represented by polygons.

Examples:

* Camping area
* Parking area
* Separate dispersed camping section
* Facility area
* Other designated geographic subdivision

A Place can contain multiple Areas.

A Site may be associated with an Area but does not require one.

Areas are not a mandatory level between Places and Sites.

Area creation, geometry editing, and management are restricted to authorized Admins and Master Scouts, subject to existing higher-level permissions.

Ordinary members do not submit Area observations during the initial V3 implementation.

The existing polygon and map editor functionality must be preserved.

5.4 Resource

A Resource is a standalone or Place-associated travel service.

Examples:

* Potable water fill
* RV dump station
* Propane service
* Public showers
* Other practical camping and overlanding resources

Resources do not require a complete Place and Site Scout Report.

They use a smaller, purpose-specific collection of questions.

A Resource should include relevant information such as its location, services, fees, operating hours, access requirements, verification date, and photos.

Resources are public information.

Members may submit Resources through the existing Add Place entry point.

Selecting Resource activates the appropriate simplified form.

Submissions follow existing moderation procedures.

Initially, Resources can be displayed through map cards rather than requiring full standalone detail pages.

⸻

6. Place and Site Creation

6.1 Community-Created Places

When a member creates a new Place, the Add Place workflow also collects the first Site.

Example:

Place: Durfield BLM Parcel
Site: Rocky Camp

Additional Sites may subsequently be added to Durfield without recreating the Place.

All submitted Places require moderation before publication.

Rejected or incomplete submissions may be returned for correction and resubmitted.

Unapproved submissions must not appear publicly.

6.2 Imported Places

An imported Place may exist before any community Scout Report is submitted.

Official data may already contain individual Site records.

These records should be imported when reliable source data is available.

Do not fabricate empty Site records to satisfy a presumed requirement that every imported Place have a Site.

A member visiting an existing imported Site can submit observations about that Site.

If the Site is not yet recorded, the member may propose a new Site under the existing Place.

6.3 Place Changes After Publication

Published Place-level facts cannot be directly edited by ordinary members.

Members must use the existing Report a Problem workflow.

Problems remain available for administrative review until verified and resolved.

Only authorized Master Scouts, Admins, and Owners may directly edit published Place information, subject to existing permissions.

New Site observations follow the Update This Place workflow and the existing moderation system.

⸻

7. Canonical Scout Report System

7.1 Central Question Registry

The canonical question registry must define each question once.

Definitions must support:

* Stable question identifier
* Question label
* Description or guidance where applicable
* Answer type
* Permitted answer options
* Validation requirements
* Place, Site, or Area ownership
* Section and group
* Display order
* Public or Community visibility
* Applicable dependencies and conditions
* Active, deprecated, or retired status

Question definitions must support future additions and revisions without requiring duplicated implementation across interfaces.

The approved Scout Report PDF remains authoritative for question names, grouping, ordering, and ownership unless explicitly superseded by an approved decision.

7.2 Unified Reporting Interfaces

The following existing interfaces must use the same canonical definitions and underlying data:

* Add Place
* Suggest Update / Update This Place
* Administrative editing
* Moderation
* Published Scout Report
* Site editing
* Authorized Area editing

These interfaces may have different layouts and permissions.

They must not independently redefine questions, answer values, or ownership rules.

Existing reporting workflows must remain functional throughout migration.

7.3 Report History

Every approved Scout Report must retain its original observations.

Historical reports must not be destructively overwritten by subsequent submissions.

The system should retain the contributor, visit date, submission date, approval information, and original answers as applicable.

Existing historical comparison functionality must be preserved and connected to canonical records.

7.4 Current Published Answers

The published Scout Report displays the latest approved answer for each question.

A report containing only one changed answer updates that answer without deleting unrelated information from previous reports.

An unanswered question must never be interpreted as an explicit negative response.

The system must distinguish missing answers, actual negative answers, and other defined answer states.

Current displayed information should be derived from canonical approved records, not maintained as a disconnected second source of truth.

7.5 Imported Answers

Official Site and Place attributes should be populated wherever reliable source information exists.

Relevant imported fields may include:

* Tent pad dimensions
* Parking dimensions
* Site dimensions
* Campsite type and capacity
* Surface information
* Vehicle restrictions
* Hookups and facilities
* Fees and reservation details
* Official access rules

Every imported fact must retain its source information.

Source updates and community observations must not silently overwrite protected Place facts.

Conflicts must follow the existing reconciliation and moderation rules.

7.6 Answer Presentation

Scout Report cards should preserve Llama Scout’s existing card-based design.

The standard card structure is:

Row 1: Question label and icon
Row 2: Current approved answer
Row 3: Observation date and contributor, or date and source

Examples:

* October 29, 2026 · Member A
* October 29, 2026 · National Park Service

For imported records, distinguish the source’s actual update or verification date from Llama Scout’s synchronization date.

Historical reports and comparison tools must remain accessible.

The current Site summary is assembled from the most recent approved information for each question.

7.7 Data Freshness

Preserve the existing freshness system.

Continue displaying relevant last-visit information, contributor distinctions, and existing administrative freshness indicators.

Do not replace the existing freshness model until its implementation has been audited.

⸻

8. Membership, Roles, and Authorization

8.1 Data Classification

Llama Scout uses two question-level data classifications:

Public: Available without paid membership.

Community: Protected information available according to membership entitlements.

Classification must be centrally defined and changeable without rewriting multiple interfaces.

Location-level visibility must also be supported.

8.2 Dispersed Camping Access

All dispersed camping Site records are restricted to authorized users.

This includes community-created Sites and imported primitive or dispersed Sites.

Authorized access includes:

* Paid members
* Members with complimentary full membership
* Free members with earned access to the applicable Place
* Authorized administrative roles

Unauthenticated visitors and ordinary free members without the appropriate entitlement cannot discover restricted Sites.

Restrictions apply to maps, search results, markers, coordinates, previews, public indexes, API responses, and future integrations.

Do not substitute fake coordinates or deliberately inaccurate locations.

Unauthorized Site information must simply not be returned.

Featured status does not change these restrictions.

8.3 Public Information

Ordinary publicly listed campgrounds, designated public campground information, and Resources remain freely accessible.

Information from external sources may be assigned public visibility when permitted by source terms and V3’s publication rules.

Public-land boundaries may remain visible independently of restricted dispersed camping Sites.

8.4 Earned Place Access

Free members can earn access to a Place through qualifying approved contributions.

Qualifying contributions include creating an approved new Place with its first legitimate Site, or creating the first legitimate new Site within an existing Place.

Earned access applies to the entire Place, including subsequently added Sites and reports.

A one-question update does not automatically qualify.

Contribution quality and legitimacy must be verified through moderation.

The treatment of a first substantial report on an already imported individual Site requires an explicit entitlement rule before implementation.

8.5 Existing Community Roles

Preserve the existing role system and its established permission checks.

The role hierarchy includes:

1. Owner
2. Admin
3. Master Scout
4. Scout
5. Ordinary members

Scout and Master Scout are appointed community positions, not automatically earned membership tiers.

They have existing qualification and retention requirements.

They receive complimentary membership benefits and applicable additional benefits.

Their selection, reputation requirements, privileges, and moderation responsibilities must not be redesigned during V3 development.

Owner retains authority over existing owner-only functionality.

8.6 Moderation

Every contribution continues through the existing moderation system.

Scouts and Master Scouts cannot approve their own submissions.

Existing Admin and Owner self-approval privileges remain intact.

Approved reporting information must retain its contributor and moderation history.

No role or permission migration may weaken existing authorization rules.

⸻

9. External Data Sources and Imports

Llama Scout intends to maximize coverage using reliable official and licensed external sources.

Existing and prospective sources include:

* Recreation Information Database (RIDB)
* National Park Service
* U.S. Forest Service recreation datasets
* Bureau of Land Management recreation datasets
* BLM and USFS land-management boundaries
* USGS protected-area datasets
* State park and recreation databases
* Overture Maps
* OpenStreetMap
* Other licensed campground and RV park directories

Existing integrations must be audited before being expanded or replaced.

9.1 Source Storage

External source records must be preserved separately from canonical published Llama Scout information.

Use existing source-specific storage and importer infrastructure wherever practical.

Each record should retain relevant external identifiers, source timestamps, provenance, and applicable license information.

9.2 Import Workflow

All external imports should follow a consistent process:

1. Retrieve source records.
2. Preserve original source information.
3. Identify additions, changes, and missing records.
4. Normalize source fields into canonical definitions.
5. Detect existing Places and Sites.
6. Reconcile conflicting information.
7. Publish approved changes to Llama Scout.
8. Retain enough history to investigate and compare future changes.

Imports must not create duplicate Places or Sites simply because multiple sources describe the same location.

Conflicts must not be silently discarded or overwritten.

Upstream record removal must not automatically delete published Llama Scout information.

The existing reconciliation tools must be reused.

9.3 Coverage Strategy

Llama Scout should aim for broad geographic coverage.

The goal is to populate campground and Site information wherever dependable sources exist so community members can concentrate on the information that requires on-site observation.

A large collection of official campground records is valuable even before those locations receive community Scout Reports.

However, imported existence must not be confused with community verification.

Public-land ownership must not automatically be treated as proof that dispersed camping is permitted.

⸻

10. V3 Website Experience

10.1 Map-First Homepage

The Llama Scout map will become the primary homepage experience.

Users should be able to begin exploring immediately without navigating through a separate promotional landing page.

The map should support campground discovery, Site selection, Resources, markers, search, filters, and authorized map layers.

Preserve existing map functionality and visual conventions wherever practical.

10.2 About Page

The useful informational content from the existing index.php and about.php should be consolidated into a clean About page.

The existing homepage layout will be replaced by the map-first experience.

Preserve important existing content, branding, links, and appropriate redirects.

10.3 Reporting Presentation

Preserve the existing Llama Scout cards, iconography, typography, and design language.

Improve consistency, readability, answer attribution, and navigation without replacing the entire visual design.

The interface should make essential information easy to scan while preserving access to detailed Scout Reports and historical comparisons.

10.4 Map Discovery

The map should progressively support:

* Public campgrounds and RV parks
* Authorized dispersed camping Sites
* Overnight parking locations
* Free Resources
* Featured locations
* Optional mapped Areas
* Relevant land-management overlays
* Advanced member search and filters

The existing map and marker system should be extended rather than discarded.

⸻

11. Featured and Curated Locations

Curated camping is a premium discovery capability, not a separate Site type.

Reuse the existing Featured status and marker functionality.

Featured locations may appear in curated collections or specialized search results.

Curated recommendations should be grounded in documented conditions and appropriate verification.

Featured status must not override membership permissions or alter historical observations.

⸻

12. Future Reservation Platform

Llama Scout may eventually support reservations for independent campgrounds, small property owners, and private camping hosts.

The proposed service may operate through:

bookings.llamascout.com

Future capabilities may include:

* Verified host accounts
* Host-controlled campground listings
* Site inventory and availability
* Reservation requests
* Online payments
* Booking fees
* Host payouts
* Cancellation and refund management

Host-managed operational information must remain distinct from independent community Scout Reports and moderation authority.

Existing Places and Sites should be reused rather than duplicated for reservations.

This is a future project and is outside the initial reporting architecture rebuild.

⸻

13. V3 Development Scope

13.1 Immediate Priority

The first implementation priority is to establish one reliable canonical reporting foundation.

This includes auditing and consolidating question definitions, answer handling, ownership, validation, persistence, and rendering.

The existing application must continue functioning during this work.

13.2 Subsequent V3 Work

After the foundation is stable, development may proceed incrementally toward:

* Improved Site and Place presentation
* Map-first homepage
* Resources
* Expanded imports
* Membership-aware advanced search
* Improved curated discovery
* Shared API services

Each workstream requires separate implementation tasks and acceptance criteria.

13.3 Future Projects

The following are not part of the initial foundation rebuild:

* Native Android application
* Native iOS application
* Offline mobile synchronization
* Published ChatGPT integration
* Published Gemini integration
* Public developer API commercialization
* Full reservation marketplace
* Replacing existing navigation platforms
* Building proprietary road or trail navigation

These capabilities should influence architectural compatibility without forcing premature implementation.

⸻

14. Codex Development Rules

All V3 development must follow these principles.

Preserve existing functionality

Do not remove or replace working V2 systems unless an approved requirement makes that necessary.

Inspect before changing

Identify existing implementations, dependencies, storage structures, and permission checks before creating replacements.

Reuse existing code

Prefer shared services and refactoring over duplicate implementations.

Avoid temporary patches, stacked overrides, and parallel systems that duplicate business logic.

Keep tasks small

Each implementation task must have a limited scope.

Avoid broad instructions to rebuild the entire website or reporting system in one execution.

Preserve data

No destructive database migrations, historical record deletion, or irreversible changes without explicit approval and verified recovery procedures.

Protect production

Develop V3 separately from the production branch.

Use staging and verified backups before testing schema or data migrations.

Protect secrets

Never commit production credentials, private configuration files, or API secrets to the repository.

Verify every change

Tasks must identify affected files, expected behavior, test requirements, and acceptance criteria.

Maintain working states

Each completed development task should leave the affected application components functional and verifiable.

Document uncertainty

When the specification and implementation conflict, document the discrepancy.

Do not invent product rules or silently choose an interpretation.

⸻

15. Specification Authority

The following sources guide V3 development:

1. Explicitly approved V3 architectural and product decisions
2. Approved Scout Report PDF for question names, grouping, ordering, and ownership
3. Existing documented business rules and permission requirements
4. Verified V2 implementation for functionality that must be preserved
5. Repository audit findings and documented migration requirements

Where these sources conflict, Codex must report the conflict and request a decision before implementing a change that depends on it.

Existing code does not automatically override an approved specification merely because it reflects the current implementation.

Likewise, this scope document does not authorize removal of functionality that it does not explicitly mention.

⸻

16. Definition of Success

Llama Scout V3 is successful when:

* Existing V2 functionality and historical data remain intact.
* One canonical registry governs Scout Report questions.
* Question ownership is correctly separated between Place, Site, and applicable Area information.
* All reporting interfaces use the same canonical definitions and business rules.
* Approved Site observations contribute to current information without destroying history.
* Existing historical comparison functionality continues working.
* Imported information retains its source and is reconciled correctly.
* Membership, earned access, and existing community roles remain correctly enforced.
* The platform supports continued expansion of imported Places and Sites.
* The map-first experience can be implemented without discarding the existing website’s strengths.
* Future APIs and applications can use shared backend services rather than duplicating reporting logic.

The primary measure of success is not how much code is rewritten.

It is whether Llama Scout becomes easier to maintain, more consistent, more expandable, and more useful without losing the functionality already developed.

⸻

17. First Implementation Milestone

Milestone: V3 Foundation Audit

The first Codex task must be read-only.

Codex must inspect the existing repository and identify:

* Current reporting architecture
* Canonical and duplicated question definitions
* Place, Site, and Area storage relationships
* Answer storage and historical records
* Reporting interfaces and their dependencies
* Existing moderation and publishing workflows
* Existing permission and role checks
* Existing import and reconciliation systems
* Existing presentation and styling components
* Relevant technical debt and architectural conflicts

The audit must compare actual implementation with the approved specifications.

It must identify what can be reused, what requires modification, and what should remain untouched.

The audit must not modify production code, change database schemas, or implement new features.

Its findings will determine the order and boundaries of subsequent implementation tasks.

⸻

Project principle:

Build V3 from everything V2 already does well. Establish one reliable source of truth, make that information accessible through multiple interfaces, and allow Llama Scout to grow without repeating the architectural problems of the past.

Know the place before you go.

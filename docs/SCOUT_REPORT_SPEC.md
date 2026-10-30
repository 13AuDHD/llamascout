Llama Scout V3

Canonical Scout Report Specification

Version: 3.0
Status: Approved behavioral architecture; field reconciliation pending
Document: docs/SCOUT_REPORT_SPEC.md

Related documents:

* docs/PROJECT_SCOPE.md
* docs/ARCHITECTURE.md
* AGENTS.md
* Approved Scout Report PDF

⸻

1. Purpose

This document defines how Llama Scout collects, stores, moderates, publishes, and displays Scout Report information.

The primary goal is to establish one canonical reporting system shared across all interfaces.

The same question must always have the same identity, meaning, answer type, validation rules, and ownership regardless of where it appears.

Individual interfaces may present questions differently because they serve different purposes.

They must not maintain independent definitions or interpretations of those questions.

This document defines reporting behavior. It does not replace the approved PDF’s question-by-question inventory.

The current reporting implementation must be audited before any changes are made.

⸻

2. Canonical Reporting Principles

2.1 One Question, One Definition

Every question must have one authoritative definition.

Examples:

* Verizon cellular reception
* Site length
* Nighttime noise
* Road condition
* Land manager
* Tent pad width

If the same question appears in Add Place, moderation, Admin editing, a published Scout Report, and a comparison tool, all interfaces must reference its canonical identifier.

No interface may independently redefine its answer options, validation, ownership, or meaning.

2.2 One Canonical Data Foundation

Questions, approved answers, source information, and historical reports must have authoritative storage.

The existing MariaDB architecture is retained.

Shared PHP services must provide consistent access to canonical records.

Presentation components must not become separate sources of truth.

2.3 Preserve History

Approved Scout Reports must remain available as historical records.

New reports supplement existing information.

A newer report must not erase unrelated answers from previous reports.

Approved reports must not be destructively modified merely because a newer observation exists.

2.4 Preserve Existing Functionality

All existing Scout Report functionality must be inspected and reused wherever possible.

This includes submission, moderation, reconciliation, publishing, freshness indicators, photos, history, and comparison tools.

No existing functionality may be removed simply because it is not explicitly mentioned in this specification.

⸻

3. Canonical Field Registry

3.1 Required Definition Properties

Each canonical field must support the following logical properties.

Property	Purpose
Question key	Stable identifier
Label	Displayed question name
Description	Optional question guidance
Ownership	Place, Site, Area, or Resource
Section	Primary reporting section
Group	Question grouping
Order	Position in the report
Answer type	Supported data type
Answer options	Permitted values
Validation	Rules for acceptable answers
Applicability	Conditions controlling eligibility
Visibility	Public or Community
Status	Active, deprecated, or retired
Version	Definition history when necessary

These properties describe the required behavior, not a mandatory new database schema.

Codex must identify existing fields and services before proposing additional storage.

3.2 Stable Identifiers

Existing canonical question identifiers must be preserved wherever possible.

Renaming a question label must not automatically generate a new identifier.

Moving a question to another group must not destroy its historical answers.

When a question’s meaning changes substantially, the system must preserve its historical interpretation.

Do not repurpose an existing identifier for an incompatible question.

3.3 Centralized Answer Options

Answer options must be defined centrally.

For example, the same cellular reception question cannot use a five-point scale on one page and unrelated text options on another unless the approved specification explicitly defines that behavior.

Input controls, validation, display labels, and API output must agree on the accepted values.

Unknown or legacy values must be handled explicitly rather than silently converted to unrelated answers.

3.4 Question Visibility

Each question must identify whether its answer is Public or Community information.

Visibility must be enforced by shared authorization services.

Location-level visibility restrictions apply independently.

A public question does not make a restricted dispersed Site publicly discoverable.

3.5 Question Evolution

The registry must support:

* Adding questions
* Adding or revising answer options
* Reordering questions
* Changing presentation labels
* Changing Public or Community visibility
* Retiring questions
* Preserving historical answers
* Handling legacy answer formats

Changes must be deliberate and traceable.

⸻

4. Authoritative Question Organization

The approved Scout Report PDF controls the exact question inventory, naming, grouping, and ordering unless an explicit V3 decision supersedes it.

The reference PDF organizes questions into the following principal sections.

4.1 Place Information

Major groups include:

1. Basic Information
2. Location
3. Amenities
4. Operating Hours
5. Road and Access
6. Vehicle Access
7. Seasonal Access
8. Gate Access
9. Nearest Services

These groups generally contain Place-wide facts.

The audit must identify any questions whose ownership conflicts with the final V3 Place/Site model.

For example, a question describing an individual Site gate must not automatically become Place-wide information merely because an older form displayed it under a Place heading.

Ownership conflicts require documented reconciliation rather than assumptions.

4.2 Site Information

Major groups include:

1. Site Info
2. Fees and Reservations
3. Setting and Environment
4. Parking and Maneuvering
5. Accessibility
6. Site Amenities
7. Connectivity
8. Sensory Profile
9. Safety and Hazards
10. Rules and Restrictions
11. Experience and Recommendations
12. Notes

These groups contain individual Site information and observations, subject to the approved ownership mapping.

Some fee, reservation, and rule questions may describe shared Place policies rather than individual Site characteristics.

The audit must identify these cases instead of creating duplicate Place and Site answers.

4.3 Visit and Submission Metadata

The approved specification also contains visit-related and moderation-related fields, including visit date, length of stay, type of camping setup, and notes for moderators.

These are not automatically ordinary public Scout Report questions.

Their storage and presentation must reflect their actual purpose.

For example:

* Visit date describes an observation event.
* Camping setup describes the visiting contributor’s equipment.
* Moderator notes are internal workflow information.
* Submission date records when the report entered the system.

These fields must not be confused with permanent attributes of a Place or Site.

4.4 Exact Question Inventory

This document intentionally does not reproduce a second independently maintained inventory of every question.

Codex must use the approved PDF to produce a question-by-question reconciliation against the existing canonical registry and reporting interfaces.

The resulting mapping must identify:

* Exact canonical key
* Approved label
* Approved section and group
* Approved order
* Correct ownership
* Answer type and permitted values
* Existing storage location
* Existing interface usage
* Missing or duplicated implementation
* Required action

No field may be removed, reassigned, or retired merely because it is absent from one existing interface.

4.5 Two-Page Scout Report Presentation

The canonical data foundation is shared, but the full Scout Report presentation must remain split into two comprehensible pages or surfaces.

The report must not be flattened into one continuous page containing both Place and Site detail.

The approved presentation surfaces are:

**Place details**

* Basic Information
* Location
* Amenities
* Operating Hours
* Road Conditions
* Vehicle Access
* Seasonal Access
* Gate Access
* Nearest Services

The Place surface uses the established Llama Scout Place accent treatment, currently represented by the green visual language in the approved Canyon Haven Place reference.

**Site details**

* Site Info
* Fees and Reservations
* Setting and Environment
* Parking and Maneuvering
* Accessibility
* Site Amenities
* Connectivity
* Daytime
* Nighttime
* Specific Sensory
* Safety and Hazards
* Rules and Restrictions
* Experience and Recommendations
* Scout Notes
* Place History report metadata

The Site surface uses the established Llama Scout Site accent treatment, currently represented by the orange visual language in the approved Canyon Haven Site reference.

The color rule is semantic, not a storage rule. Canonical reporting metadata identifies the Place or Site accent role. CSS and presentation components own the actual color values.

Interfaces may use tabs, steps, navigation, or separate URLs where appropriate, but a full reporting interface must preserve the Place/Site conceptual split.

The two surfaces share one canonical question system underneath. Separating presentation must never create duplicate question definitions, duplicate answer storage, or conflicting ownership.

4.6 Explicit V3 Question Overrides

The approved PDF remains authoritative unless an explicit V3 decision supersedes it.

**Flat Walking Surface** is an approved V3 Site Accessibility question even though it is absent from the older approved Scout Report PDF.

Canonical key:

`flat_walking_surface`

This question remains active on the Site details page under Accessibility.

Its existing storage and historical answers are preserved.

This is a deliberate specification override, not a compatibility-only or retired field.

4.7 Canonical Official Basic Information

The approved Basic Information group includes four read-only official/source fields in addition to the ordinary canonical Place questions:

* Official Address
* Phone Number
* Email Address
* Official Links

These are canonical Scout Report definitions, but they are not ordinary community-editable questions.

Their canonical keys are:

* `official_address`
* `official_phone`
* `official_email`
* `official_links`

They belong to:

* Page: Place details
* Group: Basic
* Accent role: Place
* Visibility: Public
* Editing: Read-only through the Scout Report question system

Their values come from the existing official facility/source bundle and retain provider provenance through the existing source infrastructure.

Interfaces must consume these canonical definitions for labels, grouping, and source identity rather than independently redefining the same official fields.

This rule does not require copying official source values into ordinary community answer storage.

4.8 Compound Questions

A compound question has one user-facing question identity while preserving multiple existing component keys underneath.

This pattern is used when the stored components are independently valid but presenting them as separate questions makes the Scout Report harder to understand.

Seasonal Access uses two approved compound questions:

**Season Begins**

* Canonical presentation key: `season_begins`
* Stored month: `approach_closure_month`
* Stored day: `approach_closure_day`
* Published format: Month Day, for example `April 30`

**Season Ends**

* Canonical presentation key: `season_ends`
* Stored month: `approach_reopening_month`
* Stored day: `approach_reopening_day`
* Published format: Month Day, for example `September 15`

The year is intentionally not stored because these describe recurring seasonal access, not one calendar year's closure.

Editable interfaces present Month and Day controls together inside one compound question card.

Published Scout Reports combine the component answers into one card.

The existing component keys remain authoritative for persistence, validation, historical compatibility, and update handling.

Compound presentation must not create duplicate storage or discard partial historical values.

4.9 Site and Parking Clearance

Site Overhead Clearance and Parking Clearance are separate Site questions.

**Site Overhead Clearance**

* Canonical key: `overhead_clearance_feet`
* Page: Site details
* Group: Site Info
* Meaning: lowest practical overhead clearance across the usable Site area itself
* Existing historical values remain attached to this key

**Parking Clearance**

* Canonical key: `parking_clearance_feet`
* Page: Site details
* Group: Parking and Maneuvering
* Meaning: lowest practical overhead clearance over the primary parking or driveway area

The existing `overhead_clearance_feet` key must not be repurposed as Parking Clearance because its existing label, help text, and historical meaning describe Site overhead clearance.

The new Parking Clearance field uses the existing scoped JSON detail model and does not require a database schema migration.

4.10 Camp Host Answer Vocabulary

Camp Host is an approved Place Amenities Yes/No question.

Canonical key:

`amenity_campground_host`

Active answers are:

* Yes
* No
* Unknown through the canonical answer-state system
* Unanswered through the canonical answer-state system

The historical value `seasonal` remains readable for compatibility but is not offered as a new answer.

For new reports, Yes means a campground host is normally available during the Place's normal operating season.

Existing Seasonal records must remain displayable and must not be erased merely because the active answer vocabulary has been simplified.

4.11 Toilets Answer Model

Toilets is one approved Place Amenities question with a richer answer vocabulary than the historical boolean field.

Canonical key:

`amenity_toilets`

Active answers are:

* Pit toilet
* Flush toilet
* Portable toilet
* No toilets
* Unknown through the canonical answer-state system
* Unanswered through the canonical answer-state system

Canonical storage:

`place_amenities.toilet_type`

Canonical stored values:

* `pit`
* `flush`
* `portable`
* `no`

Historical compatibility value:

* `present_unknown_type` = a toilet is known to exist, but the historical record did not identify the type

Legacy compatibility storage:

`place_amenities.toilets`

The legacy boolean column remains synchronized as a presence mirror:

* pit, flush, portable, present_unknown_type -> true
* no -> false
* Unknown or Unanswered -> null

Existing historical Yes values are backfilled to `present_unknown_type`.

Existing historical No values are backfilled to `no`.

The richer canonical field must not guess a toilet type when the source only proves that toilets exist.

Imported source data may populate the richer type only when the source evidence is specific enough.

4.12 Parking-to-Site Distance Buckets

Distance from parking to the usable Site remains a bucketed accessibility question in V3.

Canonical key:

`walking_distance_from_vehicle`

Approved V3 answers remain:

* At / beside vehicle
* Under 50 ft
* 50-100 ft
* 100-250 ft
* 250-500 ft
* 500+ ft / short hike
* Unknown
* Unanswered

The older approved Scout Report PDF describes this value in feet, but V3 intentionally keeps the existing distance buckets because they are sufficiently precise for the accessibility and Site-fit purpose of the question.

No exact-foot measurement is required.

Existing storage, historical answers, and display labels remain unchanged.

4.13 Rule and Permission Answer Vocabularies

Rules and activity permissions do not share one universal answer vocabulary.

The approved V3 answer sets are field-specific.

**Yes / With Permit / No**

The following questions use:

* Yes
* With Permit
* No
* Unknown
* Unanswered

Fields:

* Overnight stay permitted
* Dispersed camping permitted

Canonical keys:

* `overnight_camping_allowed`
* `dispersed_camping_allowed`

Stored value `2` is the active **With Permit** answer.

Historical value `3` (Conditional) remains readable when already stored but is not offered as a new answer.

**Yes / Permit required / No**

The following questions use:

* Yes
* Permit required
* No
* Unknown
* Unanswered

Fields:

* Campfires
* Collecting firewood
* Charcoal grills
* Propane fire pits
* Drone use
* Target shooting

Canonical keys:

* `campfire_allowed`
* `collecting_firewood`
* `place_charcoal_grills_allowed`
* `place_propane_fire_pits_allowed`
* `drone_use_legal`
* `target_shooting_allowed`

Historical value `3` (Conditional) remains readable when already stored but is not offered as a new answer.

Permit and conditions detail fields remain applicable to both active Permit-required values and historical Conditional records where appropriate.

**Alcohol**

Alcohol Allowed uses:

* Yes
* No Glass
* No
* Unknown
* Unanswered

Canonical key:

`place_alcohol_permitted`

Historical value `3` (Conditional) remains readable but is not offered as a new answer.

The active answer vocabulary must describe the rule itself. Additional conditions, permit links, and exceptions belong in the dedicated rule-detail fields rather than in a generic Conditional answer.

4.14 Canonical Public and Community Visibility

Every approved Scout Report question has one canonical visibility classification:

* Public
* Community

Visibility describes whether an authorized public Place response may expose the answer itself.

Visibility does not override location-level restrictions.

A restricted dispersed Site remains undiscoverable to unauthorized users even when one of its questions is Public.

### Public questions

The initial V3 Public baseline follows the existing public Place experience.

Public Place identity and orientation questions are:

* Place Name
* Place Type
* Elevation
* Nearest City
* County / Parish / Municipality
* State
* Land Type
* Land Manager / Owner
* Region / Ranger District / Local Unit

All approved shared **Place Amenities** questions are Public.

The canonical official source fields are also Public:

* Official Address
* Phone Number
* Email Address
* Official Links

### Community questions

All other approved Scout Report questions are Community unless a later approved specification explicitly changes their classification.

This includes, among other things:

* Description
* Exact Latitude
* Exact Longitude
* Road / Route
* Place activities not already exposed through the public Place experience
* Operating Hours
* Road Conditions
* Vehicle Access
* Seasonal Access
* Gate Access
* Nearest Services
* All Site details
* Fees and Reservations
* Setting and Environment
* Parking and Maneuvering
* Accessibility
* Site Amenities
* Connectivity
* Sensory information
* Safety and Hazards
* Rules and Restrictions
* Experience and Recommendations
* Scout Notes
* Visit / Place History report metadata

The fact that an old implementation group key contains the suffix `_public` does not make the group's answers Public in V3.

Examples include legacy identifiers such as:

* `connectivity_public`
* `safety_public`
* `rules_public`
* `experience_public`
* `notes_public`
* `history_public`

Canonical visibility metadata is authoritative.

### Existing behavior and enforcement

This milestone establishes canonical metadata only.

It does not, by itself, change the current public/member Place page behavior.

Later interface migration must consume this metadata through shared authorization checks rather than maintaining independent visibility lists.

Fetching a Community field internally for SEO, moderation, completion, or other server-side processing does not authorize exposing the answer publicly.

4.15 Shared Visibility Authorization

Question visibility decisions must use the canonical Public / Community classification rather than interface-specific section lists.

The shared reporting layer provides one visibility decision:

* Public questions are available at Public and Community access.
* Community questions require Community access.
* Missing or invalid visibility never grants access.

This question-level decision does not grant access to a Place or Site.

Location-level restrictions, including restricted dispersed-Site discoverability, are evaluated independently and remain authoritative.

### Locked public Scout Report preview

The locked public Scout Report preview is the first interface migrated to canonical visibility.

It now:

* reads question order and grouping from the approved Scout Report registry
* lists only Community questions as locked
* does not label Public questions as Members only
* uses canonical compound-question labels such as Season Begins and Season Ends
* preserves the separate locked Description presentation
* no longer uses legacy reporting section names as an access-control mechanism

This migration changes how the locked preview determines which questions are protected.

It does not expose Community answers to public users and does not change Complete Access eligibility.

4.16 Canonical Report Page Ownership

The published Scout Report must derive Place/Site group ownership and group order from the canonical two-page definition.

Authoritative source:

`llama_place_report_approved_pages()`

The Place page owns:

* Basic
* Location
* Amenities
* Operating Hours
* Road Conditions
* Vehicle Access
* Seasonal Access
* Gate Access
* Nearest Services

The Site page owns:

* Site Info
* Fees and Reservations
* Setting and Environment
* Parking and Maneuvering
* Accessibility
* Site Amenities
* Connectivity
* Daytime
* Nighttime
* Specific Sensory
* Safety and Hazards
* Rules and Restrictions
* Experience and Recommendations
* Scout Notes
* Place History

Published rendering must not maintain a second hardcoded list of Place groups.

Group labels and question order remain owned by the approved question registry.

Page ownership and page-local group order remain owned by the approved page registry.

The current renderer may still present both surfaces inside the existing Place experience until the later interface migration separates them visually, but its semantic ownership must already come from the canonical page definition.

4.17 Canonical Current Answer Model

Milestone 2 introduces one read-only current-answer envelope over the existing published Place, scoped inheritance, and campsite baseline precedence.

This model does not choose a new winner.

It describes the winner selected by the existing compatibility read paths in one consistent structure.

### Entry point

Canonical service:

`llama_place_report_current_answer_model()`

The caller provides:

* Place ID
* reporting target scope
* reporting target source
* reporting target ID

The target is reverified against the Place before answers are returned.

### Answer envelope

Each relevant canonical field returns:

* `field_key`
* `state`
* `value`
* `recorded`
* `inherited`
* `direct`
* `baseline`
* `imported`
* `target`
* `origin`

### Answer state

Allowed current states are:

* `answered`
* `unknown`
* `unanswered`

`value` is null for Unknown and Unanswered.

An explicitly recorded null may also have `value = null`, but its state is Answered.

State, not value truthiness, distinguishes these cases.

### Origin types

The initial compatibility origin types are:

* `published_place`
* `scoped_override`
* `campsite_baseline`

Inherited answers retain their actual origin scope and are marked with:

`inherited = true`

A campsite baseline is marked with:

`baseline = true`

A campsite baseline is marked with:

`imported = true`

only when the campsite source provider is external rather than Llama Scout.

### Current precedence

The current-answer model preserves the existing precedence.

Place:

1. current published Place value and answer state

Area:

1. direct Area scoped answer
2. eligible Place answer

Map-only Site:

1. direct Site scoped answer
2. eligible parent Area answer
3. eligible Place answer

Canonical campsite Site:

1. direct Site scoped override
2. campsite baseline
3. eligible parent Area answer
4. eligible Place answer

### Campsite baseline provenance

The target model now retains three separate Site layers:

* `baseline` for campsite-record baseline values
* `overrides` for direct scoped Scout/Admin answers
* `direct` for the composed Site-local result

Field-level provenance identifies whether the winning Site-local answer came from the campsite baseline or a scoped override.

External source provider and external ID are preserved on baseline provenance when available.

### Non-goals

M2-002 does not:

* change current-answer precedence
* change persistence
* create immutable history
* infer contributor attribution that current storage does not contain
* change public or member rendering
* change database schema

Later Milestone 2 work may add historical contribution/report identity to the same answer envelope without changing its state/value semantics.

⸻

5. Place and Site Ownership

5.1 Place Information

Place-level information describes the parent location.

Examples include:

* Place name and type
* Official contacts
* Land manager
* Shared amenities
* General access
* Place-wide restrictions
* Applicable closures

Published Place facts are protected from direct ordinary-member editing.

Corrections follow the existing Report a Problem and administrative workflows.

5.2 Site Information

Site-level information describes one individual camping, parking, or staying location.

Examples include:

* Site number or name
* Site dimensions
* Parking dimensions
* Tent pad measurements
* Site-specific utilities
* Connectivity
* Sensory conditions
* Site accessibility
* Individual Site observations

Sites may have multiple historical reports.

Each report applies to the selected Site.

A report for Site 14 must not accidentally modify Site 15 or the parent campground’s shared attributes.

5.3 No Artificial Duplication

A Place-level question must not be duplicated as a Site-level question to simplify rendering.

The reverse is also prohibited.

An interface that needs information owned by another entity should retrieve that information from its authoritative record.

The approved PDF and final V3 ownership decisions determine where facts belong.

5.4 Areas

Areas are optional administrative geographic objects.

Their geometry and existing editing functionality must be preserved.

Areas may eventually support additional reporting attributes.

Ordinary members do not submit Area reports during initial V3 development.

The existing Area question behavior must be audited rather than removed.

5.5 Resources

Resources use a simplified reporting form.

They do not require a complete camping Scout Report.

Resource fields are centrally defined and use the existing submission and moderation system.

Resource-specific questions must not be added to ordinary campground forms unless they are genuinely applicable.

⸻

6. Reporting Workflows

6.1 Add Place

Add Place creates a proposed Place and its first Site when the location is community-created.

The form presents applicable Place and Site questions from the canonical registry.

The user must understand which information applies to the overall Place and which applies to the individual Site.

The initial submission may contain imported or prefilled official information.

The submission remains unpublished until approved.

Existing minimum submission requirements, validation, correction cycles, and moderation rules must be preserved.

6.2 Add Site to Existing Place

A member may propose a new Site within an existing Place.

The Place already exists and does not need to be recreated.

The form should focus on Site-specific information.

Existing Place information may be displayed for context but should not become ordinary editable Site answers.

The new Site requires approval before publication.

Duplicate checking must include nearby existing Sites.

6.3 Update This Place

Despite its existing interface name, the ordinary member update workflow primarily collects new Site observations and other permitted contributions.

It must support partial reports.

Members may update one question or multiple questions.

The submitted information must be associated with the correct Site and contributor.

An update must not create a duplicate Site or silently rewrite protected Place facts.

6.4 Report a Problem

Report a Problem is the ordinary member workflow for identifying incorrect or outdated authoritative Place information.

Reports remain available to authorized administrators until investigated and resolved.

Verification may involve official sources, supporting evidence, or direct contact with a managing organization.

The existing issue and resolution system must be preserved.

6.5 Moderation

Moderation must retrieve the same canonical question definitions used by the submission interface.

Moderators should be able to review:

* Submitted answers
* Existing published answers
* Historical information
* Relevant source attribution
* Supporting photos
* Contributor information
* Identified conflicts

Moderators must not receive a separate, independently defined question inventory.

Existing approval, rejection, correction, and self-approval restrictions remain authoritative.

6.6 Admin Editing

Authorized administrative editing uses canonical definitions and storage rules.

It may expose additional controls or protected fields that ordinary members cannot edit.

Administrative editing must not create an independent set of answer definitions.

Corrections to published facts must preserve appropriate change history and provenance.

Owner-only and other existing administrative permissions remain enforced.

6.7 Published Scout Report

The published Scout Report is a presentation of canonical approved information.

It is not an independent storage system.

It must display the appropriate current answer for each question using the same ownership and interpretation rules as the reporting forms.

Authorized users may access historical observations and comparisons.

6.8 Comparison Tool

Preserve the existing comparison tool.

It must use the same canonical questions and stored historical records.

Historical comparisons must remain possible even when current answers have changed.

Do not rebuild the comparison tool unless the audit establishes that a specific component requires modification.

⸻

7. Reports and Historical Observations

7.1 Report Identity

Every submitted report must retain an identity that distinguishes it from other reports.

Relevant metadata includes:

* Report identifier
* Target Site or other permitted entity
* Contributor identifier
* Visit date
* Submission date
* Moderation status
* Approval information
* Associated answers
* Associated evidence and photos

The physical storage model must be determined through the repository audit.

7.2 Approved Reports

Approved reports become part of the permanent historical record.

Subsequent reports must not destructively replace them.

If an approved report requires administrative correction, the correction must be traceable without silently rewriting historical evidence.

7.3 Partial Reports

A report does not need to answer every question.

For example:

Report A, October 10

* Site length: 35 feet
* Verizon: 5/5
* Nighttime noise: Quiet

Report B, October 20

* Verizon: 3/5

Report C, October 25

* Nighttime noise: Moderate

The current Site summary becomes:

* Site length: 35 feet, Report A
* Verizon: 3/5, Report B
* Nighttime noise: Moderate, Report C

The previous answers remain available.

7.4 Observation Dates

The system must distinguish:

Visit date: When the contributor observed the conditions.

Submission date: When the report was submitted.

Approval date: When moderation approved the report.

These dates must not be treated as interchangeable.

Current observation selection should prioritize the actual observation date when reliable, using a consistent, documented tie-breaking rule.

An older visit submitted late must not automatically replace a newer on-site observation solely because its approval occurred later.

The existing behavior must be audited before finalizing the exact selection algorithm.

7.5 Conflicting Reports

Different contributors may provide conflicting observations.

Llama Scout should preserve those differences rather than automatically selecting the majority opinion.

Example:

Seven contributors report Verizon reception at 5/5.

A later visitor reports 1/5.

If the newest report is valid and approved, the current display may show 1/5.

The earlier seven observations remain accessible.

The system must not silently average, discard, or reinterpret the reports as a single objective truth.

7.6 Report Completeness

Preserve the existing completeness system.

Completeness must account for applicable questions, not simply the total number of questions in the registry.

Question dependencies, entity type, and answer states must be considered.

The audit must verify how imported answers and explicitly unknown values affect completeness.

Do not change contribution points, approval thresholds, or Scout qualifications as part of the reporting refactor.

⸻

8. Answer States and Validation

8.1 Distinct Answer States

The reporting foundation must distinguish:

* No answer supplied
* Explicit No
* Explicit Yes
* Unknown
* Not applicable
* Not tested
* An intentionally cleared or withdrawn answer
* Other approved question-specific states

Not every question needs every state.

Supported states must follow its canonical definition.

8.2 No Answer Is Not No

An unanswered Site question must never be converted automatically to No.

For example:

A member who does not test Verizon has not reported zero service.

A member who reports no Verizon coverage has provided an actual observation.

These are different states.

8.3 Clear and Correction Actions

Clearing a previously published answer must be an explicit authorized action.

Omitting a question from a partial report must never clear the current answer.

Administrative corrections and intentional withdrawal of inaccurate information must preserve their change history.

8.4 Type-Specific Validation

Validation must follow the canonical definition.

Examples include:

* Numeric ranges for rating scales
* Nonnegative measurements where appropriate
* Valid dates and times
* Valid option identifiers
* Supported units
* Properly validated URLs

Validation must occur in shared backend logic.

Frontend controls may provide immediate validation feedback but cannot replace server-side checks.

8.5 Units

Measurements must have defined units and consistent interpretation.

Examples include feet, miles, and applicable currency amounts.

If values are converted for presentation, the stored meaning must remain unambiguous.

Imported units must be normalized without silently changing their magnitude.

⸻

9. Current Published Answers

9.1 Canonical Current View

The current Site report is assembled from the latest applicable approved answer for each question.

Each displayed answer must be traceable to the report or source record that supplied it.

A current-answer cache may exist for performance, but it must be reproducible from authoritative records.

A cache must never become a competing source of truth.

9.2 Official Facts and Observations

Not all information follows identical update rules.

An official Place closure, campground phone number, or established fee may require administrative reconciliation.

A Site-level cellular reception observation may be updated by the latest approved visit report.

The system must distinguish authoritative facts from observations when determining publication behavior.

Do not use a universal newest-timestamp-wins rule for every field.

9.3 Source Attribution

Community answers retain contributor attribution.

Imported answers retain original provider attribution.

Each current answer should identify:

* Source or contributor
* Relevant observation or source update date
* Original record reference
* Any required internal provenance

The display must not present synchronization time as an observation or verification date.

⸻

10. Standard Scout Report Presentation

10.1 Card Structure

Preserve the existing Llama Scout card design, icons, typography, and visual identity.

Each standard answer card displays three rows.

Row 1: Question label and icon

Row 2: Current approved answer

Row 3: Relevant date and contributor or source

Examples:

October 29, 2026 · Member A

October 29, 2026 · National Park Service

No redundant supporting text is required inside ordinary answer cards.

Additional details may remain available through relevant history and comparison interfaces.

10.2 Current Answer Display

The current answer must use the canonical question’s formatting and supported value labels.

The renderer must distinguish actual values from missing information.

Unknown, unavailable, and not-tested answers must not be displayed as fabricated measurements.

10.3 Presentation Flexibility

Different interfaces may use:

* Input controls
* Compact cards
* Full answer cards
* Comparison tables
* Map summaries
* Mobile layouts
* API responses

Different presentation does not permit different question meaning.

10.4 Source-Specific Presentation

Imported values may display their source instead of a community contributor.

Source labels must use authoritative provenance.

Do not hardcode a provider name because a Place was originally imported from that provider.

A particular answer may have been supplied by another source later.

10.5 Historical Access

Authorized users must be able to open the relevant report history.

Existing comparison functions must remain available.

The current card should not attempt to display every historical observation simultaneously.

⸻

11. Photos and Supporting Evidence

11.1 Preserve Existing Photos

Existing Place and Site photos must be preserved.

Do not duplicate photo files simply because reporting architecture changes.

Photo associations and ownership must remain accurate.

11.2 Correct Entity Association

A Site photo must remain associated with the appropriate Site or report.

A Place photo must not automatically become a photo of every Site within that Place.

The existing photo organization and display conventions must be audited and preserved.

11.3 Evidence

Reports may contain photos or notes supporting observations.

Evidence must remain associated with its originating contribution where possible.

Removing or replacing a current displayed answer must not automatically destroy historical evidence.

⸻

12. Moderation and Permissions

12.1 Required Approval

Every community contribution follows the existing moderation process.

Submitting a report does not automatically publish it.

Only approved information can influence current published answers.

12.2 Self-Approval Restrictions

Preserve existing permissions.

Scout and Master Scout users cannot approve their own submissions.

Existing Admin and Owner self-approval permissions remain intact.

No reporting refactor may bypass these restrictions.

12.3 Place Fact Protection

Ordinary members cannot directly modify published Place facts.

They may report problems.

Only appropriately authorized Master Scouts, Admins, and Owners may make protected edits.

12.4 Earned Access

Qualifying approved Place or Site contributions may grant Place-specific membership access according to the established V3 rules.

The reporting system must retain enough contributor and approval information to support these entitlements.

Submission alone does not grant access.

An ordinary one-question update does not automatically earn full Place access.

12.5 Data Visibility

Question-level Public and Community classifications must be enforced.

Dispersed Site visibility restrictions also apply.

A restricted Site cannot become public because one of its questions is classified Public.

Authorization must be applied consistently by all reporting interfaces and future API consumers.

⸻

13. Reconciliation Mode

The reporting system must preserve the existing concept of reconciliation mode.

Reconciliation mode exists to identify discrepancies between canonical definitions and actual interface behavior.

It must be capable of showing all defined active questions applicable to the selected entity and reporting context.

Ordinary conditional dependencies may be relaxed for auditing purposes.

However, reconciliation mode must not ignore legitimate entity ownership.

A Site-only question must not become a Place question merely because reconciliation mode displays additional fields.

Reconciliation should identify:

* Missing questions
* Duplicate questions
* Misplaced questions
* Incorrect answer controls
* Invalid answer mappings
* Hidden applicable questions
* Incorrect ownership
* Retired fields still displayed
* Active fields incorrectly omitted

Reconciliation is an administrative diagnostic capability.

It must not alter ordinary production form behavior unless an approved implementation task makes the correction.

⸻

14. Legacy Data Preservation

The V2 system contains existing questions, answers, reports, imports, and historical records.

All relevant data must be preserved through migration.

Legacy records may require mapping to corrected canonical identifiers.

A migration must not assume all existing data is correct merely because it is present in the database.

Likewise, old data must not be discarded simply because its original representation differs from the V3 model.

Potential migration actions include:

* Retain existing mapping
* Correct question ownership
* Normalize answer values
* Reassign an answer to its legitimate entity
* Preserve a retired field for historical access
* Flag ambiguous information for review

Ambiguous migrations must be reported rather than guessed.

Every migration must have a recovery strategy.

⸻

15. Cross-Interface Consistency Requirements

The following must be verified for every applicable canonical question.

Interface	Required behavior
Add Place	Correct question and input
Add Site	Correct Site-specific question
Suggest Update	Correct editable observation field
Moderation	Correct submitted value and context
Admin Editing	Correct authorized controls
Published Report	Correct current approved answer
Historical Comparison	Correct historical values
API (future)	Same canonical identity and interpretation

The reporting system is not considered unified merely because several pages look visually similar.

Consistency must be verified at the definition, validation, persistence, permission, and presentation levels.

⸻

16. Required Audit Deliverables

Before implementing reporting changes, Codex must produce a report establishing the current state.

The audit must include:

16.1 Canonical Question Inventory

Identify all currently defined questions and their identifiers.

Compare them against the approved Scout Report PDF.

16.2 Ownership Reconciliation

Identify Place, Site, Area, and Resource question ownership.

Flag ambiguities requiring explicit approval.

16.3 Interface Comparison

For each applicable question, verify its presence, order, answer type, dependencies, and behavior across existing reporting interfaces.

16.4 Storage Mapping

Identify where question definitions, submitted answers, approved values, historical reports, and imported information are stored.

16.5 Publishing Flow

Document the existing process from submission through moderation to current published display.

16.6 Historical Data

Verify how individual reports, visits, contributors, and comparison records are linked.

16.7 Source Provenance

Determine how imported source identifiers, update dates, and reconciliation history are currently preserved.

16.8 Implementation Recommendations

Identify the smallest practical changes needed to eliminate duplication and inconsistent reporting behavior.

Do not implement these recommendations during the read-only audit.

⸻

17. Required Verification Scenarios

The unified system must eventually pass tests covering at least the following behaviors.

Scenario A: Complete initial report

A Place and first Site are submitted, moderated, and published with the appropriate information.

Scenario B: Partial update

A second visitor submits only Verizon reception. Other current Site answers remain unchanged.

Scenario C: Conflicting observations

A new approved reception rating differs from previous visits. The current answer updates, and previous observations remain available.

Scenario D: Unanswered question

A submitted report leaves nighttime noise unanswered. The existing nighttime noise answer remains intact.

Scenario E: Explicit negative answer

A member reports no cellular service. The system records the actual response rather than treating it as missing.

Scenario F: Older visit submitted late

A report submitted later but documenting an older visit does not silently supersede a newer observation.

Scenario G: Imported Site information

An official source provides Site length and tent pad dimensions. Those values display with appropriate provenance without requiring a community report.

Scenario H: Place-level correction

An ordinary member reports incorrect campground information. The published Place remains unchanged until authorized resolution.

Scenario I: Site isolation

Updating Site 14 cannot modify Site 15 or the parent Place’s unrelated information.

Scenario J: Historical comparison

All approved observations remain available through the existing comparison tool.

Scenario K: Membership restrictions

Unauthorized users cannot retrieve protected community answers or restricted dispersed Site information.

Scenario L: Reconciliation

All applicable active questions can be inspected without incorrect suppression or cross-entity duplication.

Scenario M: Retired or revised question

Historical answers remain interpretable after a question is revised or retired.

Scenario N: Photo integrity

Existing Place, Site, and report photos remain correctly associated and are not unnecessarily duplicated.

Scenario O: Administrative authorization

Existing Owner, Admin, Master Scout, and Scout permissions continue functioning without privilege escalation or moderation bypass.

Tests should use actual existing fixtures or controlled test data. No production data should be altered merely to demonstrate a scenario.

⸻

18. Unresolved Implementation Decisions

The following require verification or explicit resolution before their dependent implementation tasks begin.

1. Exact canonical registry file or files currently in use.
2. Actual question identifiers and legacy mappings.
3. Field ownership discrepancies between the approved PDF and V3 architecture.
4. Current answer-selection behavior for equal or unreliable visit dates.
5. Existing handling of intentional answer clearing.
6. Existing report and observation storage relationships.
7. How imported values participate in current-answer selection.
8. How source dates and verification dates are stored.
9. Whether any existing generic rating questions should be retired from future presentation while retaining history.
10. Exact treatment of a first substantial community report for an already imported Site.
11. Existing completeness behavior for partial reports and imported answers.
12. Existing report comparison and reconciliation dependencies.

These are audit and reconciliation requirements, not permission for Codex to invent answers.

⸻

19. Definition of Completion

The canonical reporting overhaul is complete when:

* One authoritative question definition exists per canonical question.
* Every applicable reporting interface uses those definitions.
* Place and Site ownership is correct.
* Answer values and validation are consistent.
* Partial updates cannot erase unrelated information.
* Current answers derive from approved canonical data.
* Historical reports remain available.
* Imported facts preserve their sources.
* Existing comparison tools continue working.
* Existing moderation and permission systems remain intact.
* All applicable questions match the approved specification.
* Question-by-question reconciliation has been completed.
* No duplicated reporting implementation remains without a documented, justified reason.
* The relevant verification scenarios pass.

The goal is not identical forms.

The goal is identical meaning, consistent behavior, authoritative data, and reliable history across every interface.

⸻

20. Implementation Boundary

This document does not authorize an immediate rewrite.

Codex must first inspect the current system and produce the required reconciliation findings.

Any changes must be implemented through small, independently verifiable tasks.

Working V2 functionality must remain operational throughout the transition.

The reporting foundation must become reliable before additional major platform features depend on it.

One canonical report system. One authoritative interpretation. Every interface stays consistent.

Know the place before you go.

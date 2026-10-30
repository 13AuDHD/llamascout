Llama Scout V3

AGENTS.md - Codex Development Instructions

Project Identity

Project: Llama Scout
Version: 3.0
Technology: PHP, MariaDB, JavaScript, HTML, CSS
Development branch: v3-development
Production: Existing V2 application

Llama Scout is a community-powered outdoor recreation information platform.

V3 modernizes the existing reporting architecture while preserving the functionality, design, data, and infrastructure developed in V2.

V3 is an evolution of V2, not a replacement.

The governing product specification is:

docs/PROJECT_SCOPE.md

Read the relevant sections before beginning any task.

Do not treat this file as authorization to implement the entire V3 project.

⸻

1. Mandatory Working Rules

1.1 Inspect Before Modifying

Before changing code:

1. Identify the existing implementation.
2. Locate related files and dependencies.
3. Examine existing database relationships.
4. Identify existing shared utilities and services.
5. Determine whether the requested functionality already exists.
6. Check whether the proposed change affects other interfaces.

Never create a replacement system without first investigating the existing system.

Do not assume that code is obsolete merely because it appears unused in one file.

1.2 Preserve V2 Functionality

All existing V2 functionality must be preserved unless explicitly changed by an approved V3 requirement.

This includes:

* Website layouts, styling, branding, and assets
* PHP backend and database connections
* Accounts, authentication, and memberships
* Scout, Master Scout, Admin, and Owner permissions
* Administrative tools and moderation
* Existing maps, markers, polygons, and layers
* Photo management
* Scout Reports and historical comparisons
* External data importers and reconciliation tools
* Shop, sales, and other existing systems

Do not remove functionality simply to simplify implementation.

1.3 One Task at a Time

Work only on the task explicitly assigned.

Do not automatically proceed into subsequent implementation phases.

Do not combine unrelated refactoring, redesign, database changes, or feature development with the requested task.

If another problem is discovered, document it separately unless it directly prevents completion.

1.4 Avoid Unnecessary Code

Prefer modifying the existing authoritative implementation over creating duplicate files or replacement services.

Avoid:

* Duplicate question definitions
* Separate implementations of the same business rules
* Unnecessary PHP pages
* Temporary compatibility systems without migration plans
* Repeated CSS overrides
* Unnecessary dependencies
* Hardcoded values already available from canonical data
* Workarounds that hide the underlying problem

Changes should make Llama Scout easier to maintain.

⸻

2. Architecture Requirements

2.1 Shared PHP Backend

Preserve the existing PHP and MariaDB architecture.

V3 will progressively centralize business rules into reusable backend services.

The existing website and future API must use the same authoritative services.

Do not rewrite the website as a separate API-driven application unless explicitly instructed.

2.2 Canonical Reporting

The reporting system must have one authoritative definition for each question.

Every reporting interface must use consistent:

* Question identifiers
* Question labels
* Ownership and applicability
* Answer types and permitted values
* Validation rules
* Visibility rules
* Data persistence
* Historical observations

Do not implement independent question definitions inside individual pages.

2.3 Geographic Relationships

Preserve these distinctions:

Place: Parent geographic location and authoritative Place-level information.

Site: Individual camping, parking, or staying location belonging to one Place.

Area: Optional mapped polygon managed through existing administrative permissions.

Resource: Public travel service using a simplified reporting workflow.

Place, Site, Area, and Resource are distinct concepts.

Do not create duplicate records to compensate for missing relationships.

Imported Places may exist without community-created Sites.

2.4 Observations and History

Preserve historical Scout Reports.

The currently displayed answer for each question should come from the latest applicable approved observation, according to the canonical reporting rules.

An unanswered question must not erase an earlier answer.

Retain contributor information, observation dates, source provenance, and existing comparison functionality.

Imported facts must retain their original sources.

⸻

3. Roles, Memberships, and Security

3.1 Existing Roles

Preserve the complete existing permission hierarchy:

1. Owner
2. Admin
3. Master Scout
4. Scout
5. Ordinary member

Owner-only functionality must remain Owner-only.

Do not assume that a role hierarchy alone determines every permission.

Inspect existing authorization checks before changing protected operations.

Do not redesign Scout qualifications, appointment requirements, complimentary membership benefits, or moderation privileges.

3.2 Moderation

Preserve existing moderation and approval behavior.

Ordinary members cannot directly edit published Place-level facts.

Published Place changes require authorized administrative editing or the existing Report a Problem workflow.

Scout and Master Scout self-approval restrictions must remain enforced.

Existing Admin and Owner privileges must be preserved.

Do not introduce automatic publication or bypass moderation without explicit approval.

3.3 Information Visibility

Canonical question data supports Public and Community visibility.

Location-level restrictions also apply.

Dispersed camping Sites are restricted to:

* Paid members
* Members with complimentary full access
* Free members with earned Place-specific access
* Authorized administrative roles

Restricted information must not leak through map markers, search results, coordinates, API responses, previews, public indexes, or other interfaces.

Publicly designated campground information and Resources remain accessible without paid membership.

Enforce permissions in backend services rather than relying on frontend hiding.

3.4 Secrets

Never expose, print, log, or commit production credentials.

Do not include active API keys, passwords, tokens, or private configuration values in generated documentation.

Do not replace existing configuration with hardcoded credentials.

Do not modify production secrets or authentication configuration without explicit authorization.

⸻

4. Database and Import Safety

4.1 Protect Existing Data

Do not perform destructive database operations without explicit approval.

This includes:

* Dropping tables
* Deleting historical reports
* Removing existing columns containing data
* Resetting identifiers
* Irreversible schema changes
* Overwriting published answers in bulk

Any migration proposal must identify affected tables, dependencies, rollback requirements, and data-preservation strategy.

Schema migrations must be reviewed before execution.

4.2 External Data Sources

Preserve the existing external-source storage and reconciliation architecture.

Additional sources may include RIDB, NPS, USFS, BLM, and other approved providers.

External data must be staged and normalized before publication into canonical Llama Scout records.

Preserve original source identifiers, available timestamps, and provenance.

Do not automatically overwrite approved information when an external source reports a conflicting value.

Do not create duplicate Places or Sites when a record already exists.

⸻

5. Frontend and Design

Preserve Llama Scout’s existing visual identity.

Reuse:

* Existing CSS stylesheets and components
* Manrope typography
* Existing icon system
* Logos and branding assets
* Responsive layout conventions
* Scout Report card styling
* Map markers and established colors

Do not introduce a new CSS framework, icon library, font, or frontend framework without explicit approval.

Avoid adding new CSS overrides when the correct existing rule can be modified.

Preserve responsive behavior across desktop, tablet, and mobile.

UI changes should be limited to the assigned task.

⸻

6. Task Execution Procedure

For every implementation task:

Before Editing

1. Read the task and relevant specifications.
2. Inspect affected code and dependencies.
3. Identify reusable functionality.
4. State the intended change and affected files.
5. Identify any unresolved decision that would materially affect correctness.

For audit-only tasks, do not modify application files.

During Implementation

1. Modify the smallest practical set of files.
2. Preserve existing behavior outside the assigned scope.
3. Reuse canonical definitions and services.
4. Avoid unrelated cleanup.
5. Keep changes reviewable and reversible.
6. Do not continue into another task without authorization.

After Implementation

1. Review the changes for unintended effects.
2. Run applicable existing checks and tests.
3. Report any tests that could not be run.
4. Confirm whether acceptance criteria were met.
5. Identify changed files.
6. Identify any database or configuration requirements.
7. Report unresolved issues or risks.

Never claim a test passed unless it was actually executed successfully.

Do not claim production behavior was verified unless the relevant environment was tested.

⸻

7. Development and Git Rules

Work on the assigned development branch or task branch.

Do not commit directly to the production branch.

Do not merge, deploy, or modify production automatically.

Do not rewrite Git history or force-push without explicit authorization.

Preserve unrelated changes already present in the working tree.

Prefer small commits with clear descriptions.

Do not create a large collection of speculative implementation files before their functionality is required.

Each task should leave the application in a reviewable state.

⸻

8. Specification Conflicts

The approved V3 project scope establishes product intent.

The approved Scout Report PDF establishes authoritative question specifications until explicitly superseded.

Existing V2 behavior must be preserved wherever the new specifications do not require changes.

If the repository conflicts with an approved specification:

1. Identify the discrepancy.
2. Identify the affected files and behavior.
3. Explain the consequence.
4. Document the required decision.
5. Do not silently invent a replacement rule.

Never assume that the current implementation is correct merely because it is already in production.

Never assume that a new architecture justifies removing existing functionality.

⸻

9. Future Capabilities

The V3 architecture must remain compatible with future:

* Android and iOS applications
* Offline mobile synchronization
* ChatGPT and Gemini integrations
* External API consumers
* Advanced maps and search
* Curated camping discovery
* Private-property booking services

Compatibility does not authorize implementation.

Do not build future features until a specific implementation task has been approved.

⸻

10. Definition of Task Completion

A task is complete only when:

* The requested change is implemented.
* Applicable acceptance criteria are satisfied.
* Existing relevant functionality remains intact.
* No unauthorized changes were introduced.
* Required checks have been performed or limitations reported.
* Changed files and unresolved issues are documented.

If the task cannot be completed safely, report the blocker and the work completed.

Do not fabricate a successful implementation or expand the scope to conceal the problem.

⸻

11. First Codex Assignment

The first development assignment is a read-only audit of the existing V2 repository.

The audit must identify the current reporting architecture, database relationships, shared services, duplicated definitions, moderation dependencies, permission checks, and relevant presentation components.

Its purpose is to determine what V3 can reuse and what actually requires modification.

Do not begin rebuilding the reporting system during this audit.

Wait for the detailed audit task and acceptance criteria before making implementation changes.

⸻

Governing Principle

Preserve V2. Unify the reporting foundation. Prepare for future integrations.

Llama Scout V3 must be built through small, deliberate, verifiable improvements to the existing application.

The goal is a reliable, maintainable platform with one source of truth, not a larger collection of competing implementations.

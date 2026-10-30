Llama Scout V3

Incremental Implementation Plan

Version: 3.0
Status: Planning complete; repository audit pending
Development branch: v3-development
Production: Existing V2 application

Governing references

* AGENTS.md
* docs/PROJECT_SCOPE.md
* docs/ARCHITECTURE.md
* docs/SCOUT_REPORT_SPEC.md
* docs/DATA_SOURCES.md
* Approved Scout Report PDF

⸻

1. Purpose

This document organizes Llama Scout V3 into small, independently executable tasks.

The project must not be implemented through one large Codex instruction.

Every task must have a defined objective, limited scope, verification requirements, and a stopping point.

Completed work must be recorded so future Codex sessions can continue without relying on previous conversation history.

The objective is not to rewrite Llama Scout.

It is to preserve the working V2 application, correct the canonical reporting foundation, and incrementally introduce V3 capabilities.

⸻

2. Execution Rules

One Task Per Execution

Codex must work on exactly one assigned task.

A task is not authorization to complete its entire parent milestone.

After completing the assigned task, Codex must:

1. Summarize the work performed.
2. Identify changed files.
3. Report verification results.
4. Document unresolved issues.
5. Stop and return control to the user.

Do not automatically begin the next task.

Selective Context

Codex must read AGENTS.md and the relevant portions of the project documentation.

Do not automatically load every specification in full.

Read additional sections only when required by the assigned task.

Inspect relevant implementation files before making changes.

Scope Boundaries

Avoid modifying unrelated systems.

Do not introduce replacement implementations where working V2 services already exist.

Do not modify production data, credentials, deployment configuration, or the production branch without separate authorization.

Independent Verification

Each task must have observable acceptance criteria.

Where automated testing is unavailable, record what was inspected and what still requires testing.

Do not claim success based solely on code generation.

⸻

3. Progress Tracking

Maintain an implementation progress record at:

docs/V3_PROGRESS.md

This record is created during the first approved Codex audit task.

Each entry should contain:

* Task identifier
* Status
* Completion date, if completed
* Relevant commit or pull request
* Files inspected or modified
* Verification performed
* Unresolved issues
* Next approved action, if any

Supported statuses:

* Not started
* In progress
* Blocked
* Ready for review
* Approved
* Completed

A task cannot be marked Completed solely because Codex generated code.

Required verification and review must be satisfied.

⸻

4. Milestone 0: Preserve and Audit V2

Goal: Understand the existing application before making architectural changes.

Modification policy: Read-only investigation, with separately authorized documentation output.

V3-001: Repository and Infrastructure Inventory

Identify:

* Main application structure
* Shared PHP services
* Database configuration patterns
* Existing CSS and JavaScript
* Fonts, icons, and branding
* Authentication and account systems
* Administrative modules
* Map and geometry components
* Importer infrastructure
* Current testing and deployment mechanisms

Deliverable: docs/audits/01-repository-inventory.md

Acceptance: Existing systems and important dependencies are documented without modifying application code.

V3-002: Reporting Definition Audit

Identify all currently implemented question registries, definitions, answer options, and dependency rules.

Find places where individual interfaces independently define or modify question behavior.

Deliverable: docs/audits/02-reporting-definitions.md

Acceptance: Existing canonical definitions and suspected duplication are identified with code references.

V3-003: Reporting Interface Audit

Inspect:

* Add Place
* Add Site
* Suggest Update / Update This Place
* Moderation
* Administrative editing
* Published Scout Reports
* Historical comparisons
* Area and Site editing

Document where each interface obtains questions, answers, and display rules.

Deliverable: docs/audits/03-reporting-interfaces.md

Acceptance: The complete reporting data flow is documented, including inconsistent implementations.

V3-004: Database and Historical Records Audit

Inspect available schema definitions, migrations, and relevant persistence code.

Identify:

* Place relationships
* Site relationships
* Area relationships
* Current answer storage
* Submitted report storage
* Historical observations
* Source provenance
* Photo associations
* Publication state

Do not access production databases or request production credentials unless separately authorized.

Deliverable: docs/audits/04-data-and-history.md

Acceptance: Existing data relationships and preservation risks are documented. Unknown runtime details are explicitly identified.

V3-005: Permissions and Moderation Audit

Inspect existing:

* Membership access
* Earned Place access
* Scout permissions
* Master Scout permissions
* Admin permissions
* Owner-only functionality
* Self-approval restrictions
* Submission and publication workflows

Deliverable: docs/audits/05-permissions-and-moderation.md

Acceptance: Existing permissions are mapped without changing authorization behavior.

V3-006: External Data Import Audit

Inspect existing:

* RIDB integrations
* NPS configuration and code
* USFS and BLM integrations
* Source databases
* Field normalization
* Duplicate detection
* Conflict resolution
* Import tracking
* Photo imports

Deliverable: docs/audits/06-external-data.md

Acceptance: Working, incomplete, and missing components are distinguished.

V3-007: Approved Scout Report Reconciliation

Compare the approved Scout Report PDF against actual implementation.

Identify:

* Missing questions
* Duplicate questions
* Incorrect ownership
* Incorrect order
* Inconsistent answer options
* Incorrect dependencies
* Hidden applicable questions
* Retired fields still in use
* Historical compatibility issues

Produce a complete question-by-question inventory.

Deliverable: docs/audits/07-scout-report-reconciliation.md

If a separate machine-readable inventory is useful, propose it before creating one.

Acceptance: Every approved question can be traced to its existing implementation or explicitly identified as missing.

Milestone 0 Completion Gate

Review all audit findings.

Determine which functionality can be reused and which specific components need modification.

Do not start implementation until the findings have been reviewed and a small initial task approved.

⸻

5. Milestone 1: Canonical Reporting Definitions

Goal: Eliminate inconsistent question definitions.

This milestone begins only after Milestone 0 approval.

Planned work includes:

* Resolve identified question ownership conflicts.
* Establish authoritative question keys.
* Normalize question definitions and answer options.
* Preserve existing identifiers where possible.
* Centralize applicability and validation rules.
* Establish Public and Community visibility.
* Implement tests for canonical definitions.
* Migrate individual interfaces to consume those definitions.

Do not migrate every interface simultaneously.

Each implementation task should address one limited responsibility or interface.

Existing report storage and publication behavior must continue working.

Completion Gate

All applicable interfaces retrieve consistent canonical definitions.

Question-by-question reconciliation identifies no unexplained discrepancies.

⸻

6. Milestone 2: Canonical Answers and History

Goal: Ensure that partial reports, imported facts, and historical observations work consistently.

Planned work includes:

* Audit and unify answer reading.
* Audit and unify answer validation.
* Audit and unify answer persistence.
* Preserve submitted report history.
* Preserve visit dates and contributor identity.
* Preserve imported source attribution.
* Establish current-answer selection rules.
* Prevent unanswered questions from overwriting previous information.
* Preserve explicit negative, unknown, and not-tested answers.
* Preserve existing comparison functionality.

Any storage migration must have a documented backup, compatibility, and recovery procedure.

Required Verification

A complete report followed by a one-question update must preserve all unrelated answers.

Historical observations must remain accessible.

Conflicting approved observations must retain their original values and attribution.

Completion Gate

Current published answers and historical reports remain consistent across all applicable reporting interfaces.

⸻

7. Milestone 3: Unified Reporting Interfaces

Goal: Connect existing interfaces to the canonical reporting foundation.

Implement changes one interface at a time.

Suggested order:

1. Add Place and initial Site
2. Add Site to an existing Place
3. Update This Place
4. Moderation
5. Admin editing
6. Published Scout Report
7. Historical comparison
8. Existing Area and Site editing

The order may be revised after the audit if dependencies require it.

Existing moderation, permissions, and styling must remain intact.

Completion Gate

Every applicable interface uses the canonical definitions and data-handling rules.

Existing functionality is verified before moving forward.

⸻

8. Milestone 4: V3 Presentation and Map

Goal: Introduce the V3 map-first experience while preserving V2’s design and functionality.

Planned work includes:

* Improve current-answer card presentation.
* Display date and contributor/source on one line.
* Preserve the existing comparison tool.
* Make the interactive map the homepage.
* Consolidate informational homepage content into About.
* Preserve existing URLs and appropriate redirects.
* Add Resource map markers and detail cards.
* Extend map filters and discovery controls.
* Preserve existing polygon and Site marker behavior.
* Apply dispersed camping visibility restrictions consistently.

The existing CSS, Manrope fonts, icons, branding, and responsive layout conventions must be reused.

Completion Gate

Users can navigate the map-first experience without losing existing Place, Site, account, or administrative functionality.

⸻

9. Milestone 5: Expanded Data Sources

Goal: Increase nationwide Place and Site coverage without duplicating data.

Implement one provider at a time.

Proposed sequence:

1. Existing RIDB importer improvements
2. NPS integration
3. USFS recreation datasets
4. BLM recreation points
5. BLM/USFS land boundaries
6. Additional approved state and commercial sources

Every integration must preserve original source records.

New and changed values must pass through normalization and reconciliation before publication.

Do not build all importers simultaneously.

Completion Gate

Imported records are traceable, repeatable, correctly matched, and compatible with existing Llama Scout data.

⸻

10. Milestone 6: Shared API Foundation

Goal: Expose existing canonical services to additional applications.

Initial API work should be read-only.

Potential capabilities include:

* Authorized Place search
* Site discovery
* Scout Report retrieval
* Historical observation retrieval
* Public Resource discovery
* Map information retrieval

Use existing authorization and reporting services.

Do not implement a second business-logic layer.

Completion Gate

The API returns the same authorized information as the existing website without exposing restricted records.

⸻

11. Future Projects

The following are separate projects, not automatic V3 implementation tasks:

* Android applications
* iOS applications
* Offline synchronization
* ChatGPT MCP integration
* Google Gemini integration
* Third-party developer API access
* Advanced curated camping products
* Private-property reservations
* bookings.llamascout.com

These projects may depend on completed V3 services.

Their future inclusion does not authorize premature development.

⸻

12. Standard Codex Task Format

Every implementation task should use this structure:

Task ID: Unique identifier

Objective: One specific outcome

Required references: Only the applicable specification sections

Existing implementation: Files or services to inspect

Allowed changes: Explicitly scoped modifications

Protected systems: Functionality that must not change

Acceptance criteria: Observable conditions for success

Verification: Tests or inspections required

Deliverable: Code, audit, or documentation output

Stop condition: Report results and await review

Do not combine unrelated tasks.

If work reveals an unexpected dependency, document it and stop before expanding scope materially.

⸻

13. Standard Task Completion Report

Every Codex task must finish with:

Completed

What was actually accomplished.

Files

Files inspected, created, or modified.

Verification

Tests performed and their results.

Issues

Unresolved questions, failures, and risks.

Next

The next recommended task, without beginning it.

This report should be concise and evidence-based.

⸻

14. Failure and Timeout Recovery

If a task fails or times out:

1. Inspect the current repository state.
2. Determine what changes, if any, were actually saved.
3. Identify incomplete or unverified work.
4. Preserve useful progress where safe.
5. Divide the remaining work into smaller tasks.
6. Resume only after the scope is clear.

Never assume that a failed execution made no changes.

Never rerun a large failed task without inspecting its previous results.

⸻

15. Definition of V3 Completion

V3 is complete when the approved V3 product requirements are implemented and verified without losing working V2 functionality.

The canonical reporting foundation must be stable before major dependent features are released.

Completion requires evidence, not merely generated code.

The project should remain deployable and maintainable through incremental changes.

⸻

16. Immediate Next Action

Begin V3-001: Repository and Infrastructure Inventory.

This is a read-only task.

Do not modify PHP, JavaScript, CSS, database schemas, configuration, or production systems.

Create only the approved audit documentation and progress tracking records.

Report findings and stop.

No other V3 task is authorized by this document.

⸻

Development principle:

One task. Limited context. Minimal changes. Verified results. Preserve V2.

Know the place before you go.

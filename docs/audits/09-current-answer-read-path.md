# M2-001: Current-Answer Read Path Audit

**Audit date:** 2026-10-30

**Repository base:** `v3-development` after Milestone 1 completion

**Method:** Static repository inspection only. No database writes, migration, or production-data mutation was performed.

## 1. Objective

Milestone 2 begins by answering one question:

> What does Llama Scout currently mean by the "current answer" for a Place, Area, or Site?

This audit traces the implemented read paths before any persistence or history changes are made.

The purpose is to preserve working precedence while identifying where provenance, state, and historical selection are incomplete.

## 2. Current Place answer source

The canonical Place current-value reader is:

`llama_place_report_current_values()`

in:

`app/place-report/persistence.php`

It reads the storage definitions produced by:

`llama_place_report_published_definitions()`

Current Place answers therefore come from the mutable published fact tables:

- `places`
- `place_details`
- `place_amenities`
- `place_connectivity`
- `place_sensory`
- `place_sensory_details`
- `place_rules`
- `place_experience`

The current reader normalizes values through the canonical field definitions.

It does not read the most recent contribution, submission, update, or historical report and derive current state from that history.

### Place answer state

Published Place Unknown state is stored separately in:

`places.place_report_answer_state`

and read by:

`llama_place_report_published_answer_state()`

For Place answers, current value and current answer state are therefore a composition of:

1. physical published value
2. published Unknown-key metadata

An absent/null physical value is not sufficient by itself to distinguish Unknown from Unanswered.

## 3. Published Place Scout Report path

The published Scout Report starts with the currently loaded Place record and then calls:

`llama_place_report_data_from_published_place()`

The function:

- normalizes nested published data
- derives compatibility fields
- applies the current published Unknown-key list

This is a presentation normalization step over mutable current Place facts.

It is not a historical current-answer selector.

## 4. Update-place current-value path

`account/update-place.php` reads current Place values through:

`llama_place_update_current_values()`

which delegates directly to:

`llama_place_report_current_values()`

It separately reads:

`llama_place_report_published_answer_state()`

The contributor therefore edits against the same mutable current Place projection used by moderation conflict checking.

For a revision in Needs Changes state, the contributor's pending proposed values are overlaid on that published current state for form presentation.

Those pending values do not become current published answers until approval.

## 5. Scoped Area/Site answer source

Area and Site current answers are stored in:

`place_report_scoped_answers`

Each row is identified by:

- Place
- scope type
- scope source
- target ID
- canonical field key

The scoped current reader is:

`llama_scoped_report_load()`

The effective current-answer resolver is:

`llama_scoped_report_effective_answers()`

and its batched form:

`llama_scoped_report_effective_answers_for_targets()`

## 6. Scoped precedence

The current scoped precedence is deterministic.

For an Area:

1. direct Area answer
2. Place answer, when the field allows Place inheritance

For a Site:

1. direct Site answer
2. parent Area answer, when allowed
3. Place answer, when allowed

The nearest recorded permitted answer wins.

This is implemented through:

`llama_place_report_resolve_scoped_answer()`

## 7. Recorded versus missing semantics

The scoped resolver intentionally uses:

`array_key_exists()`

rather than truthiness.

Therefore all of the following are recorded answers and stop inheritance:

- explicit No / false
- numeric zero
- explicit null
- canonical Unknown token
- explicitly recorded empty list

Only a genuinely absent field inherits from an ancestor.

This behavior already matches a core Milestone 2 requirement and must be preserved.

## 8. Clear override semantics

Scoped clear is not represented as a blank/null answer.

Clearing a scoped override deletes the scoped row.

The effect is:

> resume inheritance from the nearest ancestor

This distinction is important and must remain separate from:

- explicit Unknown
- explicit null
- explicit No
- Unanswered

## 9. Canonical campsite imported baseline

A canonical Site using source:

`campsite_record`

has an additional read layer.

`app/place-report/campsite-baseline.php` translates imported campsite facts into canonical Scout Report field keys.

`llama_place_report_campsite_answers()` then overlays direct scoped Site overrides over that imported baseline.

The effective Site precedence becomes:

1. direct Site Scout/Admin override
2. imported campsite baseline
3. inherited Area answer
4. inherited Place answer

subject to the field's canonical scope rules.

For Site-only fields, inherited Place/Area values are not eligible.

## 10. Imported baseline refresh behavior

Imported Site facts are evaluated at read time.

This means a source refresh can change the current imported baseline even when no Scout contribution occurred.

A direct scoped override still wins.

Historical reconstruction cannot currently reproduce an older imported baseline unless that source state was preserved elsewhere.

This is a Milestone 2 history/provenance gap, not a reason to remove read-time baseline composition.

## 11. Provenance currently returned by scoped resolution

Scoped resolution returns a `provenance` entry containing:

- scope
- source
- target ID

For ordinary Place/Area/Site inheritance, this identifies whether the selected value came from:

- Place
- Area map feature
- selected Site target

### Confirmed provenance loss for canonical campsite baseline

After `llama_place_report_with_campsite_baseline()` composes imported campsite facts with direct Site overrides, every composed direct value is marked as:

- scope = Site
- source = `campsite_record`
- ID = campsite ID

The result no longer distinguishes:

- an imported baseline answer
- a direct Scout/Admin Site override

The model does retain the original direct override map separately as:

`overrides`

but field-level `provenance` does not say which Site values were imported versus overridden.

This is a confirmed read-model gap.

## 12. Public campsite detail path

`api/place-campsite-detail.php` follows the same general model:

1. build current published Place report data
2. convert Place data to scoped canonical values
3. resolve Place/Area/Site answers
4. compose the canonical campsite imported baseline
5. expose inherited/direct/effective display data

This confirms the Site detail interface is using the same effective-answer composition model rather than maintaining an independent current-answer precedence.

## 13. Scoped update path

Scoped update submission and approval use:

`llama_place_report_target_model()`

to capture the current effective value at submission/review time.

Approval performs conflict checks against both:

- the effective inherited/imported current answer
- the direct scoped answer currently stored for that target

This is useful protection against silently approving an update over a value that changed after submission.

## 14. Place update conflict path

Place updates use:

- `llama_place_report_current_values()`
- `llama_place_report_published_answer_state()`

Approval compares the contributor's original snapshot against current published value and Unknown state before writing.

This protects the mutable Place current projection from stale approvals.

## 15. Current answer versus historical evidence

Several historical stores exist:

- `place_submissions`
- `place_update_submissions`
- `revision_history`
- `place_contributions`
- imported source/fact tables
- comparison reconstruction
- public history timeline

None of these is currently the authoritative current-answer selector.

They provide evidence, workflow history, activity provenance, or reconstructed versions around the mutable current projection.

## 16. Diagnostic append-only report contract

`app/place-report/diagnostics.php` expects an append-only contract involving:

- `scout_report_schemas`
- `scout_reports`
- `scout_report_answers`
- `scout_report_moderation_events`
- `scout_report_photos`

The repository audit still finds no active current-answer reader or writer using those tables.

They therefore cannot currently be treated as the source of truth for current answer selection.

Before Milestone 2 integrates or replaces current storage with an append-only answer ledger, the actual deployed schema must be verified.

## 17. Current read-path strengths

The existing implementation already provides several behaviors that should be preserved:

1. One canonical field registry controls value normalization.
2. Place current reads are shared by Update and moderation.
3. Area/Site current resolution is deterministic.
4. Explicit No, zero, null, Unknown, and empty lists stop inheritance.
5. Clearing an override resumes inheritance rather than creating a fake blank answer.
6. Imported campsite baseline stays separate from direct scoped override storage.
7. Direct Site overrides beat imported baseline.
8. Scoped update conflict detection checks both direct and effective values.
9. Place update conflict detection checks both value and Unknown state.
10. Partial Place publication writes only supplied registered keys.

## 18. Confirmed current-read gaps

### Gap A: no canonical answer envelope

The code returns values, state, and provenance through different structures depending on scope.

There is no single current-answer object containing, for every field:

- field key
- answer state
- value
- target scope/source/id
- origin type
- contributor/source attribution
- visit date
- submission/approval date
- imported source timestamp
- historical report/contribution identity

### Gap B: Place provenance is weak

Current Place reads return storage values but not field-level origin.

A value may have come from:

- imported source
- original Scout report
- later approved update
- Admin edit

The current read function does not expose which one produced the current value.

### Gap C: canonical campsite provenance collapses baseline and override

Imported baseline and direct Site override are distinguishable internally only because the target model retains an `overrides` map.

The field-level provenance map labels both as Site / campsite_record.

### Gap D: imported source refresh can change current baseline without historical snapshot

The current Site display is intentionally source-backed, but historical replay cannot reproduce an older baseline without a snapshot/version reference.

### Gap E: mutable current projection is not derived from immutable history

Current Place and scoped answers are mutable state.

Historical contribution/submission records do not currently drive current-answer selection.

### Gap F: Place and scoped answer-state models differ

Place Unknown is an external key list plus physical value.

Scoped Unknown is stored directly as the canonical Unknown token.

The effective result can be displayed consistently, but the underlying state representation is different.

### Gap G: explicit scoped null is recorded

A scoped row whose JSON value is null is intentionally treated as recorded and stops inheritance.

That is valid only if the caller intentionally means an explicit answered-null state.

Future canonical parsing must not turn omission into a null scoped row.

## 19. Current precedence summary

### Place

Current published fact value + published answer-state metadata

There is no competing report-history precedence at read time.

### Area

1. Area direct scoped answer
2. eligible Place current answer

### Map-only Site

1. Site direct scoped answer
2. eligible parent Area answer
3. eligible Place current answer

### Canonical campsite Site

1. direct Site scoped override
2. imported campsite baseline
3. eligible parent Area answer
4. eligible Place current answer

This is the precedence that later Milestone 2 work should preserve unless an explicit product decision changes it.

## 20. Recommended Milestone 2 direction

Do not replace the current projection immediately.

First introduce a canonical **current answer read model** that wraps the existing precedence and exposes consistent state/provenance.

The read model should be able to represent:

- value
- answered / unknown / unanswered state
- inherited versus direct
- target identity
- origin type: published Place, scoped override, imported baseline
- source/provider identity where known
- current effective answer separately from underlying layers

That adapter can then be consumed by interfaces before persistence/history is changed.

After the read model is stable, later Milestone 2 tasks can link immutable approved reports/history to the current projection without changing every interface at once.

## 21. M2-001 result

**Audit complete. No storage or behavior change made.**

The current effective-answer precedence is sufficiently deterministic to preserve as the compatibility baseline.

The next task should be:

**M2-002: Canonical Current Answer Read Model**

That task should introduce a shared answer envelope over the existing Place / Area / Site / imported-baseline readers without yet changing write precedence or migrating historical data.

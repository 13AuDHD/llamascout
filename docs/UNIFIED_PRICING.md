# Unified Place pricing

The Place-owned `place_rate_cards_json` question is the only active campsite rate editor. It is registered in the shared Add Place / moderation / Edit Place form. Saving Edit Place publishes it inside the existing report transaction and audit. The former standalone JSON-column writer now uses that same service. Ordinary members still cannot directly change published Place prices. Verified host ownership, booking inventory, payments, cancellation rules and reservation quotes remain future work.

## Deployment

1. Review and run `tools/migrations/20261106_unified_rate_card.sql` before deploying. It only adds `place_rules.rate_cards_json` when absent. No historical prices, columns, mapped rates or reports are deleted.
2. Deploy the PR together, including PHP, JavaScript and CSS. Do not deploy only the editor or only the readers.
3. Open Edit Place, expand Campsite Rates, inspect the imported legacy rows and run Check Site prices for representative dates. Save Place Report to publish the consolidated card.
4. Verify the public Place hero, rate table, map card, Site selector and Site map popup for that Place. Repeat for Free, paid, seasonal, and restricted Places using the appropriate viewer roles.

An untouched Place uses a read adapter for its existing flat price, rate-card lists and mapped/class rates. Saving the new editor writes version 2; version 2 always supersedes old prices, including when its rows are explicitly empty. Historical mapped SQL records stay untouched for rollback. Geometry saves preserve their stored legacy rates/classes and cannot become another price editor. Original old rate-card lists are retained in `legacy_cards` inside the converted document. Undated legacy holidays, undated named seasons, mixed free-text scopes, and free-text individual Site selections require review rather than becoming campground-wide rates.

Rollback: revert the application commit. Keep the nullable JSON column and its values; do not drop it. Legacy tables are retained, but old-code rollback will show the old price model rather than interpret version 2 schedules. Back up Place rules before any operator-approved bulk conversion.

## Price rules

- Versioned document with stable row IDs, USD, configurable weekend nights, holiday/event date periods, reusable matching rows and optional seasonal schedules. Dates denote nights inclusively; they are not checkout dates. Recurring MM-DD periods may cross New Year. Pricing dates are separate from operating-season closures and do not establish availability.
- Row conditions include size category, layout, hookups, exact imported type, loop and individual canonical Site IDs. Existing Area and mapped Site selectors are preserved by the legacy adapter. Selectors do not require drawing a new polygon.
- Resolve the most specific matching row first. Explicit Site selectors take precedence over category rows; Area selectors take precedence over general rows. Equal matches or missing potentially relevant attributes yield Needs pricing review. Missing prices stay Unknown; explicit zero is Free.
- Within a row: its active season replaces the corresponding base-schedule cells; missing cells inherit the corresponding base-schedule cells. Weekend replaces base for configured nights. The generic holiday cell replaces weekend/base. A price for a specific holiday/event replaces the generic holiday cell. Different overlapping event prices or overlapping seasons yield review, never summed surcharges.
- Explicit weekly/monthly/stay/season and per-person/per-vehicle prices retain their units. They are never divided into guessed nightly rates. Existing reservation, extra vehicle, membership and entrance/parking surcharge fields retain their canonical storage and appear within Additional fees. No booking total, tax amount, discount entitlement or availability is invented.
- RIDB Capacity/Size Rating, driveway layout, hookup attributes, exact source type and Loop select the matching row. Confirmed canonical Site attributes take precedence. STANDARD alone does not establish Single; driveway width and occupancy do not establish Double. Day-use picnic/shelter records do not inherit overnight rates. No undocumented Recreation.gov pricing API or text-derived price scraper is introduced.

## Readers and access

`app/place-pricing.php` validates, adapts, resolves and formats all prices. Site list/catalog/detail, Site map points, mapped geometry, supplemental overnight options, Place hero/table and discovery hero cards consume it. Sites are filtered by existing access checks before their price payloads are emitted. Public Place summaries omit individual and mapped-target rates/IDs. Public price payloads do not include selectors or private matching attributes. Admin preview uses the same resolver with a selected date, returns coverage counts and lists unresolved Sites. Published ranges are information, not live booking quotes.

## Verification

- `php tools/tests/place-pricing.php`: date precedence, holiday/event differences, zero/unknown, missing attributes, overlap conflicts, individual overrides, source validation, recurring seasons, units, canonical parsing and full report building, SQLite persisted document reads, approved Site attribute precedence, public projection, and 400-Site matching.
- `NODE_PATH=<jsdom installation> node tools/tests/place-pricing-ui.cjs`: production editor DOM, one form payload, fees, zero and inheritance, seasonal and holiday editing, row changes, holiday-specific amounts, escaped text.
- Existing reporting, coordinate/Site visibility, photo access and Place page access smoke checks.

Production MariaDB, a live signed-in save and visual browser QA still need the deployment checks above. SQLite tests do not execute the MariaDB migration or spatial queries.

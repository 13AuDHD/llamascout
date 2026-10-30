# V3-001 Repository and Infrastructure Inventory

**Status:** Ready for review

**Inventory date:** 2026-10-29

**Base branch:** `v3-development`

**Base commit:** `463462c9e64143908d0e77895393b2372a12e7aa`

## Purpose and method

This inventory records the major V2 systems that V3 must preserve or deliberately reuse. It is a targeted, read-only review of repository structure and representative implementation files. It is not a line-by-line audit, a database schema audit, or a runtime certification.

Findings marked **Confirmed** are supported by files in the repository. Findings marked **Needs investigation** identify code whose live configuration, data, permissions, or end-to-end behavior was not established in this task. No production database or private configuration was accessed.

## System relationships

The public site, account site, administration site, and API endpoints are PHP entry points that load shared services from `app/`. Most requests converge on `app/bootstrap.php`, which initializes configuration, database access, error handling, authentication, maintenance controls, place/report services, and several background-maintenance helpers. Shared templates live in `partials/`; page and feature assets live in `css/`, `js/`, `assets/`, and `images/`.

The primary MariaDB/MySQL-compatible application database is accessed through `db()`. Separate configured connectors exist for reference data, RIDB source data, and cell-coverage data. Place reporting is organized around `app/place-report.php` and the modules under `app/place-report/`. Moderation promotes submitted or proposed data into published place data. Public map and place pages then read the published results.

## 1. Website entry points and shared PHP services

**Confirmed**

| Area | Main files | Purpose and dependencies |
| --- | --- | --- |
| Shared initialization | `app/bootstrap.php`, `app/config.php`, `app/database.php` | Loads private configuration, database access, error logging, authentication, maintenance mode, place/report, profile, photo, shop, and other shared services. Starts sessions and applies request guards. |
| Public site | `index.php`, `map.php`, `place.php`, `add-place.php`, `compare.php`, `place-history.php`, `shop.php`, `checkout.php` | Primary public and member-facing page entry points. They depend on shared services in `app/` and common presentation in `partials/`. |
| Account site | `account/login.php`, `account/register.php`, `account/profile.php`, `account/contributions.php`, `account/update-place.php`, `account/membership.php`, `account/security.php` | Authentication, profile, contribution, place-update, membership, and security interfaces. |
| Administration | `admin/_header.php`, `admin/_dashboard.php`, and the feature pages under `admin/` | Admin navigation and interfaces for users, places, moderation, reports, imports, memberships, shop operations, system health, and errors. |
| APIs and endpoints | `api/places.php`, `account/api/`, `admin/api/`, `photo-upload.php`, `stripe-webhook-shop.php` | JSON/data endpoints, uploads, and webhook receivers. Endpoint-specific services are loaded from `app/`. |
| Shared presentation | `partials/header.php`, `partials/footer.php`, `partials/place-report/` | Site shell and reusable report form/read-only rendering. |

`app/bootstrap.php` also invokes opportunistic maintenance work for features such as badges, membership promotions, support, newsletters, Scout renewals, shipments, and shop cleanup. Exceptions from those helpers are caught and logged so normal page requests can continue.

**Functionality to preserve:** the common bootstrap path, shared page shell, separation between public/account/admin surfaces, and reusable service modules.

**Needs investigation:** a later architecture audit should identify the few entry points that intentionally do not use the standard bootstrap and document their equivalent protections.

## 2. Database connection architecture

**Confirmed**

- `app/config.php` and `app/database.php` load private configuration from outside the public repository. No private values are stored in the reviewed files.
- `app/database.php` creates PDO MySQL connections using `utf8mb4`, exceptions, associative fetching, native prepared statements, and UTC session time.
- Four named connection paths exist:
  - `db()` for the primary application database.
  - `reference_db()` for reference data.
  - `ridb_db()` for Recreation Information Database source records.
  - `cell_db()` for cell-coverage data.
- SQL is primarily issued through prepared PDO statements in the service modules.
- Several services contain `CREATE TABLE IF NOT EXISTS` or schema-ensure helpers, including membership, shop, Scout, MFA, error-log, NPS, and import-related modules.

**Functionality to preserve:** separate source/reference databases, UTC and Unicode connection behavior, prepared queries, and private configuration outside the web repository.

**Needs investigation:** V3-002 must establish the actual production schema, keys, constraints, migrations, and ownership of runtime schema-ensure routines. The number and topology of live database servers were not verified.

## 3. Authentication, accounts, and memberships

**Confirmed**

| Capability | Main files | Notes |
| --- | --- | --- |
| Sessions and authentication | `app/auth.php`, `app/session-invalidation.php`, `app/email-verification-guard.php` | Login state, remembered login, logout, current-user helpers, session invalidation, and verified-email enforcement. |
| Strong authentication | `app/mfa.php`, `app/passkeys.php`, `app/passkey-library.php` | MFA and passkey support. `app/auth.php` documents stronger authentication requirements for Owner/Admin sessions. |
| Account recovery and access | `account/login.php`, `account/register.php`, `account/forgot-password.php`, `account/reset-password.php`, `account/verify-email.php`, `account/security.php` | Account creation, login, recovery, verification, and security management. Cloudflare Turnstile is integrated into login, registration, and password-recovery flows. |
| Profiles and contribution history | `app/community-profiles.php`, `app/profile-images.php`, `account/profile.php`, `account/contributions.php` | Member profiles, profile images, community presentation, and contribution views. |
| Membership access | `app/access.php`, `app/memberships.php`, `app/stripe.php`, `account/membership.php`, `account/checkout.php`, `account/billing.php`, `account/stripe-webhook.php` | Access checks, membership state, Stripe checkout/billing, and membership webhook processing. |

Roles are stored through `roles` and `user_roles` queries in `app/auth.php`. Explicit compatibility and inheritance rules include Owner inheriting Admin, Master Scout inheriting Scout, and compatibility for both `master-scout` and `master_scout` slugs. `app/access.php` distinguishes paid membership from broader member access and accounts for privileged and active Scout access.

**Functionality to preserve:** remembered sessions, verification, MFA/passkeys, role inheritance, profile data, contribution history, membership status, and Stripe membership workflows.

**Needs investigation:** V3-005 should map every permission and membership state, including earned or complimentary access, expiration behavior, and all administrative overrides.

## 4. Owner, Admin, Master Scout, and Scout functionality

**Confirmed**

- Owner/Admin authorization helpers are in `app/auth.php`; administrative moderation gates are also centralized in `app/moderation.php`.
- Scout lifecycle services include `app/scout-onboarding.php`, `app/scout-policy.php`, `app/scout-ranks.php`, `app/scout-stats.php`, and `app/scout-maintenance.php`.
- Scout interfaces include `account/scout.php`, `account/scout-application.php`, `account/scout-invite.php`, and `account/scout-training.php`.
- Master Scout review is implemented through `app/master-scout.php`, `app/master-scout-moderation.php`, `account/master-moderation.php`, and `account/master-review.php`.
- `app/master-scout-moderation.php` supports review of new-place and update submissions, uses the common moderation/report services, and prevents self-review.
- Administrative Scout management includes `admin/scouts.php`, `admin/scout.php`, `admin/policies.php`, and `admin/points.php`.

The Master Scout interface describes a deliberately narrower scope than Admin: moderation work without general user administration, site settings, or unrestricted publication tools.

**Functionality to preserve:** role-specific dashboards, Scout onboarding/policy/rank/points behavior, Master Scout review queues, and self-review prevention.

**Needs investigation:** exact state transitions, notification rules, renewal timing, and every Owner-only action require the dedicated roles/permissions audit.

## 5. Administration and moderation

**Confirmed**

The `admin/` area covers users, places, campsite records, submissions, updates, reports, map proposals, verifications, memberships, Scouts, products, orders, integrations/importers, errors, audit records, and system operations. Shared service layers include `app/admin-places.php`, the `app/admin-place-*.php` modules, `app/admin-users.php`, `app/admin-system.php`, and `app/admin-errors.php`.

Moderation is implemented through `app/moderation.php`, `admin/moderate-submission.php`, `admin/moderate-update.php`, `admin/moderate-report.php`, and related report/update services. The reviewed code records status/history, moves eligible photos, awards contribution credit where applicable, and promotes approved information into published place data. Admin audit support is present in `admin/audit.php` and the audit helpers used from `app/admin-users.php`.

**Functionality to preserve:** review queues, explicit approval states, transaction boundaries, history/audit records, photo promotion, contributor attribution/credit, and the rule that member submissions do not publish directly.

**Needs investigation:** detailed queue filters, concurrency behavior, rollback/error paths, and notification coverage should be examined in the later administration and workflow audits.

## 6. Scout Report definitions and reporting interfaces

**Confirmed**

`app/place-report.php` identifies itself as the single loader for the Scout Report system. It composes the modules under `app/place-report/`, including:

- `definitions.php` for categories, sections, states, choices, and icons.
- `fields.php` and the `fields-*.php` modules for the field registry.
- `applicability.php`, `conditions.php`, `permissions.php`, and `metadata.php` for display and answer rules.
- `flow-scopes.php` and `scoped-answers.php` for Place, Area, and Site ownership/scope.
- `answers.php`, `runtime.php`, `layout.php`, and `form-cleanup.php` for form and read behavior.
- `persistence.php` for published table mapping.
- `report-targets.php`, `target-model.php`, and the scoped update/review modules for report targets and updates.

The report is used by `add-place.php`, `account/update-place.php`, `admin/place.php`, the submission/update moderation pages, Master Scout review pages, `partials/place-report/form.php`, and `partials/place-report/read-only.php`. Historical and comparison views exist in `place-history.php`, `compare.php`, `app/compare-reports.php`, and `app/place-history-timeline.php`.

Published mappings in the reporting layer reference existing tables such as `places`, `place_details`, `place_amenities`, `place_connectivity`, `place_sensory`, `place_sensory_details`, `place_rules`, and `place_experience`. Area/Site answers are handled separately by the scoped-answer modules.

**Functionality to preserve:** one canonical field definition system, identical question identity across interfaces, explicit answer state, Place/Area/Site scope, conditional questions, historical comparison, and the moderation-to-published-data path.

**Needs investigation:** V3-002 through V3-004 must verify every field, storage target, applicability rule, and form surface. Supplemental save endpoints such as `admin/save-shared-amenities.php`, `admin/save-operating-report.php`, and `admin/save-place-rules-supplemental.php` require reconciliation with the canonical report loader; their presence alone does not establish duplication or a defect.

## 7. Maps, markers, polygons, and mapping tools

**Confirmed**

| Capability | Main files | Notes |
| --- | --- | --- |
| Public map | `map.php`, `js/map.js`, `api/places.php`, `css/map.css` | Leaflet-based public map and place data endpoint. |
| Overlays | `js/map-land-overlays.js`, `js/map-weather-overlays.js`, `js/map-cell-overlays.js` | Land, weather, and cell-coverage overlays. |
| Place geometry editor | `place-map-editor.php`, `js/place-map-editor/`, `app/place-map-features.php`, `app/place-map-features-endpoint.php` | Geometry editing and server-side feature operations. |
| Admin geometry | `admin/partials/place-map.php`, `app/place-map.php` | Administrative map editing within place management. |
| Proposals | `map-contribute.php`, `app/place-map-proposals.php`, `admin/map-proposals.php` | Scout polygon proposals and moderation before publication. |
| Campsites/sites | `app/place-campsites.php`, `app/place-site-identity.php`, `app/place-campsite-media.php` | Canonical campsite/site identity and media relationships. |

`app/place-map-features.php` validates GeoJSON-style coordinates in longitude/latitude order, converts geometry to WKT, and uses MySQL spatial functions. It references polygon/feature, Area detail, campsite, rate, and site-class data, including parent Area relationships. Map proposals keep contributor edits behind moderation.

**Functionality to preserve:** Leaflet public browsing, marker and polygon data, spatial geometry, Area/Site parentage, campsite identity, overlays, and moderated geometry proposals.

**Needs investigation:** V3-006 should confirm coordinate/storage constraints, geometry repair behavior, marker visibility rules, overlay providers, map permissions, and performance with live-scale data.

## 8. CSS, fonts, icons, and branding assets

**Confirmed**

- `css/site.css` defines local `@font-face` rules for Manrope weights 400, 500, 600, 700, and 800 from `assets/fonts/`.
- CSS is split across base/site files plus page and feature directories under `css/site/`, `css/account/`, and `css/admin/`. `app/page-styles.php` supports page-specific stylesheet selection.
- `app/icons.php` provides the shared local SVG icon loader/sanitizer for the large collection in `assets/icons/`.
- `app/branding.php` centralizes light/dark logo URLs for `images/logo-lt.png` and `images/logo-dk.png`.
- `partials/header.php` supplies the shared navigation, theme, and responsive site shell.
- Partner branding assets are supported; for example, `assets/brands/loves/logo.svg` is referenced by partner administration.

**Functionality to preserve:** the Manrope typography, dark/light theme behavior, reusable SVG icons, central logo selection, responsive navigation, and approved partner marks.

**Needs investigation:** a later visual inventory should map every page-specific stylesheet and retire assets only after confirming that templates, emails, and admin tools do not reference them.

## 9. Photo and media handling

**Confirmed**

`app/photo-upload.php` is a shared upload processor for place submissions, place updates, and other authenticated workflows. It detects JPEG, PNG, WebP, HEIC/HEIF, and AVIF input, applies orientation, limits dimensions, emits clean JPEG output, strips metadata, and cleans up a failed batch. `app/photo-staging.php` manages staged files and ownership paths.

Photo/media services and endpoints include `photo-upload.php`, `account/photo-upload.php`, `admin/photo-upload.php`, `app/photo-endpoint.php`, `app/place-campsite-media.php`, `app/profile-images.php`, and `app/shop-images.php`. Repository code references separate upload locations for staging, drafts, submissions, updates, published places, reports, profiles, campsite media, and shop products. `.htaccess` blocks executable script extensions under `uploads/`.

Moderation code moves eligible submission/update photos into published place storage. Imported media code exists in `app/ridb-complete-import.php` and related RIDB modules, including source attribution fields.

**Functionality to preserve:** upload validation, orientation and normalization, metadata stripping, per-user staging ownership, cleanup, moderation promotion, display ordering, attribution, profile photos, site photos, and product images.

**Needs investigation:** actual server image-library support, filesystem permissions/quotas, retention jobs, backup behavior, and orphan cleanup were not runtime-tested.

## 10. External importers, databases, and reconciliation

**Confirmed**

| Source/system | Main files | Existing responsibility |
| --- | --- | --- |
| RIDB / Recreation.gov | `app/ridb.php`, `app/ridb-mirror.php`, `app/ridb-schema.php`, `app/ridb-normalization.php`, `app/ridb-import.php`, `app/ridb-complete-import.php`, `app/ridb-canonical.php`, `admin/ridb.php`, `admin/ridb-import.php` | Separate source database, mirror/catalog/status, normalization, inspection, canonical selection, import, campsite/media extraction, and admin tooling. |
| National Park Service | `app/nps.php`, `app/nps-place-fill.php`, `admin/nps.php`, `admin/nps-ridb-matches.php`, `admin/nps-field-reconciliation.php` | NPS import progress, place fill, RIDB matching, and field reconciliation. |
| U.S. Forest Service | `app/usfs-enrichment.php`, `app/usfs-place-report.php`, `admin/usfs-enrichment.php`, `admin/usfs-source-test.php` | Source testing and enrichment/report support. |
| PAD-US | `app/pad-us-location.php`, `app/pad-us-review.php`, `app/pad-us-sync.php`, `admin/pad-us-sync.php`, `admin/pad-us-units.php` | Protected-area location, classification/review, and synchronization. |
| FCC cell coverage | `app/fcc-cell-v2.php`, `app/fcc-cell-v2-worker.php`, `app/fcc-cell-sync.php`, `admin/cell-coverage-v2.php`, `jobs/cell-coverage-sync.php` | Separate cell database, import/sync workers, admin controls, and scheduled synchronization. |
| Cross-source reconciliation | `app/official-source-reconciliation.php` and source-specific matching modules | Comparison and reconciliation between official sources and Llama Scout place data. |

`docs/DATA_SOURCES.md` describes the intended raw-source, normalization, reconciliation, and provider architecture. The files above confirm substantial implementations, but the specification is not proof that every provider is complete or currently operating. No dedicated BLM-named importer was found; BLM/land data may instead be exposed through public map layers or other reference sources.

**Functionality to preserve:** raw-source provenance, separate source storage, refresh/retry status, source attribution, deliberate normalization, reconciliation before publication, admin review, and scheduled cell sync.

**Needs investigation:** provider credentials, schedules, live completeness, retry history, rate limits, field precedence, duplicate resolution, and BLM data ownership need dedicated source audits. No external API calls or source-database queries were made for this inventory.

## 11. Shop and sales functionality

**Confirmed**

Public commerce entry points include `shop.php`, `product.php`, `cart.php`, `checkout.php`, and `checkout-complete.php`. Services include `app/public-shop.php`, `app/shop.php`, `app/shop-catalog.php`, `app/shop-cart.php`, `app/shop-checkout.php`, `app/shipping.php`, `app/shop-customer-orders.php`, `app/shop-refunds.php`, and `app/shop-returns.php`.

The shop models products, variants/options, images, inventory, carts, pending checkout/order state, shipping, customer orders, fulfillment, refunds, and returns. Shop payment events enter through `stripe-webhook-shop.php` and `app/stripe-webhook-events.php`; this is separate from the membership webhook under `account/`. Printful and Printify adapters, mappings, synchronization, order, cancellation, and webhook-security modules are present. Shipping support includes `app/shipping.php`.

Admin interfaces include `admin/products.php`, `admin/product.php`, `admin/orders.php`, `admin/order.php`, `admin/refund-order.php`, `admin/return-order.php`, and `admin/shop-integrity.php`.

**Functionality to preserve:** catalog/variant identity, inventory reservations, payment event idempotency, membership/shop separation, shipping, fulfillment routing, supplier mappings, customer order views, refunds/returns, product media, and integrity checks.

**Needs investigation:** configured payment/shipping/supplier modes, webhook registrations, tax handling, inventory race behavior, and production reconciliation were not tested.

## 12. Error logging, security, and maintenance

**Confirmed**

- `app/error-logging.php` registers PHP error/exception handling, sanitizes context, groups occurrences by signature, stores application errors, and creates public-safe reference identifiers. `admin/errors.php` and `app/admin-errors.php` support review, resolution, retention, and test operations.
- `app/maintenance-mode.php`, `app/admin-system.php`, and the administration system pages provide maintenance and health controls.
- Authentication protections include password hashing/verification, session invalidation, email verification, MFA, passkeys, role gates, and Cloudflare Turnstile on sensitive public account flows.
- Moderation actions use authentication/authorization and CSRF checks through shared helpers.
- External lookup protection and request limiting are loaded by `app/bootstrap.php`.
- Stripe, Printful, and Printify integrations have dedicated webhook/event security code.
- `.htaccess` contains routing/access rules and blocks executable files under upload paths.
- `jobs/cell-coverage-sync.php` and `jobs/membership-promotion-email.php` are CLI/scheduled maintenance entry points.

**Functionality to preserve:** safe public error responses, internal diagnostic context, occurrence aggregation, auditability, maintenance mode, CSRF and role enforcement, strong authentication, webhook verification, upload execution blocking, and scheduled-job isolation.

**Needs investigation:** host-level headers, log retention in production, backup/restore, cron configuration, secret rotation, dependency patching, incident procedures, and the safety of runtime schema-ensure calls require later operational/security audits.

## 13. Tests, documentation, and deployment configuration

**Confirmed**

- Core project documents are `README.md`, `AGENTS.md`, `docs/PROJECT_SCOPE.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/ARCHITECTURE.md`, `docs/DATA_SOURCES.md`, and `docs/SCOUT_REPORT_SPEC.md`.
- No `tests/` directory or Composer, npm, PHPUnit, or similar test manifest was found at the reviewed depth.
- Targeted administrative diagnostics exist, including `app/admin-testing.php`, `admin/usfs-source-test.php`, `admin/scout-report-workflow-audit.php`, `admin/shop-integrity.php`, and error-log self-test support.
- `.github/workflows/deploy.yml` deploys only `main` to Porkbun cPanel by FTPS. Manual dispatch is also guarded to `refs/heads/main`. A `v3-development` change therefore does not deploy through this workflow.
- Deployment excludes Git metadata, `.github`, `node_modules`, `tests`, `tools`, and `CNAME`; it does not use a destructive clean-slate upload.

**Functionality to preserve:** repository guidance, task sequencing, production-branch deployment guard, non-destructive deployment, and existing operational diagnostics.

**Needs investigation:** there is no confirmed automated regression suite in this branch. V3 planning should decide which critical report, role, moderation, map, import, and shop behaviors need repeatable automated coverage before application changes begin. Hosting/PHP/MariaDB versions and staging deployment arrangements were not verified.

## Preservation checklist for later V3 work

Later tasks should treat the following as preservation-critical until their dedicated audits establish a safe replacement path:

1. Canonical Scout Report definitions and Place/Area/Site ownership.
2. Moderated publication, history, attribution, and role/self-review controls.
3. Authentication, verification, MFA/passkeys, memberships, and role inheritance.
4. Published place data, spatial geometry, campsite identity, photos, and public map behavior.
5. Source provenance, source databases, reconciliation, and scheduled imports.
6. Shop order/payment/inventory/fulfillment state and membership/shop separation.
7. Shared branding, typography, icons, page shell, accessibility, and responsive behavior.
8. Error references, audit trails, maintenance controls, upload security, and production deployment guards.

## Verification and limitations

- Repository paths cited above were checked against `v3-development` at commit `463462c9e64143908d0e77895393b2372a12e7aa`.
- Inspection was limited to repository files and Git metadata. No production database, private configuration, external provider account, webhook dashboard, hosting panel, or deployed site was accessed.
- No PHP, JavaScript, CSS, configuration, specification, database, or application behavior was changed.
- Detailed schema, Scout Report, role, map, importer, security, and commerce audits remain separate planned tasks. These unresolved items are intentionally not presented as confirmed defects.

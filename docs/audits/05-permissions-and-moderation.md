# V3-005: Permissions and Moderation Audit

**Audit date:** 2026-10-29

**Repository base:** `v3-development` after merged V3-004

**Method:** Static repository inspection only. No production data, role assignments, membership records, or deployed moderation queues were accessed.

## 1. Permission architecture overview

Llama Scout currently separates several kinds of authorization that must not be collapsed into one role check:

1. **Authentication and verification** in `app/auth.php`.
2. **Role hierarchy** in `app/auth.php`.
3. **Complete Access / membership entitlements** in `app/access.php`.
4. **Contribution capability labels and gates** in `app/contribution-levels.php`.
5. **Scout status and lifecycle** in Scout profile services.
6. **Admin moderation authorization** in `app/moderation.php`.
7. **Master Scout moderation authorization** in `app/master-scout-moderation.php`.
8. **Workflow-specific ownership and target checks** in contribution/update/report services.

The current system therefore does not have one universal permission matrix. Different workflows combine these layers.

## 2. Authentication and verified-email gates

`app/auth.php` provides `require_login()` and `require_verified_email()`.

Member contribution surfaces reviewed here require a signed-in user with a verified email before contribution-specific checks run.

Examples:

- `add-place.php` calls `require_verified_email()`.
- `account/update-place.php` calls `require_verified_email()`.
- `account/master-moderation.php` and `account/master-review.php` call `require_verified_email()`.

Owner/Admin accounts have stronger authentication requirements. The authentication module explicitly requires MFA for Owner/Admin roles and prevents an older password-only Remember Me token from becoming an MFA bypass after a role promotion.

**Preserve:** verified-email contribution gates and stronger Owner/Admin authentication.

## 3. Role hierarchy

`app/auth.php::user_has_role()` implements these inheritance rules:

| Requested role | Satisfied by |
|---|---|
| `owner` | Owner only |
| `admin` | Admin or Owner |
| `master-scout` | Master Scout role, with legacy `master_scout` slug compatibility |
| `scout` | Scout or Master Scout |

Owner therefore inherits Admin.

Master Scout inherits Scout.

Admin does not automatically inherit Master Scout, and Master Scout does not inherit Admin.

The code also preserves compatibility between `master-scout` and `master_scout`.

## 4. Contribution levels versus authorization

`app/contribution-levels.php` maps internal roles to public contribution levels:

- User
- Member
- Scout
- Master Scout
- Admin

Owner remains Owner internally but is presented publicly as Admin.

The current `llama_contributor_capabilities()` function grants these capabilities to any authenticated user ID greater than zero:

- `submit_place`
- `submit_update`
- `submit_photo`
- `report_problem`
- `check_in`

Scout-level rank is required for `field_report`.

Master-Scout-level rank is required for the generic `moderate_places` capability.

These capability flags are not the whole authorization decision. Individual workflows add further requirements.

For example, `account/update-place.php` checks `submit_update`, while `app/place-update.php` additionally requires Place-specific Complete Access.

## 5. Complete Access

`app/access.php::user_has_member_access()` grants global Complete Access to:

- Owner
- Admin
- eligible paid/trialing/past-due membership states
- complimentary membership state where valid
- active Scout or Master Scout profile with a valid active-through date
- active complimentary membership grants

The function intentionally keeps billing state separate from privileged or Scout access.

`user_has_paid_membership()` is narrower and does not treat Scout/Admin/Owner or complimentary access as paid membership.

**Preserve:** the distinction between paid membership and broader Complete Access.

## 6. Place-specific earned access

`app/access.php::user_original_contributed_place_ids()` derives Place-specific Complete Access from:

- `place_provenance.original_contributor_id`
- approved `place_contributions` rows with `contribution_type = "new_place"`

`user_has_place_complete_access()` grants access when either:

1. the user already has global Complete Access, or
2. the Place is in that original-contribution set.

This means a free member who originally created a published Place retains Complete Access to that Place.

### Confirmed V3 gap

The current earned-access logic does **not** grant Place-specific access merely because a member is the first approved contributor of a new Site inside an existing Place.

The V3 product specification requires that an approved first legitimate Site contribution inside an existing Place can earn access to the entire Place.

That rule is not implemented in the current access function.

This is a confirmed future implementation requirement, not a defect to patch during this audit.

## 7. Add Place permissions

`add-place.php` requires:

1. verified email
2. `llama_contributor_can(..., 'submit_place')`

The current generic capability implementation means any signed-in eligible user ID can pass the contribution capability itself.

Publishing is not direct. A normal member submission enters moderation.

The contributor does not gain Admin publication privileges merely by creating the Place.

## 8. Update permissions

`account/update-place.php` requires:

1. verified email
2. `submit_update` contributor capability

`app/place-update.php` then additionally requires:

- valid user and Place
- `user_has_place_complete_access(placeId, userId)`
- `submit_update`
- no conflicting open update for the same user/Place where applicable

Therefore a free member cannot update arbitrary Places merely because `submit_update` is globally available to authenticated contributors.

The Place-specific Complete Access gate is the material content-access restriction.

Needs-changes resubmission also rechecks Complete Access and contribution capability.

## 9. Scout permissions

Scout is both a contribution level and an access entitlement, but those concepts are implemented separately.

An active Scout profile can provide Complete Access through `app/access.php`.

Contribution-level determination in `app/contribution-levels.php` is role based and does not itself verify the Scout profile's active status.

This creates a distinction:

- role determines the contribution label/rank
- active Scout profile determines the Scout Complete Access entitlement

Whether an inactive Scout role should still appear publicly as Scout-contributed is a policy question for later review.

No behavior is changed here.

## 10. Master Scout moderation access

`app/master-scout-moderation.php::llama_master_moderator_context()` allows moderation access for:

- Owner
- Admin
- a user with Master Scout role **and** an active `scout_profiles` row whose `active_through` has not expired

For Owner/Admin using this shared community moderation service, the returned moderator context is labeled Admin.

Master Scout access therefore requires more than possession of the role slug.

## 11. Master Scout moderation boundaries

The account moderation interface explicitly states that Master Scout moderation does not provide:

- Basecamp/Admin access
- submission editing
- deletion
- Featured publishing
- user administration
- site settings

Master Scouts can review:

- new Place submissions
- structured Place updates

They can:

- approve
- request changes
- reject / mark not approved

For new Places, Master Scout approval calls the shared publication engine with publish status `active`.

Master Scouts do not choose `featured` publication status.

Admin and Master Scout approvals therefore share the core publication logic while retaining different authority at the interface layer.

## 12. Self-approval restrictions

`llama_master_moderation_assert_not_self()` explicitly rejects moderation when moderator user ID equals contributor user ID.

This check is applied:

- before rendering Master Scout review
- again before action processing
- again after locking the record inside the transaction

Master Scout queue/review logic therefore has deliberate defense-in-depth against self-review.

### Admin difference

The reviewed Admin moderation routes use `moderation_require_admin()`, CSRF protection, record locking, and audit logging.

No equivalent contributor-ID self-review rejection was found in the reviewed Admin moderation path.

Therefore:

- Master Scout self-approval is explicitly prohibited.
- Admin/Owner self-approval is not proven prohibited by the same mechanism.

This may be intentional because Admin/Owner can directly edit/publish Place data outside the community contribution workflow.

V3 must make the desired policy explicit rather than assuming both moderation surfaces currently behave the same.

## 13. Admin moderation authorization

`app/moderation.php::moderation_require_admin()` requires a logged-in user satisfying `user_has_role('admin')`.

Because Owner inherits Admin, Owner also passes this gate.

Admin moderation routes reviewed include:

- `admin/moderate-submission.php`
- `admin/moderate-update.php`
- related problem-report moderation

Admin moderation uses:

- CSRF tokens
- database transactions
- row locking
- moderation status checks
- review history
- Admin audit logging
- contributor notifications

Admin can publish new Places as Active or Featured where the Admin interface exposes that choice.

## 14. Owner-only functionality

Owner is a distinct role above Admin even though Owner inherits Admin.

Repository evidence confirms Owner-specific semantics in areas including membership grants. For example, complimentary membership grant/revoke services describe the actor as a granting/revoking Owner.

The complete repository-wide Owner-only surface was not exhaustively enumerated in this Scout Report-focused audit.

V3 must preserve Owner as a distinct role and must not flatten Owner into Admin merely because `user_has_role('admin')` returns true for Owner.

Owner-only tools should remain explicitly gated during future refactors.

## 15. Submission workflow

Normal new Place contribution flow is:

```text
verified contributor
  -> submit_place capability
  -> pending Place submission
  -> Admin or eligible Master Scout review
  -> approve / request changes / reject
  -> shared publication engine
  -> published Place + contribution provenance
```

Approval requires an active transaction and validates submission status before publication.

New Place approval records contributor provenance and contribution history.

Master Scout approval forces Active publication.

Admin may have broader publication controls.

## 16. Update workflow

Normal update flow is:

```text
verified contributor
  -> submit_update capability
  -> Complete Access to selected Place
  -> pending Place or scoped update
  -> Admin or eligible Master Scout review
  -> concurrency/original-value checks
  -> approve / request changes / reject
  -> canonical Place or scoped publication
  -> contribution provenance
```

A contributor cannot publish their own update directly through this workflow.

Partial updates preserve unrelated answers through the canonical update services documented in V3-003.

## 17. Moderation status protections

Both Admin and Master Scout moderation services validate that records remain in an appropriate review state before acting.

New Place and update approvals use row locking inside database transactions.

This reduces double-approval and stale-review risk.

Update approval also performs original/current value checks before publishing, as documented in V3-003.

## 18. CSRF and audit controls

Admin and Master Scout moderation actions use shared moderation CSRF verification.

Admin actions use Admin audit logging.

Master Scout moderation also writes audit events through the shared Admin audit infrastructure, with metadata identifying the moderation scope as Master Scout.

These audit events complement the submission/update review histories and contribution provenance.

## 19. Public/Community field authorization

V3-002 confirmed that the canonical field registry does not currently expose a complete Public/Community field classification.

Current content access is largely enforced around the report/Place experience rather than by a comprehensive per-field permission matrix.

This means the V3 requirement for explicit Public versus Community visibility cannot be considered implemented merely because membership gating exists.

That metadata and enforcement remain future implementation work.

## 20. Resource permissions

The reviewed report target and permission services are centered on Place, Area, and Site.

The V3 Resource model is not currently represented as a Scout Report scope/target in the audited authorization paths.

Resource contribution and moderation permissions therefore need to be designed when that model is implemented.

## 21. Confirmed inconsistencies and gaps

1. **First-Site earned access is missing.** Current Place-specific access rewards original/new-Place contribution only.
2. **Public/Community field-level classification is missing.** Access exists at broader content/workflow layers.
3. **Admin and Master Scout self-review rules differ.** Master Scout self-review is explicitly blocked; no equivalent Admin contributor-ID check was found.
4. **Contribution role and active Scout entitlement are separate.** A role-based Scout contribution label does not itself prove an active Scout profile.
5. **Resource authorization is not yet part of the current report target model.**
6. **Generic contribution capabilities are broad.** Fine-grained restrictions are supplied by surrounding workflows rather than the capability map itself.

## 22. Suspected risks and policy questions

- If a Scout role remains assigned after active Scout status expires, contribution labeling may continue to identify that user as Scout even when Scout Complete Access has ended.
- Future code could accidentally use `llama_contributor_can('submit_update')` alone and omit the Place Complete Access gate.
- Refactoring Owner/Admin checks could erase Owner-only boundaries because Owner satisfies Admin role checks.
- Adding first-Site earned access without durable contribution/source criteria could accidentally reward trivial or duplicate Site submissions.
- Per-field Public/Community access may drift if implemented independently in website and future API consumers.

These are risks or future policy questions, not proof of current production abuse.

## 23. Preservation requirements for V3

1. Preserve Owner as a role above Admin.
2. Preserve Owner -> Admin and Master Scout -> Scout inheritance.
3. Preserve Master Scout legacy slug compatibility until data migration is explicit.
4. Preserve stronger authentication requirements for Owner/Admin.
5. Preserve verified-email contribution gates.
6. Keep paid membership separate from broader Complete Access.
7. Preserve active Scout access independently from billing state.
8. Preserve Place-specific earned access for original/new-Place contributors.
9. Extend, rather than replace, earned access to support the approved first-new-Site rule.
10. Preserve contributor ownership and Place Complete Access checks on updates.
11. Preserve moderated publication for ordinary community contributions.
12. Preserve Master Scout anti-self-review protection.
13. Preserve Master Scout's narrower authority and Active-only new Place approval.
14. Preserve Admin CSRF, transaction, locking, audit, and publication controls.
15. Preserve role-at-submission and reviewer identity in historical records.
16. Make Public/Community visibility enforceable from shared backend services so future web/API/mobile clients cannot diverge.
17. Keep Resource permissions separate until the Resource model is explicitly defined.

## 24. Questions for later implementation

- Should Admin/Owner be prohibited from approving their own community submission, or is direct administrative publication the intended equivalent authority?
- What exact approved Site contribution qualifies a free member for Place-specific earned access?
- Should a substantial first report on an already-imported Site also earn Place access?
- When Scout status expires, should the retained role continue to determine public contribution labeling?
- Which fields are Public versus Community in the final canonical registry?
- How should Resource creation/moderation permissions map to ordinary member, Scout, Master Scout, Admin, and Owner?
- Should APIs receive a capability object from one shared authorization service rather than recreate these checks per endpoint?

No authorization behavior was changed in V3-005.

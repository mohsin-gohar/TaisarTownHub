# Phase 2.3 — Laravel Models and Relationships Review

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-1-file-inventory-20261128`  
**Reviewed snapshot:** `55b48709b29e073c4fb645145db4831d14a65868`  
**Review type:** Static model and schema/relationship review.

## Scope

Enumerated the complete current `backend/app/Models/` directory (47 tracked PHP model files), fetched and inspected every model, then cross-checked relationship keys, fillable/cast/hidden attributes, soft-delete behavior and relevant migration definitions. The schema cross-check included the model-bearing migrations for tenancy/RBAC, properties/location, document vault, CRM, finance, subscriptions, support, video engagement, maps and later lifecycle/index additions.

## Exact files reviewed (47/47)

| Status | Current path |
|---|---|
| READ | `backend/app/Models/Agency.php` |
| READ | `backend/app/Models/AgencyInvitation.php` |
| READ | `backend/app/Models/AgencyMember.php` |
| READ | `backend/app/Models/AgencyPromotion.php` |
| READ | `backend/app/Models/AgencySubscription.php` |
| READ | `backend/app/Models/AgencyVideo.php` |
| READ | `backend/app/Models/AuditLog.php` |
| READ | `backend/app/Models/Block.php` |
| READ | `backend/app/Models/BusinessRecord.php` |
| READ | `backend/app/Models/Commission.php` |
| READ | `backend/app/Models/Dealer.php` |
| READ | `backend/app/Models/DealerFile.php` |
| READ | `backend/app/Models/Document.php` |
| READ | `backend/app/Models/DocumentAccessLog.php` |
| READ | `backend/app/Models/DocumentFolder.php` |
| READ | `backend/app/Models/DocumentQuarantine.php` |
| READ | `backend/app/Models/DocumentShare.php` |
| READ | `backend/app/Models/DocumentVersion.php` |
| READ | `backend/app/Models/EmailVerificationToken.php` |
| READ | `backend/app/Models/Expense.php` |
| READ | `backend/app/Models/Inquiry.php` |
| READ | `backend/app/Models/LeadActivity.php` |
| READ | `backend/app/Models/LoginHistory.php` |
| READ | `backend/app/Models/Map.php` |
| READ | `backend/app/Models/MediaFile.php` |
| READ | `backend/app/Models/PaymentSchedule.php` |
| READ | `backend/app/Models/Permission.php` |
| READ | `backend/app/Models/Project.php` |
| READ | `backend/app/Models/PromotionPackage.php` |
| READ | `backend/app/Models/Property.php` |
| READ | `backend/app/Models/PropertyCategory.php` |
| READ | `backend/app/Models/PropertyFavorite.php` |
| READ | `backend/app/Models/PropertyStatusHistory.php` |
| READ | `backend/app/Models/PropertyView.php` |
| READ | `backend/app/Models/Role.php` |
| READ | `backend/app/Models/SavedSearch.php` |
| READ | `backend/app/Models/Sector.php` |
| READ | `backend/app/Models/SubscriptionInvoice.php` |
| READ | `backend/app/Models/SubscriptionPlan.php` |
| READ | `backend/app/Models/SupportMessage.php` |
| READ | `backend/app/Models/SupportTicket.php` |
| READ | `backend/app/Models/SystemSetting.php` |
| READ | `backend/app/Models/Transaction.php` |
| READ | `backend/app/Models/TransactionPayment.php` |
| READ | `backend/app/Models/User.php` |
| READ | `backend/app/Models/VideoComment.php` |
| READ | `backend/app/Models/VideoLike.php` |

## Findings

### P1 — Soft-deleted RBAC roles and location records are not excluded by their models

**Files:** `Role.php`, `Sector.php`, `Block.php`  
**Schema:** RBAC migration `2026_11_10_000002_create_rbac_tables.php`; location migration `2026_11_10_000005_create_location_and_category_tables.php`.

The migrations add `deleted_at` to `roles`, `sectors` and `blocks`, but these three Eloquent models do not use Laravel's `SoftDeletes` trait. As a result, normal model queries and relationship loads do not automatically exclude rows marked deleted. In particular, a soft-deleted role can still be returned by `Role` queries and a soft-deleted sector/block can still appear in relation lookups.

**Repair:** Add `SoftDeletes` to all three models, confirm all deletion/restore flows intentionally use soft-delete semantics, and add tests proving normal queries/relationships exclude trashed rows while `withTrashed()` can retrieve them where needed. Verify migration and production data before rollout.

### P1 — Tenant consistency is not enforced across several independently valid foreign keys

**Files:** `Document.php`, `DocumentFolder.php`, `DocumentShare.php`, `TransactionPayment.php`, `PaymentSchedule.php`, `Commission.php`, `LeadActivity.php`, `MediaFile.php` and related parent models.  
**Schema:** document vault, CRM and finance migrations.

The schema often has separate foreign keys for both `agency_id` and a parent record ID, but does not enforce that both references belong to the same agency. Examples include a document linked to a folder/property from another agency, a share whose `agency_id` differs from its document's agency, or a payment/schedule/commission whose agency differs from its transaction's agency. Ordinary foreign keys validate existence, not tenant equality.

**Risk:** A future controller/service path that forgets one tenant predicate can create cross-agency references and expose or misattribute data. Model relationships themselves do not enforce tenant consistency.

**Repair:** Add explicit domain-level same-agency validation inside transactional write services and tests for mismatched parent/child agency IDs. Consider composite unique keys and composite foreign keys where compatible with the existing MySQL schema and migration history. Do not add constraints until existing production data is checked for violations.

### P2 — Video engagement relations lack database referential constraints

**Files:** `AgencyVideo.php`, `VideoComment.php`, `VideoLike.php`.  
**Schema:** `2026_11_02_145313_create_agency_videos_table.php`, `2026_11_02_145315_create_video_comments_table.php`, `2026_11_10_000019_add_video_engagement_attribution.php`.

The Eloquent models define video/comment/like relations, and the like table has a unique `(agency_video_id, user_id)` index, but the migrations do not add foreign keys from video comments/likes to agency videos or from the engagement user IDs to users. Deleting a user/video can therefore leave orphaned engagement rows.

**Repair:** Check for existing orphan records, then add safe foreign keys with deliberate delete behavior (cascade or null where appropriate). Add `VideoComment::user()` and any needed inverse relationships if account attribution is used by application features.

### P2 — Several relationship-bearing models omit useful inverse relations

Examples:
- `DocumentShare` has `agency_id` but no `agency()` relation.
- `DocumentQuarantine` has `document_id` but no `document()` relation.
- `DocumentAccessLog` has `agency_id` but no `agency()` relation.
- `PaymentSchedule` has `agency_id` but no `agency()` relation.
- `PropertyView` has `user_id` but no `user()` relation.
- `VideoComment` stores `user_id` but has no `user()` relation.

**Risk:** Not automatically a security vulnerability, but it encourages repeated manual joins and makes relationship-based eager loading, audit trails and ownership checks inconsistent.

**Repair:** Add the missing relations where used by the domain, with explicit foreign/local keys and corresponding relation tests. Do not add unused relationships solely for symmetry.

### P2 — Role membership relationship does not itself distinguish agency-scoped from platform roles

**Files:** `Role.php`, `AgencyMember.php`.  
**Schema:** RBAC migrations.

`AgencyMember::role()` resolves a role by ID and `Role::members()` returns all memberships for that role. The database foreign key ensures the role exists but does not ensure that an agency membership is assigned only an agency-scoped role (or a global system role) and never a platform-scoped role. The permission service is expected to enforce this policy, but the relation and schema alone do not.

**Repair:** Keep role-scope validation in the authoritative role-assignment service and add negative feature tests for assigning platform roles to agency memberships, including custom roles belonging to a different agency.

### P2 — Project model fillable list omits a persisted schema field

**File:** `Project.php`; schema: `2026_11_02_145319_create_projects_table.php`.

The projects table contains a `subtitle` column, but the model's `$fillable` list omits `subtitle`. Current `ProjectController` only exposes read endpoints, so no active API write failure was confirmed in this task; any future `Project::create()` or `fill()` path will silently discard the field under normal mass-assignment behavior.

**Repair:** If subtitle is part of the supported project domain, add it to `$fillable` and cover persistence with a model/feature test. Otherwise document it as legacy/unused and plan a separate schema cleanup.

### P3 — AgencyPromotion::isPaid() is hard-coded false despite payment state fields

**File:** `AgencyPromotion.php`.

The model contains `payment_status` with `waived|pending|paid|refunded` states and a snapshotted price, but `isPaid()` always returns false. The model comment says this is deliberate for the free-launch state. This is not necessarily a current defect, but the helper becomes incorrect if paid placements are enabled without changing the method and its callers.

**Repair:** Either rename/document it as a free-launch policy helper or implement the actual `payment_status === 'paid'` semantics when paid placements are activated. Add tests before switching billing on.

### P3 — Monetary attributes are inconsistently cast

Examples include `Dealer` commission totals/rates, `Transaction` agreement/payment totals, and other financial fields whose schema uses integer/bigint/decimal columns but whose models do not declare explicit casts. Other finance models already cast money to integer/float/decimal strings.

**Risk:** PHP/database driver type differences can lead to inconsistent JSON serialization and arithmetic precision. Financial calculations should not depend on implicit casts or binary floating-point for currency.

**Repair:** Standardize money handling (prefer integer minor units or decimal-safe arithmetic/value objects), add casts where safe, and test serialization/rounding. Do not bulk-change casts without checking existing controller arithmetic and API compatibility.

## Positive controls observed

- Sensitive user fields, dealer CNIC, property internal financial fields, document disk paths and share token hashes are hidden from default model serialization.
- Dealer CNIC and user MFA secrets/recovery material use encrypted casts.
- Document models hide physical storage paths and checksums; private document relationships are explicit.
- The schema has useful unique constraints for agency membership, role permissions, document versions, saved-search ownership/indexes, payment schedule installment numbers, invoice numbers, and per-user video likes.
- Soft-delete traits are correctly present on several lifecycle models, including Agency, Dealer, Property, Transaction, Document, DocumentFolder, MediaFile, Project, AgencyVideo, VideoComment and TransactionPayment.

## Verification and limitations

- Model source review: **47/47 current model files inspected**.
- Relevant migration definitions were cross-checked for relationship keys, foreign keys, uniqueness, soft deletes and indexes.
- No model or migration source files were changed in this task.
- No database was booted; migration execution, schema introspection and orphan/tenant-mismatch data scans were not run.
- PHPUnit/model relationship tests, static analysis and performance profiling were not run.
- CI: intentionally not triggered.
- Findings are static-review findings; data-dependent and runtime claims require focused tests and a production-safe data audit before applying schema changes.

## Recommended repair order

1. Correct missing SoftDeletes traits for Role, Sector and Block; add regression tests.
2. Audit existing cross-tenant parent/child references before adding same-agency enforcement.
3. Add video engagement foreign keys after orphan checks.
4. Add missing relationships where domain usage requires them.
5. Enforce role scope and agency ownership in the role assignment service.
6. Decide Project.subtitle lifecycle and normalize monetary representation.
7. Correct or explicitly deprecate the AgencyPromotion::isPaid() helper before enabling paid promotions.

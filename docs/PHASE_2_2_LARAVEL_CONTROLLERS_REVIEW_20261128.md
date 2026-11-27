# Phase 2.2 — Laravel Controllers Review

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-1-file-inventory-20261128`  
**Reviewed snapshot:** `1504217879532fdfa7985cb41373e92b3c527f2d`  
**Review type:** Static source review; no source changes in this task.

## Scope and method

All **33 tracked files** under `backend/app/Http/Controllers/` were enumerated from the recursive Git tree and their current-branch blob contents were fetched for review. The review covered controller responsibilities and method inventories, route-facing authorization gates, tenant scoping, request validation, data projection/serialization, mutation and transaction boundaries, file handling, payment/refund workflows, audit logging, and public endpoints.

The 33 files include 31 API endpoint controllers plus the shared API authorization trait and base controller. The shared `AuthorizesApi.php` and `Controller.php` were also reviewed in Phase 2.1 and cross-checked in this task.

## Exact files reviewed

| Status | Current path |
|---|---|
| READ | `backend/app/Http/Controllers/Api/AdminAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminDashboardController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminSystemController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminUserController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyStaffController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyVideoController.php` |
| READ | `backend/app/Http/Controllers/Api/AuthController.php` |
| READ | `backend/app/Http/Controllers/Api/AuthorizesApi.php` |
| READ | `backend/app/Http/Controllers/Api/DealerController.php` |
| READ | `backend/app/Http/Controllers/Api/DealerFilesController.php` |
| READ | `backend/app/Http/Controllers/Api/DocumentController.php` |
| READ | `backend/app/Http/Controllers/Api/FavoriteController.php` |
| READ | `backend/app/Http/Controllers/Api/FeaturedAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/FinanceController.php` |
| READ | `backend/app/Http/Controllers/Api/HealthController.php` |
| READ | `backend/app/Http/Controllers/Api/InquiryController.php` |
| READ | `backend/app/Http/Controllers/Api/LeadController.php` |
| READ | `backend/app/Http/Controllers/Api/LocationController.php` |
| READ | `backend/app/Http/Controllers/Api/MapController.php` |
| READ | `backend/app/Http/Controllers/Api/MediaController.php` |
| READ | `backend/app/Http/Controllers/Api/NotificationController.php` |
| READ | `backend/app/Http/Controllers/Api/PaymentController.php` |
| READ | `backend/app/Http/Controllers/Api/ProjectController.php` |
| READ | `backend/app/Http/Controllers/Api/PropertyController.php` |
| READ | `backend/app/Http/Controllers/Api/SavedSearchController.php` |
| READ | `backend/app/Http/Controllers/Api/SubscriptionController.php` |
| READ | `backend/app/Http/Controllers/Api/SupportController.php` |
| READ | `backend/app/Http/Controllers/Api/TransactionController.php` |
| READ | `backend/app/Http/Controllers/Api/WorkspaceController.php` |
| READ | `backend/app/Http/Controllers/Controller.php` |

**Coverage:** 33/33 controller-directory files reviewed. This is static review evidence, not a claim that the controllers have passed runtime or integration tests.

## Findings

### P1 — Private property financial fields are not consistently protected by a finance permission

**File:** `backend/app/Http/Controllers/Api/PropertyController.php`

- `store()` accepts `purchasePricePKR`, `sellingPricePKR`, and `internalNotes` at lines 298–300.
- `update()` also accepts these fields (the corresponding validation block is in the update method).
- These writes are guarded by property create/update permissions and ownership checks, but the controller does not add a separate `finance.view` / sensitive-finance permission check for these fields.
- More critically, `setMarketplaceVisibility()` (lines 681–733) accepts property update/publish permissions, saves the visibility, then unconditionally calls `makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes'])` at line 727 and returns the model at lines 729–733.

**Risk:** A user who can update/publish a property but lacks the sensitive finance/moderation grant may receive private purchase/selling prices, internal notes, and staged moderation changes in the response. The write endpoints may also let a non-finance user alter private economics.

**Required repair:** Gate sensitive field writes and response projection independently. Return a safe property DTO from visibility changes; include financial fields only for a caller with the owning agency's explicit finance/sensitive-data permission, and include `pending_changes` only for an authorized moderator. Add role-matrix feature tests for each permission combination.

### P1 — Refund payments can exceed the amount actually collected

**File:** `backend/app/Http/Controllers/Api/FinanceController.php`, `storePayment()`, lines 658–759.

- Refunds are converted to negative ledger amounts at lines 698–703.
- The guard at lines 705–715 only rejects when collected plus the new amount exceeds the agreement value.
- There is no corresponding check that the absolute refund is less than or equal to the amount previously collected.
- The request validation has an upper bound but no lower bound for refund amounts (lines 669–679).

**Risk:** A caller with payment-management permission can record a refund larger than the collected balance, driving the payment ledger negative and corrupting derived balance/status reporting.

**Required repair:** Under the existing transaction and locked transaction row, reject refunds whose absolute value exceeds the refundable collected balance; define treatment of prior refunds and deleted payments consistently. Add tests for exact-balance, partial, excessive, repeated, concurrent and negative-input refunds.

### P1 — Media sale mutation is not protected against concurrent requests

**File:** `backend/app/Http/Controllers/Api/MediaController.php`, `sell()`, lines 306–347.

- The media row is loaded and its `sold` status checked before mutation.
- The method then writes sale amount, cost, profit, buyer details and sold status without a database transaction or row lock.

**Risk:** Two concurrent requests can both pass the initial status check and overwrite the sale attribution/details. This is inconsistent with the row-locking protection already used in `DealerFilesController::sell()`.

**Required repair:** Perform the scoped row lookup, status check and sale mutation inside a transaction using `lockForUpdate()`; return a conflict for an already-sold row. Add a concurrent duplicate-sale feature test.

### P2 — MFA challenge consumption is a non-atomic read/verify/delete sequence

**File:** `backend/app/Http/Controllers/Api/AuthController.php`, `mfaVerify()`, lines 546–627; especially 554, 573–598.

The challenge is read using `Cache::get()`, verified, and removed later using `Cache::forget()`. Concurrent requests can both read the same live challenge before either removes it, and both may issue a Sanctum token.

**Risk:** The implementation's single-use challenge guarantee is not atomic under concurrent requests.

**Required repair:** Claim/consume the challenge atomically (for example, an atomic cache lock or one-time state transition), and ensure attempt increments cannot be lost under concurrency. Test parallel valid-code submissions and recovery-code reuse.

### P2 — Subscription checkout accepts caller-controlled redirect destinations

**File:** `backend/app/Http/Controllers/Api/PaymentController.php`, `subscriptionCheckout()`, lines 34–43 and 87–89.

`success_url` and `cancel_url` are only validated as syntactically valid URLs, then passed to Stripe as redirect destinations.

**Risk:** An authenticated agency user can direct a checkout completion/cancellation flow to an unrelated external host. This can be abused for phishing or confusing checkout handoffs.

**Required repair:** Generate both URLs server-side from the configured frontend origin, or validate their scheme/host against a strict allowlist. Do not trust arbitrary client-supplied hosts.

### P2 — Agency last-owner invariant is vulnerable to concurrent membership changes

**File:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`, `updateMember()` and `removeMember()`, lines 305–390.

The controller checks `activeOwnerCount()` before demoting/deactivating/removing an owner, but the count and mutation are not performed under a transaction/lock.

**Risk:** Two concurrent owner-demotion/deactivation requests can each observe more than one active owner and both proceed, potentially leaving the agency without an active owner.

**Required repair:** Lock the agency row (or use an equivalent serialized owner-membership mutation boundary), re-count active owners inside the transaction, and then mutate. Add a parallel last-owner test.

### P2 — Bulk map upload has no explicit image-count cap or all-or-nothing cleanup

**File:** `backend/app/Http/Controllers/Api/MapController.php`, `bulk()`, lines 156–220.

The `images` array has a minimum but no maximum, while metadata arrays have maxima of 50. Each file is stored and each database row is created sequentially without an enclosing transaction or rollback cleanup.

**Risk:** Large requests can consume excessive resources; a later storage/database failure can leave a partially created batch and orphaned public files.

**Required repair:** Set a deliberate maximum image count and align metadata array validation; implement batch-safe storage/database handling with cleanup of already-written files on failure.

### P2 — Public legacy property projection exposes an internal dealer identifier

**File:** `backend/app/Http/Controllers/Api/WorkspaceController.php`, `PUBLIC_PROPERTY_KEYS`, line 81.

The public catalog allowlist includes `assignedDealerId`, while the newer `PropertyController::publicPropertyPayload()` does not expose that field.

**Risk:** The legacy public catalog leaks an internal dealer identifier and creates inconsistent privacy behavior between the legacy and relational public APIs.

**Required repair:** Remove `assignedDealerId` from the public allowlist unless there is a documented public requirement; add a public-catalog regression test.

### P2 — Admin invoice verification has no locked state transition

**File:** `backend/app/Http/Controllers/Api/AdminAgencyController.php`, `invoiceVerify()`, lines 380–420.

The invoice is read and checked for `paid`/`void` before a direct update, without a transaction or row lock.

**Risk:** Concurrent verification requests can both pass the pending-state check and overwrite payment method/reference/verifier metadata.

**Required repair:** Lock and re-check the invoice status inside a transaction; make the transition from pending to paid conditional and idempotent.

### P2 — File and database writes can become inconsistent on persistence failure

**Files:** `DocumentController.php` (`store()`, `storeVersion()`), `MediaController.php` (`store()`), and `MapController.php` (`store()`, `bulk()`).

Several upload flows write bytes to storage before the database row/version is fully persisted. Not all paths provide compensating cleanup if a later database write fails.

**Risk:** Orphaned private/public files, stale metadata, or partial multi-file operations can accumulate after exceptional failures.

**Required repair:** Use explicit staged storage + database transaction/compensation patterns and add failure-injection tests. Preserve the existing malware-scan-before-publication/private-delivery controls.

### P3 — Several pagination inputs are upper-bounded but not lower-bounded

**Files include:** `AdminAgencyController.php`, `AdminAgencyOversightController.php`, `AdminUserController.php`, `DocumentController.php`, `LeadController.php`, `MediaController.php`, `NotificationController.php`, `PropertyController.php`, and `FinanceController.php`.

Some endpoints use `min((int) request per_page, max)` without clamping the lower bound to 1. Other controllers already use `min(max(..., 1), max)`.

**Risk:** Zero or negative page sizes can cause invalid pagination behavior and inconsistent API errors.

**Required repair:** Centralize bounded pagination validation or consistently clamp to `1..max`; test zero, negative, non-numeric and oversized values.

### P3 — Controller responsibilities and legacy compatibility paths are uneven

**Files:** `FinanceController.php`, `DocumentController.php`, `WorkspaceController.php`, `AuthController.php`.

These controllers combine multiple business domains and compatibility paths (for example, finance transaction lifecycle, payments, commissions, expenses and reports in one controller; document storage, versioning, quarantine and public shares in another).

**Risk:** High change surface and duplicated policy/transaction logic make regressions harder to detect.

**Required repair:** After characterization tests, extract focused services/actions and response resources. Do not refactor before fixing the P1 data/ledger issues.

## Positive controls observed

- Most agency business endpoints resolve agency context server-side and scope records by agency ID.
- Document vault operations consistently require agency context and document permissions; private bytes are delivered through `SecureFileStore`, with malware scan status checked before delivery.
- Public property, dealer and workspace catalog projections generally use explicit allowlists rather than serializing unrestricted models.
- Dealer file sales and transaction payment inserts already demonstrate row-locking patterns that can be reused in the media/refund repairs.
- Invitation acceptance uses a locked re-read of the invitation row; workspace replace mode has explicit destructive-operation interlocks.
- Admin and agency mutation paths commonly emit audit records; MFA, password reset and session-revocation workflows have substantial security controls.

## Verification and limitations

- Static source review: **33/33 controller-directory files inspected**.
- Route registration and permission matrix were cross-referenced against the Phase 2.1 route/RBAC review.
- No controller source files were changed in this review task.
- PHPUnit / feature tests: **not run**.
- Runtime HTTP, concurrency, database and storage failure-injection tests: **not run**.
- PHPStan/Pint/static-analysis tools: **not run**.
- CI: **not triggered**, per the existing instruction.
- Findings are static-review findings; concurrency and authorization exploitability should be confirmed with focused tests before marking repaired.

## Recommended repair order

1. Fix PropertyController private-field authorization and response projection (P1).
2. Add refundable-balance enforcement to FinanceController (P1).
3. Serialize MediaController sale transition with a row lock (P1).
4. Make MFA challenge consumption atomic (P2).
5. Restrict Stripe return URLs and lock invoice/owner state transitions (P2).
6. Fix bulk map batch limits/cleanup, file-storage compensation and public projection leakage.
7. Normalize pagination bounds and then plan controller/service decomposition.

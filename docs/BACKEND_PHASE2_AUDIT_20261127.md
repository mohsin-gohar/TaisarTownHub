# Phase 2 — Backend Architecture and Code Audit (Initial Findings)

**Date:** 2026-11-27  
**Audited baseline:** `main` at `4baf396fb3074bac134dac9c8bdc40ba2da67bf0`  
**Working branch:** `audit/phase-2-backend-20261127`  
**Scope completed in this pass:** API route inventory and handler existence check; targeted review of auth/MFA, agency context/RBAC, property serialization, workspace sync, agency invitations, finance/dealer files, document/media vault, malware scanner, scheduled commands, and production preflight. This is an initial risk-ranked pass, not a complete review of every backend file.

## 1. Route-to-handler inventory
- `backend/routes/api_definitions.php` contains **203 endpoint declarations**.
- The shared route definitions are registered under both `/api` and `/api/v1`.
- **168** declarations are inside the authenticated Sanctum + `EnsureMfaVerified` group; **35** are guest/public/webhook routes.
- **31 controller classes** are referenced.
- Every referenced controller file was fetched and every routed method name was checked against its PHP method declarations: **0 missing handler methods**.
- Full route matrix is recorded in PR #32's `docs/BACKEND_ROUTE_INVENTORY_20261127.md`; path ledger reconciliation is in PR #32's `docs/LEDGER_PATH_RECONCILIATION_20261127.md`.
- This is static route/handler verification only. It does not prove that all actions enforce the correct object-level permission or behave correctly at runtime.

## 2. Security architecture reviewed
- Global middleware in `backend/bootstrap/app.php`: correlation ID, trusted-proxy resolution, security headers, and API agency context.
- Authenticated routes add Sanctum token auth and `EnsureMfaVerified`.
- `AgencyContext` derives agency scope from the authenticated user's current memberships and workspace approval state; it does not trust a request-body agency ID.
- `AuthorizesApi` resolves permissions against the active agency and distinguishes explicitly scoped platform cross-agency access.
- `User` stores MFA material encrypted and hides it from serialization; MFA verification stamps are bound to the exact Sanctum token.
- `PropertyController` separates public sale-only projections from agency/moderator views; sensitive financial/internal fields are hidden or permission-gated.
- `DocumentController` scopes vault reads by agency and permission; `SecureFileStore` streams private bytes outside the web root.
- Existing focused tests cover tenant matrices, MFA token scope, dealer-file authorization, finance isolation, malware upload decisions, and vault security.

## 3. Confirmed findings and fixes on this branch

### P1 — Quarantine purge discarded database metadata after physical deletion failed
**Affected:** `backend/app/Console/Commands/PurgeQuarantine.php`  
**Original behavior:** the command caught storage exceptions but still deleted the `DocumentQuarantine` row; it also ignored a `false` return from `Storage::delete()`. For quarantined vault documents it could continue to audit and `forceDelete()` the document after a failed physical delete. That can leave sensitive bytes orphaned on disk without a database row or retry candidate.

**Fix applied:**
- Check the boolean result of every physical delete.
- On exception or a false delete result, retain the related database row and continue to the next item.
- For quarantined documents, retain the document and version metadata if any physical delete fails.
- Return a failing command exit code when any cleanup item fails so scheduler/operations can detect it.

**Regression tests added to** `backend/tests/Feature/FileVaultSecurityTest.php`:
- Failed quarantine-file deletion retains the quarantine row.
- Failed quarantined-document deletion retains the document row.
- Failed version deletion retains document and version metadata.

**Status:** code and regression tests committed; latest MySQL 8 backend suite passed with 435 tests and 3,789 assertions.

### P1 — Production preflight did not enforce malware-scan configuration
**Affected:** `backend/app/Console/Commands/ProductionCheck.php`, `backend/config/malware.php`, `backend/tests/Feature/ProductionReadinessTest.php`.

- Runtime defaults for `malware.enabled` and `malware.fail_closed` are both false.
- `.env.example` correctly documents production values as enabled + fail-closed.
- The production preflight did not inspect either setting, and its test did not require either check. A production environment missing one of those environment variables could pass this part of preflight while uploads were accepted without a real malware verdict.

**Fix applied:** production preflight now fails if malware scanning is disabled or fail-closed mode is off; non-production environments are not blocked. Regression coverage asserts both checks are present and that a production configuration with both disabled fails those checks.

**Important limitation:** this validates configuration, not live ClamAV daemon reachability. A real staging/production scanner smoke test is still required.

**Status:** code and regression test committed on the Phase 2 branch; CI result pending.

### P1 — Flutterwave webhook accepted incomplete or rounded payment values
**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- The webhook previously treated amount and currency as optional: if either was absent, it skipped that comparison and could mark a pending invoice paid.
- Both webhook and authenticated verify flows compared integer-rounded amounts, allowing a slightly underpaid value (for example PKR 4,999.60) to round to PKR 5,000.
- The authenticated verification path also defaulted a missing amount to zero instead of requiring a numeric value.

**Fix applied:** require numeric amount and non-empty currency on the webhook; require numeric amount and non-empty currency from the provider verification response; compare amounts with a small floating-point tolerance rather than rounding to whole integers.

**Regression tests added:** reject webhook payloads missing amount or currency; reject a rounded underpayment; reject provider verification responses missing the amount. Existing successful and mismatched-amount tests remain.

**Status:** code and regression tests committed; latest MySQL 8 backend suite passed with 435 tests and 3,789 assertions.

### P1 — Stripe Checkout webhook activated sessions without proving settlement or invoice match
**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- The prior handler accepted a missing `payment_status`, missing `amount_total`, or missing `currency`; the existing success test omitted all three and still expected the invoice to become paid.
- It only rejected underpayment for PKR, using integer rounding, and did not compare the event's agency/plan metadata with the invoice.
- A signed `checkout.session.completed` event alone is not proof that an asynchronous payment has settled.

**Fix applied:** require `payment_status` to be `paid` or `no_payment_required`; require exact currency and numeric amount to match the invoice (minor-unit amount, without rounding); verify the session's agency and plan metadata match the invoice before activation.

**Regression tests added:** successful webhook fixture now includes settled payment fields; unpaid and missing-amount sessions remain pending and do not activate a subscription.

**Status:** code and regression tests committed; latest MySQL 8 backend suite passed with 435 tests and 3,789 assertions.

### P1 — MFA challenge and recovery-code consumption were not concurrency-safe
**Affected:** `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Support/MfaService.php`, `backend/tests/Feature/MfaEnforcementTest.php`.

- The MFA endpoint previously read a challenge with `Cache::get()` and only deleted it after successful verification. Parallel requests could both read the same challenge before either deleted it and mint multiple tokens.
- Recovery-code consumption loaded and saved the hashed code list without a database row lock. Two distinct MFA challenges could concurrently verify the same one-time recovery code.

**Fix applied:** serialize each challenge verification with a cache lock and consume recovery codes inside a database transaction with `lockForUpdate()` on the user row.

**Regression tests added:** a successful challenge cannot be reused; a recovery code consumed through one challenge is rejected on a second challenge.

**Status:** code and regression tests committed; latest MySQL 8 backend suite passed with 435 tests and 3,789 assertions.

### P1 — Stripe invoice settlement and subscription activation were not atomic
**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- The previous Stripe handler marked an invoice paid before creating/updating the agency subscription, with no surrounding database transaction.
- If subscription activation failed after the invoice update, Stripe's retry would see the invoice as already paid and return early, leaving a paid invoice without an activated subscription.
- Parallel/replayed events also needed a row lock and an in-transaction status re-check.

**Fix applied:** lock and re-check the invoice inside a database transaction; update the paid state and activate the subscription in that same transaction. A failure rolls both changes back.

**Regression coverage:** the Stripe success test now replays the same signed event and verifies only one subscription row exists.

**Status:** code and regression test committed on the Phase 2 branch; CI result pending.

### P1 — Support ticket ownership bypassed an agency tenant boundary
**Affected:** `backend/app/Http/Controllers/Api/SupportController.php`, `backend/tests/Feature/SupportTicketsTest.php`.

- The visibility check accepted either same-agency context OR creator ownership.
- A creator whose active agency context changed could therefore still read a ticket attached to a different agency.
- Fix: agency-linked tickets now require the exact active agency context; unlinked platform tickets remain private to their creator; platform support staff retain their explicit global access.
- Regression test: a creator cannot use ownership to read a ticket attached to another agency.

**Status:** fixed; covered by the passing MySQL 8 backend suite.

### P1 — Support ticket assignment accepted non-staff accounts
**Affected:** `SupportController.php`, `SupportTicketsTest.php`.

- The assignable-staff directory listed only active platform staff, but the assignment mutation previously validated only that the target user existed.
- Fix: assignment now independently enforces an active platform staff role, matching the directory contract.
- Regression test: assigning an agency member returns 422; an active platform operator remains assignable.

**Status:** fixed; covered by the passing MySQL 8 backend suite.

### P1 — General report permission exposed the platform-wide audit log
**Affected:** `backend/app/Http/Controllers/Api/AdminSystemController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- With no active agency context, the endpoint accepted broad legacy `view_reports` / `admin.view_reports` permissions and returned unscoped audit records.
- Fix: agency users retain only their active agency's audit trail; unscoped platform access now requires the dedicated `admin.view_audit_logs` permission.
- Regression test: a legacy platform operator with general report access is denied global audit-log access.

**Status:** fixed; covered by the passing MySQL 8 backend suite.

### P2 — Dashboard collection totals included soft-deleted payments
**Affected:** `backend/app/Http/Controllers/Api/AdminDashboardController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- The dashboard's main collection aggregates used the query builder without filtering `deleted_at`, while its time series and report endpoint already excluded deleted receipts.
- Fix: dashboard collection totals now exclude soft-deleted payment rows consistently.
- Regression test: a soft-deleted receipt does not contribute to platform collection totals.

**Status:** fixed; covered by the passing MySQL 8 backend suite.

### P1 — Manual subscription invoice verification was not concurrency-safe
**Affected:** `backend/app/Http/Controllers/Api/AdminAgencyController.php`, `backend/tests/Feature/SubscriptionInvoicesTest.php`.

- The endpoint checked the invoice status before writing it, without a transaction or row lock. Two concurrent staff requests could both read pending and overwrite payment metadata after one request had already paid the invoice.
- Fix: verification now locks and re-checks the invoice inside a database transaction; audit logging occurs after the transaction commits.
- Existing regression coverage confirms paid and void invoices cannot be verified again. True simultaneous-request stress coverage remains a separate concurrency-test item.

**Status:** fixed; MySQL 8 suite passes. Concurrency behavior is protected by the row lock but still merits a dedicated parallel-request stress test.

## 4. Open review risks — not yet classified as defects
1. **Best-effort audit persistence:** `AuthorizesApi::writeAudit()` logs a warning and continues if `AuditLog::create()` fails. Confirm which actions require durable audit records and whether those operations need a transactional outbox/fail-closed policy.
2. **Legacy workspace sync atomicity:** `WorkspaceController::save()` processes collections sequentially and writes rows one at a time. Tenant ownership and destructive replace guards exist, but a mid-request database failure can leave a partially applied multi-collection sync. Confirm the frontend contract and whether per-collection or whole-request atomicity is required.
3. **Scheduled purge overlap:** the quarantine purge schedule is daily but does not declare `withoutOverlapping()`; assess whether production will ever run multiple scheduler instances and whether a distributed lock is needed.
4. **Route middleware traceability:** the 203 route actions exist, but each must still be traced through its handler's permission gates, validators, service calls, and negative authorization tests. Static method existence is not a security review.
5. **API v1 contract:** both prefixes share identical definitions. Verify the OpenAPI document covers the same contract and that versioned routes are intentionally not assigned distinct route names.

### P1 — Quarantine storage could be orphaned when metadata persistence failed

**Affected:** `backend/app/Http/Controllers/Api/DocumentController.php`, `backend/app/Http/Controllers/Api/MediaController.php`, `backend/tests/Feature/MalwareUploadEnforcementTest.php`, `backend/tests/Feature/StorageSecurityTest.php`.

- The rejected-upload path wrote encrypted bytes to the quarantine disk and then inserted the `DocumentQuarantine` row inside one broad `try` block.
- If the database insert failed, the catch only logged the exception. The bytes remained on disk without a row for the retention purge to find.
- Fix: after a failure, check whether the quarantine metadata row exists. If it does not, attempt to delete the just-written bytes and log a critical reconciliation event if deletion fails. If the database cannot establish whether the row committed, preserve the bytes and log the exact path for manual recovery rather than risk deleting a file with valid metadata.
- Regression tests simulate metadata-create failures for both vault-document and media uploads and assert no quarantine bytes or metadata remain.

**Status:** both vault and media fixes plus regression tests committed; CI rerun in progress on the latest branch head.

### P2 — Quarantine purge scheduler could overlap

**Affected:** `backend/routes/console.php`.

- The daily purge command had no scheduler mutex. Multiple scheduler instances could run the same purge concurrently.
- Fix: added Laravel's `withoutOverlapping()` scheduler lock to the daily quarantine purge event.
- This complements the command's per-row retry-safe physical deletion checks. Multi-instance cache-lock behavior still depends on production using a shared cache backend.

**Status:** scheduler guard committed; CI rerun in progress.

### Reclassified review notes

- **Workspace sync:** the product's validation contract explicitly permits partial saves and missing collections are intentionally skipped. Therefore sequential collection processing is not, by itself, a defect. A database exception mid-sync can still leave earlier collections committed; retain this as a documented partial-failure/operational consideration rather than imposing all-or-nothing semantics without a product contract change.
- **Audit persistence:** best-effort audit writes are explicitly documented in the existing error-handling contract. This pass has not yet established which individual mutation endpoints require fail-closed or transactional audit guarantees; keep as an open governance/security decision, not a confirmed defect.
- **Scheduler lock:** the purge overlap gap is now addressed in code; production correctness still requires a shared cache lock store if more than one scheduler replica is deployed.

### P2 — Stale invitation acceptance could leave an orphan user account

**Affected:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- The handler previously created a new user before its transaction re-read and locked the invitation. If the invitation was revoked/consumed between the initial lookup and the claim, membership creation returned 422 but the newly created user remained in the database without a membership.
- Fix: perform the locked invitation claim first, then create the invited account and membership inside the same transaction. Expired, already-used, and email-conflict outcomes return without creating an account.
- Regression test simulates an invitation being revoked after the initial read and verifies that no user account is left behind.

**Status:** code and regression test committed; latest CI/Docker rerun pending.

### P1 — Concurrent staff mutations could remove every active agency owner

**Affected:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- The last-owner checks previously counted active owners and updated membership outside a transaction. Two concurrent requests could both observe two active owners and then demote/deactivate a different owner, leaving the agency with none.
- Fix: serialize role and status changes by locking the agency row and target membership inside a transaction before rechecking the active-owner count. Both update and remove flows now share the same per-agency serialization boundary.
- Regression coverage verifies the final active owner cannot be demoted, deactivated, or removed. The DB row-lock path is implemented; a true parallel-request stress test remains a follow-up.

**Status:** code and regression test committed; latest CI/Docker rerun pending.

## 5. Evidence read in this pass
- `backend/routes/api.php`, `backend/routes/api_definitions.php`
- `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`
- `AuthController`, `PropertyController`, `WorkspaceController`, `AgencyStaffController`, `TransactionController`, `FinanceController`, `DealerFilesController`, `DocumentController`, `MediaController`, `PaymentController`, `SupportController`, `AdminSystemController`, `AdminDashboardController`, `AdminAgencyController`
- `AgencyContext`, `EnsureMfaVerified`, `AuthorizesApi`
- `User`, `Agency`, `Property`, `Transaction`
- `ClamAVScanner`, `SecureFileStore`, `FileVaultCrypto`, `UploadSecurity`, `LegacyTenancyResolver`
- `routes/console.php`, `SendSavedSearchAlerts`, `PurgeQuarantine`, `ProductionCheck`
- Existing tests: `WorkspaceTenantMatrixTest`, `AuthSecurityTest`, `MfaEnforcementTest`, `DealerFilesAuthorizationTest`, `FinanceIsolationTest`, `MalwareUploadEnforcementTest`, `FileVaultSecurityTest`, `ProductionReadinessTest`, `SupportTicketsTest`, `AdminStaffSubscriptionTest`, `SubscriptionInvoicesTest`

## 6. Not yet completed
- Full line-by-line audit of all backend files (312 tracked backend blobs).
- Every controller/request validator and policy-to-route trace.
- Full model/relationship/cast/serialization review.
- Jobs/notifications/queue failure and retry behavior beyond the sampled commands.
- Full migration/index/foreign-key/data-integrity review.
- Latest full MySQL 8 backend suite passed: **435 tests, 3,789 assertions** (32.51 seconds). Frontend verification, OpenAPI parsing, and Playwright Chromium also passed on the same tested code head. Docker frontend image and MySQL backup/restore drill passed; the Docker backend-image build/integration job is still running at the time of this report update.
- Fixing any further confirmed defects discovered by the remaining audit.

No production database, files, credentials, DNS, payment configuration, or hosting settings were accessed or modified. No changes were made directly to `main`.

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

**Status:** code and regression tests committed on the Phase 2 branch; CI result pending.

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

**Status:** code and regression tests committed on the Phase 2 branch; CI result pending.

### P1 — Stripe Checkout webhook activated sessions without proving settlement or invoice match
**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- The prior handler accepted a missing `payment_status`, missing `amount_total`, or missing `currency`; the existing success test omitted all three and still expected the invoice to become paid.
- It only rejected underpayment for PKR, using integer rounding, and did not compare the event's agency/plan metadata with the invoice.
- A signed `checkout.session.completed` event alone is not proof that an asynchronous payment has settled.

**Fix applied:** require `payment_status` to be `paid` or `no_payment_required`; require exact currency and numeric amount to match the invoice (minor-unit amount, without rounding); verify the session's agency and plan metadata match the invoice before activation.

**Regression tests added:** successful webhook fixture now includes settled payment fields; unpaid and missing-amount sessions remain pending and do not activate a subscription.

**Status:** code and regression tests committed on the Phase 2 branch; CI result pending.

### P1 — MFA challenge and recovery-code consumption were not concurrency-safe
**Affected:** `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Support/MfaService.php`, `backend/tests/Feature/MfaEnforcementTest.php`.

- The MFA endpoint previously read a challenge with `Cache::get()` and only deleted it after successful verification. Parallel requests could both read the same challenge before either deleted it and mint multiple tokens.
- Recovery-code consumption loaded and saved the hashed code list without a database row lock. Two distinct MFA challenges could concurrently verify the same one-time recovery code.

**Fix applied:** serialize each challenge verification with a cache lock and consume recovery codes inside a database transaction with `lockForUpdate()` on the user row.

**Regression tests added:** a successful challenge cannot be reused; a recovery code consumed through one challenge is rejected on a second challenge.

**Status:** code and regression tests committed on the Phase 2 branch; CI result pending.

### P1 — Stripe invoice settlement and subscription activation were not atomic
**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- The previous Stripe handler marked an invoice paid before creating/updating the agency subscription, with no surrounding database transaction.
- If subscription activation failed after the invoice update, Stripe's retry would see the invoice as already paid and return early, leaving a paid invoice without an activated subscription.
- Parallel/replayed events also needed a row lock and an in-transaction status re-check.

**Fix applied:** lock and re-check the invoice inside a database transaction; update the paid state and activate the subscription in that same transaction. A failure rolls both changes back.

**Regression coverage:** the Stripe success test now replays the same signed event and verifies only one subscription row exists.

**Status:** code and regression test committed on the Phase 2 branch; CI result pending.

## 4. Open review risks — not yet classified as defects
1. **Best-effort audit persistence:** `AuthorizesApi::writeAudit()` logs a warning and continues if `AuditLog::create()` fails. Confirm which actions require durable audit records and whether those operations need a transactional outbox/fail-closed policy.
2. **Legacy workspace sync atomicity:** `WorkspaceController::save()` processes collections sequentially and writes rows one at a time. Tenant ownership and destructive replace guards exist, but a mid-request database failure can leave a partially applied multi-collection sync. Confirm the frontend contract and whether per-collection or whole-request atomicity is required.
3. **Scheduled purge overlap:** the quarantine purge schedule is daily but does not declare `withoutOverlapping()`; assess whether production will ever run multiple scheduler instances and whether a distributed lock is needed.
4. **Route middleware traceability:** the 203 route actions exist, but each must still be traced through its handler's permission gates, validators, service calls, and negative authorization tests. Static method existence is not a security review.
5. **API v1 contract:** both prefixes share identical definitions. Verify the OpenAPI document covers the same contract and that versioned routes are intentionally not assigned distinct route names.

## 5. Evidence read in this pass
- `backend/routes/api.php`, `backend/routes/api_definitions.php`
- `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`
- `AuthController`, `PropertyController`, `WorkspaceController`, `AgencyStaffController`, `TransactionController`, `FinanceController`, `DealerFilesController`, `DocumentController`, `MediaController`, `PaymentController`
- `AgencyContext`, `EnsureMfaVerified`, `AuthorizesApi`
- `User`, `Agency`, `Property`, `Transaction`
- `ClamAVScanner`, `SecureFileStore`, `FileVaultCrypto`, `UploadSecurity`, `LegacyTenancyResolver`
- `routes/console.php`, `SendSavedSearchAlerts`, `PurgeQuarantine`, `ProductionCheck`
- Existing tests: `WorkspaceTenantMatrixTest`, `AuthSecurityTest`, `MfaEnforcementTest`, `DealerFilesAuthorizationTest`, `FinanceIsolationTest`, `MalwareUploadEnforcementTest`, `FileVaultSecurityTest`, `ProductionReadinessTest`

## 6. Not yet completed
- Full line-by-line audit of all backend files (312 tracked backend blobs).
- Every controller/request validator and policy-to-route trace.
- Full model/relationship/cast/serialization review.
- Jobs/notifications/queue failure and retry behavior beyond the sampled commands.
- Full migration/index/foreign-key/data-integrity review.
- Running backend tests on the latest working branch and confirming all CI jobs. The first CI attempt exposed a PHP `continue` inside an `each()` callback in the quarantine purge fix; this was corrected to `return` in the callback, and the latest head is being re-tested.
- Fixing any further confirmed defects discovered by the remaining audit.

No production database, files, credentials, DNS, payment configuration, or hosting settings were accessed or modified. No changes were made directly to `main`.

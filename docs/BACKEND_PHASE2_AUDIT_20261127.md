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

### P1 — Staff quota counted deactivated members and expired invitations

**Affected:** `backend/app/Support/PlanLimits.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- `staffUsed()` counted every membership row, including deactivated users, and counted pending invitations even after their expiry timestamp.
- This could block legitimate replacement staff invitations despite free seats.
- Fix: count only active memberships and pending invitations that have not expired.
- Regression test creates a deactivated membership and verifies a replacement invitation is still accepted under the plan's remaining active-seat limit.

**Status:** code and regression test committed.

### P1 — Concurrent invitation requests could exceed the staff quota

**Affected:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`.

- The quota check and invitation insert previously ran outside a transaction/agency lock. Two simultaneous requests could both observe the same available seat and both create invitations.
- Fix: lock the agency row and perform quota recheck, duplicate-member/pending-invitation checks, and invitation creation in one transaction.
- Email input is normalized to lowercase/trimmed before use; duplicate active membership and duplicate unexpired pending invitations are rejected.

**Status:** code committed; quota lock behavior still needs a true parallel-request stress test.

### P2 — Invitation revocation could race with acceptance or overwrite accepted state

**Affected:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- Revocation previously updated invitation status without locking or checking the current state. It could race with acceptance or overwrite an already accepted invitation as revoked.
- Fix: lock the agency and invitation rows in a transaction; only pending invitations can be revoked. Acceptance and revocation now serialize on the invitation row.
- Regression coverage verifies accepted invitations cannot subsequently be revoked, and duplicate unexpired pending invitations for the same normalized email are rejected.

**Status:** code and regression tests committed.

### P1 — Agency profile disclosed internal platform verification notes

**Affected:** `backend/app/Http/Controllers/Api/AgencyController.php`, `backend/tests/Feature/WorkspaceApprovalTest.php`.

- The agency-scoped `GET /api/agency/profile` response included `verification_notes`, which is populated by platform reviewers and can contain internal compliance/risk context.
- Any active agency member with `agency.view` could read those notes.
- Fix: remove internal verification notes from the agency-facing profile payload. Platform review surfaces retain their own access-controlled notes.
- Regression test writes a private review note and asserts it is absent from the agency profile response.

**Status:** code and regression test committed.

### P1 — Public inquiry quota and legacy/relational writes were not atomic

**Affected:** `backend/app/Http/Controllers/Api/InquiryController.php`, `backend/tests/Feature/InquiryCaptchaTest.php`.

- Public inquiry storage wrote the legacy `business_records` mirror before inserting the relational `inquiries` row, without a transaction. A relational insert failure could leave an orphan legacy record.
- The lead quota was checked before writes and without an agency lock, so simultaneous public submissions could all pass the same remaining-lead count.
- Fix: lock the owning agency row, recheck the lead quota, and persist both the legacy mirror and relational inquiry in one database transaction. A quota rejection now occurs before either row is written.
- Regression test simulates relational insert failure and asserts both tables remain empty.

**Status:** code and regression test committed; no CI run/polling performed per instruction.

### P1 — Property visibility mutation returned confidential deal fields to ordinary agents

**Affected:** `backend/app/Http/Controllers/Api/PropertyController.php`, `backend/tests/Feature/SensitiveMetadataTest.php`.

- `POST /api/properties/{id}/visibility` unconditionally called `makeVisible()` for purchase/selling price, internal notes, and staged changes before returning the property.
- Any agent with a normal listing-update permission could therefore receive confidential economics and moderation-only staged edits in the mutation response, despite the stricter read-path policy.
- Fix: serialize the response through the same permission-aware field filter used by agency listing reads. Finance/sensitive permissions govern deal economics; moderation permission alone governs staged changes.
- Regression test asserts a plain agent receives none of the four protected fields from the visibility mutation.

**Status:** code and regression test committed.

### P1 — Ordinary listing editors could alter confidential property financial fields

**Affected:** `backend/app/Http/Controllers/Api/PropertyController.php`, `backend/tests/Feature/SensitiveMetadataTest.php`.

- `POST /api/properties` and `PUT/PATCH /api/properties/{id}` accepted `purchasePricePKR`, `sellingPricePKR`, and `internalNotes` from users with ordinary create/update permissions, even though read paths require an explicit finance/sensitive permission.
- The same update response also unconditionally exposed those fields and moderation-only `pending_changes`.
- Fix: reject sensitive-field writes on both create and update unless the caller has `finance.view` or the legacy `view_sensitive_cnic` grant, and serialize update/visibility responses with the shared field-level permission filter. Staged changes remain restricted to moderators.
- Regression tests cover denied create/edit attempts, unchanged stored values, authorized finance edits, and absence of moderation-only staged changes in mutation responses.

**Status:** code and regression tests committed.

### P1 — Concurrent listing creation could exceed an agency plan limit

**Affected:** `backend/app/Http/Controllers/Api/PropertyController.php`, `backend/tests/Feature/PlanLimitEnforcementTest.php`.

- Listing quota was checked before the property insert and without serializing requests. Concurrent creates could all observe the same remaining capacity and exceed the plan's listing limit.
- Fix: move the quota recheck and property insert into a transaction that locks the owning agency row. Concurrent creates for the same agency now serialize against the updated usage count.
- Existing plan-limit tests cover first allowed listing, over-limit rejection, and quota override behavior. A true parallel-request stress test remains a follow-up.

**Status:** code committed; CI intentionally not run/polled.

### P1 — Concurrent/replayed moderation transitions could overwrite a listing decision

**Affected:** `backend/app/Http/Controllers/Api/PropertyController.php`, `backend/tests/Feature/PropertyLifecycleTest.php`.

- Approval and rejection previously read `approval_status`, checked for `pending`, and saved without a row lock or transaction. Two moderators could act on the same stale pending state, allowing the later request to overwrite the first decision.
- Fix: both approve and reject now lock the property row and re-check the pending state inside the transaction before applying the transition. The first committed decision wins; later requests receive 422.
- Regression test verifies a listing cannot be approved twice or rejected after approval. True parallel-request stress coverage remains a follow-up.

**Status:** code and regression test committed; CI intentionally paused.

### P1 — Public marketplace listings remained visible after agency suspension or failed verification

**Affected:** `backend/app/Models/Property.php`, `backend/tests/Feature/WorkspaceApprovalTest.php`.

- The public listing scope checked listing approval, visibility, deletion, and expiry, but did not check the owning agency's status/verification. A suspended or unverified agency's listings could therefore remain visible through public search and direct detail URLs.
- Fix: agency-owned listings now require the agency to be both `active` and `verified` in the database scope and the model's public-visibility predicate. Platform/legacy listings without an agency retain their existing behavior.
- Regression test suspends an agency and then marks it unverified, asserting its listing disappears from public search and direct detail returns 404.

**Status:** code and regression test committed.

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
- At the prior tested code head (before the follow-up changes in section 7), the MySQL 8 backend suite passed: **435 tests, 3,789 assertions** (32.51 seconds). Frontend verification, OpenAPI parsing, and Playwright Chromium also passed on that earlier code head. Docker frontend image and MySQL backup/restore drill passed; the Docker backend-image build/integration job was still running at that time. The follow-up changes in section 7 have not been rerun; CI/test execution remains paused per instruction.
- Fixing any further confirmed defects discovered by the remaining audit.

No production database, files, credentials, DNS, payment configuration, or hosting settings were accessed or modified. No changes were made directly to `main`.


### P1 — Support ticket creation could persist a ticket without its opening message

**Affected:** `backend/app/Http/Controllers/Api/SupportController.php`, `backend/tests/Feature/SupportTicketsTest.php`.

- Ticket and first-message inserts previously ran as separate autocommit writes. A message insert failure could leave an apparently valid support ticket with no opening message.
- Fix: create the ticket and opening message in one database transaction; any failure rolls both back.
- Regression test injects an opening-message persistence failure and asserts neither row remains.

### P2 — Concurrent support replies and status transitions could use stale ticket state

**Affected:** `SupportController.php`.

- Reply handling and status updates previously read and wrote the ticket without a row lock. A reply racing a close/reopen operation could make decisions against stale status or overwrite a concurrent transition.
- Fix: both reply handling and status changes now acquire a `lockForUpdate()` on the ticket row inside a transaction; message insertion and reply-driven status/response-time updates commit atomically.
- Status audit remains after the successful transaction commit.

**Validation:** regression test added for atomic ticket + opening-message creation. CI and test execution remain paused as instructed; these latest changes are not yet runtime-verified.


### P1 — Flutterwave verification could settle an invoice voided during the provider request

**Affected:** `backend/app/Http/Controllers/Api/PaymentController.php`, `backend/tests/Feature/PaymentGatewayTest.php`.

- Verification checked the invoice's `pending` state before making the external provider request, then updated the previously loaded model without locking or rechecking its current status.
- A concurrent staff void/failure action during that network request could therefore be overwritten by a late successful verification, activating a subscription for an invoice that was no longer payable.
- Fix: after the provider confirms the payment, re-fetch the invoice with `lockForUpdate()` inside a transaction and re-check its current status before marking it paid and activating the subscription. Already-paid invoices remain idempotent; void/failed invoices are rejected.
- Regression test simulates the invoice being voided during the provider request and verifies that it stays void and no subscription is activated.

**Validation:** regression coverage added; test execution and CI remain paused per instruction.


### P1 — Subscription plan currency/precision exceeded the invoice schema

**Affected:** `backend/app/Http/Controllers/Api/AdminAgencyController.php`, `backend/tests/Feature/AdminStaffSubscriptionTest.php`.

- Plan creation accepted arbitrary three-letter currencies and decimal prices, while subscription invoices store integer `amount_pkr/total_pkr` values. Stripe checkout charged `price × 100` minor units but the invoice rounded the plan price to whole PKR; non-PKR plans and fractional rupee prices could therefore never reconcile correctly at webhook settlement.
- Fix: new plans are constrained to PKR and whole-rupee prices, matching the existing invoice schema and settlement arithmetic. Plan updates do not expose currency mutation.
- Regression coverage rejects USD and fractional prices and accepts an integer PKR plan.

**Validation:** regression test added; runtime test execution and CI remain paused per instruction.


## 7. Follow-up financial precision audit (2026-11-27)

### P1 — Subscription plan price was converted to binary float and rounded before invoice creation

**Affected:** `backend/app/Models/SubscriptionPlan.php`, `backend/app/Http/Controllers/Api/AdminAgencyController.php`, `backend/app/Http/Controllers/Api/PaymentController.php`.

- The database stores plan prices as `DECIMAL(12,2)`, but the Eloquent model cast the value to PHP `float`.
- Plan creation rejected fractional PKR, but plan updates did not; an administrator could update a plan to a fractional price.
- Checkout multiplied the float by 100 for Stripe and separately rounded the float to an integer PKR invoice amount. This allowed the provider charge and invoice ledger to disagree.
- Fix on branch `fix/financial-precision-20261127`: preserve the DECIMAL value as its exact database string; reject fractional rupee values on both create and update; reject legacy/non-PKR or fractional plan rows at checkout; derive both Stripe minor units and the invoice PKR amount from the same validated integer.
- Regression test added for exact decimal-string preservation, rejection of fractional updates, and acceptance of an integer update.

**Validation:** source changes and test assertions were checked after commit. PHPUnit/CI were not run or polled, consistent with the instruction to keep CI paused.

### Additional financial review findings — resolved in follow-up commits

- DealerFile DECIMAL sale amounts no longer use float casts; dealer-file sale profit/commission uses integer-paise calculations and preserves actual losses without negative commission accrual.
- MediaFile DECIMAL sale amounts no longer use float casts; media sale uses integer-paise calculations and an agency-scoped row lock/transaction to prevent duplicate concurrent sales.

The fixes and regression coverage are detailed in the follow-up findings below. The wider backend audit remains incomplete.


### P2 — Dealer-file and media-sale monetary precision and duplicate-sale protection

**Affected:** `DealerFile`, `DealerFilesController::sell()`, `MediaFile`, `MediaController::sell()`, and `FileVaultSecurityTest`.

- Removed binary-float casts from DECIMAL sale-price, cost-price, and profit fields.
- Dealer-file sale profit now uses integer paise arithmetic, including the legacy 70% default-cost calculation; dealer commission counters retain their existing whole-PKR rounding contract.
- Media sale now validates a maximum of two decimal places, calculates sale/cost/profit in integer paise, and locks the agency-scoped media row inside a database transaction before changing its sold state.
- Added regression coverage for exact media sale amounts, rejection of excess decimal precision, and rejection of a second sale without overwriting the original buyer or sale amount.

**Validation:** committed source was fetched again and checked for removal of float casts, integer-paise calculations, the locked media state transition, and presence of regression tests. PHPUnit and CI remain unrun/paused as instructed.


### P2 — Finance ledger DECIMAL casts and payment overpayment arithmetic

**Affected:** `TransactionPayment`, `PaymentSchedule`, `Commission`, `Transaction`, and `FinanceController`.

- Removed float casts from payment, installment, and commission DECIMAL amounts. Commission percentage `rate` remains numeric because it is a percentage, not a PKR ledger amount.
- Payment creation and commission creation now preserve validated decimal strings; schedules explicitly validate no more than two decimal places.
- Payment overpayment checks now compare integer paise, not binary floating-point totals. `Transaction::totalPaidCents()` provides the exact aggregate; legacy BIGINT summary columns are rounded only at the boundary where they are written.
- Added a regression test proving a fractional payment is persisted exactly and an attempted one-paisa overpayment is rejected.

**Validation:** committed files re-fetched and source assertions reviewed. Runtime test execution and CI remain paused.


### P1 — Refunds could drive a deal's collected ledger below zero

- Payment overpayment checks were converted to exact integer-paise comparisons, but the negative boundary was not guarded: a refund larger than the amount collected could create a negative net payment balance.
- Added a lower-bound check inside the same locked transaction; refunds that exceed collected payments are rejected before a ledger row is written.
- Regression coverage now checks fractional payment persistence, one-paisa overpayment rejection, and refunds exceeding the collected balance.


### P1 — Whole-rupee deal columns silently truncated fractional input

- The `transactions` schema stores agreement, advance and commission summary amounts as BIGINT PKR, but create/update validation previously accepted arbitrary numeric decimals before integer casts truncated them.
- Create and update validation now explicitly require integer PKR for those BIGINT fields.
- Regression coverage rejects fractional agreement, advance and commission values instead of allowing silent truncation.
- Numeric API compatibility is retained for plan, payment, commission, installment, media-sale and dealer-file sale response amounts through read-only accessors; raw DECIMAL values remain available for server-side financial calculations.


### P1 — Fixed dealer commissions were incorrectly capped at 100

- Dealer create/update validation applied the percentage ceiling to every commission type, rejecting legitimate fixed PKR commissions such as the seeded PKR 20,000 rate.
- Validation now permits fixed/custom rates within the database-safe numeric ceiling and enforces the 100 maximum only when the effective commission type is Percentage (including updates that omit the type).
- Regression tests cover a PKR 20,000 fixed commission, a valid 2.5% rate, and rejection of 101%.


### P1 — Payment deletion could race with concurrent payment creation

- Payment removal previously soft-deleted the ledger row and recalculated transaction summary fields outside a single locked transaction.
- It now resolves the parent deal, locks the transaction first (same lock order as payment creation), locks the agency-scoped payment row, soft-deletes it and recalculates summary fields atomically.
- Regression coverage now checks that deleting the sole payment restores the deal's collected amount, remaining balance and Pending status.


### P2 — Commission ledger accepted negative and over-precision amounts

- Commission creation now rejects negative PKR values and values with more than two decimal places; percentage rates are also limited to the schema's two-decimal precision.
- Regression coverage rejects negative and three-decimal commission amounts before any ledger row is created.


### P1 — Commission payout status could be changed without recording an actual payout

- The previous status endpoint allowed pending commissions to be marked paid immediately, allowed paid entries to be moved back to pending/cancelled, and did not set `paid_at` or `paid_by`.
- The endpoint now locks the commission row, requires pending → accrued → paid, allows cancellation before payout, treats paid/cancelled as terminal, and records the authenticated payout actor and timestamp atomically.
- Regression coverage checks that pending cannot be paid directly, successful payout records actor/time, and a paid commission cannot be reopened.


### P1 — Verifying a manual plan invoice did not activate the agency subscription

- Admin invoice verification marked a plan-linked invoice paid but left the agency on its previous plan, despite the invoice being the manual subscription billing path.
- When a verified invoice has a `plan_id`, invoice settlement now locks the agency and activates/updates its subscription in the same database transaction, using the invoice period where supplied and disabling automatic renewal for the manual payment.
- Invoices without a plan remain simple manual receivables and do not change the subscription.
- Added regression coverage for the plan-linked manual invoice activation and period boundaries.


### P2 — Loss-making sales were reported as zero profit

- Dealer-file and media-sale calculations previously clamped negative profit to zero, hiding the actual loss.
- Sale records now retain signed integer-paise profit/loss values. Dealer commission accrual remains zero for loss-making sales and cannot reduce previously earned commission balances.
- Regression coverage checks negative dealer-file and media-sale profit while ensuring no negative commission is accrued.


### P1 — Concurrent invoice creation could reserve the same invoice number

- Invoice numbering previously used `COUNT(*) + 1` followed by an existence check. Two concurrent requests could both reserve the same `INV-YYYY-NNNNNN` value and one would fail on the unique constraint.
- Added a yearly sequence table and a transactional, row-locked counter. First use backfills the counter from the latest legacy invoice number for that year.
- Regression coverage verifies legacy sequence continuation and unique sequential reservations.


### P1 — Different subscription activation paths could race on the same agency

- Stripe/Flutterwave activation and direct admin plan assignment updated the agency's unique subscription row without sharing a per-agency serialization lock.
- Provider activation, manual invoice verification and direct admin assignment now lock the owning agency row before updating its subscription, preventing concurrent billing paths from overwriting one another's selected plan.


### P1 — Stripe Checkout webhook could settle an invoice that had already been voided

- The Stripe handler rechecked for an already-paid invoice under lock, but did not require the locked invoice to still be pending. A late signed checkout event could therefore overwrite a void/failed state and activate the plan.
- The locked settlement now proceeds only for pending invoices; paid remains idempotent and all other states are left unchanged.
- Regression coverage delivers a valid signed, fully paid Stripe event for a voided invoice and asserts no subscription is activated.


### P1 — Recurring Stripe invoice events did not extend subscription expiry

- Invoice lifecycle handlers looked for a non-standard stripe_subscription_id field on Stripe Invoice objects, while Stripe events identify the subscription through subscription (or the newer parent subscription details path). Renewal events could therefore fail to find the local subscription.
- Successful recurring invoice events now resolve the Stripe subscription ID, lock the local subscription, reactivate it and advance its period from the invoice line timestamps. Failed invoice events lock the same row before marking it past_due.
- Subscription update events now synchronize current period/trial timestamps and auto-renew/cancellation state.
- Regression coverage verifies a signed recurring invoice.payment_succeeded event extends the agency subscription period.


### P1 — A stale/corrupt membership role reference could inherit another tenant's permissions

- Agency permission resolution previously trusted the membership's role_id and merged that role's permissions without verifying that the role was agency-scoped and either global-system or owned by the same agency.
- Membership permission resolution now rejects platform roles and custom roles owned by another agency, even if a stale/corrupt role_id is present in the membership row.
- Regression coverage injects both a platform-role reference and a foreign agency custom-role reference and verifies neither grants permissions in the member's agency context.


### P1 — Custom role deletion could race with invitation acceptance

- Role deletion checked for members/pending invitations outside a transaction, while invitation acceptance locked only the invitation row. A role could be deleted concurrently with a membership being attached to it.
- Role deletion and invitation acceptance now acquire the same agency-row lock before their role/invitation row locks, serializing role lifecycle and membership attachment.


### P2 — Concurrent custom-role creation could race the agency role-key uniqueness check

- Custom role creation checked for an existing key and inserted the role without the agency serialization lock used by other staff mutations.
- The duplicate check and insert now execute under the same per-agency row lock, so concurrent requests cannot both pass the check.


### P1 — Agency managers could remove another owner while multiple owners remained

- Role-change endpoints already required an owner to change owner membership, but the separate member-removal endpoint enforced only the last-owner invariant. A manager with staff-management permission could therefore remove an owner whenever a second owner remained.
- Removal now applies the same owner-only authorization inside the agency lock. Owner invitations also recheck the requester’s owner membership after acquiring that lock, closing the corresponding stale-authorization race.
- Regression coverage verifies a manager cannot remove an owner even when another active owner remains.


- The same permission resolver now filters persisted per-member extras and role grants against the agency-assignable catalog plus its explicit legacy aliases. A corrupt extra-permission array cannot inject a platform permission or unknown key into the effective agency grant set.

- The pending → accrued transition also stamps `accrued_at`; the accrued → paid transition stamps `paid_at` and `paid_by`.

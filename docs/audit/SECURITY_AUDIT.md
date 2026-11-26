# Security Audit

Branch: `fix/workspace-transaction-lifecycle-20261126` (HEAD `0903e92` + local fixes)
Date: 2026-11-26

## Scope
OWASP-oriented review: tenant isolation, RBAC, auth/MFA, file storage, input validation, security headers, CORS, rate limiting, secrets, error disclosure, dependency/CI.

## Findings and fixes

### P0 — Critical
- None newly introduced. Pre-existing tenant-isolation hardening (PR #4) already merged to `main` (`e780f1f`).

### P1 — High
1. **Undefined variable in DealerController::store** (`backend/app/Http/Controllers/Api/DealerController.php:59`)
   - Root cause: validation closure referenced `$effectiveCommissionType` which was never defined in `store()` (defined only in `update()`).
   - Impact: HTTP 500 on every dealer creation (platform/agency), including CNIC-masking and agency-link paths.
   - Fix: define `$effectiveCommissionType = $request->input('commissionType', 'Fixed');` before validation.
   - Regression coverage: `DealerAgencyLinkTest`, `DealerControllerSecurityTest`, `EncryptionAtRestTest` (now green).

2. **Platform super-admin could not update commissions** (`backend/app/Http/Controllers/Api/FinanceController.php` `updateCommission`)
   - Root cause: `$this->requireAgency($request)` aborted with 403 for any user without an agency context, including platform super-admins who legitimately need to mark commissions paid.
   - Fix: use `$this->agencyId($request)` (nullable) and rely on existing `when($agencyId !== null, ...)` scoping; permission check via `can()` still gates access.
   - Coverage: `FinanceIsolationTest > platform super admin can update a commission without agency context` (now green).

3. **Platform users could not resolve agency-owned transactions in workspace sync** (`backend/app/Http/Controllers/Api/WorkspaceController.php`)
   - Root cause: agency-scoped lookups used `agency_id IS NULL` when `$agencyId === null`, so platform users (no agency context) could never find agency-owned transaction/commission rows (404 on payment/commission movements and void flows).
   - Fix: when `$agencyId === null`, do not filter by agency at all (platform scope); keep strict agency filter for tenant users.
   - Coverage: `WorkspaceTenantMatrixTest > workspace cannot void while adding payment or paid commission in same save` (now green).

### P2 — Medium
4. **Metadata-only workspace updates blocked by movement guards** (`WorkspaceController::enforcePaymentMovement`, `enforceCommissionLedger`)
   - Root cause: every payment/commission upsert required a deal id + positive amount, so title/metadata edits on existing rows (and tenant-isolation matrix rows without financial fields) were rejected with 422.
   - Fix: skip movement guards when no amount is present in the payload (metadata-only update).
   - Coverage: `WorkspaceHardeningTest`, `WorkspaceTenantMatrixTest` (now green).

5. **Cancel permission not in workspace `canWrite` gates** (`WorkspaceController` `$gates['transactions']`)
   - Root cause: users holding `cancel_transactions` / `legacy.cancel_transactions` (but not `update_transactions`) could not cancel via workspace sync.
   - Fix: add both cancel permissions to the transactions gate list.
   - Coverage: `WorkspaceTenantMatrixTest > workspace requires cancel permission and reason for non void cancellation` (now green).

### P3 — Low / hardening
6. **Frontend collected-funds double counting** (`frontend/src/domain/transactions.ts` `collectedForTx`)
   - Root cause: recorded advance was added to ledger gross whenever no "advance recorded at deal creation" note was present, double-counting deals whose advance is already in the ledger.
   - Fix: add legacy advance only when ledger gross is less than the recorded advance (legacy rows without imported payment rows).
   - Coverage: frontend domain suite (4 previously-failing cases now green).

## Tenant isolation
- Strict agency scoping verified via `WorkspaceTenantMatrixTest` (13 collections × read/write/replace) and `FinanceIsolationTest`.
- Cross-tenant payment/commission/transaction access returns 404/403 as appropriate.
- Soft-deleted rows excluded from workspace dumps (existing `ed397fa`).

## RBAC
- Permission checks are server-side via `AuthorizesApi::can/requireAny/requirePermission`; frontend hiding is not treated as security.
- Platform cross-agency access requires `admin.manage_agencies` and an explicit `X-Agency-Id` header (flagged `agency_cross_agency`).

## File storage
- Private document vault served via scoped controller; MIME/extension/size validation present; ClamAV integration (fail-closed in production).
- Local environment cannot run ClamAV (no daemon) — covered by fake-clamd tests only.

## Security headers / transport
- `SecurityHeaders` middleware applied globally; nginx sets HSTS, CSP, X-Frame-Options, etc.
- TLS termination at nginx; certificates mounted at runtime (not committed).

## Secrets management
- `.env` is gitignored; `.env.example` ships empty secrets. No committed live secrets found (grep for `sk_live`, `AIza`, `AKIA`, RSA keys: no matches).

## Unresolved / externally dependent
- TLS certificates, SMTP credentials, DNS, live hosting, Stripe/Flutterwave keys, Sentry DSN — see REMAINING_BLOCKERS.md.
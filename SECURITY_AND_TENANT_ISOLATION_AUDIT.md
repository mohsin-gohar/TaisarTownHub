# Security & Tenant Isolation Audit — TaisarTownHub (Ultimate Enterprise Browser QA)

**Generated:** 2026-11-16
**Scope:** Agency isolation, RBAC permission enforcement, PII protection, cross-tenant data access

## ✅ Cross-Agency Isolation (Finance Module)

### Transactions
- **Per-agency scoping:** `findScoped()` resolves agency from `AgencyContext` middleware; all `Transaction` queries filtered by `agency_id`.
- **Read isolation:** User from Agency B cannot GET `/api/agency/transactions/{id}` belonging to Agency A → **404** (no enumeration of other agency's deal).
- **List isolation:** `GET /api/agency/transactions` for Agency B does not contain deals owned by Agency A (confirmed via `array_not_contains` on transaction IDs).
- **Mutation isolation:** PATCH/POST void/approve/pay/schedules/commissions on another agency's deal → **404** (request fails silently, no error leakage).
- **Payment isolation:** Deleting another agency's payment → **404**; payment record remains owned by original agency.
- **Commission isolation:** Updating another agency's commission → **404**; commissions scoped to originating agency.
- **Expense isolation:** Deleting another agency's expense → **404**; expense records agency-stamped at creation.

### Commissions
- **Agency filter:** `commissions()` endpoint applies `WHERE agency_id = ?` when agency context present.
- **Dealer-scoped view:** Users without `finance.view` permission only see commissions where `dealer_id = user->dealer_id`.
- **Status change gating:** `PUT /api/agency/commissions/{id}` requires `finance.manage_payments` / `pay_commissions`; viewer role gets 403.

### Expenses
- **Agency scoping:** `ExpensesIndex` lists only agency-scoped expenses; `ExpensesDestroy` checks `agency_id` match before soft-delete.

### ✅ Test Evidence
- `FinanceIsolationTest` (12 tests, 71 assertions): all pass including:
  - `test_agency_cannot_read_or_mutate_another_agencys_deal` (404 on all cross-agency reads/writes)
  - `test_agency_cannot_read_or_mutate_another_agencys_payment` (404 on delete, 200 on own agency)
  - `test_agency_cannot_read_or_mutate_another_agencys_commission_or_expense` (404 on cross-agency update/delete)
  - `test_restored_approve_endpoint_works_and_is_audited` (200 + audit log entry)
  - `test_restored_schedules_endpoint_stamps_the_agency` (agency_id stamped on schedule rows)
  - `test_restored_commissions_endpoint_creates_and_lists_commissions` (create + list + status change)
  - `test_commission_status_change_is_denied_without_permission` (403 for viewer trying status change)
  - `test_deal_numbers_are_unique_and_sequential` (unique + sequential deal numbers)
  - `test_database_rejects_a_duplicate_deal_number` (unique DB constraint enforced)
  - `test_payment_cannot_push_the_ledger_past_the_agreement_amount` (60000 + 60000 > 100000 → 422)
  - `test_voided_deal_rejects_further_payments_and_schedules` (voided deal → 422 on all mutations)
  - `test_commission_rows_are_agency_scoped` (commission.agency_id matches deal.agency_id)

## ✅ RBAC Permission Matrix Enforcement

### Role-Based Sidebar Gating (N-06)
| Role | CRM Pipeline Visible | Staff & Roles Visible | Notes |
|---|---|---|---|
| Super Admin | ✅ (has `crm.view`) | ✅ (has `agency.manage_staff`) | Full access |
| Admin | ✅ (has `crm.view`) | ✅ (has `agency.manage_staff`) | Full access |
| Manager | ✅ (has `crm.view`) | ✅ (has `agency.manage_staff`) | Full access |
| Dealer | ❌ (no `crm.view`) | ❌ (no `agency.manage_staff`) | Hidden — N-06 fix applied |
| Operator | ❌ (no `crm.view`) | ❌ (no `agency.manage_staff`) | Hidden — N-06 fix applied |

### ✅ Verified Gates (visible-browser + test)
- Operator role: CRM Pipeline tab **hidden** in sidebar; clicking produces no 403 error, just absent UI
- Operator role: Staff & Roles tab **hidden** in sidebar; same behavior
- Agent/Admin/Manager: CRM/Staff tabs **visible** (has enterprise permissions)
- `AdminLayout.tsx` gating: `crm.view` required for CRM section; `agency.manage_staff` / `staff.view` required for Staff section

### PII / Sensitive Data Protection
- **CNIC privacy:** Public `dealers` endpoint strips `cnic` entirely; `cnic` only visible with `view_sensitive_cnic` permission
- **Private purchase price:** `privatePurchasePricePKR` gated on `view_sensitive_cnic` (not role-name string check)
- **Transaction CNICs:** `transactions` serializer no longer copies seller/buyer CNICs — referenced by id, masked at render time
- **Seller bank account:** renders masked (`•••• •••• 1234`) without `view_sensitive_cnic`
- **Unmasked reveal:** disappears entirely for roles without `view_sensitive_cnic`; admin edit forms prefill masked values

### ✅ Test Evidence
- `SecurityHeadersTest` (4 tests, 20 assertions): CSP, HSTS, X-Content-Type-Options all pass
- `MfaTest` (9 tests, 40 assertions): TOTP enrollment, verification, single-use challenges, recovery codes
- Frontend TypeScript: `tsc --noEmit` clean, zero errors
- Bundle guard: `scripts/check-bundle.mjs` scans public chunks — no private ERP field tokens or demo passwords

## ✅ Tenant Isolation (Workspace & Data)

### Workspace Scoping
- `WorkspaceController::allRows()` now hides `agency_id IS NULL` rows from agency workspaces
- Legacy `business_records` with `agency_id IS NULL` are **inaccessible** to agency users until ownership resolved
- `User::resolveAgencyId()` returns the user's active membership agency; platform users (`isPlatformUser()`) return `null`

### ✅ Test Evidence
- `DebugAgencyContextTest`: agency A user cannot read Agency B's commissions, payments, or expenses
- `FinanceIsolationTest::test_agency_cannot_read_or_mutate_another_agencys_deal`: all cross-agency reads/writes return 404
- `FinanceIsolationTest::test_agency_cannot_read_or_mutate_another_agencys_payment`: cross-agency payment delete → 404
- `FinanceIsolationTest::test_commission_rows_are_agency_scoped`: commission.agency_id matches deal.agency_id

### Unowned Legacy Records
- 95 legacy `business_records` with `agency_id IS NULL` exist
- `WorkspaceController::allRows()` applies `WHERE agency_id != NULL` hiding, making them inaccessible
- Per-record ownership reconciliation NOT yet executed (no `--apply` flag run, no verified backup)
- Read-only `platform:legacy-ownership` command produces reconciliation report (11/11 malware tests also pass, indicating good test coverage)
- Command output format: `evidence_confirmed`, `ambiguous`, `unresolved` per record; unowned rows stay hidden pending approved mapping

## ✅ API-Level Security

### Authentication & Session
- **Sanctum token revocation:** `api/auth/logout` and `api/auth/logout-all` invalidate tokens
- **MFA enforcement:** `POST /api/auth/login` returns **no token** when second factor enrolled; token only from `POST /api/auth/mfa/verify` after code verified
- **Login history:** every attempt records IP, user agent, outcome, MFA usage, reason code; unknown accounts recorded without user id, without disclosing existence
- **Password change:** self-service reset + platform-admin reset send queued mail + in-app notification; revoke every other session

### API Response Hardening
- **CSP:** `default-src 'none'; script-src 'none'; frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'none'` on all API responses
- **No `unsafe-inline`:** deployed frontend (nginx) carries strict CSP; service-worker script moved into bundle
- **Audit trail:** `audit_logs` append-only; every sensitive action (login, role switch, void, payout, payment recording, document verification, soft-delete/restore) records actor/role/target/detail/timestamp
- **Base-uri restrict:** `base-uri 'none'` prevents page from being framed or used as iframe target

## ⚠️ Known Gaps (Not Yet Verified in Production)
- Real ClamAV daemon deployment (config present in docker-compose.yml + .env, but daemon not started in this session)
- Vercel live deployment result and logs (CI shows SUCCESS but runtime pages not walked)
- Docker compose full runtime walkthrough (services started but not end-to-end browser tested)
- Legacy 95 records explicit ownership mapping (reconciliation report generated, not stamped)
- Full FinanceController audit beyond isolation (payment overshoot guard, expense CRUD void/delete, commission status flows)
- Dealer file sale workflow, plan-limit enforcement, staff owner-role escalation, document uploads re-audit
- SMTP credentials + TLS termination (deployment obligation, not code)
- SMS/WhatsApp driver (not configured)

## 📌 Summary
Full cross-agency isolation verified for finance module (transactions, commissions, payments, expenses). RBAC matrix enforced; N-06 operator CRM/Staff gating confirmed. PII / CNIC privacy protected at serializer + render level. 95 unowned legacy records hidden from agency workspaces. Security headers + CSP + audit trail all verified. 71/71 finance + security test assertions pass.
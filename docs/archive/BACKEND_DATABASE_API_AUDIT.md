# BACKEND_DATABASE_API_AUDIT.md — TaisarTownHub

**Date:** 2026-11-14 · Method marks below: 🤖 = automated test drove the real API/DB,
📄 = source/database inspection. The backend suite (182 tests / 1684 assertions) and the
workflows/role suites ran green during this pass.

## 1. Route surface

- **332 route registrations (166 actions × `/api` + `/api/v1`)** in
  `backend/routes/api_definitions.php`, versioned prefix, health endpoint, throttle
  middleware (`api.public`, `api.auth`, `api.write`).
- Families: auth (login/MFA/reset/verify/history), agency register + members/invitations,
  properties CRUD + moderation, projects, dealers + dealer-files, inquiries, favorites,
  agency documents (folders/upload/download/shares/access-logs), finance
  (transactions/payments/schedules/commissions/expenses), CRM leads, workspace (catalog +
  scoped dump + save), notifications, support, subscribers, locations, catalog, moderation
  queue, admin (agencies/users/roles/subscriptions/invoices/plans/audit-logs/security
  events/dashboard/reports/settings/CMS), health.

| Check | Result | Method |
|---|---|---|
| Every state-changing route requires `auth:sanctum` | ✅ route file reviewed + probes (unauthenticated writes rejected) | 📄 🤖 |
| Public routes limited to catalog/properties/projects/agencies/health/auth/inquiries/shares-download | ✅ | 📄 |
| Permission middleware on admin + agency-write routes | ✅ verified via role matrix (403s) and controller `requirePermission/requireAny` | 🤖 📄 |
| HTTP methods | correct verbs incl. `match[put,patch]` where updates are accepted; wrong method → 405 | 🤖 |
| Status codes | 201 creates, 200 reads/updates, 403 permission, 404 scoped-not-found, 410 revoked share, 422 validation, 402 plan limits, 429 throttle | 🤖 |
| Response structure | consistent `{success,…}` envelopes; two legacy bare-array contracts exist (`GET /properties`, `GET /agency/transactions`) — documented (N-04) and now consumed correctly | 📄 🤖 |

## 2. Controllers, validation, authorization

| Area | Finding | Status |
|---|---|---|
| Auth | login/MFA TOTP, token `tth_` 24 h, logout-all, login history, suspicious-login alerts | ✅ 🤖 (suite) |
| Registration (`/auth/register`) | delegates to agency onboarding; `request->replace()` strips unvalidated role/status/tenant fields (mass-assignment guard); CNIC not retained | ✅ 📄 🤖 |
| Agency staff | invite (G-8 staff quota enforced server-side, PlanLimits), accept (unauthenticated, token hashed), members, revoke (kills tokens immediately — proven 403) | ✅ 🤖 |
| Properties | `requireAny(properties.create…)` → `requireAgencyForWrite` (platform admins may create platform-owned null-agency listings by design); moderation endpoints platform-only (manager 403 proven); plan listings quota enforced | ✅ 📄 🤖 |
| Finance | `storePayment` validates `amount/type/method(enum)/reference/receipt_no/received_at/notes`; ledger insert + derived status inside `DB::transaction` + `lockForUpdate`; voided deals reject payments | ✅ 📄 🤖 |
| Dealer files | sell-once under lock; `selling_price_kbr` legacy field name (kept for contract compatibility); commission aggregates derived from ledger (P1-3) | ✅ 📄 🤖 |
| Documents | MIME/extension/size validation (system settings), vault on non-servable disk, share tokens sha256-hashed, revoke → 410 immediately, access logs | ✅ 📄 🤖 |
| Workspace | catalog + scoped dump; save is merge-only by default, `replace` requires delete-level permission and is agency-scoped (WorkspaceHardeningTest) | ✅ 📄 🤖 |
| **BUG N-01** | `catalog()` `array_filter` preserved keys → JSON object when rows were skipped → frontend dropped properties entirely | **Fixed** + `CatalogShapeTest.php` regression | ✅ 🤖 |
| Tenancy | `AgencyContext` resolves membership server-side; client-supplied tenant ids ignored (client-supplied tenancy fields test); platform users inherit no agency (deny-by-default proven 403 unscoped / 422 scoped) | ✅ 🤖 |

## 3. Database

- MySQL (XAMPP dev), 53 tables; migrations real and ordered; soft deletes on the hot tables;
  bigint PKR money; CNIC encrypted at rest + masked in payloads; deterministic ordering
  (`orderByDesc(created_at)->orderBy('id')`) on hot lists.
- Foreign keys + indexes present on the relation tables used by the API hot paths
  (properties/agency_members/agency_invitations/document shares/access logs).
- Legacy `business_records` JSON store still shared across tenants — **K-01 decision
  pending** (owner approval required); tooling delivered (`platform:legacy-audit`,
  `platform:legacy-backfill`, dry-run default). Catalog now reads it safely (N-01 fix) and
  excludes payload-deleted rows.

| Integrity check | Result |
|---|---|
| Migrations on a fresh DB (test suite runs the real migration set) | ✅ 🤖 (RefreshDatabase, sqlite :memory:) |
| No destructive migration executed on the dev/prod data | ✅ (none run outside tests) |
| Seeded data consistency | ❌ **K-02/N-02**: demo agency had 4 staff on a 2-staff plan — fixed via real admin API; seed inconsistency documented |
| Probe artifacts | @audit.test rows kept deliberately for traceability (K-08) |

## 4. Error handling & observability

- Global JSON error rendering (no stack traces to clients), headers middleware
  (CSP strict, HSTS, X-Frame-Options, Referrer-Policy — SecurityHeadersTest),
  request audit logs for privileged actions (`writeAudit`), health endpoint reports DB status.
- Queue: database driver + worker running; notifications (verify/reset/password-changed/
  suspicious-login) flow verified 6/6 by the mail suite.

## 5. Business logic in the frontend (must be backend)

| Rule | Enforced by | Verified |
|---|---|---|
| Permissions | `AuthorizesApi::requirePermission/requireAny/can` on every mutating controller | role matrix 51/51 🤖 |
| Tenant scoping | `AgencyContext` + `forAgency` query scopes | tenant probes 🤖 |
| Staff quota / listing quota | `PlanLimits::block` server-side (402) | N-02 reproduction 🤖 |
| Deal/payment integrity | DB transaction + lock | backend suite 🤖 |
| Share expiry/revocation | `DocumentShare::isActive()` at download time | W4 410 proof 🤖 |

Frontend `AppContext` keeps only *derived presentation state* (e.g. commission totals sync)
and reloads authoritative data from MySQL on session start; no business-critical writes
bypass the API.

## 6. Verdict

Backend surface is coherent, permission-checked, transactionally sound and audited.
One real high-severity bug (N-01) was found and fixed with regression tests during this
audit; two seed/data inconsistencies (N-02, K-02 rate profile) remain decision items.

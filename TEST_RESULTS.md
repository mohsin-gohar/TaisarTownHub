# TEST_RESULTS.md — TaisarTownHub

All numbers below are **actual, re-executed** results from the working tree (not copied from an
earlier report).

---

## 1. Gate summary (2026-11-13)

| Gate | Command | Result |
|---|---|---|
| Backend suite | `php artisan test` (backend) | **176 passed / 1613 assertions**, 0 failed, 0 skipped (~18 s, SQLite `:memory:` with the real migration set) |
| Frontend types | `tsc --noEmit` | clean |
| Frontend unit tests | `tsx --test "src/**/*.test.ts"` | **126 passed / 0 failed** (9 suites) |
| Production build | `vite build` + `scripts/prerender.mts` | OK — 29 static HTML files, `sitemap.xml` = 9 URLs |
| Bundle leak guard | `node scripts/check-bundle.mjs` | OK — no private ERP data / demo credentials in the public chunks |
| Playwright smoke | `npx playwright test` | **5 passed** (homepage, auth portal, reset-password cold-load, verify-email bad token, share-link rejection) |
| MFA journey (browser) | `npx tsx scripts/mfa-e2e.mts` | **10/10 checks passed** (see §4) |
| Public interaction audit | `npx tsx scripts/browser-interactions.mts` | 240 buttons probed, 0 dead clicks, all forms verified (contact, newsletter, property inquiry, project booking, register validation, filters, search, 404 recovery) |
| Admin interaction audit | `npx tsx scripts/browser-admin-interactions.mts` | superadmin: 10 tabs, 0 dead clicks, 0 console errors (agency tabs covered via manager/dealer logins) |

---

## 2. Backend suites (172 tests)

| Suite | Tests | Focus |
|---|---:|---|
| `ApiVersioningTest` | 5 | `/api` vs `/api/v1` route-for-route parity |
| `AuthFlowsTest` | 7 | Reset, verification tokens, session revocation |
| `AuthSecurityTest` | 8 | 401 guards, lockout/expiry reset, token revocation, login throttle |
| `AgencyContextTest` | 6 | Membership resolution, foreign-agency 403, platform fallback |
| `BookingConcurrencyTest` | 5 | `lockForUpdate` double-booking prevention |
| `CorrelationIdTest` | 4 | `X-Request-Id` echo/rotate |
| `CrmLeadsTest` | 8 | Lead scoping, assignment, conversion, cross-agency 404 |
| `CrossAgencyCapabilitiesTest` | 3 | Platform capability parity inside an agency |
| `DocumentSharesTest` | 6 | Share token hashing, expiry, revocation, download cap |
| `EncryptionAtRestTest` | 3 | CNIC encrypted at rest + masked in responses |
| `ExpensesTest` | 3 | Expense scoping + soft delete |
| `FavoritesNotificationsTest` | 5 | Per-account favourites + notifications |
| `FinanceLifecycleTest` | 9 | 10-stage deal machine, void, ledger totals |
| `FinanceReportsTest` | 3 | Agency + platform reports |
| `LoginHistoryTest` | 5 | Sign-in trail, suspicious-IP flagging, password-change mail |
| `MfaTest` | 9 | TOTP enrolment, challenge single-use/expiry, recovery codes, disable rules |
| `MigrationSmokeTest` | 1 | Full migration set boots |
| `PlanLimitEnforcementTest` | 5 | 402 `plan_limit_reached` for listings/staff/vault/leads |
| `ProductionReadinessTest` | 2 | Preflight + config invariants |
| `PropertyLifecycleTest` | 8 | Create → submit → approve → reject, staging |
| `SecurityHeadersTest` | 4 | Security headers + strict CSP |
| `StorageSecurityTest` | 8 | Private upload/stream, MIME/size limits, media visibility |
| `SubscriptionInvoicesTest` | 4 | Immutable paid invoices, sequential numbering |
| `SupportTicketsTest` | 5 | Support desk scoping, assignment, thread ordering |
| `WorkspaceHardeningTest` | 6 | Merge-only saves, replace gating, tenancy stripping |
| `AdminStaffSubscriptionTest` | 9 | Platform user CRUD, roles, invitations, plan limits |
| `RolePermissionsTest` (baseline) | — | Legacy RBAC matrix invariants |

---

## 3. Frontend suites (126 tests)

`src/domain/*.test.ts`, `src/components/**/*.test.ts`, `src/context/AppContext.test.ts`,
`src/components/public/AuthPage.test.ts` — permissions matrix, transaction state machine,
installment maths, media policy, audit trail, lead/CRM rules, rate limiting, SEO config, MFA input
helpers (`src/domain/mfa.test.ts`), auth page behaviour, error boundary.

---

## 4. MFA journey — browser proof (10/10)

`frontend/scripts/mfa-e2e.mts` drives a real Chromium against the running app and computes the
TOTP code with an independent RFC 6238 implementation:

1. sign-in without MFA reaches the dashboard ✅
2. the enrolment control is present in **Security & Roles** ✅
3. enrolment returns a 32-character TOTP secret ✅
4. the eight one-time recovery codes are shown once ✅
5. MFA becomes **ACTIVE** after a valid confirmation code ✅
6. sign-out returns to the public portal ✅
7. the next sign-in asks for the second factor ✅
8. the prompt exposes an accessible code field ✅
9. a valid code completes the sign-in and opens the dashboard ✅
10. an invalid code is rejected with a visible error, and the factor can be removed again ✅

---

## 5. Browser audits (automated, repeatable)

| Script | Scope |
|---|---|
| `scripts/browser-audit.mts` | Passive crawl of every public route (desktop + mobile) and every admin tab: console errors, page errors, HTTP ≥ 400, dead anchors, broken images, empty pages, screenshots |
| `scripts/browser-interactions.mts` | Clicks every visible button on every public route, navigates every internal link, submits the contact/newsletter/inquiry/booking forms, validates login + register, exercises filters/search, verifies the 404 page and its recovery |
| `scripts/browser-admin-interactions.mts` | Logs in as superadmin **and** agency roles and clicks every content button of every visible admin tab, detecting dead clicks, broken dialogs and runtime errors |
| `scripts/mfa-e2e.mts` | The MFA journey above |

All four are **one-off tools**: they need a seeded dev database and are deliberately outside the
CI gates (`npm run verify` / `npm run e2e`).

---

## 6. How to re-run everything

```bash
# backend
cd backend && php artisan test

# frontend gates
cd frontend && npm run verify && npm run e2e

# browser audits (dev servers must be running: API :8090, frontend :3000)
cd frontend && npx tsx scripts/browser-audit.mts
cd frontend && npx tsx scripts/browser-interactions.mts
cd frontend && npx tsx scripts/browser-admin-interactions.mts
cd frontend && npx tsx scripts/mfa-e2e.mts
```

---

## 7. Known non-defects seen during the audits

| Observation | Why it is not a bug |
|---|---|
| `net::ERR_ABORTED` on fonts/maps/images | The audit navigates away mid-load; the requests are cancelled by the browser, not failed by the app |
| `401` on video likes for guests | Likes require an authenticated account by design |
| `NotAllowedError: clipboard writeText` | Headless Chromium denies clipboard writes; the UI falls back to a manual-copy message |
| `429 Too Many Attempts` during repeated sign-ins | The login throttle (5/min) working as designed; the UI now shows a friendly message |

---

## 8. Journey coverage (master prompt §11 — 18 journeys)

| # | Journey | Where it is proven | Status |
|---:|---|---|---|
| 1 | User registration | `AuthFlowsTest`, `AuthPage.test.ts`, public `/register` (browser-audited: empty + password-mismatch validation) | ✅ |
| 2 | Login and logout | `AuthSecurityTest`, `MfaTest`; browser: sign-in, sign-out, "logout all" | ✅ |
| 3 | Email verification | `AuthFlowsTest` (SHA-256 token, 60-min expiry) + `e2e/smoke.spec.ts` (bad-token UX) | ✅ |
| 4 | Password reset | `AuthFlowsTest` (broker token, all sessions revoked, owner notified) | ✅ |
| 5 | MFA | `MfaTest` (9) + browser journey **10/10** | ✅ |
| 6 | Agency registration | `AuthFlowsTest`, `AgencyController::register` (throttled, approval-gated) | ✅ |
| 7 | Agency approval | `WorkspaceApprovalTest`, `AdminAgencyController::verify` | ✅ |
| 8 | Agency staff invitation | `AdminStaffSubscriptionTest` (invite → accept → member) | ✅ |
| 9 | Role assignment | `AdminStaffSubscriptionTest` (agency roles CRUD) | ✅ |
| 10 | Permission enforcement | `AgencyContextTest`, `WorkspaceHardeningTest`, `CrossAgencyCapabilitiesTest` (deny-by-default) | ✅ |
| 11 | Property creation | `PropertyLifecycleTest` (create → staged `pending_changes`) | ✅ |
| 12 | Property approval | `PropertyLifecycleTest` (approve/reject, self-approve 403) + moderation queue | ✅ |
| 13 | Public property browsing | `e2e/smoke.spec.ts`, `browser-interactions.mts` (filters, search, detail pages) | ✅ |
| 14 | Customer inquiry | `CrmLeadsTest` (server-resolved agency attribution) + browser form submission | ✅ |
| 15 | Lead assignment | `CrmLeadsTest` (`crm.assign_lead`, outsider 422) | ✅ |
| 16 | Property booking + concurrency | `BookingConcurrencyTest` (`lockForUpdate`, duplicate booking blocked) | ✅ |
| 17 | Transaction + commission | `FinanceLifecycleTest` (10-stage machine, voids, ledger totals) | ✅ |
| 18 | Private document access + cross-tenant attack | `StorageSecurityTest`, `DocumentSharesTest`, `EncryptionAtRestTest` (guest 401, foreign agency 404, public URL never exposed) | ✅ |

Security-specific checks beyond the journeys: SQL-injection-safe search escaping (`escapeLike`),
IDOR (404 on foreign ids), CSRF (token API, stateless Sanctum), XSS (`strip_tags` on user content),
privilege escalation (agency-scope resolution + `requirePermission`), rate limiting (login 5/min,
register 3/min, share 30/min, subscribers 10/min), sensitive-data leakage (masked CNIC, bundle leak
guard), invalid/expired tokens (401/410 paths asserted).

---

## 9. Performance snapshot (measured 2026-11-13, dev stack)

API (5 samples, `php artisan serve` — a production php-fpm pool is faster):

| Endpoint | avg | min | max |
|---|---:|---:|---:|
| `GET /api/health` | 113 ms | 68 ms | 249 ms |
| `GET /api/catalog` | 97 ms | 80 ms | 121 ms |
| `GET /api/properties` (public) | 85 ms | 74 ms | 113 ms |
| `GET /api/locations` | 91 ms | 83 ms | 119 ms |
| `POST /api/subscribers` | 154 ms | 125 ms | 188 ms |

Frontend (Chromium, cold cache per page, dev server with unbundled modules):

| Route | load | DOMContentLoaded |
|---|---:|---:|
| `/` | 1341 ms | 1327 ms |
| `/properties` | 996 ms | 986 ms |
| `/projects` | 1291 ms | 1279 ms |
| `/contact` | 1636 ms | 1546 ms |
| `/login` | 1103 ms | 1093 ms |

Production bundle: `index.js` **429.6 KB** (≈135 KB gzip), `index.css` **87.6 KB** — guarded by
`scripts/check-bundle.mjs` (no private ERP fields, no demo credentials in the public chunks).

Reproduce with `npx tsx scripts/perf-snapshot.mts` (API + frontend must be running).

# Changelog

## 2.4.0 - Ultimate enterprise browser QA: public catalog fix, role & workflow proofs

### Fixed
- **N-01 (High) — zero listings sitewide**: `GET /api/catalog` returned `"properties"` as a
  JSON *object* (keyed by leftover indexes) whenever a legacy row was skipped
  (draft/private/payload-deleted), because `array_filter` preserves keys. The React boot
  (`Array.isArray`) then dropped the collection entirely — featured sections, `/properties`,
  hero search and detail deep-links rendered empty. Fixed in `WorkspaceController::catalog()`
  with `array_values()` reindexing (comment G-12). Regression:
  `backend/tests/Feature/CatalogShapeTest.php` (2 tests) + live browser probe (16 listing
  cards render; type filter 16→1→16; keyword search → 3).
- **N-02 (Low) — seed inconsistency**: the demo agency was seeded with 4 staff on the
  Free-Trial plan (staff limit 2), so every staff invite returned 402 out of the box.
  Upgraded via the real admin API (`PUT /admin/agencies/agency-demo/subscription`,
  Enterprise plan); no data hand-edits.

### Added - QA tooling (drive the real API/product flows)
- **`frontend/scripts/role-matrix-e2e.mts`** — staff lifecycle (invite → accept → matrix →
  revoke → token dead) + a 9-persona authorisation matrix against the live API: **51/51 PASS**.
- **`frontend/scripts/workflows-e2e.mts`** — four complete business workflows
  (buyer journey with the onboarding gate, property moderation lifecycle, deal → sell →
  commission → accountant payment, document share → revoke → 410): **28/28 PASS**.

### Verified
- Backend **182 passed / 1684 assertions** (incl. the new catalog regression tests);
  frontend **126 tests**; `tsc` clean; public crawl **41 routes clean** (0 console/page
  errors, 0 failed requests, 0 dead anchors, 0 broken images after the fix);
  public interactivity **247 click probes, 0 dead / 0 error**.

## 2.3.0 - Production deployment readiness & independent verification

### Added - verification tooling (all read-only / non-destructive by default)
- **`frontend/scripts/security-probe.mts`** - live multi-tenant attack suite: creates two real agencies through the public API, approves them and attacks tenant A from tenant B (foreign id, spoofed `X-Agency-Id`, client-supplied `agency_id`, cross-tenant vault download, CNIC exposure, admin surface, unauthenticated access, login throttle, sign-in trail). **21/21 probes pass.**
- **`frontend/scripts/legacy-visibility-probe.mts`** - measures which records two tenants can both see (proves the legacy JSON store is shared).
- **`frontend/scripts/concurrency-booking.mts`** - fires N parallel sells of one dealer file; **exactly 1 of 8 succeeds**, the rest are rejected by the `lockForUpdate()` guard.
- **`frontend/scripts/load-test.mts`** - latency/throughput harness with percentile reporting and a parallel-booking phase.
- **`frontend/scripts/mail-sink.mts`** + **`frontend/scripts/mail-flow-e2e.mts`** - a local SMTP sink (test harness only, never shipped in the app) plus the complete reset-by-mail flow (**6/6**: register -> request -> delivered -> token used -> rotated -> old password rejected).
- **`frontend/scripts/perf-snapshot.mts`** - API + page-load timings.
- **`scripts/backup.sh`** / **`scripts/backup.ps1`** / **`scripts/restore-drill.ps1`** - automated database + private-vault backup with a SHA-256 manifest and a restore drill into a scratch copy. **Drill: PASS** (restored copy identical to live across 11 tables + 53-table count, vault 13 entries).
- **Artisan commands**: `platform:inventory` (tables/RBAC/routes), `platform:legacy-audit` (per-collection tenancy picture, derivable owners, migration candidates), `platform:legacy-backfill` (dry run by default, additive, refuses `--apply` without a verified backup).

### Fixed
- **`FRONTEND_URL` pointed at a stale port** (`http://127.0.0.1:3001`) while the dev server runs on **3000** - every password-reset / verification mail carried a dead link. Fixed and verified in a delivered mail.
- **Content-Security-Policy** added to both nginx references (frontend `script-src 'self'` - the inline service-worker script moved into the bundle so no `unsafe-inline` is needed - and API `default-src 'none'`).

### Documented (evidence, no code change)
- Legacy `business_records`: 103 rows, **95 without any tenant owner**, 6 with a bogus `agency_id` (a deal id stored in the agency field); a two-tenant probe shows **every legacy row is returned to every tenant**. Options, mapping and the safe backfill path are in `REMAINING_BLOCKERS.md` B-01.
- **API rate limiting** (60/min per IP) rejected 301 of 428 requests in a 15 s load run - production needs a re-profiled limiter (B-02).
- Queue workers do **not** reload `.env` (only `artisan serve` does) - restart the worker after an environment change (B-03).
- Global "MFA required for platform admins" is **not enforced** - it needs a business decision (B-04).
- Malware scanning and SMS/WhatsApp drivers are **not implemented** (no scanner / no provider credentials) - options and provider contracts documented instead of a fake integration (B-05, B-06).

### Verified
- Backend **176 passed / 1613 assertions**; frontend **126 tests** + `tsc` clean + build/prerender + bundle guard OK; Playwright **5/5**; MFA browser journey **10/10**; security probes **21/21**; mail flow **6/6**; concurrent double-sell **1 of 8**; load run **428 requests / 0 × 5xx**; production preflight **20 pass / 1 warn / 0 fail**; restore drill **PASS**.
- New deliverables: `FINAL_PRODUCTION_AUDIT.md`, `PRODUCTION_DEPLOYMENT_CHECKLIST.md`, `SECURITY_VERIFICATION_REPORT.md`, `BACKUP_RESTORE_TEST_REPORT.md`, `LOAD_TEST_REPORT.md`, `REMAINING_BLOCKERS.md`.

## 2.2.0 - Browser-verified audit pass: MFA, 404, deterministic APIs, CSP (Phase 6/9/12/13)

### Added - real multi-factor authentication (Phase 6)
- **TOTP second factor** implemented in-house (RFC 6238 / RFC 4226, HMAC-SHA1, no new dependency): `App\Support\MfaService` (secret generation, `otpauth://` URI, drift-tolerant verification, single-use recovery codes) plus migration `2026_11_13_000004_add_mfa_and_login_history`.
- **No token before the second factor**: `POST /api/auth/login` answers `{ mfa_required: true, challenge, expires_in }` and issues **no** Sanctum token; `POST /api/auth/mfa/verify` (challenge + code, 5 attempts, 5-minute TTL, single-use) issues it.
- **Enrolment / removal**: `GET|POST /api/auth/mfa/status|setup|enable|disable` - activation needs a live code, removal needs the password **and** a code. Secrets are encrypted at rest (`encrypted` / `encrypted:array` casts) and recovery codes are stored hashed.
- **Login history + suspicious-login monitoring**: every attempt is appended to `login_histories` (IP, device, outcome, MFA usage); a first-time IP or device raises an in-app + mail notification and an audit row.
- **Password-change notifications**: queued mail + in-app notification on self-service reset and on platform-admin password resets (all sessions revoked at the same time).
- **Frontend**: `MfaChallengeForm` (shared by the admin modal and the public auth portal), `AdminMfaSettings` inside **Security & Roles** (enrol, recovery codes, disable, sign-in trail), `api.loginFlow/verifyMfa/mfaStatus/mfaSetup/mfaEnable/mfaDisable/loginHistory`, `src/domain/mfa.ts` + unit tests.

### Added - documentation set (Phase 13)
- `RBAC_MATRIX.md` (62 permissions / 13 roles / 362 grants, generated from the live database), `DATABASE_ERD.md` (53 tables + tenancy model), `API_DOCS.md`, `DEPLOYMENT_GUIDE.md`, `BACKUP_GUIDE.md`, `TEST_RESULTS.md`, `PRODUCTION_READINESS.md`; `CODEBASE_AUDIT.md` and `SECURITY_AUDIT.md` updated with this pass.

### Fixed
- **Soft 404**: unknown public URLs silently rendered the homepage. They now render a dedicated 404 screen (`NotFoundPage`), are marked `noindex,follow`, and the recovery path ("Back to Home") works - the underlying bug was a stale view after `pushState`, fixed with a derived view key plus a history re-render hook.
- **Non-deterministic list ordering**: `latest()` ordered by `created_at` only (second precision) and the tiebreaker was a random string id - rows could swap between requests and pages could repeat/skip rows. All list endpoints now use `orderByDesc('created_at')->orderBy('id')` (support tickets, properties, favourites, finance, inquiries, projects, transactions, dealers, dealer files, videos, agencies).
- **Flaky support-desk test**: the assertion depended on which of two same-second tickets landed on index 0; it is now an order-independent exact set-equality assertion (stronger, not weaker) and the endpoint itself is deterministic.
- **Admin sidebar overlap**: the pinned footer covered the lower navigation items - the nav is now a proper flex scroll region.
- **Native `window.confirm`** in the enterprise admin modules replaced with the accessible gold confirmation dialog (document vault, media studio, staff manager).
- **Clipboard failures** in the media library now fall back to a legacy copy with user feedback instead of an unhandled rejection.
- **Login throttle message**: 429 now reads "Too many sign-in attempts. Please wait a minute and try again."
- **Content Security Policy**: strict CSP on the API (`default-src 'none'`, `script-src 'none'`, `frame-ancestors 'none'`) and on the deployed frontend (nginx) - the inline service-worker script moved into the bundle so `script-src 'self'` is enough.

### Verified
- Backend **176 passed / 1613 assertions**; frontend **126 tests** + `tsc` clean + production build + prerender (29 HTML files, 9 sitemap URLs) + bundle guard OK; Playwright **5/5**; MFA browser journey **10/10**; public interaction audit 240 buttons with 0 dead clicks; admin interaction audit (superadmin + agency roles) with 0 dead clicks and 0 console errors.

## 2.1.0 - Enterprise SaaS transformation (G-gap register: 18 of 20 closed, 1 attempted, 1 partially open)

### Added — Platform modules (G-7/G-10)
- **Document share links**: `GET/POST /agency/documents/{id}/shares`, `DELETE …/shares/{shareId}` and the public capability URL `GET /api/shares/{token}/download` — SHA-256 hash-only token storage (plain token returned exactly once), expiry/revocation/download-cap enforced under a row lock, mint/revoke gated by `documents.manage_permissions`, sensitive documents require `documents.view_sensitive`, public endpoint throttled 30/min and fully audit-logged.
- **Support desk (Module 15)**: self-service tickets (`/support/tickets*`) with immutable message threads, first-response metric, staff admin surface (`/admin/support-tickets`, `/admin/support-tickets/{id}/assign`) under `admin.support_tickets`; the agency link is always resolved server-side.
- **Agency expenses**: `/agency/expenses` ledger (read `finance.view`, write/delete `finance.manage_payments`), soft-delete retention for audit.
- **Subscription invoices**: admin raise/verify (`/admin/subscription-invoices*`) with sequential `INV-YYYY-NNNNNN` numbers and **immutable paid history**; agency-side read (`/agency/subscription/invoices`) under `agency.view`.

### Added — Enforcement, analytics, platform
- **Plan-limit enforcement (G-8)**: `App\Support\PlanLimits` — server-side quotas for listings, staff (active members + pending invitations), vault storage bytes and public lead intake. Violations → `402 { code: plan_limit_reached, limit, used }`; limit `0`/absent = unlimited; public-lead messages stay visitor-safe.
- **Date-filtered reports (G-12)**: `GET /agency/reports?from&to` (`finance.view_reports`) and `GET /admin/reports?from&to` (`admin.view_reports`) — deals incl. voided split, collections, commissions paid/pending, expenses by category, invoice revenue/outstanding, net. Computed from real tables; no hardcoded statistics.
- **Queue adoption (G-9)**: `VerifyEmailLink` + `ResetPasswordLink` are `ShouldQueue` (contract-guard test included); queue worker documented and running.
- **API versioning (G-1)**: `/api/v1` served from the shared `routes/api_definitions.php` body — route-for-route parity with legacy proven by test.
- **Concurrency (G-2)**: `lockForUpdate()` + status re-checks on dealer-file sell, transaction void/approve and payment recording.
- **Encryption at rest (G-3)**: `Dealer.cnic` encrypted cast + idempotent data migration; masked-only exposure.
- **Auth completeness (G-4)**: broker-backed password reset (enumeration-safe, revokes all tokens) and SHA-256 email-verification flow (60-min tokens, tracked but not login-blocking).
- **Cold-load auth pages (G-14)**: `/reset-password` and `/verify-email` render outside the SPA shell via the `App.tsx` bridge, with noindex SEO routes and prerendered shells.
- **PWA (G-17)**: `manifest.webmanifest` + service worker that caches **public assets only** (hashed bundles cache-first, network-first elsewhere, API/private vault never cached), registered only outside dev.
- **Dead code removal (G-19)**: `frontend/src/config/firebase.ts` deleted (verified unreferenced).
- **Docker + CI (G-15)**: backend/frontend Dockerfiles (vault volume outside web root), `docker-compose.yml` (mysql + api + queue + web), `.github/workflows/ci.yml` (backend suite, frontend verify chain, OpenAPI parse).
- **OpenAPI + correlation IDs (G-16)**: `docs/openapi.yaml` (route-accurate contract) and `X-Request-Id` middleware (echo sane caller ids, generate UUIDs, attach to logs) with its own test suite.
- **Playwright (G-13, attempted)**: runner + smoke E2E spec for cold-load critical flows; the full 18-journey matrix remains open work.

### Fixed
- Date-window report math on sqlite (`whereDate` instead of string `whereBetween` for date-cast columns).
- Finance deal-scoping destructuring bug found by the new report tests.

### Honest status
The API surface is enterprise-grade and fully tested (154/1506 backend, 117 frontend). Remaining open items are infrastructure choices (vault S3, Redis/Horizon, MFA/SSO, full E2E matrix) and environment provisioning — see `PRODUCTION_READINESS_CHECKLIST.md` and `CODEBASE_AUDIT.md`.

## 2.0.7 - Close the gaps the previous audit called finished

### Fixed
- Projects and media assets are soft-deleted and restorable from the audit recycle bin. Media deletion is refused while an active listing still references the file URL, including `documentUrl`.
- The media library no longer offers a Sold action that reported a local sale when the ledger API was never reached.
- Laravel writes for properties, dealers, inquiries, transactions, and videos now require a Sanctum token plus the same permission names as the frontend matrix. Inquiry lists and the transaction ledger are no longer public.
- Admin no longer receives `manage_dealers`, `manage_users`, `pay_commissions`, or `void_transactions` on the server. Login no longer creates a dealer user with the password `password`.
- API 500 responses no longer echo internal exception text. Validation errors return field messages.

### Added
- `src/domain/mediaPolicy.ts` with regression tests for the usage guard, soft-delete, and restore.
- Persisted `audit_logs` table (writes fall back to the log file if the table is not migrated yet).
- Admin module skeleton and a phone-sized audit log.
- Root `npm run verify` delegates to the frontend gates.
- PHPUnit coverage for the server permission matrix.

### Honest status
Firebase is still a flag, not an integration. The ERP dashboard still keeps its working set in the browser and mirrors public reads to MySQL when the API is up. This build is a verified demo-grade product with authenticated API writes. It is not a hosted multi-tenant production deployment.

## 2.0.6 - Deep Audit, Discriminated Union TypeScript Fixes, Strict Mode, Auth Portal & Deployment Safety

### Added
- `src/components/public/AuthPage.tsx`: Full-page luxury portal for Sign In and Dealer Registration (`/login`, `/register`, `/admin-login`) with Midnight Navy & Royal Gold aesthetic, tab switching, live password toggling, form validation, and CNIC masking.
- `src/config/appMode.test.ts`: 6 unit tests verifying `resolveModeFromEnv()` deployment safety rules (safe public fallback when unconfigured, case-insensitivity, trim, explicit demo activation).
- `registerDealerAccount` workflow in `AppContext`: validates registration payloads, generates unique dealer IDs, logs append-only registration audit trail, and authenticates new dealer sessions.

### Changed
- `tsconfig.json`: Enabled strict TypeScript mode (`"strict": true`) with zero compilation errors.
- `src/domain/permissions.ts`: Hardened `AuthzResult` discriminated union with `{ ok: true; error?: never }` to ensure standard TypeScript type narrowing across all caller scopes.
- `src/domain/transactions.ts`: Hardened `TransitionResult`, `DealValidationResult`, `ValidatePaymentResult` with `error?: never` on positive branches.
- `src/config/appMode.ts`: Refactored mode resolution into `resolveModeFromEnv()` so unconfigured environments safely default to `'public'`. Missing env vars never silently activate demo mode.
- `src/components/admin/AdminLoginModal.tsx`: Added dual tabs for Sign In and Dealer Registration.
- `src/components/common/Navbar.tsx` & `Footer.tsx`: Added direct links to the luxury Portal Sign In and Dealer Registration views.
- Test suite expanded from 53 to 59 unit tests across 9 test suites (`npm run test` passes 59/59).

### Fixed
- Fixed 4 TypeScript discriminated-union narrowing errors in `src/context/AppContext.tsx` (around lines 461, 923, 1043, 1127) without using `any` or `@ts-ignore`.
- Prevented private field names (`cnic`, `cnicMasked`) from being emitted into static public chunks, satisfying `scripts/check-bundle.mjs`.

## 2.0.5 - Accessibility & data-integrity fixes (Cline audit triage)

### Added
- `src/components/common/ConfirmDialog.tsx`: accessible confirmation dialog
  (`role="alertdialog"`, focus trap, Escape/backdrop cancel, aria-labelledby /
  aria-describedby) driven by `confirmAction()` promise API in AppContext;
  mounted in all three App branches (public, admin, unauthenticated admin).
- `restoreProject`/`restoreMediaItem`; AdminAudit recycle bin now covers six
  entity types (Sellers, Buyers, Dealers, Properties, Projects, Media).
- Regression tests: negative `formatPKRCurrency`, WhatsApp PK phone
  normalization (0300 / 0092 / bare 10-digit / implausible fallback).

### Changed
- All six `window.confirm()` delete call sites (AdminBuyers, AdminDealers,
  AdminInquiries, AdminProjects, AdminProperties, AdminSellers) converted to
  `await confirmAction(...)` — deletes are now keyboard-accessible and styled.
- `formatPKRCurrency` renders negative amounts as `-PKR …` (refunds/reversals).
- `generateWhatsAppLink` normalizes local/international PK formats to
  `wa.me/92…`; implausible numbers degrade to `wa.me/?text=…` instead of a
  broken URL.
- `recordPayment` returns `boolean`; the payment modal stays open when the
  domain gate rejects a payment (e.g. overpay without `approve_transactions`)
  so the user can correct the amount.
- Commission payout modal no longer seeds the amount with the magic number
  20000 (it already defaults to the ledger-derived payable on open; initial
  state is now 0).

### Fixed
- Projects and Media library rows were permanently destroyed on delete — now
  soft-deleted (`deletedAt`/`deletedBy`) and restorable from the recycle bin;
  public listings (`FeaturedProjects`, `ProjectsListingPage`,
  `MediaGallerySection`) filter deleted records.

## 2.0.4 - Phase 4: storage (IndexedDB) + audit log + soft delete + Firebase flag (P0-6)

### Added
- `src/storage/indexedDb.ts`: dependency-free IndexedDB wrapper (audit_log + kv
  stores) with graceful null fallback when IndexedDB is unavailable.
- `src/domain/auditLog.ts`: append-only audit log (login, role switch, void,
  payout, payment, verify, soft-delete/restore) persisted to localStorage and
  best-effort mirrored to IndexedDB; records are never mutated or deleted.
- Soft delete: Seller/Buyer/Dealer/Property/Transaction gain `deletedAt`/`deletedBy`;
  `delete*` now marks records instead of destroying them; `restore*` reverses;
  list views filter deleted records; referenced-record guards preserved.
- `AdminAudit` component + 'Audit & Recycle Bin' tab (super-admin): append-only
  log table + recycle bin with per-record Restore buttons.
- Firebase flag (Phase 8): `src/config/firebase.ts` (`readFirebaseConfig`,
  `isFirebaseConfigured`, lazy `getFirebaseApp`/`getFirebaseAuth`/`getFirebaseDb`
  that dynamically import the firebase SDK so the public bundle stays lean when
  unset); `.env.example` documents `VITE_FIREBASE_*` vars.
- `ToastContainer` wired on the unauthenticated admin view so toasts surface.

### Changed
- AdminSellers/AdminBuyers/AdminDealers/AdminProperties/AdminTransactions filter
  soft-deleted records.
- Context type + provider expose `restoreProperty`/`restoreSeller`/`restoreBuyer`/
  `restoreDealer`.

## 2.0.3 — Phase 3: transaction lifecycle + payment/commission ledgers (P1-1…P1-5)

### Added
- `src/domain/transactions.ts`: canonical state machine (10 ordered stages, terminal
  Completed/Cancelled, Completed only via Under Transfer, Cancelled requires a
  reason), ledger-backed money math (`collectedForTx`/`dealMoney` with overflow
  clipping), `impliedStatusByPayment`, commission accrual milestone
  (`shouldAccrueCommission`/`accrueCommission`), deal-form validation
  (`validateDealPayload`), payment validation (`validatePayment` with supervisor
  overpay bypass), sequential `nextDealNumber` (TXN-YYYY-NNNN), `genId`
  (crypto.randomUUID + safe fallback), `computeFinancials`.
- `src/domain/transactions.test.ts`: 21 unit tests (state machine, payment ledger,
  commission accrual, validation & ids, ledger-backed financials).
- `PaymentEntry` + `CommissionEntry` types (`src/types/index.ts`) incl. void audit
  fields on Transaction (`voided`, `voidReason`, `voidedBy`, `voidedAt`).
- `src/data/erpSeed.ts`: `initialPaymentEntries` (4 money movements) and
  `initialCommissionEntries` (Pending Accrual / Paid / Pending Accrual) plus
  `dealNumber` on every seed deal.
- AppContext: `paymentEntries`, `commissionEntries`, `recordPayment`, `voidTransaction`
  state + hydration + localStorage persistence; `addTransaction` now creates ledger
  rows (advance → PaymentEntry, dealer commission → CommissionEntry Pending Accrual)
  and uses domain validation + `nextDealNumber`/`genId`; `updateTransaction` runs
  the domain state machine, cancels unpaid accruals on Cancel and re-checks the
  accrual milestone; `payDealerCommission` now pays ACCRUED entries newest-first
  (payout[] appended), re-derives dealer aggregates from the ledger, and explains
  the accrual rule when nothing is payable; `deleteTransaction` is now a
  compatibility shim over the audited `voidTransaction`.
- AdminTransactions UI: record-payment modal (gated `manage_payments`) with live
  balance + recorded-movement history, VOID modal (super-admin, mandatory reason)
  with `VOIDED` badge + frozen payments, cancel-reason modal (intercepted status
  select), commission accrual badge column (Pending Accrual/Accrued/Partially
  Paid/Paid/Cancelled), ledger-backed Paid/Balance column, Payments button
  (view-only ledger for non-payers).
- AdminDealers: ledger-derived payout modal (Total Earned / Already Paid / Payable
  accrued), aggregates synced from the commission ledger via AppContext effect.
- AdminOverview: financials now fed from payment + commission ledgers.

### Changed
- `calculateERPFinancials` delegates to `computeFinancials` (ledger-backed money);
  legacy alias fields preserved (`totalAdvancesCollected`, `pendingReceivables`,
  `totalCommissionPayable`, `pendingDealsCount`, `totalDealsCount`).
- AdminTransactions financial cards now reflect ledger truth (ADVANCES RECEIVED etc.).
- `src/utils/logic.test.ts`: legacy ERP-financials test rewritten to ledger-backed
  semantics (47 tests total: 10 logic + 16 permissions + 21 transactions).

### Fixed
- Void replaces hard delete — the record stays with who/when/why; totals exclude
  voided deals; recorded payments freeze with a refund note.
- Cancelled deals cancel unpaid unaccrued commission entries (paid money stands).
- Commission becomes payable only at the Fully Paid/Completed milestone; Pending
  Accrual promises are never counted as payable.
- Payment overflow clipped so balances never go negative; auto-advance of stage
  only moves forward and never past Under Transfer/Completed/Cancelled.
- Seed deal numbers now sequential (`TXN-2026-0001/0002/0003`); dealer aggregates
  re-derived from the ledger instead of stale seed constants.

## 2.0.2 — Phase 2: canonical RBAC + CNIC privacy (P0-4, P0-5)

### Added
- `src/domain/permissions.ts`: canonical role ids (`super_admin | admin | manager |
  dealer | operator`), ONE granular permission matrix (30 permissions),
  `normalizeRole` (legacy spellings + unknown → least-privilege `operator`),
  `authorize()` typed gate with dealer record-scoping via
  `scope.dealerId`, and scoped-equivalents (`view_my_deals`/`view_my_commission`
  authorize dealer rows of the ledger).
- `src/domain/permissions.test.ts`: 16 unit tests — matrix boundaries (Admin
  without pay_commissions/manage_dealers/manage_users/void_transactions; manager
  without sensitive CNIC; operator uploads but never verifies; scoping rules
  incl. dealer-without-profile denial).
- `requirePermission(permission|any-of[], scope?)` in AppContext: ~20 mutating
  actions now domain-gated (properties, sellers, buyers, dealers, transactions,
  payouts, files, media, inquiries, CMS, plot markers). Dealers scope to
  `currentUser.dealerId`.
- `maskAccountNumber` + masked bank-account rendering behind
  `view_sensitive_cnic`; seller edit form prefills masked and preserves originals
  for staff without the permission.
- Verify-document modal mandating a genuine reviewer note (auto "Verified by
  Legal Officer" text removed); Verify/Reject buttons hidden for upload-only
  roles ("Awaiting verification" hint instead).
- Sidebar tabs: `altPermission` field + `hasAnyPermission`; dealers get
  Transactions (scoped), operators get File Verification (upload path).

### Changed
- Sidebar permissions corrected to canonical: Transactions → `view_transactions`,
  Inquiries → `view_inquiries`; dealer ledger scoped (badge counts own deals);
  Admin loses manage_dealers/manage_users/pay_commissions/void_transactions;
  operator role lacks deal visibility entirely.
- AdminTransactions: dealer scoping + scoped financial cards + "Scoped view" badge,
  form validation (property/seller/buyer/same-record/positive-amount/advance ≤
  amount), CNIC-free payloads, masked receipt with permission-gated reveal,
  dealer-less deals book zero commission ("— Direct deal (no dealer) —" option),
  party dropdowns show names only, handwritten badge only for the real R-66
  record, full canonical status list in the row select (Negotiation,
  Agreement Signed, Payment Pending, Partially/Fully Paid were missing and made
  seeded deals render as Draft).
- AdminProperties: private cost gated on `view_sensitive_cnic` (was role-name
  strings).
- `erpSeed.ts`: canonical auth users (empty `permissions: []` — matrix derives
  everything; usr-4 dealer has `dealerId: 'deal-3'`), fictional full CNICs for
  seed sellers/buyers/dealers (pairs with `cnicMasked` last digit), third seed
  transaction `tx-6303` (deal-3 dealer scope demo, Negotiation, PKR 1.9 Cr).
- `package.json`: `npm run test` now runs every `src/**/*.test.ts` (26 tests).

### Fixed
- CNIC "N/A" bug where only `cnicMasked` existed; masked values now come from the
  curated field; full (fictional) reveal requires `view_sensitive_cnic`.
- Manager could see full CNICs in seller edit forms; sellers' bank account
  numbers visible to all staff — both now masked/permission-gated (P0-5).
- Payout button rendered for Admin (matrix says super-admin-only) — disabled +
  context gate `pay_commissions`.

## 2.0.1 — Phase 1: APP_MODE, public/ERP data split, PIN-gated demo (P0-2, P0-3)

### Added
- `src/config/appMode.ts`: single build-time source of truth `APP_MODE`
  (`public` | `demo` | `production`, from `VITE_APP_MODE`, legacy fallback
  `VITE_DEMO_MODE`), `DEMO_PIN` (`VITE_DEMO_PIN`, default `4545`), `IS_DEMO`.
- `src/data/publicSeed.ts` (statically imported, public-safe): CMS config,
  client themes, projects, **public-safe properties**, media, testimonials,
  plot markers (fictional map labels renamed `PlotMarker.sellerName` →
  `markerLabel` to avoid any confusion with ERP seller data).
- `src/data/erpSeed.ts` (admin-only chunk, dynamically imported at auth time):
  inquiries, sellers, buyers, dealers, transactions, property files, ERP
  private property fields for `prop-r66`, `initialAuthUsers`, and
  `DEMO_ACCOUNTS` (fictional demo credentials that never ship publicly).
- `scripts/check-bundle.mjs` + `scripts/sensitive-strings.json`: fails the
  build if any chunk statically reachable from `index.html` contains private
  ERP field tokens (word-boundary, case-sensitive) or demo passwords.

### Changed
- AppContext: ERP collections hydrate via the dynamic `ensureErpLoaded()`
  engine; localStorage writes for ERP collections are gated on `erpReady` so
  an initial empty render cannot wipe stored data. Inquiries keep read/write
  (public form submissions survive reload).
- Auth: `loginWithCredentials`/`loginAdmin` are async and validate against
  `DEMO_ACCOUNTS` from the ERP chunk. `loginAsDemo(role, pin)` and
  `switchAuthUser(role, pin)` require `APP_MODE === 'demo'` plus
  `verifyPresenterPin`. Presentation mode and the current user are
  **in-memory only** (never restored from localStorage; legacy keys are
  scrubbed on boot). Anonymous placeholder user until a session starts.
- `resetToDefaultData` is now async and demo-only (hydrates ERP seeds).
- `AdminLoginModal` rewritten: presenter-PIN form gates the instant role grid
  (only rendered when `APP_MODE === 'demo'`); credential form uses the async
  login with busy state.
- `AdminSecurityRoles`: role switcher requires the presenter PIN inline.
- `AdminLayout` demo banner checks `IS_DEMO` instead of the raw env var.
- `Footer` "Launch Client Presentation Mode" renders only in demo builds.
- `App.tsx` mounts `<PresentationBar />` (demo builds only) on the admin
  dashboard and the public router, making the three client themes, device
  preview and reset action reachable.
- `toPublicProperty`/`toPublicProject` in `src/utils/logic.ts` rewritten from
  a delete-list (destructure-rest, whose literal keys leaked into the public
  bundle) to explicit **allowlist** builders — this alone is what removed
  `purchasePricePKR`, `internalNotes`, `cnic`, etc. from the public chunk.
- `AdminOverview` hard-coded "v2.0 Production ERP" badge relabelled to
  "v2.0 ERP" to avoid conflicting with build-mode semantics.
- `vite.config.ts` drops the `__dirname` fallback (Vite config-loader warning).
- `.env` migrated to `VITE_APP_MODE` / `VITE_DEMO_PIN`.

### Fixed
- P0-2: private ERP data + demo passwords no longer ship in the public
  bundle. Public closure is now: `index-*.js` (87.7 KB gzip), icon chunk
  (11.1 KB), `publicSeed-*.js` (8.6 KB). The ERP seed (4.3 KB gzip) loads
  only inside an admin session.
- P0-3 (part 1): instant demo sign-in grid requires the presenter PIN and
  exists only in demo builds; presentation mode cannot be flipped from
  localStorage (verified by tamper test in-browser); role switcher PIN-gated.

### Known issue (accepted for Phase 1)
- `ToastContainer` is not rendered on the unauthenticated admin view, so the
  "Invalid presenter PIN" toast is invisible on that screen (the PIN still
  correctly blocks the grid). Will wire the container there with Phase 2.

## Unreleased
### Security
- Removed empty/global password login; per-account demo credentials only.
- Auth flags no longer trusted from localStorage.
- Role switching restricted to demo mode.
- Expanded RBAC permission map.

### Fixed
- Commission accounting (earned vs paid vs pending).
- Financial reporting (deal volume vs collected revenue).
- Delete guards for referenced sellers/buyers/dealers.
- Build cssMinify switched to esbuild.
- Navbar navigation missing between 640-1024px, link overflow at 1024-1200px, and drawer a11y (Escape, scroll-lock, backdrop, aria) — rewritten: inline nav only at xl, drawer below xl, constant header height per breakpoint.

### Added
- Public property serializer (toPublicProperty).
- userHasPermission helper.
- Documentation: AUDIT_REPORT, BUG_FIX_REPORT, SECURITY_AUDIT, DATABASE_SCHEMA, FIREBASE_SETUP, DEPLOYMENT, TEST_REPORT.

## Principal Audit Pass
- Implemented sidebar permission filtering, transaction lifecycle guards, plot locator badge, expanded code splitting, demo banner, media validation, field visibility, and public data separation.

## Premium UI/UX Pass (Phase 1-6)

### Visual & Motion
- Fixed silently missing CSS keyframes (animate-fadeIn / slideIn / subtleZoom) and added full premium motion system with prefers-reduced-motion support.
- Hero: word-by-word staggered headline, rAF parallax background, scroll-fade content, trust badge row (avatars + 4.9 rating), scroll-down indicator, LCP image fetchPriority=high.
- Animated count-up statistics (hero stats strip) via IntersectionObserver, no runtime deps.
- Page transitions (CSS page-enter keyed by view), scroll-aware navbar (densify on scroll + animated active underline), mobile drawer with backdrop blur.
- Toast: slide-in + auto-dismiss progress bar (synced to 4.5s timer) + type icons + aria-live.

### Components Added
- Lightbox (keyboard nav, video slot, thumbnail strip) wired into PropertyDetailPage + ProjectDetailPage.
- EmptyState standard empty states: property search, projects listing (public + back to search), admin inquiries, admin transactions.
- FavoriteHeart wishlist toggle persisted in localStorage (taisartownhub_v2_favorites) on property cards.
- Reveal scroll-triggered reveal wrappers across Featured properties/projects grids, Services cards, About core-values/team.

### Cards
- Property cards: per-sq-yd price reference, Hot Deal / Sold status badges, lazy+async images, wishlist heart.
- Project cards: h-full reveal wrappers, "View Project" hover CTA.

### Admin
- Overview KPI: quarter trend chip. Transactions & Inquiries get premium empty states.
- Admin overview verified rendered with DEMO MODE banner.

### Performance & A11y & SEO
- Removed need for 'motion' runtime; all animations CSS/IO-based. Main bundle 89.98KB gzip (was 106KB), total ~103KB under 800KB budget.
- Skip-to-content link, focus-visible rings, aria labels on icon buttons, aria-live toasts.
- Per-view document title + meta description (App.tsx), canonical/OG/Twitter tags + JSON-LD RealEstateAgent schema (index.html).
- All listing/detail images lazily loaded + decoding=async.

### Verified
- lint: 0 errors; tests: 10/10; build success; browser smoke test (home, properties, property detail lightbox keyboard nav, projects, admin login+overview) with 0 console errors.
- Navbar verified at 375 / 768 / 1180 / 1440 px (multi-iframe responsive probe): inline nav only >=1280px, drawer + backdrop + Escape close + body scroll-lock below xl, active-link aria-current, constant header height 64/80px, admin icon-only vs labeled states, zero console errors.

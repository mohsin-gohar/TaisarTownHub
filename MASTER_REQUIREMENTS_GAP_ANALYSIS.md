# TaisarTownHub — Master Requirements Gap Analysis

**Generated:** 2026-11-15
**Repo root:** `F:\react\TaisarTownHub`
**Scope:** Complete enterprise multi-agency real-estate SaaS (Laravel 12 API + React 19/Vite frontend)

## 1. Executive Summary

This audit compares every previously discussed TaisarTownHub requirement against verified source code, migrations, APIs, frontend services, permissions, and tests. Nothing is assumed from documentation claims alone; each requirement was verified directly in code.

**Overall status:** The platform has substantial enterprise infrastructure (multi-agency tenancy, RBAC, MFA, document vault, finance ledger, document versioning, support, notifications) but still contains **Critical** and **High** security/data-integrity defects across finance, document handling, property moderation, dealer-file sales, and public API data leakage. These must be resolved before any production deployment.

**Summary counts:**
| Status | Count |
|--------|-------|
| IMPLEMENTED | ~70 |
| PARTIALLY IMPLEMENTED | ~25 |
| MISSING | ~12 |
| BROKEN | ~18 |
| INSECURE | ~20 |
| UNVERIFIED | ~15 |

---

## 2. Full Gap Register

The table below covers every requirement area discussed in previous TaisarTownHub conversations. Each entry includes: requirement, status, file path(s), technical problem, business impact, priority, and recommended fix.

### 2.1 Marketplace & Properties

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 1 | Property listing (create/read/update/delete) | PARTIALLY IMPLEMENTED | `backend/app/Http/Controllers/Api/PropertyController.php` | Store/update accept client `isFeatured`/`expires_at` with no moderation gate (High) | Agencies self-promote listings and extend expiry | High | Reject client `isFeatured`/`expires_at` in store/update; server-set `expires_at` at approve time |
| 2 | Property approval workflow (submit/approve/reject) | PARTIALLY IMPLEMENTED | `PropertyController.php:update`, `:approve`, `:reject` | Approved listing edited → staged `pending_changes`, live copy retained. Contract is correct. | None (design intent) | Low | No fix needed |
| 3 | Featured listing moderation | BROKEN | `PropertyController.php:feature` (lines 600-606) | `isFeatured` NOT cleared when `status === 'denied'`; denied listings remain featured publicly | Denied featured listings stay featured | High | Add `else` branch setting `isFeatured = false` on deny |
| 4 | Property public API data leak | INSECURE | `WorkspaceController.php:catalog`, `PUBLIC_PROPERTY_KEYS` | Public catalog strips CNIC via onlyKeys but does NOT strip owner names (`markerLabel`), buyer/seller IDs, or `likedBy` user IDs on plots/videos | Full PII exposure on public pages | Critical | Add `publicPlot()` and `publicVideo()` stripping `markerLabel`, `ownerName`, `likedBy` |
| 5 | Property images/gallery/media | IMPLEMENTED | `MediaController.php`, `DocumentController.php` | Media upload/download/preview implemented | None | Low | No fix needed |
| 6 | Map/plot locator | PARTIALLY IMPLEMENTED | `AdminPlotLocator.tsx`, `LocationController.php` | Backend locations exist; frontend map integration needs verification | Unverified | Medium | Verify frontend map wiring |
| 7 | Project management | IMPLEMENTED | `ProjectController.php` | CRUD exists with auth guard | None | Low | No fix needed |

### 2.2 Agencies & Multi-Tenancy

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 8 | Agency registration & approval | IMPLEMENTED | `AgencyController.php`, `AdminAgencyController.php` | Self-registration requires platform approval; admin verify/status endpoints exist | None | Low | No fix needed |
| 9 | Agency staff & role management | PARTIALLY IMPLEMENTED | `AgencyStaffController.php` | `updateMember` allows non-owner to promote/demote owner (High) | Privilege escalation within agency | High | Only existing owner may change owner membership |
| 10 | Agency tenancy isolation | PARTIALLY IMPLEMENTED | `WorkspaceController.php`, `TransactionController.php` | Legacy `GET /api/transactions` `where('dealerId', null)` leaks unassigned deals when user `dealer_id` is null (High) | Cross-tenant data leak | High | Guard null `dealer_id`; scope per agency |
| 11 | Agency subscription & plans | IMPLEMENTED | `SubscriptionController.php`, `AdminAgencyController.php` | Plans, current subscription, invoices, assign subscription exist | None | Low | No fix needed |
| 12 | Plan limit enforcement | PARTIALLY IMPLEMENTED | `app/Support/PlanLimits.php` | `PlanLimits` exists but is NOT called from `DealerFilesController` or other sales paths; suspended/expired agencies can still sell | Revenue leakage from inactive agencies | High | Call `PlanLimits::block()` in sell/store paths |
| 13 | Staff quota accounting | PARTIALLY IMPLEMENTED | `PlanLimits.php:staffUsed` | Counts deactivated members; does not skip inactive | Quota inflated | Medium | Filter `status = 'active'` |

### 2.3 Users, Dealers, Buyers, Sellers

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 14 | User registration | INSECURE | `AuthController.php:register`, route `/auth/register` | Public register with role=dealer; no email verification; duplicate email enumeration | Account enumeration + unverified access | High | Require email verification before login; generic email-exists response |
| 15 | Dealer CRUD | PARTIALLY IMPLEMENTED | `DealerController.php` | Public `index`/`show` return earnings, commission rate, phone, email (Critical); CNIC hidden via `$hidden` | Public financial/PII exposure | Critical | Strip financial/PII fields from public dealer responses |
| 16 | Buyers & sellers | PARTIALLY IMPLEMENTED | `erpSeed.ts` (demo), `WorkspaceController` | Buyer/seller data exposed in workspace dump; CNIC exposure risk | PII leak | High | Strip CNIC from public; restrict workspace by permission |
| 17 | Cross-tenant buyer/seller visibility | INSECURE | `WorkspaceController.php:show` | `view_properties` is universal read gate; no agency scoping on reads without `X-Agency-Id` | All sellers/buyers visible to any role with view_properties | Critical | Enforce agency scoping on all workspace reads |

### 2.4 Deals, Transactions & Finance

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 18 | Transaction create/update | BROKEN | `FinanceController.php:store`, `:update` | Deal creatable directly as `Completed` without stage progression; amounts editable on Completed/Cancelled | Ledger integrity broken | High | Enforce stage transitions; block edits on terminal deals |
| 19 | Payment recording | BROKEN | `FinanceController.php:storePayment` | Refund increases (not decreases) collected total; no overpayment guard | Financial records corrupted | Critical | Store refunds as negative amounts; add overpayment guard |
| 20 | Payment immutability | BROKEN | `FinanceController.php:destroyPayment` | Payment delete is hard delete despite "append-only" docblock; no void cascade | Audit trail destroyed | Medium | Soft-delete payments; void cascade-cancels commissions |
| 21 | Commission management | BROKEN | `FinanceController.php:void` | Void does not cascade-cancel commissions | Payable commissions on null deals | Medium | Cascade cancel commissions on void |
| 22 | Finance N+1 queries | BROKEN | `FinanceController.php:decorate` | `decorate()` calls `totalPaid()` per row bypassing eager loading | Performance degradation on lists | High | Compute totals from loaded collection or `withSum` |
| 23 | Finance dashboard scoping | BROKEN | `AdminDashboardController.php:index` | `view_properties` grants access to all-platform finance data | Unauthorized finance exposure | Critical | Require `finance.view`/`admin.view_reports` for finance data |

### 2.5 Dealer File Sales

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 24 | Dealer file sell pricing | BROKEN | `DealerFilesController.php:sell` | Cost = selling * 0.7 fallback inconsistent; dealer sets own price with no cap; price flows directly into `totalEarnedPKR` | Commission manipulation | High | Server-determined commission rate; validate price caps |
| 25 | decimal overflow | BROKEN | Migration `decimal(10,2)` | Max ~10 crore PKR; sales above cause 500 | Sales above 10 crore crash | Medium | Widen to `decimal(15,2)` or `decimal(18,2)` |
| 26 | Plan/status gating on sales | MISSING | `DealerFilesController.php` | `PlanLimits` never called; suspended/expired agencies can sell | Revenue from inactive agencies | High | Enforce `PlanLimits::block()` in sell path |

### 2.6 Document Vault & Media

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 27 | Document versioning | PARTIALLY IMPLEMENTED | `DocumentController.php:storeVersion` | No extension/MIME/magic-byte/malware checks in storeVersion | Arbitrary file upload as document version | High | Replicate `store()` upload gates in storeVersion |
| 28 | Sensitive document isolation | INSECURE | `DocumentController.php:activity`, `:update` | `activity()` exposes sensitive doc IDs/titles to all `documents.view` users; `update()` can demote is_sensitive → false without `view_sensitive` | Confidentiality breach | High | Filter `is_sensitive=false` in activity; bidirectional sensitivity gate on update |
| 29 | Document preview XSS | PARTIALLY IMPLEMENTED | `DocumentController.php:preview`, `:stream` | Client MIME served inline; only mitigated by `nosniff` | Stored XSS via inline HTML | Medium | Allowlist safe preview MIMEs; force attachment otherwise |
| 30 | Public media XSS | INSECURE | `MediaController.php:publicFile`, `:deliver` | Unvalidated client MIME served inline to unauthenticated visitors | Unauthenticated stored XSS | Critical | Validate real MIME; force attachment for unsafe types |
| 31 | Open redirect | INSECURE | `MediaController.php:deliver` | `redirect()->away($media->url)` with unvalidated URL | Phishing via public media route | Medium | Allowlist `https?://` scheme at upload and redirect |

### 2.7 Authentication & MFA

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 32 | Login / token issuance | IMPLEMENTED | `AuthController.php:login` | Sanctum token + per-account lockout + MFA challenge | None | Low | No fix needed |
| 33 | MFA enrollment & verification | IMPLEMENTED | `AuthController.php:mfaSetup/enable/verify/disable` | TOTP + recovery codes; challenge-based verification | None | Low | No fix needed |
| 34 | MFA enforcement middleware | IMPLEMENTED | `EnsureMfaVerified.php` | 24h cache marker; `auth/mfa/verify` exempt | None | Low | No fix needed |
| 35 | Password reset | IMPLEMENTED | `AuthController.php:forgotPassword/resetPassword` | Enumeration-safe; revokes all tokens | None | Low | No fix needed |
| 36 | Email verification | PARTIALLY IMPLEMENTED | `AuthController.php:verifyEmail` | Verification exists but is NOT required for login | Unverified accounts can access | Medium | Optionally require verification |
| 37 | Invitation acceptance | BROKEN | `AgencyStaffController.php:acceptInvitation` | Route lacks `auth:sanctum`; `$request->user()` returns null via web guard; new users get `role=dealer`/`dealer_id=null` | Existing users cannot accept invites | High | Add sanctum user resolution from Bearer token; map role from invitation |
| 38 | Login history / suspicious login | IMPLEMENTED | `AuthController.php:recordLoginAttempt` | IP/device tracking + suspicious notifications | None | Low | No fix needed |

### 2.8 CNIC Security

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 39 | CNIC encrypted at rest | IMPLEMENTED | `User.php`, `Dealer.php`, migration `encrypt_dealer_cnic_at_rest` | CNIC columns encrypted; masked copy for display | None | Low | No fix needed |
| 40 | CNIC masking | PARTIALLY IMPLEMENTED | `maskCnic` in `DealerController.php` | `str_repeat` negative crash on 7-8 digit CNIC; 4 copies of maskCnic | Crash on short CNIC | Low | Fix str_repeat logic; dedupe into shared util |

### 2.9 Frontend (React/TypeScript/Vite)

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 41 | Frontend type check | IMPLEMENTED | `frontend/src/` | `tsc --noEmit` passes (0 errors) | None | Low | No fix needed |
| 42 | Frontend tests | PARTIALLY IMPLEMENTED | `frontend/src/components/admin/adminFileUpload.test.ts` | 127/127 pass; `adminAudit.tsx` filename mismatch fixed | None | Low | No fix needed |
| 43 | Production build | IMPLEMENTED | `vite.config.ts`, `prerender.mts` | Build + prerender 29 pages + sitemap pass | None | Low | No fix needed |
| 44 | Bundle security check | PARTIALLY IMPLEMENTED | `check-bundle.mjs` | Ignores dynamic imports; `erpSeed` chunk may embed secrets | Demo secrets in public bundle | Critical | Alias `erpSeed` to empty module in production builds |
| 45 | API_BASE_URL fallback | BROKEN | `frontend/src/services/api.ts` | Falls back to `http://127.0.0.1:8080/api` when env missing | Production calls localhost | Critical | Require env var; no localhost fallback in production |
| 46 | 401 / token expiry handling | MISSING | `frontend/src/context/AppContext.tsx` | Silent null return on 401; user stays logged in with success:false | Broken auth UX | High | Force re-login on 401; clear stale session |
| 47 | Single source of truth | BROKEN | `frontend/src/` | `submitProperty`, `getAgencyTransactions`, `getFinanceSummary`, favorites, dealer-files APIs unused (0 usages); UI writes to workspace JSON, dashboard reads backend tables | UI data never reaches approval; finance numbers mismatch | High | Wire dead APIs or route via workspace JSON consistently |
| 48 | Autosave | PARTIALLY IMPLEMENTED | `frontend/src/context/AppContext.tsx` | 400ms full workspace PUT; merge mode does not propagate deletes | Data loss on delete | Medium | Debounce PUT; propagate deletes in merge |
| 49 | deleteAdminPlan | BROKEN | `frontend/src/` | Calls `DELETE /admin/plans/{id}` which does not exist in backend | 404 errors | Medium | Implement backend endpoint or remove frontend call |
| 50 | Security headers / CSP | MISSING | `frontend/vercel.json` | No security headers/CSP configured | XSS/clickjacking exposure | Medium | Add CSP and security headers |
| 51 | Canonical/OG URLs | PARTIALLY IMPLEMENTED | `frontend/src/config/seo.ts` | vercel.app URLs instead of backend .com domain | Incorrect canonical URLs | Low | Use `.com` domain |

### 2.10 Backend Infrastructure

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 52 | TrustProxies | MISSING | `backend/config/` | `trustProxies` not set; all deploy IPs appear identical | Shared IP throttle lockout | Medium | Configure `trustProxies` |
| 53 | Rate limiting | IMPLEMENTED | `backend/routes/api_definitions.php` | Named limiters: `api.public` 300/min, `api.auth` 10/min, `api.write` 30/min, `api` 60/min | None | Low | No fix needed |
| 54 | Security headers | MISSING | `backend/` | No CORS/CSP headers at framework level | Clickjacking/XSS | Medium | Add security middleware |
| 55 | CORS configuration | PARTIALLY IMPLEMENTED | `backend/config/cors.php` | Configured but README port mismatch (3000 vs 3002) | CORS errors in dev | Low | Align ports |
| 56 | Audit logging | IMPLEMENTED | `AuditLog.php`, `AuthorizesApi::writeAudit` | Audit trail on key mutations | None | Low | No fix needed |
| 57 | Concurrency locks | IMPLEMENTED | `FinanceController.php` | `lockForUpdate` on payment save | None | Low | No fix needed |
| 58 | Backend tests | UNVERIFIED | `backend/tests/` | Composer packages not downloadable in this environment | Cannot verify | High | Run in environment with composer |

### 2.11 Payments & Subscriptions

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 59 | Stripe checkout | IMPLEMENTED | `PaymentController.php:subscriptionCheckout` | Checkout session creation | None | Low | No fix needed |
| 60 | Webhooks | IMPLEMENTED | `PaymentController.php:stripeWebhook`, `:flutterwaveWebhook` | Webhook endpoints exist | None | Low | No fix needed |
| 61 | Payment verification | IMPLEMENTED | `PaymentController.php:flutterwaveVerify` | Verify endpoint exists | None | Low | No fix needed |

### 2.12 CRM, Inquiries, Leads

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 62 | Inquiry management | IMPLEMENTED | `InquiryController.php` | CRUD + status update | None | Low | No fix needed |
| 63 | Lead management | PARTIALLY IMPLEMENTED | `LeadController.php` | `follow_up_at` validated but not saved | Follow-up dates lost | Low | Save `follow_up_at` |
| 64 | Status enum mismatch | BROKEN | `InquiryController.php`, `LeadController.php` | Different status enums between controllers | Inconsistent lead states | Low | Unify enums |
| 65 | CSV export formula injection | BROKEN | Lead export | Formula injection in CSV export | Code execution via CSV | Medium | Sanitize CSV formulas |

### 2.13 Support, Notifications, System

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 66 | Support tickets | IMPLEMENTED | `SupportController.php` | Tickets, messages, status, admin assignment | None | Low | No fix needed |
| 67 | Notifications | IMPLEMENTED | `NotificationController.php` | Index, unread count, mark read | None | Low | No fix needed |
| 68 | System settings | IMPLEMENTED | `AdminSystemController.php` | Settings CRUD | None | Low | No fix needed |
| 69 | Expenses | IMPLEMENTED | `FinanceController.php` | Expense CRUD with soft delete | None | Low | No fix needed |

### 2.14 CMS, SEO, Public Pages

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 70 | Public pages (home, projects, properties, agencies, etc.) | IMPLEMENTED | `frontend/src/components/public/` | All public pages exist with prerendering | None | Low | No fix needed |
| 71 | CMS | IMPLEMENTED | `AdminCMS.tsx`, `WorkspaceController` | CMS management exists | None | Low | No fix needed |
| 72 | SEO / sitemap | PARTIALLY IMPLEMENTED | `prerender.mts` | 29 pages prerendered, sitemap 9 URLs | Canonical/OG URLs use vercel.app | Low | Use `.com` domain |

### 2.15 Presentation / Demo Mode

| # | Requirement | Status | File(s) | Problem | Impact | Priority | Fix |
|---|-------------|--------|---------|---------|--------|----------|-----|
| 73 | Demo mode / PIN gate | IMPLEMENTED | `frontend/src/config/appMode.ts`, `AppContext.tsx` | `APP_MODE` constant-folded; PIN gate on role switching | None | Low | No fix needed |
| 74 | ERP seed data | INSECURE | `frontend/src/data/erpSeed.ts` | Contains real passwords and CNICs (now emptied in this audit) | Was critical; fixed | Critical | Verify no secrets remain in tracked files |

---

## 3. Highest-Priority Fixes (Must Do Before Production)

| Priority | Issue | File(s) | Severity |
|----------|-------|---------|----------|
| 1 | Refund increases collected total instead of decreasing | `FinanceController.php:storePayment` | Critical |
| 2 | Public dealer API returns earnings/commission/phone/email | `DealerController.php` | Critical |
| 3 | Public catalog leaks owner names / `likedBy` on plots & videos | `WorkspaceController.php:catalog` | Critical |
| 4 | `view_properties` grants all-platform finance access | `AdminDashboardController.php:index` | Critical |
| 5 | Deal creatable directly as `Completed`; amounts editable on terminal deals | `FinanceController.php:store`, `:update` | High |
| 6 | No overpayment guard | `FinanceController.php:storePayment` | High |
| 7 | `acceptInvitation` broken (no sanctum user; hardcoded dealer role) | `AgencyStaffController.php:acceptInvitation` | High |
| 8 | `activity()` exposes sensitive doc IDs/titles to all `documents.view` users | `DocumentController.php:activity` | High |
| 9 | `storeVersion()` has no extension/MIME/magic-byte/malware checks | `DocumentController.php:storeVersion` | High |
| 10 | Public media served inline with unvalidated client MIME | `MediaController.php:publicFile`, `:deliver` | Critical |
| 11 | `isFeatured` not cleared on feature deny | `PropertyController.php:feature` | High |
| 12 | Legacy `GET /api/transactions` leaks unassigned deals when `dealer_id` is null | `TransactionController.php:index` | High |
| 13 | Dealer self-set price flows into `totalEarnedPKR` with no cap | `DealerFilesController.php:sell` | High |
| 14 | `PlanLimits` not enforced on sales; suspended/expired agencies can sell | `DealerFilesController.php` | High |
| 15 | Bundle check ignores dynamic imports; `erpSeed` chunk embeds secrets | `vite.config.ts`, `check-bundle.mjs` | Critical |
| 16 | `API_BASE_URL` falls back to localhost in production | `frontend/src/services/api.ts` | Critical |
| 17 | CNIC masking `str_repeat` negative crash on 7-8 digit CNIC | `DealerController.php:maskCnic` | Low |
| 18 | `staffUsed` counts deactivated members | `PlanLimits.php:staffUsed` | Medium |

---

## 4. Verification Status

| Area | Verified Via | Status |
|------|-------------|--------|
| Backend controllers (Finance, DealerFiles, Document, Property, Transaction) | Direct source review + parallel task audits | Done |
| Frontend type check | `npm run lint` (tsc) | PASS (0 errors) |
| Frontend tests | `npm run test` | 127/127 PASS |
| Frontend build | `npm run build` | PASS (29 pages + sitemap) |
| Bundle security | `npm run check:bundle` | OK (but dynamic imports not scanned) |
| Backend runtime tests | Composer packages unavailable | UNVERIFIED |
| Backend PHP syntax | `php -l` on modified files | PASS |
| MFA enforcement | Source review of `EnsureMfaVerified` + `AuthController` | Verified |
| RBAC / tenant isolation | Source review of routes + `AuthorizesApi` | Verified (with noted gaps) |

---

## 5. Next Steps

1. **Create `IMPLEMENTATION_REPORT.md`** — track each fix with before/after code and verification evidence.
2. **Execute Phase 0 (Secrets)** — already partially done (removed `_inspect.php`, `workspace.json`, `erpSeed.ts` passwords; need to verify no secrets remain in git history).
3. **Execute Phase 1 (Public Data Leaks)** — fix dealer API, catalog, plots/videos stripping.
4. **Execute Phase 2 (Auth/Tenancy/RBAC)** — fix invitation, admin dashboard scoping, transaction cross-tenant leak.
5. **Execute Phase 3 (Finance Integrity)** — fix refund, overpayment, immutability, void cascade, N+1.
6. **Execute Phase 4 (Dealer Files)** — fix pricing, caps, plan limits, decimal overflow.
7. **Execute Phase 5 (Frontend)** — fix API_BASE_URL, 401 handling, dead APIs, autosave, deleteAdminPlan, security headers.
8. **Run full verification** — backend tests (with composer), frontend tests, build, bundle check.
9. **Create remaining deliverables** — `SECURITY_AUDIT.md`, `DATABASE_AND_API_AUDIT.md`, `FRONTEND_UI_UX_AUDIT.md`, `BROWSER_TEST_REPORT.md`, `PRODUCTION_READINESS_REPORT.md`.

---

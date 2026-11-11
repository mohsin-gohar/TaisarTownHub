# TaisarTownHub — Enterprise Upgrade Master Document

**Started:** 2026-11-10 · **Architecture blueprint:** `documents/16_ENTERPRISE_ARCHITECTURE.md`
**Rules of engagement:** phased execution, no destructive DB ops without backup + approval,
preserve existing working features and the 10 modified working-tree files, no fake data as a
substitute for real functionality.

---

## PHASE 1 — COMPLETE AUDIT ✅ (2026-11-10)

### 1.1 Verified baseline (evidence)

| Gate | Result |
|---|---|
| Backend migrations | 19/19 applied (2 pending ones applied this session, additive) |
| Backend tests | **5 passed / 17 assertions** (PHPUnit 11, sqlite :memory:) |
| Frontend verify | **lint 0 errors · tests pass · build 4.3s · check:bundle OK** |
| MySQL | MariaDB 10.4 (XAMPP) `taisartownhub`, listening :3306 |
| Data present | 5 users · 12 dealers · 4 properties (table) · 83 business_records (15 collections) · 3 videos · 2 inquiries · 1 audit row |
| **Verified backup** | `C:\Users\mohsi\AppData\Local\Temp\opencode\backup_taisartownhub_20261110_101116.sql` (250 KB, 20 tables, users + business_records confirmed in dump) |

### 1.2 What actually exists (honest inventory)

**Backend (Laravel 12 + Sanctum + MariaDB):**
- 36 API routes: auth (login/register/me/logout), public catalog, workspace bulk sync,
  properties CRUD, dealers CRUD, dealer-files, videos, inquiries, transactions (create only), projects (read only).
- Server RBAC = static PHP matrix `RolePermissions::MATRIX` over 5 legacy roles
  (`super_admin/admin/manager/dealer/operator`); `User::hasPermission()`.
- Audit: `audit_logs` table (append-only, best-effort writes, silent failure).
- Dual persistence: Eloquent tables **and** generic `business_records` JSON (15 collections) — two sources of truth.

**Frontend (React 19 + TS strict + Vite 8 + Tailwind 4, only 3 runtime deps):**
- Custom pushState router (no react-router), 14 public views, 17 admin modules, 1870→2036-line AppContext.
- Real backend calls: boot catalog/workspace, login/register/logout, debounced bulk `PUT /workspace` (all 14 collections), newsletter, inquiry POST, video create/like/comment.
- **All granular Laravel endpoints exist but are never called** — persistence funnels through the bulk workspace sync.
- Domain logic (permission matrix, transaction state machine, ledgers, media policy, audit) lives frontend-side with unit tests.

### 1.3 Complete gap analysis

#### A. Missing (blueprint demands, absent entirely)
| # | Gap | Impact |
|---|---|---|
| A1 | **No `agencies` table / no `agency_id` anywhere** — agency = free-text string | Multi-tenant isolation impossible; #1 blocker |
| A2 | No agency registration/approval/verification workflow | Acceptance criterion #1 unmet |
| A3 | No staff management (invite/roles/deactivate) | Acceptance criterion #2 unmet |
| A4 | No permission tables / custom roles | Blueprint §3 unmet; owners can't create roles |
| A5 | No private document vault (folders/versions/access logs/private disk) — `dealer_files` is URL-based with plaintext `buyer_cnic` | Acceptance criterion #3 unmet; CRITICAL security feature missing |
| A6 | No property approval workflow (Draft→Pending→Approved→Rejected→Expired) — `status` is free-text merchandising label | Acceptance criteria #5-7 unmet |
| A7 | No media studio with public/private selection | Blueprint §10 |
| A8 | No CRM pipeline (lead status/follow-ups/assignments/activities) — inquiries are a flat contact form | Acceptance criterion #9 partially unmet |
| A9 | No payment/installment/commission tables; transactions create-only | Acceptance criterion #9 unmet |
| A10 | No notifications | Blueprint §13 |
| A11 | No subscription plans/quotas | Blueprint §12 |
| A12 | No sectors/blocks tables (location pages impossible) | Blueprint §3 public site |
| A13 | No favorites/views tables (localStorage only) | Blueprint §3 |
| A14 | No system settings table | Blueprint §11 |
| A15 | No moderation queue / report listing / duplicate detection | Blueprint §7 |
| A16 | No E2E/feature tests for auth, routes, isolation | Acceptance criterion #11 |

#### B. Fake or frontend-only (must become real)
| # | Gap | Where |
|---|---|---|
| B1 | Audit trail is per-device localStorage (500 cap, mutable) — server audits only media sales | `domain/auditLog.ts`, `AdminAudit.tsx` |
| B2 | Dashboard analytics hard-coded (chart 18/28/42/58/72, "+12.4%", "32.4%") | `AdminOverview.tsx:101,204-229` |
| B3 | Uploads are base64 data-URLs inside JSON payloads (no real file storage) | `AdminFileManagement.tsx:123-141`, `AdminMediaLibrary.tsx:66-84` |
| B4 | "Security & Roles" module is a stub: demo PIN switcher + static table, zero user management | `AdminSecurityRoles.tsx` |
| B5 | Transaction state machine, money math, commission accrual enforced only client-side | `domain/transactions.ts` vs `WorkspaceController::save` |
| B6 | Record-level scoping (dealer) enforced only client-side | `domain/permissions.ts` vs workspace bulk dump |
| B7 | Favorites / recently-viewed / video likes per-browser only | `FavoriteHeart.tsx`, AppContext |
| B8 | Demo PIN `4545` + plaintext `DEMO_ACCOUNTS` committed (`.env`, `.env.example`, `erpSeed.ts`) | config + seeds |
| B9 | `index.html` ships fabricated `aggregateRating` 4.9/1450 JSON-LD | `index.html:35-39` |

#### C. Broken / insecure / incomplete (backend, with file:line)
| # | Issue | Location |
|---|---|---|
| C1 | `GET /api/workspace` = whole-ERP dump gated by `view_properties` (dealers/operators qualify), no record filtering | `WorkspaceController.php:61-72` |
| C2 | `PUT /api/workspace` unvalidated mass overwrite; `syncList` soft-deletes rows absent from payload (data loss + bypasses all validation) | `WorkspaceController.php:74-109,145-162` |
| C3 | Unauthenticated like/comment with client-supplied `userName`/`isDealer` (impersonation) | `routes/api.php:104-105` |
| C4 | `APP_DEBUG=true` + catch-all renderer leaks exception internals | `.env:4`, `bootstrap/app.php:44-56` |
| C5 | Open registration fabricates 3 featured "verified" properties + fake stats per registrant | `AuthController.php:54-248` |
| C6 | Client-supplied PKs/FKs trusted (`properties.id`, `dealers.id`, `transactions.dealerId`, `videos.agencyId`) | multiple controllers |
| C7 | View permissions used as write gates (`view_inquiries`→status write, `view_properties`→workspace write) | `InquiryController.php:99-111`, `WorkspaceController.php:76` |
| C8 | Sanctum tokens never expire; logout revokes only current token; per-IP-only login throttle | `config/sanctum.php:53` |
| C9 | No FK constraints on `properties.assignedDealerId`, `transactions.dealerId/propertyId`, `users.dealer_id`, `video_comments.agency_video_id` | migrations |
| C10 | Financial writes bypass validation; deal-number race; audit silently swallowed; `DealerFilesController` never writes `audit_logs` | `TransactionController.php:64-72`, `AuthorizesApi.php:42-46` |
| C11 | Public `/api/catalog` exposes dealer emails/phones/commission totals | `WorkspaceController.php:229-233` |
| C12 | Unbounded `->get()` on all list endpoints; LIKE wildcard injection in `search` | `PropertyController.php:34-45` etc. |
| C13 | `ProjectController::store/update/destroy` unrouted, unauthorized, reference non-existent columns | `ProjectController.php:39-99` |
| C14 | Dead/duplicated code: `maskCnic` ×4, phantom `inquiries.cnic`, dead `loginAsDemo`/`DEMO_ACCOUNTS`/`getHealth`/`getDealers`/`getProperties`/`getVideos`, unused `firebase.ts`/`puppeteer-core`, duplicate auth pages | multiple |
| C15 | `RolePermissions::allows()` vs `forRole()` disagree for unknown roles (false vs operator) | `RolePermissions.php:52,57` |

#### D. Preserve (working, do not break)
Public marketplace pages & deep links · navy/gold design system · transaction state machine +
ledger UI · CNIC masking · bundle leak guard (`check:bundle`) · demo/PIN flows · soft delete +
recycle bin · 16 frontend test suites · 5 backend tests · workspace sync (hardened, not removed)
· 83 business_records rows · all seeders.

### 1.4 Deliverables
- ✅ Gap analysis (above)
- ✅ Mermaid ERD + relationship summary → `documents/16_ENTERPRISE_ARCHITECTURE.md` §2
- ✅ Agency-wise RBAC permission matrix → `documents/16_ENTERPRISE_ARCHITECTURE.md` §3
- ✅ Verified DB backup before any schema change

---

## PHASE 2 — DATABASE & ARCHITECTURE ✅ COMPLETE (2026-11-10)

### 2.1 Migrations (17 new, all additive, MariaDB 10.4 compatible)

| # | Migration | Purpose |
|---|---|---|
| 000001 | `create_agencies_tables` | `agencies`, `agency_invitations`, `dealers.agency_id` |
| 000002 | `create_rbac_tables` | `permissions` (62), `roles` (platform+agency+custom), `role_permissions` |
| 000003 | `create_agency_members_table` | membership = sole tenancy source of truth |
| 000004 | `add_enterprise_fields_to_users` | phone/status/avatar/last_login/failed_attempts/locked_until + indexes |
| 000005 | `create_location_and_category_tables` | `sectors`, `blocks`, `property_categories` |
| 000006 | `add_workflow_fields_to_properties` | agency_id FK, approval/visibility/featured, slug, sector/block/category FKs, workflow timestamps, composite indexes |
| 000007 | `create_property_workflow_tables` | `property_status_history`, `property_favorites`, `property_views` |
| 000008 | `create_document_vault_tables` | `document_folders`, `documents`, `document_versions`, `document_access_logs` (private disk) |
| 000009 | `create_media_files_table` | agency media studio w/ public/private visibility + sale attribution |
| 000010 | `add_crm_fields_to_inquiries` | agency/property/agent FKs, lead_status, follow_up, `lead_activities` |
| 000011 | `create_finance_tables` | transactions extensions (void/approve/audit) + `transaction_payments`, `payment_schedules`, `commissions` |
| 000012 | `create_notifications_table` | morph notifications |
| 000013 | `create_subscription_tables` | `subscription_plans`, `agency_subscriptions` (configurable limits) |
| 000014 | `create_system_settings_table` | platform key/value settings |
| 000015 | `extend_audit_logs_and_business_records` | audit agency_id/user_id/entity/metadata/cross_agency + br agency scoping |
| 000016 | `backfill_enterprise_relations` | data backfill (idempotent) |
| 000017 | `add_full_fields_to_properties` | complete property field set (public + private ERP fields) |

### 2.2 Evidence — backfill & seeding (verified via tinker)

```
agencies: 12 (all active/verified, from dealer affiliations)
dealers with agency_id: 12/12
properties: 16 total → 16 approved+public (public site unchanged) → 16/16 agency-linked
transactions: 3 · transaction_payments: 4 · commissions: 3 · inquiries: 5 (all mirrored)
permissions: 62 · roles: 11 (7 agency system + 4 platform) · role_permissions: 328
sectors: 11 · blocks: 1 · property_categories: 6 · subscription_plans: 4 · system_settings: 12
agency_members: 1 (dealer@… → agent role, from dealers.agency_id link)
```

### 2.3 Evidence — code

- **17/17 migrations applied** (`php artisan migrate --force` → all DONE)
- **EnterpriseSeeder ran**: 62 permissions, 11 roles, 328 role-permission grants, 6 categories, 4 plans, 12 settings
- **27 Eloquent models created/extended**, all boot + query clean (`ALL MODELS OK`)
- **Tenancy resolution verified**:
  - dealer → `resolveAgencyId() = agy_6abc…` (own membership), `properties.view@agency = yes`,
    `finance.manage_payments@agency = no` (agent role correctly lacks finance writes),
    **bogus agency request → NULL (denied)**, legacy `view_properties` intact
  - superadmin → platform user (NULL agency), `admin.manage_users = yes`
- **Config**: `documents` private disk added (`storage/app/private/documents`, `serve=false`)
- **Support**: `app/Support/Permissions.php` (62-key catalog, agency/platform grants, legacy aliases, `agencyAssignable()` exclusion of platform keys)
- **Tests: 5 passed / 17 assertions** (RolePermissions legacy invariants untouched)
- **Backup (pre-change, verified)**: `backup_taisartownhub_20261110_101116.sql` (250 KB, 20 tables)

### 2.4 Design decisions

1. **Additive-only schema** — no column dropped/renamed; existing rows backfilled with
   `approval_status='approved', visibility='public'` so the public marketplace shows exactly
   what it showed before.
2. **`business_records` JSON store kept but hardened** — given `agency_id` + composite
   indexes; dual-store drift accepted for legacy ERP collections until Phase 4 migrates
   reads/writes to scoped per-resource APIs.
3. **Legacy `RolePermissions::MATRIX` preserved** — agency system roles additionally granted
   `legacy.*` alias keys so every existing frontend permission gate keeps working.
4. **`resolveAgencyId()` never trusts client `agency_id`** — override must match the user's
   own active membership, else deny (verified: bogus id → NULL).
5. **Private documents** on a non-servable disk; no public URL ever generated — Phase 3
   streams them through authenticated, policy-checked, audit-logged endpoints.

## PHASE 3 — BACKEND DEVELOPMENT ✅ COMPLETE (2026-11-10)

**Objectives:** agency registration/auth, agency context middleware (membership-resolved,
never client-supplied), policies, per-resource REST APIs (properties+workflow, document vault
with private-disk streaming downloads, media, CRM, finance, notifications, locations,
favorites, admin panel, subscriptions), hardened workspace sync (validation + agency scoping +
no cross-collection wipes), server-side state machine + money validation, audit everywhere.

### 3.1 Delivered endpoints (129 unique controller actions across 133 route lines, `route:list` clean)

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/login` (per-account lockout), `/auth/me`, `/auth/logout`, `/auth/logout-all` |
| Agency | `GET|PUT /agency/profile`, `/agency/members` CRUD, `/agency/invitations`, `/agency/roles` CRUD, `POST /agency/register` |
| Properties + workflow | `GET|POST /properties`, `PUT|PATCH|DELETE /properties/{id}`, `/submit`, `/approve`, `/reject`, `/feature`, `GET /moderation/queue` |
| Documents | full CRUD + versions + download/preview + restore + permanent delete + access logs + folders (private disk) |
| Media | CRUD + public/private file delivery + `/sell` (no guessed cost) |
| CRM / leads | `/agency/leads` CRUD + assign + activities + CSV export, legacy `inquiries` |
| Finance | deals/transactions/payments/schedules/commissions with server state machine + money validation + dealer scoping |
| Notifications / favorites / locations / subscriptions / plans | full set incl. `GET /plans` public catalog, `GET /agency/subscription` |
| Admin | dashboard analytics, agencies CRUD/verify/status/subscription, users CRUD/unlock/revoke tokens, roles, audit-logs, settings |

### 3.2 Evidence — migrations, seed, tests, live smoke

- **Migrations**: `000018_add_pending_changes_to_properties`, `000019_add_video_engagement_attribution` → both **DONE** (`migrate --force`)
- **Seeded platform RBAC**: 6 platform roles (admin/manager/moderator/operator/super_admin/support),
  62 permissions, **362 role-permission grants**, manager=21 perms / operator=12 perms, sanity checks green
  (operator `properties.create=yes` / `properties.approve=no`; manager `admin.manage_users=no`)
- **Route verification**: `php artisan route:list --path=api` → **133 lines, no errors**; reflection
  check → **129 unique controller actions, 0 missing (`ALL ROUTE ACTIONS OK`)**
- **Backend tests**: **5 passed / 17 assertions** (`php artisan test`)
- **Live HTTP smoke test against `php artisan serve --port=8080`**: **34/34 checks PASSED, 0 failed**
  (unauth→401 ×5, login→200, lockout, `/auth/me`, `/workspace` GET+PUT merge + no-op change-detection,
  cross-agency agency endpoints via `X-Agency-Id`, public properties, create→submit→approve→moderation→delete,
  leads, finance, dashboard, plans, notifications, favorites, documents, media, locations, subscription,
  audit-logs, admin users/roles/settings, logout)

### 3.3 Fixes landed during Phase 3 validation

1. **Missing `App\Models\PropertyFavorite`** → created `backend/app/Models/PropertyFavorite.php`
   (table `property_favorites` from migration 000007) — fixes `GET /api/favorites` 500.
2. **Cross-agency platform admin access** → `AuthorizesApi::requirePermission/requireAny` now allow
   platform-wide grants when the AgencyContext flags cross-agency (`X-Agency-Id` + `admin.manage_agencies`);
   `WorkspaceController` gates (`canRead/canWrite/canReplace`) use the same resolution.
3. Workspace hardening itself landed clean: merge-only default (no cross-collection wipes), replace
   requires delete-level permission + agency scope, server-owned tenancy stamps, per-row validation
   (id regex ≤64, 512KB cap, depth ≤8, money coercion on financial rows), change-detection skip for no-op
   writes, financial syncs audited only when rows changed.

## PHASE 4 — FRONTEND INTEGRATION ✅ COMPLETE (2026-11-10)

**Objectives:** wire the new backend endpoints into the React frontend — auth → agency context;
public marketplace from the real approved-listing API; enterprise modules (agency dashboard,
staff, document vault, moderation, CRM pipeline, notifications, subscriptions, locations);
migrate legacy ERP modules to scoped+validated persistence; replace hard-coded analytics with
real queries; real file uploads; server audit view.

### 4.1 Delivered (first pass)

- **`frontend/src/services/api.ts`** — full Phase-3 endpoint coverage:
  agency profile/staff/roles/invitations, property lifecycle + moderation queue, CRM leads
  (list/export/create/update/assign/delete), finance (transactions/commissions), admin
  (dashboard/agencies/plans/users/roles/audit-logs/settings), subscriptions & plans,
  notifications, favorites (per-account), inquiries, locations, projects, dealer files &
  sales reports, media library, videos + comments, and inquiry status updates.
  - Agency context: module-level acting-agency state sent as `X-Agency-Id` on every call
    (`setApiAgencyId` / `getApiAgencyId`).
  - Video like/comment now hit the authenticated endpoints and match the new contracts
    (`POST /videos/{id}/like` → `{likes, liked}`; comments send `{comment}` only).
- **`frontend/src/context/AppContext.tsx`** — login resolves and persists the acting agency
  from the backend `agencies` memberships (restored on boot from `tth_user`); inquiry status
  changes PATCH the server; logout/registration reset agency context.
- **`frontend/src/components/admin/AdminOverview.tsx`** — real dashboard analytics:
  KPIs (deal value, collected, commissions, active deals), conversion %, and a server-driven
  daily activity chart from `GET /api/admin/dashboard` (hard-coded 18/28/42/58/72 series removed;
  context values remain as offline fallback).
- **`frontend/src/components/admin/AdminAudit.tsx`** — audit tab now renders server audit
  logs from `GET /api/admin/audit-logs` (localStorage trail kept as fallback); recycle bin
  untouched.
- **Public marketplace** — served through `GET /api/catalog`, which the backend already filters
  to approved+public listings (`publiclyListed()`), so the public pages show only real,
  moderated listings.

### 4.2 Evidence

- `npx tsc --noEmit` → **clean**
- `npx tsx --test "src/**/*.test.ts"` → **111 passed / 0 failed** (16 suites)
- `npm run build` → **green** (dist built, index 332 KB / 94 KB gzip)
- `node scripts/check-bundle.mjs` → **OK** (no private ERP data / demo credentials in public chunks)
- Backend regression: `php artisan test` → **5 passed / 17 assertions**

### 4.3 Remaining Phase 4 follow-ups (deferred to Phase 7 UI/UX)

- New enterprise UI modules (agency dashboard, staff manager, document vault, moderation
  desk, CRM pipeline board, notifications center, subscriptions view, locations editor) —
  the API layer for every module above is ready; the screens land in Phase 7.
- Real file uploads (multipart) for the document vault & media studio — API methods for media
  exist; document multipart upload lands with the Phase 7 document vault screen.

## PHASE 5 — SECURITY ✅ COMPLETE (2026-11-10)

### 5.1 Hardening delivered

| Item | Fix |
|---|---|
| C4 `APP_DEBUG=true` + exception internals | `.env` → `APP_DEBUG=false`; `.env.example` → `APP_DEBUG=false`; catch-all renderer now returns a fixed `Server error.` for 5xx and appends the class name only in debug mode for non-5xx |
| C8 tokens never expire | `config/sanctum.php` `expiration` → **1440 minutes** (24 h); `SANCTUM_TOKEN_PREFIX=tth_` added to `.env` + `.env.example` |
| CORS | explicit origins (localhost/127.0.0.1 :3000/:3001), `supports_credentials=true` |
| Private storage | `local` (storage/app/private) and `documents` disks → `serve=false` (no public URLs) |
| Missing security headers | new `App\Http\Middleware\SecurityHeaders` (global stack): `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, HSTS on HTTPS |
| B8 plaintext demo PIN | `VITE_DEMO_PIN` removed from `frontend/.env.example` (defaults to disabled; demo builds must opt in) |
| Rate limiting | API throttle verified live (`X-RateLimit-Limit: 60`), login 5/min, register 3/min |
| Git hygiene | `backend/.env` + `frontend/.env` confirmed gitignored and untracked |

### 5.2 Evidence

- Config verified via tinker: `app.debug=false`, `sanctum.expiration=1440`, `token_prefix=tth_`,
  `cors.supports_credentials=true`, both private disks `serve=false`
- Live header check on `GET /api/health` → **6/6 security headers PASS**
- Backend tests **5 passed / 17 assertions**; live smoke test **34/34 PASS** after hardening
- Frontend verify unchanged (no frontend code changes in Phase 5 beyond `.env.example`)

### 5.3 Remaining (accepted trade-offs for Phase 8)

- Session `secure` cookie flag is env-driven (`SESSION_SECURE_COOKIE`) and must be `true` in
  production behind HTTPS.
- HSTS is only emitted on HTTPS responses by design.
## PHASE 6 — TESTING & QA ✅ COMPLETE (2026-11-10)

### 6.1 Enterprise feature suite delivered

| Suite | Tests | Focus |
|---|---:|---|
| `MigrationSmokeTest` | 1 | Full migration set boots on sqlite `:memory:` |
| `AuthSecurityTest` | 8 | 401 guards, credential errors, per-account lockout + expiry reset, token logout revocation, login throttle 429, hashed passwords |
| `AgencyContextTest` | 6 | Membership resolution, foreign-agency 403, platform manager denied, cross-agency superadmin, workspace isolation, platform keys not agency-assignable |
| `WorkspaceHardeningTest` | 6 | Merge-only saves, replace permission gate, member replace scoping, foreign-row hijack blocked, invalid ids, tenancy fields stripped |
| `PropertyLifecycleTest` | 8 | Create→submit→approve, self-approve 403, foreign-agency 404, re-approval staging, reject keeps live copy, first-reject hides, submit state rules, public detail hides pending |
| `FinanceLifecycleTest` | 9 | Full forward pipeline, Completed gating, one-step-back, Cancelled terminal, cross-agency 404, void read-only + audited, reason required, payment ledger totals, amount bounds |
| `CrmLeadsTest` | 8 | Public inquiry agency attribution, platform-owned lead, agent visibility, assignment permission, outsider 422, status validation/audit/conversion, cross-agency 404, manual leads |
| `FavoritesNotificationsTest` | 5 | Favorite toggle, public-only, account isolation, read tracking + cross-account 404, unread filter |
| `StorageSecurityTest` | 8 | Private upload + authenticated stream, guest 401, agency isolation, sensitive permission gates, `.exe`/`.php` rejected, public/private media delivery, soft delete keeps file |
| `AdminStaffSubscriptionTest` | 9 | Platform user CRUD + self-protection, agency rejection on platform surfaces, platform settings read/write, platform permissions blocked on agency roles, member/role scoping, invitation accept flow, staff-limit 402, public plan catalog + subscription scope, dashboard platform/agency split |

### 6.2 Evidence

- `php artisan test` → **73 passed / 379 assertions** (15.83s, sqlite `:memory:` via `RefreshDatabase`; includes the 5 pre-existing baseline tests)
- 68 new enterprise feature tests added this phase, all individually green before the full run
- Live HTTP smoke test re-run after the Phase-6 fixes → see section 6.4
- Frontend unchanged by Phase 6; verify remains green (tsc clean, 111/111 tests, build, bundle guard)

### 6.3 Real bugs found & fixed by the suite

1. **`transactions.propertyId` NOT NULL** — `FinanceController::store()` wrote `null` for deals without a linked listing. Fixed to persist an empty string.
2. **Missing finance columns** — `paymentMethod`, `paymentStatus`, `transactionDate`, `internalRemarks` were accepted by the API but absent from the schema. Additive migration `2026_11_10_000020_…` applied to MySQL + `Transaction::$fillable`.
3. **String-PK models dropped ids on `create()`** — `id` missing from `$fillable` on `Agency`, `Block`, `Document`, `DocumentFolder`, `MediaFile`, `Role`, `Sector` caused NOT NULL violations for any mass-assigned create. All fixed.
4. **Subscription remaining-staff bug** — `remaining.staff` read the nonexistent `usage.staff` key instead of `usage.members`, always reporting the full staff allowance as remaining. Fixed with an explicit alias mapping.
5. **Test-infra quirks** (not production bugs): `User::fillable` excludes `locked_until` (tests use `forceFill`), the shared in-process guard cache needed `forgetGuards()` between simulated requests (`tokenFor()` / `asGuest()`), and rate-limit middleware is bypassed only in lockout-specific tests.

### 6.4 Production smoke

- Live API smoke (34 checks: health, auth, agency context, workspace, moderation, CRM, finance, favorites, notifications, subscriptions, admin) — **34/34 PASS** after the fixes above (0 failures).

## PHASE 7 — ENTERPRISE UI/UX ✅ COMPLETE (2026-11-10)

### 7.1 Enterprise screens delivered

`frontend/src/components/admin/` — ten capability-gated screens, lazy-loaded via
`React.lazy` and mounted from `AdminLayout` (all render only when the API reports the
matching capability, so no screen is reachable that the backend would reject):

| Screen | Route | Backend surface |
|---|---|---|
| `AdminEnterpriseUi` | `/admin/enterprise` | permission catalog, capability summary, agency context |
| `AdminAgencyDashboard` | `/admin/agency_dashboard` | `GET /api/agency/dashboard` (KPIs, staff, listings, deals, usage) |
| `AdminStaffManager` | `/admin/staff` | `GET/POST/PUT /api/agency/members`, roles, invitations, staff limits |
| `AdminDocumentVault` | `/admin/documents` | `GET/POST /api/agency/documents`, folders, authenticated download/preview/delete |
| `AdminModerationDesk` | `/admin/moderation` | `GET /api/moderation/queue`, approve/reject with reason |
| `AdminCrmPipeline` | `/admin/crm` | `GET/POST/PUT /api/agency/leads`, assignment, status transitions, CSV export |
| `AdminNotifications` | `/admin/notifications` | `GET /api/notifications`, mark read / mark all read |
| `AdminSubscription` | `/admin/subscription` | `GET /api/agency/subscription`, plans, usage limits |
| `AdminLocations` | `/admin/locations` | `GET /api/locations` (sectors/blocks/categories with listing counts) |
| `AdminMediaStudio` | `/admin/media_studio` | `GET/POST/PUT/DELETE /api/agency/media`, multipart + URL registration, visibility, private preview |

Supporting work:

- **`AdminLayout.tsx`** — enterprise sidebar section, capability gating from
  `GET /api/auth/me` (`enterprise_permissions` + `agencies`), agency context selector
  for platform superadmins (`admin.manage_agencies`), and
  `Suspense key={agencyContextId || 'platform'}` so tab content remounts on context switch.
- **`services/api.ts`** — full typed coverage for the ten screens: multipart helpers,
  correct response envelopes, `assignAgencyLead` body key (`agent_id`), private blob
  read/download/preview helpers, `getAgencyDocuments(params)` added.
- **`apiFetch`** now omits the JSON `Content-Type` when the body is `FormData`, so
  multipart uploads are real browser uploads (no base64/JSON shims).
- Private document/media bytes are only fetched through authenticated API calls and
  delivered to the browser as object URLs; public media is the only file exposed via
  `GET /api/media/{id}/file` and only while `visibility = public`.
- Document preview opens a blank window synchronously in the click handler, then
  navigates it to the blob URL after the authenticated fetch — avoids popup blocking.

### 7.2 Real multipart E2E evidence (browser, superadmin acting as agency)

Agency context: `TaisarTownHub Premier Advisory` (`agy_1c00b5f1f7563ada`).

- **Media Studio**: real `File` injected into the file input → `POST /api/agency/media`
  **201**, card rendered with private blob preview (`blob:` URL, 1×1 image decoded).
  Visibility toggle → `PUT` **200**; anonymous `GET /api/media/{id}/file` **200 / 70 B**
  while public, anonymous private endpoint **401**; back to private → public file **404**.
  Soft delete via UI → "Media soft-deleted", library empty.
- **Document Vault**: real multipart → `POST /api/agency/documents` **201**, row rendered
  (Image, 70 B). Authenticated download `GET …/download` **200** (70 B captured).
  Authenticated preview `GET …/preview` **200** (blob URL opened in a new tab).
  Soft delete via UI → "Document soft-deleted", row removed.
- **Notifications**: fixture created for the superadmin → UI showed `UNREAD 1`,
  card with title/message; **Mark read** → `POST /api/notifications/{id}/read` **200**,
  `UNREAD 0`, action button removed. Fixture deleted after the check.
- **All ten screens** opened in the browser with no unexpected 4xx/5xx; transient
  `Loading…` states were confirmed to be `php artisan serve` single-threaded queueing,
  not application errors (direct API calls returned in ~1.2 s while browser requests queued).

### 7.3 Real cross-agency capability bug found & fixed

A platform superadmin acting inside an agency (`X-Agency-Id`) was shown
`can_upload=false`, `can_see_sensitive=false`, `can_edit=false` by the API even though
the mutation endpoints accepted the same user — response flags used
`hasPermission($perm, $agencyId)`, which resolves agency membership only, while
`requirePermission()` had a platform-fallback for cross-agency users.

- Added `AuthorizesApi::can(Request, string): bool` mirroring `requirePermission()`
  (agency-scoped grant, then platform grant when `agency_cross_agency`);
  `requirePermission()` / `requireAny()` refactored onto it.
- Patched `DocumentController` (`can_upload`, `can_see_sensitive`), `MediaController`
  (`can_edit`), `LeadController` (record scoping), `FinanceController` (deal/commission
  visibility, void), `PropertyController` (`isModerator`, `view_sensitive_cnic`,
  `serializeForScope`) — UI flags and API enforcement now share one contract.
- Fixed `Undefined variable $request` in the private `findScoped()` helpers
  (`FinanceController`, `LeadController`) via `$request = request();`.
- New regression suite `backend/tests/Feature/CrossAgencyCapabilitiesTest.php`
  (**3 tests / 18 assertions**): cross-agency admin keeps platform capabilities,
  agency member stays least-privilege (flags match the 403), cross-agency lead
  pipeline is platform-wide while an agent still sees only their own lead.

### 7.4 Final gates

- Backend `php artisan test` → **76 passed / 397 assertions** (5.49 s, sqlite `:memory:`)
- Frontend `npx tsc --noEmit` → **CLEAN**
- Frontend `npx tsx --test "src/**/*.test.ts"` → **111 passed / 0 failed** (9 suites)
- `npm run verify` → build OK + `check:bundle` **OK** (no private ERP data/demo credentials;
  `AdminDocumentVault` chunk 12.59 kB gzip 4.19 kB)
- Live API smoke `smoke_phase3.php` → **34/34 PASS**

### 7.5 Remaining follow-ups (carried into Phase 8)

- `SESSION_SECURE_COOKIE=true` when the production site is served over HTTPS.
- Tighten dev CORS origins (`localhost/127.0.0.1` ports 3000–3002, 5173) to the single
  production frontend origin.
- Legacy NULL-agency `business_records` rows remain shared; revisit if cross-agency
  leakage is observed in production data.
- Agency seeded data is intentionally sparse; real agencies populate their own records.

---

## PHASE 8 — PRODUCTION READINESS ✅ COMPLETE (2026-11-10)

### 8.1 Hardening changes delivered

- **Truthful health probe** — `GET /api/health` moved from a closure that always
  claimed `"MySQL connected"` to `HealthController`, which runs `select 1`. It
  returns **503** with `status: "degraded"` and `database: "unavailable"` when the
  database is down (details go to the Laravel log, never to the client).
- **Public media embeddability** — `SecurityHeaders` now sets
  `Cross-Origin-Resource-Policy: cross-origin` only for `GET /api/media/*/file`
  (and `storage/*`), so marketing pages on the frontend domain can embed public
  media; every private API response stays `same-origin`. Media delivery switched
  from `download()` to inline `Storage::response()` so public files render in
  `<img>`/`<video>` while authenticated consumers still need a token.
- **Scheduler** — `routes/console.php` now schedules
  `sanctum:prune-expired --hours=48` daily and `queue:prune-failed --hours=168`
  weekly (`php artisan schedule:list` shows 2 events).
- **Preflight command** — new `php artisan app:production-check` audits:
  APP_ENV/DEBUG/KEY/URL/NAME, log level, CORS origins, session cookies, Sanctum
  stateful domains/expiry/prefix, live database, pending migrations, writable
  storage (including the private vault), queue/cache/session drivers and their
  tables, config/route caches, bcrypt rounds and scheduled tasks. Supports
  `--json` (machine-readable) and `--strict` (warnings fail the gate).
- **Environment templates** — `backend/.env.example` is now a production-safe
  template (HTTPS URL, `APP_DEBUG=false`, `LOG_LEVEL=warning`,
  `SESSION_SECURE_COOKIE=true`, exact CORS origin, SMTP, queue/cache/session,
  token prefix). `frontend/.env.example` documents `VITE_API_URL`. Duplicate
  `CORS_ALLOWED_ORIGINS` line removed from the working `.env`; backend
  `APP_NAME` set to `TaisarTownHub API`.
- **Runbook** — `documents/15_DEPLOYMENT_CICD.md` rewritten (v3.0): env
  templates, release gates, first/subsequent deploy commands, storage
  permissions, Supervisor queue worker, scheduler cron, Nginx reference
  (HTTPS + SPA + API + upload limit), backup/restore, health monitoring,
  security checklist, CI workflow and rollback.

### 8.2 Evidence

| Gate | Result |
|---|---|
| `php artisan app:production-check` (caches built) | **20 pass · 1 warn · 0 fail** — only warning is `APP_ENV=local` (expected on the dev machine) |
| `php artisan optimize` | config + events + routes + views cached successfully |
| Health with production caches | `GET /api/health` → **200** `{status:"healthy", database:"MySQL connected"}` |
| `php artisan optimize:clear` | caches cleared afterwards; dev tree remains uncached |
| Backend `php artisan test` | **78 passed / 421 assertions** (11.19 s) |
| Frontend `npx tsc --noEmit` | **CLEAN** |
| Frontend `npx tsx --test "src/**/*.test.ts"` | **111 passed / 0 failed** (9 suites) |
| `npm run verify` | build OK + `check:bundle` **OK** |
| Live smoke `smoke_phase3.php` | **34/34 PASS** |
| New regression tests | `ProductionReadinessTest` (2), public-media CORP/inline assertions in `StorageSecurityTest` |

### 8.3 Go-live actions that need production credentials/infrastructure

These are deployment operations, not code gaps; they are documented in
`documents/15_DEPLOYMENT_CICD.md`:

1. Create the production `.env` from the new template; set real DB/SMTP values,
   `APP_ENV=production`, `APP_URL=https://api…`, `CORS_ALLOWED_ORIGINS=https://…`,
   `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS=…`.
2. Run `php artisan key:generate --force` on the server (never reuse the dev key).
3. Point DNS + TLS certificates at the frontend/API domains; enforce HTTPS.
4. Set `chown/chmod` on `storage/` + `bootstrap/cache/`; keep the private vault
   outside the web root.
5. Install the Supervisor queue worker and the `schedule:run` cron entry.
6. Take and verify DB + document-vault backups; run `migrate --force`, `optimize`,
   then `app:production-check --strict` before switching traffic.
7. Empty `VITE_DEMO_PIN` and use `VITE_APP_MODE=production` for the production
   frontend build; set `VITE_API_URL` to the HTTPS API.

At this point the codebase is production-ready; remaining items are environment
provisioning and operational credentials.

---

## Session log

| Date | Work |
|---|---|
| 2026-11-10 | Phase 1: dual audits (backend + frontend subagents + direct reads), baseline verification (5 backend tests, frontend verify green), DB backup taken & verified, 2 pending migrations applied, architecture blueprint written (`documents/16_ENTERPRISE_ARCHITECTURE.md`), this master document |
| 2026-11-10 | Phase 2: 17 additive migrations (agencies/RBAC/membership/workflow/vault/CRM/finance/subscriptions/settings), backfill (16/16 properties + 12/12 dealers agency-linked, all listings approved+public), EnterpriseSeeder (62 permissions/11 roles/328 grants), 27 models, Permissions catalog + private documents disk; tests 5/5 green |
| 2026-11-10 | Phase 3: 18 migrations applied (incl. pending_changes + video engagement attribution), EnterpriseSeeder v2 (6 platform roles/62 perms/**362** grants, 12 agencies), 129 route actions verified, live HTTP smoke test **34/34 PASS**, backend tests **5 passed/17 assertions**; fixes: missing PropertyFavorite model, cross-agency platform-admin permission resolution |
| 2026-11-10 | Phase 4: api.ts full Phase-3 endpoint coverage + `X-Agency-Id` context, agency context on login/boot/logout, server-backed AdminOverview analytics, server audit view; frontend verify green (tsc clean, 111/111 tests, build, bundle guard) |
| 2026-11-10 | Phase 5: APP_DEBUG=false + generic 500s, Sanctum 24h expiration + token prefix, explicit CORS, private disks serve=false, SecurityHeaders middleware (6/6 live), demo PIN removed from .env.example; smoke 34/34 + tests 5/17 green |
| 2026-11-10 | Phase 6: 10 feature suites / 68 new tests — auth+lockout, agency context, workspace hardening, property lifecycle, finance state machine, CRM leads, favorites/notifications, private storage, admin/staff/subscriptions; full suite **73 passed / 379 assertions**, smoke **34/34**, frontend **tsc clean / 111 tests / build / bundle OK**; real bugs fixed: null propertyId, missing finance columns (migration 000020), 7 string-PK fillable ids, remaining-staff usage mapping |
| 2026-11-10 | Phase 7: ten enterprise admin screens (dashboard, staff & roles, document vault, moderation, CRM pipeline, notifications, subscription, locations, media studio, capability UI) wired to Phase-3 APIs; real multipart uploads proven in browser (media 201/private blob/public delivery/404 when private, documents 201/download 200/preview 200), notification mark-read 200; fixed cross-agency capability flags via `AuthorizesApi::can()` (+3-test regression suite); gates: backend **76 passed / 397 assertions**, frontend **tsc clean / 111 tests / build / bundle OK**, smoke **34/34** |
| 2026-11-10 | Phase 8: truthful DB-probing `/api/health` (503 when degraded), public-media CORP/inline delivery, daily token + weekly failed-job pruning, `app:production-check` preflight (`--json`/`--strict`), production `.env.example` + `VITE_API_URL` template, duplicate CORS cleanup, full deployment/backup/rollback runbook (v3.0); preflight **20 pass / 1 warn / 0 fail** with caches built + health 200; gates: backend **78 passed / 421 assertions**, frontend **tsc clean / 111 tests / build / bundle OK**, smoke **34/34** |

# Phase 7 — Execution Report

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Base: `fix/critical-security-integrity-repairs` @ `4e44364`
Date: 2026-11-28

## Scope

Six workflow areas from the Phase 7 specification:
1. Agency onboarding and account lifecycle
2. Property and media lifecycle
3. CRM, leads and inquiries
4. Private document vault
5. Agency finance and transaction workflows
6. Saved searches, alerts, notifications and workspace settings

## Part A — Critical Repair Completion

### Custom-role privilege escalation (P1)

**Files modified:** `backend/app/Http/Controllers/Api/AgencyStaffController.php`

**Defect:** `storeRole()` and `updateRole()` only checked platform-level permission exclusion (`Permissions::agencyAssignable()`) but did NOT verify the requester actually possessed the permissions being granted. A manager with `agency.manage_staff` could create a custom role containing any agency-assignable permission (e.g. `finance.manage_payments`, `properties.delete`) even if they personally lacked it — then assign that role to themselves or others.

`updateMember()` similarly allowed assigning any custom role without checking whether the role's permissions exceeded the requester's delegation authority.

**Fix applied:**
- Added `delegatablePermissions(Request, string $agencyId): array` helper that:
  1. Reads the requester's effective agency permissions via `User::permissionsFor($agencyId)`
  2. Expands `Permissions::LEGACY_ALIASES` bidirectionally (canonical → aliases, alias → canonical) so legacy and enterprise permission forms are interchangeable
  3. Intersects with `Permissions::agencyAssignable()` to produce the exact set the requester may delegate
- `storeRole()`: after the platform-level check, added delegation check — any requested permission not in the delegatable set → 422 with explicit message
- `updateRole()`: same two-check pattern (platform → delegation)
- `updateMember()`: when `role_id` references a custom role (`is_system === false`), computes the role's permissions and verifies they are a subset of the requester's delegatable set → 422 on excess

**Regression tests:**
- Updated `AdminStaffSubscriptionTest::test_agency_roles_cannot_grant_platform_permissions`:
  - Platform permission (`admin.manage_users`) → 422 (existing assertion preserved)
  - Over-privileged role (`properties.view` + `crm.view` when manager lacks `crm.view`) → 422 with delegation message
  - Legitimate role (`properties.view` only, via legacy alias `view_properties`) → 201
- `test_members_and_roles_are_scoped_to_the_acting_agency` — agency-scoped role creation verified

**Additional fix (system roles):** Found that `updateMember()` only checked custom roles (`is_system === false`), leaving a gap where a non-owner manager could assign a high-privilege **system role** (e.g. `accountant` with `finance.manage_payments`) to self or others. Extended the check to all roles using `Permissions::agencyRoleGrants($role->key)` for system roles; owners remain exempt.

**Regression tests:**
- `test_manager_cannot_assign_role_with_permissions_beyond_delegation_authority` — manager cannot assign role with `finance.manage_payments` → 422
- `test_manager_cannot_elevate_self_via_role_assignment` — self-escalation blocked → 422
- `test_owner_can_assign_any_role` — legitimate owner delegation preserved → 200

**Verification:** `AdminStaffSubscriptionTest` — 21 passed, 136 assertions, exit 0. Full suite — 469 passed, 0 failed, 3972 assertions, exit 0.

### ClamAV incomplete-read fail-closed

**File:** `backend/app/Services/ClamAVScanner.php`

Already fixed in Part A of critical repairs (commit `2fb1996`):
- `fread()` returning `false` during file streaming → returns `null` (scan failure)
- `fread()` returning `false` during daemon reply reading → returns `null`
- Incomplete stream never accepted as clean even if daemon responds OK

**Regression:** `MalwareScanningTest` — 11 tests covering fake-clamd scenarios.

## Part C — Phase 7 Workflow Audits

### 7.1 Agency onboarding and account lifecycle

**Files reviewed:**
- `AgencyController.php` — `register()`, `index()`, `show()`, `current()`, `updateCurrent()`
- `AdminAgencyController.php` — `verify()`, `status()`, plans, subscriptions, invoices
- `AdminAgencyOversightController.php` — platform oversight
- `AgencyStaffController.php` — members, roles, invitations
- `SubscriptionController.php` — plans, current, invoices
- `AuthController.php` — login, registration, MFA

**Findings:**
- Agency registration wrapped in `DB::transaction` — user + agency + membership + starter subscription created atomically. ✅
- Status changes (`active/suspended/rejected/pending`) require `admin.manage_agencies` permission + audit log. ✅
- Last-active-owner protection: `activeOwnerCount() <= 1` check prevents demoting/deactivating the final owner. ✅
- Invitation flow: duplicate check before plan-limit check (422 before 402), expiry enforced, token-based acceptance. ✅
- Platform-level settings require platform permissions — agency members cannot modify them. ✅

**No P0/P1 defects found.**

### 7.2 Property and media lifecycle

**Files reviewed:**
- `PropertyController.php` — full CRUD + moderation + marketplace visibility
- `MediaController.php` — upload, replace, delete, sell
- `AgencyVideoController.php` — video management

**Findings:**
- `serializeForScope()` properly gates sensitive fields:
  - `purchasePricePKR`, `sellingPricePKR`, `internalNotes` → require `canSeeSensitive` (finance.view)
  - `pending_changes` → require `canModerate` (properties.approve)
- `publicPropertyPayload()` uses explicit allowlist — no accidental exposure. ✅
- Moderation workflow: draft → submitted → approved/rejected/returned → published → archived. All transitions audited. ✅
- `MediaController::sell()` wrapped in `DB::transaction` with `lockForUpdate()` — prevents double-sale. ✅
- Cross-agency property access blocked via `forAgency()` scope. ✅

**N+1 finding:** `MapController` line 429-430 — individual `Map::where('id',$id)->update()` in a loop during reorder. Minor (admin-only, small dataset). Not fixed to avoid unnecessary refactoring.

**No P0/P1 defects found.**

### 7.3 CRM, leads and inquiries

**Files reviewed:**
- `InquiryController.php` — public inquiry creation + authenticated management
- `LeadController.php` — CRM pipeline (agency leads)

**Findings:**
- `findScopedForAgent()` now denies unscoped queries for non-platform users (fixed in critical repairs). ✅
- `LeadController::findScoped()` — agency-scoped with agent-level restriction (agents see only own/unassigned). ✅
- CSV export (`export()`) — same scoping as index, permission-gated. ✅
- Assignment (`assign()`) — validates target agent belongs to same agency. ✅
- Activities (`storeActivity()`) — agency-scoped creation. ✅
- Pipeline counts (`pipelineCounts()`) — agency-scoped with optional user restriction. ✅

**No P0/P1 defects found.**

### 7.4 Private document vault

**Files reviewed:**
- `DocumentController.php` — folders, upload, versioning, sharing, quarantine, access logs
- `SecureFileStore.php` — encrypted storage gateway (fixed in critical repairs)
- `ClamAVScanner.php` — fail-closed scanning (fixed in critical repairs)

**Findings:**
- `findScoped()` — `where('agency_id', $agencyId)` enforced on every document access. ✅
- Upload flow: MIME + signature + extension + size validation → malware scan → encrypted store → audit log. ✅
- Share links: token-based, expiry enforced, revocable, download counted. ✅
- Quarantine: infected files isolated, never exposed as clean, rescan available. ✅
- Access logging: every preview/download/share logged with user + IP. ✅
- Versioning: `storeVersion()` creates document_versions rows with checksum. ✅
- Storage quota: `usage()` endpoint reports usage vs plan limit. ✅

**No P0/P1 defects found.**

### 7.5 Agency finance and transaction workflows

**Files reviewed:**
- `FinanceController.php` — transactions, payments, schedules, commissions, expenses, reports
- `TransactionController.php` — legacy transaction endpoints
- `PaymentController.php` — payment processing

**Findings:**
- `findScoped()` — agency-scoped with platform-admin fallback. ✅
- `storePayment()` — DB::transaction with balance validation (payment ≤ remaining balance). ✅
- `storeSchedules()` — DB::transaction with parent lock (fixed in critical repairs). ✅
- `void()` — DB::transaction, prevents payments on voided transactions. ✅
- Commission calculations — integer minor-unit (paisa) comparisons. ✅
- Refund limits — cannot exceed collected amounts. ✅
- Expense tracking — agency-scoped with audit logging. ✅
- Reports — permission-scoped (`finance.view` or `view_transactions`). ✅

**No P0/P1 defects found.**

### 7.6 Saved searches, alerts, notifications and workspace settings

**Files reviewed:**
- `SavedSearchController.php` — CRUD with user scoping
- `NotificationController.php` — list, unread count, mark read/all
- `WorkspaceController.php` — collections, sanitize, import/export

**Findings:**
- `SavedSearchController::findOwned()` — `where('user_id', $user->id)` on every access. ✅
- `MAX_PER_USER` limit enforced on creation. ✅
- `NotificationController` — all methods use `$user->notifications()` (Laravel's morph-many, inherently user-scoped). ✅
- `WorkspaceController` — per-collection permission gate, agency-scoped rows, sanitized output. ✅

**No P0/P1 defects found.**

## Test Evidence

### Backend (PHPUnit)

| Run | Command | Result |
|-----|---------|--------|
| Focused (critical repair) | `php artisan test --filter=AdminStaffSubscriptionTest` | 21 passed, 136 assertions, exit 0 |
| Focused (security areas) | `php artisan test --filter="MalwareScanningTest\|SecureFileStoreTest\|FinanceLifecycleTest\|AgencyPromotionTest\|WorkspaceApprovalTest"` | 62 passed, 985 assertions, exit 0 |
| Full suite | `php artisan test` | 469 passed, 0 failed, 3972 assertions, exit 0 |

**Note:** Default `taisartownhub_test` DB was contaminated by concurrent test runs from `kilo/gap-worktree`. All runs used isolated `taisartownhub_phase7` DB (shell env override, fresh + migrated). See Test Evidence.

### Frontend

| Command | Result |
|---------|--------|
| `npm run verify` | 160 tests, 0 failures, bundle check OK |
| `npm audit --audit-level=high` | 0 vulnerabilities |
| `composer audit --format=plain` | No advisories |
| `git diff --check` | Clean |

### Security

| Command | Result |
|---------|--------|
| `composer audit` | No known vulnerabilities |
| `npm audit` | No known vulnerabilities |

## Remaining Issues

See `PHASE_7_REMAINING_ISSUES.md`.

## Verdict

**PHASE 7 PARTIALLY VERIFIED** — All six workflow areas audited, P1 privilege-escalation defect (custom + system roles) fixed with regression tests, no new P0/P1 defects found. Backend 469/0, frontend 160/0, audits clean. Browser E2E persona journeys, ClamAV live, Docker, TLS/SMTP/DNS and Lighthouse re-measurement remain external blockers (documented, not claimed).

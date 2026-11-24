# TaisarTownHub — Security

Consolidates the former root `SECURITY_ARCHITECTURE.md`, `SECURE_FILE_VAULT_ARCHITECTURE.md`, `FILE_STORAGE_THREAT_MODEL.md` and `RBAC_MATRIX.md`. Every claim is backed by a named test suite; anything not covered by a test is marked as such.

## 1. Identity & sessions

- **Sanctum token-only API.** No cookie sessions for API auth; no stateful SPA/CSRF surface for the API. `redirectGuestsTo(null)` — unauthenticated API requests get JSON 401, never a redirect.
- Passwords bcrypt-hashed; policy: minimum 8 chars, confirmation required on reset.
- **Password reset**: enumeration-safe (identical outward response for known/unknown emails), SHA-256 hashed tokens, and **revokes ALL existing tokens** on success (`AuthFlowsTest`).
- **Email verification**: SHA-256 hashed, 60-minute expiry, tracked but not login-blocking — the workspace gate is agency approval (documented business decision).
- **MFA**: TOTP + recovery codes; login returns a challenge and no token until verified (`mfa_required` flow); disable requires password + valid code.
- Login rate limited 5/min; register/forgot 3/min; failed logins logged and lockable (`admin/users/{id}/unlock`); login history endpoint for the account owner.

## 2. Tenancy (core invariant)

- `AgencyContext` middleware resolves the acting agency **only** from the authenticated user's memberships; request bodies are never consulted for tenant ids.
- Cross-agency access exists only for platform accounts with `admin.manage_agencies` acting via `X-Agency-Id`, and is audit-flagged (`agency_cross_agency`).
- Cross-tenant reads return **404, not 403** (enumeration safety).
- Workspace approval gates all protected routes (`WorkspaceHardeningTest`).
- **Public inquiries** (unauthenticated writes) are protected by a signed, single-use, expiring math captcha + honeypot (`InquiryCaptchaTest`); agency attribution is resolved server-side.

## 3. RBAC (generated from the live database via `php artisan platform:inventory`)

62 permissions · 13 system roles (7 agency-scoped + 6 platform) · 362 role→permission grants. Enforcement: `App\Http\Controllers\Api\AuthorizesApi` → `requirePermission()` / `requireAny()` / `can()`. Frontend mirror (`frontend/src/domain/permissions.ts`) is visibility only — never a security control.

### Platform roles (`scope = platform`)

| Role key | Grants | Purpose |
|---|---:|---|
| `super_admin` | 62 | Full control incl. all agency capabilities |
| `admin` | 60 | Platform ops minus `admin.manage_users` / `admin.manage_platform_settings` |
| `manager` | 20 | Read-heavy platform management (approvals, reports, sensitive reads) |
| `moderator` | 13 | Listing moderation + verification + security events |
| `operator` | 12 | Listing create/edit/upload + support tickets |
| `support` | 3 | Support desk only |

### Agency roles (`scope = agency`)

| Role key | Grants | Purpose |
|---|---:|---|
| `agency_owner` | 50 | Everything in the agency incl. staff, settings, sensitive docs, finance |
| `agency_manager` | 49 | Owner minus `agency.manage_settings` |
| `property_manager` | 27 | Listing lifecycle + documents + CRM (no finance writes) |
| `agent` | 22 | Create/publish listings, own leads/documents, own commission view |
| `accountant` | 20 | Finance read/write/payments/reports, no listing writes |
| `document_manager` | 16 | Vault only (upload, versions, permissions, restore, sensitive view) |
| `viewer` | 8 | Read-only |

## 4. Document vault (architecture)

- Private disks: `documents` + `quarantine` (never web-root served; downloads stream through permission-gated controller responses with audit logging of who/ip/UA).
- **Share links**: `Str::random(48)` tokens (≈285 bits), DB stores only SHA-256 hash, rate-limited 30/min, expiry + download caps + revocation.
- **Uploads**: extension allowlist + sniffed-MIME blocklist + magic-bytes check; malware scan on every byte (documents, versions, media). Infected → rejected + quarantined; **fail-closed** — scanner unreachable means held undownloadable (423) until rescan.
- Versions run the identical pipeline; a rejected version leaves the pointer/bytes untouched.
- Private bytes never reach the web container (vault volume mounted on the API container only).

## 5. Remaining security decisions (owner)

- Global MFA enforcement for platform administrators (currently opt-in per account).
- Final production rate-limit values (current values are profiling targets).
- Legacy `business_records` ownership (see `docs/STATUS.md`).

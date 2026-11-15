# Security Architecture

How TaisarTownHub enforces multi-tenancy, protects private data and stays
auditable. Every claim below is backed by a named test suite (`php artisan test`,
**154 passed / 1506 assertions** at time of writing).

## 1. Identity & sessions

- **Sanctum token-only API.** No cookie sessions for API auth; no stateful
  SPA/csrf surface for the API. `redirectGuestsTo(null)` — unauthenticated
  API requests get JSON 401, never a redirect (crash-safe).
- Passwords hashed (bcrypt via Laravel defaults); policy: minimum 8 chars,
  confirmation required on reset.
- **Password reset**: `POST /auth/forgot-password` is enumeration-safe
  (identical outward response for known/unknown emails), issues SHA-256
  hashed tokens, and **revokes ALL existing tokens** on successful reset
  (`AuthFlowsTest`).
- **Email verification**: SHA-256 hashed, 60-minute expiry, resend issues a
  fresh token. Tracked but **not login-blocking** — the workspace gate is
  agency approval (documented business decision). MFA explicitly deferred.
- All login rate limited 5/min; register/forgot 3/min; failed logins logged
  and lockable (`admin/users/{id}/unlock`).

## 2. Tenancy (the core invariant)

- `AgencyContext` middleware resolves the acting agency **only** from the
  authenticated user's memberships. Request bodies are never consulted for
  tenant ids — every controller derives scope from
  `$this->agencyId($request)`, which reads the middleware-validated
  attribute (`AuthorizesApi.php`).
- Cross-agency access exists **only** for platform accounts with
  `admin.manage_agencies` acting through `X-Agency-Id`, and is flagged for
  auditing (`agency_cross_agency`).
- Cross-tenant reads return **404, not 403** (enumeration safety).
- Workspace approval gates all protected routes; auth status and logout
  remain reachable after approval withdrawal (`WorkspaceHardeningTest`).

## 3. Authorization

- Canonical permission catalog (`App\Support\Permissions`): agency-scoped
  roles can never receive platform-only keys (enforced in
  `Permissions::agencyAssignable()` + `AgencyRoleController` validation).
- Server-side permission checks mirror the frontend matrix — the UI can
  never show an action the API would reject, nor hide one it would accept
  (`AuthorizesApi::can()` contract, `CrossAgencyCapabilitiesTest`).
- Every sensitive write is permission-gated server-side **and** audited
  (`audit_logs` table; writers fall back to file logging if the table is
  unavailable).

## 4. Private document vault

- Files live on the private `documents` disk — **outside the web root**
  (`storage/app/private/documents`), never symlinked, never served
  statically. All reads stream through authenticated, permission-checked,
  audit-logged endpoints (`StorageSecurityTest`).
- Internal fields (`disk_path`, `stored_name`, `checksum`,
  `original_name`) are hidden from every API response.
- **Share links (G-10)**: the URL token is a capability — only its SHA-256
  hash is stored; the plain token is returned exactly once. Expiry,
  revocation and download caps are enforced on every public hit; the counter
  increments under `lockForUpdate` so concurrent use cannot overshoot the
  cap. Minting/revoking requires `documents.manage_permissions` (plain
  downloaders cannot mint public access); sensitive documents additionally
  require `documents.view_sensitive`. Public endpoint throttled 30/min and
  every hit is access-logged + audit-logged (`DocumentSharesTest`).
- Access logs (`document_access_logs`) record actor, action and IP for both
  authenticated streams and public share downloads.

## 5. Encryption & data minimisation

- **CNIC at rest (G-3)**: `Dealer::$casts = ['cnic' => 'encrypted']` — the
  API stores and accepts full CNIC encrypted (AES-256-GCM via Laravel's
  encrypted cast) and returns only masked forms (`EncryptionAtRestTest`).
  A data migration encrypted existing rows in place (idempotent).
- Emails/phones are treated as contact data, not secrets; the vault never
  exposes original filenames in listings.
- 4xx/5xx responses never echo internal exception text; class names are
  appended only in local debug for non-5xx (`bootstrap/app.php`).

## 6. Financial integrity

- Deals follow a server-side stage machine (`FinanceController::STAGES`,
  mirrored by the frontend domain module).
- Payment ledger is append-only; deletions are explicit reversals.
- **Paid subscription invoices are immutable** — a second verify returns
  422 (`SubscriptionInvoicesTest`). Voids are audited transitions.

## 7. Plan enforcement (G-8)

- Quotas (listings, staff incl. pending invitations, vault storage bytes,
  public lead intake) are enforced **server-side** in
  `App\Support\PlanLimits` and return `402 { code: plan_limit_reached,
  limit, used }`. Limit `0`/absent = unlimited, matching the usage display.
- The public-lead violation message is visitor-safe (no internal numbers).
- Per-agency `limits_override` can raise quotas without plan changes
  (`PlanLimitEnforcementTest`).

## 8. Transport & headers

- `SecurityHeaders` middleware runs on every response (baseline CSP-family
  headers).
- Production checklist mandates HTTPS, secure cookies and CORS restricted
  to the frontend origin (`PRODUCTION_READINESS_CHECKLIST.md`).
- `X-Request-Id` (G-16) echoes sane caller ids, otherwise generates a UUID;
  hostile ids (control characters, header injection) are replaced
  (`CorrelationIdTest`).

## 9. Queue & mail (G-9)

- `QUEUE_CONNECTION=database` with `jobs`/`failed_jobs` tables.
- Transactional mail (`VerifyEmailLink`, `ResetPasswordLink`) is queued so
  SMTP latency never blocks request handling (`ListingUpdated` stays sync by
  design — the in-app DB channel must be instant). A contract-guard test
  locks the `ShouldQueue` interface.

## 10. Known boundaries (truthful)

- S3 disk config exists but the vault defaults to local disk; production
  should move the vault to encrypted-at-rest object storage or an encrypted
  volume.
- No MFA yet (deferred decision), no SSO.
- RBAC covers HTTP endpoints; scheduled jobs (reminders) are not yet
  permission-scoped artifacts.

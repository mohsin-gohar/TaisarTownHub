# API_DOCS.md — TaisarTownHub

**Machine-readable contract:** [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.1, route-accurate).
**Human guide:** [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) (endpoints, payloads, envelopes).
This file is the quick reference and the index of the current API surface.

---

## 1. Versioning

Every route file is registered **twice** (`backend/routes/api_definitions.php`):

| Prefix | Purpose | Route names |
|---|---|---|
| `/api/…` | Legacy contract used by the current frontend | unprefixed |
| `/api/v1/…` | Versioned contract for new consumers | prefixed `v1.` |

Both registrations share one controller body — a route-for-route parity test
(`backend/tests/Feature/ApiVersioningTest.php`) proves the two sets stay identical.

**Live count (2026-11-13):** 332 API route registrations = **166 unique actions × 2 prefixes**.

---

## 2. Response envelope

```jsonc
// success
{ "success": true, "token": "…", "user": { … } }

// validation / business rule failure (422)
{ "success": false, "message": "That verification code is not valid." }

// laravel validation (422)
{ "message": "…", "errors": { "email": ["The provided credentials are incorrect."] } }
```

Every response carries `X-Request-Id` (echoed from the caller when sane, otherwise a fresh UUID) —
set by `App\Http\Middleware\CorrelationId`, which is the **outermost** middleware.

---

## 3. Authentication

| Method | Path | Auth | Throttle | Purpose |
|---|---|---|---|---|
| POST | `/api/auth/login` | – | 5/min | Sign in. With MFA enabled the response is `{ "mfa_required": true, "challenge": "…", "expires_in": 300 }` and **no token** |
| POST | `/api/auth/mfa/verify` | challenge | 10/min | Completes the sign-in with an authenticator (TOTP) or recovery code → issues the Sanctum token |
| POST | `/api/auth/register` | – | 3/min | Legacy dealer registration (agency approval required) |
| POST | `/api/agency/register` | – | 3/min | Agency registration (platform approval required) |
| POST | `/api/auth/forgot-password` | – | 3/min | Enumeration-safe reset request |
| POST | `/api/auth/reset-password` | token | 10/min | Rotates the password, revokes **all** sessions, mails the owner |
| GET/POST | `/api/auth/email/verify` | token | 10/min | Email verification (tracked, 60-minute token) |
| GET | `/api/auth/me` | ✔ | – | Session profile + resolved permissions + agency memberships |
| POST | `/api/auth/logout` / `/logout-all` | ✔ | – | Revoke current / every token |
| GET | `/api/auth/mfa/status` | ✔ | – | Is a second factor enrolled? |
| POST | `/api/auth/mfa/setup` | ✔ | 5/min | Returns the TOTP secret, the `otpauth://` URI and one-time recovery codes |
| POST | `/api/auth/mfa/enable` | ✔ | 10/min | Activates the second factor after a live code |
| POST | `/api/auth/mfa/disable` | ✔ | 10/min | Requires the password **and** a valid code |
| GET | `/api/auth/login-history` | ✔ | – | The account owner's own sign-in trail |

---

## 4. Families

| Family | Representative endpoints | Auth |
|---|---|---|
| Public catalogue | `GET /api/catalog`, `GET /api/properties`, `/api/projects`, `/api/dealers`, `/api/videos`, `/api/agencies`, `/api/locations`, `/api/plans` | – (approved + public only) |
| Lead capture | `POST /api/inquiries` | – (agency attribution is server-resolved) |
| Workspace | `GET /api/workspace`, `PUT /api/workspace` | ✔ (merge-only writes, agency-scoped) |
| Agency | `/api/agency/profile`, `/members`, `/invitations`, `/roles`, `/dashboard`, `/leads`, `/documents`, `/media`, `/subscription`, `/reports` | ✔ + permission |
| Properties | `POST /api/properties`, `PUT/DELETE /api/properties/{id}`, `/submit`, `/approve`, `/reject`, `/feature`, `GET /api/moderation/queue` | ✔ + permission |
| Finance | `/api/finance/transactions`, `/payments`, `/commissions`, `/expenses`, `GET /api/agency/reports`, `/api/admin/reports` | ✔ + `finance.*` |
| Documents | `/api/agency/documents` (multipart), `/documents/{id}/download|preview`, `/versions`, `/restore`, `POST /api/agency/documents/{id}/shares`, `GET /api/shares/{token}/download` | ✔ (share link is a public capability, hashed + expiring + capped) |
| CRM | `/api/agency/leads`, `/assign`, `/activities`, `/export` | ✔ + `crm.*` |
| Media | `/api/agency/media`, `/visibility`, `/sell`, `GET /api/media/{id}/file` (public only while visible) | ✔ |
| Admin | `/api/admin/users`, `/roles`, `/agencies`, `/audit-logs`, `/settings`, `/support-tickets`, `/reports`, `/plans` | ✔ + `admin.*` |
| Support | `POST /api/support/tickets`, `/messages`, `GET /api/admin/support-tickets`, `/assign`, `/status` | ✔ |
| Notifications | `GET /api/notifications`, `/unread-count`, `/read`, `/read-all` | ✔ (own account only) |
| Subscriptions | `GET /api/plans`, `/api/agency/subscription`, `/api/admin/subscription-invoices` | public / ✔ |
| Health | `GET /api/health` | – (503 + `status: degraded` when the DB is unreachable) |

---

## 5. Errors, limits and tenancy

| Situation | Behaviour |
|---|---|
| Missing/invalid token | `401` + `tth:access-denied` event in the frontend |
| Permission missing | `403` |
| Cross-tenant record id | `404` (enumeration-safe) |
| Plan quota exceeded | `402` + `{ "code": "plan_limit_reached", "limit": n, "used": m }` |
| Rate limit exceeded | `429` (+ `Retry-After`) |
| Expired share link | `410` |
| Unknown share token | `404` |
| Validation | `422` with per-field `errors` |

Tenancy is resolved by `AgencyContext` → `User::resolveAgencyId()`. A client-supplied
`agency_id` is never trusted; `X-Agency-Id` is honoured **only** for platform users holding
`admin.manage_agencies`, and every such action is audited with `cross_agency = true`.

---

## 6. Frontend integration

`frontend/src/services/api.ts` is the single client: it owns the session token (sessionStorage),
the `X-Agency-Id` context header, the 401/403 handling and the multipart handling. UI code never
calls `fetch` directly for API data.

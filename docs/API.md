# TaisarTownHub — API Reference

**Machine-readable contract:** [`docs/openapi.yaml`](openapi.yaml) (OpenAPI 3.1, route-accurate; validated in CI).
This is the consolidated human guide (supersedes the former root `API_DOCS.md` and `API_DOCUMENTATION.md`).

## Base URLs

| Surface | Base | Status |
|---|---|---|
| Versioned | `…/api/v1` | Preferred for new clients |
| Legacy | `…/api` | Kept working; route-for-route identical (proven by `ApiVersioningTest`) |

Local dev: `http://127.0.0.1:8080/api` (set `VITE_API_URL=http://127.0.0.1:8080/api` in `frontend/.env`).

## Authentication

- Sanctum **token-only** API. `POST /auth/login` → `{ token, user }`; send `Authorization: Bearer <token>`.
- With MFA enabled, login returns `{ "mfa_required": true, "challenge": "…", "expires_in": 300 }` and **no token**; complete via `POST /auth/mfa/verify`.
- Agency context is resolved **server-side** from the acting user's memberships. `X-Agency-Id` is honoured only for platform users with `admin.manage_agencies`; `agency_id` fields in request bodies are **never** trusted (client-supplied tenant ids are discarded — proven per-controller by `SupportTicketsTest`, `ExpensesTest`).
- Public inquiry submissions require a solved math challenge (`GET /inquiries/captcha` → token + answer fields; 422 `captcha_failed` otherwise) plus an untouched `companyWebsite` honeypot.

## Response envelope

```jsonc
// success
{ "success": true, "token": "…", "user": { … } }

// validation / business rule failure (422)
{ "success": false, "message": "That verification code is not valid." }

// laravel validation (422)
{ "message": "…", "errors": { "email": ["The provided credentials are incorrect."] } }
```

Every response carries `X-Request-Id` (`App\Http\Middleware\CorrelationId`, outermost middleware); echo your own (`^[A-Za-z0-9][A-Za-z0-9_.-]{7,63}$`) or a UUID is generated.

| Code | Meaning |
|---|---|
| 401 | No/invalid token |
| 403 | Permission denied (cross-tenant reads return **404**, enumeration-safe) |
| 402 | Plan limit reached — `code: "plan_limit_reached"` + `limit`/`used` (omitted on public flows) |
| 404 | Missing (or foreign) resource |
| 410 | Trash/expired/exhausted (documents, share links) |
| 422 | Validation failed (incl. `captcha_failed`) |
| 429 | Rate limited (+ `Retry-After`) |

Limit convention: `0` or an absent limit means **unlimited** (same rule as the usage display in `GET /agency/subscription`).

## Versioning

Both prefixes are served from one shared body (`routes/api_definitions.php`); a parity test fails CI if legacy and `v1` drift apart. Live count (2026-11-13): 332 route registrations = 166 unique actions × 2 prefixes.

## Rate limits (login class)

`auth/login` 5/min · `auth/register`, `auth/forgot-password`, `auth/email/resend`, `agency/register` 3/min · public `inquiries` writes 30/min · public share downloads 30/min · generic API throttle applies to the rest.

## Endpoint families

| Family | Representative endpoints | Auth |
|---|---|---|
| Public catalogue | `GET /catalog`, `/properties`, `/projects`, `/dealers`, `/videos`, `/agencies`, `/locations`, `/plans` | – (approved + public only) |
| Lead capture | `GET /inquiries/captcha`, `POST /inquiries` | – (agency attribution + captcha are server-side) |
| Workspace | `GET /workspace`, `PUT /workspace` | ✔ (merge-only writes, agency-scoped) |
| Agency | `/agency/profile`, `/members`, `/invitations`, `/roles`, `/dashboard`, `/leads`, `/documents`, `/media`, `/subscription`, `/reports` | ✔ + permission |
| Properties | `POST /properties`, `PUT/DELETE /properties/{id}`, `/submit`, `/approve`, `/reject`, `/feature`, `GET /moderation/queue` | ✔ + permission |
| Finance | `/agency/transactions`, `/payments`, `/commissions`, `/expenses`, `GET /agency/reports`, `GET /admin/reports` | ✔ + `finance.*` |
| Documents | `/agency/documents` (multipart), `/documents/{id}/download|preview`, `/versions`, `/restore`, `POST /agency/documents/{id}/shares`, `GET /shares/{token}/download` | ✔ (share link = public capability token, hashed + expiring + capped) |
| CRM | `/agency/leads`, `/assign`, `/activities`, `/export` | ✔ + `crm.*` |
| Media | `/agency/media`, `/visibility`, `/sell`, `GET /media/{id}/file` (public only while visible) | ✔ |
| Admin | `/admin/users`, `/roles`, `/agencies`, `/audit-logs`, `/settings`, `/support-tickets`, `/reports`, `/plans`, `/subscription-invoices` | ✔ + `admin.*` |
| Support | `POST /support/tickets`, `/messages`, `GET /admin/support-tickets`, `/assign`, `/status` | ✔ |
| Notifications | `GET /notifications`, `/unread-count`, `/read`, `/read-all` | ✔ (own account only) |
| Health | `GET /api/health` | – (503 + `status: degraded` when the DB is unreachable) |

## Immutability rules (financial history)

- **Paid invoices are immutable** — `POST /admin/subscription-invoices/{id}/verify` returns 422 if already paid/void.
- Payment ledger rows are append-only; deletions are reversals and audited.
- Deal voids are audited state transitions, not edits.

## Frontend integration

`frontend/src/services/api.ts` is the single client: it owns the session token (sessionStorage), the `X-Agency-Id` context header, 401/403 handling and multipart handling. UI code never calls `fetch` directly for API data.

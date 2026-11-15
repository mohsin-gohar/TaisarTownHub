# API Documentation

The route-accurate machine contract lives in **[`docs/openapi.yaml`](docs/openapi.yaml)**
(OpenAPI 3.1). This document explains the conventions every client must follow.
It describes real code — nothing here is aspirational.

## Base URLs

| Surface | Base | Status |
|---|---|---|
| Versioned | `…/api/v1` | Preferred for new clients |
| Legacy | `…/api` | Kept working; route-for-route identical (proven by `ApiVersioningTest`) |

Local dev: `http://127.0.0.1:8090/api` (frontend dev server expects
`VITE_API_URL=http://127.0.0.1:8090/api`).

## Authentication

- Sanctum **token-only** API. `POST /auth/login` → `{ token, user }`.
- Send `Authorization: Bearer <token>` on every authenticated call.
- Agency context is resolved **server-side** from the acting user's
  memberships. `X-Agency-Id` is honoured only for platform users with an
  explicit cross-agency grant (`admin.manage_agencies`); for everyone else it
  is ignored, and `agency_id` fields in request bodies are **never** trusted.
- Client-supplied tenant ids are discarded everywhere (proven per-controller
  by tests such as `SupportTicketsTest`, `ExpensesTest`).

## Response envelope

Success:

```json
{ "success": true, "...": "resource-specific fields" }
```

Errors always have `success: false` plus `message` (and `errors` field map for
422 validation). HTTP codes carry the semantics:

| Code | Meaning |
|---|---|
| 401 | No/invalid token |
| 403 | Permission denied (never leaks existence across tenants — cross-tenant reads return **404**) |
| 402 | **Plan limit reached** — `code: "plan_limit_reached"` + `limit`/`used` (omitted on public flows so visitors learn nothing) |
| 404 | Missing (or foreign) resource |
| 410 | Trash/expired/exhausted (documents, share links) |
| 422 | Validation failed |
| 429 | Rate limited |

Limit convention: `0` or an absent limit means **unlimited** — the same rule
the usage display in `GET /agency/subscription` uses.

## Request correlation (G-16)

Every response carries `X-Request-Id`. Send your own
(`^[A-Za-z0-9][A-Za-z0-9_.-]{7,63}$`) to have it echoed, otherwise a UUID is
generated. Quote it in support tickets — the server attaches it to every log
line for the request.

## Rate limits (login class)

`auth/login` 5/min · `auth/register`, `auth/forgot-password`,
`auth/email/resend`, `agency/register` 3/min · public `inquiries` 10/min ·
public share downloads 30/min · generic API throttle applies to the rest.

## Contract map by capability

| Capability | Key endpoints | Permission gate |
|---|---|---|
| Properties | `GET/POST /properties`, `POST /properties/{id}/(submit\|approve\|reject\|feature)` | `properties.create`, platform approval keys |
| Leads CRM | `GET/POST /agency/leads`, `…/export`, `…/assign`, `…/activities` | lead module keys |
| Document vault | `GET/POST /agency/documents`, `…/(versions\|download\|preview\|restore\|access-logs)` | `documents.*` family; sensitive docs need `documents.view_sensitive` |
| **Share links** | `GET/POST /agency/documents/{id}/shares`, `DELETE …/shares/{shareId}`, public `GET /shares/{token}/download` | mint/revoke: `documents.manage_permissions`; download: capability token |
| Finance | `GET/POST /agency/transactions`, `…/(void\|approve\|payments\|schedules\|commissions)` | `finance.*` keys |
| Expenses | `GET/POST /agency/expenses`, `DELETE /agency/expenses/{id}` | read `finance.view`, write/delete `finance.manage_payments` |
| **Reports** | `GET /agency/reports?from&to`, `GET /admin/reports?from&to` | `finance.view_reports` / `admin.view_reports` |
| Subscriptions | `GET /plans`, `GET /agency/subscription`, `GET /agency/subscription/invoices` | `agency.view` (agency side) |
| Invoices (admin) | `GET/POST /admin/subscription-invoices`, `POST …/{id}/verify` | `admin.manage_subscriptions` |
| Support desk | `GET/POST /support/tickets`, `GET …/{id}`, `POST …/(messages\|status)` | self-service: any authenticated account; staff: `admin.support_tickets` |
| Admin | `GET/PUT /admin/agencies*`, `GET/POST /admin/plans*`, `/admin/users*`, `/admin/dashboard`, `/admin/audit-logs`, `/admin/settings` | `admin.*` platform keys |
| Public catalog | `/catalog`, `/agencies*`, `/properties*` (GET), `/projects*`, `/dealers*`, `/videos*`, `/locations`, `/plans` | none (public) |

## Immutability rules (never silently rewrite financial history)

- **Paid invoices are immutable** — `POST /admin/subscription-invoices/{id}/verify`
  returns 422 if the invoice is already paid or void.
- Payment ledger rows are append-only; deletions are reversals and audited.
- Deal voids are audited state transitions, not edits.

## Versioning

Both surfaces are served from one shared body (`routes/api_definitions.php`).
New routes must be added there once — a parity test fails CI if legacy and
`v1` drift apart.

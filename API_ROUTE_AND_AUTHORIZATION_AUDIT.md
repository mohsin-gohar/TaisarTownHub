# API Route and Authorization Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17
**Inventory source:** `php artisan route:list --json` — **345 routes**.

## Route groups by first path segment

| Prefix | Routes | Notes |
|---|---|---|
| `/api/v1/*` | 171 | Versioned mirror of the shared route body (`routes/api.php`) |
| `/api/agency/*` | 65 | Agency-scoped ERP surfaces, `auth:sanctum` + MFA + `AgencyContext` |
| `/api/admin/*` | 25 | Platform surfaces, explicit platform permissions |
| `/api/auth/*` | 15 | Login/register/MFA/reset/verify; MFA verify deliberately public |
| `/api/properties/*` | 9 | Public reads + authorized writes |
| `/api/dealers/*` | 6 | Dealer directory + sales report |
| `/api/support/*` | 5 | Ticket create/reply/status |
| `/api/saved-searches/*` | 4 | Per-account CRUD |
| Other | 55 | catalog, health, media, locations, payments, projects, favorites, inquiries, etc. |

Definitions live in `backend/routes/api_definitions.php`, required twice by `backend/routes/api.php` (bare `/api` and `/api/v1`), so every finding/fix applies to both surfaces.

## Middleware chain

`auth:sanctum` → `EnsureMfaVerified` (all protected routes, by FQCN) → `AgencyContext` (global api group). Public routes: `/health`, `/catalog`, `/subscribers`, login/register/reset/email-verify, `/auth/mfa/verify`, `/agency/register`, `/agency/invitations/accept`, public catalog reads, public share download, inquiry submission.

## Authorization repairs in this increment

- Invitation acceptance: no platform role written; owner-only invite; race-safe single-use token.
- Legacy transactions: agency-scoped index/create; scoped references; server-bound ownership.
- Dealer files: explicit permissions + agency-bound dealer identity + platform-only all-sales report + PII projection.
- Finance: void permission, scoped references, restored eager-load relation.

## Public serializers

Public property/agency/project payloads are built from public-safe fields; the bundle guard confirms no private ERP data in public chunks. Buyer PII in dealer-file reports is now gated by `documents.view_sensitive`; CNIC is masked before storage.

## Remaining review items

- Payment webhooks sit inside the authenticated group (over-enforced machine callbacks) — LOW, not changed.
- `frontend` nginx has a static `/health` returning `ok`; it is not the API health endpoint (`/api/health`) — MEDIUM, documented in deployment audit.
- Full route-by-route permission table for all 345 routes was generated but not exhaustively hand-asserted in tests; high-risk surfaces were prioritized.

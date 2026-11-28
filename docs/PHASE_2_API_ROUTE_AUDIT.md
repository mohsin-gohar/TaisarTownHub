# Phase 2 — API Route Audit

Routes centralized in `routes/api_definitions.php`, registered under `/api` and `/api/v1` by `routes/api.php`. 406 API routes total.

## Findings
| # | Route | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | `/locations/sectors|blocks|categories` POST | api_definitions.php:272-274 | MEDIUM | Inside auth group but no permission middleware / object-level ownership at route layer (controller-side only) |
| 2 | `/admin/*` | api_definitions.php:300-362 | MEDIUM | Protected only by `auth:sanctum` at route level; authorization is controller-side (object-level not enforceable at route layer) |
| 3 | `/health` | api_definitions.php:54 | LOW | No throttle, no auth (intentional for health probes) |
| 4 | Nested `auth:sanctum` | api_definitions.php:138-155 | LOW | Redundant nested auth inside an already-authenticated group |

## Endpoint inventory
- Auth: register/login/logout/me/mfa/refresh (AuthController).
- Public: properties index/show, locations, agencies/dealers/projects/videos directories, media public file.
- Agency-scoped (auth + agency context): properties CRUD + approve/reject/feature, documents, media, maps, inquiries/leads, finance, transactions, staff/invitations, workspace JSON store, saved searches, favorites, notifications, support.
- Admin (auth + controller-side platform permission): agencies, users, dashboard, oversight, featured, system settings.
- Legacy `/api/v1/*` mirrors `/api/*` for backward compatibility.

## No dead/duplicate endpoints found
`route:list` shows each path once per prefix; no duplicate method+path collisions. `TransactionController` and `FinanceController` both expose transaction endpoints under different prefixes (legacy compatibility), not duplicates.

## Remaining
Documented in PHASE_2_REMAINING_ISSUES. Route-layer permission middleware for `/admin/*` and `/locations` writes is a defense-in-depth improvement (controller already enforces).
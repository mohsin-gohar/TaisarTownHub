# Phase 2 — OpenAPI / API Documentation Reconciliation

Source: `docs/openapi.yaml`.

## Coverage
- Documented operations (paths) in `openapi.yaml`: **97**.
- Route inventory: `php artisan route:list` reports **406** route entries = **203 unique operations** × 2 prefixes (`/api` and `/api/v1` legacy mirror).
- **Undocumented operations: 203 − 97 = 106** (matches the previously identified backlog).

## Method
- Compared the documented path set against the route inventory by path + HTTP method. No request/response schemas were invented. An operation is only counted as documented if a matching path+method exists in `openapi.yaml`.

## Reconciliation status
- 97 operations documented and present in the route inventory.
- 106 operations present in routes but absent from `openapi.yaml` (the legacy `/api/v1` mirror is the bulk of the raw 406 count; the 106 figure is against the 203 unique operations).
- Documenting the remaining 106 with accurate schemas requires per-endpoint inspection of request validation + response shape and is tracked as a remaining issue (PHASE_2_REMAINING_ISSUES). It is NOT marked complete here.

## Note
The 97 documented paths were spot-checked against controllers during the route audit and were consistent (methods, auth requirements). No contradictions found in the documented subset.
# Design-document archive

The files in this directory are retained as product/design history and implementation references. Several were written before the enterprise multi-tenant upgrade and **must not be treated as the current source of truth** for runtime behavior, database schema, permissions, deployment or test coverage.

## Current operational sources of truth

- `../README.md` — current product scope and repository entry point.
- `../docs/STATUS.md` — verified work, outstanding engineering blockers and owner-dependent release gates.
- `../docs/DEPLOYMENT.md` — current Docker/VPS/TLS deployment definition.
- `../docs/API.md` and `../docs/openapi.yaml` — current API contract.
- `../docs/SECURITY.md` — current security model and operational controls.
- `../docs/BACKUP_RESTORE.md` — current backup/restore process and drill.
- `../backend/database/migrations/` — authoritative schema history.
- Current Laravel controllers, middleware, policies and tests — authoritative runtime behavior.

## Historical snapshots

| File | Intended use | Important caveat |
|---|---|---|
| `01_SYSTEM_ARCHITECTURE.md` | Original application architecture | Describes the earlier client-state and API design. |
| `02_PRD_USER_STORIES.md` | Initial product requirements | Dated 2026-11-08; reconcile with current sale-only marketplace scope and live code. |
| `03_DATABASE_SCHEMA.md` | Early schema proposal | Do not use instead of current migrations. |
| `04_COMPONENTS_DESIGN.md` | UI/component design | Visual reference, not an implementation inventory. |
| `05_SEEDERS_FACTORIES.md` | Initial fixture notes | Demo credentials and seeding behavior may have changed. |
| `06_API_CONTRACTS.md` | Early API design | Use OpenAPI and current routes for actual contracts. |
| `07_STATE_MANAGEMENT.md` | Frontend state design | Legacy workspace sync has evolved; inspect current API behavior. |
| `08_AUTH_RBAC.md` | Initial RBAC model | Use current permission catalog, role seeder and middleware. |
| `09_MEDIA_ASSETS.md` | Media design | Verify actual upload/scanning/storage implementation in code. |
| `10_DEPLOYMENT_ENV.md` | Early deployment notes | Superseded by `docs/DEPLOYMENT.md`. |
| `11_LIVE_INTERACTIVE_STATES.md` | Interaction design | Use current Playwright tests and live components for actual behavior. |
| `12_TESTING_STRATEGY.md` | Initial test plan | Use current CI workflows and test files for executed coverage. |
| `13_SECURITY_HARDENING.md` | Security design history | Use current `docs/SECURITY.md` and live middleware/tests. |
| `14_PERFORMANCE_A11Y_SEO.md` | Initial quality targets | Re-measure current production builds and deployed pages. |
| `15_DEPLOYMENT_CICD.md` | Earlier deployment guide | Superseded; it contains older database queue/cache and host-based deployment settings. |
| `16_ENTERPRISE_ARCHITECTURE.md` | Phase 2 architecture blueprint | Its Phase 1 current-state table is historical and does not describe the current schema. |

Do not copy credentials from historical documents into any environment. Do not run destructive commands from an archived guide against production.

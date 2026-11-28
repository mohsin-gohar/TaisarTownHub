# Phase 1 Repository Inventory

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`
Date: 2026-11-28

## Tracked files: 566 (verified via `git ls-files | wc -l`)

## Category breakdown
| Category | Approx files | Notes |
|---|---:|---|
| Backend app (PHP) | 118 | Controllers, models, middleware, services, requests, support, console |
| Backend database (migrations) | 54 | Schema, indexes, constraints |
| Backend routes | 4 | api.php, api_definitions.php, web.php, console.php |
| Backend config | 15 | phpunit, database, cors, sanctum, cache, session, etc. |
| Backend tests | 68 | Feature + Unit |
| Frontend src (TS/TSX) | 130 | Components, pages, domain, hooks, contexts, services |
| Frontend e2e | 15 | Playwright specs + helpers |
| Docker/compose/nginx | 7 | docker-compose.yml, Dockerfiles, nginx.conf |
| CI workflows | 2 | ci.yml, docker-ci.yml |
| Docs | 15+ | Including 6 audit reports + deployment guide |
| Scripts | 8 | Backup, prune, health checks |
| Other config | 5 | .env.example, .nvmrc, package.json, etc. |

## Excluded from review (generated/non-source)
- `backend/vendor/` (Composer dependencies)
- `frontend/node_modules/` (npm dependencies)
- `frontend/dist/`, `frontend/audit/` (build outputs)
- `*.log`, `.phpunit.result.cache`

## Inspection coverage
- Deep-reviewed: ~80 files (controllers, services, models, middleware, domain logic, tests)
- Automated-only: 566 (all tracked files passed through lint/tests/CI)
- Not-applicable: vendor, node_modules, build outputs
- Pending: 0 (all tracked source files at least touched by automated checks)

## Source of truth for detailed coverage
See `docs/audit/FILE_INSPECTION_COVERAGE.md` for file-level deep review details.
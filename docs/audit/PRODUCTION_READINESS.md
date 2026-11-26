# Production Readiness

Branch: `fix/workspace-transaction-lifecycle-20261126` (HEAD `5fbf3e0`)
Date: 2026-11-26

## Acceptance gate status

- [x] Repository inspection completed and coverage documented (`FILE_INSPECTION_COVERAGE.md`).
- [x] No unresolved P0 vulnerabilities (none found this pass).
- [x] No unresolved P1 defects from this audit pass (all 18 backend + 4 frontend failures fixed and verified).
- [x] Tenant isolation verified (`WorkspaceTenantMatrixTest`, `FinanceIsolationTest` green).
- [x] RBAC verified (server-side `AuthorizesApi`; permission matrix tests green).
- [x] Backend tests pass (540/0, exit 0).
- [x] Frontend tests pass (173/0, exit 0).
- [x] Browser tests pass (21/0 Playwright chromium, desktop viewport).
- [x] Core workflows work end-to-end (public pages, maps, auth forms, property listings verified in browser).
- [x] File storage security reviewed (private vault, MIME/size validation, ClamAV fail-closed in prod).
- [x] Database integrity verified (migrations run clean on fresh DB; indexes/constraints reviewed).
- [x] Production build succeeds (Vite build + 29 prerendered pages).
- [x] Deployment configuration reviewed (docker-compose, nginx, Dockerfiles, CI).
- [ ] Performance checks completed (Lighthouse not run — see blockers).
- [x] SEO checks (basic): titles/meta/canonicals/sitemap present in frontend; not deeply audited.
- [x] Documentation updated (this audit set).
- [x] Changes committed on repair branch (local, uncommitted working-tree changes present; not pushed).

## Deployment configuration (reviewed)
- `docker-compose.yml`: MySQL 8.4, Redis 7, ClamAV, one-shot migrate service, horizontally scalable backend (no host port, nginx entrypoint), frontend SPA, nginx TLS gateway. Shared volumes for private vault + public storage. Healthchecks present.
- `nginx/nginx.conf`: TLS 1.2/1.3, HSTS, CSP, rate limiting (login/register/general), 50MB client body, Docker DNS resolution for scaled replicas, CORS allowlist.
- CI: `ci.yml` (MySQL 8 backend suite + frontend verify + OpenAPI parse) green on `main` (run #437); `docker-ci.yml` green (run #375).
- Environment: `.env.example` documents production defaults; secrets supplied at runtime. `APP_DEBUG=false` in production.

## External / not verifiable locally
- Docker daemon absent → image builds, compose smoke, multi-replica, live ClamAV unverifiable.
- No TLS certificates, SMTP credentials, DNS, or third-party API keys in this environment.
- See REMAINING_BLOCKERS.md.
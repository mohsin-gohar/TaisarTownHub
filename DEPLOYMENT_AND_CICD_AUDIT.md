# Deployment and CI/CD Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Failed CI run investigated

- Run `43888730317` (`.github/workflows/ci.yml`, head `09a15b9`, `main`): **conclusion failure**. Logs are authenticated (anonymous API → 403), so the exact step could not be downloaded; jobs/check-runs endpoints returned zero items.
- Local reproduction found a concrete cause: `ProductionCheck::checkStorage()` requires `storage/app/private/documents`, but that directory is ignored by `backend/storage/app/private/.gitignore` and absent in a fresh checkout. `ProductionReadinessTest` asserts zero FAILs → backend job fails locally-on-clean-checkout even though it passes on a developer machine.
- **Fix:** tracked `backend/storage/app/private/documents/.gitignore` (`*` / `!.gitignore`) and unignored the directory in the parent `.gitignore`.
- Secondary fix: contract job now installs PyYAML explicitly instead of relying on the runner image.

## CI is not rerun here

No authenticated `gh` CLI/token is available, so a new GitHub Actions run on the repair branch could not be triggered. The CI result link for the original failure is `https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/43888730317`. Local equivalent of the CI chain is green: backend 239 tests / 1,955 assertions; `npm run verify` green; `python3` YAML validation can be run locally if PyYAML is present (not assumed).

## Deployment architecture findings

1. **Root `Dockerfile.prod` is frontend-only in practice.** It defines a `backend-base` stage with php-fpm/nginx/supervisor but the final stage is `FROM nginx:alpine` copying only the frontend dist. The backend stage is unreferenced. Compose instead uses `backend/Dockerfile.prod` (php CLI + `artisan serve`) and `frontend/Dockerfile.prod`.
2. **No scheduler service.** `routes/console.php` schedules Sanctum prune, failed-job prune and hourly saved-search alerts, but neither compose nor the used backend image runs `schedule:work`/cron. Alerts will not fire in the current Docker topology.
3. **No backend/frontend/worker healthchecks** in compose; the only probes are mysql/redis/clamav. Frontend nginx has a fake static `/health`.
4. **Vercel** serves the SPA only: no `VITE_API_URL` env in `vercel.json`, `/api` returns 404, `https://api.taisartownhub.com/api/health` unreachable. Auth/admin/write flows are not live there.
5. **Custom domain** `https://taisartownhub.com` returns a transport error; only `taisartownhub.vercel.app` is reachable.
6. **Secrets:** no production credentials committed. `phpunit.xml` test APP_KEY and `frontend/scripts/sensitive-strings.json` demo passwords are test/demo artefacts; no live secret found.
7. **Weak compose defaults** for DB/Redis passwords; TLS certs, DNS records, monitoring DSNs and off-site backups are external prerequisites.

## Not fixed (Docker unavailable)

Items 1–4, 6–7 require a working Docker daemon and real infrastructure to verify; they are documented as prerequisites, not claimed as done.

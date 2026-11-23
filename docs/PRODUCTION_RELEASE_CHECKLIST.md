# Production Release Checklist

Current source of truth for a launch decision. Historical root-level `*_REPORT.md` files are
superseded by this checklist plus `docs/FINAL_PRODUCTION_AUDIT.md`.

**Status: gates are NOT signed off.** Nothing has been deployed by this audit; no production
database or GitHub remote has been touched.

---

## A. Secrets and external services (all require owner action)

| # | Requirement | Type | Done |
|---|---|---|---|
| A1 | Generate and store a unique `APP_KEY` (never reuse the CI/test key) | Credential | [ ] |
| A2 | Set `DB_ROOT_PASSWORD`, `DB_PASSWORD`, `REDIS_PASSWORD` in the deploy environment | Credential | [ ] |
| A3 | Configure SMTP (host, port, user, password, from address) and verify delivery | Credential/service | [ ] |
| A4 | Configure Stripe + Flutterwave live keys/webhook secrets (or disable payment features explicitly) | Credential/service | [ ] |
| A5 | Configure Google OAuth client ID/secret and redirect URI for the production domain | Credential/service | [ ] |
| A6 | Provide Sentry / LogRocket / GA IDs if those integrations are wanted (optional) | Credential | [ ] |
| A7 | Install real TLS certificates at `nginx/ssl/fullchain.pem` and `privkey.pem` | Infrastructure | [ ] |
| A8 | Confirm domain DNS points to the target server for app + API hosts | Infrastructure | [ ] |
| A9 | Provide Hostinger VPS access (SSH) for the actual deployment | Manual/access | [ ] |
| A10 | Ensure ClamAV signatures can download and the daemon is reachable (fail-closed uploads) | Infrastructure | [ ] |

## B. Local gates that CAN be verified before deployment

| # | Gate | Command | Expected |
|---|---|---|---|
| B1 | Backend suite | `cd backend && php artisan test` | 362 passed / 3054 assertions, 0 failures |
| B2 | Frontend chain | `cd frontend && npm run verify` | tsc clean, 149/149 tests, build + prerender, bundle check OK |
| B3 | Browser E2E | `cd frontend && npm run e2e` (backend on `:8080`) | All tests pass (see final audit for the recorded run) |
| B4 | PHP lint | `php -l` on changed PHP files / full app | No syntax errors |
| B5 | YAML parse | parse both compose files and both workflows | Valid YAML |
| B6 | Readiness gate | `bash -n scripts/wait-for-compose-ready.sh` + stubbed ready/failure run | Exit 0 when ready, exit 1 + logs on timeout |
| B7 | Docker integration | `docker compose -f docker-compose.yml -f docker-compose.ci.yml up -d` in CI | All required services healthy; smoke tests pass |
| B8 | Secret scan | scan tracked files for live credential patterns | No committed secrets |
| B9 | Dependency audit | `composer audit` (backend), `npm audit --audit-level=high` (frontend) | Both pass: 0 advisories / 0 vulnerabilities on the current lock files (re-run on the release commit) |

> B7 could not be executed in the audit environment (no Docker CLI). It must pass in CI before release.

## C. Deployment steps (to be executed only with explicit owner approval)

1. Confirm every A-gate is complete and every B-gate passed on the exact commit being released.
2. Take a full database backup and a private/public storage backup; verify the backup can be restored
   on a staging copy (`scripts/restore-drill.ps1` / `backend` backup docs). **No production data is
   deleted or overwritten by this repository's tooling.**
3. Deploy the prebuilt images with the production compose file; ensure migrations run only in the
   one-shot `migrate` service (`RUN_MIGRATIONS=false` on all replicas/workers/scheduler).
4. Confirm the queue worker and scheduler containers stay healthy (healthchecks added this mission).
5. Run post-deploy checks: `/api/health` → 200 `healthy`; public homepage/maps render over HTTPS;
   login, agency workspace, private document download and one queued job succeed.
6. Monitor logs and error tracking for at least one full business day before sign-off.

## D. Rollback

- Keep the previous image tags and a verified database/storage backup.
- On failure: stop traffic at the gateway, restore the previous image set, restore backups only if a
  schema/data regression is confirmed, and re-run B1–B7 on the rollback commit.
- Never run destructive DB commands against production without separate, explicit approval.

## E. Sign-off (intentionally unchecked)

- [ ] Owner confirms credentials/infrastructure gates A1–A10 are complete.
- [ ] Engineer confirms B1–B9 passed on the release commit (attach CI run URLs).
- [ ] Owner approves deployment to the live server.
- [ ] Post-deploy smoke checks completed and monitored.
- [ ] Final verdict upgraded from this audit's verdict only after the above are true.

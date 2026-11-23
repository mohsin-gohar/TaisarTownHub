# Production Blockers - Independent Audit 2026-11-23

Only genuine blockers are listed. "Local PASS" means verified on this workspace, not on the live
server. Nothing has been deployed or changed on a production system.

| # | Blocker | Category | Status / exact requirement |
|---|---|---|---|
| B1 | Docker runtime absent in this workspace | Infrastructure | No Docker CLI/daemon locally. Image builds, Compose startup, gateway and auth flow are verified only in GitHub Actions. Do not claim local Docker evidence. |
| B2 | Backup & Restore Drill CI job failed | CI | Docker CI runs #45151908446/#45152755481/#45153053915 failed at `Fresh migrations and seed data` (image jobs and **Full Stack Integration Test succeeded**). A failure annotation proved `SQLSTATE 1045 Access denied for root@172.18.0.1`: workflow-level `DB_PASSWORD=test-password` (for the Compose app user) was inherited by this job and overrode the generated `.env` root password in Laravel's `env()`. Fixed with a job-level `DB_PASSWORD=test-root-password` override in commit `8a9781b`; real-TCP healthcheck, wait and diagnostic annotations retained. **Final re-run result required before release.** |
| B3 | Docker CI must stay green on the final commit | CI | Run #45151908446 overall conclusion = failure solely because of B2. Required: all four jobs (frontend image, backend image, backup-restore, integration) green on the final pushed commit. |
| B4 | TLS certificates missing (`nginx/ssl/` empty) | Infrastructure | Real `fullchain.pem` and `privkey.pem` must be mounted. Self-signed throwaway certs were used only for `nginx -t`/runtime validation. |
| B5 | Production secrets absent | Credentials | Unique `APP_KEY`, DB/Redis passwords, SMTP, Stripe/Flutterwave, Google OAuth, Sentry/LogRocket/GA must be supplied at deploy time. Never committed. |
| B6 | Hostinger VPS access not provided | Manual/access | SSH access needed for deployment; no deploy attempted. |
| B7 | ClamAV daemon/signatures on target host | External service | Fail-closed uploads need a reachable scanner; CI disables scanning for speed only. |
| B8 | Backup restore drill on the real target | Manual | Isolated drill PASSED locally (58 tables, 256 indexes, 95 FKs, exact rows, vault, cleanup) and a corrupt-backup path failed safely. Repeat on staging with a production-format backup before launch. |
| B9 | Cross-browser + Lighthouse + load tests | Tooling | Only Chromium Playwright (87/87) was executed. Firefox/WebKit, Core Web Vitals and load tests were not run; performance remains BLOCKED. |
| B10 | Per-file source review gap | Process | 712 tracked files inventoried (706 at baseline; six audit docs added, one scratch file removed). Only files marked READ were read; most source files are INVENTORIED. Full line-by-line review was not completed. |
| B11 | Legacy SQLite backup importer retained | Scope/documentation | `LegacyBackfillBackup` reads historical `.sqlite.gz` files as a migration utility (not an active DB). Owner decision pending on retiring that path. No active SQLite configuration exists. |
| B12 | Parallel audit documents contained unsupported claims | Documentation | Corrected with explicit notices: `FINAL_ENTERPRISE_AUDIT.md`, `PRODUCTION_READINESS.md`, `RELEASE_CHECKLIST.md`, `SECURITY_AUDIT.md`, `PERFORMANCE_AUDIT.md`, `AUDIT_LEDGER_HEADER.md`. `docs/FINAL_INDEPENDENT_AUDIT.md` and `docs/TEST_EVIDENCE.md` are authoritative. |

## Non-blockers (verified and working)

- Local backend suite: 362 tests / 3054 assertions, exit 0, on MariaDB 10.4 (MySQL protocol). **Not**
  MySQL 8 evidence. MySQL 8 is authoritative and passed in CI run #45151908407.
- Frontend `npm run verify` exit 0 (149/149 unit tests, build/prerender, bundle check).
- Browser: Playwright 87/87 Chromium on an earlier commit; only CI/runtime files changed since.
- Docker CI #45151908446: both images built; **Full Stack Integration Test passed** with gateway,
  preflight, register, login, `/auth/me` and logout through nginx.
- Real nginx 1.27.5 validation: four configs pass `nginx -t`; CI and production gateways start and
  return 204 + CORS headers for OPTIONS.
- Genuine backup → isolated restore drill PASS, plus safe failure on a corrupt backup.
- `composer audit` 0 advisories; `npm audit --audit-level=high` 0 vulnerabilities; 265 PHP files lint clean.
- Redis/MySQL healthchecks, MySQL-only enforcement, fail-closed readiness gate, bundle privacy check.

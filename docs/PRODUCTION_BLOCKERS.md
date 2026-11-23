# Production Blockers — Independent Audit 2026-11-23

Only genuine blockers are listed. "Local PASS" means verified on this workspace, not on the live server.
Nothing here has been deployed or changed on a production system.

| # | Blocker | Category | Status / exact requirement |
|---|---|---|---|
| B1 | Docker runtime absent in this workspace | Infrastructure | `docker` command not installed; image rebuild and full Compose integration cannot be verified locally. The fixed Dockerfile/CI must pass on GitHub Actions before release. |
| B2 | Docker CI backend readiness failures | CI | Two verified root causes fixed (missing phpredis extension; numeric-vs-string Redis cache round-trip). Run #45120325512 proved readiness + healthcheck now PASS. A later step (`artisan test` inside a `--no-dev` image) was also invalid and removed. Final re-run must show all 3 jobs green. |
| B3 | CI run #42 had a failing redundant backend job | CI | Fixed by making the MySQL 8 job authoritative; CI runs #45119217795 and #45120325495 both concluded **success**. |
| B4 | TLS certificates missing (`nginx/ssl/` empty) | Infrastructure | Real `fullchain.pem` and `privkey.pem` must be mounted; production gateway runs HTTPS only. |
| B5 | Production secrets absent | Credentials | Unique `APP_KEY`, `DB_ROOT_PASSWORD`, `DB_PASSWORD`, `REDIS_PASSWORD`, SMTP, Stripe/Flutterwave, Google OAuth, Sentry/LogRocket/GA must be supplied at deploy time. Never committed. |
| B6 | Hostinger VPS access not provided | Manual/access | SSH access needed for actual deployment; no deploy attempted. |
| B7 | ClamAV daemon/signatures on target host | External service | Fail-closed uploads require a reachable scanner; CI disables scanning for speed only. |
| B8 | Backup restore drill on the real target | Manual | Local isolated drill PASSED; repeat on staging with production-format backup before launch. |
| B9 | Cross-browser + Lighthouse + load tests | Tooling | Only Chromium Playwright was executed. Firefox/WebKit, Core Web Vitals and load tests not run. |
| B10 | Per-file source review gap | Process | 701 files inventoried; only the files marked REVIEWED in `COMPLETE_FILE_AUDIT_LEDGER.md` were read in this session. Full line-by-line review of all app/UI files was not completed. |
| B11 | Legacy SQLite backup importer retained | Scope/documentation | `LegacyBackfillBackup` still reads historical `.sqlite.gz` files as a migration utility (not an active DB). Owner decision pending on retiring that path. |
| B12 | Historical root reports contain stale claims | Documentation | ~150 historic `*.md`/`audit-*.txt` files preserved but marked superseded; new docs in `docs/` are authoritative. |

## Non-blockers (verified and working locally)

- MySQL 8: fresh database, complete migrations (53), demo seeding, 362 backend tests, 84 browser tests.
- Genuine backup → isolated MySQL restore drill PASS (58 tables, critical rows, indexes, vault).
- `composer audit` 0 advisories; `npm audit --audit-level=high` 0 vulnerabilities.
- Redis authentication/health, MySQL health, PHP lint, YAML parse, bundle privacy check.

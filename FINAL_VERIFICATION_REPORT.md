# Final Verification Report

> **Historical snapshot (superseded):** this report covers an earlier repair branch/date. For current verified status see `CODEX_FINAL_VERIFICATION_REPORT.md` and `CODEX_BLOCKERS_AND_UNVERIFIED_CLAIMS.md`.

## Current status addendum (2026-11-19)
- **Current HEAD:** `77cb8a5` on `main` (`a42c49a` docs refresh present locally during this pass). **CI:** [Run 44580103286](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/44580103286) — **4/4 PASS** including **MySQL 8 integration**.
- **Current verified counts:** Backend **283 passed / 2311 assertions** (SQLite local + MySQL CI); Frontend **136 tests** pass; lint/build/prerender/bundle pass.
- **Key recent fixes:** schema-aware `LegacyTenancyResolver` for `agency_videos.agencyId` (+ regression test), production seeder guards, composite legacy backfill with verified backup & rollback, Docker/Nginx multi-backend config (static). Runtime infra, production deploy, and exhaustive latest browser/perf evidence remain blocked/unverified.

**Project:** TaisarTownHub · **Original branch:** `fix/complete-audit-20261117` · **Original date:** 2026-11-17

## 1. Starting point (snapshot)

- Baseline / latest `main`: `09a15b9356660e40ff849001546a0323233c74fc` (confirmed via `git fetch origin`; local HEAD matched).
- Backup reference: tag `audit-baseline-20261117`.
- Pre-existing uncommitted work preserved: modified `CODEBASE_AUDIT.md` and untracked `backend/database/migrations/2026_11_16_144458_create_bookings_table.php`.
- Repair branch created from baseline; **no push to `main`**.

## 2. Exact commits (snapshot)

- `a2a5068` — first security repair batch.
- `b99ba74` — first final report.
- Follow-up commit (re-audit round): fixes CI YAML syntax (`ci.yml`, `docs/openapi.yaml`), global MFA policy for agency owner/manager memberships, `mfaEnable()` current-token stamp, seeds the MFA policy setting, and adds 2 regression tests. See `git log` for the exact SHA.

## 2b. CI failure investigation (updated)

GitHub Actions run `44028855599` on `b99ba74` failed with the annotation **"Invalid workflow file: `.github/workflows/ci.yml#L67` — You have an error in your yaml syntax on line 67"** (confirmed from the public run page's Annotations section; API jobs/logs endpoints returned zero items anonymously).

Root causes and fixes:
1. The workflow `run:` value contained `: ` (inside `print('openapi.yaml: YAML OK')`) in an unquoted plain scalar → converted to a folded block scalar (`run: >-`) and removed the colon from the message. Local `yaml.safe_load` now parses the workflow with jobs `backend`, `frontend`, `contract`.
2. `docs/openapi.yaml` line 513 had the same `: ` problem in `description: File stream (Content-Disposition: attachment)` → quoted the value. Local parse now succeeds (94 paths).
3. Added `pip install pyyaml` to the contract job so its validation step has the parser.

A new run is triggered by the push to the repair branch/main; its result must be read from GitHub, not assumed.

## 3. Files changed (source)

`AgencyStaffController`, `TransactionController`, `DealerFilesController`, `FinanceController`, `AdminUserController`, `AuthController`, `EnsureMfaVerified`, `Transaction`, `Agency`, new `MfaVerification` service, `TransactionController` test suite, `.github/workflows/ci.yml`, `.gitignore` placeholders. Full list in `git show --stat a2a5068`.

## 4. Confirmed bugs and fixes

Critical: invitation→platform-admin escalation; legacy transaction cross-tenant list/create.
High: dealer-file authorization/tenancy/quota/PII; MFA user-wide verification; undefined finance relation (500); lost property/dealer attribution; unpermissioned void; CI fresh-checkout storage failure.
Medium: arbitrary dealer id in finance update; PyYAML CI dependency; deployment gaps documented.

Full register: `BUG_REGISTER_AND_FIXES.md`. No test was deleted, skipped or weakened.

## 5. Tests executed with real counts

| Gate | Result |
|---|---|
| Backend full suite — SQLite (`php artisan test`) | **243 passed / 1,974 assertions** |
| Backend full suite — real MySQL 8.4.9 | **243 passed / 1,974 assertions** |
| Two-process MySQL concurrency (invitations + deal numbers) | **2 tests / 10 assertions — PASS** |
| Frontend `npm run verify` (lint → tests → build/prerender → bundle guard) | **green** — 133 tests, 29 HTML, 9-URL sitemap, 10 public chunks, no private data |
| PHP lint (all changed PHP files) | **0 failures** |
| YAML parse (`ci.yml` + `openapi.yaml`) | **OK** |
| Targeted suites | AdminStaff 12, FinanceIsolation 16, LegacyTransaction 5, DealerFiles 7, MfaEnforcement 6, BookingConcurrency 5 — all pass |

## 6. Browser routes/workflows actually tested

No **new** browser pass was completed in this increment: the desktop browser tab open timed out after repeated service restarts, so route-by-route and responsive checks are marked NOT VERIFIED in `FRONTEND_PAGE_AND_INTERACTION_AUDIT.md` and `RESPONSIVE_ACCESSIBILITY_AUDIT.md`. Evidence from the previous increment (saved searches, comparison, support centre, permission gating) remains valid for unchanged frontend code but is not re-claimed as this run's result.

## 7. CI result and link

- Original failed run: `43888730317` → `https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/43888730317` (conclusion: failure). Logs require authentication (403 anonymous); jobs/check-runs endpoints returned zero items. Root cause reproduced locally and fixed as described in `DEPLOYMENT_AND_CICD_AUDIT.md`.
- New CI run on the repair branch was **not triggered** (no authenticated `gh`/token). Local equivalents are green; CI status on GitHub remains **unverified**.

## 8. Remaining blockers / conditions

1. No Docker daemon → compose build, container health, MySQL/Redis connectivity, queue worker and scheduler runtime unverified.
2. Dead backend stage in root `Dockerfile.prod`; no scheduler service or backend healthcheck in compose.
3. Vercel has no Laravel API (`/api` 404; `api.taisartownhub.com` unreachable); custom domain transport error.
4. Real ClamAV and real SMTP unverified.
5. Lighthouse/responsive/WCAG matrix not executed; screenshots unavailable.
6. MFA global-policy key unseeded and role whitelist inconsistent (Medium, needs product decision).
7. Dealer deal-visibility policy differs across legacy and finance APIs (needs decision).
8. ~~True multi-process concurrency under MySQL not executed~~ **RESOLVED**: real MySQL 8.4.9 two-process tests added and passing (see `MYSQL_PRIMARY_DATABASE.md`). Remaining: MySQL 8.0 CI service result must be observed from GitHub Actions; Docker/ClamAV/SMTP and browser/Lighthouse still unverified.
9. Legacy ownership mapping (110 unowned rows) untouched pending business approval and backup.

## 9. Second CI failure investigation (run 44059650335)

Jobs API confirmed: `Backend suite (PHP 8.3)` **success**, `OpenAPI spec parses` **success**,
`Backend suite on MySQL 8 (integration)` **failure** at step **"Wait for MySQL"** (exit 255;
the test step was skipped), `Frontend verify (Node 20)` **failure** at step
**"Verify chain"** (~1s).

### Root cause 1 — MySQL job
`php -r "$pdo = new PDO(...)"` — the `$pdo` token is expanded by the shell before PHP sees it.
Replaced with a dedicated `backend/scripts/wait-for-db.php` that reads all credentials from
`DB_*` environment variables, retries 30×, and exits non-zero with a clear error. Added
`backend/scripts/assert-db-driver.php` as a CI step so a silent SQLite fallback fails the job
(verified locally: `DB_CONNECTION=mysql`, `DB_DATABASE=tth_audit`, `MYSQL_VERSION=8.4.9`).

### Root cause 2 — Frontend job
Two independent defects, both reproduced on a fresh `git clone` + `npm ci`:
1. `tsx --test "src/**/*.test.ts"` — the quoted glob is passed literally on some shells and
   fails with `Could not find src/**/*.test.ts`. Replaced with cross-platform
   `frontend/scripts/run-tests.mjs` which discovers `*.test.ts` via `fs.readdirSync` and
   spawns tsx with explicit paths. Fresh-checkout `npm test`: **133 passed / 9 suites**.
2. `check:bundle` then failed with `jsx-runtime-*.js contains "cnicMasked"` because the
   public-safe catalog in `frontend/src/services/api.ts` emitted an empty `cnicMasked: ''`
   placeholder. Removed that private ERP field from the public catalog (it was unused by
   public components). Fresh-checkout `npm run verify` on Node 22: **green** (bundle OK).

CI also pins `node-version: '22'` (Vite 8 / rolldown 1.2 require `^20.19 || >=22.12`) and the
job was renamed `Frontend verify (Node 22)`.

## 11. Enterprise Secure Document & Media Vault (2026-11-17, commit `8c56b76`)

Full audit → fix → build cycle, documented in `SECURE_FILE_VAULT_AUDIT.md`,
`SECURE_FILE_VAULT_ARCHITECTURE.md`, `FILE_STORAGE_SECURITY_REPORT.md`,
`FILE_STORAGE_THREAT_MODEL.md`, `FILE_STORAGE_DEPLOYMENT_GUIDE.md`.

**Verified vulnerabilities fixed (each with a regression test):** magic bytes read from
offset 0 (was 32); version uploads run the full content pipeline; media uploads MIME-sniffed
+ magic-byte + malware-scanned with server-sniffed Content-Type; storage quota covers trash +
versions + media (SubscriptionController mirrored); HTTPS-public-only external URLs enforced
at registration and delivery; denied download attempts audited; scan lifecycle gates
(fail-open holds → 423 download / 422 share until rescan; infected → quarantine disk);
AES-256-GCM at-rest encryption (`FileVaultCrypto`, transparent legacy reads,
`vault:encrypt` backfill/rotation tool); quarantine disk + visibility/rescan/purge endpoints;
20/min upload throttles; header-injection-safe filenames.

**Test evidence (final, commit `a53f95e`):** backend **261 passed / 2,104 assertions** on
SQLite (38.2s) locally; on MySQL 8.4.9 locally **260 passed / 2,095 assertions** at the
17-test stage with the full vault migration; the final 261-test suite was verified against
MySQL 8 by the dedicated CI integration job. Frontend verify chain green (133 tests /
9 suites, prerender, sitemap, bundle guard); OpenAPI spec updated + parses. Frontend gains
quarantine panel with rescan in `AdminDocumentVault.tsx`.

**CI evidence (real GitHub runs, verified):** run [`44098677166`](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/44098677166)
(commit `64e4372`) — conclusion **success**; run [`44099075001`](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/44099075001)
(commit `a53f95e`, final) — conclusion **success**, all four jobs green: Backend suite (PHP
8.3), Frontend verify (Node 22), OpenAPI spec parses, and **Backend suite on MySQL 8
(integration)** with the `Confirm MySQL driver (no SQLite fallback)` guard step passing.

**Blocked (infrastructure, unchanged from §8):** real ClamAV daemon exercise (EICAR +
clean-file acceptance), real backup restore drill (RPO/RTO documented, not measured),
KMS integration, Docker compose orchestration. Fail-closed defaults keep the system safe
without them.

## 12. Verdict

**Not 100% complete.** All confirmed Critical/High code defects were fixed and covered by
passing regression tests; both CI failure root causes were reproduced locally and repaired.
Acceptance criteria that depend on an observed green GitHub Actions run, Docker, real ClamAV,
SMTP, browser/Lighthouse measurement and external infrastructure remain open. No production
claim is made.

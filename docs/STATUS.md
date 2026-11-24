# TaisarTownHub — STATUS

**Branch:** `fix/workspace-tenant-isolation-v2` (from `main` @ `2463bfe`) · **Date:** 2026-11-24  
**Review PR:** [#4 — Security: isolate workspace tenants and protect public catalog data](https://github.com/mohsin-gohar/TaisarTownHub/pull/4) (draft; not merged).

This is the single source of truth for what is verified, what is not, and what is blocked. Status vocabulary: **VERIFIED** (command + real result), **NOT VERIFIED** (exact command/requirement remains), **BLOCKED** (needs owner access, credentials, assets or business decision). No marketing language; no "100%" claims.

## A. Changes on this review branch

| Commit | Change |
|---|---|
| `139bee3` | Tenant isolation for subscribers; destructive workspace replace interlocks; public catalog allowlists and visibility checks; tenant/role/privacy regression tests. |
| `ed397fa` | Exclude soft-deleted `business_records` from workspace dumps. |
| `fdfff96` | Regression test proving soft-deleted workspace rows are hidden. |
| `d66f754` | Add Playwright Chromium UI regression suites to pull-request CI. |

No changes have been made to `main`; no production deployment has been performed.

## B. Verified on GitHub Actions for this branch

| Area | Evidence | Result |
|---|---|---|
| Backend PHP suite | CI run for commit `d66f754`, PHP 8.3 + MySQL 8 | **420 passed / 0 failed (3,694 assertions)** |
| Frontend verification | `npm run verify` in CI | **PASS** — typecheck, unit tests, build/prerender and bundle privacy guard |
| Browser UI regression | Playwright Chromium: cross-browser smoke, responsive, maps, interactive elements, WhatsApp and public pages | **46 passed / 2 skipped / 0 failed** |
| OpenAPI contract syntax | YAML parse job | **PASS** |
| Backend production image | Docker CI build, compose startup, readiness and PHP healthcheck | **PASS** |
| Frontend production image | Docker CI build and built `index.html` check | **PASS** |
| Backup/restore | MySQL 8 backup plus isolated restore drill | **PASS** — data integrity and scratch cleanup verified |
| Full-stack integration | Docker CI full-stack job | **Not yet marked complete in the latest run**; do not treat as verified until its check turns green |

CI links:
- [CI run](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45424288883)
- [Docker CI run](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45424288999)

## C. Still not verified

| Area | Required verification |
|---|---|
| API-dependent browser workflows | Run the full Playwright suite against a seeded, running API, including login/role workflows and CRUD journeys; the new CI browser job intentionally covers UI-only suites only. |
| ClamAV upload security | Verify clean upload, EICAR rejection/quarantine, and fail-closed behaviour when ClamAV is unavailable (expected hold/423). |
| Multi-replica operation | Run `docker compose up -d --scale backend=2`; verify stateless auth/session behaviour and requests across both replicas. |
| Firefox and WebKit | Install browsers and run cross-browser suites; current CI browser job uses Chromium only. |
| Lighthouse / accessibility | Run Lighthouse against a real HTTPS deployment; resolve any material accessibility, SEO or performance findings. |
| Load / capacity | Run 10/50/100-user load tests against a representative API; record p95 latency and error rates. |
| Full source audit | A line-by-line audit of all repository files has not been completed. The tree contains hundreds of files; inventory and selected high-risk reviews are not equivalent to a full audit. |

## D. Blocked on owner access, assets or decisions

1. **Production secrets:** unique `APP_KEY`, database/Redis passwords, SMTP, Stripe/Flutterwave, Google OAuth and monitoring credentials. Supply through the deployment environment only; never commit them.
2. **DNS + TLS:** configure the production domain and real certificates on the target VPS.
3. **Legacy data ownership:** unresolved `business_records` rows need a human-approved ownership mapping. Back up first, dry-run `platform:legacy-backfill`, review `platform:legacy-ownership`, then apply only the approved mapping.
4. **Global MFA policy:** decide whether platform administrators must have MFA enforced.
5. **Production rate limits:** profile real traffic and approve final thresholds; current values are initial targets.
6. **Real Scheme 45 media:** replace sample/stock video references with licensed, genuine media assets.
7. **VPS access and deployment sign-off:** SSH access, production environment values, monitoring and rollback ownership are required before launch.

## E. Release gates

- Keep PR #4 as a draft until the full-stack integration job is green and all review findings are resolved.
- Do not merge to `main` until the owner approves the reviewed diff and all required CI checks pass.
- Do not deploy to production until Section D is resolved and Section C's relevant operational checks are completed.
- Preserve a verified database/storage backup and rollback image tags before any production cutover.

## F. Known cleanup items

- Root `Dockerfile.prod` is deprecated; per-app Dockerfiles are authoritative.
- Historical audit reports were removed during documentation cleanup because some claimed checks that had not actually been run. Use this file and linked CI evidence instead.

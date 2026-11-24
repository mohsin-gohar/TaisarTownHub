# TaisarTownHub — Release Readiness

**Current main:** `e780f1f7cf104a04e3855fb61ec2bfd43422ad52` (PR #4 merged)  
**QA branch:** `qa/cross-browser-multireplica`  
**Date:** 2026-11-24

This document separates verified engineering work from checks that require a real production environment, licensed assets, or owner decisions. A green CI pipeline is not equivalent to a production launch.

## 1. Merged security work

PR #4 — [Security: isolate workspace tenants and protect public catalog data](https://github.com/mohsin-gohar/TaisarTownHub/pull/4) — is merged into `main`.

- Agency subscriber data is tenant-scoped; platform-owned subscribers are not exposed to agencies.
- Workspace replace operations reject empty/invalid payloads and require explicit confirmation for platform-wide destructive replacement.
- Soft-deleted records are excluded from workspace dumps.
- Public catalog responses use field allowlists and visibility rules.
- Regression tests cover tenant isolation, role gates, replacement safety, subscription privacy and catalog privacy.

## 2. Verified on main

CI runs for merge commit `e780f1f`:
- [CI](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45429621094) — all jobs successful.
- [Docker CI](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45429621006) — all jobs successful.

Evidence from the code-identical review run:
- Backend PHP 8.3 + MySQL 8: **420 passed / 0 failed (3,694 assertions)**.
- Frontend `npm run verify`: passed (typecheck, unit tests, build/prerender, bundle privacy guard).
- Chromium UI regression: **46 passed / 2 skipped / 0 failed**.
- OpenAPI YAML parse: passed.
- Backend and frontend production-image build/runtime checks: passed.
- MySQL 8 backup/restore drill: passed.
- Full-stack Docker integration: passed (services ready, enterprise catalog seed, gateway/auth smoke, cleanup).

## 3. Additional CI hardening on QA branch

The following checks have been added and must pass before their status is upgraded:
- Firefox + WebKit public-route smoke tests using the existing cross-browser Playwright config.
- Two-replica Docker backend test: scale backend to two containers, check each container's PHP health, then exercise the nginx gateway.
- Real ClamAV scanner smoke: send a clean fixture and the standard harmless antivirus test signature through the actual clamd TCP INSTREAM protocol; require clean and threat verdicts.

No claim is made that these new jobs have passed until GitHub Actions reports success on this QA branch.

## 4. Remaining engineering verification

| Area | Current state | Closure evidence |
|---|---|---|
| Real ClamAV scanner | Docker CI now runs a real clamd TCP smoke probe against a clean fixture and a harmless antivirus test signature; pending result on QA branch | Require real clean + threat verdicts; separately retain HTTP upload fail-closed regression tests |
| API-dependent browser workflows | UI-only Chromium smoke is green; seeded API-backed role/CRUD browser matrix is not yet run as one full suite | Run Playwright against disposable MySQL-backed stack for guest, agency owner/staff and super-admin flows |
| Firefox/WebKit | Newly added CI job pending | Both engines pass all seven public-route smoke tests |
| Multi-replica | Newly added Docker CI step pending | Both PHP replicas healthy and gateway requests succeed after scale-up |
| Lighthouse/accessibility | No production HTTPS origin has been supplied | Run Lighthouse/accessibility checks against real deployment and resolve material findings |
| Load/capacity | Harness exists at `frontend/scripts/load-test.mts`; now refuses to run without explicit mutation opt-in and dedicated test credentials, and never falls back to an existing property | Run approved 10/50/100-user tests against disposable staging; record p95/p99, 5xx and error rates |
| Full source review | Repository inventory is 631 tracked paths; no honest line-by-line review of every source/config/test file is claimed | Complete risk-ranked review of auth, tenant boundaries, money, uploads, integrations, deployment and remaining modules with evidence |

## 5. Owner/environment blockers — do not fabricate or bypass

1. **Production secrets:** unique `APP_KEY`, DB/Redis passwords, SMTP, payment provider keys/webhook secrets, OAuth and monitoring credentials. Create/store only in the VPS/deployment secret manager; never commit them.
2. **DNS/TLS and VPS:** domain A/AAAA records, VPS/SSH access, firewall, real TLS certificates, backup destination and rollback owner are not available through repository access.
3. **Legacy ownership mapping:** run the read-only `platform:legacy-ownership --json` report on a protected copy of the actual production database. The owner must approve ambiguous/unresolved record ownership. Only then run `platform:legacy-backfill --apply` with a verified backup and approved mapping.
4. **Licensed Scheme 45 media:** replace sample/stock references only with media the owner has rights to publish. Do not invent or scrape assets.
5. **Platform-wide MFA policy:** owner must decide whether Super Admin MFA is mandatory; then enforce and test the approved policy.
6. **Production rate limits:** current values are starting targets; finalize from staging/real traffic measurements.
7. **Payments and mail:** confirm whether Stripe/Flutterwave are enabled for the business, supply live credentials/webhook configuration, and verify SMTP delivery. Otherwise explicitly disable unused integrations.
8. **Data protection/retention:** owner approval required for privacy notice, retention periods, backups, data deletion and incident response.

## 6. Safe release sequence

1. Finish and pass QA branch CI (including cross-browser and multi-replica checks).
2. Verify actual ClamAV/EICAR upload path and API-backed browser journeys on disposable staging.
3. Owner supplies licensed media, approved legacy mapping, production secrets, domain and VPS access.
4. Take and verify full database + private-storage backup; run production readiness audit; deploy with rollback images ready.
5. Run post-deploy HTTPS, auth, tenant isolation, upload/download, queue, scheduler, mail/payment webhooks, monitoring and restore checks.
6. Only then mark production launch complete.

**Safety:** no production database, storage, credentials, DNS or deployment has been modified by this QA work. No unresolved owner-dependent task is silently marked complete.

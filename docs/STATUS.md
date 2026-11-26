# TaisarTownHub — Release Readiness

**Main:** `e780f1f7cf104a04e3855fb61ec2bfd43422ad52`  
**QA branch:** `qa/cross-browser-multireplica`  
**QA head at this update:** `10bf3d4dc7e4d955fe3750825b843cdebbb420e9`  
**PR #5:** open, Draft, unmerged  
**Repository inventory:** 560 tracked paths; QA is 109 commits ahead of main and 0 behind  
**Date:** 2026-11-26

This document separates verified engineering work from checks that require a real production environment, licensed assets, or owner decisions. Green CI is not equivalent to a production launch.

## 1. Merged baseline

PR #4 — [Security: isolate workspace tenants and protect public catalog data](https://github.com/mohsin-gohar/TaisarTownHub/pull/4) — is merged into `main`.

The merged baseline includes tenant-scoped agency data, safe workspace replacement, soft-delete-aware exports, public catalog field allowlists, and regression tests for tenant isolation, role gates, replacement safety, subscription privacy and catalog privacy.

## 2. Latest completed QA CI evidence

The latest fully completed CI workflow on the current QA history is commit `6bc0fe7f3520d7e5b9b15c16ce81131dbf1fc200` ([CI run](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45750136086)). All jobs passed:

- Backend PHP 8.3 + MySQL 8: **433 passed, 3,813 assertions**.
- Chromium browser regression: **46 passed, 2 skipped**.
- Firefox + WebKit cross-browser smoke: **14 passed**.
- Frontend verify (typecheck, unit tests, production build/prerender and bundle checks): passed.
- OpenAPI validation: passed.

On the same commit, Docker CI ([run](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45750136104)) had backend image build, frontend image build and MySQL backup/restore drill successful. The Full Stack Integration Test job was still running when this document was updated; it is **not counted as passed** here.

These checks are on an ancestor commit, not the current QA head. The billing and authorization changes listed below were committed after that run and still require a fresh green CI run on the current head.

## 3. QA engineering changes

### Billing and subscriptions
- Stripe recurring invoice handling now reads the Stripe subscription period, extends `ends_at` to the authoritative period end, and records renewal invoices using a unique Stripe invoice ID.
- Duplicate/out-of-order Stripe invoice events are reconciled against the local invoice ledger; delayed failure events for an older paid period must not downgrade a later active term.
- Checkout now rejects inactive plans, persists a local pending invoice before contacting Stripe, and uses a stable Stripe idempotency key so a retry can recover the same checkout session.
- Flutterwave webhook parsing accepts the actual nested `data` payload and requires amount and currency before activation. Invalid or non-payable verification outcomes no longer return a false success.
- New regression tests cover recurring periods, invoice ledger creation, incomplete Flutterwave payloads, inactive plans and checkout/payment state.

### Authorization and finance
- Irreversible transaction voiding now requires the explicit `void_transactions` permission. Agency Owner and Super Admin retain it; agency managers/accountants and the legacy platform Admin do not receive it from broad update grants.
- Deal approval now requires the explicit `approve_transactions` permission rather than a generic transaction-update grant. Agency Owner, platform Admin and Super Admin grants are aligned with the intended role matrix.
- Regression tests cover the seeded agency manager, agency owner and platform Admin permission boundaries.

### Reliability and configuration
- The yearly deal-number sequence is pre-seeded for the current and next calendar year to avoid first-write races; transaction creation retries transient lock contention.
- Production CORS defaults and `backend/.env.example` now contain only the real HTTPS frontend origin. Local Vite origins are documented as local overrides.
- Local setup instructions now explicitly call out local DB credentials and CORS overrides.

## 4. Engineering verification still required

| Area | Current state | Closure evidence |
|---|---|---|
| Current-head CI | Changes after `6bc0fe7` are not yet covered by a completed current-head run | Backend, frontend, Chromium, Firefox/WebKit, OpenAPI and Docker jobs all green on the same latest SHA |
| Full-stack / multi-replica / load | Docker full-stack job for `6bc0fe7` was still running at this update; an earlier load probe created 7/8 transactions and exposed first-year sequence contention | Complete current-head Docker integration; confirm 2 healthy replicas, 10-worker transaction integrity, 5xx=0, and cleanup |
| Stripe/Flutterwave live flows | Unit/feature tests only; no live provider credentials or real merchant webhook delivery | Owner-provided test/live credentials, verified signatures, successful/failed/refunded lifecycle tests and reconciliation |
| Financial precision | PKR transaction totals are stored as BIGINT while payment/commission amounts use DECIMAL(14,2); model casts/calculations include floats | Decide and document the currency precision policy; audit every money field/API/UI calculation; add reconciliation and large-value tests before a backwards-compatible migration |
| Storage quotas | Storage usage is measured for monitoring/reporting; uploads are not blocked by a storage quota in this release | Owner decides plan quotas and retention semantics, then enforce atomically with upload/version/media tests |
| Full source review | 560 tracked paths are inventoried; a line-by-line review of every file is not claimed | Complete risk-ranked review of auth, tenant boundaries, money, uploads, integrations, deployment and remaining modules |
| Lighthouse/accessibility | No production HTTPS origin has been supplied | Run Lighthouse/accessibility checks against the real deployment and resolve material findings |

## 5. Owner/environment blockers — do not fabricate or bypass

1. **Production secrets:** unique `APP_KEY`, DB/Redis passwords, SMTP, payment provider keys/webhook secrets, OAuth and monitoring credentials. Create/store only in the VPS/deployment secret manager; never commit them.
2. **DNS/TLS and VPS:** domain A/AAAA records, VPS/SSH access, firewall, real TLS certificates, backup destination and rollback owner are not available through repository access.
3. **Legacy ownership mapping:** run the read-only `platform:legacy-ownership --json` report on a protected copy of the actual production database. The owner must approve ambiguous/unresolved record ownership before any apply operation.
4. **Licensed Scheme 45 media:** replace sample/stock references only with media the owner has rights to publish. Do not invent or scrape assets.
5. **Platform-wide MFA policy:** owner must decide whether Super Admin MFA is mandatory; then enforce and test the approved policy.
6. **Production rate limits:** finalize from staging/real traffic measurements.
7. **Payments and mail:** confirm whether Stripe/Flutterwave are enabled, supply credentials/webhook configuration, and verify SMTP delivery. Otherwise explicitly disable unused integrations.
8. **Data protection/retention:** owner approval is required for privacy notice, retention periods, backups, data deletion and incident response.

## 6. Safe release sequence

1. Finish current-head QA CI and the full-stack / multi-replica / load gates.
2. Verify real ClamAV/EICAR upload handling and API-backed browser journeys on disposable staging.
3. Owner supplies licensed media, approved legacy mapping, production secrets, domain and VPS access.
4. Take and verify full database + private-storage backup; run the production readiness audit; deploy with rollback images ready.
5. Run post-deploy HTTPS, auth, tenant isolation, upload/download, queue, scheduler, mail/payment webhooks, monitoring and restore checks.
6. Only then mark production launch complete.

**Safety:** `main` remains unchanged. No production database, storage, credentials, DNS or deployment has been modified by this QA work. No unresolved owner-dependent task is silently marked complete.

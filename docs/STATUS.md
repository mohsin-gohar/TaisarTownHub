# TaisarTownHub — Release Readiness

**Main:** `e780f1f7cf104a04e3855fb61ec2bfd43422ad52` (PR #4 merged)  
**QA head:** `9807dab71e894367721afd66c06fbf4a96e02801`  
**Evidence refresh:** 2026-11-26  
**QA PR:** [#5 — Firefox/WebKit and multi-replica release gates](https://github.com/mohsin-gohar/TaisarTownHub/pull/5) (keep separate from main until owner review/approval).

This file distinguishes CI evidence from real production readiness. A green CI pipeline is not a production launch. Do not mark owner- or environment-dependent work complete without evidence.

## 1. Merged into main

PR #4 — [Security: isolate workspace tenants and protect public catalog data](https://github.com/mohsin-gohar/TaisarTownHub/pull/4) — is merged.

- Agency subscriber data is tenant-scoped; platform-owned subscriber rows are hidden from agencies.
- Workspace replace operations reject empty/all-invalid payloads and require explicit confirmation for platform-wide replacement.
- Soft-deleted records are excluded from workspace dumps.
- Public catalog responses use field allowlists and visibility rules.
- Regression tests cover tenant isolation, role gates, replacement safety, subscription privacy and catalog privacy.

## 2. Verified CI evidence

### QA CI — run 144

[GitHub Actions run 144](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45542849480) completed successfully on QA head `9807dab`.

- Backend PHP 8.3 + MySQL 8 suite: successful.
- Frontend verify chain (lint/typecheck, tests, build/prerender and bundle guard): successful.
- Chromium UI-only Playwright regression: successful.
- Firefox + WebKit public-route smoke: successful.
- OpenAPI YAML parse: successful.

### QA Docker CI — run 86

[GitHub Actions run 86](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45542849505) completed successfully on QA head `9807dab`.

- Backend and frontend production images built and checked.
- Real ClamAV TCP INSTREAM probe returned the expected clean-file and threat verdicts: log reports `PASS: real ClamAV clean-file verdict and threat detection verified.`
- MySQL 8 backup and isolated restore drill passed, including vault fixture verification and scratch cleanup.
- Full-stack integration stack became ready.
- Two backend replicas were independently healthy; nginx gateway health requests passed after scaling.
- API-backed Playwright role/permission suite: **13 passed**.
- Disposable concurrency probe: **8 parallel transactions received 8 unique deal numbers**; all 8 test transactions were voided and the disposable property was deleted successfully (HTTP 200).

The full-stack job log also reports p50/p95/p99/max latency of 2/4/6/120 ms during its 10-second, 10-worker probe. This is a disposable CI smoke probe, not a representative capacity benchmark: it recorded 49,857 HTTP 429 responses, 2 other client errors, 0 server errors and 0 network failures. Do not interpret its reported throughput or rounded 0.00% error rate as production capacity evidence.

## 3. Remaining engineering verification

| Area | Current state | Closure evidence |
|---|---|---|
| Full API/browser business journeys | A 13-test API-backed role/permission suite passed. This does not prove every role can complete every CRUD/business journey across all modules. | Seeded disposable MySQL stack; explicit guest/customer/agency owner/manager/staff/dealer/platform role journeys; create/read/update/delete and negative permission assertions for each module. |
| Real scanner through upload endpoint | Real ClamAV clean/threat verdicts are verified at the scanner transport. HTTP upload enforcement tests use a deterministic scanner stub. | Send harmless and EICAR test files through the actual authenticated upload API with real ClamAV; prove infected rejection/quarantine and scanner-unavailable fail-closed behaviour. |
| Multi-replica state behaviour | Both PHP replicas were healthy and nginx gateway health requests passed. | Exercise authenticated requests, queue dispatch and any shared cache/session state across replicas; verify no local-disk/session affinity assumptions. |
| Load/capacity | Only a short disposable 10-worker probe was run; most requests were rate-limited (49,857 HTTP 429). | Approved 10/50/100-user tests against representative disposable staging with rate-limit-aware phases; record p95/p99, 5xx, network failures and DB/Redis saturation. |
| Financial precision | Deal totals are stored as BIGINT and deal endpoints reject fractional rupees. Payments, schedules and commissions use DECIMAL(14,2); models/controllers convert amounts to PHP floats in several calculations. | Define one documented PKR precision/rounding policy; use exact decimal or integer-minor-unit arithmetic consistently; add fractional, large-value, refund, overpayment, schedule, commission and reconciliation tests before a backwards-compatible migration. |
| Lighthouse/accessibility | Not verified against a real HTTPS deployment. | Run Lighthouse and accessibility audits against the deployed origin and resolve material findings. |
| Full source review | Repository inventory is 631 tracked paths. No line-by-line review of every source/config/test file is claimed. | Complete a risk-ranked review of auth, tenant boundaries, finance, uploads, integrations, deployment and remaining modules, with findings and regression evidence. |

## 4. Owner and production-environment blockers

1. **Production secrets:** unique `APP_KEY`, DB/Redis passwords, SMTP, payment provider keys/webhook secrets, OAuth and monitoring credentials. Supply through the deployment secret manager only; never commit them.
2. **DNS/TLS and VPS:** domain A/AAAA records, VPS/SSH access, firewall, real TLS certificates, backup destination and rollback owner are not available through repository access.
3. **Legacy ownership mapping:** run the read-only `platform:legacy-ownership --json` report on a protected copy of the real production database. The owner must approve ambiguous/unresolved record ownership. Only then run `platform:legacy-backfill --apply` with a verified backup and approved mapping.
4. **Licensed Scheme 45 media:** replace sample/stock references only with assets the owner has rights to publish.
5. **Platform-wide MFA:** owner decision required on mandatory MFA for Super Admin/platform administrators.
6. **Production rate limits:** final thresholds require representative staging/real traffic measurements.
7. **Mail and payments:** confirm whether Stripe/Flutterwave are required; provide live credentials/webhook configuration and verify end-to-end delivery, or explicitly disable unused integrations.
8. **Data protection/retention:** owner approval required for privacy notice, retention periods, deletion, backups and incident response.

## 5. Safe release sequence

1. Review PR #5 and this evidence refresh; merge only after owner approval and all required checks pass.
2. Complete API-backed business journeys and real upload-endpoint/ClamAV integration checks on disposable staging.
3. Resolve the financial precision policy and implement/test any required backwards-compatible correction.
4. Owner supplies licensed media, approved legacy mapping, production secrets, domain and VPS access.
5. Take and verify database + private-vault backups; deploy with previous image tags and rollback procedure ready.
6. Run HTTPS, auth, tenant-isolation, upload/download, queue, scheduler, SMTP/payment webhooks, monitoring and restore checks on production.
7. Only then mark the production launch complete.

**Safety:** QA work uses disposable CI infrastructure. No production database, storage, credentials, DNS or deployment has been modified. No owner-dependent task is silently marked complete.

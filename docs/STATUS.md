# TaisarTownHub — Release Readiness

**Main baseline:** `e780f1f7cf104a04e3855fb61ec2bfd43422ad52`  
**QA code snapshot validated:** `d1a09d3ef1a13cc53d22851df6e24999cd980cfd`  
**QA branch:** `qa/cross-browser-multireplica`  
**PR #5:** open, Draft, unmerged  
**Audit snapshot inventory:** 561 tracked files; QA was 184 commits ahead of main, 0 behind, with 54 changed files before this status-document update  
**Date:** 2026-11-26

This document separates verified repository/CI work from production checks that require owner-controlled credentials, data, infrastructure, or decisions. Green CI does not mean a production launch has occurred.

## 1. Merged baseline

PR #4 — [Security: isolate workspace tenants and protect public catalog data](https://github.com/mohsin-gohar/TaisarTownHub/pull/4) — is merged into `main`.

The merged baseline includes tenant-scoped agency data, safe workspace replacement, soft-delete-aware exports, public catalog field allowlists, and regression tests for tenant isolation, role gates, replacement safety, subscription privacy and catalog privacy.

## 2. Latest completed CI evidence

Both latest workflows completed successfully on the validated QA code snapshot.

### CI run 45754283139
- Backend PHP 8.3 + MySQL 8: **448 passed, 3,921 assertions**.
- Frontend verify on Node 24: **158 tests passed**, typecheck and production build/prerender/bundle checks passed.
- Chromium Playwright: **46 passed, 2 skipped**.
- Firefox + WebKit cross-browser smoke: **14 passed**.
- OpenAPI validation: passed.

### Docker CI run 45754283114
- Backend and frontend production image builds: passed.
- Production and CI Nginx configuration validation using Nginx **1.30.8**: passed.
- MySQL backup/restore drill: passed.
- Real ClamAV TCP scan: clean-file verdict and EICAR threat detection passed.
- Full-stack disposable deployment: readiness and integration smoke (gateway, CORS preflight, register, login, current-user and logout): passed.
- Two backend replicas: both healthy; Nginx gateway health requests passed.
- API-backed browser role matrix: **13 passed**.
- 10-worker synthetic API load probe: **57,639 requests over 10 seconds**, reported 5,763.9 requests/second, **0 server 5xx**.
- Concurrent deal-number integrity: **8/8 transactions created**, all 8 deal numbers unique.
- Disposable fixtures were cleaned up successfully.

The load result is a loopback CI probe, not a production capacity benchmark. It does not replace staging tests against the actual VPS, domain, mail provider, payment providers or real traffic.

## 3. QA engineering changes

### Billing, subscriptions and finance
- Stripe recurring invoice handling uses the provider's period end, records renewal invoices, and protects subscription state from stale status events.
- Failed Stripe and Flutterwave invoices can recover to paid on a later verified retry; delayed failure events cannot overwrite an already-paid invoice.
- Flutterwave webhook handling accepts the nested provider payload, requires PKR amount/currency, and compares the exact whole-rupee settlement amount.
- Subscription checkout rejects inactive, non-PKR and fractional-rupee plans; plan administration accepts whole-PKR prices only.
- Subscription invoice numbers now use a locked yearly sequence, reconcile against imported invoice numbers, and have a MySQL concurrent-allocation regression test.
- Finance payment, installment and commission amounts are whole PKR at the API boundary. Refunds cannot exceed collected payments.
- Commission status transitions are locked and audited; accrual/payout timestamps and payout actor are recorded; paid/cancelled commissions cannot be reopened.
- Deal voiding requires zero net collected funds, blocks when a commission has already been paid, cancels unpaid commissions and schedules, and prevents later payment/commission changes on the voided deal.
- Transaction number allocation is pre-seeded for current/next year and protected against concurrent first-write races.

### Authorization
- Deal voiding requires the explicit `void_transactions` permission; broad transaction-update permission no longer grants void rights.
- Deal approval requires explicit `approve_transactions`; agency owner and approved platform roles receive the intended grant.
- Legacy approval permission is registered in the catalog and remains unavailable for arbitrary agency-role assignment.
- Regression tests cover manager, owner, platform Admin and Super Admin permission boundaries.

### Runtime and deployment
- Frontend runtime, local version pin and CI now use Node 24.
- Frontend and gateway Nginx images use the pinned stable patch `nginx:1.30.8-alpine`.
- Docker Compose fails early if a production `APP_KEY` is missing.
- Queue worker receives the application URL, Redis cache and SMTP configuration required for queued email jobs.
- Superseded branch CI runs are cancelled to avoid wasting runner capacity.

## 4. Remaining engineering/product decisions

| Area | Current state | Required closure |
|---|---|---|
| Full source review | 561 tracked files inventoried; critical auth, tenant, billing, finance, upload and deployment paths reviewed and tested. A literal line-by-line review of every tracked file is **not claimed**. | Continue a risk-ranked review of lower-risk UI, utility and documentation files before calling the entire repository exhaustively audited. |
| Legacy financial data | New API writes enforce whole-PKR amounts, but payment/commission/schedule storage columns retain decimal types for compatibility. | Run a read-only audit on a protected production DB copy for fractional amounts, duplicate invoice numbers and sequence drift before any migration or data correction. |
| Paid commission reversal | Deal void is intentionally blocked if a commission is already paid; no automatic reversal endpoint is provided. | Finance owner must approve the reversal/adjustment ledger and authorization policy before this workflow can be called complete. |
| Storage quota policy | Current plans use `storage_mb=0` (unlimited); usage is monitored, not quota-blocked. | Confirm unlimited storage is intentional. If quotas are required, define per-plan limits and retention rules, then enforce them atomically. |
| Full accessibility/performance | Browser regression is green, but no real production HTTPS origin has been supplied. | Run Lighthouse, accessibility and realistic performance checks on staging/production and resolve material findings. |

## 5. Owner/environment blockers — not fabricable from repository access

1. **Production secrets:** unique `APP_KEY`, DB/Redis credentials, SMTP credentials, Stripe/Flutterwave keys and webhook secrets, OAuth credentials, monitoring DSNs. The CI-only dummy key must never be used in production.
2. **VPS, DNS and TLS:** approved server/SSH access, DNS A/AAAA records, firewall rules, real TLS certificates and rollback owner.
3. **Legacy ownership mapping:** run the read-only `platform:legacy-ownership --json` report against a protected copy of the actual production database. Owner approval is required for ambiguous/unresolved records before any apply operation.
4. **Licensed Scheme 45 media:** replace sample/stock references only with assets the owner has rights to publish.
5. **Mail and messaging:** verify real SMTP delivery, password reset and queued notifications. If WhatsApp automation is required, provide the chosen provider, credentials, approved templates and opt-in policy.
6. **Payments:** supply provider test/live credentials and verify real checkout, signed webhooks, renewal, failure, retry, refund and reconciliation flows. Otherwise disable unused gateways.
7. **Platform MFA:** owner must decide whether Super Admin MFA is mandatory and approve the recovery policy.
8. **Privacy and operations:** approve privacy notice, retention/deletion periods, incident response, monitoring/alert routing and off-site backup retention.
9. **Production data and launch:** verify a full backup/restore rehearsal, legacy mapping, staging smoke tests and rollback plan before deployment.

## 6. Safe release sequence

1. Complete the remaining risk-ranked source review and resolve the finance/storage policy decisions above.
2. Obtain owner-approved production secrets, licensed media, legacy ownership mapping, VPS and domain access.
3. Configure SMTP/payment/OAuth/monitoring integrations in the deployment secret manager; never commit secrets.
4. Take and verify database plus private-storage backups; deploy to staging with rollback images ready.
5. Run HTTPS, tenant isolation, role matrix, upload/download, queue, scheduler, mail/payment webhook, monitoring and restore checks on staging.
6. Obtain explicit owner approval for production deployment; run post-deploy smoke and restore checks.

**Safety:** `main` remains unchanged. PR #5 remains Draft and unmerged. No production database, storage, credentials, DNS or deployment has been modified by this QA work. No owner-dependent task is silently marked complete.

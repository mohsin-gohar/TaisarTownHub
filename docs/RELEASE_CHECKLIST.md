# TaisarTownHub - Release Checklist

## Release Date: 2026-11-23
## Historical baseline commit: `6c92d52` — audit: add enterprise audit reports and file ledger

> **Updated 2026-11-27:** This checklist supersedes the historical PASS claims below. See [INDEPENDENT_REVIEW_20261127.md](INDEPENDENT_REVIEW_20261127.md). The complete file-by-file audit is not finished, PR #24 contains security fixes not yet merged/deployed, and the live API is unavailable.

## Pre-Release Gates

A release cannot be approved unless all applicable gates pass. Each item is classified as:
- **PASS** - Verified and working
- **FAIL** - Verified and not working
- **BLOCKED** - Cannot be verified here, blocker exists
- **NOT TESTED** - Has not been tested

### Source Code Review
- [ ] IN PROGRESS - Full file-by-file review is not complete. The ledger remains in progress; current baseline main tree has 564 tracked file blobs, not 706.
- [ ] FAIL - Defects found during review
- [ ] BLOCKED - Cannot review due to blocking issue
- [ ] NOT TESTED - No review performed

### Security Vulnerabilities
- [ ] BLOCKED - High-severity property metadata and CRM export scope defects were verified; fixes are in PR #24 and await latest CI, merge, and production verification.
- [ ] BLOCKED - 2 high severity items pending resolution
- [ ] FAIL - Critical security vulnerabilities found
- [ ] NOT TESTED - No security assessment performed

### MySQL Backend Tests
- [x] PASS AT PRIOR HEAD - 425 tests / 3,728 assertions passed on MySQL 8 at code commit ab9a3eb; current code CI passed.
- [ ] FAIL - Backend tests failing
- [ ] BLOCKED - Cannot run PHP in this environment
- [ ] NOT TESTED - No test suite exists

### Frontend Tests/Build
- [x] PASS AT PRIOR HEAD - Frontend verification passed at code commit ab9a3eb; Playwright ran 53 Chromium tests. Latest Vercel build is rate-limited.
- [ ] FAIL - Frontend tests failing
- [ ] BLOCKED - Cannot run Node in this environment
- [ ] NOT TESTED - No test suite exists

### Browser E2E Tests
- [x] PASS AT PRIOR HEAD - 53 Playwright Chromium tests executed and passed at code commit ab9a3eb, including 320–1920px responsive coverage; the workflow did not execute all 84 tests listed in the historical audit.
- [ ] FAIL - E2E tests failing
- [ ] BLOCKED - Cannot run browsers in this environment
- [ ] NOT TESTED - No E2E test suite exists

### Docker Integration
- [x] PASS AT PRIOR HEAD - Docker CI at ab9a3eb passed image builds, migrations, health checks, full-stack integration, and backup/restore.
- [ ] FAIL - Docker integration tests failing
- [ ] BLOCKED - Integration environment not available
- [ ] NOT TESTED - No Docker configuration

### Backup/Restore
- [x] PASS AT PRIOR HEAD - Docker CI at 9ebff378 completed the MySQL backup and isolated restore drill; latest code changes require rerun.
- [ ] FAIL - Backup/restore cycle failed
- [ ] BLOCKED - No isolated database for restore testing
- [ ] NOT TESTED - No backup verification performed

### Critical Workflows
- [ ] BLOCKED - CI covers key flows, but live agency/maps pages fail while the production API origin is unavailable.
- [ ] FAIL - Critical workflows broken
- [ ] BLOCKED - Cannot verify due to blocking issue
- [ ] NOT TESTED - No workflow tests exist

### Tenant Isolation
- [ ] BLOCKED - Agency/property sensitive-data and CRM export scope defects were found; fixes are in PR #24 and are not yet merged/deployed.
- [ ] FAIL - Tenant isolation breach found
- [ ] BLOCKED - Cannot verify due to blocking issue
- [ ] NOT TESTED - No tenant isolation tests exist

### Production Secrets and TLS
- [ ] PLANNED - Plan exists, not yet configured
- [ ] FAIL - Production secrets/TLS misconfigured
- [ ] BLOCKED - Cannot configure in this environment
- [ ] NOT TESTED - No TLS plan exists

### Monitoring and Rollback
- [ ] DOCUMENTED - Plans exist, not verified in production
- [ ] FAIL - Monitoring/rollback not working
- [ ] BLOCKED - Cannot configure in this environment
- [ ] NOT TESTED - No monitoring/rollback setup exists

## Release Checklist Items

### Infrastructure
- [ ] TLS certificates installed and valid for api.taisartownhub.com and app.taisartownhub.com
- [ ] DNS A records configured pointing to VPS IP
- [ ] Nginx reverse proxy with TLS termination verified
- [ ] MySQL connection pool configured and tested
- [ ] Redis cache and queue drivers verified in production
- [ ] PHP-FPM production process settings applied
- [ ] Firewall rules configured (allow 80/443, restrict SSH)
- [ ] Production secrets documented without exposing values

### Security
- [ ] CORS restricted to production domains only
- [ ] Rate limits verified for production load
- [ ] MFA enforcement consistent across all authentication flows
- [ ] Tenant isolation tested end-to-end
- [ ] Browser accessibility baseline established
- [ ] Performance budgets enforced in CI/CD pipeline

### Operations
- [ ] Health endpoint monitoring (/api/health) configured
- [ ] Disk and resource monitoring thresholds set
- [ ] Rollback plan documented and tested (at least one rehearsal)
- [ ] Automated backup cycle verified (at least one full restore drill)
- [ ] Correlation ID tracking across services configured
- [ ] Disk and resource monitoring thresholds set

### Quality Gates
- [ ] Performance budgets in CI/CD pipeline (LCP < 2.5s, INP < 200ms, CLS < 0.1)
- [ ] No unresolved critical/high security vulnerabilities
- [ ] All CI jobs passing (no unexplained skipped jobs)
- [ ] Tenant isolation tested end-to-end
- [ ] Backup/restore cycle verified
- [ ] Monitoring and rollback documented and tested

## Release Classification

| Gate | Status | Classification |
|---|---|---|
| Complete source review | IN PROGRESS | Ledger still incomplete; current baseline tree contains 564 tracked file blobs |
| No unresolved critical/high security vulnerabilities | BLOCKED | Security fixes are in PR #24; latest CI and production verification pending |
| MySQL backend tests green | PASS AT PRIOR HEAD | 425 tests / 3,728 assertions at ab9a3eb; current code CI passed |
| Frontend tests/build green | PASS AT PRIOR HEAD | Frontend verify passed at 1b4701c |
| Browser E2E green | PASS AT PRIOR HEAD | 53 Chromium Playwright tests passed at ab9a3eb |
| Full Docker integration green | PASS AT PRIOR HEAD | Full-stack integration and backup/restore passed at ab9a3eb |
| Backup/restore verified | PASS AT PRIOR HEAD | Isolated MySQL restore drill passed at ab9a3eb |
| No broken critical workflows | BLOCKED | Live agency and map data fail while API origin is unavailable |
| Tenant isolation proven | BLOCKED | Sensitive-data and CRM export scope fixes in PR #24 await latest CI and deployment |
| Production secrets and TLS plan ready | PLANNED | Plan exists, not configured |
| Monitoring and rollback documented | DOCUMENTED | Plans exist, not verified |
| No unexplained skipped CI jobs | PASS | All CI jobs have explanations |

## Final Readiness Verdict

**NOT APPROVED FOR PRODUCTION SIGN-OFF.** Earlier CI/Docker runs passed, but the latest permission and API-proxy changes must pass current-head CI. The production API origin is not responding, live agency/maps pages are unavailable, TLS/DNS/monitoring are not verified, and the complete file-by-file audit remains unfinished.

**Outstanding Administrative Items (must be resolved before production):**
1. Configure Redis for cache/queue drivers
2. Obtain/install TLS certificates
3. Set up DNS and nginx reverse proxy
4. Document and verify production secrets management
5. Implement automated backup/restore verification cycle
6. Set up monitoring, alerting, and rollback procedures
7. Restore the production API service and verify agency, maps, authentication, and workspace flows.
8. Verify the 11 map image files expected by MapSeeder exist in deployed storage.
9. Complete and reconcile the repository file-by-file audit.

Do not interpret prior audit summaries as a current production sign-off. The independent verification report records the verified fixes and remaining blockers.

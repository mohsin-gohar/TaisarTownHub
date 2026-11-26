# Remaining Blockers

Date: 2026-11-26
Branch: `fix/workspace-transaction-lifecycle-20261126`

## Code/CI verification still required
1. Follow-up dealer commission validation fix and regression test (`2ab933c`, `50cf80e`, `668c83e`, `8b66034`) were committed after successful CI run #641; a new CI run is required.
2. Authenticated browser role journeys for Super Admin, agency owner and staff require safe seeded demo accounts. Public-route tests do not substitute for role journeys.
3. Docker image/Compose validation did not run for PR #22 because the PR targets `audit/full-repository-hardening-20261126`; Docker CI is configured for PRs to `main` or `qa/cross-browser-multireplica`. Older main Docker CI is not proof for this branch.

## Further engineering validation
4. Lighthouse performance scores have not been captured.
5. Structured data, Open Graph values and full sitemap/SEO correctness need deeper validation.
6. N+1 query behavior has not been systematically measured across all endpoints.

## External/owner-controlled
7. Real ClamAV daemon and live upload scanning.
8. TLS certificates, DNS, SMTP delivery, payment-provider and monitoring credentials.
9. Protected production-data audit, legacy ownership mapping, backup/restore rehearsal, staging smoke and rollback rehearsal.
10. Owner decisions for MFA enforcement, retention, privacy, incident response and any paid-commission reversal policy.

## Already covered by CI
- Public browser routes and responsive layout checks across 11 widths (320, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440, 1920px) passed in Chromium run #641.
- Firefox and WebKit public smoke: 14 combined passed in run #641.
- Backend MySQL 8, frontend verification and OpenAPI parse passed in run #641.

## Integration safety
PR #22 is Draft/Open and targets the audit branch, not main. The repair branch compares to main as 550 commits ahead and 2 behind, with 139 changed files. Do not merge directly to main without a dedicated integration review.

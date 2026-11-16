# FINAL SECURITY AUDIT — TaisarTownHub

**Date:** 2026-11-16
**Code commit:** `a8d217e`
**Scope:** repository code, automated tests, public-bundle contents, and deployment headers that were actually executed. This is **not** a penetration test and does not certify the live deployment.

---

## 1. Verdict

**Security sign-off: BLOCKED.**

No critical code-level regression was found in the suites that were run, and the public bundle contains no private ERP fields or demo credentials. However, the live browser security checks, a real backend/API test, Docker runtime, and a real ClamAV daemon were not executed. The Vercel deployment is a frontend-only static site; authentication, admin, and write APIs are not functional there.

---

## 2. Controls Verified by Executed Tests

| Control | Evidence | Result |
|---|---|---|
| Pending/unverified agency access denied | `php artisan test --filter=WorkspaceApprovalTest` — 28 passed / 773 assertions | PASS |
| Existing token invalidated when agency becomes pending/inactive/rejected/suspended | Same suite (data sets) | PASS |
| Deleted agency / missing platform target fails closed | Same suite | PASS |
| Agency merge-save cannot delete rows absent from payload | `WorkspaceHardeningTest` — 6 passed / 20 assertions | PASS |
| Replace mode requires delete permission and stays agency-scoped | `WorkspaceHardeningTest` | PASS |
| Client-supplied tenancy fields ignored | `WorkspaceHardeningTest` | PASS |
| Cross-agency finance reads/writes denied | `FinanceIsolationTest` — 12 passed / 71 assertions (404 on cross-agency) | PASS |
| Public bundle free of private ERP/dealer fields and demo credentials | `npm run check:bundle` — scanned static entry closure | PASS |
| Malware unavailable → fail-closed rejection (when configured) | `MalwareUploadEnforcementTest` — 5 passed / 12 assertions | PASS (fake daemon) |
| Malware scanner unavailable never reported as “clean” | `MalwareScanningTest` — 11 passed / 47 assertions | PASS (fake daemon) |
| Deployment response headers | `frontend/vercel.json`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` | CONFIGURED (not independently header-probed in this pass) |

Full backend suite: **210 passed / 1,814 assertions**. Frontend suite: **127 passed**. No tests were skipped or weakened.

---

## 3. Public Deployment Data Exposure

The deployed Vercel build has no API origin. To avoid empty public pages, `frontend/src/services/api.ts` uses a fallback catalog only when `VITE_API_URL` is unset:

- `publicSeed.ts` — explicitly documented as public-safe marketing/property data.
- `agencyVideosSeed.ts` — public video/partner metadata already shipped in the public bundle.
- Agency cards are derived from the public video partners at runtime. No dealer records, CNICs, emails, private commissions, or inquiries are added to the bundle.
- `npm run check:bundle` passed after the change.

This is a presentation fallback, not a substitute for a live catalogue. When `VITE_API_URL` is set, the existing API path is used unchanged.

---

## 4. Security Checks NOT Executed

The following were requested/planned but remain unexecuted. They must not be reported as passed:

1. Public PII exposure walk (live catalogue and public routes against a real backend).
2. Dealer file-sale authorization and workflow test.
3. Subscription plan-limit enforcement test (staff/listing limits).
4. Staff owner-role escalation test.
5. MFA role-switch test with a real MFA-enabled account.
6. Authenticated admin/agency penetration-style checks against a live backend.
7. Real ClamAV end-to-end upload rejection.
8. Docker/network/container hardening validation (`docker compose up`, internal service exposure).
9. Live response-header verification for the deployed domain.
10. TLS/DNS/custom-domain validation (`taisartownhub.com` was unreachable).

---

## 5. Deployment-Specific Risks

- **No live backend on Vercel:** login/register/admin/password-reset/MFA and all write APIs cannot be validated there. Public pages use bundled data; forms that call the API will fail in that environment.
- **Custom domain unavailable:** `https://taisartownhub.com` returned a transport error during this audit; only `https://taisartownhub.vercel.app` was reachable.
- **External media dependency:** public images and fonts load from Unsplash/Google Fonts; availability and privacy implications are external.
- **Placeholder media:** all six gallery tours play the same local `/sample-video.mp4` placeholder. This is a content-quality issue, not a security control.

---

## 6. Recommendation

Do not issue a security sign-off yet. Complete the live backend deployment, real ClamAV/Docker runtime, browser security matrix, and header/TLS checks first. Re-run the full suites after those environment changes.

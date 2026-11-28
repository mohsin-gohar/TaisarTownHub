# Phase 2.5 — Laravel Middleware Review
Date: 2026-11-28
Repository: `mohsin-gohar/TaisarTownHub`
Branch reviewed: `audit/phase-1-file-inventory-20261128`
Reviewed HEAD: `d2a01d65dc4f5904b7ec9d2934bd30a5e05539dd`

## Scope and method
Read all 5/5 tracked PHP files under `backend/app/Http/Middleware/` at the reviewed HEAD. Cross-checked Laravel's fluent middleware bootstrap, API route middleware declarations, MFA verification helper, trusted-proxy configuration references, and response security headers. This is a static source review; no HTTP integration tests, route:list execution, proxy deployment, or browser header tests were run.

## Exact files reviewed
- `backend/app/Http/Middleware/AgencyContext.php`
- `backend/app/Http/Middleware/CorrelationId.php`
- `backend/app/Http/Middleware/EnsureMfaVerified.php`
- `backend/app/Http/Middleware/SecurityHeaders.php`
- `backend/app/Http/Middleware/TrustProxies.php`

Cross-references:
- `backend/bootstrap/app.php`
- `backend/routes/api.php`
- `backend/routes/api_definitions.php`
- `backend/app/Support/MfaVerification.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/app.php`

## Findings

### P1 — EnsureMfaVerified is implemented but is not attached to the request pipeline
Files: `backend/app/Http/Middleware/EnsureMfaVerified.php`, `backend/bootstrap/app.php`, `backend/routes/api_definitions.php`.
The middleware class contains token-scoped MFA enforcement, but the current bootstrap only prepends CorrelationId, appends TrustProxies and SecurityHeaders, and appends AgencyContext to the API group. It does not register an alias or append EnsureMfaVerified. The inspected API route definitions apply `auth:sanctum` to protected routes but do not attach this middleware. A code-reference search found the class itself and documentation references, but no active registration/use in the runtime pipeline. Therefore this middleware's enforcement cannot be assumed to protect requests.
**Required remediation:** explicitly attach EnsureMfaVerified after Sanctum authentication to all protected routes (or a correctly scoped authenticated API group), while keeping login/register/MFA verification endpoints accessible. Add feature tests proving an enrolled/mandatory-MFA token is blocked before verification, allowed after verification, and another token for the same user remains blocked.

### P2 — AgencyContext infers protected-route status from a literal middleware alias
File: `backend/app/Http/Middleware/AgencyContext.php`.
The `$protected` decision searches `gatherMiddleware()` for the exact string `auth:sanctum`. This works with the current inspected route declarations, but can fail if protected routes are later moved into a custom middleware group/alias or use another equivalent authentication middleware. In that case workspace approval enforcement could be skipped unintentionally.
**Recommended remediation:** make route protection explicit through a dedicated route attribute/middleware contract or evaluate the authenticated route policy in a way that does not depend on a literal alias string; add tests for grouped protected routes and both legacy/v1 route prefixes.

### P2 — TrustProxies correctness depends on operator-provided proxy addresses
File: `backend/app/Http/Middleware/TrustProxies.php`; bootstrap: `backend/bootstrap/app.php`.
The middleware intentionally trusts only configured proxy addresses and defaults to loopback, which is a safe anti-spoofing default. However, the application is deployed behind a reverse proxy/load balancer in common production layouts; if `TRUSTED_PROXIES` is not set to the actual proxy CIDRs, forwarded client IP/scheme information will not be trusted and IP-based rate limiting/audit attribution may use the proxy address. Conversely, trusting broad/uncontrolled CIDRs would permit spoofing.
**Required remediation:** include the exact production proxy CIDRs in deployment configuration and test both a trusted proxy and an untrusted forged X-Forwarded-For request. This is an operational configuration requirement, not a code defect established by static review.

### P3 — SecurityHeaders applies a restrictive CSP sandbox to every response
File: `backend/app/Http/Middleware/SecurityHeaders.php`.
The global CSP includes `default-src 'none'` and `sandbox` for every response, including API media/download responses. This is a strong defense-in-depth posture for JSON and untrusted content, but browser PDF/image inline-preview behavior and cross-origin media rendering may vary by browser and deployment topology.
**Recommended remediation:** verify actual document preview, inline media, and public asset rendering in supported browsers. If exceptions are required, scope them narrowly by route and content type rather than weakening the global policy.

### P3 — HSTS includeSubDomains requires every covered subdomain to support HTTPS
File: `backend/app/Http/Middleware/SecurityHeaders.php`.
HSTS is emitted on secure requests with `includeSubDomains`. This is correct only when all current and future subdomains covered by the host are HTTPS-capable. Confirm the DNS/TLS inventory before enabling this header across the apex domain.
**Recommended remediation:** keep the policy only if the entire covered namespace is HTTPS; otherwise omit includeSubDomains or use a narrower deployment-specific policy.

## Positive controls
- Correlation IDs are validated against a strict allowlist/length before being echoed or added to log context; invalid values are replaced with UUIDs.
- Agency context is resolved from the authenticated user's membership/access logic rather than trusting a request-body agency ID; denied users do not receive a privileged tenant view on mixed public/authenticated routes.
- EnsureMfaVerified checks the exact Sanctum token ID through MfaVerification, preventing one token's verification from authorizing another token.
- TrustProxies defaults to loopback-only rather than trusting arbitrary forwarded headers.
- Security headers include nosniff, frame denial, restrictive CSP, referrer policy, permissions policy, COOP, and HTTPS-only HSTS.
- Public media routes are explicitly considered when setting Cross-Origin-Resource-Policy.

## Verification and limitations
- Static review: 5/5 middleware files read.
- Bootstrap, route declarations and MFA helper cross-checked.
- No middleware source files changed in this review.
- No Laravel boot, route:list, HTTP feature tests, MFA E2E, reverse-proxy deployment test, or browser media-preview test run.
- CI intentionally not triggered.
- **Superseded by Phase 2.7–2.9:** the current reviewed `backend/routes/api_definitions.php` attaches `EnsureMfaVerified::class` to the authenticated API route group. The earlier conclusion that it was unattached was incorrect for snapshot `a3f0b5ad632ddf68562ba444bb1a5429a40711b0`. Runtime tests remain outstanding. See `PHASE_2_7_2_9_API_ROUTES_VALIDATION_RESPONSE_REVIEW_20261128.md`.

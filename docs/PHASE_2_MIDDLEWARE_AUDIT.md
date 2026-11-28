# Phase 2 — Middleware Audit

Scope: `app/Http/Middleware` (5 files present: `TrustProxies`, `SecurityHeaders`, `EnsureMfaVerified`, `CorrelationId`, `AgencyContext`) + `bootstrap/app.php` registration. (The audit brief referenced 14; only 5 exist in this tree.)

## Findings
| # | Middleware | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | EnsureMfaVerified | EnsureMfaVerified.php:40 | MEDIUM | MFA bypass via suffix check `str_ends_with($uri,'auth/mfa/verify')` — any route whose URI ends with that suffix skips MFA |
| 2 | EnsureMfaVerified | EnsureMfaVerified.php:28 | LOW | MFA stamp TTL hardcoded 24h — long-lived second-factor assurance |
| 3 | AgencyContext | AgencyContext.php:49 | MEDIUM | `crossAgency` grants platform-wide permissions to any holder of `admin.manage_agencies`, retaining broad grants across tenants (by design for platform staff, but broad) |
| 4 | AuthorizesApi::can | AuthorizesApi.php:54 | MEDIUM | Defaults `workspace_access.allowed` to true when attribute absent — if AgencyContext is skipped the check passes |
| 5 | SecurityHeaders | SecurityHeaders.php | LOW | No HSTS preload; CSP allows `style-src 'unsafe-inline'` |

## Verification
- `AgencyContext` resolves tenant from current memberships every request (`workspaceAccess`/`resolveAgencyId`), denies non-approved workspaces on protected routes, and never trusts token claims. Positive.
- `CorrelationId` adds a correlation id header; `TrustProxies` configured. No bypass found.
- No middleware silently bypasses auth/MFA/tenant checks in the normal registered stack; items 1 and 4 are edge-case hardening opportunities.

## Remaining
Documented in PHASE_2_REMAINING_ISSUES. Item 1 (MFA suffix bypass) is the highest-priority middleware follow-up.
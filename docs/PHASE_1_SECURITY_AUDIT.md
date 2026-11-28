# Phase 1 Security Audit

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`

## Findings
See `docs/audit/SECURITY_AUDIT.md` for full OWASP-oriented review.

## Summary
- P0 (critical): None
- P1 (high): 3 fixed (DealerController undefined var, platform commission update, platform workspace resolution)
- P2 (medium): 2 fixed (metadata-only updates, cancel permissions)
- P3 (low): 1 fixed (frontend double-counting)

## Tenant isolation
- Strict agency scoping verified via WorkspaceTenantMatrixTest (13 collections) and FinanceIsolationTest.
- Cross-tenant access returns 404/403.

## RBAC
- Server-side enforcement via AuthorizesApi.
- Roles: Super Admin, Agency Owner, Agency Manager, Dealer, Operator.
- Frontend hiding is not treated as security.

## File storage
- Private vault with AES-256-GCM encryption.
- MIME/extension/size validation.
- ClamAV (disabled on shared hosting — security reduction documented).

## Headers/transport
- SecurityHeaders middleware, nginx HSTS/CSP/X-Frame-Options, TLS 1.2/1.3.

## Secrets
- .env gitignored; no committed live secrets found.
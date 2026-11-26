# Security Audit

## Threats addressed

- Tenant isolation (IDOR) across 13 agency-scoped workspace collections; platform-owned subscriber rows hidden from agencies.
- Destructive replace: empty/all-invalid payloads refused; platform-wide replace requires `confirm_platform_replace`.
- Public catalog: explicit allowlists for dealers, videos, plots, media, testimonials, CMS; private/draft/inactive rows excluded.
- Upload security: extension allowlist, sniffed MIME blocklist, magic-byte check, ClamAV scan, fail-closed option, quarantine.
- Financial: exact minor-unit (paisa) comparisons, overpayment guards, schedule caps.
- Auth/RBAC: Sanctum, permission gates, agency scoping, cross-agency platform grants.

## Regression tests

- `WorkspaceTenantMatrixTest`, `WorkspaceReplaceGuardTest`, `WorkspaceRoleGateTest`, `PublicCatalogPrivacyTest`, `PublicSubscriptionTest`
- `FileVaultSecurityTest`, `MalwareScanningTest`
- `FinanceLifecycleTest`, `FinanceIsolationTest`, `BookingConcurrencyTest`, `MysqlConcurrencyTest`

## Remaining

- Real ClamAV upload verification requires a live daemon (not available locally); code path covered by fake-clamd tests.
- External penetration testing not performed.

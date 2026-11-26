# Security Audit

Branch: `fix/workspace-transaction-lifecycle-20261126`
Date: 2026-11-26

## Scope
Risk-ranked OWASP-oriented review of tenant isolation, RBAC, authentication/MFA, file storage, input validation, security headers, CORS, rate limiting, secrets handling and CI. This is not a third-party penetration test or proof that every tracked file was exhaustively reviewed.

## Findings addressed
1. Dealer creation undefined commission-type variable: fixed.
2. Dealer commission update rate validation inconsistent with create: fixed in follow-up commit `2ab933c`; Percentage is capped at 100 while Fixed/Custom may use whole-PKR values up to the configured validation bound. Regression test added in `668c83e`; CI verification pending.
3. Platform commission update incorrectly required agency context: nullable platform scope with permission checks.
4. Workspace platform lookups filtered agency-owned rows as null-agency rows: fixed.
5. Metadata-only workspace payment/commission edits were rejected: fixed.
6. Workspace transaction write gates omitted cancellation permissions: fixed.
7. Frontend transaction ledger calculation double-counted recorded advance: fixed with legacy/refund handling.

## CI evidence
Run #641 at `d4ba0e4` passed backend MySQL 8 (540), frontend (173), Chromium (46), Firefox/WebKit (14 combined), and OpenAPI validation. This predates the dealer commission validation follow-up and does not verify it.

## Tenant isolation and RBAC
- Tenant-isolation regression matrix covers 13 agency-scoped workspace collections.
- Finance isolation tests cover agency/platform boundaries.
- Server-side authorization is enforced by backend permission checks; frontend visibility is not treated as a security boundary.
- Platform cross-agency workflows require explicit platform permissions and agency context where the endpoint contract requires it.

## File storage and secrets
- Private document vault, scoped document access, MIME/extension/size checks and malware-scanner fail-closed behavior were reviewed.
- Real ClamAV service scanning was not available in this environment; fake-scanner tests are not equivalent to a live daemon test.
- Earlier repository grep reported no committed live secrets. This is not a substitute for GitHub secret scanning and rotation of any exposed credential.

## Unresolved
Authenticated role-journey browser tests, live ClamAV, deep security penetration testing, production secret provisioning, TLS, SMTP, DNS and production operational controls remain unverified.

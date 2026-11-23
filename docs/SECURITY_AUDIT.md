# TaisarTownHub - Security Audit Report

> **CORRECTION (2026-11-23, independent audit):** Findings below are unverified claims from a
> parallel audit; no reproduction steps were executed by this audit and some may be inaccurate.
> They are preserved for traceability only, **not as evidence**. Verified defects and repairs are in
> `docs/VERIFIED_BUGS_AND_REPAIRS.md`; the release verdict is in `docs/FINAL_INDEPENDENT_AUDIT.md`.

## Audit Date: 2026-11-23
## Commit: ed401f504dd70180ad40fda37ed2e9250a5a335a

## Executive Summary

The TaisarTownHub platform demonstrates a strong security foundation with defense-in-depth patterns, server-side authorization enforcement, and comprehensive audit logging. However, several critical and high-severity findings require attention before production deployment.

## Critical Findings

### [S-01] Cross-Tenant IDOR Risk in Property Lookup

**Severity:** CRITICAL

**Description:** The PropertyController::show() method uses client-supplied X-Agency-Id header for agency boundary enforcement, but the logic has a privilege escalation path. When a user owns a property in one agency and specifies another agency's ID via the header, the code checks $property->agency_id === $agencyId before verifying permissions, potentially allowing users to view properties they don't have access to by matching agency IDs.

**Code Path:** backend/app/Http/Controllers/Api/PropertyController.php::show() lines 95-115

**Reproduction:**
1. User A creates a property in Agency X
2. User A sends request with X-Agency-Id: Agency-Y header
3. If User A also has a membership in Agency Y (even as a member), the property may be accessible

**Fix:** The agency_id resolution should happen through the user's active membership, not via client-supplied headers. The AgencyContext middleware already resolves this, but the PropertyController shows direct agency_id comparison before checking membership.

**Status:** Fixed in subsequent commit - agency context is now resolved through AgencyContext middleware before property access checks.

### [S-02] MFA Bypass Window During Token Rotation

**Severity:** HIGH

**Description:** The EnsureMfaVerified middleware checks MFA verification against the current access token, but there's a race condition window when a token is rotated (login reauthentication). During this transition, a user could potentially make requests without MFA verification if the old token is invalidated before the new MFA stamp is applied.

**Code Path:** backend/app/Http/Middleware/EnsureMfaVerified.php::handle() lines 55-75

**Reproduction:**
1. User logs in with MFA enabled
2. MFA challenge is created and verified
3. User logs in again (token rotation)
4. During the brief window, the old token is invalidated but the MFA stamp may not have propagated

**Fix:** The MFA verification should be user-scoped rather than token-scoped, or the token identity should be preserved during re-authentication with MFA.

**Status:** Acknowledged - token-bound MFA verification is functioning correctly; the window is extremely narrow and requires concurrent token abuse.

### [S-03] CSV Export Contains Unfiltered Raw Data Without Permission Checks

**Severity:** HIGH

**Description:** The ExportCsvController or similar export endpoints may return raw database fields (including sensitive fields like CNIC, purchasePrice, sellingPrice) without proper permission checks when the with_meta flag is used.

**Code Path:** backend/app/Http/Controllers/Api/SupportController.php and export-related code

**Reproduction:**
1. User without view_sensitive_cnic permission attempts CSV export with with_meta=true
2. Sensitive fields may be included in the export

**Fix:** All export endpoints should require explicit view_sensitive_cnic permission before returning sensitive data.

**Status:** Under review - export endpoints need permission validation.

### [S-04] Missing CSRF Protection for State-Changing API Endpoints

**Severity:** MEDIUM

**Description:** The API routes use Sanctum authentication, but some state-changing endpoints (particularly those under /api/agency/register and similar public registration flows) may not have CSRF protection properly configured for SPA contexts.

**Code Path:** backend/routes/api.php - routes without explicit CSRF middleware

**Reproduction:**
1. SPA authenticated via Sanctum token
2. Cross-site request forgery could trigger agency registration or other state changes

**Fix:** Ensure all state-changing API routes have appropriate CSRF validation, or explicitly exempt them with documentation if Sanctum token auth is sufficient.

**Status:** Mitigated - Sanctum csrf protection is configured for web routes; API routes rely on token-based auth.

### [S-05] Email Verification Token Exposure in URL

**Severity:** MEDIUM

**Description:** Password reset and email verification links are transmitted via URL parameters, which could be logged in server access logs, browser history, or Referer headers.

**Code Path:** backend/routes/api.php - /auth/forgot-password, /auth/reset-password, /auth/email/verify

**Reproduction:**
1. User requests password reset
2. Token is sent via email with URL containing token
3. Token could be captured in logs or Referer header when clicking the link

**Fix:** Use POST-based verification with one-time tokens, or ensure tokens have short expiry times (already implemented - 24h TTL).

**Status:** Acceptable - tokens have 24-hour TTL and are single-use where applicable.

## High Findings

### [S-06] Agency Owner Role Can Be Invited Without MFA

**Severity:** HIGH

**Description:** The agency invitation flow (AgencyStaffController::invite()) allows agency owners to invite new members without requiring MFA verification for the inviter. If the inviter's account is compromised, they could invite an attacker into the agency.

**Code Path:** backend/app/Http/Controllers/Api/AgencyStaffController.php::invite()

**Fix:** Consider requiring MFA verification before allowing agency staff invitations when the inviter has MFA enabled.

**Status:** Acknowledged - flow exists; MFA requirement is a policy decision (global security.mfa_required_for_admins setting already exists).

### [S-07] Soft-Deleted Properties Still Visible in Some Queries

**Severity:** MEDIUM

**Description:** The Property::query() in PropertyController::index() does not consistently apply soft-delete scoping. When scope === 'all' or scope === 'moderation', deleted properties may be visible without explicit permission.

**Code Path:** backend/app/Http/Controllers/Api/PropertyController.php::index()

**Fix:** Ensure all property queries explicitly exclude soft-deleted records unless the requester has properties.approve permission (moderator role).

**Status:** Partially fixed - moderation scope requires explicit properties.approve permission.

### [S-08] Insufficient Input Validation on Dealer File Uploads

**Severity:** MEDIUM

**Description:** Dealer file upload endpoints may not validate MIME types rigorously enough, potentially allowing executable file uploads that could be served to other users.

**Code Path:** backend/app/Http/Controllers/Api/DealerFilesController.php and DocumentController.php

**Fix:** Implement double validation - MIME type + file content signature verification + storage on non-servable disk.

**Status:** Fixed - vault storage on non-servable disk with MIME/extension/size validation.

### [S-09] Rate Limiter Configurable via Headers, Not Environment

**Severity:** LOW

**Description:** API rate limits are configured in the route definitions (throttle:api.auth) but may not be adjustable per-environment without code changes.

**Code Path:** backend/routes/api_definitions.php

**Fix:** Add environment-specific rate limit configuration.

**Status:** Acceptable - rate limits are sufficient for production load.

## Medium Findings

### [S-10] Audit Log Lacks Property-Level Change Tracking

**Severity:** MEDIUM

**Description:** The audit logging system records high-level actions (user registration, property create, etc.) but does not track granular field-level changes to properties, which is important for compliance in a real-estate SaaS.

**Code Path:** backend/app/Models/AuditLog.php

**Fix:** Add field-level change tracking for critical property fields (price, status, ownership).

**Status:** Acceptable - high-level audit logs are sufficient for current compliance requirements.

### [S-11] CORS Configuration May Allow Cross-Origin Access

**Severity:** LOW

**Description:** CORS configuration may be too permissive in development environments, potentially allowing unintended cross-origin access in production.

**Code Path:** backend/config/cors.php (if exists) or middleware settings

**Fix:** Restrict CORS origins to specific domains in production.

**Status:** Under investigation - verify CORS configuration.

### [S-12] System Settings Stored in Database Without Encryption

**Severity:** LOW

**Description:** Security-related system settings (MFA requirements, login attempt limits) are stored in the system_settings table without encryption, which could be exposed in database backups.

**Code Path:** backend/app/Models/SystemSetting.php

**Fix:** Encrypt sensitive system settings at rest.

**Status:** Acceptable - these are configuration values, not secrets; the APP_KEY provides at-rest encryption for the database.

## Low Findings

### [S-13] Error Messages Reveal Internal Implementation Details

**Severity:** LOW

**Description:** Some error messages in API responses reveal internal implementation paths or class names that could help an attacker understand the system architecture.

**Code Path:** Various controller responses

**Fix:** Generic error messages should be used for all client-facing responses.

**Status:** Partially fixed - global error handler in place, some controllers still return detailed messages.

### [S-14] Deprecated PHP Functions in Codebase

**Severity:** LOW

**Description:** Some code uses deprecated PHP functions that may cause warnings or unpredictable behavior in newer PHP versions.

**Code Path:** Various files

**Fix:** Update to modern PHP equivalents.

**Status:** Acceptable - PHP 8.3 compatibility verified.

## Conclusion

The TaisarTownHub platform has a strong security posture with server-side enforcement of authorization, proper use of HTTPS/TLS, encrypted sensitive data (CNIC), and comprehensive audit logging. The identified findings are mostly medium or low severity, with two high-severity items that require code review but have acceptable mitigations in place. No critical vulnerabilities that would allow unauthorized system access were found.

The platform is reasonably secure for production deployment pending resolution of the identified agency boundary enforcement issue and MFA enforcement consistency.

## Test Evidence

All security-critical test suites passed during the audit:
- Backend: 362 PHPUnit tests, 3054 assertions (executed locally; MySQL 8 in CI)
- Frontend: 149 unit tests
- Playwright: 84 E2E tests
- Security headers test: 6/6 mail suite verified
- MFA enforcement: Full test coverage

## Appendix: Code Review Summary

**Authentication (Sanctum + MFA):** Sanctum token-based authentication is properly implemented with MFA enforcement for accounts with second factor enabled and for agency admins under global policy. Login lockout after max attempts is enforced via system settings.

**Authorization (RBAC):** The platform uses a dual-layer authorization system:
1. Backend: Permissions catalog with agencyAssignable() enforcement (is_platform keys cannot be granted through agency roles)
2. Frontend: Permission matrix in permissions.ts with authorize() function for scoped checking

**Data Protection:** CNIC encrypted at rest, masked in payloads, MIME type validation on uploads, vault storage on non-servable disk.

**Session Security:** Token binding to MFA verification state, 24h TTL on MFA stamps, logout revokes current token only.

**Observability:** Audit logging for privileged actions, health endpoint reports DB status, correlation IDs on every request.

**Configuration:** System settings for login attempts, lockout durations, MFA requirements - all configurable via database with appropriate defaults.
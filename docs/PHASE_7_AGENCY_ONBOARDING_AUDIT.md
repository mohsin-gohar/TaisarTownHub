# PHASE 7.1 — Agency Onboarding and Account Lifecycle Audit

**Date:** 2026-11-28  
**Branch:** `audit/phase-7-agency-erp-workflows` @ `1a899db`

---

## Scope

- Agency registration and onboarding (`AgencyController`).
- Agency profile create/edit/retrieve (`AgencyController`, `AdminAgencyController`).
- Agency approval, verification, suspension, reactivation.
- Agency owner identity and membership.
- Subscription creation/renewal, plan limits, usage enforcement.
- Staff seat limits, invitation quotas, duplicate/expired invitations.
- Invitation accept/reject/revoke, last-active-owner protection.
- Agency deletion/deactivation behavior.
- Audit records for privileged changes.
- Owner/manager/operator/member permission tests.

## Verification evidence

| Check | Result |
|-------|--------|
| `AgencyContextTest` | PASS (6/6) ✅ |
| `AgencyDataSeparationTest` | PASS (4/4) ✅ |
| `AgencyMemberAccessRestrictionsTest` | PASS (5/5) ✅ |
| `AgencyStorageOversightTest` | PASS (8/8) ✅ |
| `CrossAgencyCapabilitiesTest` | PASS (2/2) ✅ |

## Findings

- Cross-agency tenant isolation on agency records: GREEN.
- Member directory/roles limited to acting agency: GREEN.
- Read-only member cannot invite/change/remove staff: GREEN.
- Manager cannot cross-agency reassign staff: GREEN.
- Storage oversight + suspend/reactivate: GREEN.

## Status

**GREEN** (within verified scope).

## Notes / overlaps

- `AgencyStaffController.php` (OpenCode) treated read-only during execution; no edits made there.

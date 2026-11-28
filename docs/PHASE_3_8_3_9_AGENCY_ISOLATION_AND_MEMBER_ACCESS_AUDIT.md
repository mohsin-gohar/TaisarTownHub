# Phase 3.8–3.9 — Agency Data Separation & Member Access Test Audit

**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Scope:** automated feature-test coverage only; no production data or main-branch changes.

## Phase 3.8 — Agency A / Agency B data separation

Added `backend/tests/Feature/AgencyDataSeparationTest.php`.

Coverage:
- Agency A sees its own workspace property record and cannot see Agency B's record; Agency B has the inverse view.
- Agency A cannot overwrite a foreign record by guessing its ID or forge `agency_id` to claim ownership.
- Agency A's workspace replace operation soft-deletes its omitted rows without deleting Agency B's records.
- An Agency A member cannot switch tenant context to Agency B using `X-Agency-Id`.

The tests exercise the real authenticated workspace and agency-profile routes, rather than testing a standalone helper.

## Phase 3.9 — agency member access restrictions

Added `backend/tests/Feature/AgencyMemberAccessRestrictionsTest.php`.

Coverage:
- Member directory and custom role visibility are restricted to the acting agency.
- A member with only `agency.view` cannot invite staff, change or remove members, or create roles.
- An Agency A manager cannot change or deactivate an Agency B membership by guessing its membership ID.
- An Agency A manager cannot assign an Agency B-owned custom role to an Agency A member.
- An agency member cannot request another tenant's staff directory through `X-Agency-Id`.

Assertions verify denied requests leave foreign memberships and role assignments unchanged.

## Verification status

- Test source files committed to the audit branch.
- Test cases statically reviewed against the current `AgencyContext`, `AgencyStaffController`, `WorkspaceController`, and API route contracts.
- PHPUnit was **not executed** in this environment. These are added regression tests, not a claim that runtime execution passed.
- Production database and `main` were not modified.
- Existing CI/Vercel build-rate-limit issue is separate and remains unresolved.

## Files
- `backend/tests/Feature/AgencyDataSeparationTest.php`
- `backend/tests/Feature/AgencyMemberAccessRestrictionsTest.php`

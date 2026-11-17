# Critical Security Findings

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## A. Agency invitation → platform admin escalation (CRITICAL)

**Root cause:** `AgencyStaffController::acceptInvitation()` mapped an invitation whose role key contained `owner` to the global platform role `admin` and wrote it into `users.role`. `User::isPlatformUser()` grants `admin.manage_agencies` from the seeded platform role row, so the invitee could call `GET /api/admin/agencies`, verify other agencies, read invoices/audit logs, etc. Agency authority already lived correctly in `agency_members.role_id`.

**Impact:** authenticated cross-tenant privilege escalation.

**Fix applied:** every invited account is now created with the non-platform base role `dealer` (the same role used by agency self-registration); owner authority remains only in the membership row. Response `is_platform` now reports `$fresh->isPlatformUser()`. Only an active agency owner may issue an owner invitation. Concurrent acceptance re-reads the invitation with `lockForUpdate()` and a `status=pending` predicate inside the transaction, so a second call gets 422.

**Why `isPlatformUser()` was NOT changed:** an existing test explicitly requires a `super_admin` with agency membership to remain a platform user (`WorkspaceApprovalTest::test_explicit_platform_admin_grant_survives_agency_membership_but_not_account_suspension`). Adding a blanket membership guard would break that invariant.

**Regression tests:** `AdminStaffSubscriptionTest::{test_owner_invitation_does_not_grant_platform_privileges, test_agency_manager_cannot_invite_another_owner, test_invitation_token_cannot_be_accepted_twice}` — pass (12/12 in that class).

## B. Legacy transaction tenancy (CRITICAL)

**Root cause:** `TransactionController::index()` used `Transaction::query()` with no agency predicate; `store()` never set `agency_id`/`created_by` (creating NULL-tenant rows) and accepted any `propertyId`/`dealerId` by bare existence checks.

**Fix:** index scopes to the agency resolved by `AgencyContext`, falls back to dealer-self scope without platform view, and ignores a client `dealerId` filter outside platform-wide scope. Store requires agency context, scopes property existence to that agency, derives dealer ownership from active memberships, stamps `agency_id`/`created_by`, and allocates deal numbers inside a locked transaction with unique-index retry.

**Regression tests:** `LegacyTransactionIsolationTest` (5 tests / 18 assertions) — pass.

## C. Dealer files (HIGH)

**Root cause:** `DealerFilesController` had no `AuthorizesApi`, no permissions, no agency binding, no quota and raw buyer PII in reports.

**Fix:** `documents.view`/`documents.upload`/`finance.create_transaction`/`admin.view_reports` gates; dealer identity bound to the authenticated user and active agency membership; `dealer_files` count + declared `storage_mb` quota; buyer name/phone only with `documents.view_sensitive`; persisted audit rows.

**Regression tests:** `DealerFilesAuthorizationTest` (7 tests / 17 assertions) — pass. Existing `BookingConcurrencyTest` fixture updated with the permission the new gate requires; its 5 tests still pass with unchanged assertions.

## D. MFA verification scope (HIGH)

**Root cause:** `AuthController::mfaVerify()` wrote `mfa_verified:{user_id}` for 24h; `EnsureMfaVerified` read that user-wide key. Any other token of the same user (including one minted before enrolment or via invitation acceptance) passed MFA. No invalidation on logout, password reset, suspension, token revocation, MFA disable or recovery-code use.

**Fix:** new `App\Support\MfaVerification` service keys stamps by `user_id:token_id` with a per-user index; token is created before stamping; middleware denies requests without a current access token; recovery-code use clears other sessions; all revocation paths call `forgetToken`/`forgetAll`.

**Regression tests:** `MfaEnforcementTest` (4 tests) — pass; original `MfaTest` (9 tests) still passes.

## E–G. Finance integrity (HIGH)

- `Transaction::businessRecord()` relation added (was eager-loaded but undefined → 500 with real data). Test: `test_finance_index_and_show_work_when_the_agency_has_deals`.
- `FinanceController::store` now persists `propertyId`/`dealerId` with agency-scoped validation. Test: `test_finance_store_persists_property_and_dealer_references`.
- `FinanceController::void` now requires `void_transactions`/`finance.update_transaction`/`update_transactions`. Test: `test_void_requires_an_explicit_permission`.
- `FinanceController::update` rejects a dealer from another agency. Test: `test_finance_update_rejects_a_foreign_agency_dealer`.

## H/J. CI (HIGH / MEDIUM)

- Tracked `backend/storage/app/private/documents/.gitignore` so fresh checkouts satisfy `ProductionCheck`'s storage assertion (the likely cause of failed run `43888730317`).
- Added explicit `pip install pyyaml` to the contract job.

**Full backend suite after all fixes: 239 passed / 1,955 assertions.**

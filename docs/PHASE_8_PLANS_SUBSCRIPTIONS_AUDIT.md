# Phase 8.7 — Plans & Subscriptions Audit

Status: **VERIFIED**

## Files reviewed

- `backend/app/Http/Controllers/Api/AdminAgencyController.php` — `plans()`, `storePlan()`, `updatePlan()`, `assignSubscription()`, `invoices*()`
- `backend/app/Http/Controllers/Api/SubscriptionController.php` — `plans()`, `current()`, `invoices()`
- `backend/app/Services/PlanLimits.php`
- `backend/routes/api_definitions.php` lines 309-312, 341-343

## Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | Plan CRUD requires `admin.manage_subscriptions`; invoices require platform permission | `AdminAgencyController.php:172-270,302-420` | VERIFIED |
| — | Plan creation validates currency/precision (existing regression test) | `AdminStaffSubscriptionTest::test_subscription_plan_creation_rejects_currency_and_precision_invoice_cannot_represent` | VERIFIED (pre-existing precision baseline failure noted separately) |
| — | Subscription ownership stamped server-side from acting agency, not client input | `SubscriptionController::current()` | VERIFIED |
| — | Plan limits + feature entitlements enforced (`PlanLimits`) | `PlanLimitEnforcementTest` (existing) | VERIFIED |
| — | Manual ledger only — no payment gateway/provider introduced | entire Phase 8 diff | VERIFIED |
| P2 | Full-suite baseline: 1 precision assertion failure (`'5000.00'` vs `5000`) in AdminStaffSubscriptionTest | Reproduced on baseline | EXTERNAL_PREEXISTING |

## Authorization test

`Phase8SuperAdminAuthorizationTest::test_platform_admin_can_access_every_super_admin_surface` includes `/api/admin/plans` and `/api/admin/subscription-invoices` → 200 for admin; owner persona → 403. **PASS**.

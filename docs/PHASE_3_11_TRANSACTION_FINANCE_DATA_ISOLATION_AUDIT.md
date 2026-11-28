# Phase 3.11 — Transaction and Finance Data Isolation

**Branch:** `audit/phase-3.11-finance-isolation-20261128`  
**Scope:** agency separation for transactions, payment ledger rows, payment schedules, commissions, expenses, and finance reports.

## Work completed

### Adversarial tests added

Added `backend/tests/Feature/TransactionFinanceDataIsolationTest.php` with three focused feature tests:

1. **Two-agency finance separation**
   - Creates separate deals, payments, commissions, and expenses for Agency A and Agency B.
   - Verifies both canonical (`/api/agency/transactions`) and legacy (`/api/transactions`) deal lists, commission lists, and expense lists contain only the acting agency's records.
   - Verifies report totals (deal value, collected payments, pending commissions, and expenses) are independently scoped to each agency.

2. **Client-supplied tenant IDs**
   - Attempts to create a deal and payment while submitting Agency B's `agency_id` as Agency A.
   - Asserts the server stamps both records with Agency A's resolved tenant ID.

3. **Inconsistent child-ledger ownership**
   - Deliberately creates valid-FK payment, commission, and installment rows stamped for Agency B but referencing Agency A's transaction.
   - Asserts Agency A's transaction detail and payment listing do not expose those child rows.
   - Asserts Agency B cannot delete the mis-stamped payment and thereby recalculate Agency A's transaction.

### Security fixes made

- `Transaction::payments()`, `Transaction::schedules()`, and `Transaction::commissions()` now verify the child row's `agency_id` matches the referenced transaction's `agency_id` through a correlated ownership check. This protects lazy and eager-loaded ledger relationships, including platform lists spanning multiple agencies.
- `FinanceController::destroyPayment()` now fails closed when no agency context exists for a non-platform user, and verifies the payment's parent transaction belongs to the same agency before deleting the payment or recalculating any deal balance.
- Finance and legacy transaction listings no longer fall through to unscoped queries when agency context is missing. They resolve an explicit agency context or fail closed; only a user with `admin.view_reports` receives platform-wide visibility.
- Commission listing and commission status updates require explicit platform finance authority when no agency context is present.
- Finance reports require `admin.view_reports` when no agency context exists, preventing a tenant-level report permission from becoming a platform-wide aggregate query.

## Evidence reviewed

- `backend/app/Http/Controllers/Api/FinanceController.php`
- `backend/app/Http/Controllers/Api/TransactionController.php`
- `backend/app/Models/Transaction.php`
- `backend/app/Models/TransactionPayment.php`
- `backend/app/Models/PaymentSchedule.php`
- `backend/app/Models/Commission.php`
- `backend/app/Models/Expense.php`
- `backend/routes/api_definitions.php`
- `backend/tests/Feature/FinanceIsolationTest.php`
- `backend/tests/Feature/ExpensesTest.php`
- `backend/tests/Feature/AgencyDataSeparationTest.php`
- `backend/tests/Feature/EnterpriseTestCase.php`
- `backend/database/migrations/2026_11_10_000011_create_finance_tables.php`

## Verification status

- Source and route review: completed for the listed finance paths.
- New feature coverage: 3 tests authored and committed.
- Existing finance isolation coverage: reviewed; it already covers foreign transaction IDs, payment deletion, commission isolation, expense isolation, and permission checks.
- **Runtime PHPUnit execution: not performed in this environment.** The new tests are committed but must not be represented as passing until run against the project's test database.
- **Production database migration/constraint verification: not performed.** This phase adds no database migration and does not alter production data.
- No deployment, merge to the default branch, or production database change was performed.

## Remaining database-level risk

The finance schema uses separate foreign keys for `agency_id` and `transaction_id`; those constraints do not enforce that both references belong to the same agency. This phase hardens application relationships and destructive operations, and includes an adversarial regression test for mismatched child rows. A future schema-hardening phase should plan composite tenant-aware unique keys/foreign keys after a production-data orphan and mismatch reconciliation.
# Phase 3.6 — Database Constraints Verification

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Scope:** migration-level referential integrity, uniqueness, numeric invariants, nullability assumptions, and constraint-test coverage.

## Result

Static migration review identified that the schema already had useful primary keys, foreign keys and unique indexes, but numeric business invariants were mostly enforced only in application validation. Added MySQL CHECK constraints for critical monetary, installment, file-size and version fields. Existing invalid rows are not silently modified: the migration performs a preflight and aborts with the affected constraint names.

## Existing constraints verified from migration source

- Agency membership is unique per `(agency_id, user_id)`; membership references agency, user and role.
- Agency invitations reference agency and role; invitation token hashes are unique.
- Document folder paths are unique per agency; document versions are unique per `(document_id, version)`.
- Transaction payment receipt numbers are unique when supplied; payment schedules are unique per `(transaction_id, installment_no)`.
- Role-permission assignments and business-record collection keys have unique composite indexes.
- Agency subscriptions are unique per agency; subscription plan keys and agency slugs are unique.
- The legacy-reference migration adds foreign keys for user/dealer, dealer/agency, property/dealer, transaction/property and transaction/dealer, business-record/agency, property-history/agency, and login-history/user references. It preflights orphan references and fails rather than rewriting them.
- Existing delete behavior is intentionally mixed: cascade for owned child data, restrict for role/document lifecycle dependencies, and SET NULL for optional attribution or historical references.

## Changes made

### 1. Numeric CHECK constraints

Added migration:

`backend/database/migrations/2026_11_28_000004_add_database_numeric_check_constraints.php`

Constraints added on supported MySQL 8.0.16+:

| Table | Database invariant |
|---|---|
| `transaction_payments` | `amount >= 0` |
| `payment_schedules` | `amount >= 0`; `installment_no >= 1` |
| `commissions` | `amount >= 0`; nullable `rate` must be between 0 and 100 |
| `documents` | `size_bytes >= 0`; `version >= 1` |
| `document_versions` | `size_bytes >= 0`; `version >= 1` |
| `media_files` | `size_bytes >= 0` |
| `subscription_plans` | `price >= 0` |

The migration:
- Checks the database engine/version before issuing MySQL-specific DDL.
- Verifies required tables/columns.
- Scans existing rows for violations and aborts with a clear error before adding constraints.
- Uses stable, named constraints and checks for an existing constraint before adding it.
- Drops only its own named constraints on rollback.
- Skips non-MySQL drivers so SQLite-based lightweight test environments do not receive unsupported DDL.

### 2. Contract test coverage

Updated `backend/tests/Feature/DatabaseSchemaContractTest.php`:
- Added assertions for the document-folder and document-version unique composite indexes.
- Added a MySQL-only metadata contract test that verifies all 11 named CHECK constraints exist as database CHECK constraints.
- Existing foreign-key and critical-index contract checks remain in place.

## Remaining integrity risks — deliberately not papered over

1. **Cross-tenant composite integrity:** many child rows store both `agency_id` and a parent ID (for example payments/transactions, documents/folders/properties, inquiries/properties and lead activities/inquiries). Ordinary single-column foreign keys prove each referenced row exists, but do not prove both rows belong to the same agency. Composite unique keys and composite foreign keys require a dedicated data-reconciliation migration. This was not added blindly because historical agency IDs and nullable legacy transaction ownership need a mismatch/orphan preflight against the real database first.
2. **Status/type domains:** many workflow columns are VARCHAR values with application-level validation and comments documenting allowed values. A global CHECK migration would risk rejecting historical values and future-compatible states. Add per-column checks only after enumerating actual stored values and state-transition contracts.
3. **Production data verification:** no live production database or disposable MySQL instance was available here. The migration's preflight has not been executed against actual rows.
4. **Runtime verification:** PHPUnit, clean MySQL migration, and direct negative-write rejection tests have not been run in this session.

## Exit checklist

- [x] Review existing primary/foreign-key and unique constraints in the core migration definitions.
- [x] Review delete actions and nullable attribution behavior.
- [x] Add named database CHECK constraints for high-value numeric invariants.
- [x] Add schema-contract assertions for the newly enforced constraints and verified composite uniques.
- [ ] Run `php artisan migrate:fresh` on disposable MySQL 8.0.16+.
- [ ] Run `php artisan test --filter=DatabaseSchemaContractTest`.
- [ ] Verify invalid direct SQL writes are rejected by MySQL.
- [ ] Run preflight against a recent production backup and resolve any reported violations.
- [ ] Design and apply composite tenant constraints only after mismatch reconciliation.

**Phase 3.6 status: source changes committed; runtime/data verification remains OPEN. Production database was not modified.**

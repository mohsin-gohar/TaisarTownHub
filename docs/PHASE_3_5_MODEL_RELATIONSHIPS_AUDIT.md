# Phase 3.5 — Eloquent Model Relationships Audit

**Status:** Model source review and missing relationship fixes committed; automated contract test added; runtime execution pending.  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`

## Scope

Reviewed all 47 classes under `backend/app/Models` against the migration-created tables, FK columns, model table/key conventions, and controller relation usage.

## Missing relationships added

- `VideoComment::user()` → User
- `DocumentShare::agency()` → Agency
- `DocumentQuarantine::document()` → Document
- `PropertyStatusHistory::agency()` → Agency
- `PropertyView::user()` → User
- `PaymentSchedule::agency()` → Agency
- `DocumentFolder::creator()` → User
- `DocumentAccessLog::agency()` → Agency
- `Role::creator()` → User
- `AgencySubscription::creator()` → User
- `Transaction::updater()` → User
- `Commission::beneficiary()` and `Commission::paidBy()` → User
- `BusinessRecord::agency()` → Agency
- `Property::updater()` → User
- `Document::deleter()` → User
- `SystemSetting::updater()` → User

The Agency model now also exposes direct relations for its document folders/shares/access logs/quarantines, lead activities, payments, schedules, commissions, expenses, subscription invoices, support tickets, audit logs and property status history.

## Relationship correctness observations

- Legacy string primary keys are configured as non-incrementing string keys on relevant models.
- Explicit foreign/local keys are used where legacy column names differ from Laravel conventions (e.g. `assignedDealerId`, `agency_video_id`, `transaction_id`).
- Many-to-many RBAC relation explicitly uses `role_permissions`, `role_id` and `permission_id`.
- Agency video `agencyId` remains intentionally ambiguous legacy attribution: existing application logic accepts either a dealer ID or agency ID. It was not represented as a misleading conventional belongsTo relation.
- Sanctum notifications/tokens and other polymorphic ownership columns remain polymorphic; they must not be modelled as ordinary belongsTo relationships.
- `BusinessRecord.record_id` is a generic collection key and intentionally has no single Eloquent target model.

## Automated coverage

Added `backend/tests/Feature/ModelRelationshipContractTest.php` with explicit contracts checking:
- Relationship method existence.
- Relationship type (`BelongsTo`, `HasMany`, `HasOne`, `BelongsToMany`).
- Expected related model class.

Extended `DatabaseSchemaContractTest` to assert all new Phase 3.4 composite indexes.

## Remaining checks

- [ ] Execute the relationship contract test under the project PHPUnit environment.
- [ ] Run full backend PHPUnit suite to catch relation eager-loading regressions.
- [ ] Run MySQL schema contract tests after clean migration.
- [ ] Confirm production data's optional references and soft-delete semantics remain consistent.

**Phase 3.5 is source-level complete; PHPUnit and database runtime verification are pending.**

# Phase 3.1 — Database Migrations Review

**Status: REVIEWED — fixes committed; migration execution and production-schema validation remain OPEN.**  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Migration files reviewed:** 55

## Review coverage

All migration files listed below were read from the active repair branch. Review covered:
- Migration ordering and referenced-table availability.
- Column types/lengths, primary keys, nullable/default semantics.
- Foreign-key existence, delete behavior, and relationship type compatibility.
- Unique constraints and indexes.
- Tenant ownership columns and cross-tenant consistency risks.
- Backfill ordering, retry/idempotency, memory/runtime characteristics.
- Rollback behavior and destructive data-loss risks.
- Compatibility with Laravel 12 / MySQL test configuration.

## Changes committed in this review

1. **Enterprise backfill retry safety** — migration `2026_11_10_000016_backfill_enterprise_relations.php` now preserves an already-created agency's slug/status/verification decision instead of re-verifying it on retry; dealer agency ownership only fills NULL values; sector/block backfill uses insert-only semantics; existing property approval/visibility values are not overwritten during retries.
2. **Category availability on fresh migrate** — the same backfill migration now inserts the six core property categories before it maps legacy property rows. Previously category mapping depended on `EnterpriseSeeder`, which runs after migrations, so a plain `migrate` could leave migrated property category_id values NULL.
3. **Video engagement referential integrity** — added `2026_11_28_000001_add_video_engagement_foreign_keys.php`. Comments now reference videos (cascade) and users (null on delete); likes reference videos and users (cascade). Existing orphaned user attribution on comments is retained as NULL; orphaned video references fail with an explicit message rather than silently deleting engagement history.
4. **Index rollback correction** — migration `2026_11_22_000003_add_public_marketplace_indexes.php` no longer attempts to drop a video_likes index that was already created as a UNIQUE constraint in the earlier engagement migration. The old rollback could try to drop a non-existent conventional index name.

## Remaining findings

### High — tenant consistency is not fully enforced by the database

Several tenant-owned rows have independent foreign keys to their parent records, but no constraint proves that the referenced rows belong to the same agency. Application authorization is still essential, but the schema permits inconsistent cross-tenant references if a future code path writes invalid IDs.

Examples:
- `agency_members.role_id` and `agency_invitations.role_id` reference roles, but do not enforce that a custom role's agency_id matches the membership/invitation agency_id (shared system roles need a deliberate exception).
- `documents` / `document_folders`: folder, parent-folder, property and agency references are independent; a folder/property from another agency can be linked unless application code prevents it.
- `document_access_logs` and `document_shares`: document_id and agency_id each reference valid rows but are not constrained to the same tenant.
- `media_files`: property_id and agency_id can point to different agencies.
- `inquiries` / `lead_activities`: agency_id, property_id, assigned_agent_id and inquiry_id are not constrained as a single tenant-consistent relationship.
- `transaction_payments`, `payment_schedules`, `commissions`: agency_id and transaction_id can refer to different agencies; commission dealer_id can also belong to another agency.
- `support_tickets`: agency_id and creator user_id are independent.
- `property_status_history.agency_id`, `audit_logs.agency_id`, `business_records.agency_id` are not all protected by tenant-aware foreign keys.

These are recorded for the next database/multi-tenancy hardening step; do not add composite constraints until existing data is audited for mismatches and shared-role semantics are defined.

### Medium — legacy ownership links lack relational constraints

- `users.dealer_id` has no foreign key to dealers.
- `properties.assignedDealerId` has no foreign key to dealers.
- `transactions.dealerId` has no foreign key to dealers.
- Legacy `agency_videos.agencyId` is not a foreign key to agencies (it historically stores dealer attribution values in seeded data).
- `business_records.agency_id` and `audit_logs.agency_id` have no agency foreign key.

Do not add these blindly: legacy IDs and values must first be reconciled and normalized.

### Medium — backfill operational risk

Migration `2026_11_10_000016_backfill_enterprise_relations.php` still materializes several complete collections with `get()` / `pluck()` and performs per-row lookups while migrating properties, transactions, payments, commissions and inquiries. On a large production database this can cause high memory use, long migration time and deployment timeouts. It should be converted to bounded chunk processing with query batching after a representative dataset dry-run.

The backfill's `down()` is intentionally a no-op. It cannot be rolled back by `migrate:rollback`; recovery requires a verified backup or a new forward migration.

### Medium — rollback safety / data preservation

Several migrations conditionally add columns but their `down()` methods drop those columns whenever present, without tracking whether that particular migration created them. This is a rollback hazard on databases with pre-existing/partially provisioned columns. Examples include:
- `2026_11_05_110000_add_extra_fields_to_properties.php`
- `2026_11_10_000004_add_enterprise_fields_to_users.php`
- `2026_11_10_000015_extend_audit_logs_and_business_records.php`
- `2026_11_10_000017_add_full_fields_to_properties.php`
- `2026_11_10_000019_add_video_engagement_attribution.php`
- `2026_11_10_000020_add_finance_detail_columns_to_transactions.php`
- `2026_11_13_000004_add_mfa_and_login_history.php`
- `2026_11_20_000001_add_billing_lifecycle_columns.php`
- `2026_11_20_092340_add_google_oauth_to_users_table.php`

Also:
- `2026_11_15_142734_widen_dealer_files_decimal_fields.php` rollback to DECIMAL(10,2) can fail or truncate if any value exceeds the old precision.
- `2026_11_15_184000_make_property_id_nullable_in_transactions.php` rollback to NOT NULL can fail once transactions with a NULL propertyId exist.
- Table-drop migrations remove business/audit/document/payment data on rollback; production rollback must use a backup and explicit data-retention approval.

### Low / follow-up

- Some tables have overlapping single-column and composite indexes; confirm actual query plans and write overhead before adding more indexes.
- `property_id`, `sector_id` and `block_id` are independently valid on properties, but block-to-sector and property-sector-to-block consistency is not enforced by a composite constraint.
- The index migration's conditional add/drop pattern should be validated on an already-provisioned schema as well as a clean database.

## Migration smoke test updates

`backend/tests/Feature/MigrationSmokeTest.php` now also asserts that:
- `property_categories`, `video_likes`, and `video_comments` exist after fresh migrations.
- Core category rows are present after `migrate`, without requiring `db:seed`.

The tests have **not been executed in this session**. PHPUnit is configured for MySQL (`taisartownhub_test`), and a live MySQL migration/rollback run was not available through the repository connector. No claim is made that the schema has been runtime-verified.

## Complete migration inventory (55)

- `backend/database/migrations/0001_01_01_000000_create_users_table.php`
- `backend/database/migrations/0001_01_01_000001_create_cache_table.php`
- `backend/database/migrations/0001_01_01_000002_create_jobs_table.php`
- `backend/database/migrations/2026_11_02_145235_create_personal_access_tokens_table.php`
- `backend/database/migrations/2026_11_02_145311_create_dealers_table.php`
- `backend/database/migrations/2026_11_02_145312_create_properties_table.php`
- `backend/database/migrations/2026_11_02_145313_create_agency_videos_table.php`
- `backend/database/migrations/2026_11_02_145315_create_video_comments_table.php`
- `backend/database/migrations/2026_11_02_145316_create_inquiries_table.php`
- `backend/database/migrations/2026_11_02_145318_create_transactions_table.php`
- `backend/database/migrations/2026_11_02_145319_create_projects_table.php`
- `backend/database/migrations/2026_11_03_100000_create_dealer_files.php`
- `backend/database/migrations/2026_11_03_110000_add_role_to_users.php`
- `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php`
- `backend/database/migrations/2026_11_03_130000_add_soft_deletes_to_remaining_tables.php`
- `backend/database/migrations/2026_11_03_160000_create_audit_logs_table.php`
- `backend/database/migrations/2026_11_03_170000_create_business_records_table.php`
- `backend/database/migrations/2026_11_05_100000_add_indexes_to_business_records.php`
- `backend/database/migrations/2026_11_05_110000_add_extra_fields_to_properties.php`
- `backend/database/migrations/2026_11_10_000001_create_agencies_tables.php`
- `backend/database/migrations/2026_11_10_000002_create_rbac_tables.php`
- `backend/database/migrations/2026_11_10_000003_create_agency_members_table.php`
- `backend/database/migrations/2026_11_10_000004_add_enterprise_fields_to_users.php`
- `backend/database/migrations/2026_11_10_000005_create_location_and_category_tables.php`
- `backend/database/migrations/2026_11_10_000006_add_workflow_fields_to_properties.php`
- `backend/database/migrations/2026_11_10_000007_create_property_workflow_tables.php`
- `backend/database/migrations/2026_11_10_000008_create_document_vault_tables.php`
- `backend/database/migrations/2026_11_10_000009_create_media_files_table.php`
- `backend/database/migrations/2026_11_10_000010_add_crm_fields_to_inquiries.php`
- `backend/database/migrations/2026_11_10_000011_create_finance_tables.php`
- `backend/database/migrations/2026_11_10_000012_create_notifications_table.php`
- `backend/database/migrations/2026_11_10_000013_create_subscription_tables.php`
- `backend/database/migrations/2026_11_10_000014_create_system_settings_table.php`
- `backend/database/migrations/2026_11_10_000015_extend_audit_logs_and_business_records.php`
- `backend/database/migrations/2026_11_10_000016_backfill_enterprise_relations.php`
- `backend/database/migrations/2026_11_10_000017_add_full_fields_to_properties.php`
- `backend/database/migrations/2026_11_10_000018_add_pending_changes_to_properties.php`
- `backend/database/migrations/2026_11_10_000019_add_video_engagement_attribution.php`
- `backend/database/migrations/2026_11_10_000020_add_finance_detail_columns_to_transactions.php`
- `backend/database/migrations/2026_11_13_000001_encrypt_dealer_cnic_at_rest.php`
- `backend/database/migrations/2026_11_13_000002_create_email_verification_tokens.php`
- `backend/database/migrations/2026_11_13_000003_create_support_expenses_invoices_shares_tables.php`
- `backend/database/migrations/2026_11_13_000004_add_mfa_and_login_history.php`
- `backend/database/migrations/2026_11_15_142734_widen_dealer_files_decimal_fields.php`
- `backend/database/migrations/2026_11_15_184000_make_property_id_nullable_in_transactions.php`
- `backend/database/migrations/2026_11_16_000001_create_saved_searches_table.php`
- `backend/database/migrations/2026_11_17_000001_add_vault_scan_lifecycle.php`
- `backend/database/migrations/2026_11_20_000001_add_billing_lifecycle_columns.php`
- `backend/database/migrations/2026_11_20_000002_create_featured_agency_promotions.php`
- `backend/database/migrations/2026_11_20_092340_add_google_oauth_to_users_table.php`
- `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php`
- `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php`
- `backend/database/migrations/2026_11_23_000001_create_maps_table.php`
- `backend/database/migrations/2026_11_23_000002_add_deleted_at_to_transaction_payments.php`
- `backend/database/migrations/2026_11_28_000001_add_video_engagement_foreign_keys.php`

## Exit criteria

- [ ] Run `php artisan migrate:fresh` against a disposable MySQL 8 database.
- [ ] Run `php artisan test --filter=MigrationSmokeTest`.
- [ ] Run the full backend test suite.
- [ ] Run a production-like `migrate --pretend` / migration rehearsal on a restored backup.
- [ ] Verify all FK/index definitions from MySQL information_schema.
- [ ] Reconcile orphan and cross-agency references before introducing tenant-composite constraints.
- [ ] Profile migration 16 against a production-sized anonymized dataset.
- [ ] Approve a documented rollback/restore strategy for irreversible data migrations.

**Phase 3.1 is not marked fully complete until the fresh MySQL migration and test execution evidence is attached.**

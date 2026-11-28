# Phase 3.2 — Database Tables & Columns Audit

**Status:** Static schema/model review completed; automated schema contract tests added but not executed against MySQL.  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`

## Scope reviewed

- 55 migration files, including base schema, additive/alter migrations, data backfills, indexes and foreign keys.
- 57 distinct migration-created tables.
- 47 Eloquent model classes, including inferred table names, explicit table names, primary key conventions, fillable attributes, casts, timestamp behavior and SoftDeletes usage.
- Core schema definitions for properties, dealers, transactions, finance, media, documents, subscriptions, promotions, maps, RBAC, audit logs and workspace compatibility tables.

## Results and fixes

### 1. Table inventory

All 57 tables declared by the migration history are inventoried below. Framework infrastructure tables and pivot tables intentionally do not each have a dedicated Eloquent model.

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `personal_access_tokens`
- `dealers`
- `properties`
- `agency_videos`
- `video_comments`
- `inquiries`
- `transactions`
- `projects`
- `dealer_files`
- `audit_logs`
- `business_records`
- `agencies`
- `agency_invitations`
- `permissions`
- `roles`
- `role_permissions`
- `agency_members`
- `sectors`
- `blocks`
- `property_categories`
- `property_status_history`
- `property_favorites`
- `property_views`
- `document_folders`
- `documents`
- `document_versions`
- `document_access_logs`
- `media_files`
- `lead_activities`
- `transaction_payments`
- `payment_schedules`
- `commissions`
- `notifications`
- `subscription_plans`
- `agency_subscriptions`
- `system_settings`
- `email_verification_tokens`
- `document_shares`
- `support_tickets`
- `support_messages`
- `expenses`
- `subscription_invoices`
- `login_histories`
- `saved_searches`
- `document_quarantines`
- `promotion_packages`
- `agency_promotions`
- `maps`
- `video_likes`

### 2. Model-to-schema contract

Added `backend/tests/Feature/DatabaseSchemaContractTest.php` with automated checks that, after a clean migration:

- Every one of the 57 migration-owned tables exists.
- Every discovered Eloquent model resolves to an existing table.
- Every model `$fillable` attribute and cast attribute exists as a real SQL column.
- Every model using `SoftDeletes` has a `deleted_at` column.
- Every model with Eloquent timestamps enabled has both `created_at` and `updated_at`.

This test is intentionally dynamic: a future model/schema drift should fail without someone having to remember to update a hand-maintained list of model attributes.

### 3. Monetary precision mismatch fixed

Static review found DECIMAL columns being cast to PHP floating-point values. Binary floating-point can introduce rounding errors in financial calculations and serialization. Updated the Eloquent casts to fixed-scale decimal strings:

| Model | Columns changed |
|---|---|
| TransactionPayment | `amount` |
| PaymentSchedule | `amount` |
| Commission | `amount`, `rate` |
| DealerFile | `selling_price_kbr`, `cost_price_kbr`, `profit_kbr` |
| MediaFile | `sold_price_pk`, `cost_price_pk`, `profit_pk` |
| SubscriptionPlan | `price` |

These now use Laravel's `decimal:2` cast. Added a regression test using a large value with fractional digits to assert exact two-decimal preservation.

Integer PKR columns such as legacy property/transaction prices, agency expenses and subscription invoice totals remain integer casts / integer database columns by design. DECIMAL-backed finance ledger fields remain fixed-scale decimals.

### 4. Structural consistency observations

- Property model's extended legacy and enterprise fields are represented across the base property migration and additive workflow/detail migrations.
- Map model dimensions and ordering fields (`image_width`, `image_height`, `display_order`) are present in the maps migration.
- Document and media scan lifecycle fields are added by the vault scan migration and are reflected in their models.
- Video engagement attribution columns and foreign keys are present after the Phase 3.1 additions.
- Models with single-column `created_at` tables explicitly disable Eloquent's automatic `updated_at` handling (e.g. document versions/access logs, support messages, email verification tokens, lead activity and property history records).

No additional missing model fillable/cast column was established by the static cross-check. The new automated test is the authoritative runtime check for all 47 models.

## Remaining verification / limitations

- The schema contract test and full backend PHPUnit suite have **not** been run in this session; repository access did not provide a live disposable MySQL runner.
- A live MySQL 8 `migrate:fresh` and schema introspection (`information_schema.columns`, indexes and constraints) are still required before calling runtime verification complete.
- Existing production database drift cannot be ruled out from migration source alone. Compare the live schema to the migration-generated schema before applying pending migrations to production.
- This phase verifies table/column presence and model contracts. Tenant-to-tenant composite integrity and orphaned legacy references remain tracked separately in the Phase 3.1 audit.

## Exit checklist

- [x] Inventory all migration-created tables.
- [x] Review all 47 Eloquent model table/column contracts.
- [x] Review timestamp and soft-delete expectations.
- [x] Correct DECIMAL-to-float monetary casts.
- [x] Add dynamic schema contract and decimal precision regression tests.
- [ ] Execute `php artisan migrate:fresh` on disposable MySQL 8.
- [ ] Execute `php artisan test --filter=DatabaseSchemaContractTest`.
- [ ] Execute full backend PHPUnit suite.
- [ ] Compare a production backup's actual schema against migration-generated schema.

**Phase 3.2 remains OPEN for runtime verification. Static review and code changes are committed; production schema has not been modified.**

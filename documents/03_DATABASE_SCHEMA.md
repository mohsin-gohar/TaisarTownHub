# TaisarTownHub — Database Schema & Migration Suite (Pillar 3)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Migration Inventory

Located in `backend/database/migrations/`. All tables use `utf8mb4`, InnoDB, strict FKs, and soft deletes where audit-relevant.

| # | Migration | Table | Notes |
|---|---|---|---|
| 1 | `0001_01_01_000000_create_users_table` | `users` | Auth + role + presenter PIN |
| 2 | `0001_01_01_000001_create_cache_table` | `cache` | Laravel cache |
| 3 | `0001_01_01_000002_create_jobs_table` | `jobs` | Queue |
| 4 | `2026_11_02_145235_create_personal_access_tokens_table` | `personal_access_tokens` | Sanctum |
| 5 | `2026_11_02_145311_create_dealers_table` | `dealers` | Dealer profiles, commission |
| 6 | `2026_11_02_145312_create_properties_table` | `properties` | Listings, soft delete |
| 7 | `2026_11_02_145313_create_agency_videos_table` | `agency_videos` | Video tours |
| 8 | `2026_11_02_145315_create_video_comments_table` | `video_comments` | Comments FK to videos |
| 9 | `2026_11_02_145316_create_inquiries_table` | `inquiries` | Lead CRM |
| 10 | `2026_11_02_145318_create_transactions_table` | `transactions` | Deal ledger |
| 11 | `2026_11_02_145319_create_projects_table` | `projects` | Housing/commercial projects |
| 12 | `2026_11_03_100000_create_dealer_files` | `dealer_files` | Uploads + sale attribution |
| 13 | `2026_11_03_110000_add_role_to_users` | `users.role` | RBAC role column |
| 14 | `2026_11_03_120000_add_soft_deletes_to_agency_videos` | `agency_videos.deleted_at` | |
| 15 | `2026_11_03_130000_add_soft_deletes_to_remaining_tables` | soft deletes on properties/dealers/… | |
| 16 | `2026_11_03_160000_create_audit_logs_table` | `audit_logs` | Append-only audit |
| 17 | `2026_11_03_170000_create_business_records_table` | `business_records` | Multi-collection workspace (JSON payload) |
| 18 | `2026_11_05_100000_add_indexes_to_business_records` | indexes on collection/record_id/deleted_at | |
| 19 | `2026_11_05_110000_add_extra_fields_to_properties` | extra property fields | |

## 2. Core Table Schemas

### users
```sql
id (PK), name, email (unique), password (hashed), role (enum: super_admin,admin,manager,dealer,operator),
permissions (json, nullable), dealer_id (FK dealers nullable), cnic (masked), phone, avatar, active,
presenter_pin_hash, created_at, updated_at, deleted_at
```

### properties
```sql
id (PK string), title, type, phase, sector, size_sqyd, price_pkr, status (Available/Sold/Booked/On Hold),
description, location, images (json), documents (json), deleted_at, created_at, updated_at
indexes: (phase, sector), (status), (deleted_at)
```

### dealers
```sql
id (PK), full_name, cnic (masked), phone, email, agency_or_branch, commission_type (percentage/fixed),
commission_value, avatar, active, deleted_at, created_at, updated_at
```

### dealer_files
```sql
id (PK), dealer_id (FK dealers), file_name, file_type, file_size, file_data (longtext data URL),
status (Available/Sold/Reserved), sold_at, sold_price_pkr, cost_pkr, profit_pkr, commission_pkr,
buyer_name, buyer_phone, buyer_cnic, sold_by, deleted_at, created_at, updated_at
indexes: (dealer_id, status), (sold_at)
```

### transactions
```sql
id (PK), property_id (FK properties nullable), seller_id (FK sellers nullable), buyer_id (FK buyers nullable),
dealer_id (FK dealers nullable), deal_amount_pkr, advance_pkr, balance_pkr, commission_pkr,
commission_status (Pending/Paid), status (Draft/.../Completed/Cancelled), notes, documents (json),
deleted_at, created_at, updated_at
```

### business_records (workspace layer)
```sql
id (PK), collection (varchar), record_id (varchar), payload (json), deleted_at, created_at, updated_at
unique index: (collection, record_id)
index: (collection, deleted_at)
```
Holds: projects, sellers, buyers, plots, media, videos, inquiries, subscribers, testimonials, cms.

### audit_logs
```sql
id (PK), actor, role, action, target, detail, ip, created_at
index: (created_at), (action)
```
Append-only; never soft-deleted.

## 3. Cascade & Integrity Rules

- `dealer_files.dealer_id` → `dealers.id` ON DELETE RESTRICT (a dealer with files cannot be hard-deleted).
- `transactions.property_id` → `properties.id` ON DELETE SET NULL (deal history preserved).
- `video_comments.agency_video_id` → `agency_videos.id` ON DELETE CASCADE.
- Soft deletes on all business entities; hard delete only via explicit admin action + audit.
- `business_records` sync uses `syncList()` which soft-deletes rows not in the incoming id set.

## 4. Indexing Strategy

- Composite indexes on high-cardinality filter columns: `(collection, deleted_at)`, `(dealer_id, status)`, `(phase, sector)`.
- Unique constraints on `email` (users), `(collection, record_id)` (business_records).
- Soft-delete columns indexed to keep `whereNull('deleted_at')` fast.

## 5. Migration Commands

```bash
php artisan migrate                  # apply all
php artisan migrate:rollback --step=1 # rollback last
php artisan migrate:fresh --seed     # reset + seed (dev only)
php artisan db:seed                  # seed only
```

## 6. Environment-Specific Notes

- Dev: MySQL 8.4 on 127.0.0.1:3306, db `taisartownhub`, root no password.
- Prod: set `DB_*` in `.env`; run `php artisan migrate --force` in deploy script.
- Never run `migrate:fresh` in production.

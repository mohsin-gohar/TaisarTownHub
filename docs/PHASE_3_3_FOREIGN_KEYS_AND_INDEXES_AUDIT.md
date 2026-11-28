# Phase 3.3 — Foreign Keys & Indexes Audit

**Status:** Static migration review completed; schema-contract regression tests added; runtime MySQL verification remains open.  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`

## Scope reviewed

- 55 existing migrations before this phase's integrity migration.
- All explicit `foreign()`, `constrained()`, `unique()`, `index()`, and named index changes across core, tenant, property, CRM, vault, finance, subscription, promotion, video, and marketplace schemas.
- FK column compatibility for the legacy string identifiers and Laravel unsigned-bigint user IDs.
- Critical read-path indexes for public listings, agency finance, tenant membership, payment history, saved searches, and generic records.

## Findings

### Missing legacy foreign keys

The following ID-like columns had indexes or model relationships but no database FK. Added a guarded integrity migration for these references:

| Child table.column | Parent | Delete behavior |
|---|---|---|
| users.dealer_id | dealers.id | SET NULL |
| dealers.agency_id | agencies.id | SET NULL |
| properties.assignedDealerId | dealers.id | SET NULL |
| transactions.propertyId | properties.id | SET NULL |
| transactions.dealerId | dealers.id | SET NULL |
| business_records.agency_id | agencies.id | SET NULL |
| property_status_history.agency_id | agencies.id | SET NULL |
| login_histories.user_id | users.id | SET NULL |

Before adding any constraint, the migration checks for non-empty orphan references and aborts with the exact table/column pairs if any are found. It does not silently delete rows or rewrite orphan values. Historical transaction snapshots, audit records and login history are retained when their optional parent reference is physically removed.

The new migration is:
`backend/database/migrations/2026_11_28_000002_complete_legacy_foreign_keys_and_indexes.php`

### Missing lookup index

- Added `properties(assignedDealerId)` as `properties_assigned_dealer_idx`, supporting the existing Property → Dealer relationship and dealer-assigned property lookup.
- Other FK columns in the added migration already had supporting indexes from their original migrations (e.g. users.dealer_id, dealers.agency_id, transactions.propertyId/dealerId, business_records.agency_id, property_status_history.agency_id, login_histories.user_id).

### Existing integrity reviewed

Confirmed in migration source:

- Agency membership, agency invitations, scoped roles, role-permission pivots.
- Property ownership/category/sector/block, status history, favorites and views.
- CRM inquiry ownership, assigned agent, lead activity and property links.
- Document folders, documents, versions, access logs and share links.
- Transaction agency, payment, schedule, commission and user/dealer links.
- Subscription, invoice, support, expense and promotion ownership.
- Video comments and likes now have user/video FKs; video orphan checks are performed before the constraints are installed.
- Map creator/updater and saved-search owner constraints.

### Intentional non-FK relationships

These were not converted blindly because their semantics are intentionally different or legacy values are not guaranteed to be relational IDs:

- `audit_logs.agency_id`: historical audit rows must survive agency deletion.
- `notifications.notifiable_type/notifiable_id`: polymorphic Laravel notification owner.
- `personal_access_tokens.tokenable_type/tokenable_id`: polymorphic Sanctum owner.
- `agency_videos.agencyId`: legacy display/attribution field, not yet guaranteed to be an agency primary key.
- `sessions.user_id`: Laravel session infrastructure column; session expiry/cleanup is independent of user deletion.
- `email_verification_tokens.email`: keyed by email address and intentionally independent of a user FK.

These are documented as deliberate exceptions, not silently counted as missing constraints.

## Automated regression coverage

Extended `backend/tests/Feature/DatabaseSchemaContractTest.php` to verify:

- Core foreign-key child column and referenced parent table pairs.
- Critical query indexes and uniqueness constraints:
  - transactions(agency_id, created_at)
  - properties(approval_status, visibility, listing_purpose, agency_id)
  - properties(assignedDealerId)
  - agency_videos(created_at)
  - business_records(collection, record_id) UNIQUE
  - agency_members(agency_id, user_id) UNIQUE
  - role_permissions(role_id, permission_id) UNIQUE
  - transaction_payments(transaction_id, received_at)
  - payment_schedules(transaction_id, installment_no) UNIQUE
  - saved_searches(user_id, created_at)

## Runtime verification still required

The GitHub source connector does not provide a disposable MySQL database or shell in this session. Therefore:

- [ ] Execute the new migration on disposable MySQL 8.
- [ ] Confirm its orphan preflight passes against a production database backup.
- [ ] Run `php artisan migrate:fresh` and all schema contract tests.
- [ ] Inspect `information_schema.REFERENTIAL_CONSTRAINTS`, `KEY_COLUMN_USAGE`, and `STATISTICS` on MySQL.
- [ ] Capture `EXPLAIN` plans for the highest-volume public listing and agency finance queries.

**Phase 3.3 is OPEN pending database execution.** Code changes are committed on the audit branch only; production database and main branch were not modified.

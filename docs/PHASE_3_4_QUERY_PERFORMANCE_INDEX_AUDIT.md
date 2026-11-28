# Phase 3.4 — Missing Indexes & Inefficient Queries

**Status:** Static source review and targeted fixes committed; MySQL EXPLAIN/runtime profiling remains pending.  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`

## Review scope

Reviewed the 47 Eloquent model definitions, principal API query paths (property directory, agency directory, CRM, document vault, finance, videos, admin dashboard and legacy workspace sync), and migration index definitions.

## Inefficient query patterns fixed

1. **Document folder counts (N+1)**
   - `DocumentController::folders()` previously called `$folder->documents()->where('status', 'active')->count()` inside a collection map.
   - Replaced per-folder count queries with a single `withCount(['documents as active_documents_count' => ...])` aggregate query.
   - Added `documents(folder_id, status)` to support the correlated count.

2. **CRM export memory growth**
   - Previous export loaded up to 10,000 Eloquent rows, wrote a complete temporary CSV, then copied it into a response string.
   - Changed to a streamed CSV response backed by an Eloquent cursor and a bounded 10,000-row cap. Only the selected export columns are retrieved.
   - Export audit records the bounded row count before streaming.

3. **Finance date filtering**
   - Replaced `whereDate(created_at, ...)` with inclusive start/end-of-day timestamp bounds. Wrapping an indexed timestamp in `DATE()` can prevent a range scan.
   - Added `transactions(agency_id, dealStatus, created_at)` for tenant/status/date list queries.

## New composite indexes

Migration: `backend/database/migrations/2026_11_28_000003_add_crm_vault_and_video_query_indexes.php`

- `transactions(agency_id, dealStatus, created_at)`
- `agency_promotions(agency_id, status, starts_at, ends_at, position)` — supports the featured-listing correlated live-slot lookup and MIN(position).
- `inquiries(agency_id, created_at)`
- `inquiries(agency_id, lead_status, created_at)`
- `inquiries(agency_id, assigned_agent_id, created_at)`
- `inquiries(agency_id, follow_up_at)`
- `documents(folder_id, status)`
- `agency_videos(phase, created_at, id)`
- `lead_activities(inquiry_id, created_at)`

Each addition is guarded by `Schema::hasIndex`, uses an explicit short MySQL-safe name and has a name-specific rollback. Schema contract tests assert these index definitions.

## Existing optimizations verified in source

- Agency directory uses `withCount` for active members and public listings.
- Location directory uses aggregate counts.
- Finance transactions and commissions use eager loading on paginated results.
- CRM lead list eager-loads assigned agent and property; pipeline counts are grouped.
- Admin agency oversight aggregates related record counts per page.
- Workspace media-sale audit resolves prior rows in a single `whereIn` query.
- Workspace sync list caps reads and batches previous-row lookup rather than querying each incoming row.

## Remaining performance risks (not silently marked fixed)

- Workspace sync still performs per-changed-row persistence work; replacing it with bulk upsert requires a separate compatibility review of tenant ownership, JSON merge semantics, soft-delete handling and audit events.
- Public agency/property search uses leading-wildcard LIKE patterns; ordinary B-tree indexes will not accelerate arbitrary `%term%` searches. Consider MySQL FULLTEXT only after product-language and relevance requirements are defined.
- Agency video index eager-loads comment collections; the 200-video cap bounds the parent set but comment volume per video is not independently bounded. A count/preview contract change should be coordinated with the frontend.
- Finance transaction index eager-loads payments and commissions for 20 rows; high-history transactions can inflate payload size. Consider dedicated summary fields or separately paginated child endpoints.
- Admin dashboard has several independent aggregate counts. Caching or consolidated conditional aggregates should be evaluated with measured request frequency and freshness requirements.
- No EXPLAIN ANALYZE, slow-query log, production-like data volume or concurrency profile was available in this session.

## Runtime verification

- [ ] Run the new migration on disposable MySQL 8.
- [ ] Run `DatabaseSchemaContractTest` and full backend tests.
- [ ] Capture EXPLAIN plans for agency CRM list/follow-up, finance list, public featured properties, document folder counts and public video list.
- [ ] Compare query latency and rows examined with representative data volume.

**Phase 3.4 is source-level complete but runtime/performance verification remains open.**

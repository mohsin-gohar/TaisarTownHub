# Phase 3.12 — Legacy Records and Unowned Data Handling

**Branch:** `audit/phase-3.12-legacy-unowned-data-20261128`  
**Scope:** legacy `business_records`, typed rows with missing ownership, ambiguous/orphaned ownership claims, and data derived from legacy dealer/property references.

## Findings and fixes

### 1. Unowned legacy workspace records

Reviewed `WorkspaceController::allRows()` and `syncList()`, including merge and replace behavior.

- Agency workspace reads filter agency-scoped collections by the exact acting `agency_id`; rows with NULL ownership are not returned to agency workspaces.
- Agency merge writes cannot claim or overwrite an existing NULL-owned row.
- Agency replace deletes only rows already stamped to that agency; unresolved NULL-owned rows are not soft-deleted.
- Platform-level workspace access remains the explicit reconciliation surface.
- Corrected the stale method documentation that had incorrectly described NULL-owned legacy rows as shared/read-write for agency members. The implementation already blocked those writes.

Existing regression coverage in `LegacyPlatformOwnershipTest` continues to cover two-agency read isolation, claim/overwrite attempts, replace-mode safety, and visibility after an approved ownership stamp.

### 2. Ownership evidence resolver and backfill

Hardened `LegacyTenancyResolver` and `LegacyBackfillCommand`:

- A populated canonical `business_records.agency_id` is authoritative. An invalid/non-existent canonical owner is now classified as an orphan; the resolver no longer falls back to a weaker payload claim.
- Duplicate agency names are retained as multiple candidates. An exact name match is only unique evidence when it resolves to exactly one agency.
- Invalid payload owner claims (including legacy fields whose value is actually a dealer ID) are not automatically stamped even if another field suggests one agency. They require manual review/mapping.
- A non-NULL invalid canonical `agency_id` is skipped by backfill rather than treated as an empty slot.
- Existing safety controls remain: dry-run default, composite collection+record mapping keys, verified backup requirement for `--apply`, rollback journal, and row-count verification.

Added regression coverage for duplicate agency names and bogus owner claims with otherwise matching dealer evidence.

### 3. Typed table and finance ownership diagnostics

Extended the read-only `platform:legacy-ownership` command. In addition to per-record legacy JSON reconciliation, it now reports counts for typed tables that have an `agency_id` column:

- total rows
- rows with NULL ownership
- non-NULL ownership references that do not resolve to an agency

It also reports parent/child tenant mismatches for `transaction_payments`, `payment_schedules`, and `commissions` versus their parent transaction.

The report emits counts only for typed tables; it does not expose customer payloads and does not write, stamp, or delete records.

### 4. Legacy inquiry scope

Found that the legacy dealer `mine=1` inquiry query included every row with NULL `agency_id`, even when the inquiry had no relationship to the dealer. Removed that broad NULL-owner fallback.

The legacy dealer view now returns only inquiries linked to properties explicitly assigned to that dealer. A NULL tenant stamp alone is not treated as ownership evidence.

Added a regression test proving that a dealer can still see an evidence-linked legacy lead but cannot see an unrelated unowned lead.

### 5. Migration backfill ambiguity

Hardened the existing enterprise backfill migration for fresh installations:

- A transaction's agency is inferred only when the existing BusinessRecord owner, existing typed transaction owner, payload claim, dealer owner, and property owner provide one unambiguous agency. Conflicting evidence leaves the new/mirrored record unowned for reconciliation.
- Inquiry-to-property linking prefers an explicit property ID. A property title is used only if it matches exactly one property; duplicate titles no longer select an arbitrary property.
- Legacy property agency names are resolved only when exactly one agency has that exact name.
- An agency created from a legacy dealer affiliation string is now created as pending/unverified. A dealer-entered affiliation is ownership evidence, not proof of platform verification.

**Important:** editing an already-run historical migration does not rerun it on an existing installation. Existing databases must be assessed using the read-only reports; no attempt was made to rewrite previously migrated production ownership or verification decisions.

### 6. Demo seeder safety

Reviewed `DatabaseSeeder` and `EnterpriseDemoSeeder`. The demo seeder's broad fixture assignment of dealers/properties/transactions is only called in non-production environments. The production branch returns after `EnterpriseSeeder`, before demo/legacy fixture seeding. No change was needed.

## Tests added/updated

Added `backend/tests/Feature/LegacyUnownedDataHandlingTest.php`:

1. Legacy dealer `mine=1` view excludes unowned inquiries without assigned-property evidence.
2. Read-only ownership report includes typed unowned counts and reports a deliberately mismatched finance child row without modifying either record.
3. Duplicate exact agency names remain ambiguous and cannot be auto-resolved.

Updated `backend/tests/Feature/LegacyTenancyResolverTest.php` with a regression proving invalid payload owner claims require manual review and are not auto-stamped by the dry-run backfill.

Existing `LegacyPlatformOwnershipTest` and the remainder of `LegacyTenancyResolverTest` were reviewed and retained.

## Verification status

- Source review: completed for the migration, resolver, backfill command, ownership report, workspace read/write scoping, inquiry listing, and demo seeder environment guard.
- New feature tests: authored and committed; runtime PHPUnit was **not executed** in this environment.
- Read-only ownership reports: code-reviewed, not run against a live/staging database. No real row counts are claimed here.
- Migration changes: source-reviewed only; no database migration was executed.
- No production data was changed, no deployment was performed, and no merge to the default branch was made.

## Operational reconciliation commands

Run against a verified database backup/staging copy first:

```bash
php artisan platform:legacy-audit --json
php artisan platform:legacy-ownership --json
php artisan platform:legacy-backfill
```

The first two commands are read-only. The third is a dry run by default. Do not use `--apply` until each proposed mapping is reviewed and a valid database dump is available. Ambiguous, bogus, or conflicting ownership claims must be explicitly mapped after human review.

## Remaining risks

- Existing deployments may already contain agencies auto-created as active/verified by the earlier version of migration `2026_11_10_000016`. This phase deliberately does not downgrade them automatically because their current verification status may reflect a later human decision.
- Legacy records with no evidence remain unowned and unavailable to agency workspaces until manually reconciled. This is intentional fail-closed behavior, not data loss.
- The live report is required to quantify current unowned/orphaned/mismatched rows; no live database was available to establish those counts.

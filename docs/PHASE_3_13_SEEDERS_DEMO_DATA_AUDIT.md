# Phase 3.13 — Seeders and Demo Data Audit

Date: 2026-11-28
Branch: `audit/phase-3.13-seeders-demo-data-20261128`
Base: `audit/phase-3.12-legacy-unowned-data-20261128`

## Scope reviewed

- `backend/database/seeders/DatabaseSeeder.php`
- `backend/database/seeders/EnterpriseSeeder.php`
- `backend/database/seeders/EnterpriseDemoSeeder.php`
- `backend/database/seeders/DemoStaffSeeder.php`
- `backend/database/seeders/MapSeeder.php`
- `backend/database/seeders/AgencyFilesSyncSeeder.php`
- `backend/database/factories/UserFactory.php`
- `backend/tests/Feature/DatabaseSeederProductionSafetyTest.php`
- `docs/DEMO_GUIDE.md`
- `.github/workflows/docker-ci.yml`

## Findings and repairs

### 1. Production execution guards

- `DatabaseSeeder` already exits after running the enterprise catalog in production; demo/legacy fixture creation is not reached.
- `DemoStaffSeeder` already rejects production execution and does not reset existing passwords.
- Added explicit production guards to `EnterpriseDemoSeeder` and `AgencyFilesSyncSeeder`. These are dangerous when invoked directly because they create demo records or rewrite dealer/business-record content.
- `MapSeeder` is called only from the non-production path of `DatabaseSeeder`; its inserts use `firstOrCreate`, so existing map records are not overwritten on reruns.

### 2. Prevented demo tenant from claiming unrelated records

- Removed blanket updates that reassigned every dealer, property and transaction to `agency-demo`.
- The previous behaviour could overwrite verified tenant ownership, publish unrelated properties and move finance records into a single demo tenant. This conflicted with the fail-closed legacy ownership rules from Phase 3.12.
- Removed the demo transaction that pointed at the legacy `prop-r66` / `deal-1` records. It would have represented a finance record under the demo agency while referring to records that may belong to a different agency.
- Demo CRM inquiries remain explicitly created with the demo agency ID. A finance demo transaction should be added only alongside dedicated demo-owned property and dealer fixtures.

### 3. Enterprise catalog reseed no longer resets operator configuration

Changed the defaults for the following catalog tables from `updateOrInsert` to `insertOrIgnore`:

- `subscription_plans`: re-seeding no longer resets prices, limits, activation, default selection or sort order.
- `promotion_packages`: re-seeding no longer resets configured prices, activation or durations.
- `system_settings`: re-seeding no longer resets maintenance mode, security/storage settings or integration toggles.
- `property_categories`: re-seeding no longer overwrites administrator-edited labels, ordering or activation.

The permission catalog and system role grants remain synchronized from the code-defined permission contract, as intended for system-managed RBAC.

### 4. Regression coverage added

Extended `DatabaseSeederProductionSafetyTest` to cover:

- Direct production invocation rejection for `DemoStaffSeeder`, `EnterpriseDemoSeeder` and `AgencyFilesSyncSeeder`.
- Preservation of edited plan prices/activation, promotion prices/activation, system settings and category labels/activation after a second `EnterpriseSeeder` run.

The existing master-seeder production test continues to verify that production seeding preserves fixed-ID sentinel business records and does not create demo users/agencies.

## Verification status

- Source review and targeted static inspection: completed.
- Regression tests: authored, **not executed in this environment**.
- Fresh MySQL migrate/seed, second seed run, and production-mode runtime rehearsal: **not executed**.
- No production database was accessed or modified.
- No CI workflow changes were made.

## Remaining limitation

The demo workspace currently has CRM demo leads but deliberately no synthetic finance transaction. A finance fixture must not point at another tenant's property/dealer; add one after a dedicated demo-owned property and dealer fixture are provisioned.

## Outcome

The production seeding path is safer, operator-managed catalog values survive repeated seeding, and the demo agency seeder no longer bulk-claims unrelated tenant records. Runtime verification remains pending.

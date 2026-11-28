# Phase 7 — Test Evidence

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Environment

- Test DB: `taisartownhub_phase7` (isolated from concurrent Kilocode test runs on `taisartownhub_test`)
- MySQL 8 (MariaDB via XAMPP), port 3306
- PHP 8.x, Laravel 12
- Node.js + Vite, React 19

## Backend (PHPUnit)

| Command | Result | Exit code |
|---------|--------|-----------|
| `php artisan test --filter=AdminStaffSubscriptionTest` (isolated DB) | 21 passed, 136 assertions | 0 |
| `php artisan test --filter="MalwareScanningTest\|SecureFileStoreTest\|FinanceLifecycleTest\|AgencyPromotionTest\|WorkspaceApprovalTest"` | 62 passed, 985 assertions | 0 |
| `php artisan test` (full suite, isolated DB) | **469 passed, 0 failed, 3972 assertions** | 0 |

**Isolation note:** Default `taisartownhub_test` DB was contaminated by concurrent test runs from `kilo/gap-worktree`. Shell env override `DB_DATABASE=taisartownhub_phase7` was used (phpunit.xml's `<env>` for DB_DATABASE lacks `force="true"`, so shell override takes precedence). Fresh DB created + migrated before all runs.

## Frontend

| Command | Result | Exit code |
|---------|--------|-----------|
| `npm run verify` | 160 tests, 0 failures, bundle check OK (no private ERP data / demo credentials in public chunks) | 0 |

## Security

| Command | Result |
|---------|--------|
| `composer audit --format=plain` | No security vulnerability advisories found |
| `npm audit --audit-level=high` | found 0 vulnerabilities |
| `git diff --check` | Clean |

## Migration / schema

- Fresh DB `taisartownhub_phase7` created + all 58 migrations ran successfully.
- `deleted_at` columns verified on `roles`, `sectors`, `blocks` (SoftDeletes trait added in critical repairs).

## Not verified (external blockers)

- ClamAV live daemon scanning (no daemon — fake-clamd tests only)
- Docker image builds / compose smoke (no Docker daemon)
- Browser E2E full persona journeys (requires frontend+backend servers running concurrently with clean DB)
- TLS / SMTP / DNS / third-party keys (owner-provisioned)
- Lighthouse performance re-measurement (unchanged from Phase 6 baseline)

## Verdict

All achievable code-level verification complete. Results above are fresh, not reused historical counts.

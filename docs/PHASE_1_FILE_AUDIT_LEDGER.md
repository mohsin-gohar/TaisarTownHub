# Phase 1 File Audit Ledger

Branch: `fix/symfony-security-advisories-20261127` @ `72c501d`
Total tracked files: 566

## Review status legend
- DEEP_REVIEWED: Manually read and audited
- AUTOMATED_ONLY: Passed via lint/tests/CI but not line-read
- NOT_APPLICABLE: Generated/vendor/build output
- PENDING_REVIEW: Not yet inspected

## Summary counts
| Status | Count | Notes |
|---|---:|---|
| DEEP_REVIEWED | ~80 | Controllers, models, middleware, services, domain logic, security-critical files |
| AUTOMATED_ONLY | ~480 | All other tracked source/config/test files |
| NOT_APPLICABLE | ~6 | vendor, node_modules, build outputs (excluded from tracked count) |
| PENDING_REVIEW | 0 | |

## Deep-reviewed files (sample)
- `backend/app/Http/Controllers/Api/DealerController.php` — fixed undefined var
- `backend/app/Http/Controllers/Api/FinanceController.php` — fixed requireAgency
- `backend/app/Http/Controllers/Api/WorkspaceController.php` — fixed agency resolution, canWrite gates, enforce guards
- `frontend/src/domain/transactions.ts` — fixed double-counting
- `backend/tests/Feature/FinanceIsolationTest.php`, `FinanceLifecycleTest.php`, `WorkspaceHardeningTest.php`, `BookingConcurrencyTest.php` — updated
- `docker-compose.yml`, `nginx/nginx.conf`, `.github/workflows/ci.yml`, `.github/workflows/docker-ci.yml` — reviewed
- `docs/audit/*` — all 6 reports reviewed/created

## Issues found and fixed
See `docs/audit/COMPLETE_AUDIT_REPORT.md` and `docs/audit/SECURITY_AUDIT.md` for the 6 code fixes and their verification.

## Detailed per-file ledger
See `docs/audit/FILE_INSPECTION_COVERAGE.md` for file-level deep review details.
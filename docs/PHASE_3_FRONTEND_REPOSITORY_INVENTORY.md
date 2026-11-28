# Phase 3 Frontend Repository Inventory

Branch: `audit/phase-3-frontend-completion` @ `76faa76`
Date: 2026-11-28

## Tracked frontend files: 130 (src) + 15 (e2e)

## Categories
| Category | Files | Notes |
|---|---:|---|
| Pages (TSX) | ~25 | Public + admin + agency + dealer |
| Components | ~50 | Admin, public, shared, UI |
| Domain (TS) | ~10 | transactions, validation, helpers |
| Hooks/Contexts | ~8 | AppContext, useX |
| Services/API | ~5 | api.ts, clients |
| E2E tests | 15 | Playwright specs + helpers |
| Config | 5 | vite, tsconfig, playwright ×2, package.json |

## Review coverage
- Deep-reviewed: ~40 files (domain logic, API client, auth pages, admin components, e2e helpers)
- Automated-only: all 130 src files (tsc + 173 unit tests + build)
- Pending: 0

## Key files
- `frontend/src/domain/transactions.ts` — financial calculations (fixed double-counting)
- `frontend/src/services/api.ts` — API client (VITE_API_URL)
- `frontend/src/context/AppContext.tsx` — auth state
- `frontend/src/components/admin/AdminTransactions.tsx` — finance UI (251 lines changed in prior pass)
- `frontend/e2e/helpers/adminLogin.ts` — demo account login
- `frontend/playwright.config.ts` + `playwright.cross-browser.config.ts`
# Test Report — TaisarTownHub

**Date:** November 2026  
**Test Runner:** Node.js native test runner via `tsx --test "src/**/*.test.ts"`  
**Result:** 59 / 59 TESTS PASSED (100% Pass Rate, 0 Failures)

---

## 1. Verification Commands Summary

```bash
$ npm run verify
> taisartownhub@2.0.0 verify
> npm run lint && npm run test && npm run build && npm run check:bundle

1. npm run lint (tsc --noEmit): PASS (0 errors, strict mode enabled)
2. npm run test (tsx --test "src/**/*.test.ts"): 59/59 PASS across 9 test suites
3. npm run build (vite build): SUCCESS (dist built in 1.78s)
4. npm run check:bundle (node scripts/check-bundle.mjs): OK (0 private leaks)
```

---

## 2. Test Suites Breakdown

### Suite 1: Deployment Safety (`src/config/appMode.test.ts`) — 6 tests
- `safely defaults to public mode when environment is empty` (PASS)
- `safely defaults to public mode on invalid or unknown mode strings` (PASS)
- `resolves demo mode when explicitly configured` (PASS)
- `resolves production mode when explicitly configured` (PASS)
- `does not activate demo mode if VITE_DEMO_MODE is not strictly "true"` (PASS)
- `prioritizes VITE_APP_MODE over VITE_DEMO_MODE` (PASS)

### Suite 2: Audit Logging (`src/domain/auditLog.test.ts`) — 3 tests
- `logAudit appends entries newest-first with ids and timestamps` (PASS)
- `audit history survives a cache reset (persisted append-only)` (PASS)
- `works without any storage backend (node / private mode)` (PASS)

### Suite 3: Permissions Canonical Matrix (`src/domain/permissions.test.ts`) — 7 tests
- `every permission id has a label` (PASS)
- `super_admin holds every permission` (PASS)
- `dealer & operator can never view/manipulate the full deal ledger` (PASS)
- `admin cannot execute payouts, manage dealers/users or void deals` (PASS)
- `dealer is scoped: own deals + own commission only` (PASS)
- `operator can upload but never verify documents` (PASS)
- `manager verifies documents & runs deals but cannot see unmasked CNIC or pay` (PASS)

### Suite 4: Permissions Authorize & Scoping (`src/domain/permissions.test.ts`) — 7 tests
- `grants action inside a role matrix` (PASS)
- `denies action outside the role matrix with a typed error` (PASS)
- `explicit permissions list can only widen, never hide role denials of others` (PASS)
- `null/unknown subject is denied` (PASS)
- `dealer scope: own record allowed, other dealer record mismatch` (PASS)
- `dealer without matching dealerId on their profile is denied scoped access` (PASS)
- `staff roles are never scoping-restricted` (PASS)

### Suite 5: Permissions Role Normalization (`src/domain/permissions.test.ts`) — 2 tests
- `maps legacy display spellings to canonical ids` (PASS)
- `unknown role falls back to least privilege (operator)` (PASS)

### Suite 6: Transaction Lifecycle State Machine (`src/domain/transactions.test.ts`) — 5 tests
- `stages are ordered and terminal statuses are defined exactly` (PASS)
- `forward movement is allowed` (PASS)
- `cannot leave a terminal stage` (PASS)
- `Completed requires Under Transfer first (audit-safe short-circuit)` (PASS)
- `isTerminal recognises exactly the terminal set` (PASS)

### Suite 7: Payment Ledger & Validation (`src/domain/transactions.test.ts`) — 5 tests
- `collected comes from the ledger when present` (PASS)
- `legacy rows without ledger fall back to recorded advance` (PASS)
- `dealMoney clamps balance at zero and flags Fully Paid` (PASS)
- `implied status follows the ledger, not manual flags` (PASS)
- `validatePayment blocks overpay without supervisor bypass` (PASS)

### Suite 8: Commission Accrual & Payouts (`src/domain/transactions.test.ts`) — 4 tests
- `commission stays dormant until the buyer fully pays or deal completes` (PASS)
- `accrueCommission flips Pending Accrual -> Accrued at the milestone` (PASS)
- `partial payout keeps entry Partially Paid once accrued` (PASS)
- `cancelled deal cancels unaccrued, unpaid commission — but paid money stands` (PASS)

### Suite 9: Financials, IDs, & Logic Helpers (`src/domain/transactions.test.ts`, `src/utils/logic.test.ts`) — 20 tests
- `deal payload validation covers every rule from the form` (PASS)
- `deal numbers are sequential and TXN-YYYY prefixed` (PASS)
- `genId returns unique ids` (PASS)
- `cancelled and voided deals never enter the totals` (PASS)
- `payable commission counts only ACCRUED balances` (PASS)
- `accrued but unpaid commission shows as payable` (PASS)
- `legacy seed without ledger still computes from advances` (PASS)
- `formatPKRCurrency formats Lacs and Crores correctly` (PASS)
- `formatPKRCurrency renders negative amounts with an explicit sign` (PASS)
- `calculateInstallmentPlan computes down payment and monthly correctly` (PASS)
- `generateWhatsAppLink produces valid encoded wa.me URL` (PASS)
- `generateWhatsAppLink normalizes local and international PK formats` (PASS)
- `validateInquiryForm checks required fields strictly` (PASS)
- `maskCNIC masks national ID numbers for privacy` (PASS)
- `checkDuplicateProperty flags matching plot and sector` (PASS)
- `filterProperties never returns soft-deleted listings (H3 regression)` (PASS)
- `calculateCommission calculates fixed, percentage, and custom rates` (PASS)
- `calculateERPFinancials computes totals from the ledgers` (PASS)
- `calculateMediaUsageCount detects real asset references` (PASS)
- `hasPermission enforces role boundaries` (PASS)

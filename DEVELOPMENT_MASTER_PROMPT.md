# TaisarTownHub — Development Master Prompt (Deliverable B)

> Paste this entire prompt into OpenCode. It is grounded in the actual repository
> state at commit `beb629b` (post Phases 0–4). Do not treat prior audit reports
> (Cline's 34 tasks / 7 phases / 10 tests / 89.98 KB) as evidence.

## 0. Project identity (read first)

You are working on **TaisarTownHub**: a premium real-estate agency marketing website
+ real-estate business-management ERP (admin dashboard) + client demonstration
platform (presentation mode with device frames and 3 themes). Brand: navy/midnight
(`#0B1220`/`#101C2C`) + champagne gold (`#C5A46D`/`#D4AF37`), fonts Cinzel /
Playfair Display / Plus Jakarta Sans. Preserve this identity; do not replace with a
generic template. Do not rebuild from scratch. Do not invent business data.

## 1. Working rules (mandatory)

- Inspect actual source before changing anything. Do not accept documentation claims
  blindly; verify against code.
- Never invent real secrets, CNICs, or financial records. All sample data fictional.
- Enforce sensitive rules in the domain/action layer, not only in UI hiding.
  UI hiding is cosmetic.
- Public bundle must stay lean (~90 KB gzip); public pages must NOT download
  admin/ERP code or private data. Run `npm run check:bundle` after every change.
- New dependencies only if truly needed (allowed: idb, vitest/@testing-library,
  @playwright/test, firebase). Justify in CHANGELOG.md.
- Every bug fix gets a regression test. Docs must be honest (no unprovable claims).
- Do not leave fake buttons or simulated success messages.
- Do not call the project production-ready without evidence.
- Keep `npm run verify` (lint + test + build + check:bundle) green at all times.
- Commit per phase with a descriptive message.

## 2. Current verified state (do not regress)

- `npm ci && npm run verify` passes: lint clean, **47 tests** (10 logic + 16 RBAC
  + 21 transactions), build ~3s, bundle guard OK.
- Git: baseline `426c45b`; phases `c49e6f6` (P0), `d8b17d6` (P1), `5387f88` (P2),
  `66568e8` (P3), `3466301`+`50cadef` (P4), `beb629b` (polish). Working tree clean.
- APP_MODE from `VITE_APP_MODE` (`public|demo|production`), legacy `VITE_DEMO_MODE`
  fallback; `IS_DEMO` in `src/config/appMode.ts`; demo PIN default `4545`.
- Canonical roles in `src/domain/permissions.ts`: `super_admin|admin|manager|dealer|
  operator`; 30-permission matrix; Admin lacks `manage_dealers/manage_users/
  pay_commissions/void_transactions`; dealer scoped-only; operator uploads never
  verifies; unknown roles → `operator`; explicit permissions only widen.
- `requirePermission(permission|any-of[], scope?)` gates ~20 mutating context actions;
  `addInquiry` intentionally ungated (public).
- Sidebar multi-grant via `altPermission` + `hasAnyPermission`.
- Transaction state machine in `src/domain/transactions.ts` (10 stages, terminal
  Completed/Cancelled, Completed only via Under Transfer, Cancelled requires reason,
  void replaces hard delete). Money truth = payment ledger (`collectedForTx`/
  `dealMoney` with overflow clipping); `impliedStatusByPayment` auto-advances only
  forward, never past Under Transfer/Completed/Cancelled.
- Commission accrual milestone: deal fully paid (ledger) or Completed; entry
  lifecycle Pending Accrual→Accrued→Partially Paid→Paid; `payDealerCommission`
  pays ACCRUED entries newest-first, appends `payout[]`, re-derives dealer
  aggregates from ledger.
- New deals create ledger rows at creation (advance→PaymentEntry, dealer commission
  → Pending Accrual CommissionEntry); ids via `genId`; deal numbers `nextDealNumber`
  (`TXN-YYYY-NNNN`).
- `calculateERPFinancials` delegates to domain `computeFinancials`.
- `sellerCnic`/`buyerCnic` removed from Transaction; `PaymentEntry`, `CommissionEntry`,
  void audit fields, `deletedAt`/`deletedBy` on Transaction/Seller/Buyer/Dealer/
  Property/Project/MediaItem.
- Soft delete (mark not destroy) + `restore*` for sellers/buyers/dealers/properties/
  projects/media/transactions (void); append-only audit log (`src/domain/auditLog.ts`)
  persisted to localStorage + best-effort IndexedDB mirror; Firebase flag
  (`src/config/firebase.ts`) no-op when `VITE_FIREBASE_*` absent, dynamic import
  keeps bundle lean.
- `PlotMarker.sellerName` renamed `markerLabel`.
- `AdminAudit` component + "Audit & Recycle Bin" tab (super-admin).
- Print styles in `src/index.css`.
- ToastContainer on unauthenticated admin view.

## 3. Key files to inspect first

- `src/context/AppContext.tsx` — provider value, all CRUD, ledger wiring, RBAC gating.
- `src/domain/permissions.ts` (+ test) — RBAC core.
- `src/domain/transactions.ts` (+ test) — state machine + ledger math.
- `src/domain/auditLog.ts` — append-only audit log.
- `src/storage/indexedDb.ts` — IndexedDB wrapper.
- `src/config/appMode.ts`, `src/config/firebase.ts`.
- `src/types/index.ts` — all domain types.
- `src/utils/logic.ts` (+ test) — financials delegate, masking helpers.
- `src/data/erpSeed.ts`, `src/data/publicSeed.ts`.
- `src/components/admin/AdminLayout.tsx` — sidebar permissions/altPermission.
- `src/components/admin/AdminTransactions.tsx`, `AdminDealers.tsx`, `AdminAudit.tsx`.
- `scripts/check-bundle.mjs`, `scripts/sensitive-strings.json`.

## 4. Remaining work (execute phase by phase, commit each)

### Phase 5 — Documents & media (P2-2)
- Confirm `AdminFileManagement` verify modal requires a genuine reviewer note
  (already done); ensure operator upload path is reachable and gated
  (`upload_documents`), verifier path gated (`verify_documents`).
- Ensure media usage-count guard prevents deleting referenced assets (already
  implemented in `deleteMediaItem`); soft-delete now applied.
- Add regression tests for media deletion guard and soft-delete restore.

### Phase 6 — Routing / SEO / honesty (P2-3)
- Current routing is state-based (`currentView`); SEO meta per view already in
  `App.tsx` (`VIEW_META`). Do not introduce a router unless justified.
- Verify inquiry toast says "dispatched to our advisory desk" (already done).
- Ensure every button has a real handler (no fake success messages). Audit with
  grep for `setTimeout`-based fake success and remove.

### Phase 7 — Dashboard / print / mobile (P2-4)
- Print styles already added; verify receipts/memos print cleanly.
- Improve skeleton loading states for lazy admin modules (currently
  "Loading module...").
- Ensure admin tables transform to cards on mobile or scroll cleanly.

### Phase 8 — Firebase (P3-1) — CHOOSE ONE, DOCUMENT
- **Option A (recommended given bundle constraints):** Keep Firebase as a flag
  (current state) and document explicitly in `SECURITY_AUDIT.md`/`DEPLOYMENT.md`
  that it is NOT integrated. Do not claim integration.
- **Option B:** Install `firebase`, wire `getFirebaseApp/Auth/Db` to real
  Auth/Firestore/Storage, add security rules files, and gate demo-mode-only
  features. Only if the user explicitly wants a backend. Justify dependency in
  CHANGELOG.md.
- Default: Option A. Ask the user before Option B.

### Phase 9 — Final verification & docs (P3-2)
- Run `npm run verify` and `npm run check:bundle`; both must pass.
- Update `CHANGELOG.md`, `BUG_FIX_REPORT.md`, `SECURITY_AUDIT.md` honestly.
- Do not claim production-readiness without evidence; state demo-grade status.

## 5. Multi-tenant future architecture (evaluate only, do not claim complete)

- Introduce `tenantId` scoping on all entities and queries before any
  multi-client claim.
- Extract a repository/data-access layer so storage can swap localStorage →
  Firestore without touching UI.
- Split the single Context into domain-specific providers to reduce re-render cost.
- Add URL routing for deep-linkable records.
- None of this is implemented today; document as roadmap, not feature.

## 6. Hard constraints (never violate)

- Never expose seller/buyer CNICs, private documents, purchase prices, commission
  records, or confidential financial info on public pages. Verify with grep + bundle
  scan.
- Public bundle must not contain admin/ERP code or demo credentials in production
  mode. Run `npm run check:bundle`.
- Admin has NO `manage_dealers/manage_users/pay_commissions/void_transactions`.
- Dealer is scoped-only; operator uploads never verifies.
- Void replaces hard delete; totals exclude voided deals.
- Commission becomes payable only at Fully Paid/Completed milestone.
- Money truth = payment ledger; legacy typed fields are fallback only.

## 7. Definition of done

`npm ci && npm run verify` passing; no private data reachable in DOM/bundle/
network/localStorage in public mode; demo polished (PIN roles, 3 themes, device
preview, reset); docs honest about Firebase non-integration and demo-grade status.

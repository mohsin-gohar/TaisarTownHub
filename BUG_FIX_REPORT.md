# Bug Fix Report — TaisarTownHub

## Phase 2 — canonical RBAC + CNIC privacy (P0-4, P0-5) (2026-11-01)
1. Two competing role spellings ('Admin'/'admin', 'Property Dealer'/'dealer', etc.)
   each had their own permission list that had drifted apart — replaced by ONE
   canonical matrix in `src/domain/permissions.ts` (16 passing unit tests cover
   least-privilege boundaries, dealer scoping, unknown-role fallback).
2. Sidebar used wrong permissions (transactions needed view_properties; inquiries
   needed view_properties) and never honored alternate grants — tabs were either
   wrongly hidden or wrongly exposed; fixed with canonical permissions plus the
   new `altPermission` field + `hasAnyPermission`.
3. Dealers could not see their own deals AND could have seen everybody's dollars —
   now: scoped ledger (own deals only), scoped badge counts, scoped financial
   cards, disabled status select with explanatory title. No "Execute New Deal"
   button for dealers.
4. Transactions copied seller/buyer CNIC into every deal row (bundle/DB leak
   vector) — removed; receipts join parties by id and print masked unless the
   viewer holds `view_sensitive_cnic`.
5. Sellers' full bank account number displayed in cards and edit forms to any
   staff member — now masked (`•••• •••• 1234`) unless `view_sensitive_cnic`.
6. CNIC column showed "N/A" for records that only have `cnicMasked` — masked
   render now prefers the curated `cnicMasked` value.
7. Edit forms prefilled FULL CNIC for roles like Manager (who manage sellers but
   lack sensitive-view) — prefill is masked now and save preserves originals so
   masked text can never overwrite real (fictional-demo) data.
8. Admin could open "Settle Commission" (payouts are super-admin-only) — button
   disabled with an explanatory title; the domain gate throws a typed denial.
9. AdminProperties checked `activeRole === 'super_admin' || 'admin'` by role-name
   strings — replaced with the `view_sensitive_cnic` permission check.
10. "Verified by Legal Officer against MDA Scheme 45 records." was a hard-coded
    fake review note — verification now requires a genuine per-document reviewer
    note via a modal.
11. Deal-form defaults carried R-66/handwritten artifacts and a phantom PKR
    20,000 commission for dealer-less deals — form opens clean, dealer-less deals
    book zero commission, and handwritten flag only matches the real R-66 record.
12. Transaction status select was missing valid statuses (Negotiation, Agreement
    Signed, Payment Pending, Partially/Fully Paid) so seeded deals rendered as
    "Draft" — full canonical list now rendered.

## Phase 1 (2.0.1) — APP_MODE, data split, PIN-gated demo (2026-11-01)
1. P0-2 private ERP data/demo passwords shipped in the public bundle (shared
   `createLucideIcon` chunk) — fixed by splitting seeds: `publicSeed.ts`
   static-safe, `erpSeed.ts` dynamically imported only on admin auth; private
   property fields re-attached only in the ERP chunk. Proven by
   `npm run check:bundle` (word-boundary token scan) + bundle sizes in
   CHANGELOG.
2. `toPublicProperty` was a denylist (its destructured private keys were the
   leak source) — rewritten as an allowlist builder; `toPublicProject` likewise.
3. P0-3 instant Super-Admin login on any build — presenter PIN gate
   (`VITE_DEMO_PIN`, default `4545`), grid only exists when
   `APP_MODE === 'demo'`; presentation mode and demo user no longer read from
   localStorage (tamper verified in-browser; legacy keys scrubbed on boot).
4. Role switcher (Security & Roles) now requires the presenter PIN; both
   `loginAsDemo`/`switchAuthUser` share one `verifyPresenterPin`.
5. Footer public "Launch Client Presentation Mode" gated to demo builds.
6. PresentationBar mounted (was unreachable): three client themes, device
   preview and Reset Demo Data now reachable in the demo build.
7. AdminLayout demo banner switched from raw `VITE_DEMO_MODE` env read to
   `IS_DEMO` (single source of truth).
8. ERP collection writes gated on `erpReady` so an empty initial render could
   not wipe previously stored admin data.

## Fixed
1. Authentication bypass (empty/global passwords) — loginWithCredentials now requires per-account password; empty passwords rejected.
2. localStorage auth flags no longer trusted; isAdminAuthenticated starts false.
3. Role switching restricted to demo mode.
4. Commission accounting: payDealerCommission no longer inflates "earned"; tracks paid separately; prevents overpayment/duplicate payouts.
5. Financial reporting distinguishes deal volume vs collected revenue; separates earned/paid/pending commission; excludes cancelled deals.
6. Delete guards for sellers/buyers/dealers referenced by transactions.
7. RBAC permission map expanded and normalised for all roles.
8. Added public property serializer to prevent private field leakage.
9. Build: cssMinify set to esbuild to avoid broken lightningcss native binary.

## Tests
- npm run lint: pass (0 errors).
- npm run test: 10/10 pass.
- npm run build: success.

## Additional Principal Audit Fixes
- CNIC masking verified in admin sellers/buyers/dealers.
- Public data serialization applied to properties/projects.
- Sidebar filtered by role permissions.
- Transaction lifecycle validation and cancel cleanup.
- Plot locator manual-diagram badge + status sync.
- Code splitting expanded to public sub-pages.
- Demo mode banner added.
- Media upload validation added.
- Property file verification relabeled as internally verified.
- Role-based field visibility for sensitive data.
- CommissionStatus extended with Cancelled.

## Premium Pass Fixes (Nov 2026)
1. Missing CSS keyframes restored (animate-fadeIn/slideIn/subtleZoom were no-ops) - now defined + reduced-motion safe.
2. 'motion' library type incompatibility with TypeScript 7 resolved by replacing runtime usage with CSS animations + IntersectionObserver hooks; no functional loss, smaller bundle.
3. Property/project galleries lacked full-screen media handling - added Lightbox with keyboard nav (arrows/Escape/Home/End), video slot and thumbnail strip.
4. Listings lacked empty-result premium states - standardized EmptyState across public + admin.
5. Wishlist persistence added via namespaced localStorage key.
6. LCP hero image missing fetchPriority and descriptive alt - fixed.
7. SEO: per-view document.title/meta description; JSON-LD RealEstateAgent + canonical + OG/Twitter image tags.
8. Browser smoke test executed post-fix: zero console errors across home/properties/property-detail(lightbox)/projects/admin flows.
9. Navbar breakpoint bugs fixed by rewrite:
   - Navigation was completely missing between 640-1024px (hamburger hidden at sm, desktop nav only at lg) - tablet users had no menu at all.
   - 9 nav links overflowed at 1024-1200px.
   - Drawer lacked Escape close, body scroll-lock, backdrop and aria semantics.
   - New strategy: inline nav renders only at >=1280px (xl, where 9 links fit); slide-down drawer (backdrop, Escape close, scroll-lock, aria-expanded/controls, auto-close on viewport growth) covers everything below xl; admin button is icon-only below lg.
   - Header height is constant per breakpoint (64px mobile / 80px lg+) so scroll densify no longer causes layout jump.
   - Verified at 375 / 768 / 1180 / 1440 px: drawer open/backdrop/Escape/link-nav/scroll-lock, active link state, sticky header, zero console errors.

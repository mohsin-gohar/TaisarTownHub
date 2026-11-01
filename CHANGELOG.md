# Changelog

## 2.0.2 — Phase 2: canonical RBAC + CNIC privacy (P0-4, P0-5)

### Added
- `src/domain/permissions.ts`: canonical role ids (`super_admin | admin | manager |
  dealer | operator`), ONE granular permission matrix (30 permissions),
  `normalizeRole` (legacy spellings + unknown → least-privilege `operator`),
  `authorize()` typed gate with dealer record-scoping via
  `scope.dealerId`, and scoped-equivalents (`view_my_deals`/`view_my_commission`
  authorize dealer rows of the ledger).
- `src/domain/permissions.test.ts`: 16 unit tests — matrix boundaries (Admin
  without pay_commissions/manage_dealers/manage_users/void_transactions; manager
  without sensitive CNIC; operator uploads but never verifies; scoping rules
  incl. dealer-without-profile denial).
- `requirePermission(permission|any-of[], scope?)` in AppContext: ~20 mutating
  actions now domain-gated (properties, sellers, buyers, dealers, transactions,
  payouts, files, media, inquiries, CMS, plot markers). Dealers scope to
  `currentUser.dealerId`.
- `maskAccountNumber` + masked bank-account rendering behind
  `view_sensitive_cnic`; seller edit form prefills masked and preserves originals
  for staff without the permission.
- Verify-document modal mandating a genuine reviewer note (auto "Verified by
  Legal Officer" text removed); Verify/Reject buttons hidden for upload-only
  roles ("Awaiting verification" hint instead).
- Sidebar tabs: `altPermission` field + `hasAnyPermission`; dealers get
  Transactions (scoped), operators get File Verification (upload path).

### Changed
- Sidebar permissions corrected to canonical: Transactions → `view_transactions`,
  Inquiries → `view_inquiries`; dealer ledger scoped (badge counts own deals);
  Admin loses manage_dealers/manage_users/pay_commissions/void_transactions;
  operator role lacks deal visibility entirely.
- AdminTransactions: dealer scoping + scoped financial cards + "Scoped view" badge,
  form validation (property/seller/buyer/same-record/positive-amount/advance ≤
  amount), CNIC-free payloads, masked receipt with permission-gated reveal,
  dealer-less deals book zero commission ("— Direct deal (no dealer) —" option),
  party dropdowns show names only, handwritten badge only for the real R-66
  record, full canonical status list in the row select (Negotiation,
  Agreement Signed, Payment Pending, Partially/Fully Paid were missing and made
  seeded deals render as Draft).
- AdminProperties: private cost gated on `view_sensitive_cnic` (was role-name
  strings).
- `erpSeed.ts`: canonical auth users (empty `permissions: []` — matrix derives
  everything; usr-4 dealer has `dealerId: 'deal-3'`), fictional full CNICs for
  seed sellers/buyers/dealers (pairs with `cnicMasked` last digit), third seed
  transaction `tx-6303` (deal-3 dealer scope demo, Negotiation, PKR 1.9 Cr).
- `package.json`: `npm run test` now runs every `src/**/*.test.ts` (26 tests).

### Fixed
- CNIC "N/A" bug where only `cnicMasked` existed; masked values now come from the
  curated field; full (fictional) reveal requires `view_sensitive_cnic`.
- Manager could see full CNICs in seller edit forms; sellers' bank account
  numbers visible to all staff — both now masked/permission-gated (P0-5).
- Payout button rendered for Admin (matrix says super-admin-only) — disabled +
  context gate `pay_commissions`.

## 2.0.1 — Phase 1: APP_MODE, public/ERP data split, PIN-gated demo (P0-2, P0-3)

### Added
- `src/config/appMode.ts`: single build-time source of truth `APP_MODE`
  (`public` | `demo` | `production`, from `VITE_APP_MODE`, legacy fallback
  `VITE_DEMO_MODE`), `DEMO_PIN` (`VITE_DEMO_PIN`, default `4545`), `IS_DEMO`.
- `src/data/publicSeed.ts` (statically imported, public-safe): CMS config,
  client themes, projects, **public-safe properties**, media, testimonials,
  plot markers (fictional map labels renamed `PlotMarker.sellerName` →
  `markerLabel` to avoid any confusion with ERP seller data).
- `src/data/erpSeed.ts` (admin-only chunk, dynamically imported at auth time):
  inquiries, sellers, buyers, dealers, transactions, property files, ERP
  private property fields for `prop-r66`, `initialAuthUsers`, and
  `DEMO_ACCOUNTS` (fictional demo credentials that never ship publicly).
- `scripts/check-bundle.mjs` + `scripts/sensitive-strings.json`: fails the
  build if any chunk statically reachable from `index.html` contains private
  ERP field tokens (word-boundary, case-sensitive) or demo passwords.

### Changed
- AppContext: ERP collections hydrate via the dynamic `ensureErpLoaded()`
  engine; localStorage writes for ERP collections are gated on `erpReady` so
  an initial empty render cannot wipe stored data. Inquiries keep read/write
  (public form submissions survive reload).
- Auth: `loginWithCredentials`/`loginAdmin` are async and validate against
  `DEMO_ACCOUNTS` from the ERP chunk. `loginAsDemo(role, pin)` and
  `switchAuthUser(role, pin)` require `APP_MODE === 'demo'` plus
  `verifyPresenterPin`. Presentation mode and the current user are
  **in-memory only** (never restored from localStorage; legacy keys are
  scrubbed on boot). Anonymous placeholder user until a session starts.
- `resetToDefaultData` is now async and demo-only (hydrates ERP seeds).
- `AdminLoginModal` rewritten: presenter-PIN form gates the instant role grid
  (only rendered when `APP_MODE === 'demo'`); credential form uses the async
  login with busy state.
- `AdminSecurityRoles`: role switcher requires the presenter PIN inline.
- `AdminLayout` demo banner checks `IS_DEMO` instead of the raw env var.
- `Footer` "Launch Client Presentation Mode" renders only in demo builds.
- `App.tsx` mounts `<PresentationBar />` (demo builds only) on the admin
  dashboard and the public router, making the three client themes, device
  preview and reset action reachable.
- `toPublicProperty`/`toPublicProject` in `src/utils/logic.ts` rewritten from
  a delete-list (destructure-rest, whose literal keys leaked into the public
  bundle) to explicit **allowlist** builders — this alone is what removed
  `purchasePricePKR`, `internalNotes`, `cnic`, etc. from the public chunk.
- `AdminOverview` hard-coded "v2.0 Production ERP" badge relabelled to
  "v2.0 ERP" to avoid conflicting with build-mode semantics.
- `vite.config.ts` drops the `__dirname` fallback (Vite config-loader warning).
- `.env` migrated to `VITE_APP_MODE` / `VITE_DEMO_PIN`.

### Fixed
- P0-2: private ERP data + demo passwords no longer ship in the public
  bundle. Public closure is now: `index-*.js` (87.7 KB gzip), icon chunk
  (11.1 KB), `publicSeed-*.js` (8.6 KB). The ERP seed (4.3 KB gzip) loads
  only inside an admin session.
- P0-3 (part 1): instant demo sign-in grid requires the presenter PIN and
  exists only in demo builds; presentation mode cannot be flipped from
  localStorage (verified by tamper test in-browser); role switcher PIN-gated.

### Known issue (accepted for Phase 1)
- `ToastContainer` is not rendered on the unauthenticated admin view, so the
  "Invalid presenter PIN" toast is invisible on that screen (the PIN still
  correctly blocks the grid). Will wire the container there with Phase 2.

## Unreleased
### Security
- Removed empty/global password login; per-account demo credentials only.
- Auth flags no longer trusted from localStorage.
- Role switching restricted to demo mode.
- Expanded RBAC permission map.

### Fixed
- Commission accounting (earned vs paid vs pending).
- Financial reporting (deal volume vs collected revenue).
- Delete guards for referenced sellers/buyers/dealers.
- Build cssMinify switched to esbuild.
- Navbar navigation missing between 640-1024px, link overflow at 1024-1200px, and drawer a11y (Escape, scroll-lock, backdrop, aria) — rewritten: inline nav only at xl, drawer below xl, constant header height per breakpoint.

### Added
- Public property serializer (toPublicProperty).
- userHasPermission helper.
- Documentation: AUDIT_REPORT, BUG_FIX_REPORT, SECURITY_AUDIT, DATABASE_SCHEMA, FIREBASE_SETUP, DEPLOYMENT, TEST_REPORT.

## Principal Audit Pass
- Implemented sidebar permission filtering, transaction lifecycle guards, plot locator badge, expanded code splitting, demo banner, media validation, field visibility, and public data separation.

## Premium UI/UX Pass (Phase 1-6)

### Visual & Motion
- Fixed silently missing CSS keyframes (animate-fadeIn / slideIn / subtleZoom) and added full premium motion system with prefers-reduced-motion support.
- Hero: word-by-word staggered headline, rAF parallax background, scroll-fade content, trust badge row (avatars + 4.9 rating), scroll-down indicator, LCP image fetchPriority=high.
- Animated count-up statistics (hero stats strip) via IntersectionObserver, no runtime deps.
- Page transitions (CSS page-enter keyed by view), scroll-aware navbar (densify on scroll + animated active underline), mobile drawer with backdrop blur.
- Toast: slide-in + auto-dismiss progress bar (synced to 4.5s timer) + type icons + aria-live.

### Components Added
- Lightbox (keyboard nav, video slot, thumbnail strip) wired into PropertyDetailPage + ProjectDetailPage.
- EmptyState standard empty states: property search, projects listing (public + back to search), admin inquiries, admin transactions.
- FavoriteHeart wishlist toggle persisted in localStorage (taisartownhub_v2_favorites) on property cards.
- Reveal scroll-triggered reveal wrappers across Featured properties/projects grids, Services cards, About core-values/team.

### Cards
- Property cards: per-sq-yd price reference, Hot Deal / Sold status badges, lazy+async images, wishlist heart.
- Project cards: h-full reveal wrappers, "View Project" hover CTA.

### Admin
- Overview KPI: quarter trend chip. Transactions & Inquiries get premium empty states.
- Admin overview verified rendered with DEMO MODE banner.

### Performance & A11y & SEO
- Removed need for 'motion' runtime; all animations CSS/IO-based. Main bundle 89.98KB gzip (was 106KB), total ~103KB under 800KB budget.
- Skip-to-content link, focus-visible rings, aria labels on icon buttons, aria-live toasts.
- Per-view document title + meta description (App.tsx), canonical/OG/Twitter tags + JSON-LD RealEstateAgent schema (index.html).
- All listing/detail images lazily loaded + decoding=async.

### Verified
- lint: 0 errors; tests: 10/10; build success; browser smoke test (home, properties, property detail lightbox keyboard nav, projects, admin login+overview) with 0 console errors.
- Navbar verified at 375 / 768 / 1180 / 1440 px (multi-iframe responsive probe): inline nav only >=1280px, drawer + backdrop + Escape close + body scroll-lock below xl, active-link aria-current, constant header height 64/80px, admin icon-only vs labeled states, zero console errors.
